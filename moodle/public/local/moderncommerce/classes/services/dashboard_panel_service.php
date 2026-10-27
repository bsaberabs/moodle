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
 * Per-admin dashboard KPI panel layout: which summary tiles show and in what order.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

/**
 * Canonical catalog of the dashboard KPI panels plus per-admin visibility/order resolution.
 */
class dashboard_panel_service {
    /** @var string Component for config storage. */
    private const COMPONENT = 'local_moderncommerce';

    /** @var int[] Allowed 12-grid spans (full, half, third, quarter). */
    public const SIZES = [12, 6, 4, 3];

    /**
     * Canonical, ordered catalog of every KPI panel (matches get_dashboard metric keys).
     *
     * @return array<string, array{titlekey: string, defaultsize: int}>
     */
    public static function catalog(): array {
        return [
            'revenue' => ['titlekey' => 'totalrevenue', 'defaultsize' => 3],
            'orders' => ['titlekey' => 'totalorders', 'defaultsize' => 3],
            'pending' => ['titlekey' => 'pendingorders', 'defaultsize' => 3],
            'products' => ['titlekey' => 'activeproducts', 'defaultsize' => 3],
        ];
    }

    /**
     * Read the resolved panel layout (personal -> site default), keyed by panel id.
     *
     * @param int|null $userid User id, or null for the current user.
     * @return array<string, array{enabled: bool, size: int, order: int}>
     */
    private static function stored(?int $userid = null): array {
        $decoded = dashboard_pref_service::resolve_layout(
            dashboard_pref_service::uid($userid),
            'panellayout',
            dashboard_pref_service::CFG_PANELS
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
     * The full panel layout merged with the catalog.
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
                'order' => $s ? $s['order'] : $fallbackorder++,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return $a['order'] <=> $b['order'] ?: strcmp($a['id'], $b['id']);
        });

        foreach ($rows as $i => &$row) {
            $row['order'] = $i;
        }
        unset($row);

        return $rows;
    }

    /**
     * Enabled panels in display order with their size: [panelid => size].
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
    private static function clamp_size(int $size, int $default = 3): int {
        return in_array($size, self::SIZES, true) ? $size : $default;
    }

    /**
     * Persist a submitted panel layout. Unknown ids ignored; sizes clamped; order re-sequenced.
     *
     * @param array $items List of {id, enabled, size, order}.
     * @param int|null $userid User id, or null for the current user (personal scope only).
     * @param string $scope dashboard_pref_service::SCOPE_PERSONAL|SCOPE_SITE.
     */
    public static function save_layout(
        array $items,
        ?int $userid = null,
        string $scope = dashboard_pref_service::SCOPE_PERSONAL
    ): void {
        $catalog = self::catalog();

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
            dashboard_pref_service::set_site_layout(dashboard_pref_service::CFG_PANELS, array_values($clean));
        } else {
            dashboard_pref_service::set_personal(
                dashboard_pref_service::uid($userid),
                ['panellayout' => json_encode(array_values($clean))]
            );
        }
    }
}
