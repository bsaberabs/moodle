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
 * External API listing Modern Commerce email notification types.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\emails;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\email\notification_catalog;

/**
 * List email notification types with enabled state.
 */
class list_emails extends external_api {
    /**
     * The notification type registry.
     *
     * @return array[] Keyed by type with metadata.
     */
    public static function types(): array {
        return notification_catalog::types();
    }

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
        require_capability('local/moderncommerce:viewemailtemplates', $context);

        $items = [];
        foreach (self::types() as $key => $type) {
            $configkey = (string) ($type['configkey'] ?? $key);
            $items[] = [
                'key' => $key,
                'name' => (string) $type['name'],
                'description' => (string) $type['description'],
                'icon' => $type['icon'],
                'color' => $type['color'],
                'groupkey' => (string) ($type['groupkey'] ?? ''),
                'grouplabel' => (string) ($type['grouplabel'] ?? ''),
                'enabled' => notification_catalog::is_enabled($key),
                'timemodified' => self::modified_time($DB, $configkey, (string) ($type['templatekey'] ?? '')),
            ];
        }

        return ['items' => $items, 'warnings' => []];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'Notification type key.'),
                'name' => new external_value(PARAM_TEXT, 'Display name.'),
                'description' => new external_value(PARAM_TEXT, 'Description.'),
                'icon' => new external_value(PARAM_TEXT, 'Bootstrap icon class.'),
                'color' => new external_value(PARAM_ALPHA, 'Accent colour key.'),
                'groupkey' => new external_value(PARAM_ALPHANUMEXT, 'Business group key.'),
                'grouplabel' => new external_value(PARAM_TEXT, 'Business group label.'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the notification is enabled.'),
                'timemodified' => new external_value(PARAM_INT, 'Last modified timestamp.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Resolve the best available last-modified timestamp for one email setting.
     *
     * @param \moodle_database $db Moodle database.
     * @param string $configkey Email config key.
     * @param string $templatekey Bundled template key.
     * @return int Unix timestamp or 0.
     */
    private static function modified_time(\moodle_database $db, string $configkey, string $templatekey): int {
        $saved = (int) get_config('local_moderncommerce', $configkey . '_timemodified');
        if ($saved > 0) {
            return $saved;
        }

        if (!$db->get_manager()->table_exists(new \xmldb_table('local_moderncommerce_emailtpl'))) {
            return 0;
        }

        $templateid = (int) get_config('local_moderncommerce', $configkey . '_template');
        if ($templateid > 0) {
            $modified = $db->get_field('local_moderncommerce_emailtpl', 'timemodified', [
                'id' => $templateid,
                'status' => 'active',
            ]);
            if (!empty($modified)) {
                return (int) $modified;
            }
        }

        if ($templatekey === '') {
            return 0;
        }

        $modified = $db->get_field('local_moderncommerce_emailtpl', 'timemodified', [
            'template_key' => $templatekey,
            'status' => 'active',
        ]);

        return !empty($modified) ? (int) $modified : 0;
    }
}
