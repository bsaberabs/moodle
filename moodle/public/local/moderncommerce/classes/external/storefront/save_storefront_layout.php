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
 * External API saving the storefront widget layout (zone, order, visibility).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\storefront;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use invalid_parameter_exception;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\zones;

/**
 * Persists the arranged storefront layout: each widget's zone, visibility and order.
 */
class save_storefront_layout extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Widget id.'),
                'zone' => new external_value(PARAM_ALPHANUMEXT, 'Zone slug.'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the widget is shown.'),
                'sortorder' => new external_value(PARAM_INT, 'Order within the zone.'),
            ]), 'Arranged widget rows.', VALUE_DEFAULT, []),
            'page' => new external_value(PARAM_ALPHANUMEXT, 'Page type.', VALUE_DEFAULT, zones::PAGE_CATALOG),
        ]);
    }

    /**
     * Execute.
     *
     * @param array $items Arranged widget rows.
     * @param string $page Page type.
     * @return array
     */
    public static function execute(array $items = [], string $page = zones::PAGE_CATALOG): array {
        $params = self::validate_parameters(self::execute_parameters(), ['items' => $items, 'page' => $page]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        if (!zones::is_page($params['page'])) {
            throw new invalid_parameter_exception('Unknown page type.');
        }

        $pagezones = zones::for_page($params['page']);
        $globalzones = zones::for_page(zones::PAGE_GLOBAL);
        $validzones = $params['page'] === zones::PAGE_GLOBAL
            ? $globalzones
            : array_merge($pagezones, $globalzones);

        // Order submitted items by zone then sortorder, then re-sequence per zone for clean indices.
        $rows = array_values($params['items']);
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) $a['zone'], (string) $b['zone']) ?: ($a['sortorder'] <=> $b['sortorder']);
        });

        $zonecounter = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $zone = (string) $row['zone'];
            if ($id <= 0 || !in_array($zone, $validzones, true)) {
                continue;
            }
            $w = widget::get_record(['id' => $id]);
            if (!$w) {
                continue;
            }
            $pagetype = (string) $w->get('pagetype');
            if ($pagetype === zones::PAGE_GLOBAL) {
                if (!in_array($zone, $globalzones, true)) {
                    continue;
                }
            } else if ($pagetype !== $params['page'] || !in_array($zone, $pagezones, true)) {
                continue;
            }
            $order = $zonecounter[$zone] ?? 0;
            $zonecounter[$zone] = $order + 1;

            $w->set('zone', $zone);
            $w->set('enabled', !empty($row['enabled']) ? 1 : 0);
            $w->set('sortorder', $order);
            $w->update();
        }

        return [
            'success' => true,
            'message' => get_string('widget_layout_saved', 'local_moderncommerce'),
            'warnings' => [],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the layout was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
