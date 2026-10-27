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
 * Subscription service - Core subscription lifecycle logic.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription_service {
    /** Active subscription status. */
    const STATUS_ACTIVE = 'active';

    /** Trial subscription status. */
    const STATUS_TRIAL = 'trial';

    /** Grace-period subscription status. */
    const STATUS_GRACE = 'grace';

    /** Suspended subscription status. */
    const STATUS_SUSPENDED = 'suspended';

    /** Cancelled subscription status. */
    const STATUS_CANCELLED = 'cancelled';

    /** Expired subscription status. */
    const STATUS_EXPIRED = 'expired';

    /**
     * Check if user has an active subscription.
     *
     * @param int $userid User ID.
     * @return bool True if user has active subscription.
     */
    public static function has_active_subscription(int $userid): bool {
        global $DB;

        $activestates = [self::STATUS_ACTIVE, self::STATUS_TRIAL, self::STATUS_GRACE];
        [$insql, $params] = $DB->get_in_or_equal($activestates, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $params['now'] = time();

        return $DB->record_exists_select(
            'local_moderncommerce_user_subscriptions',
            "userid = :userid AND status $insql AND end_date > :now",
            $params
        );
    }

    /**
     * Activate a new subscription for a user.
     *
     * @param int $userid User ID.
     * @param int $planid Plan ID.
     * @param int|null $orderid Order ID from Modern Commerce.
     * @param bool|null $starttrial Force trial (true), force active (false), or auto when null
     *                              (trial when the plan offers one). Paid activations pass false.
     * @return int Subscription ID.
     */
    public static function activate(int $userid, int $planid, ?int $orderid = null, ?bool $starttrial = null): int {
        global $DB;

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $planid], '*', MUST_EXIST);
        $now = time();

        // Calculate duration based on billing cycle.
        $duration = self::get_cycle_duration($plan->billing_cycle);
        $enddate = $now + $duration;

        // Check for existing active subscription.
        $existing = self::get_active_subscription($userid);
        if ($existing) {
            // Renew or upgrade existing subscription.
            return self::renew($existing->id, $planid, $orderid);
        }

        // Determine initial status. Null = auto (trial when the plan offers one).
        $wanttrial = ($starttrial === null) ? ((int) $plan->trial_days > 0) : ($starttrial && (int) $plan->trial_days > 0);
        $status = self::STATUS_ACTIVE;
        $trialenddate = null;
        if ($wanttrial) {
            $status = self::STATUS_TRIAL;
            $trialenddate = $now + ($plan->trial_days * DAYSECS);
        }

        // Create new subscription.
        $subscription = new \stdClass();
        $subscription->userid = $userid;
        $subscription->planid = $planid;
        $subscription->orderid = $orderid;
        $subscription->status = $status;
        $subscription->start_date = $now;
        $subscription->end_date = $enddate;
        $subscription->trial_end_date = $trialenddate;
        $subscription->grace_end_date = null;
        $subscription->auto_renew = 0;
        $subscription->renewal_count = 0;
        $subscription->timecreated = $now;
        $subscription->timemodified = $now;

        $subscriptionid = $DB->insert_record('local_moderncommerce_user_subscriptions', $subscription);

        // Grant access to courses/categories/bundles.
        access_service::grant_plan_access($userid, $planid, $subscriptionid);

        // Log history.
        self::log_history($subscriptionid, $userid, 'created', null, $planid, null, $enddate, $plan->price, $orderid);

        // Trigger event.
        $event = \local_moderncommerce\event\subscription_created::create([
            'objectid' => $subscriptionid,
            'context' => \context_system::instance(),
            'relateduserid' => $userid,
            'other' => ['planid' => $planid],
        ]);
        $event->trigger();

        return $subscriptionid;
    }

    /**
     * Renew an existing subscription.
     *
     * @param int $subscriptionid Subscription ID.
     * @param int|null $newplanid New plan ID (for upgrade/downgrade).
     * @param int|null $orderid Order ID.
     * @return int Subscription ID.
     */
    public static function renew(int $subscriptionid, ?int $newplanid = null, ?int $orderid = null): int {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $oldplanid = $subscription->planid;
        $oldenddate = $subscription->end_date;

        $planid = $newplanid ?? $subscription->planid;
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $planid], '*', MUST_EXIST);

        $now = time();
        $duration = self::get_cycle_duration($plan->billing_cycle);

        // Extend from current end date or now, whichever is later.
        $basedate = max($subscription->end_date, $now);
        $newenddate = $basedate + $duration;

        // Update subscription.
        $subscription->planid = $planid;
        $subscription->status = self::STATUS_ACTIVE;
        $subscription->end_date = $newenddate;
        $subscription->grace_end_date = null;
        $subscription->renewal_count++;
        $subscription->timemodified = $now;
        if ($orderid) {
            $subscription->orderid = $orderid;
        }

        // Handle plan change - skip trial and update last_plan_change timestamp.
        if ($newplanid && $newplanid !== $oldplanid) {
            // Skip trial on plan changes - go straight to active status.
            $subscription->status = self::STATUS_ACTIVE;
            $subscription->trial_end_date = null;
            // Update last_plan_change for cooldown tracking.
            $subscription->last_plan_change = $now;
            // Clear any pending plan change since we're applying a change now.
            $subscription->pending_planid = null;
            $subscription->pending_change_date = null;
        }

        $DB->update_record('local_moderncommerce_user_subscriptions', $subscription);

        // Handle plan change access.
        if ($newplanid && $newplanid !== $oldplanid) {
            access_service::revoke_plan_access($subscription->userid, $oldplanid);
            access_service::grant_plan_access($subscription->userid, $planid, $subscriptionid);
        }

        // Access rows carry their own expiry, stamped from the end date at the
        // time they were granted. Without this the rows keep the term that just
        // elapsed, and a paying renewed subscriber is refused until the access
        // sync task next runs.
        $DB->set_field(
            'local_moderncommerce_subscription_access',
            'expires_at',
            $newenddate,
            ['subscriptionid' => $subscriptionid]
        );

        // Log history.
        $action = ($newplanid && $newplanid !== $oldplanid) ? 'upgraded' : 'renewed';
        self::log_history(
            $subscriptionid,
            $subscription->userid,
            $action,
            $oldplanid,
            $planid,
            $oldenddate,
            $newenddate,
            $plan->price,
            $orderid
        );

        // Trigger event.
        $event = \local_moderncommerce\event\subscription_renewed::create([
            'objectid' => $subscriptionid,
            'context' => \context_system::instance(),
            'relateduserid' => $subscription->userid,
            'other' => ['planid' => $planid, 'new_end_date' => $newenddate],
        ]);
        $event->trigger();

        // Optional: send renewal digest email.
        notification_service::send_renewal_digest(
            $subscription->userid,
            $subscriptionid
        );

        return $subscriptionid;
    }

    /**
     * Move subscription to grace period.
     *
     * @param int $subscriptionid Subscription ID.
     * @return bool Success.
     */
    public static function enter_grace_period(int $subscriptionid): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);

        $now = time();
        $gracedays = $plan->grace_period_days ?: get_config('local_moderncommerce', 'default_grace_days') ?: 7;

        $subscription->status = self::STATUS_GRACE;
        $subscription->grace_end_date = $now + ($gracedays * DAYSECS);
        $subscription->timemodified = $now;

        $DB->update_record('local_moderncommerce_user_subscriptions', $subscription);

        // Log history.
        self::log_history($subscriptionid, $subscription->userid, 'grace_started');

        return true;
    }

    /**
     * Suspend a subscription (after grace period).
     *
     * @param int $subscriptionid Subscription ID.
     * @return bool Success.
     */
    public static function suspend(int $subscriptionid): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);

        $subscription->status = self::STATUS_SUSPENDED;
        $subscription->timemodified = time();

        $DB->update_record('local_moderncommerce_user_subscriptions', $subscription);

        // Revoke access.
        access_service::revoke_plan_access($subscription->userid, $subscription->planid);

        // Log history.
        self::log_history($subscriptionid, $subscription->userid, 'suspended');

        // Trigger event.
        $event = \local_moderncommerce\event\subscription_expired::create([
            'objectid' => $subscriptionid,
            'context' => \context_system::instance(),
            'relateduserid' => $subscription->userid,
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Cancel a subscription.
     *
     * @param int $subscriptionid Subscription ID.
     * @param string $reason Cancellation reason.
     * @return bool Success.
     */
    public static function cancel(int $subscriptionid, string $reason = ''): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $now = time();

        $subscription->status = self::STATUS_CANCELLED;
        $subscription->cancellation_reason = $reason;
        $subscription->cancelled_at = $now;
        $subscription->auto_renew = 0;
        $subscription->timemodified = $now;

        $DB->update_record('local_moderncommerce_user_subscriptions', $subscription);

        // A cancelled subscription no longer entitles the holder to anything, so
        // pull the enrolments now rather than waiting up to six hours for the
        // access sync task to notice.
        access_service::revoke_plan_access($subscription->userid, $subscription->planid);

        // Log history.
        self::log_history($subscriptionid, $subscription->userid, 'cancelled', null, null, null, null, null, null, $reason);

        // Trigger event.
        $event = \local_moderncommerce\event\subscription_cancelled::create([
            'objectid' => $subscriptionid,
            'context' => \context_system::instance(),
            'relateduserid' => $subscription->userid,
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Reactivate a suspended or cancelled subscription.
     *
     * @param int $subscriptionid Subscription ID.
     * @param int|null $orderid New order ID.
     * @return bool Success.
     */
    public static function reactivate(int $subscriptionid, ?int $orderid = null): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);

        $now = time();
        $duration = self::get_cycle_duration($plan->billing_cycle);

        $subscription->status = self::STATUS_ACTIVE;
        $subscription->start_date = $now;
        $subscription->end_date = $now + $duration;
        $subscription->grace_end_date = null;
        $subscription->cancellation_reason = null;
        $subscription->cancelled_at = null;
        $subscription->timemodified = $now;
        if ($orderid) {
            $subscription->orderid = $orderid;
        }

        $DB->update_record('local_moderncommerce_user_subscriptions', $subscription);

        // Grant access.
        access_service::grant_plan_access($subscription->userid, $subscription->planid, $subscriptionid);

        // Log history.
        self::log_history(
            $subscriptionid,
            $subscription->userid,
            'reactivated',
            null,
            $subscription->planid,
            null,
            $subscription->end_date,
            $plan->price,
            $orderid
        );

        return true;
    }

    /**
     * Get active subscription for user.
     *
     * @param int $userid User ID.
     * @return object|null Subscription record or null.
     */
    public static function get_active_subscription(int $userid): ?object {
        global $DB;

        $activestates = [self::STATUS_ACTIVE, self::STATUS_TRIAL, self::STATUS_GRACE];
        [$insql, $params] = $DB->get_in_or_equal($activestates, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;

        $sql = "SELECT s.*, p.name as plan_name, p.billing_cycle, p.price, p.currency
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE s.userid = :userid AND s.status $insql
                ORDER BY s.end_date DESC";

        $records = $DB->get_records_sql($sql, $params, 0, 1);
        return !empty($records) ? reset($records) : null;
    }

    /**
     * Get cycle duration in seconds.
     *
     * @param string $cycle Billing cycle (monthly|yearly).
     * @return int Duration in seconds.
     */
    public static function get_cycle_duration(string $cycle): int {
        return ($cycle === 'yearly') ? YEARSECS : (30 * DAYSECS);
    }

    /**
     * Log subscription history.
     */
    public static function log_history(
        int $subscriptionid,
        int $userid,
        string $action,
        ?int $oldplanid = null,
        ?int $newplanid = null,
        ?int $oldenddate = null,
        ?int $newenddate = null,
        ?float $amountpaid = null,
        ?int $orderid = null,
        ?string $notes = null
    ): int {
        global $DB, $USER;

        $history = new \stdClass();
        $history->subscriptionid = $subscriptionid;
        $history->userid = $userid;
        $history->action = $action;
        $history->old_planid = $oldplanid;
        $history->new_planid = $newplanid;
        $history->old_end_date = $oldenddate;
        $history->new_end_date = $newenddate;
        $history->amount_paid = $amountpaid;
        $history->orderid = $orderid;
        $history->notes = $notes;
        $history->timecreated = time();
        $history->createdby = $USER->id ?? 0;

        return $DB->insert_record('local_moderncommerce_subscription_history', $history);
    }
}
