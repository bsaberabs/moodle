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
 * External API for learner-initiated subscription changes.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\api\order_api;
use local_moderncommerce\subscription\api\subscription_api;
use local_moderncommerce\subscription\services\subscription_service;
use local_moderncommerce\subscription\services\upgrade_service;
use moodle_url;

/**
 * Change plan, cancel at period end, or renew the learner's own subscription.
 *
 * Every action resolves the subscription from the logged-in user rather than trusting an
 * ID from the client, so a learner can only ever act on their own.
 */
class change_subscription extends external_api {
    /** @var string Switch to a different plan. */
    private const ACTION_CHANGE_PLAN = 'changeplan';

    /** @var string Stop auto-renewal, keeping access until the period ends. */
    private const ACTION_CANCEL = 'cancel';

    /** @var string Undo a scheduled cancellation. */
    private const ACTION_RESUME = 'resume';

    /** @var string Renew the current plan. */
    private const ACTION_RENEW = 'renew';

    /** @var string Drop a scheduled plan change. */
    private const ACTION_CANCELCHANGE = 'cancelchange';

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHA, 'changeplan, cancel, resume, renew or cancelchange.'),
            'planid' => new external_value(PARAM_INT, 'Target plan ID, for changeplan.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $action Action key.
     * @param int $planid Target plan ID.
     * @return array
     */
    public static function execute(string $action, int $planid = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'planid' => $planid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:subscribetoplan', $context);

        $userid = (int)$USER->id;
        $subscription = subscription_api::get_active_for_user($userid);
        if (!$subscription) {
            return self::failure(get_string('noactivesubscription', 'local_moderncommerce'));
        }

        switch ($params['action']) {
            case self::ACTION_CHANGE_PLAN:
                return self::change_plan($subscription, (int)$params['planid'], $userid);

            case self::ACTION_CANCEL:
                // Self-service cancellation can be switched off site-wide.
                if ((int) (get_config('local_moderncommerce', 'allow_user_cancel') ?? 1) === 0) {
                    return self::failure(get_string('error:cancelnotallowed', 'local_moderncommerce'));
                }

                // Immediate mode ends the subscription now; the default closes it
                // at the end of the paid term so the buyer keeps what they paid for.
                if ((string) get_config('local_moderncommerce', 'cancel_mode') === 'immediate') {
                    subscription_service::cancel(
                        (int) $subscription->id,
                        get_string('cancelledbyuser', 'local_moderncommerce')
                    );
                    return self::success(get_string('subscriptioncancelled', 'local_moderncommerce'));
                }

                return self::set_cancel_at_period_end($subscription, true);

            case self::ACTION_RESUME:
                return self::set_cancel_at_period_end($subscription, false);

            case self::ACTION_RENEW:
                return self::renew($subscription, $userid);

            case self::ACTION_CANCELCHANGE:
                upgrade_service::cancel_scheduled_change((int)$subscription->id);
                return self::success(get_string('planchangecancelled', 'local_moderncommerce'));

            default:
                return self::failure(get_string('invalidaction', 'local_moderncommerce'));
        }
    }

    /**
     * Move to another plan: immediately when free, at period end when a downgrade, or
     * through checkout when payment is due.
     *
     * @param \stdClass $subscription Subscription record.
     * @param int $planid Target plan ID.
     * @param int $userid User ID.
     * @return array
     */
    private static function change_plan(\stdClass $subscription, int $planid, int $userid): array {
        global $DB;

        if ($planid <= 0 || (int)$subscription->planid === $planid) {
            return self::failure(get_string('error:alreadysubscribed', 'local_moderncommerce'));
        }

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $planid]);
        if (!$plan || (string)$plan->status !== 'active') {
            return self::failure(get_string('invalidplan', 'local_moderncommerce'));
        }

        $canchange = upgrade_service::can_change_plan((int)$subscription->id);
        if (empty($canchange['allowed'])) {
            return self::failure(($canchange['reason'] ?? '') === 'cooldown'
                ? get_string('planchangeblocked', 'local_moderncommerce', (int)($canchange['wait_days'] ?? 0))
                : get_string('planchangeunavailable', 'local_moderncommerce'));
        }

        $preview = upgrade_service::preview_upgrade((int)$subscription->id, $planid);

        // Downgrades and non-immediate moves are queued for the end of the paid period,
        // so the learner keeps what they already paid for.
        if (empty($preview['immediate'])) {
            upgrade_service::schedule_downgrade((int)$subscription->id, $planid);
            $effective = (int)($preview['effective_date'] ?? $subscription->end_date);

            return self::success(get_string(
                'planchangescheduled',
                'local_moderncommerce',
                (object)[
                    'plan' => format_string((string)$plan->name),
                    'date' => userdate($effective, get_string('strftimedateshort')),
                ]
            ));
        }

        // Immediate and fully covered by credit or a zero cost: apply it now.
        $cost = (float)($preview['upgradecost'] ?? 0);
        if (empty($preview['requires_payment']) || $cost <= 0) {
            upgrade_service::process_immediate_upgrade((int)$subscription->id, $planid);

            return self::success(get_string(
                'planchanged',
                'local_moderncommerce',
                format_string((string)$plan->name)
            ));
        }

        // Payment due: hand off to the existing Modern Commerce checkout. The payment
        // observer applies the upgrade when the order is paid.
        $order = order_api::create_subscription_order(
            $userid,
            $planid,
            $cost,
            format_string((string)$plan->name),
            '',
            ['action' => 'upgrade', 'from_subscription_id' => (int)$subscription->id]
        );

        return self::success('', (new moodle_url('/local/moderncommerce/checkout.php', [
            'orderid' => (int)$order->id,
        ]))->out(false));
    }

    /**
     * Schedule or undo cancellation at the end of the paid period.
     *
     * Learner-facing cancellation never terminates access mid-period: it clears
     * auto-renew and flags the subscription so the expiry task closes it at end_date.
     *
     * @param \stdClass $subscription Subscription record.
     * @param bool $cancel Whether to schedule cancellation.
     * @return array
     */
    private static function set_cancel_at_period_end(\stdClass $subscription, bool $cancel): array {
        global $DB;

        $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
            'id' => (int)$subscription->id,
            'cancel_at_period_end' => $cancel ? 1 : 0,
            'auto_renew' => $cancel ? 0 : (int)$subscription->auto_renew,
            'timemodified' => time(),
        ]);

        $enddate = !empty($subscription->end_date)
            ? userdate((int)$subscription->end_date, get_string('strftimedateshort'))
            : '';

        return self::success($cancel
            ? get_string('subscriptioncancelscheduled', 'local_moderncommerce', $enddate)
            : get_string('subscriptioncancelreverted', 'local_moderncommerce'));
    }

    /**
     * Renew the current plan, through checkout when it is a paid plan.
     *
     * @param \stdClass $subscription Subscription record.
     * @param int $userid User ID.
     * @return array
     */
    private static function renew(\stdClass $subscription, int $userid): array {
        global $DB;

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid]);
        if (!$plan) {
            return self::failure(get_string('invalidplan', 'local_moderncommerce'));
        }

        $price = (float)$plan->price;
        if ($price <= 0) {
            \local_moderncommerce\subscription\services\subscription_service::renew((int)$subscription->id);
            return self::success(get_string('subscriptionrenewed', 'local_moderncommerce'));
        }

        $order = order_api::create_subscription_order(
            $userid,
            (int)$plan->id,
            $price,
            format_string((string)$plan->name),
            '',
            ['action' => 'renewal', 'subscription_id' => (int)$subscription->id]
        );

        return self::success('', (new moodle_url('/local/moderncommerce/checkout.php', [
            'orderid' => (int)$order->id,
        ]))->out(false));
    }

    /**
     * Successful response.
     *
     * @param string $message Result message.
     * @param string $checkouturl Checkout URL when payment is required.
     * @return array
     */
    private static function success(string $message, string $checkouturl = ''): array {
        return [
            'success' => true,
            'message' => $message,
            'checkouturl' => $checkouturl,
            'warnings' => [],
        ];
    }

    /**
     * Failed response.
     *
     * @param string $message Failure message.
     * @return array
     */
    private static function failure(string $message): array {
        return [
            'success' => false,
            'message' => $message,
            'checkouturl' => '',
            'warnings' => [],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the action succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'checkouturl' => new external_value(PARAM_RAW, 'Checkout URL when payment is required, else empty.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
