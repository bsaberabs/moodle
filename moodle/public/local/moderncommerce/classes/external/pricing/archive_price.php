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
 * External API for archiving product price rows.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\pricing;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Disable a price row while keeping historical references intact.
 */
class archive_price extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Price row ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Archive a product price.
     *
     * @param int $id Price row ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB;

        ['id' => $id] = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        if ($id <= 0) {
            return self::failure(get_string('pricenotfound', 'local_moderncommerce'));
        }

        $price = $DB->get_record('local_moderncommerce_product_prices', ['id' => $id]);
        if (!$price) {
            return self::failure(get_string('pricenotfound', 'local_moderncommerce'));
        }

        $DB->update_record('local_moderncommerce_product_prices', (object) [
            'id' => $id,
            'enabled' => 0,
            'timemodified' => time(),
        ]);

        \local_moderncommerce\audit\audit_service::record('price_archived', 'price', $id, [
            'olddata' => $price,
            'newdata' => [
                'enabled' => 0,
                'productid' => (int) $price->productid,
            ],
            'severity' => 'warning',
        ]);

        return [
            'success' => true,
            'priceid' => $id,
            'productid' => (int) $price->productid,
            'message' => get_string('pricearchived', 'local_moderncommerce'),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether archive succeeded.'),
            'priceid' => new external_value(PARAM_INT, 'Price row ID.'),
            'productid' => new external_value(PARAM_INT, 'Product ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
        ]);
    }

    /**
     * Failure response.
     *
     * @param string $message Result message.
     * @return array
     */
    private static function failure(string $message): array {
        return [
            'success' => false,
            'priceid' => 0,
            'productid' => 0,
            'message' => $message,
        ];
    }
}
