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
 * Resolver for public page call-to-action bands.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\style_controls;
use moodle_url;

/**
 * Builds CTA payloads.
 */
class cta_resolver implements widget_resolver {
    /**
     * Build the CTA payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();
        $tone = (string)($settings['tone'] ?? 'primary');

        return [
            'heading' => self::clean((string)($settings['heading'] ?? $instance->get('title')), $ctx),
            'text' => self::clean((string)($settings['text'] ?? $instance->get('subtitle')), $ctx),
            'tone' => in_array($tone, ['primary', 'quiet', 'success'], true) ? $tone : 'primary',
            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'primarybuttoncolor' => style_controls::colour($settings['primarybuttoncolor'] ?? ''),
            'primarybuttontextcolor' => style_controls::colour($settings['primarybuttontextcolor'] ?? ''),
            'secondarybuttoncolor' => style_controls::colour($settings['secondarybuttoncolor'] ?? ''),
            'secondarybuttontextcolor' => style_controls::colour($settings['secondarybuttontextcolor'] ?? ''),
            'buttonradius' => style_controls::number($settings['buttonradius'] ?? 0, 0, 0, 96),
            'cardradius' => style_controls::number($settings['cardradius'] ?? 8, 8, 0, 96),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
            'primary' => [
                'label' => self::clean((string)($settings['primarylabel'] ?? ''), $ctx),
                'url' => self::url((string)($settings['primaryurl'] ?? '')),
            ],
            'secondary' => [
                'label' => self::clean((string)($settings['secondarylabel'] ?? ''), $ctx),
                'url' => self::url((string)($settings['secondaryurl'] ?? '')),
            ],
        ];
    }

    /**
     * Clean display text.
     *
     * @param string $value Raw text.
     * @param \context $ctx Context.
     * @return string
     */
    private static function clean(string $value, \context $ctx): string {
        return format_string(trim($value), true, ['context' => $ctx, 'escape' => false]);
    }

    /**
     * Normalise URLs.
     *
     * @param string $value Raw URL.
     * @return string
     */
    private static function url(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if ($value[0] === '/') {
            return (new moodle_url($value))->out(false);
        }
        return $value;
    }
}
