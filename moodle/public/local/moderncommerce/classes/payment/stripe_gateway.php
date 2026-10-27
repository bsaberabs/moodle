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

namespace local_moderncommerce\payment;


use local_moderncommerce\api\order_api;
use local_moderncommerce\logging\paylog_service;

/**
 * Stripe payment gateway
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stripe_gateway implements gateway_interface, gateway_return_interface {
    use subscription_mandate_trait;

    /** @var string Stripe API base URL */
    const API_URL = 'https://api.stripe.com/v1';

    /** @var string Gateway name */
    const GATEWAY_NAME = 'stripe';

    /**
     * Initialize payment with Stripe
     *
     * @param object $order Order record
     * @return array Payment initialization data
     */
    public function initialize_payment($order) {

        $config = $this->get_config();
        if (!$this->is_enabled()) {
            throw new \moodle_exception('gatewaydisabled', 'local_moderncommerce', '', $this->get_name());
        }

        try {
            $successurl = gateway_manager::callback_url(self::GATEWAY_NAME, [
                'orderid' => $order->id,
            ])->out(false);
            $successurl .= (strpos($successurl, '?') === false ? '?' : '&') . 'session_id={CHECKOUT_SESSION_ID}';
            $cancelurl = gateway_manager::callback_url(self::GATEWAY_NAME, [
                'orderid' => $order->id, 'status' => 'cancelled',
            ])->out(false);
            // Create Stripe checkout session.
            $sessiondata = [
                'payment_method_types[]' => 'card',
                'mode' => 'payment',
                'success_url' => $successurl,
                'cancel_url' => $cancelurl,
                'customer_email' => $this->resolve_order_email($order),
                'client_reference_id' => $order->ordernumber,
                'metadata[order_id]' => $order->id,
                'metadata[order_number]' => $order->ordernumber,
                'metadata[user_id]' => $order->userid,
                'payment_intent_data[metadata][order_id]' => $order->id,
                'payment_intent_data[metadata][order_number]' => $order->ordernumber,
                'payment_intent_data[metadata][user_id]' => $order->userid,
                'line_items[0][price_data][currency]' => strtolower($order->currency),
                'line_items[0][price_data][unit_amount]' => (int) round(((float) $order->total) * 100),
                'line_items[0][price_data][product_data][name]' => get_string('pluginname', 'local_moderncommerce') .
                    ' - Order ' . $order->ordernumber,
                'line_items[0][quantity]' => 1,
            ];

            // Subscription orders must leave a reusable mandate behind, otherwise
            // the renewal task has no card to charge later. Asking Stripe to set
            // the payment method up for off-session use also creates a Customer,
            // and both IDs are captured on return.
            if (self::order_is_subscription($order)) {
                $sessiondata['payment_intent_data[setup_future_usage]'] = 'off_session';
                $sessiondata['customer_creation'] = 'always';
            }

            $response = $this->make_request('POST', '/checkout/sessions', $sessiondata);

            if (!$response || empty($response['id'])) {
                throw new \moodle_exception(
                    'paymentinitfailed',
                    'local_moderncommerce',
                    '',
                    null,
                    $response['error']['message'] ?? get_string('p1_payment_unknownerror', 'local_moderncommerce')
                );
            }
            // Log transaction.
            $this->log_transaction($order->id, 'initialize', $response);

            return [
                'success' => true,
                'authorization_url' => $response['url'],
                'reference' => $order->ordernumber,
                'session_id' => $response['id'],
            ];
        } catch (\Exception $e) {
            $debuginfo = $e instanceof \moodle_exception && !empty($e->debuginfo)
                ? $e->debuginfo
                : $e->getMessage();
            throw new \moodle_exception('paymentinitfailed', 'local_moderncommerce', '', null, $debuginfo);
        }
    }
    /**
     * Verify payment with Stripe
     *
     * @param string $sessionid Stripe session ID
     * @return object Payment verification data
     */
    public function verify_payment($sessionid) {

        try {
            $response = $this->make_request('GET', '/checkout/sessions/' . rawurlencode((string)$sessionid));
            if (!$response || ($response['payment_status'] ?? '') !== 'paid') {
                $detail = 'Stripe Checkout session is not paid';
                if (!empty($response['status']) || !empty($response['payment_status'])) {
                    $detail .= ' (status: ' . ($response['status'] ?? 'unknown') .
                        ', payment_status: ' . ($response['payment_status'] ?? 'unknown') . ')';
                }
                throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce', '', $detail);
            }

            // Get payment intent for additional details.
            if (!empty($response['payment_intent'])) {
                $intent = $this->make_request('GET', '/payment_intents/' . rawurlencode((string)$response['payment_intent']));
                $response['payment_intent_details'] = $intent;
            }

            return (object) $response;
        } catch (\Exception $e) {
            if ($e instanceof \moodle_exception) {
                throw $e;
            }
            throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce', '', $e->getMessage());
        }
    }
    /**
     * Handle Stripe webhook events
     *
     * @param array $data Webhook payload
     * @return bool
     */
    public function handle_webhook($data) {
        $eventtype = $data['type'] ?? '';

        switch ($eventtype) {
            case 'checkout.session.completed':
                return $this->handle_checkout_session_completed($data['data']['object'], $eventtype);
            case 'charge.succeeded':
                return $this->handle_payment_success($data['data']['object'], $eventtype);
            case 'charge.failed':
                return $this->handle_payment_failed($data['data']['object'], $eventtype);
            case 'charge.refunded':
                return $this->handle_refund($data['data']['object'], $eventtype);
            // Subscription events - delegate to modernsubscription if available.
            case 'invoice.paid':
            case 'invoice.payment_failed':
            case 'customer.subscription.deleted':
            case 'customer.subscription.updated':
                return $this->handle_subscription_event($eventtype, $data['data']['object'] ?? []);

            default:
                // Log unknown events.
                $this->log_webhook('unknown', $data);
                return true;
        }
    }

    /**
     * Handle subscription-related webhook events.
     *
     * Delegates to local_moderncommerce webhook service if available.
     *
     * @param string $eventtype Event type.
     * @param array $data Event data.
     * @return bool
     */
    protected function handle_subscription_event($eventtype, $data) {
        // Check if subscription plugin is available.
        if (class_exists('\local_moderncommerce\subscription\services\webhook_service')) {
            return \local_moderncommerce\subscription\services\webhook_service::handle_stripe_event($eventtype, $data);
        }

        // Log event if subscription plugin not available.
        $this->log_webhook($eventtype, $data);
        return true;
    }

    /**
     * Process webhook (required by interface)
     *
     * @param array $payload Webhook payload
     * @return bool
     */
    public function process_webhook($payload, array $headers = [], ?string $rawpayload = null) {

        $this->verify_webhook_signature($headers, $rawpayload);
        return $this->handle_webhook($payload);
    }

    /**
     * Process the hosted Stripe return.
     *
     * @param array $params Return request parameters.
     * @return \stdClass Normalized payment result.
     */
    public function process_return(array $params): \stdClass {

        if (($params['status'] ?? '') === 'cancelled') {
            return (object) [
                'status' => 'cancelled',
                'orderid' => null,
                'orderreference' => null,
                'gatewayreference' => null,
                'gatewaytransactionid' => null,
                'message' => get_string('paymentcancelled', 'local_moderncommerce'),
                'rawdata' => $params,
            ];
        }

        $sessionid = $this->resolve_return_session_id($params);
        if (empty($sessionid)) {
            throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce', '', 'Missing Stripe session ID');
        }
        if (strpos($sessionid, 'CHECKOUT_SESSION_ID') !== false) {
            throw new \moodle_exception(
                'paymentverifyfailed',
                'local_moderncommerce',
                '',
                'Stripe did not return the Checkout session ID'
            );
        }

        $session = $this->verify_payment($sessionid);
        $rawdata = json_decode(json_encode($session), true);
        $paymentintent = $session->payment_intent ?? null;
        if (!empty($rawdata['payment_intent_details']['id'])) {
            $paymentintent = $rawdata['payment_intent_details']['id'];
        }

        // Store the reusable mandate before the order is fulfilled, so the
        // subscription created by the payment observer already has a card on
        // file for its first renewal.
        $this->store_mandate_fields((int) ($params['orderid'] ?? 0), [
            'stripe_customer_id' => $rawdata['customer'] ?? '',
            'stripe_payment_method_id' => $rawdata['payment_intent_details']['payment_method'] ?? '',
        ]);

        return (object) [
            'status' => ($session->payment_status ?? '') === 'paid' ? 'success' : 'failed',
            'orderid' => null,
            'orderreference' => $session->client_reference_id ?? null,
            'gatewayreference' => $sessionid,
            'gatewaytransactionid' => $paymentintent,
            'amount' => !empty($session->amount_total) ? ($session->amount_total / 100) : null,
            'currency' => !empty($session->currency) ? strtoupper($session->currency) : null,
            'paidat' => !empty($session->created) ? (int)$session->created : null,
            'channel' => 'card',
            'message' => $session->payment_status ?? '',
            'rawdata' => $rawdata,
        ];
    }

    /**
     * Resolve the Checkout session ID from the return URL.
     *
     * Older generated Stripe sessions encoded the {CHECKOUT_SESSION_ID} placeholder in the success URL. If that
     * happens, recover from the pending attempt for the current user/order instead of leaving a paid order pending.
     *
     * @param array $params Return request parameters.
     * @return string Stripe Checkout session ID or unresolved incoming value.
     */
    private function resolve_return_session_id(array $params): string {

        global $DB, $USER;
        $sessionid = trim((string)($params['session_id'] ?? ''));
        if ($sessionid !== '' && strpos($sessionid, 'CHECKOUT_SESSION_ID') === false) {
            return $sessionid;
        }

        if (empty($USER->id)) {
            return $sessionid;
        }

        $conditions = [
            'gateway' => self::GATEWAY_NAME, 'userid' => (int)$USER->id, 'prefix' => 'cs%', 'since' => time() - (24 * 60 * 60),
        ];
        $ordersql = '';
        $statussql = '';
        $limit = 2;
        if (!empty($params['orderid'])) {
            $conditions['orderid'] = (int)$params['orderid'];
            $ordersql = ' AND o.id = :orderid';
            $limit = 1;
        } else {
            $conditions['status'] = 'pending';
            $statussql = ' AND pa.status = :status';
        }

        $sql = "SELECT pa.*
                  FROM {local_moderncommerce_payment_attempts} pa
                  JOIN {local_moderncommerce_orders} o ON o.id = pa.orderid
                 WHERE pa.gateway = :gateway
                   {$statussql}
                   AND pa.gatewaytransactionid LIKE :prefix
                   AND pa.timecreated >= :since
                   AND o.userid = :userid
                   {$ordersql}
              ORDER BY pa.id DESC";
        $attempts = $DB->get_records_sql($sql, $conditions, 0, $limit);
        if (!empty($params['orderid']) && !empty($attempts)) {
            $attempt = reset($attempts);
            return !empty($attempt->gatewaytransactionid) ? (string)$attempt->gatewaytransactionid : $sessionid;
        }

        if (count($attempts) === 1) {
            $attempt = reset($attempts);
            return !empty($attempt->gatewaytransactionid) ? (string)$attempt->gatewaytransactionid : $sessionid;
        }

        return $sessionid;
    }

    /**
     * Charge a saved card off-session, for subscription renewals.
     *
     * Uses the same REST transport as the rest of this gateway rather than the
     * Stripe PHP SDK, which this plugin does not bundle.
     *
     * @param string $customerid Stripe customer ID.
     * @param string $paymentmethodid Stripe payment method ID.
     * @param float $amount Amount in major units.
     * @param string $currency ISO currency code.
     * @param string $description Statement description.
     * @param array $metadata Extra metadata to attach.
     * @return array{success: bool, reference: string, error: string, requiresaction: bool}
     */
    public function charge_saved_card(
        string $customerid,
        string $paymentmethodid,
        float $amount,
        string $currency,
        string $description = '',
        array $metadata = []
    ): array {
        $result = ['success' => false, 'reference' => '', 'error' => '', 'requiresaction' => false];

        if ($customerid === '' || $paymentmethodid === '' || $amount <= 0) {
            $result['error'] = 'Missing customer, payment method, or amount';
            return $result;
        }

        $payload = [
            'amount' => (int) round($amount * 100),
            'currency' => strtolower($currency ?: 'usd'),
            'customer' => $customerid,
            'payment_method' => $paymentmethodid,
            'off_session' => 'true',
            'confirm' => 'true',
        ];
        if ($description !== '') {
            $payload['description'] = $description;
        }
        foreach ($metadata as $key => $value) {
            $payload['metadata[' . $key . ']'] = (string) $value;
        }

        try {
            $response = $this->make_request('POST', '/payment_intents', $payload);
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }

        if (!empty($response['error'])) {
            $result['error'] = (string) ($response['error']['message'] ?? 'Stripe declined the payment');
            return $result;
        }

        $status = (string) ($response['status'] ?? '');
        $result['reference'] = (string) ($response['id'] ?? '');

        if ($status === 'succeeded') {
            $result['success'] = true;
            return $result;
        }

        // The card needs the buyer present (typically 3-D Secure); this is not a
        // hard decline, so the caller should prompt rather than count a failure.
        if ($status === 'requires_action' || $status === 'requires_confirmation') {
            $result['requiresaction'] = true;
            $result['error'] = 'Payment requires customer authentication';
            return $result;
        }

        $result['error'] = $status !== '' ? 'Payment status: ' . $status : 'Payment failed';
        return $result;
    }

    /**
     * Resolve the customer email from canonical and legacy order fields.
     *
     * @param object $order Order record.
     * @return string Customer email.
     */
    private function resolve_order_email(object $order): string {

        return (string) (
        $order->customeremail ?? $order->billingemail ?? $order->email ?? ''
        );
    }

    /**
     * Verify Stripe webhook signature when a signing secret is configured.
     *
     * @param array $headers Request headers.
     * @param string|null $rawpayload Raw request body.
     */
    private function verify_webhook_signature(array $headers, ?string $rawpayload): void {

        $config = $this->get_config();
        $secret = trim((string)($config['webhook_secret'] ?? ''));
        if ($secret === '') {
            throw new \moodle_exception('invalidwebhooksignature', 'local_moderncommerce');
        }

        $signature = self::get_header($headers, 'Stripe-Signature');
        if ($signature === '' && !empty($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
            $signature = (string)$_SERVER['HTTP_STRIPE_SIGNATURE'];
        }

        if ($signature === '' || $rawpayload === null || $rawpayload === '') {
            throw new \moodle_exception('invalidwebhooksignature', 'local_moderncommerce');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$key, $value] = $pair;
            if ($key === 't') {
                $timestamp = (int)$value;
            } else if ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (empty($timestamp) || empty($signatures)) {
            throw new \moodle_exception('invalidwebhooksignature', 'local_moderncommerce');
        }

        $tolerance = 300;
        if (abs(time() - $timestamp) > $tolerance) {
            throw new \moodle_exception('invalidwebhooksignature', 'local_moderncommerce');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawpayload, $secret);
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return;
            }
        }

        throw new \moodle_exception('invalidwebhooksignature', 'local_moderncommerce');
    }

    /**
     * Read a header by name from a case-insensitive header array.
     *
     * @param array $headers Request headers.
     * @param string $name Header name.
     * @return string Header value.
     */
    private static function get_header(array $headers, string $name): string {

        $target = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === $target) {
                return is_array($value) ? implode(',', $value) : (string)$value;
            }
        }

        return '';
    }

    /**
     * Handle completed Stripe Checkout session webhooks.
     *
     * @param array $session Checkout session data.
     * @param string $eventtype Event type.
     * @return bool Success.
     */
    protected function handle_checkout_session_completed($session, string $eventtype = 'checkout.session.completed') {

        global $DB;
        $sessionid = $session['id'] ?? '';
        if ($sessionid === '') {
            return false;
        }

        if (($session['payment_status'] ?? '') !== 'paid') {
            $this->log_webhook($eventtype, $session);
            return true;
        }

        $metadata = $session['metadata'] ?? [];
        $orderid = !empty($metadata['order_id']) ? (int)$metadata['order_id'] : 0;
        $order = false;
        if ($orderid > 0) {
            $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        }

        if (!$order && !empty($session['client_reference_id'])) {
            $order = $DB->get_record('local_moderncommerce_orders', ['ordernumber' => $session['client_reference_id']]);
        }

        if (!$order) {
            return false;
        }

        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'success',
            'orderid' => $order->id,
            'orderreference' => $order->ordernumber,
            'gatewayreference' => $sessionid,
            'gatewaytransactionid' => $session['payment_intent'] ?? $sessionid,
            'amount' => isset($session['amount_total']) ? ((float)$session['amount_total'] / 100) : null,
            'currency' => !empty($session['currency']) ? strtoupper($session['currency']) : null,
            'paidat' => $session['created'] ?? time(),
            'channel' => 'card',
            'message' => $session['payment_status'] ?? '',
            'source' => 'webhook',
            'eventtype' => $eventtype,
            'gatewayeventid' => $sessionid,
            'rawdata' => $session,
        ]);
        order_api::update_order_status($order->id, 'paid');
        $this->log_webhook($eventtype, $session);
        return true;
    }

    /**
     * Handle successful charge webhook
     *
     * @param array $charge Charge data
     * @return bool
     */
    protected function handle_payment_success($charge, string $eventtype = 'charge.succeeded') {

        global $DB;
        $reference = $charge['id'] ?? '';
        if (empty($reference)) {
            return false;
        }

        // Find order by metadata or description.
        $metadata = $charge['metadata'] ?? [];
        $orderid = $metadata['order_id'] ?? null;

        if (!$orderid) {
            return false;
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        if (!$order) {
            return false;
        }

        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'success',
            'orderid' => $order->id,
            'orderreference' => $order->ordernumber,
            'gatewayreference' => $reference,
            'gatewaytransactionid' => $reference,
            'amount' => isset($charge['amount']) ? ((float)$charge['amount'] / 100) : null,
            'currency' => !empty($charge['currency']) ? strtoupper($charge['currency']) : null,
            'paidat' => $charge['created'] ?? time(),
            'channel' => $charge['payment_method_details']['type'] ?? null,
            'message' => $charge['status'] ?? '',
            'source' => 'webhook',
            'eventtype' => $eventtype,
            'gatewayeventid' => $charge['id'] ?? null,
            'rawdata' => $charge,
        ]);
        // Update order status.
        order_api::update_order_status($order->id, 'paid');

        // Log webhook.
        $this->log_webhook('charge.succeeded', $charge);

        return true;
    }

    /**
     * Handle failed charge webhook
     *
     * @param array $charge Charge data
     * @return bool
     */
    protected function handle_payment_failed($charge, string $eventtype = 'charge.failed') {

        global $DB;
        $reference = $charge['id'] ?? '';
        if (empty($reference)) {
            return false;
        }

        $metadata = $charge['metadata'] ?? [];
        $orderid = $metadata['order_id'] ?? null;

        if (!$orderid) {
            return false;
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        if (!$order) {
            return false;
        }

        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'failed',
            'orderid' => $order->id,
            'orderreference' => $order->ordernumber,
            'gatewayreference' => $reference,
            'gatewaytransactionid' => $reference,
            'amount' => isset($charge['amount']) ? ((float)$charge['amount'] / 100) : null,
            'currency' => !empty($charge['currency']) ? strtoupper($charge['currency']) : null,
            'message' => $charge['failure_message'] ?? get_string('paymentfailed', 'local_moderncommerce'),
            'source' => 'webhook',
            'eventtype' => $eventtype,
            'gatewayeventid' => $charge['id'] ?? null,
            'rawdata' => $charge,
        ]);
        // Update order status.
        order_api::update_order_status($order->id, 'failed');

        // Log webhook.
        $this->log_webhook('charge.failed', $charge);

        return true;
    }

    /**
     * Handle refund webhook
     *
     * @param array $charge Charge data
     * @return bool
     */
    protected function handle_refund($charge, string $eventtype = 'charge.refunded') {

        global $DB;
        $reference = $charge['id'] ?? '';
        if (empty($reference)) {
            return false;
        }

        $metadata = $charge['metadata'] ?? [];
        $orderid = $metadata['order_id'] ?? null;

        if (!$orderid) {
            return false;
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        if (!$order) {
            return false;
        }

        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'refunded',
            'orderid' => $order->id,
            'orderreference' => $order->ordernumber,
            'gatewayreference' => $reference,
            'gatewaytransactionid' => $reference,
            'amount' => isset($charge['amount_refunded']) ? ((float)$charge['amount_refunded'] / 100) : null,
            'currency' => !empty($charge['currency']) ? strtoupper($charge['currency']) : null,
            'message' => $charge['status'] ?? 'refunded',
            'source' => 'webhook',
            'eventtype' => $eventtype,
            'gatewayeventid' => $charge['id'] ?? null,
            'rawdata' => $charge,
        ]);
        // Update order status.
        order_api::update_order_status($order->id, 'refunded');

        // Log webhook.
        $this->log_webhook('charge.refunded', $charge);

        return true;
    }

    /**
     * Make API request to Stripe
     *
     * @param string $method HTTP method (GET, POST)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     */
    protected function make_request($method, $endpoint, $data = []) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $config = $this->get_config();
        $apikey = $config['secret_key'];

        $url = self::API_URL . $endpoint;

        $curl = new \curl();
        $options = [
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_HTTPAUTH' => CURLAUTH_BASIC,
            'CURLOPT_USERPWD' => $apikey . ':',
        ];

        if ($method === 'POST') {
            $response = $curl->post($url, http_build_query($data), $options);
        } else {
            $response = $curl->get($url, [], $options);
        }

        $error = $curl->get_errno() ? $curl->error : '';
        $httpcode = (int)$curl->get_info()['http_code'];
        if ($error) {
            throw new \moodle_exception('apirequestfailed', 'local_moderncommerce', '', $error, $error);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('apirequestfailed', 'local_moderncommerce', '', 'Stripe returned an invalid response');
        }

        if (!empty($decoded['error'])) {
            $message = $decoded['error']['message'] ?? 'Stripe API error';
            throw new \moodle_exception('apirequestfailed', 'local_moderncommerce', '', $message);
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            throw new \moodle_exception('apirequestfailed', 'local_moderncommerce', '', 'Stripe returned HTTP ' . $httpcode);
        }

        return $decoded;
    }
    /**
     * Log transaction
     *
     * @param int $orderid Order ID
     * @param string $action Action type
     * @param array $response Response data
     */
    protected function log_transaction($orderid, $action, $response) {
        global $DB;

        // Get order to extract reference.
        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid], 'ordernumber');
        $reference = $order ? $order->ordernumber : '';

        // Use paylog_service to log to paylog table.
        paylog_service::log($orderid, $this->get_name(), $action, $reference, $response);
    }

    /**
     * Log webhook event
     *
     * @param string $event Event type
     * @param array $data Event data
     */
    protected function log_webhook($event, $data) {
        // Extract order_id and reference from webhook data if available.
        $orderid = null;
        $reference = '';

        if (isset($data['metadata']['order_id'])) {
            $orderid = $data['metadata']['order_id'];
        }

        if (isset($data['metadata']['order_number'])) {
            $reference = $data['metadata']['order_number'];
        } else if (isset($data['id'])) {
            $reference = $data['id'];
        }

        // Use paylog_service to log to paylog table.
        paylog_service::log($orderid, $this->get_name(), 'webhook_' . $event, $reference, $data);
    }

    /**
     * Get gateway configuration
     *
     * @return array
     */
    public function get_config() {

        return gateway_manager::get_gateway_config(self::GATEWAY_NAME);
    }
    /**
     * Get gateway name
     *
     * @return string
     */
    public function get_name() {
        return self::GATEWAY_NAME;
    }

    /**
     * Check if gateway is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        $config = $this->get_config();
        return !empty($config['enabled']) && !empty($config['secret_key']);
    }

    /**
     * Get supported currencies
     *
     * @return array
     */
    public function get_supported_currencies() {
        // Stripe supports 135+ currencies.
        return ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'CNY', 'INR', 'NGN',
                'ZAR', 'BRL', 'MXN', 'AED', 'SGD', 'HKD', 'NZD', 'SEK', 'NOK', 'DKK'];
    }
}
