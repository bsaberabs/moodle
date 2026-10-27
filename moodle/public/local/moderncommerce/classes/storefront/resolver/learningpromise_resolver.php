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
 * Resolver for the centered learning promise widget.
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
 * Builds the payload for a centered learning promise statement.
 */
class learningpromise_resolver implements widget_resolver {
    /**
     * Build the learning promise payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();

        return [
            'title' => self::clean((string) $instance->get('title'), $ctx)
                ?: 'Skills are the key to unlocking potential',
            'body' => self::clean((string) ($settings['body'] ?? ''), $ctx)
                ?: 'Whether you want to learn a new skill, train your team, or invest in a full programme, ' .
                    'you are in the right place. Our course marketplace helps you find the right offer, ' .
                    'buy with confidence, and start learning right away.',
            'bgcolor' => branding::runtime_colour(
                (string) ($settings['bgcolor'] ?? ''),
                'var(--mc-surface)',
                ['#ffffff']
            ),
            'headingcolor' => branding::runtime_colour(
                (string) ($settings['headingcolor'] ?? ''),
                'var(--mc-text)',
                ['#111827', '#0f172a']
            ),
            'headingfontsize' => style_controls::number($settings['headingfontsize'] ?? 0, 0, 0, 96),
            'textcolor' => branding::runtime_colour(
                (string) ($settings['textcolor'] ?? ''),
                'var(--mc-text)',
                ['#111827', '#0f172a']
            ),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
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
}
