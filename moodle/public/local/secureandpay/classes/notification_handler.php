<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_secureandpay;

use local_moderncommerce\api\order_api;
use local_moderncommerce\payment\gateway_manager;
use local_moderncommerce\subscription\services\subscription_service;
use moodle_exception;

/**
 * Applies SecureAndPay server-to-server notifications to Modern Commerce orders and subscriptions.
 *
 * TransStatus: 00 paid, 01 deferred/pre-authorised, 02 deferred validated, 03 refunded,
 * 04 chargeback, 05 refused, 06 subscription cancelled. Every instalment of a ClicktoPay
 * subscription is notified with the orderID of the first payment; banqueId tells them apart,
 * so a repeated notification for the same instalment is applied only once.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_handler {

    /**
     * Verify and apply one notification.
     *
     * @param string $rawdata Base64 'data' field as posted.
     * @param string $signature 'signature' field as posted.
     * @return string Outcome, for logging.
     */
    public static function handle(string $rawdata, string $signature): string {
        global $DB;

        // Each order is signed with the key of the account that took it (the platform or a trainer), so the
        // order is looked up first, read-only, and nothing is changed before the signature is verified.
        $data = json_decode((string) base64_decode($rawdata, true), true);
        if (!is_array($data) || empty($data['PAYID'])) {
            throw new moodle_exception('invalidnotification', 'local_secureandpay');
        }
        $order = self::find_order($data);
        $merchant = gateway::merchant_for_order($order, false);
        if (!$merchant->is_configured() || !hash_equals($merchant->sign($rawdata), strtolower(trim($signature)))) {
            throw new moodle_exception('invalidsignature', 'local_secureandpay');
        }

        $status = (string) ($data['TransStatus'] ?? '');
        $paid = in_array($order->status, ['paid', 'completed'], true);
        $subscription = self::find_subscription($order);

        switch ($status) {
            case '00':
            case '02':
                self::remember_card($order, $data, $merchant);
                if (!$paid) {
                    self::record($order, $data, 'success');
                    order_api::update_order_status($order->id, 'paid');
                    if (gateway::is_trial_order($order)) {
                        // The card is registered for 0: the subscription Modern Commerce just created is a trial.
                        self::start_trial($order);
                        return 'trial_started';
                    }
                    if ($subscription = self::find_subscription($order)) {
                        self::end_after_last_payment($order, $subscription);
                    }
                    return 'paid';
                }
                if ($subscription && !self::record($order, $data, 'success')->duplicate) {
                    // A later ClicktoPay instalment: extend the subscription by one cycle, or bring it
                    // back if Modern Commerce expired it before the instalment arrived.
                    if (in_array($subscription->status, ['active', 'trial', 'grace'], true)) {
                        subscription_service::renew((int) $subscription->id, null, (int) $order->id);
                    } else {
                        subscription_service::reactivate((int) $subscription->id, (int) $order->id);
                    }
                    $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscription->id]);
                    return self::end_after_last_payment($order, $subscription) ? 'last_payment' : 'renewed';
                }
                return 'duplicate';

            case '01':
                self::record($order, $data, 'pending');
                return 'pending';

            case '03':
            case '04':
                self::record($order, $data, 'refunded');
                if (!$subscription) {
                    order_api::update_order_status($order->id, 'refunded');
                }
                return $status === '03' ? 'refunded' : 'chargeback';

            case '05':
                self::record($order, $data, 'failed');
                if (!$paid) {
                    order_api::update_order_status($order->id, 'failed');
                } else if ($subscription) {
                    $DB->set_field('local_moderncommerce_user_subscriptions', 'payment_failed_count',
                        (int) $subscription->payment_failed_count + 1, ['id' => $subscription->id]);
                    $DB->set_field('local_moderncommerce_user_subscriptions', 'last_payment_attempt',
                        time(), ['id' => $subscription->id]);
                }
                return 'refused';

            case '06':
                if ($subscription) {
                    self::end_at_period_end($subscription);
                }
                return 'subscription_cancelled';
        }

        return 'ignored';
    }

    /**
     * Resolve the order from the orderID (PAYID) recorded when the payment started.
     *
     * @param array $data Notification.
     * @return object
     */
    private static function find_order(array $data): object {
        global $DB;

        $reference = (string) $data['PAYID'];
        $orderid = (int) $DB->get_field('local_moderncommerce_payment_attempts', 'orderid', [
            'gateway' => gateway::GATEWAY,
            'reference' => $reference,
        ], IGNORE_MULTIPLE);

        // The order id also travels as osc_id; it must agree with the reference. A free trial has
        // no payment attempt yet, so it is found by osc_id and checked against its notes.
        $oscid = (int) ($data['osCsid'] ?? 0);
        if (!$orderid && $oscid) {
            $order = $DB->get_record('local_moderncommerce_orders', ['id' => $oscid]);
            if ($order && gateway::reference_matches($order, $reference)) {
                $orderid = $oscid;
            }
        }
        if (!$orderid || ($oscid && $oscid !== $orderid)) {
            throw new moodle_exception('invalidorder', 'local_moderncommerce');
        }
        return order_api::get_order($orderid);
    }

    /**
     * Keep the card token for one-click payments, when that option is on.
     *
     * @param object $order Order.
     * @param array $data Notification.
     * @param merchant $merchant Account that took the payment and holds the card.
     */
    private static function remember_card(object $order, array $data, merchant $merchant): void {
        if (!empty($data['token']) && get_config('local_secureandpay', 'oneclick')) {
            token_store::save((int) $order->userid, $merchant->siteid, (string) $data['token'],
                (string) ($data['dateValidate'] ?? ''));
        }
    }

    /**
     * Turn the subscription created for a 0-cost trial order into a trial.
     *
     * The trial ends one day after SecureAndPay's first charge is due, so Modern Commerce
     * does not expire it before that charge is notified.
     *
     * @param object $order Trial order.
     */
    private static function start_trial(object $order): void {
        global $DB;

        $subscription = self::find_subscription($order);
        if (!$subscription || $subscription->status === 'trial') {
            return;
        }
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);
        $trialend = time() + ((int) $plan->trial_days + 1) * DAYSECS;
        $DB->update_record('local_moderncommerce_user_subscriptions', (object) [
            'id' => $subscription->id,
            'status' => 'trial',
            'trial_end_date' => $trialend,
            'end_date' => $trialend,
            'timemodified' => time(),
        ]);
        $DB->set_field('local_moderncommerce_subscription_access', 'expires_at', $trialend,
            ['subscriptionid' => $subscription->id]);
        subscription_service::log_history((int) $subscription->id, (int) $subscription->userid, 'trial_started',
            null, (int) $subscription->planid, null, $trialend, (float) $order->total, (int) $order->id);
    }

    /**
     * Subscription created from this order, if any.
     *
     * @param object $order Order.
     * @return object|null
     */
    private static function find_subscription(object $order): ?object {
        global $DB;

        if (!gateway::order_is_subscription($order)) {
            return null;
        }
        return $DB->get_record('local_moderncommerce_user_subscriptions', ['orderid' => $order->id]) ?: null;
    }

    /**
     * Record the payment event; banqueId makes each instalment a distinct, idempotent event.
     *
     * @param object $order Order.
     * @param array $data Notification.
     * @param string $status Normalised status.
     * @return object Ledger result from Modern Commerce.
     */
    private static function record(object $order, array $data, string $status): object {
        $banqueid = (string) ($data['banqueId'] ?? '');
        $transstatus = (string) ($data['TransStatus'] ?? '');
        $amount = $data['PayAmount'] ?? ($data['TotalAmount'] ?? null);

        return gateway_manager::record_payment_result($order, gateway::GATEWAY, (object) [
            'status' => $status,
            'orderid' => $order->id,
            'orderreference' => $order->ordernumber,
            'reference' => (string) $data['PAYID'],
            'gatewaytransactionid' => $banqueid ?: (string) $data['PAYID'],
            // Modern Commerce deduplicates events across all orders, so scope the bank reference to the orderID.
            'gatewayeventid' => $transstatus . '-' . $data['PAYID'] . '-' . $banqueid,
            'amount' => is_numeric($amount) ? ((float) $amount / 100) : null,
            'currency' => $order->currency,
            'channel' => 'card',
            'message' => trim(($data['codeRefus'] ?? '') . ' ' . ($data['detailsRefus'] ?? '')),
            'source' => 'webhook',
            'eventtype' => 'secureandpay.' . $transstatus,
            'rawdata' => $data,
        ]);
    }

    /**
     * A plan billed for a fixed number of payments: once the last one is paid, SecureAndPay stops billing and
     * the subscription ends with the period that payment covers (no renewal reminder or failed-payment notice).
     *
     * @param object $order Order whose payment started the schedule.
     * @param object $subscription Subscription.
     * @return bool Whether this was the last payment.
     */
    private static function end_after_last_payment(object $order, object $subscription): bool {
        global $DB;

        $payments = gateway::plan_payments((int) $subscription->planid);
        if (!$payments || !empty($subscription->cancel_at_period_end)) {
            return false;
        }
        // Instalments charged: the trial's own payment (the card registration, at 0 or at a token amount) is not one.
        $paid = $DB->count_records_select('local_moderncommerce_payment_events',
            'orderid = :orderid AND gateway = :gateway AND status = :status AND amount > 0',
            ['orderid' => $order->id, 'gateway' => gateway::GATEWAY, 'status' => 'success']);
        if (gateway::is_trial_order($order) && (float) $order->total > 0) {
            $paid--;
        }
        if ($paid < $payments) {
            return false;
        }
        $DB->update_record('local_moderncommerce_user_subscriptions', (object) [
            'id' => $subscription->id,
            'cancel_at_period_end' => 1,
            'auto_renew' => 0,
            'cancellation_reason' => 'completed',
            'timemodified' => time(),
        ]);
        subscription_service::log_history((int) $subscription->id, (int) $subscription->userid, 'payments_completed',
            null, null, null, null, null, null, get_string('lastpaymentreceived', 'local_secureandpay', $payments));
        return true;
    }

    /**
     * SecureAndPay stopped billing: keep access until the paid period ends, then let it expire.
     *
     * @param object $subscription Subscription.
     */
    private static function end_at_period_end(object $subscription): void {
        global $DB;

        if (!empty($subscription->cancel_at_period_end)) {
            return;
        }
        $DB->update_record('local_moderncommerce_user_subscriptions', (object) [
            'id' => $subscription->id,
            'cancel_at_period_end' => 1,
            'auto_renew' => 0,
            'cancellation_reason' => 'secureandpay',
            'timemodified' => time(),
        ]);
        subscription_service::log_history((int) $subscription->id, (int) $subscription->userid, 'gateway_stopped',
            null, null, null, null, null, null, get_string('cancelledatsecureandpay', 'local_secureandpay'));
    }
}
