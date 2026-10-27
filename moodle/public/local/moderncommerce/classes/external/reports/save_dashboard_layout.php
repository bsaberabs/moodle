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
 * External API saving the global dashboard chart layout.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\reports;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\dashboard_layout_service;
use local_moderncommerce\services\dashboard_panel_service;
use local_moderncommerce\services\dashboard_pref_service;

/**
 * Persist the dashboard layout (chart + panel visibility/order/size + default range).
 *
 * Saves to the current admin's personal layout by default; a site administrator may instead
 * save a shared site default that seeds every admin who has not personalised yet.
 */
class save_dashboard_layout extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Chart id.'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the chart is shown.'),
                'size' => new external_value(PARAM_INT, '12-grid span: 12|6|4|3.'),
                'order' => new external_value(PARAM_INT, 'Display order index.'),
            ]), 'Chart layout rows.', VALUE_DEFAULT, []),
            'panels' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Panel id.'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the panel is shown.'),
                'size' => new external_value(PARAM_INT, '12-grid span: 12|6|4|3.'),
                'order' => new external_value(PARAM_INT, 'Display order index.'),
            ]), 'KPI panel layout rows.', VALUE_DEFAULT, []),
            'range' => new external_value(PARAM_ALPHANUMEXT, 'Preferred default date range.', VALUE_DEFAULT, ''),
            'scope' => new external_value(PARAM_ALPHA, 'Save scope: personal|sitedefault.', VALUE_DEFAULT, 'personal'),
            'reset' => new external_value(PARAM_BOOL, 'Reset to defaults (ignore items).', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Execute.
     *
     * @param array $items Chart layout rows.
     * @param array $panels KPI panel layout rows.
     * @param string $range Preferred default date range.
     * @param string $scope Save scope (personal|sitedefault).
     * @param bool $reset Whether to reset to defaults.
     * @return array
     */
    public static function execute(
        array $items = [],
        array $panels = [],
        string $range = '',
        string $scope = 'personal',
        bool $reset = false
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'items' => $items,
            'panels' => $panels,
            'range' => $range,
            'scope' => $scope,
            'reset' => $reset,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewreports', $context);

        // Only commerce settings managers may write the shared site default; everyone else
        // customises their own dashboard. An unknown scope falls back to the safe personal default.
        $sitescope = $params['scope'] === dashboard_pref_service::SCOPE_SITE;
        if ($sitescope) {
            require_capability('local/moderncommerce:managesettings', $context);
        }
        $scope = $sitescope ? dashboard_pref_service::SCOPE_SITE : dashboard_pref_service::SCOPE_PERSONAL;
        $userid = dashboard_pref_service::uid();

        if ($params['reset']) {
            if ($sitescope) {
                dashboard_pref_service::clear_site();
            } else {
                dashboard_pref_service::clear_personal($userid);
            }
            return [
                'success' => true,
                'message' => get_string('chart_layout_saved', 'local_moderncommerce'),
                'warnings' => [],
            ];
        }

        $result = dashboard_layout_service::save_layout($params['items'], $userid, $scope);
        dashboard_panel_service::save_layout($params['panels'], $userid, $scope);
        if ($params['range'] !== '') {
            dashboard_pref_service::save_range($userid, $params['range'], $scope);
        }

        return [
            'success' => $result['success'],
            'message' => $result['message'],
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
            'success' => new external_value(PARAM_BOOL, 'Whether the layout was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
