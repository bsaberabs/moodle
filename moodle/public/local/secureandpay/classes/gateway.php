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
 * Gateway admin fields: "Merchant client ID" holds the IdSite (or "Public key", which the
 * Modern Commerce form requires anyway, when the merchant ID is left empty) and "Secret key"
 * holds the signing key supplied by SecureAndPay. The webhook secret is not used:
 * notifications are signed with the same key.
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

    /** Web service stopping a ClicktoPay schedule (IdSite + orderID). */
    private const URL_UNSUBSCRIBE_LIVE = 'https://www.secureandpay.com/desabonnerWs.php';
    private const URL_UNSUBSCRIBE_TEST = 'https://www.secureandpay.com:4443/desabonnerWs.php';

    /** Currencies accepted by SecureAndPay. */
    private const CURRENCIES = ['EUR', 'USD', 'GBP'];

    /** Months between two instalments (ReccurentFrequency), per Modern Commerce billing cycle. */
    private const CYCLE_MONTHS = ['monthly' => 1, 'yearly' => 12];

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
            'publickey' => $existing->publickey ?? null,
            'merchantid' => $existing->merchantid ?? null,
            'supportedcurrencies' => implode(',', self::CURRENCIES),
            // Notifications are verified with the secret key, so no webhook secret is asked for.
            'supportswebhooks' => 0,
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
        return !empty($this->get_config()['enabled']) && self::platform_merchant()->is_configured();
    }

    /**
     * The platform's own SecureAndPay account, from the Modern Commerce gateway settings.
     *
     * @return merchant
     */
    public static function platform_merchant(): merchant {
        $config = gateway_manager::get_gateway_config(self::GATEWAY);
        return new merchant(self::site_id($config), (string) ($config['secret_key'] ?? ''), !empty($config['testmode']));
    }

    /**
     * SecureAndPay account that takes an order: the platform's, unless a plugin routes it elsewhere
     * (see hook\resolve_merchant).
     *
     * @param object $order Order.
     * @param bool $forpayment True before a payment starts: a refusal then stops the payment.
     * @return merchant
     */
    public static function merchant_for_order(object $order, bool $forpayment): merchant {
        $hook = new hook\resolve_merchant($order, $forpayment);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        if ($forpayment && $hook->get_refusal() !== null) {
            throw new moodle_exception('paymentrefused', 'local_secureandpay', '', $hook->get_refusal());
        }
        return $hook->get_merchant() ?? self::platform_merchant();
    }

    /**
     * SecureAndPay IdSite: the "Merchant client ID" field, or "Public key" when that one is empty.
     *
     * @param array $config Gateway config.
     * @return string
     */
    private static function site_id(array $config): string {
        return trim((string) (($config['merchantid'] ?? '') ?: ($config['public_key'] ?? '')));
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

        $merchant = self::merchant_for_order($order, true);
        if (!$merchant->is_configured()) {
            throw new moodle_exception('notconfigured', 'local_secureandpay');
        }
        $user = $DB->get_record('user', ['id' => $order->userid], '*', MUST_EXIST);
        // A purchase in instalments is charged one instalment now, the others by SecureAndPay.
        $instalments = self::instalment_plan($order);
        $amount = (int) round((float) ($instalments ? $instalments['amount'] : $order->total) * 100);
        // Only a free trial is paid 0: SecureAndPay then just registers the card.
        if ($amount < 0 || ($amount === 0 && !self::is_trial_order($order))) {
            throw new moodle_exception('invalidamount', 'local_secureandpay');
        }

        $products = [];
        foreach (order_api::get_order_items($order->id) as $item) {
            $products[] = trim(($item->name ?? $item->itemname ?? '') . ' x' . (int) ($item->quantity ?? 1));
        }

        $data = [
            'IdSite' => $merchant->siteid,
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
            'urlOk' => self::return_url($order)->out(false),
            'urlNotify' => (new moodle_url('/local/secureandpay/notify.php'))->out(false),
        ];
        $data = array_merge($data, $this->payment_mode_fields($order, $amount, $merchant));

        $encoded = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return [
            'url' => $merchant->testmode ? self::URL_TEST : self::URL_LIVE,
            'data' => $encoded,
            'signature' => $merchant->sign($encoded),
        ];
    }

    /**
     * Whether SecureAndPay keeps the buyer's card for later purchases: when one-click payment is on for the site, or
     * when the order asks for it ("savecard" in its notes, for example an order form offering an extra after payment).
     *
     * @param object $order Order.
     * @return bool
     */
    public static function saves_card(object $order): bool {
        $notes = json_decode((string) ($order->notes ?? ''), true);
        return (bool) get_config('local_secureandpay', 'oneclick') || !empty($notes['savecard']);
    }

    /**
     * Page the buyer comes back to after paying: the store's confirmation, or the page named in the order notes
     * ("returnpath", a page of the site, such as the thank-you page of an order form).
     *
     * @param object $order Order.
     * @return moodle_url
     */
    public static function return_url(object $order): moodle_url {
        $notes = json_decode((string) ($order->notes ?? ''), true);
        $path = (string) ($notes['returnpath'] ?? '');
        if (preg_match('~^/local/[a-z0-9_/]+\.php$~', $path)) {
            return new moodle_url($path, ['orderid' => $order->id]);
        }
        return new moodle_url('/local/moderncommerce/success.php', ['orderid' => $order->id]);
    }

    /**
     * Clicktopay mode: 0 simple payment, 1 card registered at SecureAndPay, 2 payment with a saved card.
     *
     * With one-click payment on, a one-off purchase is sent as a single ClicktoPay instalment:
     * SecureAndPay registers the card, returns its token and closes the schedule without charging
     * again (its recurring cron stops once ReccurentNumber instalments are paid).
     *
     * @param object $order Order.
     * @param int $amount Amount in cents.
     * @param merchant $merchant Account taking the payment: a card token only works with the account that saved it.
     * @return array
     */
    private function payment_mode_fields(object $order, int $amount, merchant $merchant): array {
        $oneclick = self::saves_card($order);

        if (self::order_is_subscription($order)) {
            $fields = $this->subscription_fields($order);
        } else if ($instalments = self::instalment_plan($order)) {
            // Monthly instalments, the first one charged now: SecureAndPay stops after the last one.
            $fields = [
                'Clicktopay' => 1,
                'MontantReccurent' => (string) $amount,
                'ReccurentNumber' => (string) $instalments['count'],
                'ReccurentFrequency' => '1',
                'ReccurentTestPeriod' => '0',
            ];
        } else if ($oneclick) {
            $fields = [
                'Clicktopay' => 1,
                'MontantReccurent' => (string) $amount,
                'ReccurentNumber' => '1',
                'ReccurentFrequency' => '1',
                'ReccurentTestPeriod' => '0',
            ];
        } else {
            return ['Clicktopay' => 0];
        }

        $token = $oneclick ? token_store::get((int) $order->userid, $merchant->siteid) : null;
        if ($token !== null) {
            $fields['Clicktopay'] = 2;
            $fields['token'] = $token;
        }
        return $fields;
    }

    /**
     * ClicktoPay fields: SecureAndPay charges the plan price every cycle, until cancelled or for the plan's
     * number of payments, after the plan's free trial when the order starts one.
     *
     * @param object $order Subscription order.
     * @return array
     */
    private function subscription_fields(object $order): array {
        global $DB;

        $notes = json_decode((string) $order->notes, true);
        $planid = (int) ($notes['subscription']['planid'] ?? 0);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $planid], '*', MUST_EXIST);
        $trial = self::is_trial_order($order);
        $payments = self::plan_payments($planid);

        return [
            'Clicktopay' => 1,
            'MontantReccurent' => (string) (int) round((float) $plan->price * 100),
            // ReccurentNumber counts every payment of the schedule, the first one included (as with one-click
            // payment, where 1 = no second charge); a trial's first payment is the card registration, at 0 or at
            // the plan's trial price.
            'ReccurentNumber' => $payments ? (string) ($payments + ($trial ? 1 : 0)) : 'X',
            'ReccurentFrequency' => (string) (self::CYCLE_MONTHS[$plan->billing_cycle] ?? 1),
            'ReccurentTestPeriod' => (string) ($trial ? (int) $plan->trial_days : 0),
        ];
    }

    /**
     * Number of payments a subscription plan is billed for (then it ends), or 0 for billing until cancelled.
     *
     * @param int $planid Modern Commerce subscription plan.
     * @return int
     */
    public static function plan_payments(int $planid): int {
        return max(0, (int) get_config('local_secureandpay', 'payments_' . $planid));
    }

    /**
     * Amount paid at the start of a plan's trial (0 for a free trial, or a token amount such as 1).
     *
     * @param int $planid Modern Commerce subscription plan.
     * @return float
     */
    public static function plan_trial_price(int $planid): float {
        return max(0.0, round((float) get_config('local_secureandpay', 'trialprice_' . $planid), 2));
    }

    /**
     * Ask SecureAndPay to stop a ClicktoPay schedule (web service desabonnerWs.php).
     *
     * @param object $order Order whose payment started the schedule.
     * @param string $reference SecureAndPay orderID of that payment.
     * @return array [bool stopped, string SecureAndPay's answer or the error]
     */
    public static function stop_schedule(object $order, string $reference): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $merchant = self::merchant_for_order($order, false);
        if (!$merchant->is_configured()) {
            return [false, 'merchant not configured'];
        }
        $data = base64_encode(json_encode(['IdSite' => $merchant->siteid, 'orderID' => $reference]));
        $curl = new \curl();
        $answer = (string) $curl->post($merchant->testmode ? self::URL_UNSUBSCRIBE_TEST : self::URL_UNSUBSCRIBE_LIVE,
            ['data' => $data, 'sig' => $merchant->sign($data)], ['CURLOPT_TIMEOUT' => 20]);
        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        if ($curl->get_errno() || $httpcode !== 200) {
            return [false, $curl->error ?: 'HTTP ' . $httpcode];
        }
        return self::read_stop_answer($answer);
    }

    /**
     * Read the answer of desabonnerWs.php.
     *
     * {"code": 200, "msg": "..."} when the schedule is stopped; 201 missing data, 202 unknown orderID, 203 wrong
     * IdSite, 204 bad signature, 205 schedule not active (already stopped or finished: nothing more will be charged).
     *
     * @param string $answer Response body.
     * @return array [bool nothing more will be charged, string code and message]
     */
    public static function read_stop_answer(string $answer): array {
        $decoded = json_decode($answer, true);
        $code = is_array($decoded) ? (int) ($decoded['code'] ?? 0) : 0;
        $message = is_array($decoded) ? trim((string) ($decoded['msg'] ?? '')) : trim($answer);
        return [in_array($code, [200, 205], true), trim($code . ' ' . $message)];
    }

    /**
     * Instalments of a purchase paid in several times: number and amount of each, or null for a single payment.
     *
     * Set in the order notes by whoever created the order (see start_instalments()).
     *
     * @param object $order Order.
     * @return array|null ['count' => int, 'amount' => float]
     */
    public static function instalment_plan(object $order): ?array {
        $notes = json_decode((string) ($order->notes ?? ''), true);
        $count = (int) ($notes['instalments']['count'] ?? 0);
        $amount = round((float) ($notes['instalments']['amount'] ?? 0), 2);
        return $count >= 2 && $amount > 0 ? ['count' => $count, 'amount' => $amount] : null;
    }

    /**
     * SecureAndPay orderID of a purchase in instalments, kept in its notes: the buyer goes straight to pay.php.
     *
     * @param object $order Order with an instalment plan.
     * @return string SecureAndPay orderID.
     */
    public static function start_instalments(object $order): string {
        global $DB;

        $reference = self::build_reference($order, 1);
        $notes = json_decode((string) $order->notes, true) ?: [];
        $notes['instalments']['secureandpay_reference'] = $reference;
        $DB->set_field('local_moderncommerce_orders', 'notes', json_encode($notes), ['id' => $order->id]);
        return $reference;
    }

    /**
     * Charges received for an order (refused or refunded ones are not counted, nor a card registration at 0).
     *
     * Counted from SecureAndPay's own status: Modern Commerce keeps an order's payment "success" once it was paid,
     * and records later notifications of that order (a refused instalment included) with that status.
     *
     * @param object $order Order.
     * @return int
     */
    public static function instalments_paid(object $order): int {
        global $DB;

        return $DB->count_records_select('local_moderncommerce_payment_events',
            "orderid = :orderid AND gateway = :gateway AND eventtype IN ('secureandpay.00', 'secureandpay.02')
             AND amount > 0", ['orderid' => $order->id, 'gateway' => self::GATEWAY]);
    }

    /**
     * Whether a subscription order starts a free trial (created by trial.php).
     *
     * @param object $order Order.
     * @return bool
     */
    public static function is_trial_order(object $order): bool {
        $notes = json_decode((string) ($order->notes ?? ''), true);
        return !empty($notes['subscription']['trial']);
    }

    /**
     * Whether a SecureAndPay orderID belongs to this order.
     *
     * Paid orders get theirs from the Modern Commerce payment attempt; free trials and purchases in instalments,
     * which do not go through Modern Commerce's checkout, keep it in the order notes.
     *
     * @param object $order Order.
     * @param string $reference SecureAndPay orderID.
     * @return bool
     */
    public static function reference_matches(object $order, string $reference): bool {
        global $DB;

        if ($DB->record_exists('local_moderncommerce_payment_attempts',
                ['orderid' => $order->id, 'gateway' => self::GATEWAY, 'reference' => $reference])) {
            return true;
        }
        $notes = json_decode((string) ($order->notes ?? ''), true);
        return ($notes['subscription']['secureandpay_reference'] ?? null) === $reference
            || ($notes['instalments']['secureandpay_reference'] ?? null) === $reference;
    }

    /**
     * Start a free trial: the order costs 0 and SecureAndPay registers the card.
     *
     * @param object $order Trial subscription order.
     * @return string SecureAndPay orderID.
     */
    public function start_trial(object $order): string {
        $reference = self::build_reference($order, 1);
        $this->store_mandate_fields((int) $order->id, ['secureandpay_reference' => $reference]);
        return $reference;
    }

    /**
     * HMAC-SHA256 of the base64 data with the platform key.
     *
     * @param string $data Base64 payload.
     * @return string
     */
    public static function sign(string $data): string {
        return self::platform_merchant()->sign($data);
    }
}
