<?php
// This file is part of Moodle and is licensed under the
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_moderncommerce\observer;


use local_moderncommerce\api\bundle_api;
use local_moderncommerce\api\bundle_enrollment_api;
use local_moderncommerce\api\order_api;
use local_moderncommerce\services\enrolment_service;
/**
 * Enrollment observer
 *
 * Auto-enrolls users in courses when orders are paid
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrollment_observer {
    /**
     * Handle order paid event
     *
     * @param \local_moderncommerce\event\order_paid $event
     */
    public static function order_paid(\local_moderncommerce\event\order_paid $event) {
        global $DB;

        $orderid = $event->objectid;
        try {
            $order = order_api::get_order((int) $orderid);
            $items = order_api::get_order_items((int) $orderid);
        } catch (\Exception $e) {
            debugging('Order ' . $orderid . ' not found for enrollment', DEBUG_DEVELOPER);
            return;
        }

        // Send payment receipt email.
        try {
            $transaction = self::get_successful_payment_record($orderid);
            if ($transaction) {
                \local_moderncommerce\email_notifications::send_payment_receipt($order, $transaction, $items);
            }
        } catch (\Exception $e) {
            debugging('Failed to send payment receipt email: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Auto-enroll in purchased courses.
        $user = $DB->get_record('user', ['id' => $order->userid]);

        foreach ($items as $item) {
            // Handle bundle enrollment.
            if (!empty($item->bundleid)) {
                if (!empty($item->enrolled)) {
                    continue;
                }
                self::enroll_user_in_bundle($order->userid, $item->bundleid, $orderid, $user);
                continue;
            }
            // Handle individual course enrollment.
            if (empty($item->courseid)) {
                continue;
            }

            if (!empty($item->enrolled)) {
                continue;
            }
            // Verify course exists.
            $course = $DB->get_record('course', ['id' => $item->courseid]);
            if (!$course) {
                continue;
            }

            // Check if already enrolled.
            $context = \context_course::instance($course->id);
            if (is_enrolled($context, $order->userid)) {
                self::mark_item_enrolled(self::get_order_items_table(), [
                    'orderid' => $orderid, 'courseid' => $course->id,
                ]);
                continue;
            }

            // Enroll user.
            try {
                self::enroll_user($order->userid, $course->id, $orderid);
            } catch (\Exception $e) {
                // Log error but continue with other enrollments.
                debugging('Failed to enroll user ' . $order->userid . ' in course ' . $course->id . ': ' .
                    $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Enroll user in all courses within a bundle
     *
     * @param int $userid User ID
     * @param int $bundleid Bundle ID
     * @param int $orderid Order ID
     * @param object $user User record (for email)
     */
    protected static function enroll_user_in_bundle($userid, $bundleid, $orderid, $user) {
        global $DB;

        // Get bundle info.
        $bundle = bundle_api::get($bundleid);
        if (!$bundle) {
            debugging('Bundle ' . $bundleid . ' not found for enrollment', DEBUG_DEVELOPER);
            return;
        }
        // Get all courses in the bundle.
        $bundlecourses = bundle_api::get_courses($bundleid);

        if (empty($bundlecourses)) {
            debugging('No courses found in bundle ' . $bundleid, DEBUG_DEVELOPER);
            return;
        }
        if (!empty($bundle->isprogram)) {
            try {
                bundle_enrollment_api::enroll_user((int)$userid, (int)$bundleid, (int)$orderid);
                self::mark_item_enrolled(self::get_order_items_table(), [
                    'orderid' => $orderid, 'bundleid' => $bundleid,
                ]);
            } catch (\Throwable $exception) {
                debugging('Failed to start program ' . $bundleid . ': ' . $exception->getMessage(), DEBUG_DEVELOPER);
            }
            return;
        }
        $enrolledcount = 0;
        $processedcount = 0;
        foreach ($bundlecourses as $bundlecourse) {
            $course = $DB->get_record('course', ['id' => $bundlecourse->courseid]);
            if (!$course) {
                continue;
            }

            // Check if already enrolled.
            $context = \context_course::instance($course->id);
            if (is_enrolled($context, $userid)) {
                $processedcount++;
                continue;
            }

            try {
                $result = self::enroll_user($userid, $course->id, $orderid, $bundleid);
                $processedcount++;
                if (!empty($result->created)) {
                    $enrolledcount++;
                }
            } catch (\Exception $e) {
                debugging('Failed to enroll user ' . $userid . ' in bundle course ' . $course->id . ': ' .
                $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        if ($processedcount === count($bundlecourses)) {
            self::mark_item_enrolled(self::get_order_items_table(), [
                'orderid' => $orderid, 'bundleid' => $bundleid,
            ]);
        }
    }

    /**
     * Enroll user in course
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $orderid Order ID
     * @param int $bundleid Optional bundle ID if enrolling via bundle
     */
    protected static function enroll_user($userid, $courseid, $orderid, $bundleid = null) {

        global $DB;
        $result = enrolment_service::enrol_user_in_course($userid, $courseid, [
            'orderid' => $orderid, 'method' => $bundleid ? 'bundle' : 'purchase',
        ]);
        // Mark the order item as enrolled (only for individual courses, bundles marked separately).
        if (!$bundleid) {
            self::mark_item_enrolled(self::get_order_items_table(), [
                'orderid' => $orderid, 'courseid' => $courseid,
            ]);
        }

        // Send the enrolment confirmation (gated by enrollmentconfirmation_enabled inside the method).
        try {
            $user = $DB->get_record('user', ['id' => $userid]);
            $course = $DB->get_record('course', ['id' => $courseid]);
            $order = order_api::get_order((int) $orderid);
            if ($user && $course && $order) {
                \local_moderncommerce\email_notifications::send_enrollment_confirmation($user, $course, $order->ordernumber);
            }
        } catch (\Throwable $e) {
            debugging('Enrolment confirmation email failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $result;
    }

    /**
     * Resolve the installed order items table.
     *
     * @return string Table name.
     */
    private static function get_order_items_table(): string {

        return 'local_moderncommerce_order_items';
    }

    /**
     * Get the successful payment record for receipt emails.
     *
     * @param int $orderid Order ID.
     * @return object|false
     */
    private static function get_successful_payment_record(int $orderid) {

        global $DB;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists(new \xmldb_table('local_moderncommerce_payment_attempts'))) {
            return $DB->get_record(
                'local_moderncommerce_payment_attempts',
                ['orderid' => $orderid, 'status' => 'success'],
                '*',
                IGNORE_MULTIPLE
            );
        }

        if ($dbman->table_exists(new \xmldb_table('local_moderncommerce_transactions'))) {
            return $DB->get_record(
                'local_moderncommerce_transactions',
                ['orderid' => $orderid, 'status' => 'success'],
                '*',
                IGNORE_MULTIPLE
            );
        }

        return false;
    }

    /**
     * Mark an order item enrolled when the installed table supports that field.
     *
     * @param string $tablename Item table name.
     * @param array $conditions Update conditions.
     */
    private static function mark_item_enrolled(string $tablename, array $conditions): void {

        global $DB;
        $dbman = $DB->get_manager();
        $table = new \xmldb_table($tablename);
        if (!$dbman->field_exists($table, new \xmldb_field('enrolled'))) {
            return;
        }

        $DB->set_field($tablename, 'enrolled', 1, $conditions);
    }
}
