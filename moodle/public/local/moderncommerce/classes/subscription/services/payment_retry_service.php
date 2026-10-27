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
 * Payment retry service - Unified retry tracking across all payment gateways.
 *
 * Gateways handle their own retry logic (Stripe retries automatically, Paystack has
 * configured retry rules, etc.). This service tracks failures locally and suspends
 * subscriptions after max retries are exceeded.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_retry_service {
    /** Default max retry attempts before suspension. */
    const DEFAULT_MAX_RETRIES = 3;

    /** Default days between retry attempts. */
    const DEFAULT_RETRY_INTERVAL_DAYS = 3;

    /**
     * Track a payment failure.
     *
     * Increments the failure count and suspends the subscription if max retries exceeded.
     *
     * @param int $subscriptionid Subscription ID.
     * @param string $gateway Gateway name (stripe, paypal, paystack, flutterwave).
     * @param string $reason Failure reason.
     * @return int New failure count.
     */
    public static function track_failure(int $subscriptionid, string $gateway, string $reason = ''): int {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
        if (!$subscription) {
            return 0;
        }

        $now = time();
        $failedcount = ($subscription->payment_failed_count ?? 0) + 1;

        // Update subscription.
        $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
            'id' => $subscription->id,
            'payment_failed_count' => $failedcount,
            'last_payment_attempt' => $now,
            'timemodified' => $now,
        ]);

        // Log the failure.
        $DB->insert_record('local_moderncommerce_subscription_log', [
            'subscriptionid' => $subscription->id,
            'userid' => $subscription->userid,
            'planid' => $subscription->planid,
            'action' => 'payment_failed',
            'details' => json_encode([
                'gateway' => $gateway,
                'attempt' => $failedcount,
                'max_retries' => self::get_max_retries(),
                'reason' => $reason,
            ]),
            'timecreated' => $now,
        ]);

        // Suspend if max retries exceeded.
        if ($failedcount >= self::get_max_retries()) {
            subscription_service::suspend($subscription->id);
        }

        return $failedcount;
    }

    /**
     * Track a successful payment (reset failure count).
     *
     * @param int $subscriptionid Subscription ID.
     * @return void
     */
    public static function track_success(int $subscriptionid): void {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
        if (!$subscription) {
            return;
        }

        // Only update if there were previous failures.
        if (($subscription->payment_failed_count ?? 0) > 0) {
            $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
                'id' => $subscription->id,
                'payment_failed_count' => 0,
                'last_payment_attempt' => time(),
                'timemodified' => time(),
            ]);
        }
    }

    /**
     * Check if a subscription should be suspended based on failure count.
     *
     * @param object $subscription Subscription record.
     * @return bool True if should be suspended.
     */
    public static function should_suspend(object $subscription): bool {
        $failedcount = $subscription->payment_failed_count ?? 0;
        return $failedcount >= self::get_max_retries();
    }

    /**
     * Get max retry attempts from config.
     *
     * @return int Max retries.
     */
    public static function get_max_retries(): int {
        $maxretries = get_config('local_moderncommerce', 'payment_max_retries');
        return !empty($maxretries) ? (int)$maxretries : self::DEFAULT_MAX_RETRIES;
    }

    /**
     * Get retry interval in days from config.
     *
     * @return int Days between retries.
     */
    public static function get_retry_interval_days(): int {
        $interval = get_config('local_moderncommerce', 'payment_retry_interval_days');
        return !empty($interval) ? (int)$interval : self::DEFAULT_RETRY_INTERVAL_DAYS;
    }

    /**
     * Get the next retry date for a subscription.
     *
     * @param object $subscription Subscription record.
     * @return int Unix timestamp of next retry.
     */
    public static function get_next_retry_date(object $subscription): int {
        $lastattempt = $subscription->last_payment_attempt ?? time();
        $intervaldays = self::get_retry_interval_days();
        return $lastattempt + ($intervaldays * DAYSECS);
    }

    /**
     * Get subscriptions due for payment retry.
     *
     * @return array Subscription records.
     */
    public static function get_subscriptions_due_for_retry(): array {
        global $DB;

        $maxretries = self::get_max_retries();
        $intervaldays = self::get_retry_interval_days();
        $retrythreshold = time() - ($intervaldays * DAYSECS);

        return $DB->get_records_select(
            'local_moderncommerce_user_subscriptions',
            "status IN ('active', 'grace')
             AND payment_failed_count > 0
             AND payment_failed_count < :maxretries
             AND (last_payment_attempt IS NULL OR last_payment_attempt < :threshold)",
            [
                'maxretries' => $maxretries,
                'threshold' => $retrythreshold,
            ]
        );
    }
}
