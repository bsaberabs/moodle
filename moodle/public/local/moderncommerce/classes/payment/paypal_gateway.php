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

defined('MOODLE_INTERNAL') || die();

// Load PayPal SDK.
require_once(__DIR__ . '/../../vendor/autoload.php');
use local_moderncommerce\logging\paylog_service;

use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Controllers\OrdersController;
use PaypalServerSdkLib\Models\Builders\OrderRequestBuilder;
use PaypalServerSdkLib\Models\Builders\PurchaseUnitRequestBuilder;
use PaypalServerSdkLib\Models\Builders\AmountWithBreakdownBuilder;
use PaypalServerSdkLib\Models\Builders\AmountBreakdownBuilder;
use PaypalServerSdkLib\Models\Builders\MoneyBuilder;
use PaypalServerSdkLib\Models\Builders\ItemBuilder;
use PaypalServerSdkLib\Models\Builders\OrderApplicationContextBuilder;
use PaypalServerSdkLib\Models\CheckoutPaymentIntent;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Exceptions\ApiException;
use local_moderncommerce\services\commerce_settings_service;

/**
 * PayPal payment gateway
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class paypal_gateway implements gateway_interface, gateway_return_interface {
    /** @var string Gateway name */
    const GATEWAY_NAME = 'paypal';

    /** @var object PayPal SDK client */
    private $client = null;

    /** @var OrdersController PayPal orders controller */
    private $orderscontroller = null;

    /**
     * Initialize payment with PayPal
     *
     * @param object $order Order record
     * @return array Payment initialization data
     */
    public function initialize_payment($order) {
        global $CFG;

        if (!$this->is_enabled()) {
            throw new \moodle_exception('gatewaydisabled', 'local_moderncommerce', '', $this->get_name());
        }

        try {
            $config = $this->get_config();

            if (empty($config['client_id']) || empty($config['secret_key'])) {
                throw new \Exception('PayPal credentials not configured. Please add Client ID and Secret Key in plugin settings.');
            }

            $client = $this->get_client();
            $orderscontroller = $client->getOrdersController();

            // Build order items.
            $items = [];
            $orderitems = $this->get_order_items($order);
            $calculateditemtotalcents = 0;

            foreach ($orderitems as $itemdata) {
                $unitamount = MoneyBuilder::init(
                    $itemdata['unit_amount']['currency_code'],
                    $itemdata['unit_amount']['value']
                )->build();

                $items[] = ItemBuilder::init(
                    $itemdata['name'],
                    $unitamount,
                    (string)$itemdata['quantity']
                )
                    ->description($itemdata['description'])
                    ->build();

                // Calculate actual item total using integer cents to avoid rounding issues.
                $valuestr = (string)$itemdata['unit_amount']['value'];
                $unitcents = (int)round(((float)$valuestr) * 100);
                $qty = (int)$itemdata['quantity'];
                $calculateditemtotalcents += ($unitcents * $qty);
            }

            // Use calculated item total for both breakdown and overall amount to ensure they match.
            $totalformatted = number_format($calculateditemtotalcents / 100, 2, '.', '');

            // Debug removed.

            // Build amount breakdown with item_total.
            $breakdown = AmountBreakdownBuilder::init()
                ->itemTotal(MoneyBuilder::init($order->currency, $totalformatted)->build())
                ->build();

            // Build amount with breakdown - use same total as item_total.
            $amount = AmountWithBreakdownBuilder::init(
                $order->currency,
                $totalformatted
            )
            ->breakdown($breakdown)
            ->build();

            // Build purchase unit.
            $purchaseunit = PurchaseUnitRequestBuilder::init($amount)
                ->referenceId($order->ordernumber)
                ->description(get_string('p1_paypal_orderdescription', 'local_moderncommerce', $order->ordernumber))
                ->customId((string)$order->id)
                ->items($items)
                ->build();

            // Build application context.
            $storesettings = commerce_settings_service::get_admin_settings();
            $sitename = $storesettings->businessname;
            if (empty($sitename)) {
                $site = get_site();
                $sitename = $site->shortname;
            }

            $appcontext = OrderApplicationContextBuilder::init()
                ->brandName($sitename)
                ->returnUrl(gateway_manager::callback_url(self::GATEWAY_NAME, ['orderid' => $order->id])->out(false))
                ->cancelUrl($CFG->wwwroot . '/local/moderncommerce/checkout.php?cancelled=1')
                ->userAction('PAY_NOW')
                ->build();

            // Create order request.
            $orderrequest = OrderRequestBuilder::init(CheckoutPaymentIntent::CAPTURE, [$purchaseunit])
                ->applicationContext($appcontext)
                ->build();

            // Debug removed.

            // Create order via SDK.
            $response = $orderscontroller->createOrder(['body' => $orderrequest]);
            // Debug removed.
            $result = $response->getResult();
            // Debug removed.

            // Debug removed.

            // Detect PayPal error responses (e.g., UNPROCESSABLE_ENTITY) and fail early.
            if (is_array($result) && isset($result['name']) && isset($result['message'])) {
                $detail = '';
                if (isset($result['details'][0]['description'])) {
                    $detail = ' - ' . $result['details'][0]['description'];
                } else if (isset($result['details'][0]->description)) {
                    $detail = ' - ' . $result['details'][0]->description;
                }
                $debugid = isset($result['debug_id']) ? ' [debug_id: ' . $result['debug_id'] . ']' : '';
                throw new \moodle_exception(
                    'paymentinitfailed',
                    'local_moderncommerce',
                    '',
                    $result['name'] . ': ' . $result['message'] . $detail . $debugid
                );
            }

            // Handle both array and object responses.
            $approvalurl = '';
            $orderid = '';
            $status = '';

            if (is_array($result)) {
                // Array response.
                $orderid = $result['id'] ?? '';
                $status = $result['status'] ?? '';
                if (isset($result['links'])) {
                    foreach ($result['links'] as $link) {
                        $rel = is_array($link) ? ($link['rel'] ?? '') : (isset($link->rel) ? $link->rel : '');
                        if ($rel === 'approve') {
                            $approvalurl = is_array($link) ? ($link['href'] ?? '') : (isset($link->href) ? $link->href : '');
                            break;
                        }
                    }
                }
            } else {
                // Object response (stdClass or typed object).
                $orderid = method_exists($result, 'getId') ? $result->getId() : ($result->id ?? '');
                $status = method_exists($result, 'getStatus') ? $result->getStatus() : ($result->status ?? '');
                $links = method_exists($result, 'getLinks') ? $result->getLinks() : ($result->links ?? []);
                if ($links) {
                    foreach ($links as $link) {
                        $rel = '';
                        $href = '';
                        if (is_array($link)) {
                            $rel = $link['rel'] ?? '';
                            $href = $link['href'] ?? '';
                        } else {
                            if (method_exists($link, 'getRel')) {
                                $rel = $link->getRel();
                            } else if (isset($link->rel)) {
                                $rel = $link->rel;
                            }
                            if (method_exists($link, 'getHref')) {
                                $href = $link->getHref();
                            } else if (isset($link->href)) {
                                $href = $link->href;
                            }
                        }
                        if ($rel === 'approve' && !empty($href)) {
                            $approvalurl = $href;
                            break;
                        }
                    }
                }
            }

            // Log transaction to paylog table.
            paylog_service::log($order->id, $this->get_name(), 'initialize', $orderid, [
                'status' => $status,
            ]);
            $this->save_payment_attempt($order, $orderid, $status, $approvalurl);

            return [
                'success' => true, 'authorization_url' => $approvalurl, 'paypal_order_id' => $orderid,
                'reference' => $order->ordernumber,
            ];
        } catch (ApiException $e) {
            $errormsg = $e->getMessage();
            $details = '';

            // Get detailed error from response.
            if ($e->hasResponse()) {
                $response = $e->getHttpResponse();
                $statuscode = $response->getStatusCode();
                $details = ' (HTTP ' . $statuscode . ')';
            }

            // Build comprehensive error message.
            $fullerror = 'PayPal API Error: ' . $errormsg . $details .
                         ' | Order: ' . $order->id .
                         ' | Total: ' . $order->total . ' ' . $order->currency .
                         ' | Exception: ' . get_class($e);

            // Log the full error for debugging.
            debugging($fullerror, DEBUG_DEVELOPER);

            throw new \moodle_exception('paymentinitfailed', 'local_moderncommerce', '', $errormsg . $details);
        } catch (\Exception $e) {
            $fullerror = 'PayPal Exception: ' . $e->getMessage() .
                         ' | Class: ' . get_class($e) .
                         ' | File: ' . $e->getFile() . ':' . $e->getLine();
            debugging($fullerror, DEBUG_DEVELOPER);
            throw new \moodle_exception('paymentinitfailed', 'local_moderncommerce', '', $e->getMessage());
        }
    }

    /**
     * Verify payment with PayPal
     *
     * @param string $reference Payment reference (PayPal order ID)
     * @return object Payment verification data
     */
    public function verify_payment($reference) {
        try {
            $client = $this->get_client();
            $orderscontroller = $client->getOrdersController();

            $response = $orderscontroller->getOrder(['id' => $reference]);
            $result = $response->getResult();

            // Handle both array and object responses.
            $status = '';
            $orderid = '';
            $amount = 0;
            $currency = '';
            $paidat = null;
            $payeremail = null;

            if (is_array($result)) {
                // Array response.
                $status = $result['status'] ?? '';
                $orderid = $result['id'] ?? $reference;
                $purchaseunits = $result['purchase_units'] ?? [];
                $purchaseunit = !empty($purchaseunits) ? $purchaseunits[0] : null;

                if ($purchaseunit) {
                    $amount = floatval($purchaseunit['amount']['value'] ?? 0);
                    $currency = $purchaseunit['amount']['currency_code'] ?? '';

                    $payments = $purchaseunit['payments'] ?? [];
                    $captures = $payments['captures'] ?? [];
                    if (!empty($captures)) {
                        $paidat = $captures[0]['create_time'] ?? null;
                    }
                }

                $payer = $result['payer'] ?? [];
                $payeremail = $payer['email_address'] ?? null;
            } else {
                // Object response.
                $status = $result->getStatus();
                $orderid = $result->getId();
                $purchaseunits = $result->getPurchaseUnits();
                $purchaseunit = !empty($purchaseunits) ? $purchaseunits[0] : null;

                if ($purchaseunit) {
                    $amountobj = $purchaseunit->getAmount();
                    if ($amountobj) {
                        $amount = floatval($amountobj->getValue());
                        $currency = $amountobj->getCurrencyCode();
                    }

                    $payments = $purchaseunit->getPayments();
                    if ($payments) {
                        $captures = $payments->getCaptures();
                        if (!empty($captures)) {
                            $paidat = $captures[0]->getCreateTime();
                        }
                    }
                }

                $payer = $result->getPayer();
                if ($payer) {
                    $payeremail = $payer->getEmailAddress();
                }
            }

            return (object)[
                'status' => $status,
                'reference' => $orderid,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => $paidat,
                'channel' => 'PayPal',
                'gateway_response' => $status,
                'payer_email' => $payeremail,
                'raw_data' => is_array($result) ? $result : json_decode(json_encode($result), true),
            ];
        } catch (ApiException $e) {
            $errormsg = $e->getMessage();
            throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce', '', $errormsg);
        } catch (\Exception $e) {
            throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce', '', $e->getMessage());
        }
    }

    /**
     * Process PayPal webhook
     *
     * @param array $payload Webhook payload
     * @return bool Success status
     */
    public function process_webhook($payload, array $headers = [], ?string $rawpayload = null) {
        global $DB;

        // Verify webhook signature.
        if (!$this->verify_webhook_signature($payload, $headers)) {
            return false;
        }

        $eventtype = $payload['event_type'] ?? '';
        $resource = $payload['resource'] ?? [];

        switch ($eventtype) {
            case 'CHECKOUT.ORDER.APPROVED':
                return $this->handle_order_approved($resource);

            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handle_payment_captured($resource);

            case 'PAYMENT.CAPTURE.DENIED':
                return $this->handle_payment_failed($resource);

            case 'PAYMENT.CAPTURE.REFUNDED':
                return $this->handle_payment_refunded($resource);

            // Subscription events - delegate to modernsubscription if available.
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
            case 'PAYMENT.SALE.COMPLETED':
            case 'PAYMENT.SALE.DENIED':
            case 'PAYMENT.SALE.REFUNDED':
                return $this->handle_subscription_event($eventtype, $resource);

            default:
                // Log unhandled events.
                $this->log_webhook($eventtype, $resource);
                return true;
        }
    }

    /**
     * Process the hosted PayPal return.
     *
     * @param array $params Return request parameters.
     * @return \stdClass Normalized payment result.
     */
    public function process_return(array $params): \stdClass {
        $reference = $params['token'] ?? '';
        $orderid = !empty($params['orderid']) ? (int)$params['orderid'] : null;

        if (empty($reference)) {
            throw new \moodle_exception(
                'paymentverifyfailed',
                'local_moderncommerce',
                '',
                get_string('p1_paypal_missingtoken', 'local_moderncommerce')
            );
        }

        $capture = $this->capture_payment($reference);
        $rawdata = is_array($capture) ? $capture : json_decode(json_encode($capture), true);
        $status = is_object($capture) && method_exists($capture, 'getStatus')
            ? $capture->getStatus()
            : ($rawdata['status'] ?? '');

        return (object) [
            'status' => $status === 'COMPLETED' ? 'success' : 'failed',
            'orderid' => $orderid,
            'orderreference' => null,
            'gatewayreference' => $reference,
            'gatewaytransactionid' => $reference,
            'amount' => null,
            'currency' => null,
            'paidat' => time(),
            'channel' => 'PayPal',
            'message' => $status,
            'rawdata' => $rawdata,
        ];
    }

    /**
     * Capture a PayPal order.
     *
     * @param string $reference PayPal order ID.
     * @return mixed PayPal capture response result.
     */
    public function capture_payment(string $reference) {
        try {
            $client = $this->get_client();
            $orderscontroller = $client->getOrdersController();
            $response = $orderscontroller->captureOrder(['id' => $reference]);
            return $response->getResult();
        } catch (ApiException $e) {
            throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce', '', $e->getMessage());
        } catch (\Exception $e) {
            throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce', '', $e->getMessage());
        }
    }

    /**
     * Handle subscription-related webhook events.
     *
     * Delegates to local_moderncommerce webhook service if available.
     *
     * @param string $eventtype Event type.
     * @param array $resource Resource data.
     * @return bool
     */
    protected function handle_subscription_event($eventtype, $resource) {
        // Check if subscription plugin is available.
        if (class_exists('\local_moderncommerce\subscription\services\webhook_service')) {
            return \local_moderncommerce\subscription\services\webhook_service::handle_paypal_event($eventtype, $resource);
        }

        // Log event if subscription plugin not available.
        $this->log_webhook($eventtype, $resource);
        return true;
    }

    /**
     * Get gateway configuration
     *
     * @return array Configuration array
     */
    public function get_config() {
        return gateway_manager::get_gateway_config(self::GATEWAY_NAME);
    }

    /**
     * Check if gateway is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        $config = $this->get_config();
        return !empty($config['enabled']) && !empty($config['client_id']) && !empty($config['secret_key']);
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
     * Get supported currencies
     *
     * @return array Supported currency codes
     */
    public function get_supported_currencies() {
        // PayPal supported currencies
        // See: https://developer.paypal.com/docs/reports/reference/paypal-supported-currencies/.
        return [
            'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY',
            'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK',
            'CHF', 'THB', 'USD',
        ];
    }

    /**
     * Get PayPal SDK client
     *
     * @return object PayPal client
     */
    private function get_client() {
        if ($this->client !== null) {
            return $this->client;
        }

        $config = $this->get_config();

        $environment = $config['sandbox_mode']
            ? Environment::SANDBOX
            : Environment::PRODUCTION;

        $this->client = PaypalServerSdkClientBuilder::init()
            ->clientCredentialsAuthCredentials(
                ClientCredentialsAuthCredentialsBuilder::init(
                    $config['client_id'],
                    $config['secret_key']
                )
            )
            ->environment($environment)
            ->build();

        return $this->client;
    }

    /**
     * Get order items for PayPal
     *
     * @param object $order Order record
     * @return array Items array
     */
    private function get_order_items($order) {

        global $DB;
        $items = [];
        $orderitems = $DB->get_records('local_moderncommerce_order_items', ['orderid' => $order->id]);

        foreach ($orderitems as $item) {
            $items[] = [
                'name' => $item->itemname,
                'description' => get_string('p1_paypal_itemdescription', 'local_moderncommerce'),
                'unit_amount' => [
                    'currency_code' => $order->currency, 'value' => number_format($item->unitprice, 2, '.', ''),
                ],
                'quantity' => (int)$item->quantity,
            ];
        }

        return $items;
    }

    /**
     * Verify webhook signature with PayPal.
     *
     * @param array $payload Webhook payload.
     * @param array $headers Request headers.
     * @return bool
     */
    private function verify_webhook_signature(array $payload, array $headers = []): bool {
        $config = $this->get_config();
        $webhookid = trim((string)($config['webhook_id'] ?? ($config['webhook_secret'] ?? '')));
        if ($webhookid === '') {
            debugging('PayPal webhook rejected because no webhook ID is configured.', DEBUG_DEVELOPER);
            return false;
        }

        $verification = [
            'auth_algo' => self::get_header($headers, 'Paypal-Auth-Algo'),
            'cert_url' => self::get_header($headers, 'Paypal-Cert-Url'),
            'transmission_id' => self::get_header($headers, 'Paypal-Transmission-Id'),
            'transmission_sig' => self::get_header($headers, 'Paypal-Transmission-Sig'),
            'transmission_time' => self::get_header($headers, 'Paypal-Transmission-Time'),
            'webhook_id' => $webhookid,
            'webhook_event' => $payload,
        ];

        foreach (['auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time'] as $field) {
            if ($verification[$field] === '') {
                debugging('PayPal webhook rejected because required signature headers are missing.', DEBUG_DEVELOPER);
                return false;
            }
        }

        try {
            $response = $this->post_paypal_json('/v1/notifications/verify-webhook-signature', $verification);
        } catch (\Throwable $e) {
            debugging('PayPal webhook verification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        return ($response['verification_status'] ?? '') === 'SUCCESS';
    }

    /**
     * Resolve a request header case-insensitively.
     *
     * @param array $headers Request headers.
     * @param string $name Header name.
     * @return string Header value.
     */
    private static function get_header(array $headers, string $name): string {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string)$key, $name) === 0) {
                return is_array($value) ? (string)reset($value) : (string)$value;
            }
        }

        return '';
    }

    /**
     * POST JSON to PayPal's REST API.
     *
     * @param string $path API path.
     * @param array $payload JSON payload.
     * @return array Decoded response.
     */
    private function post_paypal_json(string $path, array $payload): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $token = $this->request_access_token();
        if ($token === '') {
            throw new \moodle_exception('gatewaynotconfigured', 'local_moderncommerce', '', $this->get_name());
        }

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        $response = $curl->post(
            $this->paypal_api_base_url() . $path,
            json_encode($payload),
            [
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_TIMEOUT' => 30,
            ]
        );

        $error = $curl->get_errno() ? $curl->error : '';
        $httpcode = (int)$curl->get_info()['http_code'];

        if ($response === false || $httpcode < 200 || $httpcode >= 300) {
            throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce', '', $error ?: (string)$httpcode);
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('paymentverifyfailed', 'local_moderncommerce');
        }

        return $decoded;
    }

    /**
     * Request a PayPal REST API access token.
     *
     * @return string Access token or empty string.
     */
    private function request_access_token(): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $config = $this->get_config();
        $clientid = (string)($config['client_id'] ?? '');
        $secret = (string)($config['secret_key'] ?? '');
        if ($clientid === '' || $secret === '') {
            return '';
        }

        $curl = new \curl();
        $curl->setHeader([
            'Accept: application/json',
            'Accept-Language: en_US',
        ]);
        $response = $curl->post(
            $this->paypal_api_base_url() . '/v1/oauth2/token',
            'grant_type=client_credentials',
            [
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_TIMEOUT' => 30,
                'CURLOPT_HTTPAUTH' => CURLAUTH_BASIC,
                'CURLOPT_USERPWD' => $clientid . ':' . $secret,
            ]
        );

        $httpcode = (int)$curl->get_info()['http_code'];

        if ($response === false || $httpcode < 200 || $httpcode >= 300) {
            return '';
        }

        $decoded = json_decode((string)$response, true);
        return is_array($decoded) ? (string)($decoded['access_token'] ?? '') : '';
    }

    /**
     * Get the PayPal REST API base URL.
     *
     * @return string API base URL.
     */
    private function paypal_api_base_url(): string {
        $config = $this->get_config();
        if (!empty($config['sandbox_mode']) || !empty($config['testmode'])) {
            return 'https://api-m.sandbox.paypal.com';
        }

        return 'https://api-m.paypal.com';
    }

    /**
     * Handle order approved event
     *
     * @param array $resource Resource data
     * @return bool
     */
    private function handle_order_approved($resource) {
        // Order approved but not yet captured - just log it.
        $this->log_webhook('order_approved', $resource);
        return true;
    }

    /**
     * Handle payment captured event
     *
     * @param array $resource Resource data
     * @return bool
     */
    private function handle_payment_captured($resource) {
        global $DB;

        $customid = $resource['custom_id'] ?? null;
        if (!$customid) {
            return false;
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $customid]);
        if (!$order) {
            return false;
        }

        $reference = $resource['id'] ?? $order->ordernumber;
        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'success',
            'orderid' => $order->id,
            'orderreference' => $order->ordernumber,
            'gatewayreference' => $reference,
            'gatewaytransactionid' => $resource['id'] ?? null,
            'amount' => isset($resource['amount']['value']) ? (float)$resource['amount']['value'] : (float)$order->total,
            'currency' => $resource['amount']['currency_code'] ?? $order->currency,
            'paidat' => time(),
            'channel' => 'PayPal',
            'message' => $resource['status'] ?? '',
            'source' => 'webhook',
            'eventtype' => 'PAYMENT.CAPTURE.COMPLETED',
            'gatewayeventid' => $resource['id'] ?? null,
            'rawdata' => $resource,
        ]);
        \local_moderncommerce\api\order_api::update_order_status($order->id, 'completed');
        return true;
    }

    /**
     * Handle payment failed event
     *
     * @param array $resource Resource data
     * @return bool
     */
    private function handle_payment_failed($resource) {
        global $DB;

        $customid = $resource['custom_id'] ?? null;
        if (!$customid) {
            return false;
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $customid]);
        if (!$order) {
            return false;
        }

        $reference = $resource['id'] ?? $order->ordernumber;
        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'failed',
            'orderid' => $order->id,
            'orderreference' => $order->ordernumber,
            'gatewayreference' => $reference,
            'gatewaytransactionid' => $resource['id'] ?? null,
            'amount' => isset($resource['amount']['value']) ? (float)$resource['amount']['value'] : (float)$order->total,
            'currency' => $resource['amount']['currency_code'] ?? $order->currency,
            'message' => $resource['status'] ?? get_string('paymentfailed', 'local_moderncommerce'),
            'source' => 'webhook',
            'eventtype' => 'PAYMENT.CAPTURE.DENIED',
            'gatewayeventid' => $resource['id'] ?? null,
            'rawdata' => $resource,
        ]);
        \local_moderncommerce\api\order_api::update_order_status($order->id, 'failed');
        return true;
    }

    /**
     * Handle payment refunded event.
     *
     * @param array $resource Resource data.
     * @return bool
     */
    private function handle_payment_refunded($resource) {
        global $DB;

        $customid = $resource['custom_id'] ?? null;
        if (!$customid) {
            return false;
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $customid]);
        if (!$order) {
            return false;
        }

        $reference = $resource['id'] ?? $order->ordernumber;
        gateway_manager::record_payment_result($order, self::GATEWAY_NAME, (object) [
            'status' => 'refunded',
            'orderid' => $order->id,
            'orderreference' => $order->ordernumber,
            'gatewayreference' => $reference,
            'gatewaytransactionid' => $resource['id'] ?? null,
            'amount' => isset($resource['amount']['value']) ? (float)$resource['amount']['value'] : (float)$order->total,
            'currency' => $resource['amount']['currency_code'] ?? $order->currency,
            'message' => $resource['status'] ?? 'refunded',
            'source' => 'webhook',
            'eventtype' => 'PAYMENT.CAPTURE.REFUNDED',
            'gatewayeventid' => $resource['id'] ?? null,
            'rawdata' => $resource,
        ]);
        \local_moderncommerce\api\order_api::update_order_status($order->id, 'refunded');
        return true;
    }

    /**
     * Log transaction
     *
     * @param int $orderid Order ID
     * @param string $action Action performed
     * @param array $response Response data
     */
    private function log_transaction($orderid, $action, $response) {
        // Delegate to paylog_service; keep signature for backward compatibility.
        paylog_service::log($orderid, $this->get_name(), $action, null, $response);
    }

    /**
     * Log webhook event
     *
     * @param string $event Event type
     * @param array $data Event data
     */
    private function log_webhook($event, $data) {

        global $DB;
        try {
            // Check if table exists before trying to insert.
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists(new \xmldb_table('local_moderncommerce_webhook_events'))) {
                // Table doesn't exist, skip logging.
                debugging('Webhook log table does not exist, skipping webhook log', DEBUG_DEVELOPER);
                return;
            }

            $payload = self::payload_for_storage($data);
            $payloadhash = hash('sha256', (string)$payload);
            $gatewayeventid = $data['id'] ?? null;
            $reference = $data['custom_id'] ?? ($data['invoice_id'] ?? null);
            $dedupekey = hash('sha256', $this->get_name() . '|' . $event . '|' . ($gatewayeventid ?: $payloadhash));
            if ($DB->record_exists('local_moderncommerce_webhook_events', ['dedupekey' => $dedupekey])) {
                return;
            }

            $log = new \stdClass();
            $log->gateway = $this->get_name();
            $log->dedupekey = $dedupekey;
            $log->gatewayeventid = $gatewayeventid;
            $log->eventtype = $event;
            $log->reference = $reference;
            $log->signatureverified = 1;
            $log->payloadhash = $payloadhash;
            $log->payload = $payload;
            $log->status = 'received';
            $log->attemptcount = 1;
            $log->timecreated = time();
            $DB->insert_record('local_moderncommerce_webhook_events', $log);
        } catch (\Exception $e) {
            // Don't fail the webhook if logging fails.
            debugging('Failed to log webhook: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Insert or update a PayPal payment attempt.
     *
     * @param object $order Order record.
     * @param string $reference Gateway reference.
     * @param string $status Attempt status.
     * @param string|null $redirecturl Gateway approval URL.
     * @param string|null $transactionid Gateway transaction ID.
     * @return int Attempt ID.
     */
    private function save_payment_attempt($order, $reference, $status, $redirecturl = null, $transactionid = null) {

        global $DB;
        $now = time();
        $reference = (string)($reference ?: $order->ordernumber);
        $attempt = $DB->get_record('local_moderncommerce_payment_attempts', [
            'gateway' => $this->get_name(),
            'reference' => $reference,
        ], '*', IGNORE_MISSING);
        $record = (object) [
            'orderid' => $order->id,
            'gateway' => $this->get_name(),
            'reference' => $reference,
            'amount' => (float)$order->total,
            'currency' => $order->currency,
            'status' => $status ?: 'pending',
            'gatewaytransactionid' => $transactionid,
            'redirecturl' => $redirecturl,
            'timemodified' => $now,
        ];
        if ($attempt) {
            $record->id = $attempt->id;
            $record->timecreated = $attempt->timecreated;
            $record->timecompleted = in_array($status, ['success', 'failed'], true) ? $now : $attempt->timecompleted;
            $DB->update_record('local_moderncommerce_payment_attempts', $record);
            return (int)$attempt->id;
        }

        $record->timecreated = $now;
        $record->timecompleted = in_array($status, ['success', 'failed'], true) ? $now : null;
        return (int)$DB->insert_record('local_moderncommerce_payment_attempts', $record);
    }

    /**
     * Log a PayPal payment lifecycle event.
     *
     * @param int|null $orderid Order ID.
     * @param int|null $attemptid Attempt ID.
     * @param string $eventtype Event type.
     * @param array $payload Event payload.
     * @param string $status Event status.
     * @param string|null $reference Gateway reference.
     * @param string|null $transactionid Gateway transaction ID.
     * @param float|null $amount Event amount.
     * @param string|null $currency Currency.
     * @param bool $verified Whether the event was verified.
     */
    private function log_payment_event(
        $orderid,
        $attemptid,
        $eventtype,
        $payload,
        $status,
        $reference = null,
        $transactionid = null,
        $amount = null,
        $currency = null,
        $verified = false
    ) {

        global $DB;
        $rawpayload = self::payload_for_storage($payload);
        $payloadhash = hash('sha256', (string)$rawpayload);
        $gatewayeventid = $payload['id'] ?? null;
        $dedupekey = hash('sha256', $this->get_name() . '|' . $eventtype . '|' . ($gatewayeventid ?: $payloadhash));
        if ($DB->record_exists('local_moderncommerce_payment_events', ['dedupekey' => $dedupekey])) {
            return;
        }

        $record = (object) [
            'orderid' => $orderid,
            'attemptid' => $attemptid,
            'gateway' => $this->get_name(),
            'dedupekey' => $dedupekey,
            'eventtype' => $eventtype,
            'gatewayeventid' => $gatewayeventid,
            'reference' => $reference,
            'transactionid' => $transactionid,
            'amount' => $amount ?? 0,
            'currency' => $currency,
            'status' => $status ?: 'received',
            'verified' => $verified ? 1 : 0,
            'payloadhash' => $payloadhash,
            'rawpayload' => $rawpayload,
            'processed' => 1,
            'timecreated' => time(),
            'timeprocessed' => time(),
        ];
        $DB->insert_record('local_moderncommerce_payment_events', $record);
    }

    /**
     * Encode a redacted payload snapshot for diagnostic storage.
     *
     * @param mixed $payload Payload.
     * @return string JSON payload.
     */
    private static function payload_for_storage($payload): string {
        $json = json_encode(self::redact_payload($payload));
        return $json === false ? '{}' : $json;
    }

    /**
     * Redact personal and payment-card values from provider payloads.
     *
     * @param mixed $value Payload value.
     * @return mixed Redacted payload value.
     */
    private static function redact_payload($value) {
        $sensitive = [
            'address', 'address_line_1', 'address_line_2', 'authorization', 'billing',
            'card', 'customer', 'email', 'first_name', 'firstname', 'last_name',
            'lastname', 'name', 'payer', 'phone', 'shipping', 'user_id', 'userid',
        ];

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                    $redacted[$key] = '[redacted]';
                    continue;
                }
                $redacted[$key] = self::redact_payload($item);
            }
            return $redacted;
        }

        if (is_object($value)) {
            return self::redact_payload(get_object_vars($value));
        }

        if (is_string($value) && strlen($value) > 512) {
            return substr($value, 0, 512) . '...';
        }

        return $value;
    }
}
