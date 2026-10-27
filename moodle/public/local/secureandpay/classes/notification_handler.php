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
 * subscription is notified with the orderID of the first payment; banqueId tells them apart.
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
        if ($rawdata === '' || !hash_equals(gateway::sign($rawdata), strtolower(trim($signature)))) {
            throw new moodle_exception('invalidsignature', 'local_secureandpay');
        }
        $data = json_decode((string) base64_decode($rawdata, true), true);
        if (!is_array($data) || empty($data['PAYID'])) {
            throw new moodle_exception('invalidnotification', 'local_secureandpay');
        }

        $order = self::find_order($data);
        $status = (string) ($data['TransStatus'] ?? '');
        $paid = in_array($order->status, ['paid', 'completed'], true);
        $subscription = self::find_subscription($order);

        switch ($status) {
            case '00':
            case '02':
                if (!$paid) {
                    self::record($order, $data, 'success');
                    order_api::update_order_status($order->id, 'paid');
                    return 'paid';
                }
                if ($subscription && !self::record($order, $data, 'success')->duplicate) {
                    // A later ClicktoPay instalment: extend the subscription by one cycle.
                    subscription_service::renew((int) $subscription->id, null, (int) $order->id);
                    return 'renewed';
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
                    global $DB;
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

        $orderid = (int) $DB->get_field('local_moderncommerce_payment_attempts', 'orderid', [
            'gateway' => gateway::GATEWAY,
            'reference' => (string) $data['PAYID'],
        ], IGNORE_MULTIPLE);

        // The order id also travels as osc_id; it must agree with the reference.
        $oscid = (int) ($data['osCsid'] ?? 0);
        if (!$orderid || ($oscid && $oscid !== $orderid)) {
            throw new moodle_exception('invalidorder', 'local_moderncommerce');
        }
        return order_api::get_order($orderid);
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
            'gatewayeventid' => $transstatus . '-' . ($banqueid ?: $data['PAYID']),
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
        subscription_service::log_history((int) $subscription->id, (int) $subscription->userid, 'cancel_scheduled',
            null, null, null, null, null, null, get_string('cancelledatsecureandpay', 'local_secureandpay'));
    }
}
