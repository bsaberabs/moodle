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
 * Resolver for public page FAQ widgets.
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
 * Builds FAQ payloads.
 */
class faq_resolver implements widget_resolver {
    /**
     * Build the FAQ payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();
        $items = [];

        foreach (($settings['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $question = self::clean((string)($item['question'] ?? ''), $ctx);
            $answer = self::paragraphs((string)($item['answer'] ?? ''), $ctx);
            if ($question === '' && empty($answer)) {
                continue;
            }
            $items[] = ['question' => $question, 'answer' => $answer];
        }

        return [
            'title' => self::clean((string)$instance->get('title'), $ctx),
            'subtitle' => self::clean((string)$instance->get('subtitle'), $ctx),
            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'subtitlecolor' => style_controls::colour($settings['subtitlecolor'] ?? ''),
            'subtitlefontsize' => style_controls::number($settings['subtitlefontsize'] ?? 0, 0, 0, 96),
            'itembgcolor' => style_controls::colour($settings['itembgcolor'] ?? ''),
            'itembordercolor' => style_controls::colour($settings['itembordercolor'] ?? ''),
            'cardborderwidth' => style_controls::number($settings['cardborderwidth'] ?? 1, 1, 0, 24),
            'cardradius' => style_controls::number($settings['cardradius'] ?? 6, 6, 0, 96),
            'questioncolor' => style_controls::colour($settings['questioncolor'] ?? ''),
            'labelfontsize' => style_controls::number($settings['labelfontsize'] ?? 0, 0, 0, 96),
            'answercolor' => style_controls::colour($settings['answercolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'iconcolor' => style_controls::colour($settings['iconcolor'] ?? ''),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
            'items' => $items,
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
     * Split body text into paragraphs.
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
}
