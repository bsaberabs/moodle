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
 * Resolver for the category tiles widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use local_moderncommerce\branding;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\services\category_service;
use moodle_url;

/**
 * Builds the top-level category tile list, linking into the filtered catalog.
 */
class categories_resolver implements widget_resolver {
    /** @var array|null Visible product categories keyed by id, loaded once per resolve. */
    private $categories = null;

    /** @var array|null Sellable product counts keyed by category id, loaded once per resolve. */
    private $productcounts = null;

    /**
     * Build the categories payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $showcount = (bool)($settings['showcount'] ?? true);
        $items = $settings['items'] ?? [];

        $style = (string)($settings['style'] ?? 'minimal');
        if (!in_array($style, ['minimal', 'colourful', 'carousel'], true)) {
            $style = 'minimal';
        }

        // Carousel: how many tiles are visible per view (the card width divisor). Clamp to a
        // sane band so a stray value can't collapse or over-shrink the track.
        $visiblecards = max(1, min(6, (int)($settings['visiblecards'] ?? 4)));

        // Optional admin-chosen section background for the carousel band ('' keeps the CSS default).
        $bgcolor = branding::runtime_colour(
            (string)($settings['bgcolor'] ?? ''),
            '',
            ['#0b7f75', '#0f766e', '#115e59', '#7c3aed']
        );

        $tiles = [];
        if (is_array($items) && !empty($items)) {
            // Admin-curated selection: resolve each chosen category in the chosen order.
            foreach ($items as $item) {
                $catid = (int)($item['categoryid'] ?? 0);
                if ($catid <= 0) {
                    continue;
                }
                $tile = $this->build_tile(
                    $catid,
                    $showcount,
                    (string)($item['icon'] ?? ''),
                    (string)($item['color'] ?? '')
                );
                if ($tile !== null) {
                    $tiles[] = $tile;
                }
            }
        } else {
            // Nothing selected yet: fall back to the visible product categories, in the order set
            // on the admin categories screen.
            try {
                foreach ($this->get_categories() as $category) {
                    $tile = $this->build_tile((int)$category['id'], $showcount, 'collection', '');
                    if ($tile !== null) {
                        $tiles[] = $tile;
                    }
                    if (count($tiles) >= 12) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $tiles = [];
            }
        }

        return [
            'categories' => $tiles,
            'showcount' => $showcount,
            'style' => $style,
            'visiblecards' => $visiblecards,
            'bgcolor' => $bgcolor,
            'titlecolor' => branding::runtime_colour((string)($settings['titlecolor'] ?? ''), '', []),
            'titlefontsize' => self::number($settings['titlefontsize'] ?? 0, 0, 96),
            'subtitlecolor' => branding::runtime_colour((string)($settings['subtitlecolor'] ?? ''), '', []),
            'subtitlefontsize' => self::number($settings['subtitlefontsize'] ?? 0, 0, 96),
            'cardbgcolor' => branding::runtime_colour((string)($settings['cardbgcolor'] ?? ''), '', []),
            'cardtextcolor' => branding::runtime_colour((string)($settings['cardtextcolor'] ?? ''), '', []),
            'cardtextfontsize' => self::number($settings['cardtextfontsize'] ?? 0, 0, 96),
            'cardradius' => self::number($settings['cardradius'] ?? 0, 0, 96),
            'iconbgcolor' => branding::runtime_colour((string)($settings['iconbgcolor'] ?? ''), '', []),
            'iconcolor' => branding::runtime_colour(
                (string)($settings['iconcolor'] ?? ''),
                'var(--mc-primary)',
                ['#0b7f75', '#0f766e', '#115e59', '#7c3aed']
            ),
            'iconsize' => self::number($settings['iconsize'] ?? 0, 0, 96),
            'countcolor' => branding::runtime_colour((string)($settings['countcolor'] ?? ''), '', []),
            'countfontsize' => self::number($settings['countfontsize'] ?? 0, 0, 96),
            'paddingtop' => max(0, (int)($settings['paddingtop'] ?? 0)),
            'paddingbottom' => max(0, (int)($settings['paddingbottom'] ?? 0)),
            'labels' => [
                // The tile count spans every sellable product type, not just courses.
                'courses' => get_string('productcountlabel', 'local_moderncommerce'),
                'scrollleft' => get_string('p1_storefront_scrollleft', 'local_moderncommerce'),
                'scrollright' => get_string('p1_storefront_scrollright', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Build a single category tile, or null if the category is missing/not visible.
     *
     * @param int $catid Category id.
     * @param bool $showcount Whether to include the course count.
     * @param string $icon Raw icon value (bare name or bi-prefixed).
     * @param string $color Optional admin-chosen tile colour (hex); '' falls back to the palette.
     * @return array|null
     */
    private function build_tile(int $catid, bool $showcount, string $icon, string $color = ''): ?array {
        try {
            $categories = $this->get_categories();
            // Only visible categories are loaded, so a miss means the category is hidden or gone.
            if (!isset($categories[$catid])) {
                return null;
            }
            $category = $categories[$catid];
            // Normalise to a bare icon name (no "bi-" / "bi " prefix); React adds "bi bi-".
            $icon = (string) preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', trim($icon)));
            // Only accept a valid hex colour; anything else becomes '' (palette fallback client-side).
            $color = trim((string) clean_param($color, PARAM_TEXT));
            if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color)) {
                $color = '';
            }
            if ($color !== '') {
                $color = branding::runtime_colour(
                    $color,
                    'var(--mc-primary)',
                    ['#0b7f75', '#0f766e', '#115e59', '#7c3aed']
                );
            }
            return [
                'id' => $catid,
                // React escapes text nodes itself, so decode first or ampersands double-encode.
                'name' => html_entity_decode($category['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                // Count what a shopper can actually buy in this category, not raw Moodle courses.
                'count' => $showcount ? ($this->get_product_counts()[$catid] ?? 0) : 0,
                'url' => (new moodle_url(
                    '/local/moderncommerce/index.php',
                    ['categoryid' => $catid]
                ))->out(false),
                'icon' => $icon !== '' ? $icon : 'collection',
                'color' => $color,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * The visible product categories keyed by id, loaded once per resolve.
     *
     * @return array Category id => ['id' => int, 'name' => string, 'slug' => string].
     */
    private function get_categories(): array {
        if ($this->categories === null) {
            $this->categories = [];
            foreach (category_service::get_visible_categories() as $category) {
                $this->categories[(int)$category['id']] = $category;
            }
        }

        return $this->categories;
    }

    /**
     * Sellable product counts keyed by category id, loaded once per resolve.
     *
     * @return array Category id => product count.
     */
    private function get_product_counts(): array {
        if ($this->productcounts === null) {
            $this->productcounts = category_service::count_visible_products();
        }

        return $this->productcounts;
    }

    /**
     * Bound a numeric style value.
     *
     * @param mixed $value Raw value.
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     * @return int
     */
    private static function number($value, int $min, int $max): int {
        return min($max, max($min, (int) round((float) $value)));
    }
}
