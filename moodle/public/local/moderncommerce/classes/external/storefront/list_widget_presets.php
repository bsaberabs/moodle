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
 * External API listing style-only widget presets.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\storefront;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\storefront\preset_service;

/**
 * Lists saved widget style presets, optionally filtered by widget type.
 */
class list_widget_presets extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_RAW, 'Optional widget type.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $type Optional widget type.
     * @return array
     */
    public static function execute(string $type = ''): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['type' => $type]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $requestedtype = trim((string) $params['type']);
        $cleantype = $requestedtype !== '' ? preset_service::widget_type($requestedtype) : '';
        if ($requestedtype !== '' && $cleantype === '') {
            $records = [];
        } else {
            $records = $cleantype !== ''
                ? $DB->get_records(preset_service::TABLE, ['type' => $cleantype], 'name ASC, id ASC')
                : $DB->get_records(preset_service::TABLE, null, 'type ASC, name ASC, id ASC');
        }

        return [
            'presets' => array_map([preset_service::class, 'export_record'], array_values($records)),
            'warnings' => [],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'presets' => new external_multiple_structure(self::preset_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Preset return structure.
     *
     * @return external_single_structure
     */
    private static function preset_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Preset id.'),
            'type' => new external_value(PARAM_RAW, 'Widget type.'),
            'name' => new external_value(PARAM_RAW, 'Preset name.'),
            'styleconfig' => new external_value(PARAM_RAW, 'Universal style config JSON.'),
            'settingspatch' => new external_value(PARAM_RAW, 'Type-specific visual settings patch JSON.'),
            'timemodified' => new external_value(PARAM_INT, 'Last modified time.'),
        ]);
    }
}
