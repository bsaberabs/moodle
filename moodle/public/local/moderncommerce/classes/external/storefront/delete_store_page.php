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
 * External API deleting a custom Modern Commerce store page.
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
use local_moderncommerce\event\store_page_deleted;
use local_moderncommerce\persistent\storepage;
use local_moderncommerce\storefront\storepage_service;

/**
 * Deletes a custom public page along with every widget placed on it.
 */
class delete_store_page extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Store page id.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Store page id.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $record = $DB->get_record(storepage::TABLE, ['id' => (int) $params['id']], '*', MUST_EXIST);
        $slug = (string) $record->slug;

        $transaction = $DB->start_delegated_transaction();

        storepage_service::delete_page_widgets($slug);
        storepage_service::delete_page_config($slug);
        $DB->delete_records(storepage::TABLE, ['id' => (int) $record->id]);

        $transaction->allow_commit();

        storepage_service::purge_cache();

        store_page_deleted::create([
            'objectid' => (int) $record->id,
            'context' => $context,
            'other' => ['slug' => $slug],
        ])->trigger();

        return [
            'success' => true,
            'message' => get_string('storepage_deleted', 'local_moderncommerce'),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the page was deleted.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
