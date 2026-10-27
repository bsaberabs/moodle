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
 * External API returning the editable storefront widget layout (manager drawer).
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
use local_moderncommerce\storefront\widget_types;
use local_moderncommerce\storefront\zones;

/**
 * Returns every storefront widget with its zone/order/enabled state, plus the zone and
 * addable-type catalogs, for the in-page Customize drawer.
 */
class get_storefront_layout extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page' => new external_value(PARAM_ALPHANUMEXT, 'Page type.', VALUE_DEFAULT, zones::PAGE_CATALOG),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $page Page type.
     * @return array
     */
    public static function execute(string $page = zones::PAGE_CATALOG): array {
        $params = self::validate_parameters(self::execute_parameters(), ['page' => $page]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        if (!zones::is_page($params['page'])) {
            throw new invalid_parameter_exception('Unknown page type.');
        }

        $pagezones = zones::for_page($params['page']);
        $globalzones = zones::for_page(zones::PAGE_GLOBAL);
        $renderorder = $params['page'] === zones::PAGE_GLOBAL
            ? $globalzones
            : array_merge([zones::GLOBAL_TOP], $pagezones, [zones::GLOBAL_BOTTOM]);
        $orderindex = array_flip($renderorder);

        $records = widget::get_records(['pagetype' => $params['page']]);
        if ($params['page'] !== zones::PAGE_GLOBAL) {
            $records = array_merge($records, widget::get_records(['pagetype' => zones::PAGE_GLOBAL]));
        }
        $widgets = [];
        foreach ($records as $w) {
            $pagetype = (string) $w->get('pagetype');
            $widgets[] = [
                'id' => (int) $w->get('id'),
                'type' => (string) $w->get('type'),
                'typelabel' => widget_types::label((string) $w->get('type')),
                'zone' => (string) $w->get('zone'),
                'enabled' => (bool) $w->get('enabled'),
                'sortorder' => (int) $w->get('sortorder'),
                'title' => (string) $w->get('title'),
                'pagetype' => $pagetype,
                'scope' => $pagetype === zones::PAGE_GLOBAL ? 'global' : 'page',
            ];
        }

        // Order by zone render position, then sortorder.
        usort($widgets, static function (array $a, array $b) use ($orderindex): int {
            $za = $orderindex[$a['zone']] ?? 999;
            $zb = $orderindex[$b['zone']] ?? 999;
            return $za <=> $zb ?: ($a['sortorder'] <=> $b['sortorder']);
        });

        $zonelist = [];
        foreach ($renderorder as $slug) {
            $zonelist[] = ['slug' => $slug, 'label' => zones::zone_label($slug)];
        }

        $typelist = [];
        foreach (widget_types::all() as $type) {
            $typelist[] = ['key' => $type, 'label' => widget_types::label($type)];
        }

        return [
            'widgets' => $widgets,
            'zones' => $zonelist,
            'types' => $typelist,
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
            'widgets' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Widget id.'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'Widget type key.'),
                'typelabel' => new external_value(PARAM_TEXT, 'Widget type label.'),
                'zone' => new external_value(PARAM_ALPHANUMEXT, 'Zone slug.'),
                'enabled' => new external_value(PARAM_BOOL, 'Whether the widget is shown.'),
                'sortorder' => new external_value(PARAM_INT, 'Order within the zone.'),
                'title' => new external_value(PARAM_TEXT, 'Widget title.'),
                'pagetype' => new external_value(PARAM_ALPHANUMEXT, 'Widget page scope.'),
                'scope' => new external_value(PARAM_ALPHANUMEXT, 'Layout scope: page or global.'),
            ])),
            'zones' => new external_multiple_structure(new external_single_structure([
                'slug' => new external_value(PARAM_ALPHANUMEXT, 'Zone slug.'),
                'label' => new external_value(PARAM_TEXT, 'Zone label.'),
            ])),
            'types' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'Widget type key.'),
                'label' => new external_value(PARAM_TEXT, 'Widget type label.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }
}
