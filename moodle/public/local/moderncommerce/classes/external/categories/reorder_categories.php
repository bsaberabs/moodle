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
 * External API for reordering Modern Commerce product categories.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\categories;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\services\category_service;

/**
 * Persist a drag-and-drop display order for product categories.
 */
class reorder_categories extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Category ID.'),
                'Category IDs in the desired display order.'
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int[] $ids Category IDs in display order.
     * @return array
     */
    public static function execute(array $ids): array {
        ['ids' => $ids] = self::validate_parameters(self::execute_parameters(), ['ids' => $ids]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecategories', $context);

        return category_service::reorder_categories($ids);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the order was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
        ]);
    }
}
