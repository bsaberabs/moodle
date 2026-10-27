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

namespace local_moderncommerce\subscription\services;

/**
 * Access service - Handles course enrollment based on subscription plans.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_service {
    /** Course access type. */
    const ACCESS_COURSE = 'course';

    /** Category access type. */
    const ACCESS_CATEGORY = 'category';

    /** Bundle access type. */
    const ACCESS_BUNDLE = 'bundle';

    /**
     * Grant plan access to a user.
     *
     * @param int $userid User ID.
     * @param int $planid Plan ID.
     * @param int $subscriptionid Subscription ID.
     * @return bool Success.
     */
    public static function grant_plan_access(int $userid, int $planid, int $subscriptionid): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
        $expiresat = $subscription ? $subscription->end_date : null;

        // Get all access rules for this plan.
        $rules = $DB->get_records('local_moderncommerce_subscription_access_rules', ['planid' => $planid]);

        $courseids = [];

        foreach ($rules as $rule) {
            switch ($rule->access_type) {
                case self::ACCESS_COURSE:
                    $courseids[] = $rule->target_id;
                    break;

                case self::ACCESS_CATEGORY:
                    // Get all courses in this category.
                    $catcourses = $DB->get_fieldset_select(
                        'course',
                        'id',
                        'category = :catid AND visible = 1 AND id != 1',
                        ['catid' => $rule->target_id]
                    );
                    $courseids = array_merge($courseids, $catcourses);
                    break;

                case self::ACCESS_BUNDLE:
                    // Get all courses included in this bundle/program (canonical product_courses).
                    $bundlecourses = $DB->get_fieldset_select(
                        'local_moderncommerce_product_courses',
                        'courseid',
                        'productid = :productid AND relationtype = :relationtype',
                        ['productid' => $rule->target_id, 'relationtype' => 'included']
                    );
                    $courseids = array_merge($courseids, $bundlecourses);
                    break;
            }
        }

        // Remove duplicates.
        $courseids = array_unique($courseids);

        // Enroll user in each course and track access.
        foreach ($courseids as $courseid) {
            self::enroll_user_in_course($userid, $courseid);
            self::track_access($userid, $subscriptionid, $courseid, $expiresat);
        }

        // Send consolidated activation summary email (optional, must never break activation).
        try {
            \local_moderncommerce\subscription\services\notification_service::send_activation_summary(
                $userid,
                $subscriptionid,
                $courseids
            );
        } catch (\Throwable $e) {
            debugging('Subscription activation summary email failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return true;
    }

    /**
     * Revoke plan access from a user.
     *
     * @param int $userid User ID.
     * @param int $planid Plan ID.
     * @return bool Success.
     */
    public static function revoke_plan_access(int $userid, int $planid): bool {
        global $DB;

        // Get user's subscription for this plan.
        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'userid' => $userid,
            'planid' => $planid,
        ]);

        if (!$subscription) {
            return false;
        }

        // Get tracked access records.
        $accessrecords = $DB->get_records('local_moderncommerce_subscription_access', [
            'userid' => $userid,
            'subscriptionid' => $subscription->id,
        ]);

        foreach ($accessrecords as $access) {
            // Check if user has access via another subscription.
            $othersubscriptions = $DB->get_records_sql(
                "SELECT sa.id FROM {local_moderncommerce_subscription_access} sa
                 JOIN {local_moderncommerce_user_subscriptions} s ON s.id = sa.subscriptionid
                 WHERE sa.userid = :userid
                   AND sa.courseid = :courseid
                   AND sa.subscriptionid != :subid
                   AND s.status IN ('active', 'trial', 'grace')",
                [
                    'userid' => $userid,
                    'courseid' => $access->courseid,
                    'subid' => $subscription->id,
                ]
            );

            // Only unenroll if no other active subscription grants access.
            if (empty($othersubscriptions)) {
                self::suspend_user_enrollment($userid, $access->courseid);
            }

            // Remove access record.
            $DB->delete_records('local_moderncommerce_subscription_access', ['id' => $access->id]);
        }

        return true;
    }

    /**
     * Check if user has subscription access to a course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool True if user has access.
     */
    public static function has_course_access(int $userid, int $courseid): bool {
        global $DB;

        $sql = "SELECT sa.id
                FROM {local_moderncommerce_subscription_access} sa
                JOIN {local_moderncommerce_user_subscriptions} s ON s.id = sa.subscriptionid
                WHERE sa.userid = :userid
                  AND sa.courseid = :courseid
                  AND s.status IN ('active', 'trial', 'grace')
                  AND (sa.expires_at IS NULL OR sa.expires_at > :now)";

        return $DB->record_exists_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'now' => time(),
        ]);
    }

    /**
     * Get all courses a user has access to via subscription.
     *
     * @param int $userid User ID.
     * @return array Array of course IDs.
     */
    public static function get_user_accessible_courses(int $userid): array {
        global $DB;

        $sql = "SELECT DISTINCT sa.courseid
                FROM {local_moderncommerce_subscription_access} sa
                JOIN {local_moderncommerce_user_subscriptions} s ON s.id = sa.subscriptionid
                WHERE sa.userid = :userid
                  AND s.status IN ('active', 'trial', 'grace')
                  AND (sa.expires_at IS NULL OR sa.expires_at > :now)";

        return $DB->get_fieldset_sql($sql, [
            'userid' => $userid,
            'now' => time(),
        ]);
    }

    /**
     * Enroll user in a course using manual enrollment.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool Success.
     */
    private static function enroll_user_in_course(int $userid, int $courseid): bool {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return false;
        }

        // Get manual enrol instance.
        $enrol = enrol_get_plugin('manual');
        $instances = enrol_get_instances($courseid, true);
        $manualinstance = null;

        foreach ($instances as $instance) {
            if ($instance->enrol === 'manual') {
                $manualinstance = $instance;
                break;
            }
        }

        if (!$manualinstance) {
            // Create manual enrol instance if not exists.
            $manualinstance = $DB->get_record('enrol', [
                'courseid' => $courseid,
                'enrol' => 'manual',
            ]);

            if (!$manualinstance) {
                $enrolid = $enrol->add_instance($course);
                $manualinstance = $DB->get_record('enrol', ['id' => $enrolid]);
            }
        }

        // Check if already enrolled.
        if (!is_enrolled(\context_course::instance($courseid), $userid)) {
            // Enroll with student role.
            $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
            $enrol->enrol_user($manualinstance, $userid, $studentroleid);
        }

        return true;
    }

    /**
     * Suspend user enrollment in a course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool Success.
     */
    private static function suspend_user_enrollment(int $userid, int $courseid): bool {
        global $DB;

        // Get manual enrol instance.
        $instance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'manual',
        ]);

        if (!$instance) {
            return false;
        }

        $enrol = enrol_get_plugin('manual');

        // Get user enrollment.
        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $userid,
        ]);

        if ($ue) {
            // Suspend enrollment instead of unenrolling (preserves progress).
            $enrol->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
        }

        return true;
    }

    /**
     * Track user access grant.
     *
     * @param int $userid User ID.
     * @param int $subscriptionid Subscription ID.
     * @param int $courseid Course ID.
     * @param int|null $expiresat Expiration timestamp.
     * @return int Access record ID.
     */
    public static function track_access(int $userid, int $subscriptionid, int $courseid, ?int $expiresat = null): int {
        global $DB;

        // Check for existing record.
        $existing = $DB->get_record('local_moderncommerce_subscription_access', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        if ($existing) {
            // Update with later expiration.
            if (!$expiresat || ($existing->expires_at && $expiresat > $existing->expires_at)) {
                $existing->subscriptionid = $subscriptionid;
                $existing->expires_at = $expiresat;
                $DB->update_record('local_moderncommerce_subscription_access', $existing);
            }
            return $existing->id;
        }

        // Create new record.
        $access = new \stdClass();
        $access->userid = $userid;
        $access->subscriptionid = $subscriptionid;
        $access->courseid = $courseid;
        $access->granted_at = time();
        $access->expires_at = $expiresat;

        return $DB->insert_record('local_moderncommerce_subscription_access', $access);
    }

    /**
     * Sync access for a user's subscription.
     *
     * @param int $subscriptionid Subscription ID.
     * @return bool Success.
     */
    public static function sync_subscription_access(int $subscriptionid): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
        if (!$subscription) {
            return false;
        }

        $activestates = [
            subscription_service::STATUS_ACTIVE,
            subscription_service::STATUS_TRIAL,
            subscription_service::STATUS_GRACE,
        ];

        if (in_array($subscription->status, $activestates)) {
            // Ensure access is granted.
            return self::grant_plan_access($subscription->userid, $subscription->planid, $subscriptionid);
        } else {
            // Ensure access is revoked.
            return self::revoke_plan_access($subscription->userid, $subscription->planid);
        }
    }
}
