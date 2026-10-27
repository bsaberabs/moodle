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
 * Canonical product price resolver.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;


/**
 * Resolves the current sellable price for course, bundle, program, and product checkout lines.
 */
class price_resolver {
    /**
     * Resolve a course product price.
     *
     * @param int $courseid Moodle course ID.
     * @param float $quantity Requested quantity.
     * @param bool $requireavailable Require active and visible product.
     * @param int|null $time Effective timestamp.
     * @return \stdClass|null Resolved price snapshot or null.
     */
    public static function resolve_for_course(
        int $courseid,
        float $quantity = 1.0,
        bool $requireavailable = true,
        ?int $time = null
    ): ?\stdClass {
        global $DB;

        $quantity = self::normalise_quantity($quantity);
        $time = $time ?? time();

        $where = [
            'pc.courseid = :courseid',
            'pc.relationtype = :relationtype',
            'p.producttype = :producttype',
        ];
        $params = [
            'courseid' => $courseid,
            'relationtype' => 'included',
            'producttype' => 'course',
        ];

        if ($requireavailable) {
            $where[] = 'p.status = :status';
            $where[] = 'p.visible = :visible';
            $params['status'] = 'active';
            $params['visible'] = 1;
        }

        $sql = self::product_select_sql() . "
                 JOIN {local_moderncommerce_product_courses} pc ON pc.productid = p.id
                WHERE " . implode(' AND ', $where) . "
             ORDER BY CASE WHEN p.status = 'active' THEN 0 ELSE 1 END ASC,
                      p.visible DESC,
                      p.id ASC";

        $records = $DB->get_records_sql($sql, $params, 0, 1);
        if (!$records) {
            return null;
        }

        $product = reset($records);
        $product->courseid = $courseid;

        return self::resolve_product_record($product, $quantity, $requireavailable, $time);
    }

    /**
     * Resolve a product price.
     *
     * @param int $productid Product ID.
     * @param float $quantity Requested quantity.
     * @param bool $requireavailable Require active and visible product.
     * @param int|null $time Effective timestamp.
     * @return \stdClass|null Resolved price snapshot or null.
     */
    public static function resolve_for_product(
        int $productid,
        float $quantity = 1.0,
        bool $requireavailable = true,
        ?int $time = null
    ): ?\stdClass {
        global $DB;

        $quantity = self::normalise_quantity($quantity);
        $time = $time ?? time();

        $where = ['p.id = :productid'];
        $params = ['productid' => $productid];

        if ($requireavailable) {
            $where[] = 'p.status = :status';
            $where[] = 'p.visible = :visible';
            $params['status'] = 'active';
            $params['visible'] = 1;
        }

        $sql = self::product_select_sql() . "
                WHERE " . implode(' AND ', $where);

        $product = $DB->get_record_sql($sql, $params);
        if (!$product) {
            return null;
        }

        if ((string) $product->producttype === 'course') {
            $product->courseid = self::get_primary_course_id((int) $product->id);
        }

        return self::resolve_product_record($product, $quantity, $requireavailable, $time);
    }

    /**
     * Resolve a product record into a price snapshot.
     *
     * @param \stdClass $product Product row with inventory aliases.
     * @param float $quantity Quantity.
     * @param bool $requireavailable Require active and visible product.
     * @param int $time Effective timestamp.
     * @return \stdClass|null Resolved price snapshot or null.
     */
    private static function resolve_product_record(
        \stdClass $product,
        float $quantity,
        bool $requireavailable,
        int $time
    ): ?\stdClass {
        if ($requireavailable && !self::is_product_available($product, $quantity)) {
            return null;
        }

        $prices = self::get_applicable_prices((int) $product->id, (string) $product->producttype, $quantity, $time);
        if (!$prices) {
            return null;
        }

        $price = reset($prices);
        return self::build_snapshot($product, $price, $prices, $quantity);
    }

    /**
     * Get product select SQL with inventory fields.
     *
     * @return string SQL fragment.
     */
    private static function product_select_sql(): string {
        return "SELECT p.id,
                       p.producttype,
                       p.name,
                       p.slug,
                       p.sku,
                       p.status,
                       p.visible,
                       p.featured,
                       p.taxable,
                       p.taxcategory,
                       p.enrolduration,
                       p.maxenrollment,
                       p.currentenrollment,
                       COALESCE(inv.stockmanaged, 0) AS stockmanaged,
                       inv.stock,
                       COALESCE(inv.reservedstock, 0) AS reservedstock,
                       COALESCE(inv.allowbackorder, 0) AS allowbackorder
                  FROM {local_moderncommerce_products} p
             LEFT JOIN {local_moderncommerce_product_inventory} inv ON inv.productid = p.id";
    }

