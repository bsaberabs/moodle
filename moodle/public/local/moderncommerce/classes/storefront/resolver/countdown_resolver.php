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
 * Resolver for the countdown bar widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use local_moderncommerce\persistent\widget;
use moodle_url;

/**
 * Builds the configuration payload for a countdown bar widget.
 */
class countdown_resolver implements widget_resolver {
    /**
     * Build the countdown payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();

        $ctaurl = trim((string)($settings['ctaurl'] ?? ''));
        if ($ctaurl !== '' && !preg_match('#^https?://#i', $ctaurl) && $ctaurl[0] === '/') {
            $ctaurl = (new moodle_url($ctaurl))->out(false);
        }

        return [
            'heading' => (string)($settings['heading'] ?? ''),
            'endtime' => (int)($settings['endtime'] ?? 0),
            'expiredmessage' => (string)($settings['expiredmessage'] ?? ''),
            'ctalabel' => (string)($settings['ctalabel'] ?? ''),
            'ctaurl' => $ctaurl,
            'bgcolor' => self::colour((string)($settings['bgcolor'] ?? '')),
            'textcolor' => self::colour((string)($settings['textcolor'] ?? '')),
            'headingcolor' => self::colour((string)($settings['headingcolor'] ?? '')),
            'headingfontsize' => self::number($settings['headingfontsize'] ?? 0, 0, 96),
            'timerbgcolor' => self::colour((string)($settings['timerbgcolor'] ?? '')),
            'timernumbercolor' => self::colour((string)($settings['timernumbercolor'] ?? '')),
            'timernumberfontsize' => self::number($settings['timernumberfontsize'] ?? 0, 0, 96),
            'timerlabelcolor' => self::colour((string)($settings['timerlabelcolor'] ?? '')),
            'timerlabelfontsize' => self::number($settings['timerlabelfontsize'] ?? 0, 0, 96),
            'buttoncolor' => self::colour((string)($settings['buttoncolor'] ?? '')),
            'buttontextcolor' => self::colour((string)($settings['buttontextcolor'] ?? '')),
            'expiredbgcolor' => self::colour((string)($settings['expiredbgcolor'] ?? '')),
            'expiredtextcolor' => self::colour((string)($settings['expiredtextcolor'] ?? '')),
            'paddingtop' => max(0, (int)($settings['paddingtop'] ?? 0)),
            'paddingbottom' => max(0, (int)($settings['paddingbottom'] ?? 0)),
            'labels' => [
                'days' => get_string('cd_days', 'local_moderncommerce'),
                'hours' => get_string('cd_hours', 'local_moderncommerce'),
                'minutes' => get_string('cd_minutes', 'local_moderncommerce'),
                'seconds' => get_string('cd_seconds', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Sanitize a CSS colour/token accepted by storefront controls.
     *
     * @param string $value Raw colour.
     * @return string Safe CSS colour token or blank.
     */
    private static function colour(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)) {
            return strtolower($value);
        }
        if (preg_match('/^var\(--mc-[a-z0-9-]+\)$/', $value)) {
            return $value;
        }
        return '';
    }

    /**
     * Bound a numeric control value.
     *
     * @param mixed $value Raw value.
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     * @return int
     */
    private static function number($value, int $min, int $max): int {
        return min($max, max($min, (int) round((float) $value)));
    }
}
