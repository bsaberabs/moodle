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

use local_moderncommerce\logging\paylog_service;

/**
 * Webhook service - Handles subscription-related webhook events from payment gateways.
 *
 * This service is called by local_moderncommerce gateway classes when they receive
 * subscription-related webhook events. All webhook endpoints are in moderncommerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook_service {
    /**
     * Subscription-related event types by gateway.
     */
    const SUBSCRIPTION_EVENTS = [
        'stripe' => [
            'invoice.paid',
            'invoice.payment_failed',
            'customer.subscription.deleted',
            'customer.subscription.updated',
        ],
        'paypal' => [
            'BILLING.SUBSCRIPTION.ACTIVATED',
            'BILLING.SUBSCRIPTION.CANCELLED',
            'BILLING.SUBSCRIPTION.SUSPENDED',
            'BILLING.SUBSCRIPTION.EXPIRED',
            'PAYMENT.SALE.COMPLETED',
            'PAYMENT.SALE.DENIED',
            'PAYMENT.SALE.REFUNDED',
        ],
        'paystack' => [
            'subscription.create',
            'subscription.not_renew',
            'subscription.disable',
            'invoice.create',
            'invoice.payment_failed',
            'invoice.update',
        ],
        'flutterwave' => [
            'subscription.cancelled',
        ],
    ];

    /**
     * Check if an event is subscription-related.
     *
     * @param string $eventtype Event type from webhook.
     * @param string $gateway Gateway name (stripe, paypal, paystack, flutterwave).
     * @return bool True if this is a subscription event.
     */
    public static function is_subscription_event(string $eventtype, string $gateway): bool {
        $events = self::SUBSCRIPTION_EVENTS[$gateway] ?? [];
        return in_array($eventtype, $events);
    }

    /**
     * Handle Stripe subscription webhook event.
     *
     * @param string $eventtype Event type (e.g., 'invoice.paid').
     * @param object|array $data Event data object.
     * @return bool True on success.
     */
    public static function handle_stripe_event(string $eventtype, $data): bool {
        // Convert to object if array.
        if (is_array($data)) {
            $data = json_decode(json_encode($data));
        }

        self::log_event('stripe', $eventtype, $data);

        switch ($eventtype) {
            case 'invoice.paid':
                return self::handle_stripe_invoice_paid($data);

            case 'invoice.payment_failed':
                return self::handle_stripe_invoice_failed($data);

            case 'customer.subscription.deleted':
                return self::handle_stripe_subscription_deleted($data);

            case 'customer.subscription.updated':
                return self::handle_stripe_subscription_updated($data);

            default:
                return true;
        }
    }

    /**
     * Handle PayPal subscription webhook event.
     *
     * @param string $eventtype Event type (e.g., 'BILLING.SUBSCRIPTION.ACTIVATED').
     * @param object|array $data Event data object.
     * @return bool True on success.
     */
    public static function handle_paypal_event(string $eventtype, $data): bool {
        if (is_array($data)) {
            $data = json_decode(json_encode($data));
        }

        self::log_event('paypal', $eventtype, $data);

        switch ($eventtype) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                return self::handle_paypal_subscription_activated($data);

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                return self::handle_paypal_subscription_cancelled($data);

            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                return self::handle_paypal_subscription_suspended($data);

            case 'BILLING.SUBSCRIPTION.EXPIRED':
                return self::handle_paypal_subscription_expired($data);

            case 'PAYMENT.SALE.COMPLETED':
                return self::handle_paypal_payment_completed($data);

            case 'PAYMENT.SALE.DENIED':
            case 'PAYMENT.SALE.REFUNDED':
                return self::handle_paypal_payment_failed($data);

            default:
                return true;
        }
    }

    /**
     * Handle Paystack subscription webhook event.
     *
     * @param string $eventtype Event type (e.g., 'subscription.create').
     * @param object|array $data Event data object.
     * @return bool True on success.
     */
    public static function handle_paystack_event(string $eventtype, $data): bool {
        if (is_array($data)) {
            $data = json_decode(json_encode($data));
        }

        self::log_event('paystack', $eventtype, $data);

        switch ($eventtype) {
            case 'subscription.create':
                return self::handle_paystack_subscription_created($data);

            case 'subscription.not_renew':
            case 'subscription.disable':
                return self::handle_paystack_subscription_cancelled($data);

            case 'invoice.create':
            case 'invoice.update':
                return self::handle_paystack_invoice($data);

            case 'invoice.payment_failed':
                return self::handle_paystack_payment_failed($data);

            default:
                return true;
        }
    }

    /**
     * Handle Flutterwave subscription webhook event.
     *
     * @param string $eventtype Event type (e.g., 'subscription.cancelled').
     * @param object|array $data Event data object.
     * @return bool True on success.
     */
    public static function handle_flutterwave_event(string $eventtype, $data): bool {
        if (is_array($data)) {
            $data = json_decode(json_encode($data));
        }

        self::log_event('flutterwave', $eventtype, $data);

        switch ($eventtype) {
            case 'subscription.cancelled':
                return self::handle_flutterwave_subscription_cancelled($data);

            default:
                return true;
        }
    }

    // Stripe handlers.

    /**
     * Handle Stripe invoice.paid event.
     */
    protected static function handle_stripe_invoice_paid(object $invoice): bool {
        global $DB;

        // Only process subscription invoices.
        $subscriptionid = $invoice->subscription ?? null;
        if (empty($subscriptionid)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'stripe_subscription_id' => $subscriptionid,
        ]);

        if (!$subscription) {
            // Try by customer ID.
            $customerid = $invoice->customer ?? null;
            if ($customerid) {
                $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
                    'stripe_customer_id' => $customerid,
                    'status' => 'active',
                ]);
            }
        }

        if (!$subscription) {
            return true;
        }

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid]);
        if (!$plan) {
            return true;
        }

        $now = time();
        $duration = subscription_service::get_cycle_duration($plan->billing_cycle);

        // Renew the subscription.
        $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
            'id' => $subscription->id,
            'status' => 'active',
            'end_date' => $subscription->end_date + $duration,
            'next_billing_date' => $subscription->end_date + $duration,
            'renewal_count' => $subscription->renewal_count + 1,
            'payment_failed_count' => 0,
            'last_payment_attempt' => $now,
            'timemodified' => $now,
        ]);

        payment_retry_service::track_success($subscription->id);

        self::log_subscription_action($subscription, 'stripe_renewed', [
            'invoice_id' => $invoice->id ?? '',
            'amount_paid' => isset($invoice->amount_paid) ? $invoice->amount_paid / 100 : 0,
            'currency' => $invoice->currency ?? '',
        ]);

        return true;
    }

    /**
     * Handle Stripe invoice.payment_failed event.
     */
    protected static function handle_stripe_invoice_failed(object $invoice): bool {
        global $DB;

        $subscriptionid = $invoice->subscription ?? null;
        if (empty($subscriptionid)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'stripe_subscription_id' => $subscriptionid,
        ]);

        if (!$subscription) {
            return true;
        }

        payment_retry_service::track_failure(
            $subscription->id,
            'stripe',
            'Invoice payment failed'
        );

        return true;
    }

    /**
     * Handle Stripe customer.subscription.deleted event.
     */
    protected static function handle_stripe_subscription_deleted(object $stripesubscription): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'stripe_subscription_id' => $stripesubscription->id,
        ]);

        if (!$subscription) {
            return true;
        }

        subscription_service::cancel($subscription->id, 'Cancelled via Stripe');

        self::log_subscription_action($subscription, 'stripe_cancelled', [
            'stripe_subscription_id' => $stripesubscription->id,
        ]);

        return true;
    }

    /**
     * Handle Stripe customer.subscription.updated event.
     */
    protected static function handle_stripe_subscription_updated(object $stripesubscription): bool {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'stripe_subscription_id' => $stripesubscription->id,
        ]);

        if (!$subscription) {
            return true;
        }

        $newstatus = null;
        switch ($stripesubscription->status) {
            case 'active':
                $newstatus = 'active';
                break;
            case 'past_due':
                $newstatus = 'grace';
                break;
            case 'canceled':
            case 'unpaid':
                $newstatus = 'cancelled';
                break;
        }

        if ($newstatus && $newstatus !== $subscription->status) {
            $oldstatus = $subscription->status;

            $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
                'id' => $subscription->id,
                'status' => $newstatus,
                'timemodified' => time(),
            ]);

            self::log_subscription_action($subscription, 'stripe_status_changed', [
                'old_status' => $oldstatus,
                'new_status' => $newstatus,
                'stripe_status' => $stripesubscription->status,
            ]);
        }

        return true;
    }

    // PayPal handlers.

    /**
     * Handle PayPal BILLING.SUBSCRIPTION.ACTIVATED event.
     */
    protected static function handle_paypal_subscription_activated(object $resource): bool {
        global $DB;

        $paypalsubscriptionid = $resource->id ?? '';
        if (empty($paypalsubscriptionid)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'paypal_subscription_id' => $paypalsubscriptionid,
        ]);

        if (!$subscription) {
            return true;
        }

        if ($subscription->status !== 'active') {
            $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
                'id' => $subscription->id,
                'status' => 'active',
                'auto_renew' => 1,
                'payment_failed_count' => 0,
                'timemodified' => time(),
            ]);

            self::log_subscription_action($subscription, 'paypal_activated', [
                'paypal_subscription_id' => $paypalsubscriptionid,
            ]);
        }

        return true;
    }

    /**
     * Handle PayPal BILLING.SUBSCRIPTION.CANCELLED event.
     */
    protected static function handle_paypal_subscription_cancelled(object $resource): bool {
        global $DB;

        $paypalsubscriptionid = $resource->id ?? '';
        if (empty($paypalsubscriptionid)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'paypal_subscription_id' => $paypalsubscriptionid,
        ]);

        if (!$subscription) {
            return true;
        }

        subscription_service::cancel($subscription->id, 'Cancelled via PayPal');

        self::log_subscription_action($subscription, 'paypal_cancelled', [
            'paypal_subscription_id' => $paypalsubscriptionid,
        ]);

        return true;
    }

    /**
     * Handle PayPal BILLING.SUBSCRIPTION.SUSPENDED event.
     */
    protected static function handle_paypal_subscription_suspended(object $resource): bool {
        global $DB;

        $paypalsubscriptionid = $resource->id ?? '';
        if (empty($paypalsubscriptionid)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'paypal_subscription_id' => $paypalsubscriptionid,
        ]);

        if (!$subscription) {
            return true;
        }

        subscription_service::suspend($subscription->id);

        self::log_subscription_action($subscription, 'paypal_suspended', [
            'paypal_subscription_id' => $paypalsubscriptionid,
        ]);

        return true;
    }

    /**
     * Handle PayPal BILLING.SUBSCRIPTION.EXPIRED event.
     */
    protected static function handle_paypal_subscription_expired(object $resource): bool {
        global $DB;

        $paypalsubscriptionid = $resource->id ?? '';
        if (empty($paypalsubscriptionid)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'paypal_subscription_id' => $paypalsubscriptionid,
        ]);

        if (!$subscription) {
            return true;
        }

        $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
            'id' => $subscription->id,
            'status' => 'expired',
            'auto_renew' => 0,
            'timemodified' => time(),
        ]);

        access_service::revoke_plan_access(
            $subscription->userid,
            $subscription->planid
        );

        self::log_subscription_action($subscription, 'paypal_expired', [
            'paypal_subscription_id' => $paypalsubscriptionid,
        ]);

        return true;
    }

    /**
     * Handle PayPal PAYMENT.SALE.COMPLETED event.
     */
    protected static function handle_paypal_payment_completed(object $resource): bool {
        global $DB;

        $billingagreementid = $resource->billing_agreement_id ?? '';
        if (empty($billingagreementid)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'paypal_subscription_id' => $billingagreementid,
        ]);

        if (!$subscription) {
            return true;
        }

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid]);
        if (!$plan) {
            return true;
        }

        $now = time();
        $duration = subscription_service::get_cycle_duration($plan->billing_cycle);

        $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
            'id' => $subscription->id,
            'status' => 'active',
            'end_date' => $subscription->end_date + $duration,
            'next_billing_date' => $subscription->end_date + $duration,
            'renewal_count' => $subscription->renewal_count + 1,
            'payment_failed_count' => 0,
            'last_payment_attempt' => $now,
            'timemodified' => $now,
        ]);

        payment_retry_service::track_success($subscription->id);

        $amount = isset($resource->amount) && isset($resource->amount->total)
            ? $resource->amount->total
            : $plan->price;
        $currency = isset($resource->amount) && isset($resource->amount->currency)
            ? $resource->amount->currency
            : $plan->currency;

        self::log_subscription_action($subscription, 'paypal_renewed', [
            'transaction_id' => $resource->id ?? '',
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return true;
    }

    /**
     * Handle PayPal PAYMENT.SALE.DENIED/REFUNDED event.
     */
    protected static function handle_paypal_payment_failed(object $resource): bool {
        global $DB;

        $billingagreementid = $resource->billing_agreement_id ?? '';
        if (empty($billingagreementid)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'paypal_subscription_id' => $billingagreementid,
        ]);

        if (!$subscription) {
            return true;
        }

        $reason = $resource->reason_code ?? 'Payment failed';
        payment_retry_service::track_failure($subscription->id, 'paypal', $reason);

        return true;
    }

    // Paystack handlers.

    /**
     * Handle Paystack subscription.create event.
     */
    protected static function handle_paystack_subscription_created(object $data): bool {
        global $DB;

        $subscriptioncode = $data->subscription_code ?? '';
        $customeremail = $data->customer->email ?? '';

        if (empty($subscriptioncode) || empty($customeremail)) {
            return true;
        }

        // Find subscription by email (active or trial).
        $user = $DB->get_record('user', ['email' => $customeremail]);
        if (!$user) {
            return true;
        }

        $subscription = $DB->get_record_select(
            'local_moderncommerce_user_subscriptions',
            "userid = :userid AND status IN ('active', 'trial')",
            ['userid' => $user->id]
        );

        if (!$subscription) {
            return true;
        }

        // Store Paystack subscription code.
        $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
            'id' => $subscription->id,
            'paystack_subscription_code' => $subscriptioncode,
            'auto_renew' => 1,
            'timemodified' => time(),
        ]);

        self::log_subscription_action($subscription, 'paystack_created', [
            'subscription_code' => $subscriptioncode,
        ]);

        return true;
    }

    /**
     * Handle Paystack subscription.not_renew/disable event.
     */
    protected static function handle_paystack_subscription_cancelled(object $data): bool {
        global $DB;

        $subscriptioncode = $data->subscription_code ?? '';
        if (empty($subscriptioncode)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'paystack_subscription_code' => $subscriptioncode,
        ]);

        if (!$subscription) {
            return true;
        }

        subscription_service::cancel($subscription->id, 'Cancelled via Paystack');

        self::log_subscription_action($subscription, 'paystack_cancelled', [
            'subscription_code' => $subscriptioncode,
        ]);

        return true;
    }

    /**
     * Handle Paystack invoice.create/update event (renewal).
     */
    protected static function handle_paystack_invoice(object $data): bool {
        global $DB;

        // Only process paid invoices.
        if (($data->status ?? '') !== 'success' && ($data->paid ?? false) !== true) {
            return true;
        }

        $subscriptioncode = $data->subscription->subscription_code ?? $data->subscription_code ?? '';
        if (empty($subscriptioncode)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'paystack_subscription_code' => $subscriptioncode,
        ]);

        if (!$subscription) {
            return true;
        }

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid]);
        if (!$plan) {
            return true;
        }

        $now = time();
        $duration = subscription_service::get_cycle_duration($plan->billing_cycle);

        $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
            'id' => $subscription->id,
            'status' => 'active',
            'end_date' => $subscription->end_date + $duration,
            'next_billing_date' => $subscription->end_date + $duration,
            'renewal_count' => $subscription->renewal_count + 1,
            'payment_failed_count' => 0,
            'last_payment_attempt' => $now,
            'timemodified' => $now,
        ]);

        payment_retry_service::track_success($subscription->id);

        self::log_subscription_action($subscription, 'paystack_renewed', [
            'invoice_code' => $data->invoice_code ?? '',
            'amount' => isset($data->amount) ? $data->amount / 100 : $plan->price,
        ]);

        return true;
    }

    /**
     * Handle Paystack invoice.payment_failed event.
     */
    protected static function handle_paystack_payment_failed(object $data): bool {
        global $DB;

        $subscriptioncode = $data->subscription->subscription_code ?? $data->subscription_code ?? '';
        if (empty($subscriptioncode)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'paystack_subscription_code' => $subscriptioncode,
        ]);

        if (!$subscription) {
            return true;
        }

        payment_retry_service::track_failure($subscription->id, 'paystack', 'Invoice payment failed');

        return true;
    }

    // Flutterwave handlers.

    /**
     * Handle Flutterwave subscription.cancelled event.
     */
    protected static function handle_flutterwave_subscription_cancelled(object $data): bool {
        global $DB;

        $subscriptionid = $data->id ?? '';
        if (empty($subscriptionid)) {
            return true;
        }

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', [
            'flutterwave_subscription_id' => $subscriptionid,
        ]);

        if (!$subscription) {
            return true;
        }

        subscription_service::cancel($subscription->id, 'Cancelled via Flutterwave');

        self::log_subscription_action($subscription, 'flutterwave_cancelled', [
            'flutterwave_subscription_id' => $subscriptionid,
        ]);

        return true;
    }

    // Logging.

    /**
     * Log webhook event using paylog_service.
     *
     * @param string $gateway Gateway name.
     * @param string $eventtype Event type.
     * @param object|array $data Event data.
     */
    protected static function log_event(string $gateway, string $eventtype, $data): void {
        try {
            paylog_service::log(
                null,
                $gateway,
                'subscription_webhook_' . $eventtype,
                '',
                is_object($data) ? json_decode(json_encode($data), true) : $data
            );
        } catch (\Exception $e) {
            debugging('Failed to log webhook event: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Log subscription action to local_moderncommerce_subscription_log.
     *
     * @param object $subscription Subscription record.
     * @param string $action Action name.
     * @param array $details Additional details.
     */
    protected static function log_subscription_action(object $subscription, string $action, array $details = []): void {
        global $DB;

        try {
            $DB->insert_record('local_moderncommerce_subscription_log', [
                'subscriptionid' => $subscription->id,
                'userid' => $subscription->userid,
                'planid' => $subscription->planid,
                'action' => $action,
                'details' => json_encode($details),
                'timecreated' => time(),
            ]);
        } catch (\Exception $e) {
            debugging('Failed to log subscription action: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
