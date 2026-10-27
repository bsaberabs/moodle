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
 * Resolver for the feature grid widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\style_controls;

/**
 * Builds a responsive grid of icon feature cards.
 *
 * Cards carry their own icon and accent colour so a site can run the
 * multi-colour treatment (one hue per card) without extra markup. The grid is a
 * plain Bootstrap row/col: the admin picks 3 or 4 per row on desktop and the
 * layout collapses to 2-up on tablet and 1-up on phones.
 */
class featuregrid_resolver implements widget_resolver {
    /** @var int Most cards an instance may render. */
    private const MAX_CARDS = 12;

    /** @var string[] Supported card treatments. */
    private const STYLES = ['topbar', 'plain', 'tile', 'bordered'];

    /**
     * Build the feature grid payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();

        $style = (string) ($settings['style'] ?? 'topbar');
        if (!in_array($style, self::STYLES, true)) {
            $style = 'topbar';
        }

        // Desktop columns. Bootstrap's grid is twelve-wide, so 3 and 4 per row
        // divide evenly; anything else would leave a ragged final row.
        $columns = (int) ($settings['columns'] ?? 3);
        if (!in_array($columns, [3, 4], true)) {
            $columns = 3;
        }

        $cards = [];
        foreach (($settings['cards'] ?? []) as $card) {
            if (!is_array($card)) {
                continue;
            }
            $title = self::clean((string) ($card['title'] ?? ''), $ctx);
            $text = self::clean((string) ($card['text'] ?? ''), $ctx);
            // A card with no words is just a floating icon - skip it.
            if ($title === '' && $text === '') {
                continue;
            }

            $cards[] = [
                'icon' => style_controls::icon($card['icon'] ?? '', 'star'),
                'title' => $title,
                'text' => $text,
                // Blank falls back to the widget-level colour, then the brand palette.
                'iconcolor' => style_controls::colour($card['iconcolor'] ?? ''),
                'accentcolor' => style_controls::colour($card['accentcolor'] ?? ''),
                'linklabel' => self::clean((string) ($card['linklabel'] ?? ''), $ctx),
                'linkurl' => style_controls::url($card['linkurl'] ?? ''),
            ];

            if (count($cards) >= self::MAX_CARDS) {
                break;
            }
        }

        return [
            'style' => $style,
            'columns' => $columns,
            'cards' => $cards,

            'title' => self::clean((string) $instance->get('title'), $ctx),
            'subtitle' => self::clean((string) $instance->get('subtitle'), $ctx),
            'showheader' => self::truthy($settings['showheader'] ?? true),
            'centercards' => self::truthy($settings['centercards'] ?? true),

            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'subtitlecolor' => style_controls::colour($settings['subtitlecolor'] ?? ''),
            'subtitlefontsize' => style_controls::number($settings['subtitlefontsize'] ?? 0, 0, 0, 96),
            'cardbgcolor' => style_controls::colour($settings['cardbgcolor'] ?? ''),
            'cardbordercolor' => style_controls::colour($settings['cardbordercolor'] ?? ''),
            'cardborderwidth' => style_controls::number($settings['cardborderwidth'] ?? 1, 1, 0, 24),
            'cardradius' => style_controls::number($settings['cardradius'] ?? 12, 12, 0, 96),
            'iconcolor' => style_controls::colour($settings['iconcolor'] ?? ''),
            'iconsize' => style_controls::number($settings['iconsize'] ?? 40, 40, 12, 96),
            'iconbgcolor' => style_controls::colour($settings['iconbgcolor'] ?? ''),
            'cardtitlecolor' => style_controls::colour($settings['cardtitlecolor'] ?? ''),
            'cardtitlefontsize' => style_controls::number($settings['cardtitlefontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'linkcolor' => style_controls::colour($settings['linkcolor'] ?? ''),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 72, 72),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 72, 72),
        ];
    }

    /**
     * Normalise a stored toggle to a boolean.
     *
     * Settings arrive from the editor as strings, so a plain cast is not enough:
     * the string '0' is truthy in PHP.
     *
     * @param mixed $value Raw setting value.
     * @return bool
     */
    private static function truthy($value): bool {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return $value !== '' && $value !== '0' && strtolower($value) !== 'false';
        }

        return !empty($value);
    }

    /**
     * Clean a display string.
     *
     * @param string $value Raw text.
     * @param \context $ctx Context.
     * @return string
     */
    private static function clean(string $value, \context $ctx): string {
        return format_string(trim($value), true, ['context' => $ctx, 'escape' => false]);
    }
}
