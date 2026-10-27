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
 * Resolver for the side-by-side media story carousel widget.
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
 * Builds the payload for a media-and-copy carousel.
 */
class mediastorycarousel_resolver implements widget_resolver {
    /** @var array<string, array{0: string, 1: string}> Supported navigation icon pairs. */
    private const NAV_ICON_PAIRS = [
        'chevron-right' => ['chevron-left', 'chevron-right'],
        'arrow-right' => ['arrow-left', 'arrow-right'],
        'caret-right' => ['caret-left', 'caret-right'],
        'caret-right-fill' => ['caret-left-fill', 'caret-right-fill'],
        'chevron-compact-right' => ['chevron-compact-left', 'chevron-compact-right'],
        'chevron-double-right' => ['chevron-double-left', 'chevron-double-right'],
        'arrow-right-circle' => ['arrow-left-circle', 'arrow-right-circle'],
        'arrow-right-short' => ['arrow-left-short', 'arrow-right-short'],
    ];

    /** @var array<string, string> Legacy navigation styles from the first single-setting implementation. */
    private const LEGACY_NAV_STYLES = [
        'chevron' => 'chevron-right',
        'arrow' => 'arrow-right',
        'caret' => 'caret-right',
        'filled-caret' => 'caret-right-fill',
        'compact-chevron' => 'chevron-compact-right',
        'double-chevron' => 'chevron-double-right',
        'circle-arrow' => 'arrow-right-circle',
        'short-arrow' => 'arrow-right-short',
    ];

    /**
     * Build the carousel payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();
        $slides = $this->resolve_slides($settings, $ctx);
        $navstyle = self::nav_style_for_settings($settings);
        [$previcon, $nexticon] = self::NAV_ICON_PAIRS[$navstyle];

        if (empty($slides)) {
            $slides[] = [
                'heading' => 'Courses that move careers forward',
                'subheading' => 'Showcase programmes with clear outcomes, secure checkout, and instant access ' .
                    'so learners can start building valuable skills right away.',
                'media' => [
                    'type' => 'image',
                    'mode' => 'image',
                    'url' => '',
                    'posterurl' => '',
                    'alt' => '',
                    'mimetype' => '',
                    'embedurl' => '',
                    'hasmedia' => false,
                ],
            ];
        }

        return [
            'mediaposition' => ((string) ($settings['mediaposition'] ?? 'left')) === 'right' ? 'right' : 'left',
            'bgcolor' => branding::runtime_colour(
                (string) ($settings['bgcolor'] ?? ''),
                'var(--mc-surface-alt)',
                ['#f4f6f8', '#f8fafc', '#f1f5f9']
            ),
            'paddingtop' => max(0, (int) ($settings['paddingtop'] ?? 20)),
            'paddingbottom' => max(0, (int) ($settings['paddingbottom'] ?? 80)),
            'navicon' => $navstyle,
            'previcon' => $previcon,
            'nexticon' => $nexticon,
            'cardbgcolor' => style_controls::colour($settings['cardbgcolor'] ?? ''),
            'cardbordercolor' => style_controls::colour($settings['cardbordercolor'] ?? ''),
            'cardborderwidth' => style_controls::number($settings['cardborderwidth'] ?? 0, 0, 0, 24),
            'cardradius' => style_controls::number($settings['cardradius'] ?? 8, 8, 0, 96),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'textcolor' => style_controls::colour($settings['textcolor'] ?? ''),
            'textfontsize' => style_controls::number($settings['textfontsize'] ?? 0, 0, 0, 96),
            'iconcolor' => style_controls::colour($settings['iconcolor'] ?? ''),
            'iconbgcolor' => style_controls::colour($settings['iconbgcolor'] ?? ''),
            'mediaradius' => style_controls::number($settings['mediaradius'] ?? 8, 8, 0, 96),
            'slides' => $slides,
            'labels' => [
                'previousslide' => get_string('p1_storefront_previousslide', 'local_moderncommerce'),
                'nextslide' => get_string('p1_storefront_nextslide', 'local_moderncommerce'),
                'playvideo' => get_string('p1_storefront_playvideo', 'local_moderncommerce'),
                'video' => get_string('p1_storefront_video', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Resolve slide list.
     *
     * @param array $settings Settings.
     * @param \context $ctx Context.
     * @return array[]
     */
    private function resolve_slides(array $settings, \context $ctx): array {
        $rows = is_array($settings['slides'] ?? null) ? $settings['slides'] : [];
        $slides = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $heading = self::clean((string) ($row['heading'] ?? ''), $ctx);
            $subheading = self::clean((string) ($row['subheading'] ?? ''), $ctx);
            $media = $this->resolve_media($row);

            if ($heading === '' && $subheading === '' && empty($media['hasmedia'])) {
                continue;
            }

            $slides[] = [
                'heading' => $heading,
                'subheading' => $subheading,
                'media' => $media,
            ];
        }

