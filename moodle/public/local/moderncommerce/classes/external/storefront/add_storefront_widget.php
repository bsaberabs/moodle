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
 * External API creating a new storefront widget.
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
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\field_schema;
use local_moderncommerce\storefront\preset_service;
use local_moderncommerce\storefront\widget_types;
use local_moderncommerce\storefront\zones;

/**
 * Creates a storefront widget of a chosen type in a chosen zone, seeded with default settings.
 */
class add_storefront_widget extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Widget type key.'),
            'zone' => new external_value(PARAM_ALPHANUMEXT, 'Zone slug.'),
            'page' => new external_value(PARAM_ALPHANUMEXT, 'Page type.', VALUE_DEFAULT, zones::PAGE_CATALOG),
            'presetid' => new external_value(PARAM_INT, 'Optional style preset id to apply.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $type Widget type.
     * @param string $zone Zone slug.
     * @param string $page Page type.
     * @return array
     */
    public static function execute(
        string $type = '',
        string $zone = '',
        string $page = zones::PAGE_CATALOG,
        int $presetid = 0
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'type' => $type,
            'zone' => $zone,
            'page' => $page,
            'presetid' => $presetid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        if (!zones::is_page($params['page'])) {
            throw new invalid_parameter_exception('Unknown page type.');
        }
        if (!in_array($params['type'], widget_types::all(), true)) {
            throw new invalid_parameter_exception('Unknown widget type.');
        }
        if (!in_array($params['zone'], zones::for_page($params['page']), true)) {
            throw new invalid_parameter_exception('Unknown zone.');
        }

        // Default settings from the type schema (excluding the row-level title/subtitle).
        $settings = [];
        $title = '';
        $subtitle = '';
        foreach (field_schema::for_type($params['type']) as $field) {
            if ($field['name'] === 'title') {
                $title = (string) ($field['default'] ?? '');
                continue;
            }
            if ($field['name'] === 'subtitle') {
                $subtitle = (string) ($field['default'] ?? '');
                continue;
            }
            $settings[$field['name']] = $field['default'] ?? '';
        }

        $styleconfig = [];
        if ((int) $params['presetid'] > 0) {
            $preset = $DB->get_record(preset_service::TABLE, ['id' => (int) $params['presetid']], '*', MUST_EXIST);
            if ((string) $preset->type !== (string) $params['type']) {
                throw new invalid_parameter_exception('Preset does not match widget type.');
            }
            $settings = preset_service::apply_settingspatch(
                $settings,
                preset_service::decode_object((string) $preset->settingspatch)
            );
            $styleconfig = preset_service::sanitize_styleconfig(
                preset_service::decode_object((string) $preset->styleconfig)
            );
            $settings['presetid'] = (int) $params['presetid'];
        }

        $sortorder = widget::count_records(['pagetype' => $params['page'], 'zone' => $params['zone']]);

        $w = new widget();
        $w->set('type', $params['type']);
        $w->set('zone', $params['zone']);
        $w->set('pagetype', $params['page']);
        $w->set('sortorder', $sortorder);
        $w->set('title', $title);
        $w->set('subtitle', $subtitle);
        $w->set('enabled', 1);
        $w->set('settings', json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $w->set('styleconfig', preset_service::encode_object($styleconfig));
        $w->create();

        return [
            'success' => true,
            'id' => (int) $w->get('id'),
            'message' => get_string('widget_added', 'local_moderncommerce'),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the widget was created.'),
            'id' => new external_value(PARAM_INT, 'New widget id.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
