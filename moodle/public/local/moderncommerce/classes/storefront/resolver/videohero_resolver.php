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
 * Resolver for the split video-hero widget (copy + click-to-play video panel + stat card).
 *
 * Ports the rendering model of the former block_startuhero into the storefront widget system.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\branding;
use local_moderncommerce\persistent\widget;
use moodle_url;

/**
 * Builds the render payload for a video-hero widget.
 */
class videohero_resolver implements widget_resolver {
    /**
     * Build the payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $s = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();

        $heading = trim((string) ($s['heading'] ?? '')) !== ''
            ? (string) $s['heading'] : 'Shop courses and|programmes online';
        $subtext = trim((string) ($s['subtext'] ?? '')) !== ''
            ? (string) $s['subtext']
            : 'Find the right course, purchase securely, and get instant access to expert-led '
                . 'learning, certificates, and bundled programme offers.';

        $primary = [
            'label' => self::clean(trim((string) ($s['btn_primary_label'] ?? '')) !== ''
                ? (string) $s['btn_primary_label'] : 'Browse courses', $ctx),
            'url' => self::to_url((string) ($s['btn_primary_url'] ?? '')) ?: '#',
        ];
        $secondary = null;
        $seclabel = trim((string) ($s['btn_secondary_label'] ?? ''));
        $securl = self::to_url((string) ($s['btn_secondary_url'] ?? ''));
        if ($seclabel !== '' && $securl !== '') {
            $secondary = ['label' => self::clean($seclabel, $ctx), 'url' => $securl];
        }

        return [
            'headinglines' => array_map(static fn($p) => self::clean(trim($p), $ctx), explode('|', $heading)),
            'subtext' => self::clean($subtext, $ctx),
            'primary' => $primary,
            'secondary' => $secondary,
            'hassecondary' => $secondary !== null,
            'bgcolor' => branding::runtime_colour(
                (string) ($s['bgcolor'] ?? ''),
                'var(--mc-primary)',
                ['#0b7f75', '#0f766e', '#115e59', '#7c3aed']
            ),
            'accent' => branding::runtime_colour(
                (string) ($s['accentcolor'] ?? ''),
                'var(--mc-surface)',
                ['#ffffff', '#fff', '#f8fafc']
            ),
            'primarybuttoncolor' => branding::runtime_colour((string) ($s['primarybuttoncolor'] ?? ''), '', []),
            'primarybuttontextcolor' => branding::runtime_colour((string) ($s['primarybuttontextcolor'] ?? ''), '', []),
            'secondarybuttoncolor' => branding::runtime_colour((string) ($s['secondarybuttoncolor'] ?? ''), '', []),
            'secondarybuttontextcolor' => branding::runtime_colour((string) ($s['secondarybuttontextcolor'] ?? ''), '', []),
            'infocardbgcolor' => branding::runtime_colour((string) ($s['infocardbgcolor'] ?? ''), '', []),
            'infoiconbgcolor' => branding::runtime_colour((string) ($s['infoiconbgcolor'] ?? ''), '', []),
            'infoiconcolor' => branding::runtime_colour((string) ($s['infoiconcolor'] ?? ''), '', []),
            'infoheadingcolor' => branding::runtime_colour((string) ($s['infoheadingcolor'] ?? ''), '', []),
            'infoheadingfontsize' => min(96, max(0, (int) ($s['infoheadingfontsize'] ?? 0))),
            'infotextcolor' => branding::runtime_colour((string) ($s['infotextcolor'] ?? ''), '', []),
            'video' => $this->resolve_video($s),
            'infoitems' => $this->resolve_infoitems($s, $ctx),
            'showquote' => (bool) ($s['showquote'] ?? true),
            'quote' => $this->resolve_quote($s, $ctx),
            'labels' => [
                'playvideo' => get_string('p1_storefront_playvideo', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Resolve the right-hand video panel into a render mode (image|file|embed).
     *
     * @param array $s Settings.
     * @return array
     */
    private function resolve_video(array $s): array {
        $source = (string) ($s['video_source'] ?? 'none');
        $title = self::clean(trim((string) ($s['video_title'] ?? '')), context_system::instance());
        $poster = self::to_url((string) ($s['video_poster'] ?? ''));

        $video = [
            'mode' => 'image',
            'haspanelvideo' => false,
            'embedurl' => '',
            'fileurl' => '',
            'mimetype' => '',
            'posterurl' => $source === 'none' ? '' : $poster,
            'title' => $title,
            'hastitle' => $title !== '',
        ];

        if ($source === 'upload') {
            // The video_file value is stored as {url, mime} (object) by the editor.
            $file = $s['video_file'] ?? '';
            $url = is_array($file) ? (string) ($file['url'] ?? '') : '';
            if ($url !== '') {
                $video['mode'] = 'file';
                $video['haspanelvideo'] = true;
                $video['fileurl'] = $url;
                $video['mimetype'] = is_array($file) ? (string) ($file['mime'] ?? '') : '';
            }
        } else if ($source === 'url') {
            $embed = self::parse_embed((string) ($s['video_url'] ?? ''));
            if ($embed !== null) {
                $video['mode'] = 'embed';
                $video['haspanelvideo'] = true;
                $video['embedurl'] = $embed['embedurl'];
                if ($video['posterurl'] === '' && $embed['posterurl'] !== '') {
                    $video['posterurl'] = $embed['posterurl'];
                }
            } else {
                // A direct video file URL (.mp4/.webm/.ogg/.mov) plays inline like an upload.
                $direct = trim((string) ($s['video_url'] ?? ''));
                if (preg_match('~^https?://.+\.(mp4|webm|ogg|ogv|mov)(\?.*)?$~i', $direct, $m)) {
                    $video['mode'] = 'file';
                    $video['haspanelvideo'] = true;
                    $video['fileurl'] = (new moodle_url($direct))->out(false);
                    $ext = strtolower($m[1]);
                    $video['mimetype'] = ($ext === 'mov') ? 'video/quicktime'
                        : 'video/' . ($ext === 'ogv' ? 'ogg' : $ext);
                }
            }
        }

        $video['hasposter'] = $video['posterurl'] !== '';
        return $video;
    }

