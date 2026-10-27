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
 * External API checking whether a custom store page slug is available.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\storefront;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\storefront\storepage_service;
use moodle_url;

/**
 * Validates a proposed slug so the admin form can report a clash before saving.
 */
class check_store_page_slug extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'slug' => new external_value(PARAM_RAW, 'Proposed slug, normalised server-side.'),
            'excludeid' => new external_value(PARAM_INT, 'Page id to ignore, when editing.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $slug Proposed slug.
     * @param int $excludeid Page id to ignore.
     * @return array
     */
    public static function execute(string $slug, int $excludeid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'slug' => $slug,
            'excludeid' => $excludeid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $normalised = storepage_service::normalise_slug($params['slug']);
        $error = storepage_service::validate_slug($normalised, (int)$params['excludeid']);

        return [
            'slug' => $normalised,
            'available' => $error === '',
            'message' => $error === '' ? '' : get_string($error, 'local_moderncommerce'),
            'url' => (new moodle_url('/local/moderncommerce/page.php', ['slug' => $normalised]))->out(false),
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
            'slug' => new external_value(PARAM_TEXT, 'The normalised slug.'),
            'available' => new external_value(PARAM_BOOL, 'Whether the slug can be used.'),
            'message' => new external_value(PARAM_TEXT, 'Reason the slug was rejected, if any.'),
            'url' => new external_value(PARAM_URL, 'Public URL the slug would produce.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
