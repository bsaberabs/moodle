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
 * External API for creating or updating a Modern Commerce product category.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\categories;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\external\field_errors_trait;
use local_moderncommerce\services\category_service;

/**
 * Create or update a product category.
 */
class save_category extends external_api {
    use field_errors_trait;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Category ID, or 0 to create.', VALUE_DEFAULT, 0),
            // Trimmed so a whitespace-only name is rejected as empty rather than
            // being accepted as a present value.
            'name' => new external_value(PARAM_RAW_TRIMMED, 'Category name.', VALUE_REQUIRED),
            'description' => new external_value(PARAM_TEXT, 'Category description.', VALUE_DEFAULT, ''),
            'visible' => new external_value(PARAM_BOOL, 'Whether the category is visible.', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Category ID.
     * @param string $name Name.
     * @param string $description Description.
     * @param bool $visible Visible flag.
     * @return array
     */
    public static function execute(
        int $id = 0,
        string $name = '',
        string $description = '',
        bool $visible = true
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'visible' => $visible,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecategories', $context);

        [$normalised, $errors] = category_service::validate_category($params, (int) $params['id']);

        if (!empty($errors)) {
            $response = self::field_errors_response($errors);
            $response['id'] = 0;
            return $response;
        }

        $result = category_service::save_category(
            (int) $normalised['id'],
            (string) $normalised['name'],
            (string) $normalised['description'],
            (bool) $normalised['visible']
        );

        return $result + ['errors' => [], 'warnings' => []];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the category was saved.'),
            'id' => new external_value(PARAM_INT, 'Category ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'errors' => self::field_errors_structure(),
            'warnings' => new external_warnings(),
        ]);
    }
}