    /**
     * Resolve the three info-card stat boxes, falling back to the course-store defaults.
     *
     * @param array $s Settings.
     * @param \context $ctx Context.
     * @return array
     */
    private function resolve_infoitems(array $s, \context $ctx): array {
        $items = is_array($s['infoitems'] ?? null) ? $s['infoitems'] : [];
        if (empty($items)) {
            $items = [
                ['icon' => 'bi-mortarboard', 'title' => 'Course bundles',
                    'text' => 'Save on curated learning paths.'],
                ['icon' => 'bi-lock', 'title' => 'Secure checkout', 'text' => 'Buy with confidence in minutes.'],
                ['icon' => 'bi-unlock', 'title' => 'Instant enrolment',
                    'text' => 'Start learning right after payment.'],
            ];
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $text = trim((string) ($item['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            // Normalise to a bare icon name (no "bi-" / "bi " prefix); React adds "bi bi-".
            $icon = (string) preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', trim((string) ($item['icon'] ?? ''))));
            $out[] = [
                'iconclass' => $icon,
                'hasicon' => $icon !== '',
                'title' => self::clean($title, $ctx),
                'text' => self::clean($text, $ctx),
            ];
        }
        return $out;
    }

    /**
     * Resolve the testimonial quote.
     *
     * @param array $s Settings.
     * @param \context $ctx Context.
     * @return array
     */
    private function resolve_quote(array $s, \context $ctx): array {
        $text = trim((string) ($s['quote_text'] ?? '')) !== ''
            ? (string) $s['quote_text']
            : 'Our mission is to make premium learning easy to discover, purchase, and start.';
        $author = trim((string) ($s['quote_author'] ?? '')) !== ''
            ? (string) $s['quote_author'] : 'Modern Commerce';
        return [
            'text' => self::clean($text, $ctx),
            'author' => self::clean($author, $ctx),
            'hasauthor' => $author !== '',
        ];
    }

    /**
     * Parse a YouTube/Vimeo URL into an embeddable player URL (+ poster when derivable).
     *
     * @param string $url Raw URL.
     * @return array|null ['embedurl' => string, 'posterurl' => string] or null.
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
     * Format a plain string for safe display.
     *
     * @param string $value Raw text.
     * @param \context $ctx Context.
     * @return string
     */
    private static function clean(string $value, \context $ctx): string {
        // Escape=false: React renders these as text nodes and escapes them itself, so
        // pre-encoding here would double-escape (e.g. "&" would show as "&amp;").
        return format_string($value, true, ['context' => $ctx, 'escape' => false]);
    }

    /**
     * Normalise a stored link into an absolute URL (absolute http(s) or site-relative path).
     *
     * @param string $value Raw link.
     * @return string Absolute URL or empty string.
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
