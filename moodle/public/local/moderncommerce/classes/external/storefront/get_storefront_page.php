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
 * External API returning the resolved storefront widgets for a page (public).
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
use local_moderncommerce\output\public_page;
use local_moderncommerce\storefront\page_builder;

/**
 * Returns the resolved storefront widgets, grouped by zone, for the React landing page.
 */
class get_storefront_page extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page' => new external_value(PARAM_ALPHANUMEXT, 'Page type, e.g. catalog.', VALUE_DEFAULT, 'catalog'),
            'zone' => new external_value(PARAM_ALPHANUMEXT, 'Optional single zone slug.', VALUE_DEFAULT, ''),
            'context' => new external_value(PARAM_RAW, 'JSON object of context ids.', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $page Page type.
     * @param string $zone Optional single zone slug.
     * @param string $context JSON context object.
     * @return array
     */
    public static function execute(string $page = 'catalog', string $zone = '', string $context = '{}'): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'page' => $page,
            'zone' => $zone,
            'context' => $context,
        ]);

        $systemcontext = context_system::instance();
        self::validate_public_context($systemcontext);

        $isloggedin = isloggedin() && !isguestuser();
        $contextids = self::parse_context($params['context']);

        // An unpublished page serves no content to visitors. Managers still get the
        // widgets so they can build the page before publishing it. Only navigable pages
        // are gated: the site-wide 'global' scope is not in the page catalog and must
        // keep rendering its bands.
        if (
            array_key_exists($params['page'], public_page::page_catalog())
            && !public_page::is_enabled($params['page'])
            && !has_capability('local/moderncommerce:managestorefront', $systemcontext)
        ) {
            return [
                'zones' => [],
                'warnings' => [],
            ];
        }

        $zones = page_builder::build($params['page'], $contextids, $params['zone'], $isloggedin);

        return [
            'zones' => $zones,
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
            'zones' => new external_multiple_structure(new external_single_structure([
                'slug' => new external_value(PARAM_ALPHANUMEXT, 'Zone slug.'),
                'widgets' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Widget instance ID.'),
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

    /**
     * Validate access without forcing anonymous visitors to log in (mirrors the catalog).
     *
     * @param context_system $context System context.
     */
    private static function validate_public_context(context_system $context): void {
        global $PAGE;

        if (isloggedin() && !isguestuser()) {
            self::validate_context($context);
            require_capability('local/moderncommerce:viewcatalog', $context);
            return;
        }

        // This endpoint is the read-only data source for the public storefront.
        // Do not depend on the site's guest-role capability assignment here: that
        // assignment may be missing on upgraded sites and Moodle then reports the
        // public AJAX request as login-required. Editing and all write endpoints
        // remain protected by login and the managestorefront capability.
        $PAGE->set_context($context);
    }

    /**
     * Parse the JSON context object into a sanitised scalar array.
     *
     * Numeric values are kept as integers for older resolver contexts. String
     * values are used for page-aware global widgets, for example hostpage.
     *
     * @param string $json Raw JSON context string.
     * @return array<string, int|string>
     */
    private static function parse_context(string $json): array {
        if (trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $clean = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $cleankey = clean_param($key, PARAM_ALPHANUMEXT);
            if ($cleankey === '') {
                continue;
            }
            if (is_int($value) || (is_string($value) && is_numeric($value))) {
                $clean[$cleankey] = (int)$value;
                continue;
            }
            if (is_string($value)) {
                $clean[$cleankey] = clean_param($value, PARAM_ALPHANUMEXT);
            }
        }

        return $clean;
    }
}
