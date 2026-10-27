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
 * Resolver for the global breadcrumb/page-title widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use context_system;
use local_moderncommerce\branding;
use local_moderncommerce\output\public_page;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\zones;
use moodle_url;

/**
 * Builds a page-aware breadcrumb banner payload.
 */
class breadcrumb_resolver implements widget_resolver {
    /** @var string Fallback image for the media-backed styles. */
    private const DEFAULT_IMAGE =
        'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=1800&q=80';

    /**
     * Build the breadcrumb payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context.
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $ctx = context_system::instance();
        $hostpage = self::current_page($context);
        $excluded = self::page_list((string)($settings['excludedpages'] ?? ''));

        if ($hostpage !== '' && in_array($hostpage, $excluded, true)) {
            return [
                'hidden' => true,
                'currentpage' => $hostpage,
            ];
        }

        $catalog = self::page_map();
        $current = $catalog[$hostpage] ?? $catalog[zones::PAGE_CATALOG];
        $title = self::clean((string)$instance->get('title'), $ctx);
        if ($title === '') {
            $title = $current['title'];
        }

        $subtitle = self::clean((string)($settings['subtitle'] ?? ''), $ctx);
        $homelabel = self::clean((string)($settings['homelabel'] ?? get_string('home')), $ctx) ?: get_string('home');
        $homeurl = self::url((string)($settings['homeurl'] ?? '/local/moderncommerce/index.php'));
        $items = [
            ['label' => $homelabel, 'url' => $homeurl, 'active' => false],
        ];

        $sectionlabel = self::clean((string)($settings['sectionlabel'] ?? ''), $ctx);
        if ($sectionlabel !== '') {
            $items[] = [
                'label' => $sectionlabel,
                'url' => self::url((string)($settings['sectionurl'] ?? '')),
                'active' => false,
            ];
        }

        $items[] = ['label' => $title, 'url' => '', 'active' => true];

        return [
            'hidden' => false,
            'currentpage' => $hostpage,
            'style' => self::style((string)($settings['style'] ?? 'imagehero')),
            'alignment' => self::alignment((string)($settings['alignment'] ?? 'center')),
            'title' => $title,
            'subtitle' => $subtitle,
            'items' => $items,
            'backgroundimage' => self::image($settings),
            'bgcolor' => branding::runtime_colour(
                (string)($settings['bgcolor'] ?? ''),
                'var(--mc-surface-alt)',
                ['#f8fafc', '#f1f5f9', '#f4f6f8']
            ),
            'overlaycolor' => branding::runtime_colour(
                (string)($settings['overlaycolor'] ?? ''),
                'var(--mc-text)',
                ['#0f172a', '#111827']
            ),
            'gradientstart' => branding::runtime_colour(
                (string)($settings['gradientstart'] ?? ''),
                'var(--mc-primary)',
                ['#0b7f75', '#0f766e', '#7c3aed']
            ),
            'gradientend' => branding::runtime_colour(
                (string)($settings['gradientend'] ?? ''),
                'var(--mc-primary-hover)',
                ['#115e59', '#0f766e', '#6d33d1']
            ),
            'textcolor' => branding::runtime_colour(
                (string)($settings['textcolor'] ?? ''),
                'var(--mc-text)',
                ['#111827', '#0f172a']
            ),
            'accentcolor' => branding::runtime_colour(
                (string)($settings['accentcolor'] ?? ''),
                'var(--mc-primary)',
                ['#0b7f75', '#0f766e', '#7c3aed']
            ),
            'breadcrumbcolor' => branding::runtime_colour((string)($settings['breadcrumbcolor'] ?? ''), '', []),
            'titlecolor' => branding::runtime_colour((string)($settings['titlecolor'] ?? ''), '', []),
            'subtitlecolor' => branding::runtime_colour((string)($settings['subtitlecolor'] ?? ''), '', []),
            'breadcrumbfontsize' => self::optional_number($settings['breadcrumbfontsize'] ?? null, 8, 48),
            'titlefontsize' => self::optional_number($settings['titlefontsize'] ?? null, 16, 96),
            'subtitlefontsize' => self::optional_number($settings['subtitlefontsize'] ?? null, 10, 56),
            'overlayopacity' => self::optional_percent($settings['overlayopacity'] ?? null),
            'paddingtop' => self::spacing($settings['paddingtop'] ?? 82),
            'paddingbottom' => self::spacing($settings['paddingbottom'] ?? 82),
            'labels' => [
                'breadcrumb' => get_string('p1_publicpage_breadcrumb', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Resolve the real storefront page hosting a global widget.
     *
     * @param array $context Page context.
     * @return string
     */
    private static function current_page(array $context): string {
        $value = (string)($context['hostpage'] ?? $context['pagetype'] ?? '');
        $value = clean_param(strtolower(trim($value)), PARAM_ALPHANUMEXT);
        return zones::is_page($value) ? $value : zones::PAGE_CATALOG;
    }

