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
use local_moderncommerce\payment\gateway_interface;
use local_moderncommerce\payment\gateway_manager;
use local_moderncommerce\payment\subscription_mandate_trait;
use moodle_exception;
use moodle_url;

/**
 * SecureAndPay hosted payment page, registered as a Modern Commerce gateway.
 *
 * The buyer's browser posts a signed form to SecureAndPay (see pay.php), and
 * SecureAndPay reports every payment to notify.php. One-off orders use a simple
 * authorisation; subscription orders use ClicktoPay, so SecureAndPay charges the
 * following instalments itself.
 *
 * Gateway admin fields: "Merchant client ID" holds the IdSite and "Secret key"
 * holds the signing key supplied by SecureAndPay.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway implements gateway_interface {
    use subscription_mandate_trait;

    /** Gateway id in the Modern Commerce registry. */
    public const GATEWAY = 'secureandpay';

    /** Payment request URLs. */
    private const URL_LIVE = 'https://www.secureandpay.com/demandePaiement.php';
    private const URL_TEST = 'https://www.secureandpay.com:4443/demandePaiement.php';

    /** Currencies accepted by SecureAndPay. */
    private const CURRENCIES = ['EUR', 'USD', 'GBP'];

    /** Days between two instalments, per Modern Commerce billing cycle. */
    private const CYCLE_DAYS = ['monthly' => 30, 'yearly' => 365];

    /**
     * Register (or refresh) the gateway row in the Modern Commerce registry.
     *
     * Existing credentials, test mode and enabled state are kept.
     */
    public static function register(): void {
        global $DB;

        $existing = $DB->get_record('local_moderncommerce_gateways', ['gateway' => self::GATEWAY]);
        gateway_manager::save_gateway_config(self::GATEWAY, [
            'enabled' => $existing->enabled ?? 0,
            'displayname' => $existing->displayname ?? 'Carte bancaire (SecureAndPay)',
            'displayorder' => $existing->displayorder ?? 15,
            'component' => 'local_secureandpay',
            'classname' => '\\' . self::class,
            'methodtype' => 'redirect',
            'testmode' => $existing->testmode ?? 1,
            'merchantid' => $existing->merchantid ?? null,
            'supportedcurrencies' => implode(',', self::CURRENCIES),
            'supportswebhooks' => 1,
            'supportsrefunds' => 0,
            'supportsrecurring' => 1,
            'icon' => 'credit-card',
        ]);
    }

    public function get_config() {
        return gateway_manager::get_gateway_config(self::GATEWAY);
    }

    public function get_name() {
        return get_string('pluginname', 'local_secureandpay');
    }

    public function is_enabled() {
        $config = $this->get_config();
        return !empty($config['enabled']) && !empty($config['merchantid']) && !empty($config['secret_key']);
    }

    public function get_supported_currencies() {
        return self::CURRENCIES;
    }

    /**
     * Send the buyer to pay.php, which posts the signed form to SecureAndPay.
     *
     * @param object $order Order record.
     * @return array
     */
    public function initialize_payment($order) {
        global $DB;

        // A refused or abandoned payment keeps its orderID at SecureAndPay, so each
        // attempt gets its own reference.
        $attempts = $DB->count_records('local_moderncommerce_payment_attempts', [
            'orderid' => $order->id,
            'gateway' => self::GATEWAY,
        ]);
        $reference = self::build_reference($order, $attempts + 1);

        return [
            'authorization_url' => (new moodle_url('/local/secureandpay/pay.php', [
                'orderid' => $order->id,
                'ref' => $reference,
            ]))->out(false),
            'reference' => $reference,
        ];
    }

    /**
     * Report the latest recorded result for a reference.
     *
     * @param string $reference SecureAndPay orderID.
     * @return object
     */
    public function verify_payment($reference) {
        global $DB;

        $attempt = $DB->get_record('local_moderncommerce_payment_attempts', [
            'gateway' => self::GATEWAY,
            'reference' => $reference,
        ]);
        return (object) [
            'status' => $attempt->status ?? 'pending',
            'reference' => $reference,
            'orderid' => $attempt->orderid ?? 0,
        ];
    }

    /**
     * SecureAndPay notifies notify.php with a form post, not the shared JSON webhook.
     *
     * @param array $payload Payload with 'data' and 'signature'.
     * @param array $headers Request headers.
     * @param string|null $rawpayload Raw body.
     * @return bool
     */
    public function process_webhook($payload, array $headers = [], ?string $rawpayload = null) {
        notification_handler::handle((string) ($payload['data'] ?? ''), (string) ($payload['signature'] ?? ''));
        return true;
    }

    /**
     * SecureAndPay orderID: 10 to 20 alphanumeric characters.
     *
     * @param object $order Order record.
     * @param int $attempt Attempt number.
     * @return string
     */
    public static function build_reference(object $order, int $attempt): string {
        $base = preg_replace('/[^A-Za-z0-9]/', '', (string) $order->ordernumber);
        $suffix = str_pad((string) min($attempt, 99), 2, '0', STR_PAD_LEFT);
        $base = substr($base, 0, 18);
        return str_pad($base, 8, '0', STR_PAD_LEFT) . $suffix;
    }

    /**
     * Build the signed form fields for an order.
     *
     * @param object $order Order record.
     * @param string $reference SecureAndPay orderID.
     * @return array ['url' => string, 'data' => string, 'signature' => string]
     */
    public function build_request(object $order, string $reference): array {
        global $DB;

        if (!$this->is_enabled()) {
            throw new moodle_exception('notconfigured', 'local_secureandpay');
        }
        $currency = strtoupper((string) $order->currency);
        if (!in_array($currency, self::CURRENCIES, true)) {
            throw new moodle_exception('unsupportedcurrency', 'local_secureandpay', '', $currency);
        }

        $config = $this->get_config();
        $user = $DB->get_record('user', ['id' => $order->userid], '*', MUST_EXIST);
        $amount = (int) round((float) $order->total * 100);
        if ($amount <= 0) {
            throw new moodle_exception('invalidamount', 'local_secureandpay');
        }

        $products = [];
        foreach (order_api::get_order_items($order->id) as $item) {
            $products[] = trim(($item->name ?? $item->itemname ?? '') . ' x' . (int) ($item->quantity ?? 1));
        }

        $data = [
            'IdSite' => (string) $config['merchantid'],
            'orderID' => $reference,
            'osc_id' => (string) $order->id,
            'Amount' => (string) $amount,
            'Currency' => $currency,
            'PaymentType' => 'Autorisation',
            'EMAIL' => \core_text::substr($user->email, 0, 55),
            'CustName' => \core_text::substr($user->lastname, 0, 25),
            'CustFirstName' => \core_text::substr($user->firstname, 0, 25),
            'CustTel' => preg_replace('/[^0-9+]/', '', (string) ($user->phone1 ?: $user->phone2)),
            'CustAddress' => (string) $user->address,
            'CustCity' => \core_text::substr((string) $user->city, 0, 30),
            'CustCountry' => (string) $user->country,
            'Language' => strpos(current_language(), 'fr') === 0 ? 'fr' : 'en',
            'orderProducts' => implode(', ', array_filter($products)) ?: $order->ordernumber,
            'Clicktopay' => 0,
            'urlOk' => (new moodle_url('/local/moderncommerce/success.php', ['orderid' => $order->id]))->out(false),
            'urlNotify' => (new moodle_url('/local/secureandpay/notify.php'))->out(false),
        ];

        if (self::order_is_subscription($order)) {
            $data = array_merge($data, $this->subscription_fields($order));
        }

        $encoded = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return [
            'url' => !empty($config['testmode']) ? self::URL_TEST : self::URL_LIVE,
            'data' => $encoded,
            'signature' => self::sign($encoded),
        ];
    }

    /**
     * ClicktoPay fields: SecureAndPay charges the plan price every cycle until cancelled.
     *
     * @param object $order Subscription order.
     * @return array
     */
    private function subscription_fields(object $order): array {
        global $DB;

        $notes = json_decode((string) $order->notes, true);
        $planid = (int) ($notes['subscription']['planid'] ?? 0);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $planid], '*', MUST_EXIST);

        return [
            'Clicktopay' => 1,
            'MontantReccurent' => (string) (int) round((float) $plan->price * 100),
            'ReccurentNumber' => 'X',
            'ReccurentFrequency' => (string) (self::CYCLE_DAYS[$plan->billing_cycle] ?? 30),
            'ReccurentTestPeriod' => '0',
        ];
    }

    /**
     * HMAC-SHA256 of the base64 data with the merchant key.
     *
     * @param string $data Base64 payload.
     * @return string
     */
    public static function sign(string $data): string {
        $config = gateway_manager::get_gateway_config(self::GATEWAY);
        return hash_hmac('sha256', $data, (string) ($config['secret_key'] ?? ''));
    }
}
