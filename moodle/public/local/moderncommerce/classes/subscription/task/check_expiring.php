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

use local_moderncommerce\subscription\api\subscription_api;

/**
 * Task to check for expiring subscriptions and send renewal reminders.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_expiring extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:checkexpiring', 'local_moderncommerce');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting subscription expiration check...');

        // Get reminder intervals from config.
        $reminderdays = get_config('local_moderncommerce', 'reminder_days');
        if (empty($reminderdays)) {
            $reminderdays = '7,3,1'; // Default: 7 days, 3 days, 1 day.
        }

        $intervals = array_map('intval', explode(',', $reminderdays));

        foreach ($intervals as $days) {
            $this->process_reminder_interval($days);
        }

        mtrace('Subscription expiration check complete.');
    }

    /**
     * Process a single reminder interval.
     *
     * @param int $days Days until expiration.
     */
    private function process_reminder_interval(int $days): void {
        global $DB;

        mtrace("Checking for subscriptions expiring in {$days} days...");

        $now = time();
        $targetstart = strtotime("+{$days} days midnight");
        $targetend = $targetstart + 86400; // 24 hours window.

        // Get subscriptions expiring in this window that haven't received this reminder.
        $sql = "SELECT s.*, u.firstname, u.lastname, u.email, p.name AS plan_name, p.billing_cycle, p.price, p.currency
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {user} u ON u.id = s.userid
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                LEFT JOIN {local_moderncommerce_subscription_reminders} r
                       ON r.subscriptionid = s.id AND r.reminder_type = :remindertype
                WHERE s.status IN ('active', 'trial')
                  AND s.end_date >= :targetstart
                  AND s.end_date < :targetend
                  AND s.auto_renew = 1
                  AND r.id IS NULL";

        $subscriptions = $DB->get_records_sql($sql, [
            'remindertype' => 'expiring_' . $days,
            'targetstart' => $targetstart,
            'targetend' => $targetend,
        ]);

        mtrace("Found " . count($subscriptions) . " subscriptions to remind.");

        foreach ($subscriptions as $subscription) {
            $this->send_reminder($subscription, $days);
        }
    }

    /**
     * Send renewal reminder email.
     *
     * @param object $subscription Subscription with user info.
     * @param int $days Days until expiration.
     */
    private function send_reminder(object $subscription, int $days): void {
        global $DB;

        mtrace("Sending {$days}-day reminder to user {$subscription->userid} for subscription {$subscription->id}");

        // Send via notification service.
        try {
            \local_moderncommerce\subscription\services\notification_service::send_expiring_reminder(
                $subscription->userid,
                $subscription->id,
                $days
            );
        } catch (\Exception $e) {
            mtrace("Failed to send email: " . $e->getMessage());
        }

        // Record that reminder was sent.
        $reminder = new \stdClass();
        $reminder->subscriptionid = $subscription->id;
        $reminder->reminder_type = 'expiring_' . $days;
        $reminder->sent_at = time();
        $reminder->email_sent = 1;
        $DB->insert_record('local_moderncommerce_subscription_reminders', $reminder);
    }
}
