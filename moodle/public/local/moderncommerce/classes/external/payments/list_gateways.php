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
 * External API listing payment gateways with readiness and recent activity.
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
use local_moderncommerce\localisation;
use local_moderncommerce\payment\gateway_manager;
use local_moderncommerce\services\pricing_service;

/**
 * List payment gateways for the admin gateways screen.
 *
 * Never returns secret values: only boolean "configured" flags are exposed.
 */
class list_gateways extends external_api {
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
        global $DB;

        self::validate_parameters(self::execute_parameters(), []);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:configuregateways', $context);

        $activecurrency = strtoupper((string) pricing_service::get_currency_config()->currency);
        $rows = gateway_manager::get_gateway_admin_rows();

        $gateways = [];
        $hostedready = 0;
        foreach ($rows as $gatewayid => $row) {
            $gatewayid = (string) $gatewayid;
            $readiness = gateway_manager::get_gateway_readiness($gatewayid, null, $row);
            if (!empty($readiness->hosted) && !empty($readiness->ready)) {
                $hostedready++;
            }

            $methodtype = (string) ($row['methodtype'] ?? 'redirect');
            $requiressecret = $methodtype === 'redirect';
            $secretok = !$requiressecret || !empty($row['secretconfigured']);
            $webhookok = empty($row['supportswebhooks']) || !empty($row['webhooksecretconfigured']);

            $gateways[] = [
                'gateway' => $gatewayid,
                'displayname' => (string) ($row['displayname'] ?? $gatewayid),
                'displayorder' => (int) ($row['displayorder'] ?? 0),
                'methodtype' => $methodtype,
                'methodlabel' => self::method_label($methodtype),
                'component' => (string) ($row['component'] ?? 'local_moderncommerce'),
                'classname' => (string) ($row['classname'] ?? ''),
                'icon' => (string) ($row['icon'] ?? 'credit-card-2-front'),
                'enabled' => !empty($row['recordenabled']),
                'testmode' => !empty($row['testmode']),
                'publickey' => (string) ($row['publickey'] ?? ''),
                'merchantid' => (string) ($row['merchantid'] ?? ''),
                'supportedcurrencies' => (string) ($row['supportedcurrencies'] ?? ''),
                'ipwhitelist' => (string) ($row['ip_whitelist'] ?? ''),
                'secretconfigured' => !empty($row['secretconfigured']),
                'webhooksecretconfigured' => !empty($row['webhooksecretconfigured']),
                'supportswebhooks' => !empty($row['supportswebhooks']),
                'supportsrefunds' => !empty($row['supportsrefunds']),
                'supportsrecurring' => !empty($row['supportsrecurring']),
                'secretok' => $secretok,
                'webhookok' => $webhookok,
                'ready' => !empty($readiness->ready),
                'hosted' => !empty($readiness->hosted),
                'currencysupported' => !empty($readiness->currencysupported),
                'readinessmessage' => (string) ($readiness->message ?? ''),
                'supportedcurrencylist' => implode(', ', (array) ($readiness->supportedcurrencies ?? [])),
                'lastpaymentevent' => self::latest_event('local_moderncommerce_payment_events', $gatewayid),
                'lastwebhookevent' => self::latest_event('local_moderncommerce_webhook_events', $gatewayid),
            ];
        }

