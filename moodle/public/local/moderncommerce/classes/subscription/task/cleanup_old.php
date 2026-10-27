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

/**
 * Task to cleanup old subscription data.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_old extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:cleanupold', 'local_moderncommerce');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace('Starting subscription data cleanup...');

        // Get retention period from config (default 2 years).
        $retentiondays = get_config('local_moderncommerce', 'history_retention_days');
        if (empty($retentiondays)) {
            $retentiondays = 730; // 2 years.
        }

        $cutoff = time() - ($retentiondays * 86400);

        // Cleanup old history records.
        $this->cleanup_history($cutoff);

        // Cleanup old reminder records.
        $this->cleanup_reminders($cutoff);

        // Cleanup old cancelled/expired subscriptions.
        $this->cleanup_old_subscriptions($cutoff);

        mtrace('Cleanup complete.');
    }

    /**
     * Cleanup old history records.
     *
     * @param int $cutoff Cutoff timestamp.
     */
    private function cleanup_history(int $cutoff): void {
        global $DB;

        $count = $DB->count_records_select(
            'local_moderncommerce_subscription_history',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );

        if ($count > 0) {
            $DB->delete_records_select(
                'local_moderncommerce_subscription_history',
                'timecreated < :cutoff',
                ['cutoff' => $cutoff]
            );
            mtrace("Deleted {$count} old history records.");
        }
    }

    /**
     * Cleanup old reminder records.
     *
     * @param int $cutoff Cutoff timestamp.
     */
    private function cleanup_reminders(int $cutoff): void {
        global $DB;

        $count = $DB->count_records_select(
            'local_moderncommerce_subscription_reminders',
            'sent_at < :cutoff',
            ['cutoff' => $cutoff]
        );

        if ($count > 0) {
            $DB->delete_records_select(
                'local_moderncommerce_subscription_reminders',
                'sent_at < :cutoff',
                ['cutoff' => $cutoff]
            );
            mtrace("Deleted {$count} old reminder records.");
        }
    }

    /**
     * Cleanup old cancelled/expired subscriptions.
     *
     * @param int $cutoff Cutoff timestamp.
     */
    private function cleanup_old_subscriptions(int $cutoff): void {
        global $DB;

        // Only cleanup if enabled.
        $cleanupsubscriptions = get_config('local_moderncommerce', 'cleanup_old_subscriptions');
        if (!$cleanupsubscriptions) {
            return;
        }

        // Get old cancelled/expired subscriptions.
        $sql = "SELECT id FROM {local_moderncommerce_user_subscriptions}
                WHERE status IN ('cancelled', 'expired')
                  AND timemodified < :cutoff";

        $subscriptions = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);

        if (empty($subscriptions)) {
            return;
        }

        mtrace("Found " . count($subscriptions) . " old subscriptions to archive.");

        foreach ($subscriptions as $subscription) {
            // Delete related access records.
            $DB->delete_records('local_moderncommerce_subscription_access', ['subscriptionid' => $subscription->id]);

            // Delete related reminders.
            $DB->delete_records('local_moderncommerce_subscription_reminders', ['subscriptionid' => $subscription->id]);

            // Delete related history (keep minimal audit trail).
            // Note: We may want to keep history in an archive table instead.
            // For now, just leave history as it has its own cleanup.
        }

        // We don't delete the subscription records themselves to maintain audit trail.
        // Instead, we could move them to an archive table if needed.
        mtrace("Cleaned up data for " . count($subscriptions) . " old subscriptions.");
    }
}
