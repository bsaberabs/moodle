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
 * Resolver for structured public policy content.
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
 * Builds policy section payloads for terms, privacy, and refund pages.
 */
class policy_resolver implements widget_resolver {
    /**
     * Build the policy payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();
        $sections = [];

        foreach (($settings['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $heading = self::clean((string)($section['heading'] ?? ''), $ctx);
            $body = self::paragraphs((string)($section['body'] ?? ''), $ctx);
            $bullets = self::lines((string)($section['bullets'] ?? ''), $ctx);
            if ($heading === '' && empty($body) && empty($bullets)) {
                continue;
            }
            $sections[] = [
                'heading' => $heading,
                'body' => $body,
                'bullets' => $bullets,
            ];
        }

        return [
            'title' => self::clean((string)$instance->get('title'), $ctx),
            'subtitle' => self::clean((string)$instance->get('subtitle'), $ctx),
            'effectivedate' => self::clean((string)($settings['effectivedate'] ?? ''), $ctx),
            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'cardbgcolor' => style_controls::colour($settings['cardbgcolor'] ?? ''),
            'cardbordercolor' => style_controls::colour($settings['cardbordercolor'] ?? ''),
            'cardborderwidth' => style_controls::number($settings['cardborderwidth'] ?? 1, 1, 0, 24),
            'cardradius' => style_controls::number($settings['cardradius'] ?? 8, 8, 0, 96),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'subtitlecolor' => style_controls::colour($settings['subtitlecolor'] ?? ''),
            'subtitlefontsize' => style_controls::number($settings['subtitlefontsize'] ?? 0, 0, 0, 96),
            'labelcolor' => style_controls::colour($settings['labelcolor'] ?? ''),
            'labelfontsize' => style_controls::number($settings['labelfontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
            'sections' => $sections,
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
     * Split textarea content into paragraphs.
     *
     * @param string $value Raw text.
     * @param \context $ctx Context.
     * @return string[]
     */
    private static function paragraphs(string $value, \context $ctx): array {
        $parts = preg_split('/\R{2,}/', trim($value)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $text = trim((string)$part);
            if ($text !== '') {
                $out[] = self::clean($text, $ctx);
            }
        }
        return $out;
    }

    /**
     * Split newline-separated bullet text.
     *
     * @param string $value Raw text.
     * @param \context $ctx Context.
     * @return string[]
     */
    private static function lines(string $value, \context $ctx): array {
        $parts = preg_split('/\R/', trim($value)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $text = trim((string)$part);
            if ($text !== '') {
                $out[] = self::clean($text, $ctx);
            }
        }
        return $out;
    }
}
