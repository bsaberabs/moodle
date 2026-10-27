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
 * External API saving a payment gateway configuration.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\payments;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\payment\gateway_manager;

/**
 * Save a payment gateway registry row.
 *
 * Secret values are write-only: blank submissions keep the stored secret.
 */
class save_gateway extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'gateway' => new external_value(PARAM_ALPHANUMEXT, 'Gateway ID.'),
            'displayname' => new external_value(PARAM_TEXT, 'Display name.'),
            'displayorder' => new external_value(PARAM_INT, 'Display order.', VALUE_DEFAULT, 0),
            'methodtype' => new external_value(PARAM_ALPHA, 'Method type.', VALUE_DEFAULT, 'redirect'),
            'component' => new external_value(PARAM_COMPONENT, 'Owning component.', VALUE_DEFAULT, 'local_moderncommerce'),
            'classname' => new external_value(PARAM_TEXT, 'Gateway class name.', VALUE_DEFAULT, ''),
            'icon' => new external_value(PARAM_TEXT, 'Icon class.', VALUE_DEFAULT, 'credit-card-2-front'),
            'publickey' => new external_value(PARAM_NOTAGS, 'Publishable key.', VALUE_DEFAULT, ''),
            'merchantid' => new external_value(PARAM_NOTAGS, 'Merchant/client ID.', VALUE_DEFAULT, ''),
            'secretkey' => new external_value(PARAM_NOTAGS, 'Secret key (blank keeps existing).', VALUE_DEFAULT, ''),
            'webhooksecret' => new external_value(PARAM_NOTAGS, 'Webhook secret (blank keeps existing).', VALUE_DEFAULT, ''),
            'supportedcurrencies' => new external_value(PARAM_TEXT, 'Supported currencies.', VALUE_DEFAULT, ''),
            'ipwhitelist' => new external_value(PARAM_TEXT, 'Webhook IP whitelist.', VALUE_DEFAULT, ''),
            'enabled' => new external_value(PARAM_BOOL, 'Whether enabled.', VALUE_DEFAULT, false),
            'testmode' => new external_value(PARAM_BOOL, 'Whether test mode.', VALUE_DEFAULT, false),
            'supportswebhooks' => new external_value(PARAM_BOOL, 'Supports webhooks.', VALUE_DEFAULT, false),
            'supportsrefunds' => new external_value(PARAM_BOOL, 'Supports refunds.', VALUE_DEFAULT, false),
            'supportsrecurring' => new external_value(PARAM_BOOL, 'Supports recurring.', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $gateway Gateway ID.
     * @param string $displayname Display name.
     * @param int $displayorder Display order.
     * @param string $methodtype Method type.
     * @param string $component Component.
     * @param string $classname Class name.
     * @param string $icon Icon.
     * @param string $publickey Publishable key.
     * @param string $merchantid Merchant ID.
     * @param string $secretkey Secret key.
     * @param string $webhooksecret Webhook secret.
     * @param string $supportedcurrencies Supported currencies.
     * @param string $ipwhitelist IP whitelist.
     * @param bool $enabled Enabled.
     * @param bool $testmode Test mode.
     * @param bool $supportswebhooks Supports webhooks.
     * @param bool $supportsrefunds Supports refunds.
     * @param bool $supportsrecurring Supports recurring.
     * @return array
     */
    public static function execute(
        string $gateway,
        string $displayname,
        int $displayorder = 0,
        string $methodtype = 'redirect',
        string $component = 'local_moderncommerce',
        string $classname = '',
        string $icon = 'credit-card-2-front',
        string $publickey = '',
        string $merchantid = '',
        string $secretkey = '',
        string $webhooksecret = '',
        string $supportedcurrencies = '',
        string $ipwhitelist = '',
        bool $enabled = false,
        bool $testmode = false,
        bool $supportswebhooks = false,
        bool $supportsrefunds = false,
        bool $supportsrecurring = false
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'gateway' => $gateway,
            'displayname' => $displayname,
            'displayorder' => $displayorder,
            'methodtype' => $methodtype,
            'component' => $component,
            'classname' => $classname,
            'icon' => $icon,
            'publickey' => $publickey,
            'merchantid' => $merchantid,
            'secretkey' => $secretkey,
            'webhooksecret' => $webhooksecret,
            'supportedcurrencies' => $supportedcurrencies,
            'ipwhitelist' => $ipwhitelist,
            'enabled' => $enabled,
            'testmode' => $testmode,
            'supportswebhooks' => $supportswebhooks,
            'supportsrefunds' => $supportsrefunds,
            'supportsrecurring' => $supportsrecurring,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:configuregateways', $context);

        $gatewayid = gateway_manager::normalize_gateway_id($params['gateway']);
        if ($gatewayid === '') {
            return [
                'success' => false,
                'gateway' => '',
                'message' => get_string('invalidpaymentmethod', 'local_moderncommerce'),
                'warnings' => [],
            ];
        }

        $allowedmethods = ['redirect', 'offline', 'key'];
        $methodtype = in_array($params['methodtype'], $allowedmethods, true) ? $params['methodtype'] : 'redirect';
        $existing = $DB->get_record('local_moderncommerce_gateways', ['gateway' => $gatewayid], '*', IGNORE_MISSING);

        $newdata = [
            'enabled' => $params['enabled'] ? 1 : 0,
            'displayname' => $params['displayname'] !== '' ? $params['displayname'] : ucfirst($gatewayid),
            'displayorder' => $params['displayorder'],
            'component' => $params['component'],
            'classname' => $params['classname'],
            'methodtype' => $methodtype,
            'testmode' => $params['testmode'] ? 1 : 0,
            'publickey' => $params['publickey'],
            'secretkey' => $params['secretkey'],
            'merchantid' => $params['merchantid'],
            'webhooksecret' => $params['webhooksecret'],
            'supportedcurrencies' => $params['supportedcurrencies'],
            'icon' => $params['icon'] !== '' ? $params['icon'] : 'credit-card-2-front',
            'supportsrefunds' => $params['supportsrefunds'] ? 1 : 0,
            'supportswebhooks' => $params['supportswebhooks'] ? 1 : 0,
            'supportsrecurring' => $params['supportsrecurring'] ? 1 : 0,
            'ip_whitelist' => $params['ipwhitelist'],
        ];
        gateway_manager::save_gateway_config($gatewayid, $newdata);
        $saved = $DB->get_record('local_moderncommerce_gateways', ['gateway' => $gatewayid], 'id', IGNORE_MISSING);

        \local_moderncommerce\audit\audit_service::record(
            $existing ? 'payment_gateway_updated' : 'payment_gateway_created',
            'payment_gateway',
            (int) ($saved->id ?? $existing->id ?? 0),
            [
                'olddata' => $existing ?: null,
                'newdata' => $newdata + ['gateway' => $gatewayid],
                'severity' => 'critical',
            ]
        );

        return [
            'success' => true,
            'gateway' => $gatewayid,
            'message' => get_string('gatewaysaved', 'local_moderncommerce'),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the gateway was saved.'),
            'gateway' => new external_value(PARAM_ALPHANUMEXT, 'Saved gateway ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
