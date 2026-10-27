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

namespace local_moderncommerce\subscription\api;

use local_moderncommerce\subscription\services\subscription_service;

/**
 * Subscription API - CRUD operations for user subscriptions.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription_api {
    /** @var array Static cache for subscriptions */
    private static $cache = [];

    /**
     * Get a subscription by ID.
     *
     * @param int $subscriptionid Subscription ID.
     * @param bool $usecache Use cache.
     * @return object|false Subscription record or false.
     */
    public static function get(int $subscriptionid, bool $usecache = true) {
        global $DB;

        if ($usecache && isset(self::$cache['sub_' . $subscriptionid])) {
            return self::$cache['sub_' . $subscriptionid];
        }

        $sql = "SELECT s.*, p.name AS plan_name, p.billing_cycle, p.price, p.currency
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE s.id = :id";

        $subscription = $DB->get_record_sql($sql, ['id' => $subscriptionid]);

        if ($subscription) {
            self::$cache['sub_' . $subscriptionid] = $subscription;
        }

        return $subscription;
    }

    /**
     * Get active subscription for a user.
     *
     * @param int $userid User ID.
     * @return object|false Active subscription or false.
     */
    public static function get_active_for_user(int $userid) {
        global $DB;

        $sql = "SELECT s.*, p.name AS plan_name, p.billing_cycle, p.price, p.currency
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE s.userid = :userid
                  AND s.status IN ('active', 'trial', 'grace')
                ORDER BY s.start_date DESC";

        return $DB->get_record_sql($sql, ['userid' => $userid], IGNORE_MULTIPLE);
    }

    /**
     * Get all subscriptions for a user.
     *
     * @param int $userid User ID.
     * @return array Array of subscription records.
     */
    public static function get_all_for_user(int $userid): array {
        global $DB;

        $sql = "SELECT s.*, p.name AS plan_name, p.billing_cycle, p.price, p.currency
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE s.userid = :userid
                ORDER BY s.start_date DESC";

        return $DB->get_records_sql($sql, ['userid' => $userid]);
    }

    /**
     * Get subscribers for a plan.
     *
     * @param int $planid Plan ID.
     * @param string|null $status Filter by status.
     * @param int $limitfrom Start from.
     * @param int $limitnum Number to return.
     * @return array Array of subscription records with user info.
     */
    public static function get_plan_subscribers(int $planid, string $status = null, int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;

        $params = ['planid' => $planid];
        $where = 's.planid = :planid';

        if ($status) {
            $where .= ' AND s.status = :status';
            $params['status'] = $status;
        }

        $sql = "SELECT s.*, u.firstname, u.lastname, u.email, p.name AS plan_name
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {user} u ON u.id = s.userid
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE {$where}
                ORDER BY s.start_date DESC";

        return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    }

    /**
     * Create a new subscription.
     *
     * @param int $userid User ID.
     * @param int $planid Plan ID.
     * @param int|null $paymentid Payment/Order ID.
     * @param bool $starttrial Start with trial period.
     * @return int Subscription ID.
     * @throws \moodle_exception
     */
    public static function create(int $userid, int $planid, ?int $paymentid = null, bool $starttrial = false): int {
        global $DB;

        $plan = plan_api::get($planid);
        if (!$plan || (string) $plan->status !== 'active') {
            throw new \moodle_exception('error:planinvalid', 'local_moderncommerce');
        }

        $existing = $DB->get_record_select(
            'local_moderncommerce_user_subscriptions',
            "userid = :userid AND planid = :planid AND status IN ('active', 'trial', 'grace')",
            ['userid' => $userid, 'planid' => $planid]
        );

        if ($existing) {
            throw new \moodle_exception('error:alreadysubscribed', 'local_moderncommerce');
        }

        if (!plan_api::has_available_seat($plan, $userid)) {
            throw new \moodle_exception('error:planfull', 'local_moderncommerce');
        }

        // Delegate to the canonical implementation so both entry points share one
        // set of trial, end-date, and auto-renew semantics. This wrapper keeps the
        // pre-flight validation its callers expect.
        $subscriptionid = subscription_service::activate($userid, $planid, $paymentid, $starttrial);

        self::clear_user_cache($userid);

        return (int) $subscriptionid;
    }

    /**
     * Get total active subscriptions count.
     *
     * @return int Active subscription count.
     */
    public static function get_total_active_count(): int {
        global $DB;

        return $DB->count_records_select(
            'local_moderncommerce_user_subscriptions',
            "status IN ('active', 'trial', 'grace')"
        );
    }

    /**
     * Get subscriptions expiring soon.
     *
     * @param int $days Days until expiration.
     * @return array Array of subscriptions expiring.
     */
    public static function get_expiring_soon(int $days = 7): array {
        global $DB;

        $now = time();
        $future = $now + ($days * 86400);

        $sql = "SELECT s.*, u.firstname, u.lastname, u.email, p.name AS plan_name, p.billing_cycle
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {user} u ON u.id = s.userid
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE s.status IN ('active', 'trial')
                  AND s.end_date > :now
                  AND s.end_date <= :future
                  AND s.auto_renew = 1
                ORDER BY s.end_date ASC";

        return $DB->get_records_sql($sql, ['now' => $now, 'future' => $future]);
    }

    /**
     * Get expired subscriptions needing processing.
     *
     * @return array Array of expired subscriptions.
     */
    public static function get_expired(): array {
        global $DB;

        $now = time();

        $sql = "SELECT s.*, p.grace_period_days
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE s.status IN ('active', 'trial')
                  AND s.end_date <= :now";

        return $DB->get_records_sql($sql, ['now' => $now]);
    }

    /**
     * Get subscriptions with expired grace period.
     *
     * @return array Array of subscriptions past grace.
     */
    public static function get_grace_expired(): array {
        global $DB;

        $now = time();

        $sql = "SELECT s.*
                FROM {local_moderncommerce_user_subscriptions} s
                WHERE s.status = 'grace'
                  AND s.grace_end_date <= :now";

        return $DB->get_records_sql($sql, ['now' => $now]);
    }

    /**
     * Update auto-renewal preference.
     *
     * @param int $subscriptionid Subscription ID.
     * @param bool $autorenew Auto-renew preference.
     * @return bool Success.
     */
    public static function set_auto_renew(int $subscriptionid, bool $autorenew): bool {
        global $DB;

        $subscription = self::get($subscriptionid, false);
        if (!$subscription) {
            return false;
        }

        $DB->set_field('local_moderncommerce_user_subscriptions', 'auto_renew', $autorenew ? 1 : 0, ['id' => $subscriptionid]);

        // Log history. The signature is (subscriptionid, userid, action, ...),
        // so the note goes in the trailing notes argument.
        $message = $autorenew ? 'Auto-renewal enabled' : 'Auto-renewal disabled';
        subscription_service::log_history(
            $subscriptionid,
            (int) $subscription->userid,
            'autorenew_changed',
            null,
            null,
            null,
            null,
            null,
            null,
            $message
        );

        // Clear cache.
        self::clear_user_cache($subscription->userid);

        return true;
    }

    /**
     * Get subscription statistics.
     *
     * @return object Statistics object.
     */
    public static function get_statistics(): object {
        global $DB;

        $stats = new \stdClass();

        // Total by status.
        $stats->active = $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'active']);
        $stats->trial = $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'trial']);
        $stats->grace = $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'grace']);
        $stats->cancelled = $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'cancelled']);
        $stats->expired = $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'expired']);

        // Total active (active + trial + grace).
        $stats->total_active = $stats->active + $stats->trial + $stats->grace;

        // MRR calculation (monthly recurring revenue).
        $sql = "SELECT SUM(
                    CASE WHEN p.billing_cycle = 'monthly' THEN p.price
                         WHEN p.billing_cycle = 'yearly' THEN p.price / 12
                         ELSE 0 END
                ) AS mrr
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE s.status = 'active' AND s.auto_renew = 1";

        $stats->mrr = $DB->get_field_sql($sql) ?: 0;
        $stats->arr = $stats->mrr * 12;

        // Subscriptions expiring in 7 days.
        $stats->expiring_soon = count(self::get_expiring_soon(7));

        // New this month.
        $monthstart = strtotime('first day of this month midnight');
        $stats->new_this_month = $DB->count_records_select(
            'local_moderncommerce_user_subscriptions',
            'timecreated >= :monthstart',
            ['monthstart' => $monthstart]
        );

        return $stats;
    }

    /**
     * Clear user cache.
     *
     * @param int $userid User ID.
     */
    private static function clear_user_cache(int $userid): void {
        foreach (self::$cache as $key => $value) {
            if (strpos($key, 'user_' . $userid) !== false) {
                unset(self::$cache[$key]);
            }
        }
    }
}
