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
 * External API saving one Modern Commerce email notification's settings.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\emails;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Save one email notification type's settings.
 */
class save_email extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Notification type key.'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether enabled.', VALUE_DEFAULT, false),
            'subject' => new external_value(PARAM_TEXT, 'Subject line.', VALUE_DEFAULT, ''),
            'body' => new external_value(PARAM_RAW, 'HTML body.', VALUE_DEFAULT, ''),
            'templateid' => new external_value(PARAM_INT, 'Wrapper template ID.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $type Notification type key.
     * @param bool $enabled Enabled flag.
     * @param string $subject Subject.
     * @param string $body Body.
     * @param int $templateid Template ID.
     * @return array
     */
    public static function execute(
        string $type,
        bool $enabled = false,
        string $subject = '',
        string $body = '',
        int $templateid = 0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'type' => $type,
            'enabled' => $enabled,
            'subject' => $subject,
            'body' => $body,
            'templateid' => $templateid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:manageemailtemplates', $context);

        $types = list_emails::types();
        if (!isset($types[$params['type']])) {
            throw new \moodle_exception('invalidemail', 'local_moderncommerce');
        }
        $type = $params['type'];
        $configkey = (string) ($types[$type]['configkey'] ?? $type);

        set_config($configkey . '_enabled', $params['enabled'] ? 1 : 0, 'local_moderncommerce');
        set_config($configkey . '_template', $params['templateid'], 'local_moderncommerce');
        set_config($configkey . '_subject', $params['subject'], 'local_moderncommerce');
        set_config($configkey . '_body', $params['body'], 'local_moderncommerce');
        set_config($configkey . '_timemodified', time(), 'local_moderncommerce');

        return [
            'success' => true,
            'message' => get_string('changessaved'),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the settings were saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
