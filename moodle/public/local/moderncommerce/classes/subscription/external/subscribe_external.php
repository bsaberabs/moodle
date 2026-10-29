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
 * Buyer-facing subscription APIs: list plans, subscribe, and redeem a key.
 *
 * Consumed by the Modern Commerce public subscription page. Paid plans create a
 * Modern Commerce order and return a checkout URL; free/trial plans and key
 * redemptions activate the subscription immediately.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\subscription\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\subscription\api\feature_api;
use local_moderncommerce\subscription\api\plan_api;
use local_moderncommerce\subscription\api\subscription_api;
use local_moderncommerce\subscription\services\subscription_service;

/**
 * Buyer subscription webservice methods.
 */
class subscribe_external extends external_api {
    /**
     * Parameters for get_plans.
     *
     * @return external_function_parameters
     */
    public static function get_plans_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * List active plans for the public subscribe page plus the user's current subscription.
     *
     * @return array
     */
    public static function get_plans(): array {
        global $USER;

        self::require_subscribe_capability();

        $plans = array_values(array_map([self::class, 'format_plan'], plan_api::get_all()));

        return [
            'plans' => $plans,
            'currentsubscription' => self::format_current((int) $USER->id),
            'currency' => self::currency_config(),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_plans.
     *
     * @return external_single_structure
     */
    public static function get_plans_returns(): external_single_structure {
        return new external_single_structure([
            'plans' => new external_multiple_structure(self::plan_structure()),
            'currentsubscription' => self::current_structure(),
            'currency' => self::currency_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for subscribe.
     *
     * @return external_function_parameters
     */
    public static function subscribe_parameters(): external_function_parameters {
        return new external_function_parameters([
            'planid' => new external_value(PARAM_INT, 'Plan to subscribe to.', VALUE_REQUIRED),
            'starttrial' => new external_value(PARAM_BOOL, 'Start a free trial instead of paying.', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Subscribe the current user to a plan.
     *
     * Free/trial plans activate immediately; paid plans return a checkout URL.
     *
     * @param int $planid Plan ID.
     * @param bool $starttrial Whether to start a trial.
     * @return array
     */
    public static function subscribe(int $planid, bool $starttrial = false): array {
        global $USER;

        $params = self::validate_parameters(self::subscribe_parameters(), [
            'planid' => $planid,
            'starttrial' => $starttrial,
        ]);
        self::require_subscribe_capability();

        $userid = (int) $USER->id;
        $plan = plan_api::get((int) $params['planid'], false);
        if (!$plan || (string) $plan->status !== 'active') {
            throw new \moodle_exception('error:planinvalid', 'local_moderncommerce');
        }

        $current = subscription_api::get_active_for_user($userid);
        if ($current && (int) $current->planid === (int) $plan->id) {
            throw new \moodle_exception('error:alreadysubscribed', 'local_moderncommerce');
        }

        // A capped plan must not oversell; existing holders keep their seat.
        if (!plan_api::has_available_seat($plan, $userid)) {
            throw new \moodle_exception('error:planfull', 'local_moderncommerce');
        }

        $price = (float) plan_api::get_effective_price($plan);
        $wanttrial = !empty($params['starttrial']) && (int) $plan->trial_days > 0 && !$current;

        // Free plan or trial start: activate immediately, no payment.
        if ($wanttrial || $price <= 0) {
            $subscriptionid = subscription_service::activate($userid, (int) $plan->id, null, $wanttrial);
            return [
                'activated' => true,
                'checkouturl' => '',
                'message' => $wanttrial
                    ? get_string('trialstarted', 'local_moderncommerce')
                    : get_string('subscriptionactivated', 'local_moderncommerce'),
                'subscriptionid' => (int) $subscriptionid,
                'warnings' => [],
            ];
        }

        // Paid plan: create a pending order and hand off to Modern Commerce checkout.
        $planname = format_string($plan->name) . ' (' . self::cycle_label((string) $plan->billing_cycle) . ')';
        $order = \local_moderncommerce\api\order_api::create_subscription_order(
            $userid,
            (int) $plan->id,
            $price,
            $planname,
            '',
            ['action' => 'new']
        );
        $checkouturl = (new \moodle_url('/local/moderncommerce/checkout.php', ['orderid' => (int) $order->id]))->out(false);

        return [
            'activated' => false,
            'checkouturl' => $checkouturl,
            'message' => '',
            'subscriptionid' => 0,
            'warnings' => [],
        ];
    }

    /**
     * Return structure for subscribe.
     *
     * @return external_single_structure
     */
    public static function subscribe_returns(): external_single_structure {
        return new external_single_structure([
            'activated' => new external_value(PARAM_BOOL, 'Whether the subscription was activated immediately.'),
            'checkouturl' => new external_value(PARAM_RAW, 'Checkout URL for paid plans, or empty.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'subscriptionid' => new external_value(PARAM_INT, 'New subscription ID when activated, else 0.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for redeem_key.
     *
     * @return external_function_parameters
     */
    public static function redeem_key_parameters(): external_function_parameters {
        return new external_function_parameters([
            'keycode' => new external_value(PARAM_TEXT, 'Subscription key code.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Redeem a pre-paid subscription key to activate a plan.
     *
     * @param string $keycode Key code.
     * @return array
     */
    public static function redeem_key(string $keycode): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::redeem_key_parameters(), ['keycode' => $keycode]);
        self::require_subscribe_capability();

        $userid = (int) $USER->id;
        $code = trim($params['keycode']);
        if ($code === '') {
            throw new \moodle_exception('invalidsubscriptionkey', 'local_moderncommerce');
        }

        $key = $DB->get_record('local_moderncommerce_subscription_keys', ['keycode' => $code]);
        if (!$key) {
            throw new \moodle_exception('invalidsubscriptionkey', 'local_moderncommerce');
        }
        self::validate_key($key, $userid);

        $plan = plan_api::get((int) $key->planid, false);
        if (!$plan || (string) $plan->status !== 'active') {
            throw new \moodle_exception('invalidplan', 'local_moderncommerce');
        }

        $current = subscription_api::get_active_for_user($userid);
        if ($current && (int) $current->planid === (int) $plan->id) {
            throw new \moodle_exception('error:alreadysubscribed', 'local_moderncommerce');
        }

        // A capped plan must not oversell; existing holders keep their seat.
        if (!plan_api::has_available_seat($plan, $userid)) {
            throw new \moodle_exception('error:planfull', 'local_moderncommerce');
        }

        $transaction = $DB->start_delegated_transaction();
        try {
            $subscriptionid = subscription_service::activate($userid, (int) $plan->id, null, false);

            // Apply the key's duration override when set.
            if ((int) $key->duration_days > 0) {
                $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
                if ($subscription) {
                    $start = (int) ($subscription->start_date ?: time());
                    $DB->set_field(
                        'local_moderncommerce_user_subscriptions',
                        'end_date',
                        $start + ((int) $key->duration_days * DAYSECS),
                        ['id' => $subscriptionid]
                    );
                }
            }

            $DB->insert_record('local_moderncommerce_subscription_key_usage', (object) [
                'keyid' => (int) $key->id,
                'userid' => $userid,
                'subscriptionid' => (int) $subscriptionid,
                'orderid' => null,
                'ipaddress' => getremoteaddr(),
                'timecreated' => time(),
            ]);

            $DB->set_field('local_moderncommerce_subscription_keys', 'usedcount', (int) $key->usedcount + 1, ['id' => $key->id]);
            $DB->set_field('local_moderncommerce_subscription_keys', 'timemodified', time(), ['id' => $key->id]);

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }

        \local_moderncommerce\audit\audit_service::record('subscription_key_redeemed', 'subscription_key', (int) $key->id, [
            'actoruserid' => $userid,
            'subjectuserid' => $userid,
            'newdata' => [
                'keyid' => (int) $key->id,
                'keycode' => $key->keycode,
                'planid' => (int) $plan->id,
                'subscriptionid' => (int) $subscriptionid,
            ],
            'source' => 'redeem',
        ]);

        return [
            'activated' => true,
            'planname' => format_string($plan->name),
            'message' => get_string('subscriptionactivated', 'local_moderncommerce'),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for redeem_key.
     *
     * @return external_single_structure
     */
    public static function redeem_key_returns(): external_single_structure {
        return new external_single_structure([
            'activated' => new external_value(PARAM_BOOL, 'Whether the subscription was activated.'),
            'planname' => new external_value(PARAM_TEXT, 'Activated plan name.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Validate a subscription key for the given user, throwing on failure.
     *
     * @param \stdClass $key Key record.
     * @param int $userid User ID.
     */
    private static function validate_key(\stdClass $key, int $userid): void {
        global $DB;

        if ((string) $key->status !== 'active') {
            throw new \moodle_exception('keyinactive', 'local_moderncommerce');
        }

        $now = time();
        if (!empty($key->startdate) && $now < (int) $key->startdate) {
            throw new \moodle_exception('keynotstarted', 'local_moderncommerce');
        }
        if (!empty($key->expirydate) && $now > (int) $key->expirydate) {
            throw new \moodle_exception('keyexpired', 'local_moderncommerce');
        }

        $usedcount = (int) $DB->count_records('local_moderncommerce_subscription_key_usage', ['keyid' => $key->id]);
        if ((int) $key->maxuses > 0 && $usedcount >= (int) $key->maxuses) {
            throw new \moodle_exception('keymaxuses', 'local_moderncommerce');
        }

        $useruses = (int) $DB->count_records('local_moderncommerce_subscription_key_usage', [
            'keyid' => $key->id,
            'userid' => $userid,
        ]);
        if ((int) $key->maxusesperuser > 0 && $useruses >= (int) $key->maxusesperuser) {
            throw new \moodle_exception('keyalreadyused', 'local_moderncommerce');
        }

        if (!empty($key->requiredemail)) {
            global $USER;
            if (strcasecmp(trim($key->requiredemail), trim((string) $USER->email)) !== 0) {
                throw new \moodle_exception('keynotforyou', 'local_moderncommerce');
            }
        }

        if (!empty($key->userids)) {
            $allowed = json_decode($key->userids, true);
            if (is_array($allowed) && !empty($allowed) && !in_array($userid, array_map('intval', $allowed), true)) {
                throw new \moodle_exception('keynotforyou', 'local_moderncommerce');
            }
        }
    }

    /**
     * Format a plan for the public page.
     *
     * @param \stdClass $plan Plan record.
     * @return array
     */
    private static function format_plan(\stdClass $plan): array {
        $regular = (float) ($plan->price ?? 0);
        $effective = (float) plan_api::get_effective_price($plan);
        $haspromo = plan_api::has_active_promo($plan);

        $features = [];
        foreach (feature_api::get_plan_features((int) $plan->id) as $feature) {
            $features[] = [
                'name' => format_string($feature->name),
                'icon' => (string) ($feature->icon ?: 'check-circle'),
            ];
        }

        // Terms set by the plugin that bills the plan (Oriskill addition, hook plan_terms).
        $terms = new \local_moderncommerce\hook\plan_terms($plan);
        \core\di::get(\core\hook\manager::class)->dispatch($terms);
        $trialdays = (int) ($plan->trial_days ?? 0);
        $trialprice = $trialdays > 0 ? max(0.0, $terms->trialprice) : 0.0;

        return [
            'id' => (int) $plan->id,
            'name' => format_string($plan->name),
            'code' => (string) ($plan->code ?? ''),
            'description' => (string) ($plan->description ?? ''),
            'billingcycle' => (string) ($plan->billing_cycle ?? 'monthly'),
            'price' => $effective,
            'displayprice' => self::format_price($effective),
            'regularprice' => $regular,
            'displayregularprice' => self::format_price($regular),
            'haspromo' => (bool) $haspromo,
            'isfree' => $effective <= 0,
            'trialdays' => $trialdays,
            'trialprice' => $trialprice,
            'displaytrialprice' => self::format_price($trialprice),
            'payments' => max(0, $terms->payments),
            'maxseats' => (int) ($plan->max_seats ?? 0),
            'featured' => !empty($plan->featured),
            'sortorder' => (int) ($plan->sortorder ?? 0),
            'features' => $features,
        ];
    }

    /**
     * Format the user's current active subscription, or an empty placeholder.
     *
     * @param int $userid User ID.
     * @return array
     */
    private static function format_current(int $userid): array {
        $current = subscription_api::get_active_for_user($userid);
        if (!$current) {
            return [
                'hassubscription' => false,
                'planid' => 0,
                'planname' => '',
                'billingcycle' => '',
                'status' => '',
                'startdate' => 0,
                'enddate' => 0,
            ];
        }

        return [
            'hassubscription' => true,
            'planid' => (int) $current->planid,
            'planname' => format_string($current->plan_name ?? $current->planname ?? ''),
            'billingcycle' => (string) ($current->billing_cycle ?? ''),
            'status' => (string) $current->status,
            'startdate' => (int) ($current->start_date ?? 0),
            'enddate' => (int) ($current->end_date ?? 0),
        ];
    }

    /**
     * Require login and the buyer subscribe capability.
     *
     * @return context_system
     */
    private static function require_subscribe_capability(): context_system {
        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:subscribetoplan', $context);
        return $context;
    }

    /**
     * A human label for a billing cycle.
     *
     * @param string $cycle Cycle.
     * @return string
     */
    private static function cycle_label(string $cycle): string {
        return $cycle === 'yearly'
            ? get_string('billingcycle_yearly', 'local_moderncommerce')
            : get_string('billingcycle_monthly', 'local_moderncommerce');
    }

    /**
     * Format a price using the Modern Commerce site currency helper.
     *
     * @param float $amount Amount.
     * @return string
     */
    private static function format_price(float $amount): string {
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            return \local_moderncommerce\services\pricing_service::format_price($amount);
        }
        $currency = get_config('local_moderncommerce', 'primary_currency') ?: 'USD';
        return $currency . ' ' . number_format($amount, 2);
    }

    /**
     * Site currency config for the client.
     *
     * @return array
     */
    private static function currency_config(): array {
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            $config = \local_moderncommerce\services\pricing_service::get_currency_config();
            return [
                'code' => (string) $config->currency,
                'symbol' => (string) $config->symbol,
                'position' => (string) $config->position,
                'decimals' => (int) $config->decimals,
            ];
        }
        return [
            'code' => get_config('local_moderncommerce', 'primary_currency') ?: 'USD',
            'symbol' => '$',
            'position' => 'before',
            'decimals' => 2,
        ];
    }

    /**
     * Plan structure.
     *
     * @return external_single_structure
     */
    private static function plan_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Plan ID.'),
            'name' => new external_value(PARAM_TEXT, 'Plan name.'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Plan code.'),
            'description' => new external_value(PARAM_RAW, 'Description.'),
            'billingcycle' => new external_value(PARAM_ALPHANUMEXT, 'Billing cycle.'),
            'price' => new external_value(PARAM_FLOAT, 'Effective price.'),
            'displayprice' => new external_value(PARAM_TEXT, 'Formatted effective price.'),
            'regularprice' => new external_value(PARAM_FLOAT, 'Regular price.'),
            'displayregularprice' => new external_value(PARAM_TEXT, 'Formatted regular price.'),
            'haspromo' => new external_value(PARAM_BOOL, 'Whether a promo price is active.'),
            'isfree' => new external_value(PARAM_BOOL, 'Whether the plan is free.'),
            'trialdays' => new external_value(PARAM_INT, 'Trial days.'),
            'trialprice' => new external_value(PARAM_FLOAT, 'Amount paid when the trial starts (0: free trial).'),
            'displaytrialprice' => new external_value(PARAM_TEXT, 'Formatted trial amount.'),
            'payments' => new external_value(PARAM_INT, 'Number of payments, then access stops (0: until cancelled).'),
            'maxseats' => new external_value(PARAM_INT, 'Max seats.'),
            'featured' => new external_value(PARAM_BOOL, 'Featured flag.'),
            'sortorder' => new external_value(PARAM_INT, 'Sort order.'),
            'features' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Feature name.'),
                'icon' => new external_value(PARAM_TEXT, 'Bootstrap icon name.'),
            ])),
        ]);
    }

    /**
     * Current subscription structure.
     *
     * @return external_single_structure
     */
    private static function current_structure(): external_single_structure {
        return new external_single_structure([
            'hassubscription' => new external_value(PARAM_BOOL, 'Whether the user has an active subscription.'),
            'planid' => new external_value(PARAM_INT, 'Current plan ID.'),
            'planname' => new external_value(PARAM_TEXT, 'Current plan name.'),
            'billingcycle' => new external_value(PARAM_ALPHANUMEXT, 'Current billing cycle.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Current status.'),
            'startdate' => new external_value(PARAM_INT, 'Start timestamp.'),
            'enddate' => new external_value(PARAM_INT, 'End timestamp.'),
        ]);
    }

    /**
     * Currency structure.
     *
     * @return external_single_structure
     */
    private static function currency_structure(): external_single_structure {
        return new external_single_structure([
            'code' => new external_value(PARAM_TEXT, 'Currency code.'),
            'symbol' => new external_value(PARAM_TEXT, 'Currency symbol.'),
            'position' => new external_value(PARAM_ALPHANUMEXT, 'Symbol position.'),
            'decimals' => new external_value(PARAM_INT, 'Decimal places.'),
        ]);
    }
}
