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
 * Dashboard insights layout service: which charts show, in what order, and at what width.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

/**
 * Reads/writes the per-admin dashboard chart layout and is the canonical chart catalog.
 *
 * Storage is delegated to {@see dashboard_pref_service}: a layout resolves personal -> site
 * default -> the built-in catalog defaults below, so every admin gets their own arrangement.
 */
class dashboard_layout_service {
    /** @var string Component for config storage. */
    private const COMPONENT = 'local_moderncommerce';

    /** @var string Config key holding the site-default JSON layout. */
    public const CONFIG_KEY = dashboard_pref_service::CFG_CHARTS;

    /** @var int[] Allowed 12-grid spans (full, half, third, quarter). */
    public const SIZES = [12, 6, 4, 3];

    /**
     * Canonical, ordered catalog of every dashboard chart.
     *
     * Single source of truth for chart ids, their title string keys, and default 12-grid size.
     * Adding a new chart here (and its builder in get_dashboard_charts) makes it auto-appear.
     *
     * @return array<string, array{titlekey: string, defaultsize: int}>
     */
    public static function catalog(): array {
        return [
            'revenue_trend' => ['titlekey' => 'chart_revenue_trend', 'defaultsize' => 12],
            'orders_conversion' => ['titlekey' => 'chart_orders_conversion', 'defaultsize' => 12],
            'recent_orders' => ['titlekey' => 'chart_recent_orders', 'defaultsize' => 6],
            'top_products_table' => ['titlekey' => 'chart_top_products_table', 'defaultsize' => 6],
            'aov_trend' => ['titlekey' => 'chart_aov', 'defaultsize' => 6],
            'top_products' => ['titlekey' => 'chart_top_products', 'defaultsize' => 6],
            'revenue_mix' => ['titlekey' => 'chart_revenue_mix', 'defaultsize' => 6],
            'gateway_success' => ['titlekey' => 'chart_gateway_success', 'defaultsize' => 6],
            'leakage_trend' => ['titlekey' => 'chart_leakage', 'defaultsize' => 6],
            'cart_funnel' => ['titlekey' => 'chart_cart_funnel', 'defaultsize' => 6],
            'new_vs_returning' => ['titlekey' => 'chart_new_returning', 'defaultsize' => 12],
            'sales_heatmap' => ['titlekey' => 'chart_heatmap', 'defaultsize' => 12],
            'tax_trend' => ['titlekey' => 'chart_tax', 'defaultsize' => 6],
            'coupon_roi' => ['titlekey' => 'chart_coupon_roi', 'defaultsize' => 6],
            'key_redemption' => ['titlekey' => 'chart_key_redemption', 'defaultsize' => 6],
            'time_to_payment' => ['titlekey' => 'chart_time_to_pay', 'defaultsize' => 6],
            'wishlist_demand' => ['titlekey' => 'chart_wishlist', 'defaultsize' => 6],
            'geo_revenue' => ['titlekey' => 'chart_geo', 'defaultsize' => 6],
        ];
    }

