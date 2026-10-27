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

/**
 * External API returning webhook configuration per gateway.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\payments;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\payment\gateway_manager;

/**
 * Build webhook setup data for each hosted gateway.
 */
class get_webhooks extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute.
     *
     * @return array
     */
    public static function execute(): array {
        self::validate_parameters(self::execute_parameters(), []);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:configuregateways', $context);

        $definitions = [
            'stripe' => [
                'name' => 'Stripe',
                'events' => [
                    'charge.succeeded', 'charge.failed', 'charge.refunded', 'invoice.paid',
                    'invoice.payment_failed', 'customer.subscription.deleted', 'customer.subscription.updated',
                ],
                'secret' => static function (array $c): bool {
                    return !empty($c['webhook_secret']);
                },
                'testmode' => static function (array $c): bool {
                    return !empty($c['testmode']) || strpos($c['secret_key'] ?? '', 'sk_test_') === 0;
                },
            ],
            'paypal' => [
                'name' => 'PayPal',
                'events' => [
                    'CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED', 'BILLING.SUBSCRIPTION.*', 'PAYMENT.SALE.*',
                ],
                'secret' => static function (array $c): bool {
                    return !empty($c['webhook_id']) || !empty($c['webhook_secret']);
                },
                'testmode' => static function (array $c): bool {
                    return !empty($c['sandbox_mode']);
                },
            ],
            'paystack' => [
                'name' => 'Paystack',
                'events' => [
                    'charge.success', 'charge.failed', 'subscription.create', 'subscription.disable', 'invoice.payment_failed',
                ],
                'secret' => static function (array $c): bool {
                    return !empty($c['secret_key']);
                },
                'testmode' => static function (array $c): bool {
                    return !empty($c['testmode']) || strpos($c['secret_key'] ?? '', 'sk_test_') === 0;
                },
            ],
            'flutterwave' => [
                'name' => 'Flutterwave',
                'events' => ['charge.completed', 'charge.failed', 'subscription.cancelled'],
                'secret' => static function (array $c): bool {
                    return !empty($c['secret_hash']) || !empty($c['webhook_secret']);
                },
                'testmode' => static function (array $c): bool {
                    return !empty($c['testmode']);
                },
            ],
        ];

        $ipwhitelistenabled = !empty(get_config('local_moderncommerce', 'enable_webhook_ip_whitelist'));

        $gateways = [];
        $hasunconfigured = false;
        foreach ($definitions as $gatewayid => $def) {
            $config = gateway_manager::get_gateway_config($gatewayid);
            $secretconfigured = (bool) $def['secret']($config);
            $enabled = !empty($config['enabled']);
            if ($enabled && !$secretconfigured) {
                $hasunconfigured = true;
            }

            $gateways[] = [
                'gateway' => $gatewayid,
                'name' => $def['name'],
                'webhookurl' => gateway_manager::webhook_url($gatewayid)->out(false),
                'enabled' => $enabled,
                'secretconfigured' => $secretconfigured,
                'testmode' => (bool) $def['testmode']($config),
                'ipwhitelist' => $gatewayid === 'paystack' && $ipwhitelistenabled && !empty($config['ip_whitelist']),
                'events' => array_values($def['events']),
            ];
        }

        return [
            'gateways' => $gateways,
            'hasunconfigured' => $hasunconfigured,
            'settingsurl' => (new \moodle_url('/local/moderncommerce/admin/settings.php'))->out(false),
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'gateways' => new external_multiple_structure(new external_single_structure([
                'gateway' => new external_value(PARAM_ALPHANUMEXT, 'Gateway ID.'),
                'name' => new external_value(PARAM_TEXT, 'Gateway name.'),
                'webhookurl' => new external_value(PARAM_URL, 'Webhook endpoint URL.'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the gateway is enabled.'),
                'secretconfigured' => new external_value(PARAM_BOOL, 'Whether a webhook secret is stored.'),
                'testmode' => new external_value(PARAM_BOOL, 'Whether the gateway is in test mode.'),
                'ipwhitelist' => new external_value(PARAM_BOOL, 'Whether IP whitelisting is active.'),
                'events' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Webhook event name.')),
            ])),
            'hasunconfigured' => new external_value(PARAM_BOOL, 'Whether an enabled gateway is missing its secret.'),
            'settingsurl' => new external_value(PARAM_URL, 'Commerce settings URL.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
