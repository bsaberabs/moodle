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
 * Resolver for the partner logos widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\style_controls;

/**
 * Builds the logo band payload for a partner logos widget.
 */
class partners_resolver implements widget_resolver {
    /** @var string[] Recognised layout modes. */
    private const STYLES = ['grid', 'carousel', 'marquee'];

    /**
     * Build the partner logos payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();

        $logos = [];
        foreach (($settings['logos'] ?? []) as $row) {
            $image = style_controls::url($row['image'] ?? '');
            if ($image === '') {
                // A row without an image has nothing to render.
                continue;
            }
            $logos[] = [
                'image' => $image,
                'name' => trim((string) ($row['name'] ?? '')),
                'linkurl' => style_controls::url($row['linkurl'] ?? ''),
            ];
        }

        $style = (string) ($settings['style'] ?? 'grid');
        if (!in_array($style, self::STYLES, true)) {
            $style = 'grid';
        }

        return [
            'logos' => $logos,
            'style' => $style,
            'heading' => trim((string) ($settings['heading'] ?? '')),
            'visiblelogos' => style_controls::number($settings['visiblelogos'] ?? 5, 5, 1, 12),
            'speed' => style_controls::number($settings['speed'] ?? 30, 30, 5, 240),
            'pauseonhover' => !empty($settings['pauseonhover']),
            'greyscale' => !empty($settings['greyscale']),
            'logoheight' => style_controls::number($settings['logoheight'] ?? 48, 48, 16, 200),
            'logogap' => style_controls::number($settings['logogap'] ?? 48, 48, 0, 160),
            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'headingcolor' => style_controls::colour($settings['headingcolor'] ?? ''),
            'headingfontsize' => style_controls::number($settings['headingfontsize'] ?? 28, 28, 0, 96),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
            'labels' => [
                'scrollleft' => get_string('p1_storefront_scrollleft', 'local_moderncommerce'),
                'scrollright' => get_string('p1_storefront_scrollright', 'local_moderncommerce'),
            ],
        ];
    }
}
