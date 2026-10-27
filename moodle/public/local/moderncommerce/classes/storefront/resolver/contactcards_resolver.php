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
 * Resolver for public contact/help cards.
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
 * Builds contact card payloads.
 */
class contactcards_resolver implements widget_resolver {
    /**
     * Build the contact cards payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();
        $cards = [];

        foreach (($settings['cards'] ?? []) as $card) {
            if (!is_array($card)) {
                continue;
            }
            $title = self::clean((string)($card['title'] ?? ''), $ctx);
            $text = self::clean((string)($card['text'] ?? ''), $ctx);
            if ($title === '' && $text === '') {
                continue;
            }
            $cards[] = [
                'icon' => self::icon((string)($card['icon'] ?? 'life-preserver')),
                'title' => $title,
                'text' => $text,
                'linklabel' => self::clean((string)($card['linklabel'] ?? ''), $ctx),
                'linkurl' => self::url((string)($card['linkurl'] ?? '')),
            ];
        }

        return [
            'title' => self::clean((string)$instance->get('title'), $ctx),
            'subtitle' => self::clean((string)$instance->get('subtitle'), $ctx),
            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'subtitlecolor' => style_controls::colour($settings['subtitlecolor'] ?? ''),
            'subtitlefontsize' => style_controls::number($settings['subtitlefontsize'] ?? 0, 0, 0, 96),
            'cardbgcolor' => style_controls::colour($settings['cardbgcolor'] ?? ''),
            'cardbordercolor' => style_controls::colour($settings['cardbordercolor'] ?? ''),
            'cardborderwidth' => style_controls::number($settings['cardborderwidth'] ?? 1, 1, 0, 24),
            'cardradius' => style_controls::number($settings['cardradius'] ?? 8, 8, 0, 96),
            'iconbgcolor' => style_controls::colour($settings['iconbgcolor'] ?? ''),
            'iconcolor' => style_controls::colour($settings['iconcolor'] ?? ''),
            'iconsize' => style_controls::number($settings['iconsize'] ?? 0, 0, 0, 96),
            'labelcolor' => style_controls::colour($settings['labelcolor'] ?? ''),
            'labelfontsize' => style_controls::number($settings['labelfontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'linkcolor' => style_controls::colour($settings['linkcolor'] ?? ''),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
            'cards' => $cards,
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
     * Normalise a Bootstrap icon value.
     *
     * @param string $value Raw icon.
     * @return string
     */
    private static function icon(string $value): string {
        $icon = preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', trim($value)));
        return $icon !== '' ? $icon : 'life-preserver';
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
