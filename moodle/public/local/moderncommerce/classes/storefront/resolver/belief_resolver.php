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
 * Resolver for the full-width belief statement widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\branding;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\style_controls;

/**
 * Builds the About-page belief statement payload.
 */
class belief_resolver implements widget_resolver {
    /**
     * Default points used when an instance has not been configured yet.
     *
     * @return array[]
     */
    private static function default_items(): array {
        return [
            [
                'icon' => 'globe2',
                'text' => 'It helps learners move from uncertainty to capability, from interest to purchase, ' .
                    'and from enrolment to real progress.',
            ],
            [
                'icon' => 'people',
                'text' => 'It can transform careers, teams, families, and communities when the right course ' .
                    'is easy to find and start.',
            ],
            [
                'icon' => 'graph-up-arrow',
                'text' => 'No matter where someone begins, accessible courses and programmes can unlock new ' .
                    'skills, confidence, and opportunity.',
            ],
            [
                'icon' => 'bank',
                'text' => 'That is why this store brings trusted instructors, practical programmes, ' .
                    'secure checkout, and instant enrolment together.',
            ],
        ];
    }

    /**
     * Build the belief statement payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();
        $items = self::items($settings['items'] ?? [], $ctx);
        if (empty($items)) {
            $items = self::items(self::default_items(), $ctx);
        }

        return [
            'title' => self::clean((string) $instance->get('title'), $ctx) ?: 'We believe',
            'subtitle' => self::clean((string) $instance->get('subtitle'), $ctx)
                ?: 'Learning is the source of human progress.',
            'bgcolor' => branding::runtime_colour(
                (string) ($settings['bgcolor'] ?? ''),
                'var(--mc-primary)',
                ['#0b7f75', '#0f766e', '#115e59', '#7c3aed']
            ),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'subtitlecolor' => style_controls::colour($settings['subtitlecolor'] ?? ''),
            'subtitlefontsize' => style_controls::number($settings['subtitlefontsize'] ?? 0, 0, 0, 96),
            'iconcolor' => style_controls::colour($settings['iconcolor'] ?? ''),
            'iconsize' => style_controls::number($settings['iconsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'labelcolor' => style_controls::colour($settings['labelcolor'] ?? ''),
            'labelfontsize' => style_controls::number($settings['labelfontsize'] ?? 0, 0, 0, 96),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
            'items' => $items,
            'closing' => self::clean((string) ($settings['closing'] ?? ''), $ctx)
                ?: 'So anyone, anywhere can buy the right course and turn learning into opportunity.',
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
     * Normalise configured icon/text rows.
     *
     * @param mixed $rows Raw row list.
     * @param \context $ctx Context.
     * @return array[]
     */
    private static function items($rows, \context $ctx): array {
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $text = self::clean((string) ($row['text'] ?? ''), $ctx);
            if ($text === '') {
                continue;
            }
            $items[] = [
                'icon' => self::icon((string) ($row['icon'] ?? 'globe2')),
                'text' => $text,
            ];
        }

        return $items;
    }

    /**
     * Normalise a Bootstrap icon value.
     *
     * @param string $value Raw icon.
     * @return string
     */
    private static function icon(string $value): string {
        $icon = preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', trim($value)));
        return $icon !== '' ? $icon : 'globe2';
    }
}
