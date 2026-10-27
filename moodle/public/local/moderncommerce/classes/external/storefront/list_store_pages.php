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
 * External API listing every Modern Commerce public store page.
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
use moodle_url;

/**
 * Returns the built-in and admin-created public pages for the admin page manager.
 */
class list_store_pages extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute.
     *
     * @return array
     */
    public static function execute(): array {
        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $pages = [];
        foreach (public_page::page_catalog() as $key => $page) {
            $pages[] = [
                'id' => (int) ($page['id'] ?? 0),
                'key' => $key,
                'title' => format_string(public_page::setting($key, 'title', $page['title'])),
                'summary' => format_string(public_page::setting($key, 'summary', $page['summary'] ?? '')),
                'icon' => preg_replace('/[^a-z0-9 -]/i', '', (string) ($page['icon'] ?? 'bi-file-text')),
                'url' => (new moodle_url($page['route'], $page['params'] ?? []))->out(false),
                'enabled' => public_page::is_enabled($key),
                'required' => !public_page::can_disable($key),
                'custom' => !empty($page['custom']),
                'template' => (string) ($page['template'] ?? ''),
            ];
        }

        return [
            'pages' => $pages,
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
            'pages' => new external_multiple_structure(self::page_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Public page structure.
     *
     * @return external_single_structure
     */
    public static function page_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Store page id, or 0 for a built-in page.'),
            'key' => new external_value(PARAM_ALPHANUMEXT, 'Page key or slug.'),
            'title' => new external_value(PARAM_TEXT, 'Page title.'),
            'summary' => new external_value(PARAM_TEXT, 'Page summary.'),
            'icon' => new external_value(PARAM_TEXT, 'Bootstrap icon class.'),
            'url' => new external_value(PARAM_URL, 'Public page URL.'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether the page is visible to buyers.'),
            'required' => new external_value(PARAM_BOOL, 'Whether the page cannot be disabled.'),
            'custom' => new external_value(PARAM_BOOL, 'Whether an administrator created this page.'),
            'template' => new external_value(PARAM_TEXT, 'Zone template for custom pages.'),
        ]);
    }
}
