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

namespace local_moderncommerce\subscription\task;

use local_moderncommerce\subscription\services\access_service;

/**
 * Task to sync subscription access with enrollments.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_access extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:syncaccess', 'local_moderncommerce');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('Syncing subscription access...');

        // Get all active subscriptions.
        $sql = "SELECT s.id, s.userid, s.planid, s.status
                FROM {local_moderncommerce_user_subscriptions} s
                WHERE s.status IN ('active', 'trial', 'grace')";

        $subscriptions = $DB->get_records_sql($sql);

        mtrace("Found " . count($subscriptions) . " active subscriptions to sync.");

        $synced = 0;
        $errors = 0;

        foreach ($subscriptions as $subscription) {
            try {
                access_service::sync_subscription_access($subscription->id);
                $synced++;
            } catch (\Exception $e) {
                mtrace("Error syncing subscription {$subscription->id}: " . $e->getMessage());
                $errors++;
            }
        }

        // Also check for orphaned access records.
        $this->cleanup_orphaned_access();

        mtrace("Sync complete. Synced: {$synced}, Errors: {$errors}");
    }

    /**
     * Remove access records for inactive subscriptions.
     */
    private function cleanup_orphaned_access(): void {
        global $DB;

        mtrace('Cleaning up orphaned access records...');

        // Find access records where subscription is no longer active.
        $sql = "SELECT sa.id, sa.userid, sa.courseid
                FROM {local_moderncommerce_subscription_access} sa
                LEFT JOIN {local_moderncommerce_user_subscriptions} s ON s.id = sa.subscriptionid
                WHERE s.id IS NULL
                   OR s.status NOT IN ('active', 'trial', 'grace')";

        $orphaned = $DB->get_records_sql($sql);

        mtrace("Found " . count($orphaned) . " orphaned access records.");

        foreach ($orphaned as $access) {
            // Check if user has other active access to this course.
            $hasotheractive = $DB->record_exists_sql(
                "SELECT 1 FROM {local_moderncommerce_subscription_access} sa
                 JOIN {local_moderncommerce_user_subscriptions} s ON s.id = sa.subscriptionid
                 WHERE sa.userid = :userid
                   AND sa.courseid = :courseid
                   AND sa.id != :accessid
                   AND s.status IN ('active', 'trial', 'grace')",
                [
                    'userid' => $access->userid,
                    'courseid' => $access->courseid,
                    'accessid' => $access->id,
                ]
            );

            if (!$hasotheractive) {
                // Suspend enrollment.
                $this->suspend_enrollment($access->userid, $access->courseid);
            }

            // Delete orphaned record.
            $DB->delete_records('local_moderncommerce_subscription_access', ['id' => $access->id]);
        }
    }

    /**
     * Suspend user enrollment in a course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     */
    private function suspend_enrollment(int $userid, int $courseid): void {
        global $DB;

        // Get manual enrol instance.
        $instance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'manual',
        ]);

        if (!$instance) {
            return;
        }

        $enrol = enrol_get_plugin('manual');

        // Get user enrollment.
        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $userid,
        ]);

        if ($ue) {
            $enrol->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            mtrace("Suspended enrollment for user {$userid} in course {$courseid}");
        }
    }
}
