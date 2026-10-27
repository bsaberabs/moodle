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
 * Flutterwave payment gateway
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class flutterwave_gateway implements gateway_interface, gateway_return_interface {
    /** @var string Flutterwave API base URL */
    const API_URL = 'https://api.flutterwave.com/v3';

    /** @var string Gateway name */
    const GATEWAY_NAME = 'flutterwave';

    /**
     * Initialize payment with Flutterwave
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

        // Generate a unique reference for this payment attempt.
        // For retry attempts, append a suffix to ensure uniqueness.
        $attemptstable = new \xmldb_table('local_moderncommerce_payment_attempts');
        $existingtransactions = $DB->get_manager()->table_exists($attemptstable)
            ? $DB->count_records('local_moderncommerce_payment_attempts', [
                'orderid' => $order->id,
                'gateway' => self::GATEWAY_NAME,
            ])
            : 0;
        if ($existingtransactions > 0) {
            // This is a retry - generate a unique reference.
            $txref = $order->ordernumber . '-R' . ($existingtransactions + 1);
        } else {
            $txref = $order->ordernumber;
        }

        // Use the order currency snapshot. Gateway readiness guards ensure it is valid.
        $currency = strtoupper((string)$order->currency);
        $data = [
            'tx_ref' => $txref,
            'amount' => $order->total,
            'currency' => $currency,
            'redirect_url' => gateway_manager::callback_url(self::GATEWAY_NAME)->out(false),
            'payment_options' => 'card,banktransfer,ussd,mobilemoneyghana,mobilemoneyuganda,mobilemoneyrwanda',
            'customer' => [
                'email' => $this->resolve_order_email($order),
                'phonenumber' => $this->resolve_order_phone($order),
                'name' => $this->resolve_order_name($order),
            ],
            'customizations' => [
                'title' => get_string('pluginname', 'local_moderncommerce'),
                'description' => 'Order ' . $order->ordernumber,
                'logo' => $CFG->wwwroot . '/theme/image.php/boost/core/1/moodlelogo',
            ],
            'meta' => [
                'order_id' => $order->id,
                'order_number' => $order->ordernumber,
                'user_id' => $order->userid,
            ],
        ];

        $response = $this->make_request('POST', '/payments', $data);

        if (!$response || $response['status'] !== 'success') {
            throw new \moodle_exception(
                'paymentinitfailed',
                'local_moderncommerce',
                '',
                $response['message'] ?? get_string('p1_payment_unknownerror', 'local_moderncommerce')
            );
        }

        // Log transaction.
        $this->log_transaction($order->id, 'initialize', $response);

        return [
            'success' => true,
            'authorization_url' => $response['data']['link'],
            'reference' => $order->ordernumber,
        ];
    }

    /**
     * Verify payment with Flutterwave
     *
     * @param string $transactionid Transaction ID from Flutterwave
     * @return object Payment verification data
     */
    public function verify_payment($transactionid) {
        $response = $this->make_request('GET', '/transactions/' . $transactionid . '/verify');

        if (!$response || $response['status'] !== 'success') {
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
            'tx_ref' => $data['tx_ref'],
            'transaction_id' => $data['id'],
            'flw_ref' => $data['flw_ref'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'charged_amount' => $data['charged_amount'],
            'payment_type' => $data['payment_type'],
            'created_at' => $data['created_at'],
            'raw_data' => $data,
        ];
    }

    /**
     * Process Flutterwave webhook
     *
     * @param array $payload Webhook payload
     * @return bool Success status
     */
    public function process_webhook($payload, array $headers = [], ?string $rawpayload = null) {

        global $DB;

        // Verify webhook signature.
        if (!$this->verify_webhook_signature($headers)) {
            return false;
        }
        $event = $payload['event'] ?? '';
        $data = $payload['data'] ?? [];

        switch ($event) {
            case 'charge.completed':
                return $this->handle_payment_success($data, $event);
            case 'charge.failed':
                return $this->handle_payment_failed($data, $event);
            case 'transfer.completed':
            case 'transfer.failed':
                return $this->handle_transfer_event($event, $data);

            // Subscription events - delegate to modernsubscription if available.
            case 'subscription.cancelled':
                return $this->handle_subscription_event($event, $data);

            default:
                // Log unhandled events.
                $this->log_webhook($event, $data);
                return true;
        }
    }

    /**
     * Process the hosted Flutterwave return.
     *
     * @param array $params Return request parameters.
     * @return \stdClass Normalized payment result.
     */
    public function process_return(array $params): \stdClass {

        if (($params['status'] ?? '') === 'cancelled') {
            return (object) [
                'status' => 'cancelled',
                'orderid' => null,
                'orderreference' => $params['tx_ref'] ?? null,
                'gatewayreference' => $params['tx_ref'] ?? null,
                'gatewaytransactionid' => $params['transaction_id'] ?? null,
                'message' => get_string('paymentcancelled', 'local_moderncommerce'),
                'rawdata' => $params,
            ];
        }

        $transactionid = $params['transaction_id'] ?? '';
        if (empty($transactionid)) {
            throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce', '', 'Missing Flutterwave transaction ID');
        }

        $verification = $this->verify_payment($transactionid);
        return (object) [
            'status' => $verification->status === 'successful' ? 'success' : 'failed',
            'orderid' => null,
            'orderreference' => $verification->tx_ref ?? null,
            'gatewayreference' => $verification->tx_ref ?? null,
            'gatewaytransactionid' => $verification->transaction_id ?? $transactionid,
            'amount' => $verification->amount ?? null,
            'currency' => $verification->currency ?? null,
            'paidat' => !empty($verification->created_at) ? strtotime($verification->created_at) : null,
            'channel' => $verification->payment_type ?? null,
            'message' => $verification->status ?? '',
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
            return \local_moderncommerce\subscription\services\webhook_service::handle_flutterwave_event($event, $data);
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
    protected function verify_webhook_signature(array $headers = []): bool {

        $config = $this->get_config();
        $secret = $config['secret_hash'] ?? ($config['webhook_secret'] ?? '');
        if (empty($secret)) {
            return false;
        }

        $signature = self::get_header($headers, 'verif-hash');
        if ($signature === '' && !empty($_SERVER['HTTP_VERIF_HASH'])) {
            $signature = (string) $_SERVER['HTTP_VERIF_HASH'];
        }

        if ($signature === '') {
            return false;
        }

        return hash_equals((string) $secret, $signature);
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
     * Resolve the customer phone from canonical and legacy order fields.
     *
     * @param object $order Order record.
     * @return string Customer phone.
     */
    protected function resolve_order_phone(object $order): string {

        return (string) (
        $order->customerphone ?? $order->billingphone ?? ''
        );
    }

    /**
     * Resolve a customer display name from the order or Moodle user record.
     *
     * @param object $order Order record.
     * @return string Customer name.
     */
    protected function resolve_order_name(object $order): string {

        global $DB;
        $name = (string) (
            $order->customername ?? $order->billingname ?? ''
        );
        if ($name !== '') {
            return $name;
        }

        if (!empty($order->userid)) {
            $user = $DB->get_record('user', ['id' => (int) $order->userid], '*', IGNORE_MISSING);
            if ($user) {
                return fullname($user);
            }
        }

        return $this->resolve_order_email($order);
    }
    /**
     * Handle successful payment
     *
     * @param array $data Payment data
     * @return bool
     */
    protected function handle_payment_success($data, string $eventtype = 'charge.completed') {

        global $DB;
        // Verify payment status.
        if ($data['status'] !== 'successful') {
            return false;
        }

        $txref = $data['tx_ref'] ?? '';
        if (empty($txref)) {
            return false;
        }

        // Find order by reference (order number).
        $order = $DB->get_record('local_moderncommerce_orders', ['ordernumber' => $txref]);
        if (!$order) {
            return false;
        }

        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'success',
            'orderid' => $order->id,
            'orderreference' => $txref,
            'gatewayreference' => $data['tx_ref'] ?? $txref,
            'gatewaytransactionid' => $data['id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'paidat' => !empty($data['created_at']) ? strtotime($data['created_at']) : time(),
            'channel' => $data['payment_type'] ?? null,
            'message' => $data['processor_response'] ?? '',
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
        $txref = $data['tx_ref'] ?? '';
        if (empty($txref)) {
            return false;
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['ordernumber' => $txref]);
        if (!$order) {
            return false;
        }

        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'failed',
            'orderid' => $order->id,
            'orderreference' => $txref,
            'gatewayreference' => $data['tx_ref'] ?? $txref,
            'gatewaytransactionid' => $data['id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'message' => $data['processor_response'] ?? get_string('paymentfailed', 'local_moderncommerce'),
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
     * Make API request to Flutterwave
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
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
        global $DB;

        // Get order to extract tx_ref (ordernumber).
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

        if (isset($data['meta']['order_id'])) {
            $orderid = $data['meta']['order_id'];
        }

        // Extract reference - try multiple possible locations.
        if (isset($data['tx_ref'])) {
            $reference = $data['tx_ref'];
        } else if (isset($data['flw_ref'])) {
            $reference = $data['flw_ref'];
        } else if (isset($data['id'])) {
            $reference = $data['id'];
        }

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
        return ['NGN', 'GHS', 'KES', 'UGX', 'ZAR', 'USD', 'EUR', 'GBP'];
    }
}