        return [
            'gateways' => $gateways,
            'activecurrency' => $activecurrency,
            'hostedready' => $hostedready,
            'methodtypes' => [
                ['value' => 'redirect', 'label' => self::method_label('redirect')],
                ['value' => 'offline', 'label' => self::method_label('offline')],
                ['value' => 'key', 'label' => self::method_label('key')],
            ],
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
            'gateways' => new external_multiple_structure(self::gateway_structure()),
            'activecurrency' => new external_value(PARAM_ALPHANUMEXT, 'Active site currency.'),
            'hostedready' => new external_value(PARAM_INT, 'Count of hosted gateways ready for the active currency.'),
            'methodtypes' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_ALPHA, 'Method type value.'),
                'label' => new external_value(PARAM_TEXT, 'Method type label.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Gateway return structure.
     *
     * @return external_single_structure
     */
    private static function gateway_structure(): external_single_structure {
        return new external_single_structure([
            'gateway' => new external_value(PARAM_ALPHANUMEXT, 'Gateway ID.'),
            'displayname' => new external_value(PARAM_TEXT, 'Display name.'),
            'displayorder' => new external_value(PARAM_INT, 'Display order.'),
            'methodtype' => new external_value(PARAM_ALPHA, 'Method type.'),
            'methodlabel' => new external_value(PARAM_TEXT, 'Method type label.'),
            'component' => new external_value(PARAM_COMPONENT, 'Owning component.'),
            'classname' => new external_value(PARAM_RAW, 'Gateway class name.'),
            'icon' => new external_value(PARAM_TEXT, 'Icon class.'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether the gateway is enabled.'),
            'testmode' => new external_value(PARAM_BOOL, 'Whether test mode is on.'),
            'publickey' => new external_value(PARAM_RAW, 'Publishable key.'),
            'merchantid' => new external_value(PARAM_RAW, 'Merchant/client ID.'),
            'supportedcurrencies' => new external_value(PARAM_TEXT, 'Configured supported currencies.'),
            'ipwhitelist' => new external_value(PARAM_RAW, 'Webhook IP whitelist.'),
            'secretconfigured' => new external_value(PARAM_BOOL, 'Whether a secret key is stored.'),
            'webhooksecretconfigured' => new external_value(PARAM_BOOL, 'Whether a webhook secret is stored.'),
            'supportswebhooks' => new external_value(PARAM_BOOL, 'Supports webhooks.'),
            'supportsrefunds' => new external_value(PARAM_BOOL, 'Supports refunds.'),
            'supportsrecurring' => new external_value(PARAM_BOOL, 'Supports recurring.'),
            'secretok' => new external_value(PARAM_BOOL, 'Whether the secret requirement is satisfied.'),
            'webhookok' => new external_value(PARAM_BOOL, 'Whether the webhook secret requirement is satisfied.'),
            'ready' => new external_value(PARAM_BOOL, 'Whether the gateway is checkout-ready.'),
            'hosted' => new external_value(PARAM_BOOL, 'Whether the gateway is hosted/redirect.'),
            'currencysupported' => new external_value(PARAM_BOOL, 'Whether the active currency is supported.'),
            'readinessmessage' => new external_value(PARAM_TEXT, 'Readiness message.'),
            'supportedcurrencylist' => new external_value(PARAM_TEXT, 'Resolved supported currency list.'),
            'lastpaymentevent' => self::event_summary_structure(),
            'lastwebhookevent' => self::event_summary_structure(),
        ]);
    }

    /**
     * Event summary structure.
     *
     * @return external_single_structure
     */
    private static function event_summary_structure(): external_single_structure {
        return new external_single_structure([
            'hasevent' => new external_value(PARAM_BOOL, 'Whether an event exists.'),
            'status' => new external_value(PARAM_TEXT, 'Event status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Status badge class.'),
            'date' => new external_value(PARAM_TEXT, 'Formatted event date.'),
        ]);
    }

    /**
     * Fetch the latest ledger event summary for a gateway.
     *
     * @param string $table Table name.
     * @param string $gatewayid Gateway ID.
     * @return array
     */
    private static function latest_event(string $table, string $gatewayid): array {
        global $DB;

        $empty = ['hasevent' => false, 'status' => '', 'statusclass' => 'neutral', 'date' => ''];
        if (!$DB->get_manager()->table_exists($table)) {
            return $empty;
        }

        $records = $DB->get_records($table, ['gateway' => $gatewayid], 'timecreated DESC, id DESC', '*', 0, 1);
        if (empty($records)) {
            return $empty;
        }

        $event = reset($records);
        $status = (string) ($event->status ?? 'unknown');

        return [
            'hasevent' => true,
            'status' => localisation::status_label($status),
            'statusclass' => self::status_class($status),
            'date' => userdate((int) $event->timecreated, get_string('strftimedatetime', 'langconfig')),
        ];
    }

    /**
     * Badge class for an event status.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_class(string $status): string {
        $status = strtolower($status);
        if (in_array($status, ['success', 'processed', 'completed', 'paid', 'verified'], true)) {
            return 'success';
        }
        if (in_array($status, ['failed', 'error', 'invalid', 'declined'], true)) {
            return 'danger';
        }
        if (in_array($status, ['pending', 'received', 'processing', 'retrying'], true)) {
            return 'warning';
        }

        return 'neutral';
    }

    /**
     * Method type label.
     *
     * @param string $methodtype Method type.
     * @return string
     */
    private static function method_label(string $methodtype): string {
        $known = ['redirect', 'offline', 'key'];
        if (in_array($methodtype, $known, true)) {
            return get_string('gatewaymethod_' . $methodtype, 'local_moderncommerce');
        }

        return ucfirst($methodtype);
    }
}
