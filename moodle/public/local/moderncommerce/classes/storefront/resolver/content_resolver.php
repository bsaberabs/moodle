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
 * Resolver for editable public page content sections.
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
use moodle_url;

/**
 * Builds the payload for a general content section widget.
 */
class content_resolver implements widget_resolver {
    /**
     * Build the content payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();

        return [
            'eyebrow' => self::clean((string)($settings['eyebrow'] ?? ''), $ctx),
            'title' => self::clean((string)$instance->get('title'), $ctx),
            'subtitle' => self::clean((string)$instance->get('subtitle'), $ctx),
            'icon' => self::icon((string)($settings['icon'] ?? 'mortarboard')),
            'layout' => self::layout((string)($settings['layout'] ?? 'card')),
            'mediaposition' => self::media_position((string)($settings['mediaposition'] ?? 'right')),
            'bgcolor' => branding::runtime_colour(
                (string)($settings['bgcolor'] ?? ''),
                'var(--mc-surface)',
                ['#ffffff']
            ),
            'panelbgcolor' => style_controls::colour($settings['panelbgcolor'] ?? ''),
            'panelbordercolor' => style_controls::colour($settings['panelbordercolor'] ?? ''),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'subtitlecolor' => style_controls::colour($settings['subtitlecolor'] ?? ''),
            'subtitlefontsize' => style_controls::number($settings['subtitlefontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'iconbgcolor' => style_controls::colour($settings['iconbgcolor'] ?? ''),
            'iconcolor' => style_controls::colour($settings['iconcolor'] ?? ''),
            'iconsize' => style_controls::number($settings['iconsize'] ?? 0, 0, 0, 96),
            'benefitnumbercolor' => style_controls::colour($settings['benefitnumbercolor'] ?? ''),
            'benefitnumberfontsize' => style_controls::number($settings['benefitnumberfontsize'] ?? 0, 0, 0, 96),
            'benefittitlecolor' => style_controls::colour($settings['benefittitlecolor'] ?? ''),
            'benefittitlefontsize' => style_controls::number($settings['benefittitlefontsize'] ?? 0, 0, 0, 96),
            'benefittextcolor' => style_controls::colour($settings['benefittextcolor'] ?? ''),
            'benefittextfontsize' => style_controls::number($settings['benefittextfontsize'] ?? 0, 0, 0, 96),
            'benefitbordercolor' => style_controls::colour($settings['benefitbordercolor'] ?? ''),
            'buttoncolor' => style_controls::colour($settings['buttoncolor'] ?? ''),
            'buttontextcolor' => style_controls::colour($settings['buttontextcolor'] ?? ''),
            'buttonradius' => style_controls::number($settings['buttonradius'] ?? 0, 0, 0, 96),
            'mediaradius' => style_controls::number($settings['mediaradius'] ?? 8, 8, 0, 96),
            'paddingtop' => self::spacing($settings['paddingtop'] ?? 72),
            'paddingbottom' => self::spacing($settings['paddingbottom'] ?? 72),
            'paddingleft' => self::spacing($settings['paddingleft'] ?? 0),
            'paddingright' => self::spacing($settings['paddingright'] ?? 0),
            'cardradius' => self::radius($settings['cardradius'] ?? 8),
            'paragraphs' => self::paragraphs((string)($settings['body'] ?? ''), $ctx),
            'benefits' => self::benefits($settings['benefits'] ?? [], $ctx),
            'image' => style_controls::sourced_image($settings, 'imagesource', 'imageurl', 'imagefile', 'image'),
            'cta' => [
                'label' => self::clean((string)($settings['ctalabel'] ?? ''), $ctx),
                'url' => self::url((string)($settings['ctaurl'] ?? '')),
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
     * Split textarea content into display paragraphs.
     *
     * @param string $value Raw body text.
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
     * Clean repeated benefit rows.
     *
     * @param mixed $rows Raw rows from widget settings.
     * @param \context $ctx Context.
     * @return array<int, array<string, string>>
     */
    private static function benefits($rows, \context $ctx): array {
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = self::clean((string)($row['title'] ?? ''), $ctx);
            $text = self::clean((string)($row['text'] ?? ''), $ctx);
            if ($title === '' && $text === '') {
                continue;
            }
            $number = self::clean((string)($row['number'] ?? ''), $ctx);
            if ($number === '') {
                $number = str_pad((string)(count($out) + 1), 2, '0', STR_PAD_LEFT);
            }
            $out[] = [
                'number' => $number,
                'title' => $title,
                'text' => $text,
            ];
        }
        return $out;
    }

    /**
     * Normalise a Bootstrap icon value.
     *
     * @param string $value Raw icon.
     * @return string
     */
    private static function icon(string $value): string {
        $icon = preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', trim($value)));
        return $icon !== '' ? $icon : 'mortarboard';
    }

    /**
     * Restrict layout to supported values.
     *
     * @param string $layout Layout key.
     * @return string
     */
    private static function layout(string $layout): string {
        return in_array($layout, ['card', 'centered', 'split'], true) ? $layout : 'card';
    }

    /**
     * Restrict media position to supported values.
     *
     * @param string $position Media position key.
     * @return string
     */
    private static function media_position(string $position): string {
        return in_array($position, ['left', 'right'], true) ? $position : 'right';
    }

    /**
     * Clamp vertical spacing to a sane pixel range.
     *
     * @param mixed $value Submitted value.
     * @return int
     */
    private static function spacing($value): int {
        return max(0, min(200, (int)$value));
    }

    /**
     * Clamp card radius to a practical pixel range.
     *
     * @param mixed $value Submitted value.
     * @return int
     */
    private static function radius($value): int {
        return max(0, min(40, (int)$value));
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
