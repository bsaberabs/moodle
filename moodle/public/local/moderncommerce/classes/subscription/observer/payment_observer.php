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

namespace local_moderncommerce\subscription\observer;

use local_moderncommerce\subscription\services\subscription_service;
use local_moderncommerce\subscription\services\upgrade_service;

/**
 * Payment observer - creates/renews subscriptions when a Modern Commerce order is paid.
 *
 * Subscription orders are created by
 * local_moderncommerce\api\order_api::create_subscription_order(), which stores
 * the plan and intent in the order notes JSON under a "subscription" key:
 *   {"subscription": {"planid": N, "action": "new|upgrade|renewal", ...}}
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_observer {
    /**
     * Handle the Modern Commerce order_paid event.
     *
     * @param \local_moderncommerce\event\order_paid $event
     */
    public static function order_paid(\local_moderncommerce\event\order_paid $event) {
        global $DB;

        $orderid = (int) $event->get_data()['objectid'];
        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        if (!$order || empty($order->notes)) {
            return;
        }

        $notes = json_decode($order->notes, true);
        if (!is_array($notes) || empty($notes['subscription']) || empty($notes['subscription']['planid'])) {
            return;
        }

        self::process_subscription_payment($order, $notes['subscription']);
    }

    /**
     * Create, renew, upgrade, or reactivate a subscription for a paid order.
     *
     * @param object $order Modern Commerce order record.
     * @param array $sub Subscription intent from the order notes.
     */
    private static function process_subscription_payment(object $order, array $sub) {
        global $DB;

        $planid = (int) $sub['planid'];
        $userid = (int) $order->userid;
        $action = (string) ($sub['action'] ?? 'new');

        // Explicit upgrade of a known subscription.
        if ($action === 'upgrade' && !empty($sub['from_subscription_id'])) {
            $existing = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => (int) $sub['from_subscription_id']]);
            if ($existing && (int) $existing->userid === $userid) {
                upgrade_service::process_immediate_upgrade((int) $existing->id, $planid, (int) $order->id);
                self::store_billing_mandate((int) $existing->id, $sub);
                return;
            }
        }

        // Explicit renewal of a known subscription.
        if ($action === 'renewal' && !empty($sub['subscription_id'])) {
            $existing = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => (int) $sub['subscription_id']]);
            if ($existing && (int) $existing->userid === $userid) {
                if (in_array($existing->status, ['active', 'trial', 'grace'], true)) {
                    subscription_service::renew((int) $existing->id, $planid ?: null, (int) $order->id);
                } else {
                    subscription_service::reactivate((int) $existing->id, (int) $order->id);
                }
                self::store_billing_mandate((int) $existing->id, $sub);
                return;
            }
        }

        // New subscription. If the user already holds this plan, renew/reactivate it; otherwise create.
        $existing = $DB->get_record_sql(
            "SELECT * FROM {local_moderncommerce_user_subscriptions}
              WHERE userid = :userid AND planid = :planid
                AND status IN ('active', 'trial', 'grace', 'suspended')",
            ['userid' => $userid, 'planid' => $planid]
        );

        if ($existing) {
            if (in_array($existing->status, ['active', 'trial', 'grace'], true)) {
                subscription_service::renew((int) $existing->id, null, (int) $order->id);
            } else if ($existing->status === 'suspended') {
                subscription_service::reactivate((int) $existing->id, (int) $order->id);
            }
            self::store_billing_mandate((int) $existing->id, $sub);
            return;
        }

        // Seats can fill between raising the order and the payment landing. The
        // buyer has already paid at this point, so honour the purchase and let an
        // admin reconcile rather than silently swallowing it.
        $plan = \local_moderncommerce\subscription\api\plan_api::get($planid, false);
        if ($plan && !\local_moderncommerce\subscription\api\plan_api::has_available_seat($plan, $userid)) {
            debugging(
                'Modern Commerce: plan ' . $planid . ' is over its seat limit; order '
                    . $order->id . ' was honoured anyway.',
                DEBUG_NORMAL
            );
        }

        // Paid purchase, so no trial.
        $subscriptionid = subscription_service::activate($userid, $planid, (int) $order->id, false);
        self::store_billing_mandate((int) $subscriptionid, $sub);
    }

    /**
     * Copy the saved card captured at checkout onto the subscription.
     *
     * The gateway writes the customer and payment method into the order notes
     * when the buyer completes a subscription checkout. Persisting them here is
     * what allows the recurring payment task to charge off-session; without a
     * mandate the subscription stays on manual renewal, so auto-renew is only
     * switched on when a card is actually on file.
     *
     * @param int $subscriptionid Subscription that was just created or renewed.
     * @param array $sub Subscription intent from the order notes.
     * @return void
     */
    private static function store_billing_mandate(int $subscriptionid, array $sub): void {
        global $DB;

        if ($subscriptionid <= 0) {
            return;
        }

        $update = ['id' => $subscriptionid];

        // Stripe needs the customer and the payment method together.
        $customerid = trim((string) ($sub['stripe_customer_id'] ?? ''));
        $paymentmethodid = trim((string) ($sub['stripe_payment_method_id'] ?? ''));
        if ($customerid !== '' && $paymentmethodid !== '') {
            $update['stripe_customer_id'] = $customerid;
            $update['stripe_payment_method_id'] = $paymentmethodid;
        }

        // Paystack charges a reusable authorization against the buyer's email.
        $authcode = trim((string) ($sub['paystack_authorization_code'] ?? ''));
        $authemail = trim((string) ($sub['paystack_email'] ?? ''));
        if ($authcode !== '' && $authemail !== '') {
            $update['paystack_authorization_code'] = $authcode;
            $update['paystack_email'] = $authemail;
        }

        // Nothing reusable was captured, so the subscription stays manual-renewal.
        if (count($update) === 1) {
            return;
        }

        if (!$DB->record_exists('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid])) {
            return;
        }

        $update['auto_renew'] = 1;
        $update['payment_failed_count'] = 0;
        $update['timemodified'] = time();

        $DB->update_record('local_moderncommerce_user_subscriptions', (object) $update);
    }
}
