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
 * External API returning the synthetic "every widget, every style" gallery (admin only).
 *
 * Returns the same zone/widget envelope as {@see get_storefront_page} so the existing
 * React storefront component can render the gallery with no client changes. The single
 * "page" param is unused (always the gallery); the "zone" param is reused as an optional
 * widget-type filter so the admin gallery page can mount one widget type per region.
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
use local_moderncommerce\storefront\gallery_builder;

/**
 * Returns the resolved demo gallery widgets, grouped by widget type, for admins.
 */
class get_storefront_gallery extends external_api {
    /**
     * Parameters (shape-compatible with get_storefront_page so the same React app can call it).
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page' => new external_value(PARAM_ALPHANUMEXT, 'Unused; always the gallery.', VALUE_DEFAULT, 'gallery'),
            'zone' => new external_value(PARAM_ALPHANUMEXT, 'Optional single widget type to build.', VALUE_DEFAULT, ''),
            'context' => new external_value(PARAM_RAW, 'Unused JSON context.', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $page Unused.
     * @param string $zone Optional single widget type filter.
     * @param string $context Unused JSON context.
     * @return array
     */
    public static function execute(string $page = 'gallery', string $zone = '', string $context = '{}'): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'page' => $page,
            'zone' => $zone,
            'context' => $context,
        ]);

        $systemcontext = context_system::instance();
        self::validate_context($systemcontext);
        require_capability('local/moderncommerce:managestorefront', $systemcontext);

        return [
            'zones' => gallery_builder::build($params['zone']),
            'warnings' => [],
        ];
    }

    /**
     * Returns (identical to get_storefront_page).
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'zones' => new external_multiple_structure(new external_single_structure([
                'slug' => new external_value(PARAM_ALPHANUMEXT, 'Zone slug (widget type).'),
                'widgets' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Synthetic widget id.'),
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Widget type key.'),
                    'sortorder' => new external_value(PARAM_INT, 'Order within the zone.'),
                    'title' => new external_value(PARAM_TEXT, 'Optional widget title.'),
                    'subtitle' => new external_value(PARAM_TEXT, 'Optional widget subtitle.'),
                    'settings' => new external_value(PARAM_RAW, 'Type-specific config as a JSON string.'),
                    'styleconfig' => new external_value(PARAM_RAW, 'Universal style config as a JSON string.'),
                    'data' => new external_value(PARAM_RAW, 'Resolved render payload as a JSON string.'),
                    'bg' => new external_value(PARAM_TEXT, 'Optional band background colour.'),
                    'spacingtop' => new external_value(PARAM_INT, 'Top spacing in pixels.'),
                    'spacingbottom' => new external_value(PARAM_INT, 'Bottom spacing in pixels.'),
                ])),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }
}
