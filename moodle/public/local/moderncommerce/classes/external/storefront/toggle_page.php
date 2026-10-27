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
 * External API toggling a Modern Commerce public page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\storefront;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use invalid_parameter_exception;
use local_moderncommerce\output\public_page;
use local_moderncommerce\persistent\storepage;
use local_moderncommerce\storefront\storepage_service;

/**
 * Toggles whether an optional public store page is visible to buyers.
 */
class toggle_page extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page' => new external_value(PARAM_ALPHANUMEXT, 'Public page key.'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether the page should be enabled.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $page Page key.
     * @param bool $enabled Desired enabled state.
     * @return array
     */
    public static function execute(string $page, bool $enabled): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'page' => $page,
            'enabled' => $enabled,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        if (!public_page::can_disable($params['page'])) {
            throw new invalid_parameter_exception('This page cannot be disabled.');
        }

        // Custom pages own their published state in the storepage table; the built-ins
        // keep theirs in plugin config.
        $custom = storepage_service::by_slug($params['page']);
        if ($custom !== null) {
            $page = new storepage((int) $custom->id);
            $page->set('enabled', !empty($params['enabled']) ? 1 : 0);
            $page->update();
            storepage_service::purge_cache();
        } else {
            set_config(
                public_page::config_name($params['page'], 'enabled'),
                !empty($params['enabled']) ? '1' : '0',
                'local_moderncommerce'
            );
        }

        return [
            'success' => true,
            'message' => get_string(!empty($params['enabled']) ? 'pageenabled' : 'pagedisabled', 'local_moderncommerce'),
            'page' => [
                'key' => $params['page'],
                'enabled' => !empty($params['enabled']),
            ],
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
            'success' => new external_value(PARAM_BOOL, 'Whether the page was toggled.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'page' => new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'Public page key.'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the page is enabled.'),
            ]),
            'warnings' => new external_warnings(),
        ]);
    }
}
