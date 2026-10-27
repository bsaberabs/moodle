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
 * External API for quick product state changes.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\products;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Toggle product flags or status from React admin screens.
 */
class toggle_product extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Product ID.', VALUE_REQUIRED),
            'field' => new external_value(
                PARAM_ALPHANUMEXT,
                'Field to update: visible, featured, status, priceenabled.',
                VALUE_REQUIRED
            ),
            'value' => new external_value(PARAM_TEXT, 'New value.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute quick update.
     *
     * @param int $id Product ID.
     * @param string $field Field name.
     * @param string $value New value.
     * @return array
     */
    public static function execute(int $id, string $field, string $value): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'field' => $field,
            'value' => $value,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $id = (int)$params['id'];
        $field = strtolower((string)$params['field']);
        $value = trim((string)$params['value']);

        $product = $id > 0 ? $DB->get_record('local_moderncommerce_products', ['id' => $id]) : false;
        if (!$product) {
            return [
                'success' => false,
                'productid' => 0,
                'field' => $field,
                'value' => '',
                'message' => get_string('productnotfound', 'local_moderncommerce'),
            ];
        }

        if (in_array($field, ['visible', 'featured'], true)) {
            $boolvalue = self::to_bool($value) ? 1 : 0;
            $DB->update_record('local_moderncommerce_products', (object)[
                'id' => $id,
                $field => $boolvalue,
                'modifiedby' => $USER->id ?? null,
                'timemodified' => time(),
            ]);

            \local_moderncommerce\audit\audit_service::record('product_updated', 'product', $id, [
                'olddata' => [$field => $product->{$field} ?? null],
                'newdata' => [$field => $boolvalue],
            ]);

            return self::success_response($id, $field, (string)$boolvalue);
        }

        if ($field === 'status') {
            $allowed = ['active', 'draft', 'inactive', 'archived'];
            if (!in_array($value, $allowed, true)) {
                return self::invalid_response($id, $field);
            }

            $DB->update_record('local_moderncommerce_products', (object)[
                'id' => $id,
                'status' => $value,
                'modifiedby' => $USER->id ?? null,
                'timemodified' => time(),
            ]);

            \local_moderncommerce\audit\audit_service::record('product_status_changed', 'product', $id, [
                'olddata' => ['status' => $product->status ?? null],
                'newdata' => ['status' => $value],
                'severity' => $value === 'archived' ? 'warning' : 'info',
            ]);

            return self::success_response($id, $field, $value);
        }

        if ($field === 'priceenabled') {
            $prices = $DB->get_records('local_moderncommerce_product_prices', [
                'productid' => $id,
                'pricetype' => 'regular',
            ], 'id ASC', '*', 0, 1);
            $price = $prices ? reset($prices) : false;

            if (!$price) {
                return self::invalid_response($id, $field);
            }

            $oldenabled = (int) $price->enabled;
            $price->enabled = self::to_bool($value) ? 1 : 0;
            $price->timemodified = time();
            $DB->update_record('local_moderncommerce_product_prices', $price);

            \local_moderncommerce\audit\audit_service::record('price_enabled_changed', 'price', (int) $price->id, [
                'olddata' => ['enabled' => $oldenabled, 'productid' => $id],
                'newdata' => ['enabled' => $price->enabled, 'productid' => $id],
            ]);

            return self::success_response($id, $field, (string)$price->enabled);
        }

        return self::invalid_response($id, $field);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether update succeeded.'),
            'productid' => new external_value(PARAM_INT, 'Product ID.'),
            'field' => new external_value(PARAM_ALPHANUMEXT, 'Updated field.'),
            'value' => new external_value(PARAM_TEXT, 'Stored value.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
        ]);
    }

    /**
     * Convert common truthy strings to bool.
     *
     * @param string $value Submitted value.
     * @return bool
     */
    private static function to_bool(string $value): bool {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Success response.
     *
     * @param int $id Product ID.
     * @param string $field Field.
     * @param string $value Value.
     * @return array
     */
    private static function success_response(int $id, string $field, string $value): array {
        return [
            'success' => true,
            'productid' => $id,
            'field' => $field,
            'value' => $value,
            'message' => get_string('productupdated', 'local_moderncommerce'),
        ];
    }

    /**
     * Invalid update response.
     *
     * @param int $id Product ID.
     * @param string $field Field.
     * @return array
     */
    private static function invalid_response(int $id, string $field): array {
        return [
            'success' => false,
            'productid' => $id,
            'field' => $field,
            'value' => '',
            'message' => get_string('invaliddata', 'error'),
        ];
    }
}