    /**
     * Get applicable prices ordered by checkout priority.
     *
     * @param int $productid Product ID.
     * @param string $producttype Product type.
     * @param float $quantity Requested quantity.
     * @param int $time Effective timestamp.
     * @return array Price rows.
     */
    private static function get_applicable_prices(int $productid, string $producttype, float $quantity, int $time): array {
        global $DB;

        $allowedtypes = self::allowed_price_types($producttype);
        [$insql, $inparams] = $DB->get_in_or_equal($allowedtypes, SQL_PARAMS_NAMED, 'ptype');
        $params = array_merge($inparams, [
            'productid' => $productid,
            'nowstart' => $time,
            'nowend' => $time,
            'quantitymin' => $quantity,
            'quantitymax' => $quantity,
        ]);

        $records = $DB->get_records_sql(
            "SELECT id,
                    productid,
                    pricetype,
                    amount,
                    compareamount,
                    minquantity,
                    maxquantity,
                    startdate,
                    enddate,
                    enabled,
                    timecreated,
                    timemodified
               FROM {local_moderncommerce_product_prices}
              WHERE productid = :productid
                AND enabled = 1
                AND pricetype {$insql}
                AND (startdate IS NULL OR startdate = 0 OR startdate <= :nowstart)
                AND (enddate IS NULL OR enddate = 0 OR enddate >= :nowend)
                AND (minquantity IS NULL OR minquantity <= :quantitymin)
                AND (maxquantity IS NULL OR maxquantity = 0 OR maxquantity >= :quantitymax)",
            $params
        );

        $prices = array_values($records);
        usort($prices, static function (\stdClass $left, \stdClass $right) use ($producttype): int {
            return self::compare_prices($left, $right, $producttype);
        });

        return $prices;
    }

    /**
     * Determine price types that can sell a product type.
     *
     * @param string $producttype Product type.
     * @return array Price types.
     */
    private static function allowed_price_types(string $producttype): array {
        if ($producttype === 'subscription') {
            return ['subscription', 'tier', 'sale', 'regular'];
        }

        return ['tier', 'sale', 'regular'];
    }

    /**
     * Compare prices by priority and customer-facing amount.
     *
     * @param \stdClass $left Left price.
     * @param \stdClass $right Right price.
     * @param string $producttype Product type.
     * @return int Sort result.
     */
    private static function compare_prices(\stdClass $left, \stdClass $right, string $producttype): int {
        $leftrank = self::price_rank($left, $producttype);
        $rightrank = self::price_rank($right, $producttype);
        if ($leftrank !== $rightrank) {
            return $leftrank <=> $rightrank;
        }

        if ((string) $left->pricetype === 'tier' && (string) $right->pricetype === 'tier') {
            $leftmin = empty($left->minquantity) ? 1 : (int) $left->minquantity;
            $rightmin = empty($right->minquantity) ? 1 : (int) $right->minquantity;
            if ($leftmin !== $rightmin) {
                return $rightmin <=> $leftmin;
            }
        }

        $leftamount = (float) $left->amount;
        $rightamount = (float) $right->amount;
        if ($leftamount !== $rightamount) {
            return $leftamount <=> $rightamount;
        }

        return (int) $right->id <=> (int) $left->id;
    }

    /**
     * Get selection rank for a price row.
     *
     * @param \stdClass $price Price row.
     * @param string $producttype Product type.
     * @return int Rank.
     */
    private static function price_rank(\stdClass $price, string $producttype): int {
        $type = (string) $price->pricetype;

        if ($producttype === 'subscription' && $type === 'subscription') {
            return 0;
        }

        if ($type === 'tier') {
            return $producttype === 'subscription' ? 1 : 0;
        }

        if ($type === 'sale' || self::has_compare_discount($price)) {
            return $producttype === 'subscription' ? 2 : 1;
        }

        if ($type === 'regular') {
            return $producttype === 'subscription' ? 3 : 2;
        }

        return 9;
    }

