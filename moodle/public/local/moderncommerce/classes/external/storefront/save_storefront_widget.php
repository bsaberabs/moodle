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
 * External API saving one widget's settings (and slides for sliders).
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
use local_moderncommerce\persistent\widget;
use local_moderncommerce\persistent\widget_slide;
use local_moderncommerce\storefront\preset_service;

/**
 * Persists a widget's title/subtitle/settings (and reconciles slider slides).
 */
class save_storefront_widget extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Widget id.'),
            'values' => new external_value(PARAM_RAW, 'Settings values as a JSON object string.'),
            'slides' => new external_value(PARAM_RAW, 'Slider slides as a JSON array string.', VALUE_DEFAULT, '[]'),
            'styleconfig' => new external_value(
                PARAM_RAW,
                'Universal widget style config as a JSON object string.',
                VALUE_DEFAULT,
                '{}'
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Widget id.
     * @param string $values JSON object of settings values.
     * @param string $slides JSON array of slides.
     * @return array
     */
    public static function execute(int $id, string $values, string $slides = '[]', string $styleconfig = '{}'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'values' => $values,
            'slides' => $slides,
            'styleconfig' => $styleconfig,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $w = widget::get_record(['id' => $params['id']], MUST_EXIST);

        $decoded = json_decode($params['values'], true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        // Title/subtitle live on the widget row; everything else is the settings JSON.
        $title = (string) ($decoded['title'] ?? '');
        $subtitle = (string) ($decoded['subtitle'] ?? '');
        unset($decoded['title'], $decoded['subtitle']);
        $presetid = (int) ($decoded['presetid'] ?? 0);
        if ($presetid > 0) {
            $preset = $DB->get_record(preset_service::TABLE, ['id' => $presetid]);
            if (!$preset || (string) $preset->type !== (string) $w->get('type')) {
                unset($decoded['presetid']);
            } else {
                $decoded['presetid'] = $presetid;
            }
        } else {
            unset($decoded['presetid']);
        }

        $w->set('title', $title);
        $w->set('subtitle', $subtitle);
        $w->set('settings', json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $w->set('styleconfig', preset_service::encode_object(
            preset_service::sanitize_styleconfig(preset_service::decode_object($params['styleconfig']))
        ));
        $w->update();

        // Reconcile slider slides (replace-all keeps the save path simple and predictable).
        if ((string) $w->get('type') === 'slider') {
            self::save_slides((int) $w->get('id'), $params['slides']);
        }

        return [
            'success' => true,
            'message' => get_string('widget_saved', 'local_moderncommerce'),
            'warnings' => [],
        ];
    }

    /**
     * Replace a slider's slides with the submitted list.
     *
     * @param int $instanceid Slider widget id.
     * @param string $slidesjson JSON array of slide objects.
     */
    private static function save_slides(int $instanceid, string $slidesjson): void {
        $slides = json_decode($slidesjson, true);
        if (!is_array($slides)) {
            $slides = [];
        }

        // Drop existing slides, then recreate from the submitted order.
        foreach (widget_slide::get_records(['instanceid' => $instanceid]) as $existing) {
            $existing->delete();
        }

        $sortorder = 0;
        foreach ($slides as $data) {
            if (!is_array($data)) {
                continue;
            }
            $slide = new widget_slide();
            $slide->set('instanceid', $instanceid);
            $slide->set('sortorder', $sortorder++);
            $slide->set('image', self::slide_image_value($data));
            $slide->set('heading', (string) ($data['heading'] ?? ''));
            $slide->set('subheading', (string) ($data['subheading'] ?? ''));
            $slide->set('ctalabel', (string) ($data['ctalabel'] ?? ''));
            $slide->set('ctaurl', (string) ($data['ctaurl'] ?? ''));
            $slide->set('ctastyle', (string) ($data['ctastyle'] ?? 'primary'));
            $slide->set('bgcolor', (string) ($data['bgcolor'] ?? ''));
            $slide->set('enabled', !empty($data['enabled']) ? 1 : 0);
            $slide->create();
        }
    }

    /**
     * Collapse the editor's source-specific slide image fields into the legacy image column.
     *
     * @param array $data Slide payload.
     * @return string Stored image reference.
     */
    private static function slide_image_value(array $data): string {
        $source = (string) ($data['imagesource'] ?? '');
        if ($source === 'upload') {
            return (string) ($data['imagefile'] ?? $data['image'] ?? '');
        }
        if ($source === 'url') {
            return (string) ($data['imageurl'] ?? $data['image'] ?? '');
        }
        return (string) ($data['image'] ?? $data['imagefile'] ?? $data['imageurl'] ?? '');
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the widget was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
