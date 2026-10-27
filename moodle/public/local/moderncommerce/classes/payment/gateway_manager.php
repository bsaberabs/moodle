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


use local_moderncommerce\services\pricing_service;
use moodle_exception;
use moodle_url;
use stdClass;
use xmldb_table;

/**
 * Payment gateway registry and lifecycle helper.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway_manager {
    /** @var string Gateway table name. */
    private const TABLE_GATEWAYS = 'local_moderncommerce_gateways';

    /** @var string Payment attempts table name. */
    private const TABLE_ATTEMPTS = 'local_moderncommerce_payment_attempts';

    /** @var string Payment events table name. */
    private const TABLE_EVENTS = 'local_moderncommerce_payment_events';

    /** @var string Webhook events table name. */
    private const TABLE_WEBHOOKS = 'local_moderncommerce_webhook_events';

    /**
     * Built-in gateway definitions.
     *
     * Additional gateways can be added by inserting a gateway row with a classname
     * that implements gateway_interface.
     *
     * @return array
     */
    public static function builtin_definitions(): array {
        return [
            'paystack' => [
                'gateway' => 'paystack',
                'displayname' => 'Paystack',
                'classname' => paystack_gateway::class,
                'methodtype' => 'redirect',
                'descriptionkey' => 'paystack_description',
                'icon' => 'credit-card-2-front',
                'displayorder' => 20,
                'defaultenabled' => 0,
                'supportswebhooks' => 1,
                'supportsrefunds' => 0,
                'supportsrecurring' => 1,
            ],
            'flutterwave' => [
                'gateway' => 'flutterwave',
                'displayname' => 'Flutterwave',
                'classname' => flutterwave_gateway::class,
                'methodtype' => 'redirect',
                'descriptionkey' => 'flutterwave_description',
                'icon' => 'credit-card-2-front',
                'displayorder' => 30,
                'defaultenabled' => 0,
                'supportswebhooks' => 1,
                'supportsrefunds' => 0,
                'supportsrecurring' => 1,
            ],
            'stripe' => [
                'gateway' => 'stripe',
                'displayname' => 'Stripe',
                'classname' => stripe_gateway::class,
                'methodtype' => 'redirect',
                'descriptionkey' => 'stripe_description',
                'icon' => 'stripe',
                'displayorder' => 40,
                'defaultenabled' => 0,
                'supportswebhooks' => 1,
                'supportsrefunds' => 1,
                'supportsrecurring' => 1,
            ],
            'paypal' => [
                'gateway' => 'paypal',
                'displayname' => 'PayPal',
                'classname' => paypal_gateway::class,
                'methodtype' => 'redirect',
                'descriptionkey' => 'paypal_description',
                'icon' => 'paypal',
                'displayorder' => 50,
                'defaultenabled' => 0,
                'supportswebhooks' => 1,
                'supportsrefunds' => 1,
                'supportsrecurring' => 1,
            ],
            'manual' => [
                'gateway' => 'manual',
                'displayname' => get_string('manualpayment', 'local_moderncommerce'),
                'classname' => null,
                'methodtype' => 'offline',
                'descriptionkey' => 'manualpaymentdesc',
                'icon' => 'bank',
                'displayorder' => 90,
                'defaultenabled' => 1,
                'supportswebhooks' => 0,
                'supportsrefunds' => 0,
                'supportsrecurring' => 0,
            ],
            'enrollkey' => [
                'gateway' => 'enrollkey',
                'displayname' => get_string('enrollmentkey', 'local_moderncommerce'),
                'classname' => null,
                'methodtype' => 'key',
                'descriptionkey' => 'enrollmentkeydesc',
                'icon' => 'key',
                'displayorder' => 10,
                'defaultenabled' => 0,
                'supportswebhooks' => 0,
                'supportsrefunds' => 0,
                'supportsrecurring' => 0,
            ],
        ];
    }

    /**
     * Seed or refresh the built-in gateway registry rows.
     */
    public static function sync_builtin_gateways(): void {
        global $DB;

        if (!self::table_exists(self::TABLE_GATEWAYS)) {
            return;
        }

        $now = time();
        foreach (self::builtin_definitions() as $id => $definition) {
            $record = $DB->get_record(self::TABLE_GATEWAYS, ['gateway' => $id], '*', IGNORE_MISSING);
            $legacy = self::get_legacy_gateway_config($id);
            $metadata = [
                'descriptionkey' => $definition['descriptionkey'],
                'methodtype' => $definition['methodtype'],
                'supportswebhooks' => (int)$definition['supportswebhooks'],
                'supportsrefunds' => (int)$definition['supportsrefunds'],
                'supportsrecurring' => (int)$definition['supportsrecurring'],
            ];

            if ($record) {
                $update = (object) [
                    'id' => $record->id,
                    'gateway' => $id,
                    'displayname' => !empty($record->displayname) ? $record->displayname : $definition['displayname'],
                    'displayorder' => isset($record->displayorder) ? (int)$record->displayorder : (int)$definition['displayorder'],
                    'icon' => !empty($record->icon) ? $record->icon : $definition['icon'],
                    'classname' => $definition['classname'],
                    'component' => 'local_moderncommerce',
                    'methodtype' => $definition['methodtype'],
                    'supportswebhooks' => (int)$definition['supportswebhooks'],
                    'supportsrefunds' => (int)$definition['supportsrefunds'],
                    'supportsrecurring' => (int)$definition['supportsrecurring'],
                    'configdata' => json_encode($metadata),
                    'publickey' => !empty($record->publickey) ? $record->publickey : ($legacy['public_key'] ?? null),
                    'secretkey' => !empty($record->secretkey) ? $record->secretkey : ($legacy['secret_key'] ?? null),
                    'merchantid' => !empty($record->merchantid) ? $record->merchantid : ($legacy['merchantid'] ?? null),
                    'webhooksecret' => !empty($record->webhooksecret)
                        ? $record->webhooksecret
                        : ($legacy['webhook_secret'] ?? null),
                    'timemodified' => $now,
                ];
                $DB->update_record(self::TABLE_GATEWAYS, self::filter_record_fields(self::TABLE_GATEWAYS, $update));
                continue;
            }

            $insert = (object) [
                'gateway' => $id,
                'enabled' => array_key_exists('enabled', $legacy) ? (int)$legacy['enabled'] : (int)$definition['defaultenabled'],
                'displayname' => $definition['displayname'],
                'displayorder' => (int)$definition['displayorder'],
                'testmode' => array_key_exists('testmode', $legacy) ? (int)$legacy['testmode'] : 1,
                'icon' => $definition['icon'],
                'classname' => $definition['classname'],
                'component' => 'local_moderncommerce',
                'methodtype' => $definition['methodtype'],
                'supportswebhooks' => (int)$definition['supportswebhooks'],
                'supportsrefunds' => (int)$definition['supportsrefunds'],
                'supportsrecurring' => (int)$definition['supportsrecurring'],
                'publickey' => $legacy['public_key'] ?? null,
                'secretkey' => $legacy['secret_key'] ?? null,
                'merchantid' => $legacy['merchantid'] ?? null,
                'webhooksecret' => $legacy['webhook_secret'] ?? null,
                'configdata' => json_encode($metadata),
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record(self::TABLE_GATEWAYS, self::filter_record_fields(self::TABLE_GATEWAYS, $insert));
        }
    }

    /**
     * Get checkout-ready payment methods.
     *
     * @return array
     */
    public static function get_payment_methods(): array {
        $methods = [];
        $definitions = self::get_registry_definitions();

        uasort($definitions, static function (array $a, array $b): int {
            return ((int)$a['displayorder'] <=> (int)$b['displayorder']) ?: strcmp($a['gateway'], $b['gateway']);
        });

        foreach ($definitions as $id => $definition) {
            if (!self::is_definition_enabled($definition)) {
                continue;
            }

            if ($definition['methodtype'] === 'redirect' && !self::get_gateway_readiness($id, null, $definition)->ready) {
                continue;
            }

            $description = '';
            if (!empty($definition['descriptionkey'])) {
                $description = get_string($definition['descriptionkey'], 'local_moderncommerce');
            }

            $methods[$id] = [
                'id' => $id,
                'name' => $definition['displayname'],
                'description' => $description,
                'icon' => $definition['icon'] ?? 'credit-card-2-front',
                'methodtype' => $definition['methodtype'],
            ];
        }

        return $methods;
    }

    /**
     * Determine whether a payment method is currently selectable.
     *
     * @param string $gatewayid Gateway ID.
     * @return bool
     */
    public static function is_payment_method_available(string $gatewayid): bool {
        $gatewayid = self::normalize_gateway_id($gatewayid);
        $methods = self::get_payment_methods();
        return isset($methods[$gatewayid]);
    }

    /**
     * Get operational readiness for a gateway.
     *
     * @param string $gatewayid Gateway ID.
     * @param object|null $order Optional order snapshot for payment-specific checks.
     * @param array|null $definition Optional already-loaded gateway definition.
     * @return stdClass Readiness details.
     */
    public static function get_gateway_readiness(
        string $gatewayid,
        ?object $order = null,
        ?array $definition = null
    ): stdClass {
        $gatewayid = self::normalize_gateway_id($gatewayid);
        $definitions = $definition === null ? self::get_registry_definitions() : [$gatewayid => $definition];
        $definition = $definition ?? ($definitions[$gatewayid] ?? null);

        $activecurrency = strtoupper(pricing_service::get_currency_config()->currency);
        $ordercurrency = !empty($order->currency) ? strtoupper((string)$order->currency) : '';
        $methodtype = (string)($definition['methodtype'] ?? 'redirect');
        $hosted = $methodtype === 'redirect';
        $enabled = $definition !== null && self::is_definition_enabled($definition);
        $supported = [];
        $reasoncode = '';
        $message = '';
        $configured = !$hosted;
        $currencysupported = !$hosted;
        $ordercurrencysupported = !$hosted;
        $ordercurrencymatches = true;
        $amountvalid = true;

        if ($definition === null) {
            return (object) [
                'gateway' => $gatewayid,
                'activecurrency' => $activecurrency,
                'ordercurrency' => $ordercurrency,
                'methodtype' => $methodtype,
                'hosted' => true,
                'enabled' => false,
                'configured' => false,
                'currencysupported' => false,
                'ordercurrencysupported' => false,
                'ordercurrencymatches' => false,
                'amountvalid' => false,
                'ready' => false,
                'supportedcurrencies' => [],
                'reasoncode' => 'gatewaynotconfigured',
                'message' => get_string('gatewaynotconfigured', 'local_moderncommerce', $gatewayid),
            ];
        }

        if (!$enabled) {
            $reasoncode = 'gatewaydisabled';
            $message = get_string('gatewaydisabled', 'local_moderncommerce', $gatewayid);
        }

        if ($hosted) {
            if (empty($definition['classname'])) {
                $configured = false;
                $reasoncode = $reasoncode ?: 'gatewaynotconfigured';
                $message = $message ?: get_string('gatewaynotconfigured', 'local_moderncommerce', $gatewayid);
            } else {
                try {
                    $gateway = self::get_gateway($gatewayid);
                    $configured = $gateway->is_enabled();
                    $supported = self::get_supported_currencies_for_gateway($gatewayid, $definition, $gateway);
                    $currencysupported = self::currency_is_supported($activecurrency, $supported);
                    $ordercurrencysupported = $ordercurrency === ''
                        ? true
                        : self::currency_is_supported($ordercurrency, $supported);
                } catch (\Throwable $e) {
                    $configured = false;
                    $reasoncode = $reasoncode ?: 'gatewaynotconfigured';
                    $message = $message ?: $e->getMessage();
                    debugging('Payment gateway readiness failed: ' . $gatewayid . ' - ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }

            if (!$configured) {
                $reasoncode = $reasoncode ?: 'gatewaynotconfigured';
                $message = $message ?: get_string('gatewaynotconfigured', 'local_moderncommerce', $gatewayid);
            }

            if (!$currencysupported) {
                $reasoncode = $reasoncode ?: 'gatewaycurrencyunsupported';
                $message = $message ?: get_string(
                    'gatewaycurrencyunsupported',
                    'local_moderncommerce',
                    (object)['gateway' => $definition['displayname'] ?? $gatewayid, 'currency' => $activecurrency]
                );
            }

            if (!$ordercurrencysupported) {
                $reasoncode = $reasoncode ?: 'gatewayordercurrencyunsupported';
                $message = $message ?: get_string(
                    'gatewayordercurrencyunsupported',
                    'local_moderncommerce',
                    (object)['gateway' => $definition['displayname'] ?? $gatewayid, 'currency' => $ordercurrency]
                );
            }
        }

        if ($order !== null) {
            $amountvalid = (float)($order->total ?? 0) > 0;
            $ordercurrencymatches = $ordercurrency !== '' && $ordercurrency === $activecurrency;

            if (!$amountvalid) {
                $reasoncode = $reasoncode ?: 'paymentinvalidamount';
                $message = $message ?: get_string('paymentinvalidamount', 'local_moderncommerce');
            }

            if (!$ordercurrencymatches) {
                $reasoncode = $reasoncode ?: 'ordercurrencymismatch';
                $message = $message ?: get_string(
                    'ordercurrencymismatch',
                    'local_moderncommerce',
                    (object)['ordercurrency' => $ordercurrency ?: get_string('unknown', 'local_moderncommerce'),
                    'activecurrency' => $activecurrency]
                );
            }
        }

        $ready = $enabled && $configured && $currencysupported && $ordercurrencysupported
            && $amountvalid && $ordercurrencymatches;

        if ($ready) {
            $reasoncode = '';
            $message = get_string('ready', 'local_moderncommerce');
        }

        return (object) [
            'gateway' => $gatewayid,
            'activecurrency' => $activecurrency,
            'ordercurrency' => $ordercurrency,
            'methodtype' => $methodtype,
            'hosted' => $hosted,
            'enabled' => $enabled,
            'configured' => $configured,
            'currencysupported' => $currencysupported,
            'ordercurrencysupported' => $ordercurrencysupported,
            'ordercurrencymatches' => $ordercurrencymatches,
            'amountvalid' => $amountvalid,
            'ready' => $ready,
            'supportedcurrencies' => $supported,
            'reasoncode' => $reasoncode,
            'message' => $message,
        ];
    }

    /**
     * Get a gateway implementation.
     *
     * @param string $gatewayid Gateway ID.
     * @return gateway_interface
     */
    public static function get_gateway(string $gatewayid): gateway_interface {
        $gatewayid = self::normalize_gateway_id($gatewayid);
        $definitions = self::get_registry_definitions();

        if (empty($definitions[$gatewayid]) || empty($definitions[$gatewayid]['classname'])) {
            throw new moodle_exception('invalidpaymentmethod', 'local_moderncommerce');
        }

        $classname = $definitions[$gatewayid]['classname'];
        if (!class_exists($classname) || !is_subclass_of($classname, gateway_interface::class)) {
            throw new moodle_exception('gatewaynotconfigured', 'local_moderncommerce', '', $gatewayid);
        }

        return new $classname();
    }

    /**
     * Get normalized gateway configuration from the registry.
     *
     * Legacy plugin settings are read only as a fallback to avoid losing existing
     * development credentials during the transition to registry-backed config.
     *
     * @param string $gatewayid Gateway ID.
     * @return array
     */
    public static function get_gateway_config(string $gatewayid): array {
        global $DB;

        $gatewayid = self::normalize_gateway_id($gatewayid);
        $config = self::get_legacy_gateway_config($gatewayid);

        if (self::table_exists(self::TABLE_GATEWAYS)) {
            $record = $DB->get_record(self::TABLE_GATEWAYS, ['gateway' => $gatewayid], '*', IGNORE_MISSING);
            if ($record) {
                $extra = self::decode_configdata($record->configdata ?? null);
                $config = array_merge($config, $extra);
                $config['enabled'] = (bool)$record->enabled;
                $config['testmode'] = (bool)$record->testmode;
                $config['sandbox_mode'] = (bool)$record->testmode;
                $config['public_key'] = $record->publickey ?: ($config['public_key'] ?? '');
                $config['secret_key'] = $record->secretkey ?: ($config['secret_key'] ?? '');
                $config['merchantid'] = $record->merchantid ?: ($config['merchantid'] ?? '');
                $config['client_id'] = $record->merchantid ?: ($config['client_id'] ?? '');
                $config['webhook_secret'] = $record->webhooksecret ?: ($config['webhook_secret'] ?? '');
                $config['secret_hash'] = $record->webhooksecret ?: ($config['secret_hash'] ?? '');
                $config['webhook_id'] = $record->webhooksecret ?: ($config['webhook_id'] ?? '');
                $config['supported_currencies'] = $record->supportedcurrencies ?: ($config['supported_currencies'] ?? '');
            }
        }

        return $config;
    }

    /**
     * Get registry rows for admin management.
     *
     * @return array
     */
    public static function get_gateway_admin_rows(): array {
        self::sync_builtin_gateways();

        $definitions = self::get_registry_definitions();
        uasort($definitions, static function (array $a, array $b): int {
            return ((int)$a['displayorder'] <=> (int)$b['displayorder']) ?: strcmp($a['gateway'], $b['gateway']);
        });

        return $definitions;
    }

    /**
     * Save a gateway registry row.
     *
     * @param string $gatewayid Gateway ID.
     * @param array $data Submitted data.
     */
    public static function save_gateway_config(string $gatewayid, array $data): void {
        global $DB;

        if (!self::table_exists(self::TABLE_GATEWAYS)) {
            throw new moodle_exception('gatewaynotconfigured', 'local_moderncommerce', '', $gatewayid);
        }

        $gatewayid = self::normalize_gateway_id($gatewayid);
        $record = $DB->get_record(self::TABLE_GATEWAYS, ['gateway' => $gatewayid], '*', IGNORE_MISSING);
        $now = time();
        $existingextra = $record ? self::decode_configdata($record->configdata ?? null) : [];
        $extra = array_merge($existingextra, [
            'descriptionkey' => $data['descriptionkey'] ?? ($existingextra['descriptionkey'] ?? ''),
            'methodtype' => $data['methodtype'] ?? ($record->methodtype ?? 'redirect'),
            'supportswebhooks' => !empty($data['supportswebhooks']) ? 1 : 0,
            'supportsrefunds' => !empty($data['supportsrefunds']) ? 1 : 0,
            'supportsrecurring' => !empty($data['supportsrecurring']) ? 1 : 0,
            'ip_whitelist' => $data['ip_whitelist'] ?? ($existingextra['ip_whitelist'] ?? ''),
        ]);

        $save = (object) [
            'gateway' => $gatewayid,
            'enabled' => !empty($data['enabled']) ? 1 : 0,
            'displayname' => $data['displayname'] ?? ucfirst($gatewayid),
            'displayorder' => (int)($data['displayorder'] ?? 0),
            'component' => $data['component'] ?? 'local_moderncommerce',
            'classname' => $data['classname'] ?? null,
            'methodtype' => $data['methodtype'] ?? 'redirect',
            'testmode' => !empty($data['testmode']) ? 1 : 0,
            'publickey' => $data['publickey'] ?? null,
            'secretkey' => !empty($data['secretkey']) ? $data['secretkey'] : ($record->secretkey ?? null),
            'merchantid' => $data['merchantid'] ?? null,
            'webhooksecret' => !empty($data['webhooksecret']) ? $data['webhooksecret'] : ($record->webhooksecret ?? null),
            'supportedcurrencies' => $data['supportedcurrencies'] ?? null,
            'supportsrefunds' => !empty($data['supportsrefunds']) ? 1 : 0,
            'supportswebhooks' => !empty($data['supportswebhooks']) ? 1 : 0,
            'supportsrecurring' => !empty($data['supportsrecurring']) ? 1 : 0,
            'icon' => $data['icon'] ?? 'credit-card-2-front',
            'configdata' => json_encode($extra),
            'timemodified' => $now,
        ];

        if ($record) {
            $save->id = $record->id;
            $save->timecreated = $record->timecreated;
            $DB->update_record(self::TABLE_GATEWAYS, self::filter_record_fields(self::TABLE_GATEWAYS, $save));
            return;
        }

        $save->timecreated = $now;
        $DB->insert_record(self::TABLE_GATEWAYS, self::filter_record_fields(self::TABLE_GATEWAYS, $save));
    }

    /**
     * Initialize a hosted gateway payment and record the attempt.
     *
     * @param string $gatewayid Gateway ID.
     * @param object $order Order record.
     * @return array Gateway initialization result.
     */
    public static function initialize_payment(string $gatewayid, object $order): array {
        $gatewayid = self::normalize_gateway_id($gatewayid);

        $readiness = self::get_gateway_readiness($gatewayid, $order);
        if (!$readiness->ready) {
            throw new moodle_exception(
                'gatewayunavailable',
                'local_moderncommerce',
                '',
                $readiness->message ?: $gatewayid
            );
        }

        $gateway = self::get_gateway($gatewayid);
        $payment = $gateway->initialize_payment($order);
        self::record_payment_attempt($order, $gatewayid, $payment, 'pending');

        return $payment;
    }

    /**
     * Process a browser return for a hosted gateway.
     *
     * @param string $gatewayid Gateway ID.
     * @param array $params Return request parameters.
     * @return \stdClass Normalized payment result.
     */
    public static function process_return(string $gatewayid, array $params): stdClass {
        $gateway = self::get_gateway($gatewayid);
        if (!$gateway instanceof gateway_return_interface) {
            throw new moodle_exception('gatewayreturnunsupported', 'local_moderncommerce', '', $gatewayid);
        }

        return $gateway->process_return($params);
    }

    /**
     * Process a webhook payload for a gateway.
     *
     * @param string $gatewayid Gateway ID.
     * @param array $payload Webhook payload.
     * @param string|null $rawpayload Raw request body.
     * @param array $headers Request headers.
     * @return bool
     */
    public static function process_webhook(
        string $gatewayid,
        array $payload,
        ?string $rawpayload = null,
        array $headers = []
    ): bool {
        $gatewayid = self::normalize_gateway_id($gatewayid);
        $gateway = self::get_gateway($gatewayid);

        try {
            $success = $gateway->process_webhook($payload, $headers, $rawpayload);
        } catch (\Throwable $e) {
            self::record_webhook_event($gatewayid, $payload, 'failed', $e->getMessage());
            self::notify_admins_gateway_error($gatewayid, $e->getMessage());
            throw $e;
        }

        self::record_webhook_event($gatewayid, $payload, $success ? 'processed' : 'failed');
        if (!$success) {
            self::notify_admins_gateway_error($gatewayid, 'Webhook processing returned failure');
        }
        return $success;
    }

    /**
     * Alert store admins that a payment gateway is erroring (operational), via the hub.
     *
     * @param string $gatewayid Gateway id.
     * @param string $detail Error detail.
     * @return void
     */
    private static function notify_admins_gateway_error(string $gatewayid, string $detail): void {
        global $CFG;

        $url = $CFG->wwwroot . '/local/moderncommerce/index.php';
        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'gateway_error'))
            ->category('operational')
            ->template('ops_gateway_error')
            ->placeholders([
                'gateway_name' => ucfirst($gatewayid),
                'error_detail' => \core_text::substr($detail, 0, 200),
                'failed_count' => 1,
                'period_label' => 'just now',
                'admin_dashboard_url' => $url,
            ])
            ->context_url($url)
            // Per-gateway, per-day dedupe so distinct gateways alert separately but not repeatedly.
            ->related((int) (abs(crc32($gatewayid)) % 100000));

        \local_moderncommerce\notifications\api::notify_admins($notification);
    }

    /**
     * Send the buyer a dunning "payment failed — retry" notice via the hub.
     *
     * @param object $order Order record.
     * @param stdClass $result Normalized payment result.
     * @return void
     */
    private static function notify_buyer_payment_failed(object $order, stdClass $result): void {
        global $CFG, $DB;

        $userid = (int) ($order->userid ?? 0);
        if ($userid <= 0) {
            return;
        }

        $names = [];
        foreach ($DB->get_records('local_moderncommerce_order_items', ['orderid' => $order->id]) as $item) {
            if (!empty($item->coursename)) {
                $names[] = $item->coursename;
            } else if (!empty($item->bundlename)) {
                $names[] = $item->bundlename;
            } else if (!empty($item->itemname)) {
                $names[] = $item->itemname;
            } else if (!empty($item->courseid)) {
                $names[] = (string) $DB->get_field('course', 'fullname', ['id' => $item->courseid]);
            }
        }
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            $total = \local_moderncommerce\services\pricing_service::format_order_price((float) $order->total, $order);
        } else {
            $total = number_format((float) $order->total, 2);
        }
        $url = $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id;

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'order_payment_failed'))
            ->category('dunning')
            ->template('moderncommerce_order_payment_failed')
            ->to_user($userid)
            ->placeholders([
                'order_number' => $order->ordernumber ?? ('#' . $order->id),
                'order_total' => $total,
                'courses_list' => implode('<br>', array_filter($names)),
                'retry_payment_url' => $url,
            ])
            ->context_url($url)
            ->related((int) $order->id);

        \local_moderncommerce\notifications\api::notify($notification);
    }

    /**
     * Record a normalized payment result in the payment attempt ledger.
     *
     * @param object $order Order record.
     * @param string $gatewayid Gateway ID.
     * @param \stdClass $result Normalized result.
     * @return \stdClass Ledger metadata.
     */
    public static function record_payment_result(object $order, string $gatewayid, stdClass $result): stdClass {
        global $DB;

        $gatewayid = self::normalize_gateway_id($gatewayid);
        $referencecandidates = [
            $result->reference ?? null,
            $result->orderreference ?? null,
            $order->ordernumber ?? null,
            $result->gatewayreference ?? null,
        ];
        $transactioncandidates = [
            $result->gatewaytransactionid ?? null,
            $result->transactionid ?? null,
            $result->gatewayreference ?? null,
        ];
        $reference = self::first_non_empty($referencecandidates);
        $status = self::normalize_payment_status($result->status ?? 'pending');
        $result->status = $status;
        $attemptid = 0;
        $previousstatus = null;
        $persistedstatus = $status;
        $now = time();

        if (self::table_exists(self::TABLE_ATTEMPTS)) {
            $attempt = self::find_payment_attempt($gatewayid, (int)$order->id, $referencecandidates, $transactioncandidates);
            if ($attempt) {
                $reference = (string)$attempt->reference;
            }
            $previousstatus = $attempt->status ?? null;
            $persistedstatus = self::resolve_attempt_status($previousstatus, $status);

            $record = (object) [
                'orderid' => $order->id,
                'gateway' => $gatewayid,
                'reference' => $reference,
                'amount' => self::resolve_payment_amount($order, $result),
                'currency' => self::resolve_payment_currency($order, $result),
                'status' => $persistedstatus,
                'gatewaytransactionid' => self::first_non_empty([
                    $result->gatewaytransactionid ?? null,
                    $result->transactionid ?? null,
                    $result->gatewayreference ?? null,
                    $attempt->gatewaytransactionid ?? null,
                ]),
                'errormessage' => $persistedstatus === 'failed' ? ($result->message ?? null) : null,
                'timemodified' => $now,
            ];

            if ($attempt) {
                $record->id = $attempt->id;
                $record->timecreated = $attempt->timecreated;
                $record->timecompleted = self::is_completed_payment_status($persistedstatus)
                    ? ($attempt->timecompleted ?: $now)
                    : $attempt->timecompleted;
                $DB->update_record(self::TABLE_ATTEMPTS, self::filter_record_fields(self::TABLE_ATTEMPTS, $record));
                $attemptid = (int)$attempt->id;
            } else {
                $record->idempotencykey = hash('sha256', $order->id . '|' . $gatewayid . '|' . $reference);
                $record->timecreated = $now;
                $record->timecompleted = self::is_completed_payment_status($persistedstatus) ? $now : null;
                $attemptid = (int)$DB->insert_record(
                    self::TABLE_ATTEMPTS,
                    self::filter_record_fields(self::TABLE_ATTEMPTS, $record)
                );
            }
        }

        $event = self::record_payment_event($order, $gatewayid, $result, $attemptid ?: null, $persistedstatus);
        self::update_order_operational_payment_lifecycle(
            (int)$order->id,
            $attemptid,
            (int)($event->eventid ?? 0),
            $persistedstatus
        );
        self::record_legacy_transaction($order, $gatewayid, $result);

        // On a fresh payment failure, send the buyer a dunning "retry" notice via the hub.
        if ($persistedstatus === 'failed' && $previousstatus !== 'failed') {
            self::notify_buyer_payment_failed($order, $result);
        }

        return (object) [
            'attemptid' => $attemptid,
            'eventid' => (int)($event->eventid ?? 0),
            'dedupekey' => $event->dedupekey ?? null,
            'duplicate' => !empty($event->duplicate),
            'incomingstatus' => $status,
            'status' => $persistedstatus,
            'previousstatus' => $previousstatus,
        ];
    }

    /**
     * Find the attempt that belongs to a gateway payment result.
     *
     * Hosted gateways do not all use the same reference as their transaction ID. We first match the business
     * reference written at initialization, then gateway transaction/session values, then the latest order attempt.
     *
     * @param string $gatewayid Gateway ID.
     * @param int $orderid Order ID.
     * @param array $references Candidate attempt references.
     * @param array $transactionids Candidate gateway transaction IDs.
     * @return stdClass|null Existing attempt.
     */
    private static function find_payment_attempt(
        string $gatewayid,
        int $orderid,
        array $references,
        array $transactionids
    ): ?stdClass {
        global $DB;

        foreach ($references as $reference) {
            $reference = trim((string)$reference);
            if ($reference === '') {
                continue;
            }

            $records = $DB->get_records(self::TABLE_ATTEMPTS, [
                'gateway' => $gatewayid,
                'reference' => $reference,
            ], 'id DESC', '*', 0, 1);
            if (!empty($records)) {
                return reset($records);
            }
        }

        foreach ($transactionids as $transactionid) {
            $transactionid = trim((string)$transactionid);
            if ($transactionid === '') {
                continue;
            }

            $records = $DB->get_records(self::TABLE_ATTEMPTS, [
                'gateway' => $gatewayid,
                'gatewaytransactionid' => $transactionid,
            ], 'id DESC', '*', 0, 1);
            if (!empty($records)) {
                return reset($records);
            }
        }

        if ($orderid > 0) {
            $records = $DB->get_records(self::TABLE_ATTEMPTS, [
                'gateway' => $gatewayid,
                'orderid' => $orderid,
            ], 'id DESC', '*', 0, 1);
            if (!empty($records)) {
                return reset($records);
            }
        }

        return null;
    }

    /**
     * Build the generic payment initialization URL.
     *
     * @param string $gatewayid Gateway ID.
     * @param int $orderid Order ID.
     * @return moodle_url
     */
    public static function init_url(string $gatewayid, int $orderid): moodle_url {
        return new moodle_url('/local/moderncommerce/payment/init.php', [
            'gateway' => self::normalize_gateway_id($gatewayid),
            'orderid' => $orderid,
        ]);
    }

    /**
     * Build the generic callback URL.
     *
     * @param string $gatewayid Gateway ID.
     * @param array $params Additional URL params.
     * @return moodle_url
     */
    public static function callback_url(string $gatewayid, array $params = []): moodle_url {
        return new moodle_url('/local/moderncommerce/payment/callback.php', array_merge([
            'gateway' => self::normalize_gateway_id($gatewayid),
        ], $params));
    }

    /**
     * Build the generic webhook URL.
     *
     * @param string $gatewayid Gateway ID.
     * @return moodle_url
     */
    public static function webhook_url(string $gatewayid): moodle_url {
        return new moodle_url('/local/moderncommerce/payment/webhook.php', [
            'gateway' => self::normalize_gateway_id($gatewayid),
        ]);
    }

    /**
     * Normalize a gateway ID.
     *
     * @param string $gatewayid Gateway ID.
     * @return string
     */
    public static function normalize_gateway_id(string $gatewayid): string {
        return preg_replace('/[^a-z0-9_\\-]/', '', strtolower(trim($gatewayid)));
    }

    /**
     * Get built-in and DB-defined gateway definitions.
     *
     * @return array
     */
    private static function get_registry_definitions(): array {
        global $DB;

        $definitions = self::builtin_definitions();

        if (!self::table_exists(self::TABLE_GATEWAYS)) {
            return $definitions;
        }

        $records = $DB->get_records(self::TABLE_GATEWAYS, null, 'displayorder ASC, gateway ASC');
        if (empty($records)) {
            self::sync_builtin_gateways();
            $records = $DB->get_records(self::TABLE_GATEWAYS, null, 'displayorder ASC, gateway ASC');
        }

        foreach ($records as $record) {
            $id = self::normalize_gateway_id($record->gateway);
            $metadata = self::decode_configdata($record->configdata ?? null);
            $base = $definitions[$id] ?? [
                'gateway' => $id,
                'displayname' => $record->displayname ?: ucfirst($id),
                'classname' => null,
                'methodtype' => 'redirect',
                'descriptionkey' => '',
                'icon' => 'credit-card-2-front',
                'displayorder' => (int)$record->displayorder,
                'enabledconfig' => '',
                'defaultenabled' => 0,
                'supportswebhooks' => 0,
                'supportsrefunds' => 0,
                'supportsrecurring' => 0,
            ];

            $definitions[$id] = array_merge($base, [
                'gateway' => $id,
                'displayname' => $record->displayname ?: $base['displayname'],
                'classname' => $record->classname ?? ($metadata['classname'] ?? $base['classname']),
                'component' => $record->component ?? 'local_moderncommerce',
                'methodtype' => $record->methodtype ?? ($metadata['methodtype'] ?? $base['methodtype']),
                'descriptionkey' => $metadata['descriptionkey'] ?? $base['descriptionkey'],
                'icon' => $record->icon ?: $base['icon'],
                'displayorder' => (int)($record->displayorder ?? $base['displayorder']),
                'recordenabled' => (int)$record->enabled,
                'testmode' => (int)($record->testmode ?? 1),
                'publickey' => $record->publickey ?? '',
                'secretconfigured' => !empty($record->secretkey),
                'merchantid' => $record->merchantid ?? '',
                'webhooksecretconfigured' => !empty($record->webhooksecret),
                'supportedcurrencies' => $record->supportedcurrencies ?? '',
                'ip_whitelist' => $metadata['ip_whitelist'] ?? '',
                'supportswebhooks' => (int)(
                    $record->supportswebhooks ?? ($metadata['supportswebhooks'] ?? $base['supportswebhooks'])
                ),
                'supportsrefunds' => (int)($record->supportsrefunds ?? ($metadata['supportsrefunds'] ?? $base['supportsrefunds'])),
                'supportsrecurring' => (int)(
                    $record->supportsrecurring ?? ($metadata['supportsrecurring'] ?? $base['supportsrecurring'])
                ),
            ]);
        }

        return $definitions;
    }

    /**
     * Determine if a definition is enabled.
     *
     * @param array $definition Gateway definition.
     * @return bool
     */
    private static function is_definition_enabled(array $definition): bool {
        if (array_key_exists('recordenabled', $definition)) {
            return !empty($definition['recordenabled']);
        }

        return !empty($definition['defaultenabled']);
    }

    /**
     * Get supported currencies from gateway metadata or implementation.
     *
     * @param string $gatewayid Gateway ID.
     * @param array $definition Gateway definition.
     * @param gateway_interface|null $gateway Gateway instance.
     * @return array Currency codes.
     */
    private static function get_supported_currencies_for_gateway(
        string $gatewayid,
        array $definition,
        ?gateway_interface $gateway = null
    ): array {
        $configured = self::parse_currency_list((string)($definition['supportedcurrencies'] ?? ''));
        if (!empty($configured)) {
            return $configured;
        }

        $gateway = $gateway ?: self::get_gateway($gatewayid);
        return self::parse_currency_list($gateway->get_supported_currencies());
    }

    /**
     * Normalize a currency list from a string or array.
     *
     * @param array|string $currencies Currency list.
     * @return array Currency codes.
     */
    private static function parse_currency_list($currencies): array {
        if (is_array($currencies)) {
            $parts = $currencies;
        } else {
            $parts = preg_split('/[\s,;|]+/', (string)$currencies, -1, PREG_SPLIT_NO_EMPTY);
        }

        $normalized = [];
        foreach ($parts as $currency) {
            $currency = strtoupper(trim((string)$currency));
            if ($currency !== '') {
                $normalized[$currency] = $currency;
            }
        }

        return array_values($normalized);
    }

    /**
     * Determine whether a currency is supported.
     *
     * @param string $currency Currency code.
     * @param array $supported Supported currency codes. Empty means no restriction.
     * @return bool
     */
    private static function currency_is_supported(string $currency, array $supported): bool {
        $currency = strtoupper(trim($currency));
        return $currency !== '' && (empty($supported) || in_array($currency, $supported, true));
    }

    /**
     * Record a payment initialization attempt.
     *
     * @param object $order Order record.
     * @param string $gatewayid Gateway ID.
     * @param array $payment Gateway initialization result.
     * @param string $status Attempt status.
     * @return int Attempt ID or 0.
     */
    private static function record_payment_attempt(object $order, string $gatewayid, array $payment, string $status): int {
        global $DB;

        if (!self::table_exists(self::TABLE_ATTEMPTS)) {
            return 0;
        }

        $reference = self::first_non_empty([
            $payment['reference'] ?? null,
            $payment['paypal_order_id'] ?? null,
            $payment['session_id'] ?? null,
            $order->ordernumber ?? null,
        ]);
        $gatewaytransactionid = self::first_non_empty([
            $payment['gatewaytransactionid'] ?? null,
            $payment['paypal_order_id'] ?? null,
            $payment['session_id'] ?? null,
        ]);

        $attempt = $DB->get_record(self::TABLE_ATTEMPTS, [
            'gateway' => $gatewayid,
            'reference' => $reference,
        ], '*', IGNORE_MISSING);

        $record = (object) [
            'orderid' => $order->id,
            'gateway' => $gatewayid,
            'reference' => $reference,
            'amount' => (float)($order->total ?? 0),
            'currency' => $order->currency ?? '',
            'status' => $status,
            'idempotencykey' => hash('sha256', $order->id . '|' . $gatewayid . '|' . $reference),
            'gatewaytransactionid' => $gatewaytransactionid,
            'redirecturl' => $payment['authorization_url'] ?? ($payment['redirect_url'] ?? null),
            'timemodified' => time(),
        ];

        if ($attempt) {
            $record->id = $attempt->id;
            $record->timecreated = $attempt->timecreated;
            $DB->update_record(self::TABLE_ATTEMPTS, self::filter_record_fields(self::TABLE_ATTEMPTS, $record));
            return (int)$attempt->id;
        }

        $record->timecreated = time();
        return (int)$DB->insert_record(self::TABLE_ATTEMPTS, self::filter_record_fields(self::TABLE_ATTEMPTS, $record));
    }

    /**
     * Record a normalized payment event.
     *
     * @param object $order Order record.
     * @param string $gatewayid Gateway ID.
     * @param \stdClass $result Payment result.
     * @param int|null $attemptid Payment attempt ID.
     * @param string|null $status Persisted payment status.
     * @return \stdClass Payment event metadata.
     */
    private static function record_payment_event(
        object $order,
        string $gatewayid,
        stdClass $result,
        ?int $attemptid = null,
        ?string $status = null
    ): stdClass {
        global $DB;

        if (!self::table_exists(self::TABLE_EVENTS)) {
            return (object) [
                'eventid' => 0,
                'dedupekey' => null,
                'duplicate' => false,
            ];
        }

        $rawpayload = self::payload_for_storage($result->rawdata ?? $result);
        $payloadhash = hash('sha256', (string)$rawpayload);
        $status = self::normalize_payment_status($status ?? ($result->status ?? 'received'));
        $eventtype = self::first_non_empty([
            $result->eventtype ?? null,
            !empty($result->source) ? $result->source . '.' . $status : null,
            'return.' . $status,
        ]);
        $gatewayeventid = self::first_non_empty([
            $result->gatewayeventid ?? null,
            $result->eventid ?? null,
        ]) ?: null;
        $reference = self::first_non_empty([
            $result->reference ?? null,
            $result->orderreference ?? null,
            $order->ordernumber ?? null,
            $result->gatewayreference ?? null,
        ]);
        $transactionid = self::first_non_empty([
            $result->gatewaytransactionid ?? null,
            $result->transactionid ?? null,
        ]) ?: null;
        $dedupesource = self::first_non_empty([
            $gatewayeventid,
            $transactionid,
            $reference,
            $payloadhash,
        ]);
        $dedupekey = hash('sha256', $gatewayid . '|' . $eventtype . '|' . $dedupesource);

        $existing = $DB->get_record(
            self::TABLE_EVENTS,
            ['dedupekey' => $dedupekey],
            'id, attemptid, reference, status',
            IGNORE_MISSING
        );
        if ($existing) {
            $eventupdate = new stdClass();
            $eventupdate->id = (int)$existing->id;
            $needsupdate = false;
            if (!empty($attemptid) && (int)($existing->attemptid ?? 0) !== (int)$attemptid) {
                $eventupdate->attemptid = (int)$attemptid;
                $needsupdate = true;
            }
            if ($reference !== '' && (string)($existing->reference ?? '') !== $reference) {
                $eventupdate->reference = $reference;
                $needsupdate = true;
            }
            if ($needsupdate) {
                $DB->update_record(self::TABLE_EVENTS, self::filter_record_fields(self::TABLE_EVENTS, $eventupdate));
            }

            return (object) [
                'eventid' => (int)$existing->id,
                'dedupekey' => $dedupekey,
                'duplicate' => true,
                'status' => $existing->status,
            ];
        }

        $record = (object) [
            'orderid' => $order->id,
            'attemptid' => $attemptid,
            'gateway' => $gatewayid,
            'dedupekey' => $dedupekey,
            'eventtype' => $eventtype,
            'gatewayeventid' => $gatewayeventid,
            'reference' => $reference ?: null,
            'transactionid' => $transactionid,
            'amount' => self::resolve_payment_amount($order, $result),
            'currency' => self::resolve_payment_currency($order, $result),
            'status' => $status,
            'verified' => $status === 'success' ? 1 : 0,
            'payloadhash' => $payloadhash,
            'rawpayload' => $rawpayload,
            'processed' => 1,
            'timecreated' => time(),
            'timeprocessed' => time(),
        ];

        try {
            $eventid = (int)$DB->insert_record(self::TABLE_EVENTS, self::filter_record_fields(self::TABLE_EVENTS, $record));
        } catch (\dml_write_exception $e) {
            $existing = $DB->get_record(
                self::TABLE_EVENTS,
                ['dedupekey' => $dedupekey],
                'id, attemptid, reference, status',
                IGNORE_MISSING
            );
            if ($existing) {
                $eventupdate = new stdClass();
                $eventupdate->id = (int)$existing->id;
                $needsupdate = false;
                if (!empty($attemptid) && (int)($existing->attemptid ?? 0) !== (int)$attemptid) {
                    $eventupdate->attemptid = (int)$attemptid;
                    $needsupdate = true;
                }
                if ($reference !== '' && (string)($existing->reference ?? '') !== $reference) {
                    $eventupdate->reference = $reference;
                    $needsupdate = true;
                }
                if ($needsupdate) {
                    $DB->update_record(self::TABLE_EVENTS, self::filter_record_fields(self::TABLE_EVENTS, $eventupdate));
                }

                return (object) [
                    'eventid' => (int)$existing->id,
                    'dedupekey' => $dedupekey,
                    'duplicate' => true,
                    'status' => $existing->status,
                ];
            }
            throw $e;
        }

        return (object) [
            'eventid' => $eventid,
            'dedupekey' => $dedupekey,
            'duplicate' => false,
            'status' => $status,
        ];
    }

    /**
     * Record webhook intake.
     *
     * @param string $gatewayid Gateway ID.
     * @param array $payload Payload.
     * @param string $status Event status.
     * @param string|null $error Processing error.
     * @return \stdClass Webhook event metadata.
     */
    private static function record_webhook_event(
        string $gatewayid,
        array $payload,
        string $status,
        ?string $error = null
    ): stdClass {
        global $DB;

        if (!self::table_exists(self::TABLE_WEBHOOKS)) {
            return (object) [
                'eventid' => 0,
                'dedupekey' => null,
                'duplicate' => false,
                'status' => $status,
            ];
        }

        $rawpayload = self::payload_for_storage($payload);
        $payloadhash = hash('sha256', (string)$rawpayload);
        $gatewayeventid = $payload['id'] ?? ($payload['event_id'] ?? ($payload['data']['id'] ?? null));
        $eventtype = $payload['event'] ?? ($payload['type'] ?? ($payload['event_type'] ?? 'unknown'));
        $reference = $payload['reference'] ?? ($payload['data']['reference'] ?? ($payload['data']['tx_ref'] ?? null));
        $dedupekey = hash('sha256', $gatewayid . '|' . $eventtype . '|' . ($gatewayeventid ?: $payloadhash));

        $existing = $DB->get_record(self::TABLE_WEBHOOKS, ['dedupekey' => $dedupekey], '*', IGNORE_MISSING);
        if ($existing) {
            $internalstatusupdate = ($existing->status ?? '') === 'received'
                && in_array($status, ['processed', 'success', 'failed'], true);
            $existing->attemptcount = ((int)($existing->attemptcount ?? 0)) + ($internalstatusupdate ? 0 : 1);
            $existing->status = self::resolve_webhook_status($existing->status ?? 'received', $status);
            if (in_array($status, ['processed', 'success', 'failed'], true)) {
                $existing->timeprocessed = time();
            }
            if (in_array($status, ['processed', 'success'], true)) {
                $existing->signatureverified = 1;
            }
            if ($error !== null) {
                $existing->lasterror = $error;
            }
            $DB->update_record(self::TABLE_WEBHOOKS, self::filter_record_fields(self::TABLE_WEBHOOKS, $existing));

            return (object) [
                'eventid' => (int)$existing->id,
                'dedupekey' => $dedupekey,
                'duplicate' => true,
                'status' => $existing->status,
            ];
        }

        $record = (object) [
            'gateway' => $gatewayid,
            'dedupekey' => $dedupekey,
            'gatewayeventid' => $gatewayeventid,
            'eventtype' => $eventtype,
            'reference' => $reference,
            'signatureverified' => in_array($status, ['processed', 'success'], true) ? 1 : 0,
            'payloadhash' => $payloadhash,
            'payload' => $rawpayload,
            'status' => $status,
            'attemptcount' => 1,
            'lasterror' => $error,
            'timecreated' => time(),
            'timeprocessed' => in_array($status, ['processed', 'success', 'failed'], true) ? time() : null,
        ];

        $eventid = (int)$DB->insert_record(self::TABLE_WEBHOOKS, self::filter_record_fields(self::TABLE_WEBHOOKS, $record));

        return (object) [
            'eventid' => $eventid,
            'dedupekey' => $dedupekey,
            'duplicate' => false,
            'status' => $status,
        ];
    }

    /**
     * Resolve a payment amount from result payload or order.
     *
     * @param object $order Order record.
     * @param \stdClass $result Payment result.
     * @return float
     */
    private static function resolve_payment_amount(object $order, stdClass $result): float {
        if (isset($result->amount) && is_numeric($result->amount)) {
            return (float)$result->amount;
        }

        return (float)($order->total ?? 0);
    }

    /**
     * Resolve a payment currency from result payload or order.
     *
     * @param object $order Order record.
     * @param \stdClass $result Payment result.
     * @return string|null
     */
    private static function resolve_payment_currency(object $order, stdClass $result): ?string {
        if (!empty($result->currency)) {
            return strtoupper((string)$result->currency);
        }

        return !empty($order->currency) ? strtoupper((string)$order->currency) : null;
    }

    /**
     * Normalize gateway result status into the internal payment vocabulary.
     *
     * @param string $status Gateway status.
     * @return string
     */
    private static function normalize_payment_status(string $status): string {
        $status = strtolower(trim($status));

        if (in_array($status, ['successful', 'succeeded', 'paid', 'complete', 'completed'], true)) {
            return 'success';
        }

        if (in_array($status, ['failure', 'declined', 'denied', 'error'], true)) {
            return 'failed';
        }

        if ($status === 'canceled') {
            return 'cancelled';
        }

        if ($status === '') {
            return 'received';
        }

        return $status;
    }

    /**
     * Resolve the persisted attempt status without downgrading final successful states.
     *
     * @param string|null $current Current persisted status.
     * @param string $incoming Incoming status.
     * @return string
     */
    private static function resolve_attempt_status(?string $current, string $incoming): string {
        $current = $current ? self::normalize_payment_status($current) : null;
        $incoming = self::normalize_payment_status($incoming);

        if ($current === null) {
            return $incoming;
        }

        $rank = [
            'received' => 0,
            'pending' => 1,
            'cancelled' => 2,
            'failed' => 2,
            'success' => 3,
            'refunded' => 4,
        ];

        $currentrank = $rank[$current] ?? 1;
        $incomingrank = $rank[$incoming] ?? 1;

        return $incomingrank >= $currentrank ? $incoming : $current;
    }

    /**
     * Determine whether an attempt status has reached a completed state.
     *
     * @param string $status Payment status.
     * @return bool
     */
    private static function is_completed_payment_status(string $status): bool {
        return in_array(self::normalize_payment_status($status), ['success', 'failed', 'cancelled', 'refunded'], true);
    }

    /**
     * Update operational payment pointers from the payment ledger.
     *
     * @param int $orderid Order ID.
     * @param int $attemptid Attempt ID.
     * @param int $eventid Event ID.
     * @param string $status Payment status.
     */
    private static function update_order_operational_payment_lifecycle(
        int $orderid,
        int $attemptid,
        int $eventid,
        string $status
    ): void {
        global $DB;

        if (!self::table_exists('local_moderncommerce_order_operational')) {
            return;
        }

        $record = $DB->get_record('local_moderncommerce_order_operational', ['orderid' => $orderid], '*', IGNORE_MISSING);
        if (!$record) {
            return;
        }

        if ($attemptid > 0) {
            $record->lastpaymentattemptid = $attemptid;
        }
        if ($eventid > 0) {
            $record->lastgatewayeventid = $eventid;
        }

        if ($status === 'success') {
            $record->paymentstatus = 'paid';
            $record->timepaid = !empty($record->timepaid) ? $record->timepaid : time();
        } else if (in_array($status, ['failed', 'cancelled', 'refunded'], true)) {
            $record->paymentstatus = $status;
            if ($status === 'cancelled') {
                $record->timecancelled = !empty($record->timecancelled) ? $record->timecancelled : time();
            }
        }

        $record->timemodified = time();
        $DB->update_record(
            'local_moderncommerce_order_operational',
            self::filter_record_fields('local_moderncommerce_order_operational', $record)
        );
    }

    /**
     * Resolve webhook intake status without downgrading already processed events.
     *
     * @param string $current Current status.
     * @param string $incoming Incoming status.
     * @return string
     */
    private static function resolve_webhook_status(string $current, string $incoming): string {
        $current = strtolower(trim($current ?: 'received'));
        $incoming = strtolower(trim($incoming ?: 'received'));

        if (in_array($current, ['processed', 'success'], true) && $incoming === 'received') {
            return $current;
        }

        if (in_array($current, ['processed', 'success'], true) && $incoming === 'failed') {
            return $current;
        }

        if ($incoming === 'success') {
            return 'processed';
        }

        return $incoming;
    }

    /**
     * Keep the legacy transaction table updated when it exists.
     *
     * @param object $order Order record.
     * @param string $gatewayid Gateway ID.
     * @param \stdClass $result Payment result.
     */
    private static function record_legacy_transaction(object $order, string $gatewayid, stdClass $result): void {
        global $DB;

        $table = 'local_moderncommerce_transactions';
        if (!self::table_exists($table)) {
            return;
        }

        $reference = $result->gatewayreference ?? ($order->ordernumber ?? '');
        $transaction = $DB->get_record($table, [
            'orderid' => $order->id,
            'gateway' => $gatewayid,
        ], '*', IGNORE_MISSING);

        $record = (object) [
            'orderid' => $order->id,
            'gateway' => $gatewayid,
            'reference' => $reference,
            'transactionid' => $result->gatewaytransactionid ?? $reference,
            'amount' => (float)($order->total ?? 0),
            'currency' => $order->currency ?? '',
            'status' => $result->status === 'success' ? 'success' : ($result->status ?? 'failed'),
            'gatewayresponse' => json_encode($result->rawdata ?? $result),
            'failurereason' => $result->status === 'failed' ? ($result->message ?? '') : '',
            'timemodified' => time(),
        ];

        if ($record->status === 'success') {
            $record->timecompleted = $result->paidat ?? time();
            $record->paidat = $result->paidat ?? time();
        }

        if ($transaction) {
            $record->id = $transaction->id;
            $record->timecreated = $transaction->timecreated;
            $DB->update_record($table, self::filter_record_fields($table, $record));
            return;
        }

        $record->timecreated = time();
        $record->ipaddress = getremoteaddr();
        $DB->insert_record($table, self::filter_record_fields($table, $record));
    }

    /**
     * Decode gateway JSON metadata.
     *
     * @param string|null $configdata JSON metadata.
     * @return array
     */
    private static function decode_configdata(?string $configdata): array {
        if (empty($configdata)) {
            return [];
        }
        $decoded = json_decode($configdata, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Read legacy per-provider settings as a transitional fallback.
     *
     * @param string $gatewayid Gateway ID.
     * @return array
     */
    private static function get_legacy_gateway_config(string $gatewayid): array {
        $gatewayid = self::normalize_gateway_id($gatewayid);

        switch ($gatewayid) {
            case 'paystack':
                return [
                    'enabled' => (bool)get_config('local_moderncommerce', 'paystack_enabled'),
                    'public_key' => get_config('local_moderncommerce', 'paystack_public_key') ?: '',
                    'secret_key' => get_config('local_moderncommerce', 'paystack_secret_key') ?: '',
                    'ip_whitelist' => get_config('local_moderncommerce', 'paystack_ip_whitelist') ?: '',
                ];

            case 'flutterwave':
                return [
                    'enabled' => (bool)get_config('local_moderncommerce', 'flutterwave_enabled'),
                    'public_key' => get_config('local_moderncommerce', 'flutterwave_public_key') ?: '',
                    'secret_key' => get_config('local_moderncommerce', 'flutterwave_secret_key') ?: '',
                    'webhook_secret' => get_config('local_moderncommerce', 'flutterwave_secret_hash') ?: '',
                    'secret_hash' => get_config('local_moderncommerce', 'flutterwave_secret_hash') ?: '',
                ];

            case 'stripe':
                return [
                    'enabled' => (bool)get_config('local_moderncommerce', 'stripe_enabled'),
                    'public_key' => get_config('local_moderncommerce', 'stripe_public_key') ?: '',
                    'secret_key' => get_config('local_moderncommerce', 'stripe_secret_key') ?: '',
                    'webhook_secret' => get_config('local_moderncommerce', 'stripe_webhook_secret') ?: '',
                ];

            case 'paypal':
                return [
                    'enabled' => (bool)get_config('local_moderncommerce', 'paypal_enabled'),
                    'client_id' => get_config('local_moderncommerce', 'paypal_client_id') ?: '',
                    'merchantid' => get_config('local_moderncommerce', 'paypal_client_id') ?: '',
                    'secret_key' => get_config('local_moderncommerce', 'paypal_secret_key') ?: '',
                    'sandbox_mode' => (bool)get_config('local_moderncommerce', 'paypal_sandbox_mode'),
                    'testmode' => (bool)get_config('local_moderncommerce', 'paypal_sandbox_mode'),
                    'webhook_id' => get_config('local_moderncommerce', 'paypal_webhook_id') ?: '',
                    'webhook_secret' => get_config('local_moderncommerce', 'paypal_webhook_id') ?: '',
                ];

            case 'manual':
                $enabled = get_config('local_moderncommerce', 'enable_manual_payment');
                return ['enabled' => $enabled === false ? true : (bool)$enabled];

            case 'enrollkey':
                return ['enabled' => (bool)get_config('local_moderncommerce', 'enable_enrollment_key')];
        }

        return [];
    }

    /**
     * Return the first non-empty value from a list.
     *
     * @param array $values Candidate values.
     * @return string
     */
    private static function first_non_empty(array $values): string {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }
        return '';
    }

    /**
     * Encode a redacted provider payload snapshot for diagnostic storage.
     *
     * @param mixed $payload Provider payload.
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
            'address', 'address1', 'address2', 'address_line_1', 'address_line_2',
            'authorization', 'billing', 'card', 'customer', 'customer_email', 'email',
            'first_name', 'firstname', 'ip', 'ipaddress', 'last_name', 'lastname',
            'metadata', 'name', 'payer', 'phone', 'receipt_email', 'shipping',
            'user_agent', 'user_id', 'useragent', 'userid',
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

    /**
     * Check whether a table exists.
     *
     * @param string $tablename Table name.
     * @return bool
     */
    private static function table_exists(string $tablename): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($tablename));
    }

    /**
     * Remove object properties that are not columns on the target table.
     *
     * @param string $tablename Table name.
     * @param object $record Record object.
     * @return object
     */
    private static function filter_record_fields(string $tablename, object $record): object {
        global $DB;

        $columns = $DB->get_columns($tablename);
        $filtered = new stdClass();

        foreach (get_object_vars($record) as $field => $value) {
            if (isset($columns[$field])) {
                $filtered->{$field} = $value;
            }
        }

        return $filtered;
    }
}
