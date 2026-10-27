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
 * External API saving a style-only widget preset.
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
use local_moderncommerce\storefront\preset_service;

/**
 * Creates or updates a saved style preset for one widget type.
 */
class save_widget_preset extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Preset id, or 0 to create.', VALUE_DEFAULT, 0),
            'type' => new external_value(PARAM_RAW, 'Widget type.'),
            'name' => new external_value(PARAM_RAW, 'Preset name.'),
            'styleconfig' => new external_value(PARAM_RAW, 'Universal style config JSON object.', VALUE_DEFAULT, '{}'),
            'settingspatch' => new external_value(
                PARAM_RAW,
                'Type-specific visual settings patch JSON object.',
                VALUE_DEFAULT,
                '{}'
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Preset id.
     * @param string $type Widget type.
     * @param string $name Preset name.
     * @param string $styleconfig Style config JSON.
     * @param string $settingspatch Settings patch JSON.
     * @return array
     */
    public static function execute(
        int $id = 0,
        string $type = '',
        string $name = '',
        string $styleconfig = '{}',
        string $settingspatch = '{}'
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'type' => $type,
            'name' => $name,
            'styleconfig' => $styleconfig,
            'settingspatch' => $settingspatch,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $cleantype = preset_service::widget_type($params['type']);
        if ($cleantype === '') {
            throw new \moodle_exception('widgetpreset_invalidtype', 'local_moderncommerce');
        }
        $cleanname = preset_service::preset_name($params['name']);
        if ($cleanname === '') {
            throw new \moodle_exception('widgetpreset_requiredname', 'local_moderncommerce');
        }

        $style = preset_service::sanitize_styleconfig(preset_service::decode_object($params['styleconfig']));
        $patch = preset_service::sanitize_settingspatch(preset_service::decode_object($params['settingspatch']));

        $existingid = (int) $DB->get_field(preset_service::TABLE, 'id', [
            'type' => $cleantype,
            'name' => $cleanname,
        ]);
        if ($existingid > 0 && $existingid !== (int) $params['id']) {
            throw new \moodle_exception('widgetpreset_duplicate', 'local_moderncommerce');
        }

        $now = time();
        $record = (object) [
            'type' => $cleantype,
            'name' => $cleanname,
            'styleconfig' => preset_service::encode_object($style),
            'settingspatch' => preset_service::encode_object($patch),
            'usermodified' => (int) $USER->id,
            'timemodified' => $now,
        ];

        if ((int) $params['id'] > 0) {
            $current = $DB->get_record(preset_service::TABLE, ['id' => (int) $params['id']], '*', MUST_EXIST);
            if ((string) $current->type !== $cleantype) {
                throw new \moodle_exception('widgetpreset_typemismatch', 'local_moderncommerce');
            }
            $record->id = (int) $current->id;
            $record->timecreated = (int) $current->timecreated;
            $DB->update_record(preset_service::TABLE, $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record(preset_service::TABLE, $record);
        }

        $saved = $DB->get_record(preset_service::TABLE, ['id' => (int) $record->id], '*', MUST_EXIST);

        return [
            'success' => true,
            'message' => get_string('widgetpreset_saved', 'local_moderncommerce'),
            'preset' => preset_service::export_record($saved),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the preset was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'preset' => self::preset_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Preset structure.
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
