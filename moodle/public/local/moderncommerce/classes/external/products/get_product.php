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
 * External API for reading one catalog product.
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
 * Get one product with editable pricing and inventory fields.
 */
class get_product extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Product ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute product lookup.
     *
     * @param int $id Product ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB;

        ['id' => $id] = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $sql = "SELECT p.id,
                       p.producttype,
                       p.name,
                       p.slug,
                       p.sku,
                       p.status,
                       p.visible,
                       p.featured,
                       p.shortdescription,
                       p.description,
                       p.displayorder,
                       p.taxable,
                       pr.id AS priceid,
                       pr.enabled AS priceenabled,
                       pr.amount AS regularprice,
                       pr.compareamount,
                       inv.stockmanaged,
                       inv.stock,
                       inv.reservedstock,
                       inv.allowbackorder,
                       (SELECT MIN(pc.courseid)
                          FROM {local_moderncommerce_product_courses} pc
                         WHERE pc.productid = p.id
                           AND pc.relationtype = 'included') AS primarycourseid,
                       (SELECT pcm.categoryid
                          FROM {local_moderncommerce_product_category_map} pcm
                         WHERE pcm.productid = p.id
                           AND pcm.id = (
                               SELECT MIN(pcmfirst.id)
                                 FROM {local_moderncommerce_product_category_map} pcmfirst
                                WHERE pcmfirst.productid = p.id
                                  AND pcmfirst.isprimary = (
                                      SELECT MAX(pcmprimary.isprimary)
                                        FROM {local_moderncommerce_product_category_map} pcmprimary
                                       WHERE pcmprimary.productid = p.id
                                  )
                           )) AS primarycategoryid
                  FROM {local_moderncommerce_products} p
             LEFT JOIN {local_moderncommerce_product_prices} pr ON pr.id = (
                           SELECT MIN(prmin.id)
                             FROM {local_moderncommerce_product_prices} prmin
                            WHERE prmin.productid = p.id
                              AND prmin.pricetype = :pricetype
                       )
             LEFT JOIN {local_moderncommerce_product_inventory} inv ON inv.productid = p.id
                 WHERE p.id = :id";
        $record = $DB->get_record_sql($sql, [
            'id' => $id,
            'pricetype' => 'regular',
        ]);

        if (!$record) {
            return self::empty_response(get_string('productnotfound', 'local_moderncommerce'));
        }

        return [
            'success' => true,
            'message' => '',
            'id' => (int)$record->id,
            'producttype' => (string)$record->producttype,
            'name' => format_string($record->name, true, ['context' => $context]),
            'slug' => (string)$record->slug,
            'sku' => (string)$record->sku,
            'status' => (string)$record->status,
            'visible' => !empty($record->visible),
            'featured' => !empty($record->featured),
            'shortdescription' => trim(strip_tags((string)$record->shortdescription)),
            'description' => (string)$record->description,
            'displayorder' => (int)$record->displayorder,
            'taxable' => !empty($record->taxable),
            'primarycategoryid' => $record->primarycategoryid === null ? 0 : (int)$record->primarycategoryid,
            'primarycourseid' => $record->primarycourseid === null ? 0 : (int)$record->primarycourseid,
            'priceid' => $record->priceid === null ? 0 : (int)$record->priceid,
            'priceenabled' => !empty($record->priceenabled),
            'regularprice' => $record->regularprice === null ? 0.0 : (float)$record->regularprice,
            'compareamount' => $record->compareamount === null ? 0.0 : (float)$record->compareamount,
            'stockmanaged' => !empty($record->stockmanaged),
            'stock' => $record->stock === null ? 0 : (int)$record->stock,
            'reservedstock' => $record->reservedstock === null ? 0 : (int)$record->reservedstock,
            'allowbackorder' => !empty($record->allowbackorder),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the product was found.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'id' => new external_value(PARAM_INT, 'Product ID.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
            'name' => new external_value(PARAM_TEXT, 'Product name.'),
            'slug' => new external_value(PARAM_TEXT, 'Product slug.'),
            'sku' => new external_value(PARAM_TEXT, 'Product SKU.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Product status.'),
            'visible' => new external_value(PARAM_BOOL, 'Whether product is visible.'),
            'featured' => new external_value(PARAM_BOOL, 'Whether product is featured.'),
            'shortdescription' => new external_value(PARAM_TEXT, 'Short description.'),
            'description' => new external_value(PARAM_RAW, 'Full description.'),
            'displayorder' => new external_value(PARAM_INT, 'Display order.'),
            'taxable' => new external_value(PARAM_BOOL, 'Whether product is taxable.'),
            'primarycategoryid' => new external_value(PARAM_INT, 'Primary category ID.'),
            'primarycourseid' => new external_value(PARAM_INT, 'Primary course ID.'),
            'priceid' => new external_value(PARAM_INT, 'Regular price row ID.'),
            'priceenabled' => new external_value(PARAM_BOOL, 'Whether regular price is enabled.'),
            'regularprice' => new external_value(PARAM_FLOAT, 'Current regular selling price.'),
            'compareamount' => new external_value(PARAM_FLOAT, 'Compare-at amount.'),
            'stockmanaged' => new external_value(PARAM_BOOL, 'Whether stock is managed.'),
            'stock' => new external_value(PARAM_INT, 'Stock quantity.'),
            'reservedstock' => new external_value(PARAM_INT, 'Reserved stock quantity.'),
            'allowbackorder' => new external_value(PARAM_BOOL, 'Whether backorders are allowed.'),
        ]);
    }

    /**
     * Build an empty not-found response that still matches the return contract.
     *
     * @param string $message Message.
     * @return array
     */
    private static function empty_response(string $message): array {
        return [
            'success' => false,
            'message' => $message,
            'id' => 0,
            'producttype' => '',
            'name' => '',
            'slug' => '',
            'sku' => '',
            'status' => '',
            'visible' => false,
            'featured' => false,
            'shortdescription' => '',
            'description' => '',
            'displayorder' => 0,
            'taxable' => true,
            'primarycategoryid' => 0,
            'primarycourseid' => 0,
            'priceid' => 0,
            'priceenabled' => false,
            'regularprice' => 0.0,
            'compareamount' => 0.0,
            'stockmanaged' => false,
            'stock' => 0,
            'reservedstock' => 0,
            'allowbackorder' => false,
        ];
    }
}