    /**
     * Map page keys to short buyer-facing titles and routes.
     *
     * @return array<string, array{title: string, route: string}>
     */
    private static function page_map(): array {
        $catalog = public_page::page_catalog();
        $map = [
            zones::PAGE_CATALOG => [
                'title' => (string)$catalog[zones::PAGE_CATALOG]['title'],
                'route' => '/local/moderncommerce/index.php',
            ],
            zones::PAGE_ABOUT => [
                'title' => (string)$catalog[zones::PAGE_ABOUT]['title'],
                'route' => '/local/moderncommerce/about.php',
            ],
            zones::PAGE_SUPPORT => [
                'title' => (string)$catalog[zones::PAGE_SUPPORT]['title'],
                'route' => '/local/moderncommerce/support.php',
            ],
            zones::PAGE_TERMS => [
                'title' => (string)$catalog[zones::PAGE_TERMS]['title'],
                'route' => '/local/moderncommerce/terms.php',
            ],
            zones::PAGE_PRIVACY => [
                'title' => (string)$catalog[zones::PAGE_PRIVACY]['title'],
                'route' => '/local/moderncommerce/privacy.php',
            ],
            zones::PAGE_REFUND => [
                'title' => (string)$catalog[zones::PAGE_REFUND]['title'],
                'route' => '/local/moderncommerce/refund-policy.php',
            ],
            zones::PAGE_GLOBAL => ['title' => get_string('storeglobal', 'local_moderncommerce'), 'route' => ''],
        ];

        foreach ($catalog as $key => $page) {
            if (!isset($map[$key])) {
                $map[$key] = [
                    'title' => (string)($page['title'] ?? ucwords(str_replace('-', ' ', $key))),
                    'route' => (string)($page['route'] ?? ''),
                ];
            }
        }

        return $map;
    }

    /**
     * Clean display text.
     *
     * @param string $value Raw value.
     * @param \context $ctx Context.
     * @return string
     */
    private static function clean(string $value, \context $ctx): string {
        return format_string(trim($value), true, ['context' => $ctx, 'escape' => false]);
    }

    /**
     * Restrict the visual style to known variants.
     *
     * @param string $style Raw style.
     * @return string
     */
    private static function style(string $style): string {
        $style = strtolower(trim($style));
        return in_array($style, ['imagehero', 'clean', 'gradient', 'pastel', 'illustration'], true)
            ? $style
            : 'imagehero';
    }

    /**
     * Restrict alignment.
     *
     * @param string $alignment Raw alignment.
     * @return string
     */
    private static function alignment(string $alignment): string {
        $alignment = strtolower(trim($alignment));
        return in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : 'center';
    }

    /**
     * Parse comma/newline separated page exclusions.
     *
     * @param string $value Raw list.
     * @return string[]
     */
    private static function page_list(string $value): array {
        $parts = preg_split('/[\s,]+/', strtolower($value)) ?: [];
        $pages = [];
        foreach ($parts as $part) {
            $page = clean_param(trim($part), PARAM_ALPHANUMEXT);
            if ($page !== '' && zones::is_page($page)) {
                $pages[] = $page;
            }
        }

        return array_values(array_unique($pages));
    }

    /**
     * Resolve background image preference.
     *
     * @param array $settings Widget settings.
     * @return string
     */
    private static function image(array $settings): string {
        $source = clean_param(strtolower((string)($settings['backgroundsource'] ?? '')), PARAM_ALPHANUMEXT);
        if ($source === 'url') {
            $external = self::url((string)($settings['backgroundimage'] ?? ''));
            return $external !== '' ? $external : self::DEFAULT_IMAGE;
        }
        if ($source === 'upload') {
            $uploaded = self::url((string)($settings['backgroundfile'] ?? ''));
            return $uploaded !== '' ? $uploaded : self::DEFAULT_IMAGE;
        }

        $uploaded = self::url((string)($settings['backgroundfile'] ?? ''));
        if ($uploaded !== '') {
            return $uploaded;
        }

        $external = self::url((string)($settings['backgroundimage'] ?? ''));
        return $external !== '' ? $external : self::DEFAULT_IMAGE;
    }

    /**
     * Normalise URL values.
     *
     * @param string $value Raw URL.
     * @return string
     */
    private static function url(string $value): string {
        $value = trim(str_replace(['"', "'"], '', $value));
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if ($value[0] === '/') {
            return (new moodle_url($value))->out(false);
        }
        return '';
    }

    /**
     * Clamp spacing values to a practical range.
     *
     * @param mixed $value Raw number.
     * @return int
     */
    private static function spacing($value): int {
        return max(32, min(220, (int)$value));
    }

    /**
     * Clamp an optional numeric value. Blank means no explicit override.
     *
     * @param mixed $value Raw number.
     * @param int $min Minimum.
     * @param int $max Maximum.
     * @return int Zero when unset, otherwise a bounded value.
     */
    private static function optional_number($value, int $min, int $max): int {
        if ($value === null || $value === '') {
            return 0;
        }
        return max($min, min($max, (int)$value));
    }

    /**
     * Clamp optional overlay opacity from 0 to 100.
     *
     * @param mixed $value Raw opacity percentage.
     * @return int|null Null when unset.
     */
    private static function optional_percent($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }
        return max(0, min(100, (int)$value));
    }
}
