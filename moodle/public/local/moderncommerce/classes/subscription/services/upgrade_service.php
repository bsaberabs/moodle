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

use local_moderncommerce\subscription\api\plan_api;

/**
 * Upgrade service - Handles subscription plan changes with proration.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Modern Commerce
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_service {
    /** Days in a monthly billing cycle. */
    const MONTHLY_DAYS = 30;

    /** Days in a yearly billing cycle. */
    const YEARLY_DAYS = 365;

    /** Default cooldown between plan changes */
    const DEFAULT_COOLDOWN_DAYS = 30;

    /**
     * Get the number of days in a billing cycle.
     *
     * @param string $cycle Billing cycle (monthly/yearly).
     * @return int Days in cycle.
     */
    public static function get_cycle_days(string $cycle): int {
        return $cycle === 'yearly' ? self::YEARLY_DAYS : self::MONTHLY_DAYS;
    }

    /**
     * Calculate daily rate for a plan.
     *
     * @param object $plan Plan object.
     * @return float Daily rate.
     */
    public static function get_daily_rate(object $plan): float {
        $price = plan_api::get_effective_price($plan);
        $days = self::get_cycle_days($plan->billing_cycle);
        return $price / $days;
    }

    /**
     * Calculate remaining value of current subscription.
     *
     * @param object $subscription Subscription object.
     * @return float Remaining value.
     */
    public static function calculate_remaining_value(object $subscription): float {
        global $DB;

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);
        $dailyrate = self::get_daily_rate($plan);

        $now = time();
        if ($subscription->end_date <= $now) {
            return 0;
        }

        $daysremaining = ceil(($subscription->end_date - $now) / DAYSECS);
        return round($dailyrate * $daysremaining, 4);
    }

    /**
     * Get days remaining on subscription.
     *
     * @param object $subscription Subscription object.
     * @return int Days remaining.
     */
    public static function get_days_remaining(object $subscription): int {
        $now = time();
        if ($subscription->end_date <= $now) {
            return 0;
        }
        return ceil(($subscription->end_date - $now) / DAYSECS);
    }

    /**
     * Get the normalized monthly value for a plan.
     *
     * Converts yearly plans to equivalent monthly value for comparison.
     * This allows fair comparison between monthly and yearly plans.
     *
     * @param object $plan Plan object.
     * @return float Monthly equivalent value.
     */
    public static function get_monthly_value(object $plan): float {
        $price = plan_api::get_effective_price($plan);
        if ($plan->billing_cycle === 'yearly') {
            return $price / 12;
        }
        return $price;
    }

    /**
     * Check if this is a lateral move (same tier, same cycle).
     *
     * A lateral move means switching to a plan with the same normalized monthly value
     * AND the same billing cycle.
     *
     * @param object $currentplan Current plan.
     * @param object $newplan New plan.
     * @return bool True if lateral move.
     */
    public static function is_lateral_move(object $currentplan, object $newplan): bool {
        // Must be same billing cycle.
        if ($currentplan->billing_cycle !== $newplan->billing_cycle) {
            return false;
        }

        $currentprice = plan_api::get_effective_price($currentplan);
        $newprice = plan_api::get_effective_price($newplan);

        return abs($currentprice - $newprice) < 0.01; // Same price within floating point tolerance.
    }

    /**
     * Check if this is an upgrade (higher value or monthly→yearly).
     *
     * An upgrade is defined as:
     * 1. Moving from monthly to yearly of ANY tier
     *    (yearly commitment is always considered an upgrade)
     * 2. Moving to a plan with higher normalized monthly value (same cycle)
     *
     * @param object $currentplan Current plan.
     * @param object $newplan New plan.
     * @return bool True if upgrade.
     */
    public static function is_upgrade(object $currentplan, object $newplan): bool {
        // Case 1: Moving from monthly to yearly is ALWAYS an upgrade.
        // (yearly commitment shows higher intent, regardless of per-month price).
        if ($currentplan->billing_cycle === 'monthly' && $newplan->billing_cycle === 'yearly') {
            return true;
        }

        // Case 2: Same billing cycle - compare monthly values.
        $currentmonthly = self::get_monthly_value($currentplan);
        $newmonthly = self::get_monthly_value($newplan);

        if ($newmonthly > $currentmonthly + 0.01) {
            return true;
        }

        return false;
    }

    /**
     * Check if this is a downgrade (lower value or yearly→monthly).
     *
     * A downgrade is defined as:
     * 1. Moving from yearly to monthly at any tier
     *    (breaking yearly commitment is considered a downgrade)
     * 2. Moving to a plan with lower normalized monthly value (same cycle)
     *
     * @param object $currentplan Current plan.
     * @param object $newplan New plan.
     * @return bool True if downgrade.
     */
    public static function is_downgrade(object $currentplan, object $newplan): bool {
        // Case 1: Moving from yearly to monthly is ALWAYS a downgrade.
        // (breaking yearly commitment).
        if ($currentplan->billing_cycle === 'yearly' && $newplan->billing_cycle === 'monthly') {
            return true;
        }

        // Case 2: Monthly to yearly is NEVER a downgrade.
        if ($currentplan->billing_cycle === 'monthly' && $newplan->billing_cycle === 'yearly') {
            return false;
        }

        // Case 3: Same billing cycle - compare monthly values.
        $currentmonthly = self::get_monthly_value($currentplan);
        $newmonthly = self::get_monthly_value($newplan);

        if ($newmonthly < $currentmonthly - 0.01) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can change plan (cooldown check).
     *
     * @param int $subscriptionid Subscription ID.
     * @return array Result with 'allowed' key and optional 'wait_days', 'next_change_date'.
     */
    public static function can_change_plan(int $subscriptionid): array {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
        if (!$subscription) {
            return ['allowed' => false, 'reason' => 'invalid'];
        }

        $cooldown = get_config('local_moderncommerce', 'upgrade_cooldown_days');
        if ($cooldown === false) {
            $cooldown = self::DEFAULT_COOLDOWN_DAYS;
        }

        // 0 = unlimited changes allowed
        if ($cooldown == 0) {
            return ['allowed' => true];
        }

        if (!empty($subscription->last_plan_change)) {
            $dayssince = (time() - $subscription->last_plan_change) / DAYSECS;
            if ($dayssince < $cooldown) {
                $waitdays = ceil($cooldown - $dayssince);
                return [
                    'allowed' => false,
                    'reason' => 'cooldown',
                    'wait_days' => $waitdays,
                    'next_change_date' => $subscription->last_plan_change + ($cooldown * DAYSECS),
                ];
            }
        }

        return ['allowed' => true];
    }

    /**
     * Calculate upgrade cost with proration.
     *
     * @param object $subscription Subscription object.
     * @param int $newplanid New plan ID.
     * @return array Upgrade details.
     */
    public static function calculate_upgrade_cost(object $subscription, int $newplanid): array {
        global $DB;

        $currentplan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);
        $newplan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $newplanid], '*', MUST_EXIST);

        $newprice = plan_api::get_effective_price($newplan);
        $credit = self::calculate_remaining_value($subscription);
        $accountcredit = $subscription->account_credit ?? 0;
        $totalcredit = $credit + $accountcredit;

        // Check for lateral move (same price, same cycle).
        $allowlateral = get_config('local_moderncommerce', 'allow_lateral_moves');
        if ($allowlateral !== false && $allowlateral && self::is_lateral_move($currentplan, $newplan)) {
            return [
                'credit' => $credit,
                'account_credit' => $accountcredit,
                'newprice' => $newprice,
                'upgradecost' => 0,
                'stored_credit' => 0,
                'credit_applied' => 0,
                'islateral' => true,
                'isupgrade' => false,
                'isdowngrade' => false,
                'requires_payment' => false,
                'immediate' => true,
            ];
        }

        $isupgrade = self::is_upgrade($currentplan, $newplan);
        $isdowngrade = self::is_downgrade($currentplan, $newplan);

        if ($isupgrade || self::is_lateral_move($currentplan, $newplan)) {
            // Upgrade: immediate, pay difference.
            $upgradecost = max(0, $newprice - $totalcredit);
            $creditapplied = min($totalcredit, $newprice);

            return [
                'credit' => $credit,
                'account_credit' => $accountcredit,
                'newprice' => $newprice,
                'upgradecost' => round($upgradecost, 2),
                'stored_credit' => 0,
                'credit_applied' => $creditapplied,
                'islateral' => false,
                'isupgrade' => true,
                'isdowngrade' => false,
                'requires_payment' => ($upgradecost > 0),
                'immediate' => true,
            ];
        } else {
            // Downgrade: scheduled at end of period, store excess credit.
            $storedowngradecredit = get_config('local_moderncommerce', 'store_downgrade_credit');
            if ($storedowngradecredit === false) {
                $storedowngradecredit = true;
            }

            $storedcredit = 0;
            if ($storedowngradecredit && $credit > $newprice) {
                $storedcredit = round($credit - $newprice, 4);
            }

            return [
                'credit' => $credit,
                'account_credit' => $accountcredit,
                'newprice' => $newprice,
                'upgradecost' => 0,
                'stored_credit' => $storedcredit,
                'credit_applied' => 0,
                'islateral' => false,
                'isupgrade' => false,
                'isdowngrade' => true,
                'requires_payment' => false,
                'immediate' => false,
                'effective_date' => $subscription->end_date,
            ];
        }
    }

    /**
     * Get full preview of plan change.
     *
     * @param int $subscriptionid Subscription ID.
     * @param int $newplanid New plan ID.
     * @return array Preview data.
     */
    public static function preview_upgrade(int $subscriptionid, int $newplanid): array {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $currentplan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);
        $newplan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $newplanid], '*', MUST_EXIST);

        $cost = self::calculate_upgrade_cost($subscription, $newplanid);
        $daysremaining = self::get_days_remaining($subscription);

        return array_merge($cost, [
            'subscription_id' => $subscriptionid,
            'current_plan' => [
                'id' => $currentplan->id,
                'name' => $currentplan->name,
                'price' => plan_api::get_effective_price($currentplan),
                'billing_cycle' => $currentplan->billing_cycle,
            ],
            'new_plan' => [
                'id' => $newplan->id,
                'name' => $newplan->name,
                'price' => plan_api::get_effective_price($newplan),
                'billing_cycle' => $newplan->billing_cycle,
            ],
            'days_remaining' => $daysremaining,
            'end_date' => $subscription->end_date,
        ]);
    }

    /**
     * Get available plans for upgrade/downgrade.
     *
     * @param int $userid User ID.
     * @return array Plans with upgrade info.
     */
    public static function get_available_plans(int $userid): array {
        global $DB;

        // Get user's current subscription.
        $subscription = subscription_service::get_active_subscription($userid);
        if (!$subscription) {
            return [];
        }

        $currentplan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid]);
        if (!$currentplan) {
            return [];
        }

        // Get all active plans except current.
        $plans = $DB->get_records('local_moderncommerce_subscription_plans', ['status' => 'active'], 'sortorder ASC');
        $result = [];

        foreach ($plans as $plan) {
            if ($plan->id == $currentplan->id) {
                continue; // Skip current plan.
            }

            $cost = self::calculate_upgrade_cost($subscription, $plan->id);

            $result[] = [
                'plan' => $plan,
                'price' => plan_api::get_effective_price($plan),
                'isupgrade' => $cost['isupgrade'],
                'isdowngrade' => $cost['isdowngrade'],
                'islateral' => $cost['islateral'],
                'upgradecost' => $cost['upgradecost'],
                'requires_payment' => $cost['requires_payment'],
                'immediate' => $cost['immediate'],
                'effective_date' => $cost['effective_date'] ?? null,
                'stored_credit' => $cost['stored_credit'],
            ];
        }

        return $result;
    }

    /**
     * Schedule a downgrade for end of billing period.
     *
     * @param int $subscriptionid Subscription ID.
     * @param int $newplanid New plan ID.
     * @return bool Success.
     */
    public static function schedule_downgrade(int $subscriptionid, int $newplanid): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);

        $subscription->pending_planid = $newplanid;
        $subscription->pending_change_date = $subscription->end_date;
        $subscription->timemodified = time();

        $DB->update_record('local_moderncommerce_user_subscriptions', $subscription);

        // Log history.
        $newplan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $newplanid]);
        self::log_scheduled_change($subscriptionid, $subscription->userid, $subscription->planid, $newplanid);

        return true;
    }

    /**
     * Cancel a scheduled plan change.
     *
     * @param int $subscriptionid Subscription ID.
     * @return bool Success.
     */
    public static function cancel_scheduled_change(int $subscriptionid): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);

        if (empty($subscription->pending_planid)) {
            return false;
        }

        $oldpending = $subscription->pending_planid;
        $subscription->pending_planid = null;
        $subscription->pending_change_date = null;
        $subscription->timemodified = time();

        $DB->update_record('local_moderncommerce_user_subscriptions', $subscription);

        // Log cancellation.
        self::log_cancelled_change($subscriptionid, $subscription->userid, $oldpending);

        return true;
    }

    /**
     * Process pending plan changes (called by cron).
     */
    public static function process_pending_changes(): void {
        global $DB;

        $now = time();

        // Find subscriptions with pending changes that are due.
        $sql = "SELECT * FROM {local_moderncommerce_user_subscriptions}
                WHERE pending_planid IS NOT NULL
                AND pending_change_date IS NOT NULL
                AND pending_change_date <= :now
                AND status IN ('active', 'trial', 'grace')";

        $subscriptions = $DB->get_records_sql($sql, ['now' => $now]);

        foreach ($subscriptions as $subscription) {
            self::apply_pending_change($subscription);
        }
    }

    /**
     * Apply a pending plan change.
     *
     * @param object $subscription Subscription with pending change.
     */
    protected static function apply_pending_change(object $subscription): void {
        global $DB;

        $oldplanid = $subscription->planid;
        $newplanid = $subscription->pending_planid;

        // Calculate any stored credit.
        $cost = self::calculate_upgrade_cost($subscription, $newplanid);
        if ($cost['stored_credit'] > 0) {
            self::add_account_credit($subscription->id, $cost['stored_credit']);
        }

        // Use renew to handle the plan change.
        subscription_service::renew($subscription->id, $newplanid);

        // Clear pending fields.
        $DB->set_field('local_moderncommerce_user_subscriptions', 'pending_planid', null, ['id' => $subscription->id]);
        $DB->set_field('local_moderncommerce_user_subscriptions', 'pending_change_date', null, ['id' => $subscription->id]);
    }

    /**
     * Apply account credit to a payment.
     *
     * @param int $subscriptionid Subscription ID.
     * @param float $amount Amount to apply.
     * @return float Amount actually applied.
     */
    public static function apply_account_credit(int $subscriptionid, float $amount): float {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
        if (!$subscription) {
            return 0;
        }

        $available = min($subscription->account_credit ?? 0, $amount);

        if ($available > 0) {
            $newcredit = ($subscription->account_credit ?? 0) - $available;
            $DB->set_field('local_moderncommerce_user_subscriptions', 'account_credit', $newcredit, ['id' => $subscriptionid]);
        }

        return $available;
    }

    /**
     * Add account credit.
     *
     * @param int $subscriptionid Subscription ID.
     * @param float $amount Amount to add.
     */
    public static function add_account_credit(int $subscriptionid, float $amount): void {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
        if (!$subscription) {
            return;
        }

        $newcredit = ($subscription->account_credit ?? 0) + $amount;
        $DB->set_field('local_moderncommerce_user_subscriptions', 'account_credit', $newcredit, ['id' => $subscriptionid]);
    }

    /**
     * Process immediate upgrade (with payment or lateral).
     *
     * @param int $subscriptionid Subscription ID.
     * @param int $newplanid New plan ID.
     * @param int|null $orderid Order ID if payment was made.
     * @return bool Success.
     */
    public static function process_immediate_upgrade(int $subscriptionid, int $newplanid, ?int $orderid = null): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $cost = self::calculate_upgrade_cost($subscription, $newplanid);

        // Apply account credit if used.
        if ($cost['account_credit'] > 0 && $cost['credit_applied'] > 0) {
            $creditused = min($cost['account_credit'], $cost['credit_applied']);
            self::apply_account_credit($subscriptionid, $creditused);
        }

        // Use subscription service to handle the plan change.
        subscription_service::renew($subscriptionid, $newplanid, $orderid);

        // Update last_plan_change timestamp.
        $DB->set_field('local_moderncommerce_user_subscriptions', 'last_plan_change', time(), ['id' => $subscriptionid]);

        return true;
    }

    /**
     * Log scheduled change to history.
     */
    protected static function log_scheduled_change(int $subscriptionid, int $userid, int $oldplanid, int $newplanid): void {
        global $DB;

        $history = new \stdClass();
        $history->subscriptionid = $subscriptionid;
        $history->userid = $userid;
        $history->action = 'downgrade_scheduled';
        $history->old_planid = $oldplanid;
        $history->new_planid = $newplanid;
        $history->notes = get_string('downgradescheduled', 'local_moderncommerce', userdate(time()));
        $history->timecreated = time();
        $history->createdby = $userid;

        $DB->insert_record('local_moderncommerce_subscription_history', $history);
    }

    /**
     * Log cancelled change to history.
     */
    protected static function log_cancelled_change(int $subscriptionid, int $userid, int $cancelledplanid): void {
        global $DB;

        $history = new \stdClass();
        $history->subscriptionid = $subscriptionid;
        $history->userid = $userid;
        $history->action = 'downgrade_cancelled';
        $history->old_planid = $cancelledplanid;
        $history->notes = get_string('canceldowngrade', 'local_moderncommerce');
        $history->timecreated = time();
        $history->createdby = $userid;

        $DB->insert_record('local_moderncommerce_subscription_history', $history);
    }
}
