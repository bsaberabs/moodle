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

/**
 * Order validation service to prevent duplicate purchases.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;


/**
 * Service class for validating orders before payment processing.
 */
class order_validation_service {
    /**
     * Validate an order for payment.
     * Checks if the user already has access to any items in the order.
     *
     * @param int $orderid Order ID to validate.
     * @param int $userid User ID.
     * @return array ['valid' => bool, 'errors' => array, 'cancel_order' => bool]
     */
    public static function validate_order_for_payment(int $orderid, int $userid): array {
        global $DB;

        $errors = [];
        $cancelorder = false;

        $items = \local_moderncommerce\api\order_api::get_order_items($orderid);
        if (empty($items)) {
            return [
                'valid' => false,
                'errors' => ['Order has no items.'],
                'cancel_order' => true,
            ];
        }

        foreach ($items as $item) {
            switch ($item->itemtype) {
                case 'course':
                    if ($item->courseid && access_service::user_has_course_access($userid, (int)$item->courseid)) {
                        $coursename = $DB->get_field('course', 'fullname', ['id' => $item->courseid]);
                        $errors[] = get_string('coursealreadyaccessible_name', 'local_moderncommerce', $coursename);
                        $cancelorder = true;
                    }
                    break;

                case 'bundle':
                case 'program':
                    $productid = (int)($item->bundleid ?: ($item->productid ?? 0));
                    if ($productid > 0 && access_service::user_has_product_purchase_access($userid, $productid)) {
                        $bundlename = $item->bundlename ?? $item->coursename ?? get_string('unknownbundle', 'local_moderncommerce');
                        $errors[] = get_string('productalreadyaccessible_name', 'local_moderncommerce', $bundlename);
                        $cancelorder = true;
                    }

                    break;

                case 'subscription':
                    // Check if user already has an active subscription to this plan.

                    if (self::has_active_plan_subscription($userid, $item->planid ?? 0)) {
                        $planname = self::get_subscription_plan_name((int)($item->planid ?? 0));
                        $errors[] = get_string('alreadysubscribed_plan', 'local_moderncommerce', $planname);
                        $cancelorder = true;
                    }

                    break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'cancel_order' => $cancelorder,
        ];
    }

    /**
     * Check if user is enrolled in a specific course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool True if enrolled.
     */
    public static function is_user_enrolled_in_course(int $userid, int $courseid): bool {

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        return is_enrolled($context, $userid, '', true);
    }

    /**
     * Check if user is enrolled in ALL courses of a bundle.
     *
     * @param int $userid User ID.
     * @param int $bundleid Bundle ID.
     * @return bool True if enrolled in all courses.
     */
    public static function is_user_enrolled_in_all_bundle_courses(int $userid, int $bundleid): bool {
        $bundlecourses = \local_moderncommerce\api\bundle_api::get_courses($bundleid);
        if (empty($bundlecourses)) {
            return false;
        }

        foreach ($bundlecourses as $bc) {
            if (!self::is_user_enrolled_in_course($userid, $bc->courseid)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has an active subscription to a specific plan.
     *
     * @param int $userid User ID.
     * @param int $planid Plan ID.
     * @return bool True if has active subscription.
     */
    public static function has_active_plan_subscription(int $userid, int $planid): bool {
        global $DB;

        if (empty($planid)) {
            return false;
        }

        if (
            !self::subscription_plugin_available()
                || !self::table_exists('local_moderncommerce_user_subscriptions')
        ) {
            return false;
        }

        $activestates = ['active', 'trial', 'grace'];
        [$insql, $params] = $DB->get_in_or_equal($activestates, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $params['planid'] = $planid;
        $params['now'] = time();

        return $DB->record_exists_select(
            'local_moderncommerce_user_subscriptions',
            "userid = :userid AND planid = :planid AND status $insql AND end_date > :now",
            $params
        );
    }

    /**
     * Get a subscription plan name when the optional subscription add-on is available.
     *
     * @param int $planid Plan ID.
     * @return string Plan name.
     */
    private static function get_subscription_plan_name(int $planid): string {

        global $DB;
        if (
            $planid > 0 && self::subscription_plugin_available()
                && self::table_exists('local_moderncommerce_subscription_plans')
        ) {
            $planname = $DB->get_field('local_moderncommerce_subscription_plans', 'name', ['id' => $planid]);
            if ($planname !== false && $planname !== '') {
                return (string)$planname;
            }
        }

        return get_string('subscription', 'local_moderncommerce');
    }

    /**
     * Check whether the optional subscription plugin is installed and upgraded.
     *
     * @return bool
     */
    private static function subscription_plugin_available(): bool {

        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('local_moderncommerce');
        return $plugininfo !== null && $plugininfo->is_installed_and_upgraded();
    }

    /**
     * Check whether a table exists.
     *
     * @param string $table Table name without prefix.
     * @return bool
     */
    private static function table_exists(string $table): bool {

        global $DB;
        return $DB->get_manager()->table_exists(new \xmldb_table($table));
    }
    /**
     * Cancel an order due to duplicate purchase attempt.
     *
     * @param int $orderid Order ID.
     * @param array $reasons Reasons for cancellation.
     * @return bool Success.
     */
    public static function cancel_duplicate_order(int $orderid, array $reasons): bool {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        if (!$order) {
            return false;
        }

        // Only cancel pending orders.
        if ($order->status !== 'pending') {
            return false;
        }

        $order->status = 'cancelled';
        $order->notes = get_string('ordercancel_duplicate', 'local_moderncommerce') . "\n" . implode("\n", $reasons);
        $order->timemodified = time();

        $DB->update_record('local_moderncommerce_orders', $order);

        // Log the cancellation.
        \local_moderncommerce\api\order_api::log_audit(
            $order->userid,
            'order_auto_cancelled',
            'order',
            $orderid,
            ['status' => 'pending'],
            ['status' => 'cancelled', 'reason' => 'duplicate_purchase']
        );

        return true;
    }
}
