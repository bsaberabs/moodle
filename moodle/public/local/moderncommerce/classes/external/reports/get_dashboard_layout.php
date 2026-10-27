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
 * External API returning the editable dashboard chart layout (manager drawer).
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
 * Return the chart + panel catalog merged with the current admin's saved layout.
 */
class get_dashboard_layout extends external_api {
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
        require_capability('local/moderncommerce:viewreports', $context);

        return [
            'charts' => dashboard_layout_service::get_layout(),
            'panels' => dashboard_panel_service::get_layout(),
            'sizeoptions' => dashboard_layout_service::size_options(),
            'range' => dashboard_pref_service::resolve_range(dashboard_pref_service::uid()),
            'ranges' => dashboard_pref_service::RANGES,
            'cansavedefault' => has_capability('local/moderncommerce:managesettings', $context),
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
            'charts' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Chart id.'),
                'title' => new external_value(PARAM_TEXT, 'Chart title.'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the chart is shown.'),
                'size' => new external_value(PARAM_INT, '12-grid span: 12|6|4|3.'),
                'order' => new external_value(PARAM_INT, 'Display order index.'),
            ])),
            'panels' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Panel id.'),
                'title' => new external_value(PARAM_TEXT, 'Panel title.'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the panel is shown.'),
                'size' => new external_value(PARAM_INT, '12-grid span: 12|6|4|3.'),
                'order' => new external_value(PARAM_INT, 'Display order index.'),
            ])),
            'sizeoptions' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_INT, 'Span value.'),
                'label' => new external_value(PARAM_TEXT, 'Span label.'),
            ])),
            'range' => new external_value(PARAM_ALPHANUMEXT, 'Resolved preferred default date range.'),
            'ranges' => new external_multiple_structure(new external_value(PARAM_ALPHANUMEXT, 'Allowed range key.')),
            'cansavedefault' => new external_value(PARAM_BOOL, 'Whether this admin can save a shared site default.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
