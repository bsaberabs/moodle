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
 * Paystack payment gateway
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class paystack_gateway implements gateway_interface, gateway_return_interface {
    use subscription_mandate_trait;

    /** @var string Paystack API base URL */
    const API_URL = 'https://api.paystack.co';

    /** @var string Gateway name */
    const GATEWAY_NAME = 'paystack';

    /**
     * Initialize payment with Paystack
     *
     * @param object $order Order record
     * @return array Payment initialization data
     */
    public function initialize_payment($order) {
        global $CFG, $DB;

        $config = $this->get_config();

        if (!$this->is_enabled()) {
            throw new \moodle_exception('gatewaydisabled', 'local_moderncommerce', '', $this->get_name());
        }

        // Convert amount to kobo (Paystack uses smallest currency unit).
        $amount = (int) round(((float) $order->total) * 100);
        // Generate a unique reference for this payment attempt.
        // For retry attempts, append a timestamp to ensure uniqueness.
        $attemptstable = new \xmldb_table('local_moderncommerce_payment_attempts');
        $existingtransactions = $DB->get_manager()->table_exists($attemptstable)
            ? $DB->count_records('local_moderncommerce_payment_attempts', [
                'orderid' => $order->id,
                'gateway' => self::GATEWAY_NAME,
            ])
            : 0;
        if ($existingtransactions > 0) {
            // This is a retry - generate a unique reference.
            $reference = $order->ordernumber . '-R' . ($existingtransactions + 1);
        } else {
            $reference = $order->ordernumber;
        }

        // Use the order currency snapshot. Gateway readiness guards ensure it is valid.
        $currency = strtoupper((string)$order->currency);
        $data = [
            'email' => $this->resolve_order_email($order),
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => gateway_manager::callback_url(self::GATEWAY_NAME)->out(false),
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->ordernumber,
                'user_id' => $order->userid,
                'custom_fields' => [
                    [
                        'display_name' => 'Order Number',
                        'variable_name' => 'order_number',
                        'value' => $order->ordernumber,
                    ],
                ],
            ],
        ];

        $response = $this->make_request('POST', '/transaction/initialize', $data);

        if (!$response || !isset($response['status']) || !$response['status']) {
            debugging('Paystack API error response: ' . json_encode($response), DEBUG_DEVELOPER);
            debugging('Paystack init request data: ' . json_encode([
                'email' => $this->resolve_order_email($order),
                'amount' => $amount,
                'currency' => $currency,
                'reference' => $reference,
            ]), DEBUG_DEVELOPER);
            $errormsg = $response['message'] ?? get_string('p1_payment_unknownerror', 'local_moderncommerce');
            throw new \moodle_exception('paymentinitfailed', 'local_moderncommerce', '', $errormsg);
        }

        // Log transaction.
        $this->log_transaction($order->id, 'initialize', $response);

        return [
            'success' => true,
            'authorization_url' => $response['data']['authorization_url'],
            'access_code' => $response['data']['access_code'],
            'reference' => $response['data']['reference'],
        ];
    }

    /**
     * Verify payment with Paystack
     *
     * @param string $reference Payment reference
     * @return object Payment verification data
     */
    public function verify_payment($reference) {
        $response = $this->make_request('GET', '/transaction/verify/' . $reference);

        if (!$response || !isset($response['status']) || !$response['status']) {
            throw new \moodle_exception(
                'paymentverifyfailed',
                'local_moderncommerce',
                '',
                $response['message'] ?? get_string('p1_payment_unknownerror', 'local_moderncommerce')
            );
        }

        $data = $response['data'];

        return (object)[
            'status' => $data['status'],
            'reference' => $data['reference'],
            'amount' => $data['amount'] / 100, // Convert from kobo.
            'currency' => $data['currency'],
            'paid_at' => $data['paid_at'],
            'channel' => $data['channel'],
            'gateway_response' => $data['gateway_response'],
            'authorization' => $data['authorization'] ?? null,
            'raw_data' => $data,
        ];
    }

    /**
     * Process Paystack webhook
     *
     * @param array $payload Webhook payload
     * @return bool Success status
     */
    public function process_webhook($payload, array $headers = [], ?string $rawpayload = null) {

        global $DB;

        // Verify webhook signature.
        if (!$this->verify_webhook_signature($headers, $rawpayload)) {
            return false;
        }
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        switch ($event) {
            case 'charge.success':
                return $this->handle_payment_success($data, $event);
            case 'charge.failed':
                return $this->handle_payment_failed($data, $event);
            case 'transfer.success':
            case 'transfer.failed':
            case 'transfer.reversed':
                return $this->handle_transfer_event($event, $data);

            // Subscription events - delegate to modernsubscription if available.
            case 'subscription.create':
            case 'subscription.not_renew':
            case 'subscription.disable':
            case 'invoice.create':
            case 'invoice.update':
            case 'invoice.payment_failed':
                return $this->handle_subscription_event($event, $data);

            default:
                // Log unhandled events.
                $this->log_webhook($event, $data);
                return true;
        }
    }

    /**
     * Process the hosted Paystack return.
     *
     * @param array $params Return request parameters.
     * @return \stdClass Normalized payment result.
     */
    public function process_return(array $params): \stdClass {

        $reference = $params['reference'] ?? '';
        if (empty($reference)) {
            throw new \moodle_exception(
                'paymentverifyfailed',
                'local_moderncommerce',
                '',
                get_string('p1_paystack_missingreference', 'local_moderncommerce')
            );
        }

        $verification = $this->verify_payment($reference);

        // Paystack returns a reusable authorization for card charges. Storing it
        // is what lets the renewal task charge the same card again later; a
        // non-reusable channel (bank transfer, USSD) simply has nothing to save.
        $authorization = $verification->authorization ?? null;
        if (is_array($authorization) && !empty($authorization['reusable'])) {
            $this->store_mandate_fields((int) ($params['orderid'] ?? 0), [
                'paystack_authorization_code' => $authorization['authorization_code'] ?? '',
                'paystack_email' => $verification->raw_data['customer']['email'] ?? '',
            ]);
        }

        return (object) [
            'status' => $verification->status === 'success' ? 'success' : 'failed',
            'orderid' => null,
            'orderreference' => $reference,
            'gatewayreference' => $verification->reference ?? $reference,
            'gatewaytransactionid' => $verification->raw_data['id'] ?? null,
            'amount' => $verification->amount ?? null,
            'currency' => $verification->currency ?? null,
            'paidat' => !empty($verification->paid_at) ? strtotime($verification->paid_at) : null,
            'channel' => $verification->channel ?? null,
            'message' => $verification->gateway_response ?? '',
            'rawdata' => $verification->raw_data ?? [],
        ];
    }
    /**
     * Handle subscription-related webhook events.
     *
     * Delegates to local_moderncommerce webhook service if available.
     *
     * @param string $event Event type.
     * @param array $data Event data.
     * @return bool
     */
    protected function handle_subscription_event($event, $data) {
        // Check if subscription plugin is available.
        if (class_exists('\local_moderncommerce\subscription\services\webhook_service')) {
            return \local_moderncommerce\subscription\services\webhook_service::handle_paystack_event($event, $data);
        }

        // Log event if subscription plugin not available.
        $this->log_webhook($event, $data);
        return true;
    }

    /**
     * Verify webhook signature
     *
     * @return bool
     */
    protected function verify_webhook_signature(array $headers = [], ?string $rawpayload = null): bool {

        $config = $this->get_config();
        if (empty($config['secret_key'])) {
            return false;
        }

        // Verify IP whitelist if enabled.
        if (!$this->verify_ip_whitelist()) {
            return false;
        }

        $input = $rawpayload ?? file_get_contents('php://input');
        $signature = self::get_header($headers, 'X-Paystack-Signature');
        if ($signature === '' && !empty($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) {
            $signature = (string) $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'];
        }

        if ($signature === '' || $input === null || $input === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $input, $config['secret_key']), $signature);
    }

    /**
     * Resolve a request header case-insensitively.
     *
     * @param array $headers Request headers.
     * @param string $name Header name.
     * @return string Header value.
     */
    protected static function get_header(array $headers, string $name): string {

        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return is_array($value) ? (string) reset($value) : (string) $value;
            }
        }

        return '';
    }

    /**
     * Resolve the customer email from canonical and legacy order fields.
     *
     * @param object $order Order record.
     * @return string Customer email.
     */
    protected function resolve_order_email(object $order): string {

        return (string) (
            $order->customeremail ?? $order->billingemail ?? $order->email ?? ''
        );
    }
    /**
     * Verify webhook request comes from allowed Paystack IPs.
     *
     * @return bool True if IP is allowed or whitelist is disabled.
     */
    protected function verify_ip_whitelist(): bool {
        $enabled = get_config('local_moderncommerce', 'enable_webhook_ip_whitelist');
        if (empty($enabled)) {
            return true;
        }

        $config = $this->get_config();
        $whitelist = $config['ip_whitelist'] ?? '';
        if (empty($whitelist)) {
            return true;
        }

        $allowedips = array_filter(array_map('trim', explode("\n", $whitelist)));
        if (empty($allowedips)) {
            return true;
        }

        $clientip = $this->get_client_ip();
        return in_array($clientip, $allowedips, true);
    }

    /**
     * Get client IP address.
     *
     * @return string Client IP.
     */
    protected function get_client_ip(): string {
        // Check for forwarded IP (behind proxy/load balancer).
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Handle successful payment
     *
     * @param array $data Payment data
     * @return bool
     */
    protected function handle_payment_success($data, string $eventtype = 'charge.success') {

        global $DB;
        $reference = $data['reference'] ?? '';
        if (empty($reference)) {
            return false;
        }

        // Find order by reference (order number).
        $order = $DB->get_record('local_moderncommerce_orders', ['ordernumber' => $reference]);
        if (!$order) {
            return false;
        }

        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'success',
            'orderid' => $order->id,
            'orderreference' => $reference,
            'gatewayreference' => $data['reference'] ?? $reference,
            'gatewaytransactionid' => $data['id'] ?? null,
            'amount' => isset($data['amount']) ? ((float)$data['amount'] / 100) : null,
            'currency' => $data['currency'] ?? null,
            'paidat' => !empty($data['paid_at']) ? strtotime($data['paid_at']) : time(),
            'channel' => $data['channel'] ?? null,
            'message' => $data['gateway_response'] ?? '',
            'source' => 'webhook',
            'eventtype' => $eventtype,
            'gatewayeventid' => $data['id'] ?? null,
            'rawdata' => $data,
        ]);
        // Update order status.
        order_api::update_order_status($order->id, 'paid');

        return true;
    }

    /**
     * Handle failed payment
     *
     * @param array $data Payment data
     * @return bool
     */
    protected function handle_payment_failed($data, string $eventtype = 'charge.failed') {

        global $DB;
        $reference = $data['reference'] ?? '';
        if (empty($reference)) {
            return false;
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['ordernumber' => $reference]);
        if (!$order) {
            return false;
        }

        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'failed',
            'orderid' => $order->id,
            'orderreference' => $reference,
            'gatewayreference' => $data['reference'] ?? $reference,
            'gatewaytransactionid' => $data['id'] ?? null,
            'amount' => isset($data['amount']) ? ((float)$data['amount'] / 100) : null,
            'currency' => $data['currency'] ?? null,
            'message' => $data['gateway_response'] ?? get_string('paymentfailed', 'local_moderncommerce'),
            'source' => 'webhook',
            'eventtype' => $eventtype,
            'gatewayeventid' => $data['id'] ?? null,
            'rawdata' => $data,
        ]);
        // Update order status.
        order_api::update_order_status($order->id, 'failed');

        return true;
    }

    /**
     * Make API request to Paystack
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     */
    /**
     * Charge a stored Paystack authorization, for subscription renewals.
     *
     * @param string $authorizationcode Reusable authorization code.
     * @param string $email Customer email the authorization belongs to.
     * @param float $amount Amount in major units.
     * @param string $currency ISO currency code.
     * @param array $metadata Extra metadata to attach.
     * @return array{success: bool, reference: string, error: string, requiresaction: bool}
     */
    public function charge_saved_authorization(
        string $authorizationcode,
        string $email,
        float $amount,
        string $currency,
        array $metadata = []
    ): array {
        $result = ['success' => false, 'reference' => '', 'error' => '', 'requiresaction' => false];

        if ($authorizationcode === '' || $email === '' || $amount <= 0) {
            $result['error'] = 'Missing authorization, email, or amount';
            return $result;
        }

        $payload = [
            'authorization_code' => $authorizationcode,
            'email' => $email,
            // Paystack works in the currency's minor unit, as with the initial charge.
            'amount' => (int) round($amount * 100),
            'currency' => strtoupper($currency ?: 'NGN'),
        ];
        if (!empty($metadata)) {
            $payload['metadata'] = json_encode($metadata);
        }

        try {
            $response = $this->make_request('POST', '/transaction/charge_authorization', $payload);
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }

        if (empty($response['status'])) {
            $result['error'] = (string) ($response['message'] ?? 'Paystack declined the charge');
            return $result;
        }

        $data = $response['data'] ?? [];
        $result['reference'] = (string) ($data['reference'] ?? '');

        if (($data['status'] ?? '') === 'success') {
            $result['success'] = true;
            return $result;
        }

        $result['error'] = (string) ($data['gateway_response'] ?? 'Payment failed');
        return $result;
    }

    /**
     * Make an authenticated request to the Paystack API.
     *
     * @param string $method HTTP method.
     * @param string $endpoint API endpoint path.
     * @param array $data Request payload.
     * @return array Decoded response.
     */
    protected function make_request($method, $endpoint, $data = []) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $config = $this->get_config();

        if (empty($config['secret_key'])) {
            throw new \moodle_exception('gatewaynotconfigured', 'local_moderncommerce', '', $this->get_name());
        }

        $url = self::API_URL . $endpoint;
        $headers = [
            'Authorization: Bearer ' . $config['secret_key'],
            'Content-Type: application/json',
            'Cache-Control: no-cache',
        ];

        $curl = new \curl();
        $curl->setHeader($headers);
        $options = [
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_TIMEOUT' => 30,
        ];

        if ($method === 'POST') {
            $response = $curl->post($url, json_encode($data), $options);
        } else {
            $response = $curl->get($url, [], $options);
        }

        if ($curl->get_errno()) {
            throw new \moodle_exception('apirequestfailed', 'local_moderncommerce', '', $curl->error);
        }

        return json_decode($response, true);
    }

    /**
     * Log transaction
     *
     * @param int $orderid Order ID
     * @param string $action Action type
     * @param array $response Response data
     */
    protected function log_transaction($orderid, $action, $response) {
        // Extract reference from response if available.
        $reference = '';
        if (isset($response['data']['reference'])) {
            $reference = $response['data']['reference'];
        }

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
        // Extract order_id from webhook data if available.
        $orderid = null;
        if (isset($data['order_id'])) {
            $orderid = $data['order_id'];
        } else if (isset($data['metadata']['order_id'])) {
            $orderid = $data['metadata']['order_id'];
        }

        // Extract reference if available.
        $reference = $data['reference'] ?? '';

        // Use paylog_service to log to paylog table.
        paylog_service::log($orderid, $this->get_name(), 'webhook_' . $event, $reference, $data);
    }

    /**
     * Handle transfer events (for refunds/payouts)
     *
     * @param string $event Event type
     * @param array $data Event data
     * @return bool
     */
    protected function handle_transfer_event($event, $data) {
        // Log transfer events for now.
        $this->log_webhook($event, $data);
        return true;
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
        return ['NGN', 'GHS', 'ZAR', 'USD'];
    }
}
