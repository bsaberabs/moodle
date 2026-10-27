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
 * External API returning Modern Commerce branding fields.
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
 * Get the Modern Commerce branding fields, grouped, with current and default values.
 */
class get_branding extends external_api {
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
        require_capability('local/moderncommerce:managesettings', $context);

        $defaults = branding::get_defaults();
        $fields = [];

        foreach (branding::get_groups() as $groupkey => $tokens) {
            $grouplabel = get_string('brandgroup_' . $groupkey, 'local_moderncommerce');
            foreach ($tokens as $key => $token) {
                $fields[] = [
                    'key' => $key,
                    'group' => $groupkey,
                    'grouplabel' => $grouplabel,
                    'label' => get_string($key, 'local_moderncommerce'),
                    'type' => $token['type'],
                    'var' => $token['var'],
                    'value' => (string) get_config('local_moderncommerce', $key),
                    'default' => (string) ($defaults[$key] ?? ''),
                    'derived' => branding::get_derived($key),
                ];
            }
        }

        // Non-colour fields rendered after the colour groups.
        $fields[] = [
            'key' => 'brand_radius',
            'group' => 'shape',
            'grouplabel' => get_string('brandgroup_shape', 'local_moderncommerce'),
            'label' => get_string('brand_radius', 'local_moderncommerce'),
            'type' => 'length',
            'var' => '--mc-radius',
            'value' => (string) get_config('local_moderncommerce', 'brand_radius'),
            'default' => (string) ($defaults['brand_radius'] ?? ''),
            'derived' => [],
        ];
        $fields[] = [
            'key' => 'customcss',
            'group' => 'advanced',
            'grouplabel' => get_string('brandgroup_advanced', 'local_moderncommerce'),
            'label' => get_string('customcss', 'local_moderncommerce'),
            'type' => 'css',
            'var' => '',
            'value' => (string) get_config('local_moderncommerce', 'customcss'),
            'default' => '',
            'derived' => [],
        ];

        return [
            'fields' => $fields,
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
            'fields' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'Setting key.'),
                'group' => new external_value(PARAM_ALPHANUMEXT, 'Group key.'),
                'grouplabel' => new external_value(PARAM_TEXT, 'Group label.'),
                'label' => new external_value(PARAM_TEXT, 'Field label.'),
                'type' => new external_value(PARAM_ALPHA, 'Field type: colour, text, length or css.'),
                'var' => new external_value(PARAM_RAW, 'The --mc-* custom property, or empty for non-CSS-variable fields.'),
                'value' => new external_value(PARAM_RAW, 'Current stored value (empty = inherit default).'),
                'default' => new external_value(PARAM_RAW, 'Design-system default value.'),
                'derived' => new external_multiple_structure(new external_single_structure([
                    'var' => new external_value(PARAM_RAW, 'Derived --mc-* custom property.'),
                    'expr' => new external_value(PARAM_RAW, 'color-mix() expression computed from the seed.'),
                ])),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }
}
