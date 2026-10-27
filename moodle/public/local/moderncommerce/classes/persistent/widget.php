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
 * Persistent for a placed storefront widget instance.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\persistent;

use core\persistent;
use local_moderncommerce\storefront\preset_service;

/**
 * Represents one widget placed into a page zone.
 */
class widget extends persistent {
    /** @var string Table name. */
    const TABLE = 'local_moderncommerce_widget';

    /** @var object|null|false Cached preset record; false until first lookup. */
    private $presetrecord = false;

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'type' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => 'slider',
            ],
            'zone' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'pagetype' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => 'catalog',
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'title' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'subtitle' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'enabled' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
            'audience' => [
                'type' => PARAM_ALPHA,
                'default' => 'all',
                'choices' => ['all', 'guest', 'loggedin'],
            ],
            'settings' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'styleconfig' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'timestart' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'timeend' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Whether this instance is live for the given moment and visitor.
     *
     * @param int $now Unix timestamp.
     * @param bool $isloggedin Whether the visitor is an authenticated, non-guest user.
     * @return bool
     */
    public function is_active(int $now, bool $isloggedin): bool {
        if (!$this->get('enabled')) {
            return false;
        }

        $start = (int)$this->get('timestart');
        $end = (int)$this->get('timeend');
        if ($start > 0 && $now < $start) {
            return false;
        }
        if ($end > 0 && $now > $end) {
            return false;
        }

        $audience = $this->get('audience');
        if ($audience === 'guest' && $isloggedin) {
            return false;
        }
        if ($audience === 'loggedin' && !$isloggedin) {
            return false;
        }

        return true;
    }

    /**
     * Decode the settings JSON into an array.
     *
     * @return array
     */
    public function get_settings_array(): array {
        $raw = (string)$this->get('settings');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Decode the style config JSON into an array.
     *
     * @return array
     */
    public function get_style_array(): array {
        $raw = (string)$this->get('styleconfig');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Settings as rendered: preset defaults underneath the widget's own settings.
     *
     * Applying a preset copies its visual values into the widget. The preset patch is kept
     * as a fallback for older/incomplete records, while explicit widget values must win so
     * controls such as "Show hero section" continue to work after save and reload.
     *
     * @return array
     */
    public function get_resolved_settings_array(): array {
        $settings = $this->get_settings_array();
        $patch = $this->get_preset_settingspatch();

        return $patch === [] ? $settings : array_merge($patch, $settings);
    }

    /**
     * Universal style config as rendered, with the linked preset applied underneath.
     *
     * @return array
     */
    public function get_resolved_style_array(): array {
        $style = $this->get_style_array();
        $preset = $this->get_preset_record();
        if (!$preset) {
            return $style;
        }
        $patch = preset_service::sanitize_styleconfig(
            preset_service::decode_object((string)($preset->styleconfig ?? ''))
        );

        return $patch === [] ? $style : array_merge($patch, $style);
    }

    /**
     * The style-only settings patch from this widget's linked preset, if any.
     *
     * @return array
     */
    private function get_preset_settingspatch(): array {
        $preset = $this->get_preset_record();
        if (!$preset) {
            return [];
        }

        return preset_service::sanitize_settingspatch(
            preset_service::decode_object((string)($preset->settingspatch ?? ''))
        );
    }

    /**
     * Load the preset referenced by this widget's settings, if it is still valid.
     *
     * @return object|null Preset record, or null when unset, missing, or type-mismatched.
     */
    private function get_preset_record(): ?object {
        global $DB;

        if ($this->presetrecord !== false) {
            return $this->presetrecord;
        }

        $this->presetrecord = null;
        $presetid = (int)($this->get_settings_array()['presetid'] ?? 0);
        if ($presetid > 0) {
            $preset = $DB->get_record(preset_service::TABLE, ['id' => $presetid]);
            // A preset only applies to its own widget type; a mismatch means stale data.
            if ($preset && (string)$preset->type === (string)$this->get('type')) {
                $this->presetrecord = $preset;
            }
        }

        return $this->presetrecord;
    }
}
