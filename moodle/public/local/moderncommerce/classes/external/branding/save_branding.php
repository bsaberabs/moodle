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
 * External API saving Modern Commerce branding fields.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\branding;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\branding;

/**
 * Validate and save the Modern Commerce branding fields.
 */
class save_branding extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHANUMEXT, 'Setting key.'),
                    'value' => new external_value(PARAM_RAW, 'Raw value (empty clears the override).'),
                ]),
                'Branding fields to persist.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param array $fields Submitted branding fields.
     * @return array
     */
    public static function execute(array $fields = []): array {
        $params = self::validate_parameters(self::execute_parameters(), ['fields' => $fields]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managesettings', $context);

        $editable = branding::get_editable_fields();

        foreach ($params['fields'] as $field) {
            $key = $field['key'];
            // Ignore any key not in the branding registry.
            if (!isset($editable[$key])) {
                continue;
            }
            $value = branding::sanitize_field($editable[$key], (string) $field['value']);
            set_config($key, $value, 'local_moderncommerce');
        }

        return [
            'success' => true,
            'message' => get_string('settingssaved', 'local_moderncommerce'),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the branding was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