    /**
     * Build the checkout price snapshot.
     *
     * @param \stdClass $product Product row.
     * @param \stdClass $price Selected price row.
     * @param array $allprices All applicable price rows.
     * @param float $quantity Quantity.
     * @return \stdClass Snapshot.
     */
    private static function build_snapshot(
        \stdClass $product,
        \stdClass $price,
        array $allprices,
        float $quantity
    ): \stdClass {
        $currency = pricing_service::get_currency_config()->currency;
        $amount = (float) $price->amount;
        $compareamount = self::get_compare_amount($price, $allprices);
        $onsale = $compareamount > $amount;
        $stockmanaged = !empty($product->stockmanaged);
        $stock = $product->stock === null ? 0 : (int) $product->stock;
        $reservedstock = (int) $product->reservedstock;
        $allowbackorder = !empty($product->allowbackorder);
        $sold = self::get_sold_count((int) $product->id);
        $available = $stockmanaged ? max(0, $stock - $reservedstock - $sold) : PHP_INT_MAX;

        $snapshot = new \stdClass();
        $snapshot->productid = (int) $product->id;
        $snapshot->producttype = (string) $product->producttype;
        $snapshot->productname = (string) $product->name;
        $snapshot->slug = (string) $product->slug;
        $snapshot->sku = (string) $product->sku;
        $snapshot->courseid = empty($product->courseid) ? null : (int) $product->courseid;
        $snapshot->priceid = (int) $price->id;
        $snapshot->pricetype = (string) $price->pricetype;
        $snapshot->quantity = $quantity;
        $snapshot->amount = $amount;
        $snapshot->unitprice = $amount;
        $snapshot->final_price = $amount;
        $snapshot->compareamount = $compareamount;
        $snapshot->regularprice = $onsale ? $compareamount : $amount;
        $snapshot->saleprice = $onsale ? $amount : null;
        $snapshot->currency = $currency;
        $snapshot->enabled = (string) $product->status === 'active'
            && !empty($product->visible)
            && !empty($price->enabled);
        $snapshot->productstatus = (string) $product->status;
        $snapshot->visible = !empty($product->visible);
        $snapshot->featured = !empty($product->featured) ? 1 : 0;
        $snapshot->taxable = !empty($product->taxable) ? 1 : 0;
        $snapshot->taxcategory = $product->taxcategory ?? null;
        $snapshot->enrolduration = empty($product->enrolduration) ? null : (int) $product->enrolduration;
        $snapshot->minquantity = empty($price->minquantity) ? 1 : (int) $price->minquantity;
        $snapshot->maxquantity = empty($price->maxquantity) ? null : (int) $price->maxquantity;
        $snapshot->startdate = empty($price->startdate) ? null : (int) $price->startdate;
        $snapshot->enddate = empty($price->enddate) ? null : (int) $price->enddate;
        $snapshot->stockmanaged = $stockmanaged ? 1 : 0;
        $snapshot->stock = $stock;
        $snapshot->reservedstock = $reservedstock;
        $snapshot->allowbackorder = $allowbackorder ? 1 : 0;
        $snapshot->sold = $sold;
        $snapshot->available = $available;
        $snapshot->has_stock = !$stockmanaged || $allowbackorder || $available >= $quantity;
        $snapshot->is_unlimited = !$stockmanaged;
        $snapshot->has_sale = $onsale;
        $snapshot->on_sale = $onsale;
        $snapshot->discount_amount = $onsale ? ($compareamount - $amount) : 0;
        $snapshot->discount_percentage = $onsale && $compareamount > 0
            ? round((($compareamount - $amount) / $compareamount) * 100)
            : 0;
        $snapshot->is_free = $amount == 0.0;

        return $snapshot;
    }

    /**
     * Determine compare-at amount for the selected row.
     *
     * @param \stdClass $selected Selected price row.
     * @param array $allprices Applicable price rows.
     * @return float Compare amount.
     */
    private static function get_compare_amount(\stdClass $selected, array $allprices): float {
        if (self::has_compare_discount($selected)) {
            return (float) $selected->compareamount;
        }

        if ((string) $selected->pricetype !== 'sale') {
            return 0.0;
        }

        foreach ($allprices as $price) {
            if ((string) $price->pricetype !== 'regular') {
                continue;
            }

            $amount = (float) $price->amount;
            if ($amount > (float) $selected->amount) {
                return $amount;
            }
        }

        return 0.0;
    }

    /**
     * Check if a price carries a compare-at discount.
     *
     * @param \stdClass $price Price row.
     * @return bool
     */
    private static function has_compare_discount(\stdClass $price): bool {
        return $price->compareamount !== null && (float) $price->compareamount > (float) $price->amount;
    }

    /**
     * Check whether a product can be sold.
     *
     * @param \stdClass $product Product row.
     * @param float $quantity Requested quantity.
     * @return bool
     */
    private static function is_product_available(\stdClass $product, float $quantity): bool {
        if ((string) $product->status !== 'active' || empty($product->visible)) {
            return false;
        }

        if (empty($product->stockmanaged) || !empty($product->allowbackorder)) {
            return true;
        }

        $stock = $product->stock === null ? 0 : (int) $product->stock;
        $reservedstock = (int) $product->reservedstock;
        $available = max(0, $stock - $reservedstock - self::get_sold_count((int) $product->id));

        return $available >= $quantity;
    }

    /**
     * Get the primary included course for a course product.
     *
     * @param int $productid Product ID.
     * @return int|null Course ID.
     */
    private static function get_primary_course_id(int $productid): ?int {
        global $DB;

        $records = $DB->get_records('local_moderncommerce_product_courses', [
            'productid' => $productid,
            'relationtype' => 'included',
        ], 'sortorder ASC, id ASC', 'id, courseid', 0, 1);

        if (!$records) {
            return null;
        }

        $record = reset($records);
        return (int) $record->courseid;
    }

    /**
     * Count paid/completed sold quantity for a product.
     *
     * @param int $productid Product ID.
     * @return int Sold quantity.
     */
    private static function get_sold_count(int $productid): int {
        global $DB;

        return (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(oi.quantity), 0)
               FROM {local_moderncommerce_order_items} oi
               JOIN {local_moderncommerce_orders} o ON o.id = oi.orderid
              WHERE oi.productid = :productid
                AND o.status IN ('paid', 'completed')",
            ['productid' => $productid]
        );
    }

    /**
     * Normalise quantity to a positive line quantity.
     *
     * @param float $quantity Quantity.
     * @return float Normalised quantity.
     */
    private static function normalise_quantity(float $quantity): float {
        return max(1.0, $quantity);
    }
}
