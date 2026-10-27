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
 * Resolver for the trust badges strip widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use local_moderncommerce\branding;
use local_moderncommerce\persistent\widget;

/**
 * Builds the badge list payload for a trust badges widget.
 */
class trustbadges_resolver implements widget_resolver {
    /**
     * Build the badges payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $badges = [];

        foreach (($settings['badges'] ?? []) as $badge) {
            $label = trim((string)($badge['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            // Normalise to a bare icon name (no "bi-" / "bi " prefix); React adds "bi bi-".
            $icon = (string) preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', trim((string)($badge['icon'] ?? ''))));
            $badges[] = [
                'icon' => $icon !== '' ? $icon : 'check-circle',
                'label' => $label,
                'sublabel' => trim((string)($badge['sublabel'] ?? '')),
            ];
        }

        return [
            'badges' => $badges,
            'bgcolor' => branding::runtime_colour((string)($settings['bgcolor'] ?? ''), '', []),
            'titlecolor' => branding::runtime_colour((string)($settings['titlecolor'] ?? ''), '', []),
            'titlefontsize' => self::number($settings['titlefontsize'] ?? 24, 0, 96),
            'cardbgcolor' => branding::runtime_colour((string)($settings['cardbgcolor'] ?? ''), '', []),
            'cardbordercolor' => branding::runtime_colour((string)($settings['cardbordercolor'] ?? ''), '', []),
            'cardborderwidth' => self::number($settings['cardborderwidth'] ?? 1, 0, 24),
            'cardradius' => self::number($settings['cardradius'] ?? 8, 0, 96),
            'iconbgcolor' => branding::runtime_colour((string)($settings['iconbgcolor'] ?? ''), '', []),
            'iconcolor' => branding::runtime_colour((string)($settings['iconcolor'] ?? ''), '', []),
            'iconsize' => self::number($settings['iconsize'] ?? 26, 0, 96),
            'labelcolor' => branding::runtime_colour((string)($settings['labelcolor'] ?? ''), '', []),
            'labelfontsize' => self::number($settings['labelfontsize'] ?? 16, 0, 96),
            'sublabelcolor' => branding::runtime_colour((string)($settings['sublabelcolor'] ?? ''), '', []),
            'sublabelfontsize' => self::number($settings['sublabelfontsize'] ?? 14, 0, 96),
            'paddingtop' => self::number($settings['paddingtop'] ?? 0, 0, 240),
            'paddingbottom' => self::number($settings['paddingbottom'] ?? 0, 0, 240),
            'labels' => [
                'trust' => get_string('p1_storefront_trust', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Bound a numeric visual control.
     *
     * @param mixed $value Raw value.
     * @param int $min Minimum accepted value.
     * @param int $max Maximum accepted value.
     * @return int
     */
    private static function number($value, int $min, int $max): int {
        return min($max, max($min, (int) round((float) $value)));
    }
}