        return $slides;
    }

    /**
     * Resolve one slide media object.
     *
     * @param array $row Slide settings.
     * @return array
     */
    private function resolve_media(array $row): array {
        $type = ((string) ($row['mediatype'] ?? 'image')) === 'video' ? 'video' : 'image';
        $source = ((string) ($row['mediasource'] ?? 'url')) === 'upload' ? 'upload' : 'url';
        $alt = trim((string) ($row['alt'] ?? ''));

        if ($type === 'image') {
            $url = $source === 'upload'
                ? self::to_url((string) ($row['imagefile'] ?? ''))
                : self::to_url((string) ($row['imageurl'] ?? ''));
            return [
                'type' => 'image',
                'mode' => 'image',
                'url' => $url,
                'posterurl' => '',
                'embedurl' => '',
                'mimetype' => '',
                'alt' => $alt,
                'hasmedia' => $url !== '',
            ];
        }

        $poster = self::to_url((string) ($row['posterimage'] ?? ''))
            ?: self::to_url((string) ($row['posterurl'] ?? ''));

        if ($source === 'upload') {
            $file = $row['videofile'] ?? '';
            $url = is_array($file) ? self::to_url((string) ($file['url'] ?? '')) : '';
            return [
                'type' => 'video',
                'mode' => 'file',
                'url' => $url,
                'posterurl' => $poster,
                'embedurl' => '',
                'mimetype' => is_array($file) ? (string) ($file['mime'] ?? '') : '',
                'alt' => $alt,
                'hasmedia' => $url !== '',
            ];
        }

        $videourl = trim((string) ($row['videourl'] ?? ''));
        $embed = self::parse_embed($videourl);
        if ($embed !== null) {
            return [
                'type' => 'video',
                'mode' => 'embed',
                'url' => '',
                'posterurl' => $poster ?: $embed['posterurl'],
                'embedurl' => $embed['embedurl'],
                'mimetype' => '',
                'alt' => $alt,
                'hasmedia' => true,
            ];
        }

        $direct = self::direct_video($videourl);
        return [
            'type' => 'video',
            'mode' => 'file',
            'url' => $direct['url'],
            'posterurl' => $poster,
            'embedurl' => '',
            'mimetype' => $direct['mimetype'],
            'alt' => $alt,
            'hasmedia' => $direct['url'] !== '',
        ];
    }

    /**
     * Parse YouTube/Vimeo URL into an embeddable player URL.
     *
     * @param string $url Raw URL.
     * @return array|null
     */
    private static function parse_embed(string $url): ?array {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $yt = '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|v/))([A-Za-z0-9_-]{11})~';
        if (preg_match($yt, $url, $m)) {
            return [
                'embedurl' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0',
                'posterurl' => 'https://i.ytimg.com/vi/' . $m[1] . '/hqdefault.jpg',
            ];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return ['embedurl' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1', 'posterurl' => ''];
        }

        return null;
    }

    /**
     * Resolve a direct video file URL and MIME type.
     *
     * @param string $url Raw URL.
     * @return array
     */
    private static function direct_video(string $url): array {
        $url = trim($url);
        if ($url === '') {
            return ['url' => '', 'mimetype' => ''];
        }

        if (preg_match('~^https?://.+\.(mp4|webm|ogg|ogv|mov)(\?.*)?$~i', $url, $m)) {
            $ext = strtolower($m[1]);
            return [
                'url' => (new moodle_url($url))->out(false),
                'mimetype' => ($ext === 'mov') ? 'video/quicktime' : 'video/' . ($ext === 'ogv' ? 'ogg' : $ext),
            ];
        }

        return ['url' => '', 'mimetype' => ''];
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
     * Resolve a selected or legacy navigation style.
     *
     * @param array $settings Widget settings.
     * @return string
     */
    private static function nav_style_for_settings(array $settings): string {
        $style = self::nav_style((string) ($settings['navicon'] ?? ''));

        if ($style === '') {
            $style = self::nav_style_from_legacy(
                (string) ($settings['previcon'] ?? ''),
                (string) ($settings['nexticon'] ?? '')
            );
        }

        return $style ?: 'chevron-right';
    }

    /**
     * Normalise a selected navigation style.
     *
     * @param string $value Raw value.
     * @return string Empty string when unsupported.
     */
    private static function nav_style(string $value): string {
        $style = strtolower(trim($value));
        $style = preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', $style));
        if (array_key_exists($style, self::LEGACY_NAV_STYLES)) {
            $style = self::LEGACY_NAV_STYLES[$style];
        }
        return array_key_exists($style, self::NAV_ICON_PAIRS) ? $style : '';
    }

    /**
     * Convert the old separate previous/next icon settings to the new single style.
     *
     * @param string $prev Previous icon.
     * @param string $next Next icon.
     * @return string Empty string when no matching pair exists.
     */
    private static function nav_style_from_legacy(string $prev, string $next): string {
        $prev = strtolower(trim((string) preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', $prev))));
        $next = strtolower(trim((string) preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', $next))));

        foreach (self::NAV_ICON_PAIRS as $style => [$left, $right]) {
            if ($prev === $left && $next === $right) {
                return $style;
            }
        }

        foreach (self::NAV_ICON_PAIRS as $style => [$left, $right]) {
            if ($prev === $left || $next === $right) {
                return $style;
            }
        }

        return '';
    }

    /**
     * Normalise a URL.
     *
     * @param string $value Raw URL.
     * @return string
     */
    private static function to_url(string $value): string {
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
