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
 * External API returning one Modern Commerce email notification's settings.
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
 * Get one email notification type's editable settings.
 */
class get_email extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Notification type key.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $type Notification type key.
     * @return array
     */
    public static function execute(string $type): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['type' => $type]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewemailtemplates', $context);

        $types = list_emails::types();
        if (!isset($types[$params['type']])) {
            throw new \moodle_exception('invalidemail', 'local_moderncommerce');
        }
        $type = $params['type'];
        $meta = $types[$type];
        $configkey = (string) ($meta['configkey'] ?? $type);

        $enabled = notification_catalog::is_enabled($type);
        $subject = get_config('local_moderncommerce', $configkey . '_subject');
        $body = get_config('local_moderncommerce', $configkey . '_body');
        $templateid = (int) get_config('local_moderncommerce', $configkey . '_template');
        $template = null;

        if ($DB->get_manager()->table_exists(new \xmldb_table('local_moderncommerce_emailtpl'))) {
            if ($templateid > 0) {
                $template = $DB->get_record('local_moderncommerce_emailtpl', [
                    'id' => $templateid,
                    'status' => 'active',
                ]);
            }

            if (!$template && !empty($meta['templatekey'])) {
                $template = $DB->get_record('local_moderncommerce_emailtpl', [
                    'template_key' => (string) $meta['templatekey'],
                    'status' => 'active',
                ]);
                if ($template) {
                    $templateid = (int) $template->id;
                }
            }
        }

        if ($subject === false || $subject === '') {
            $subject = $template ? (string) ($template->subject ?? '') : (string) ($meta['subjectdefault'] ?? '');
        }
        if ($body === false || $body === '') {
            $body = $template ? (string) ($template->body ?? '') : (string) ($meta['bodydefault'] ?? '');
        }

        $placeholders = array_values(array_filter(array_map('trim', explode(',', $meta['placeholders']))));

        return [
            'type' => $type,
            'name' => (string) $meta['name'],
            'enabled' => $enabled,
            'subject' => (string) $subject,
            'body' => (string) $body,
            'templateid' => $templateid,
            'placeholders' => $placeholders,
            'templateoptions' => self::get_template_options($CFG),
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
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Notification type key.'),
            'name' => new external_value(PARAM_TEXT, 'Display name.'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether enabled.'),
            'subject' => new external_value(PARAM_TEXT, 'Subject line.'),
            'body' => new external_value(PARAM_RAW, 'HTML body.'),
            'templateid' => new external_value(PARAM_INT, 'Selected wrapper template ID.'),
            'placeholders' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Placeholder token.')),
            'templateoptions' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Template ID.'),
                'name' => new external_value(PARAM_TEXT, 'Template name.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Available wrapper template options.
     *
     * @param object $cfg Global config.
     * @return array
     */
    private static function get_template_options(object $cfg): array {
        require_once($cfg->dirroot . '/local/moderncommerce/lib.php');

        $options = [];
        if (function_exists('local_moderncommerce_get_available_templates')) {
            foreach (local_moderncommerce_get_available_templates() as $id => $name) {
                if ((int) $id <= 0) {
                    continue;
                }
                $options[] = ['id' => (int) $id, 'name' => (string) $name];
            }
        }

        return $options;
    }
}