    /**
     * Read the resolved layout (personal -> site default), keyed by chart id.
     *
     * @param int|null $userid User id, or null for the current user.
     * @return array<string, array{enabled: bool, size: int, order: int}>
     */
    private static function stored(?int $userid = null): array {
        $decoded = dashboard_pref_service::resolve_layout(
            dashboard_pref_service::uid($userid),
            'chartslayout',
            self::CONFIG_KEY
        );
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }
            $out[(string) $row['id']] = [
                'enabled' => !empty($row['enabled']),
                'size' => self::clamp_size((int) ($row['size'] ?? 0)),
                'order' => (int) ($row['order'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * The full layout merged with the catalog: every catalog chart appears exactly once,
     * stored values applied where present, unknown stored ids dropped, new charts appended.
     *
     * @param int|null $userid User id, or null for the current user.
     * @return array<int, array{id: string, title: string, enabled: bool, size: int, order: int}>
     */
    public static function get_layout(?int $userid = null): array {
        $catalog = self::catalog();
        $stored = self::stored($userid);

        $rows = [];
        $fallbackorder = count($stored);
        foreach ($catalog as $id => $meta) {
            $s = $stored[$id] ?? null;
            $rows[] = [
                'id' => $id,
                'title' => get_string($meta['titlekey'], self::COMPONENT),
                'enabled' => $s ? $s['enabled'] : true,
                'size' => $s ? $s['size'] : (int) $meta['defaultsize'],
                // New charts (not yet in stored config) sort after everything saved.
                'order' => $s ? $s['order'] : $fallbackorder++,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return $a['order'] <=> $b['order'] ?: strcmp($a['id'], $b['id']);
        });

        // Re-sequence order to 0..n-1 so the client always gets clean indices.
        foreach ($rows as $i => &$row) {
            $row['order'] = $i;
        }
        unset($row);

        return $rows;
    }

    /**
     * Enabled charts in display order with their size: [chartid => size].
     *
     * @param int|null $userid User id, or null for the current user.
     * @return array<string, int>
     */
    public static function enabled_in_order(?int $userid = null): array {
        $out = [];
        foreach (self::get_layout($userid) as $row) {
            if ($row['enabled']) {
                $out[$row['id']] = $row['size'];
            }
        }
        return $out;
    }

    /**
     * Persist a submitted layout. Unknown ids ignored; sizes clamped; order re-sequenced.
     *
     * @param array $items List of {id, enabled, size, order}.
     * @param int|null $userid User id, or null for the current user (personal scope only).
     * @param string $scope dashboard_pref_service::SCOPE_PERSONAL|SCOPE_SITE.
     * @return array{success: bool, message: string}
     */
    public static function save_layout(
        array $items,
        ?int $userid = null,
        string $scope = dashboard_pref_service::SCOPE_PERSONAL
    ): array {
        $catalog = self::catalog();

        // Order items by their submitted order, keep only known ids (first occurrence wins).
        usort($items, static function ($a, $b): int {
            return (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0);
        });

        $clean = [];
        $seen = [];
        $order = 0;
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            if (!isset($catalog[$id]) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $clean[] = [
                'id' => $id,
                'enabled' => !empty($item['enabled']),
                'size' => self::clamp_size((int) ($item['size'] ?? 0), (int) $catalog[$id]['defaultsize']),
                'order' => $order++,
            ];
        }

        if ($scope === dashboard_pref_service::SCOPE_SITE) {
            dashboard_pref_service::set_site_layout(self::CONFIG_KEY, array_values($clean));
        } else {
            dashboard_pref_service::set_personal(
                dashboard_pref_service::uid($userid),
                ['chartslayout' => json_encode(array_values($clean))]
            );
        }

        return ['success' => true, 'message' => get_string('chart_layout_saved', self::COMPONENT)];
    }

    /**
     * Reset to defaults: clears the personal layout (or the site default in site scope).
     *
     * @param int|null $userid User id, or null for the current user.
     * @param string $scope dashboard_pref_service::SCOPE_PERSONAL|SCOPE_SITE.
     */
    public static function reset_layout(
        ?int $userid = null,
        string $scope = dashboard_pref_service::SCOPE_PERSONAL
    ): void {
        if ($scope === dashboard_pref_service::SCOPE_SITE) {
            dashboard_pref_service::set_site_layout(self::CONFIG_KEY, null);
        } else {
            // Personal reset clears the whole personal row so charts, panels and range all
            // fall back to the site default (or built-in catalog) together.
            dashboard_pref_service::clear_personal(dashboard_pref_service::uid($userid));
        }
    }

    /**
     * Size dropdown options for the manager UI.
     *
     * @return array<int, array{value: int, label: string}>
     */
    public static function size_options(): array {
        $labels = [
            12 => 'chart_size_full',
            6 => 'chart_size_half',
            4 => 'chart_size_third',
            3 => 'chart_size_quarter',
        ];
        $out = [];
        foreach (self::SIZES as $size) {
            $out[] = ['value' => $size, 'label' => get_string($labels[$size], self::COMPONENT)];
        }
        return $out;
    }

    /**
     * Clamp a size to an allowed span.
     *
     * @param int $size Requested size.
     * @param int $default Fallback when invalid.
     * @return int
     */
    private static function clamp_size(int $size, int $default = 6): int {
        return in_array($size, self::SIZES, true) ? $size : $default;
    }
}
