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

namespace local_moderncommerce\subscription\observer;

/**
 * Course observer - Handles course-related events.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_observer {
    /**
     * Handle course completed event.
     *
     * Could be used for:
     * - Awarding completion badges for subscription users
     * - Tracking subscription value (courses completed during subscription)
     * - Analytics
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event) {
        global $DB;

        $data = $event->get_data();
        $userid = $data['relateduserid'];
        $courseid = $data['courseid'];

        // Check if this completion was via subscription access.
        $hassubaccess = $DB->record_exists_sql(
            "SELECT 1 FROM {local_moderncommerce_subscription_access} sa
             JOIN {local_moderncommerce_user_subscriptions} s ON s.id = sa.subscriptionid
             WHERE sa.userid = :userid
               AND sa.courseid = :courseid
               AND s.status IN ('active', 'trial', 'grace')",
            ['userid' => $userid, 'courseid' => $courseid]
        );

        if ($hassubaccess) {
            // Log for analytics/reporting.
            // This could be expanded to track ROI, engagement metrics, etc.
            self::log_subscription_completion($userid, $courseid);
        }
    }

    /**
     * Log subscription-based course completion.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     */
    private static function log_subscription_completion(int $userid, int $courseid): void {
        // Placeholder for analytics integration.
        // Could store in a dedicated analytics table or send to external service.
    }
}
