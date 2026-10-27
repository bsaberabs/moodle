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
 * Pricing API for Modern Commerce
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\api;
use local_moderncommerce\services\pricing_service;

/**
 * Pricing API facade.
 */
class pricing_api {
    /**
     * Get the configured currency
     *
     * @return string Currency code
     */
    public static function get_currency() {

        $config = pricing_service::get_currency_config();
        return $config->currency;
    }
    /**
     * Get course price information
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_price($courseid) {

        $pricing = pricing_service::get_course_pricing((int) $courseid);
        if (!$pricing) {
            return [
                'enabled' => false,
                'price' => 0,
                'sale_price' => 0,
                'effective_price' => 0,
                'currency' => self::get_currency(),
                'on_sale' => false,
                'taxable' => true,
                'stock_managed' => false,
                'stock' => 0,
                'in_stock' => true,
                'featured' => false,
                'productid' => 0,
                'priceid' => 0,
            ];
        }

        $stock = pricing_service::get_stock_info((int) $courseid);
        return [
            'enabled' => (bool)$pricing->enabled,
            'price' => (float)$pricing->price,
            'sale_price' => $pricing->saleprice === null ? 0 : (float)$pricing->saleprice,
            'effective_price' => (float)$pricing->final_price,
            'currency' => $pricing->currency,
            'on_sale' => (bool)$pricing->has_sale,
            'taxable' => (bool)$pricing->taxable,
            'stock_managed' => (bool)$pricing->stockmanaged,
            'stock' => $pricing->stock,
            'in_stock' => (bool)$stock->is_in_stock,
            'featured' => (bool)$pricing->featured,
            'productid' => (int)$pricing->productid,
            'priceid' => (int)$pricing->priceid,
        ];
    }
    /**
     * Calculate cart totals
     *
     * @param array $cartitems
     * @param string|null $couponcode
     * @param bool $strictcoupon Throw when a submitted coupon is invalid.
     * @return array
     */
    public static function calculate_cart_totals($cartitems, $couponcode = null, bool $strictcoupon = false) {

        $subtotal = 0;
        $discount = 0;
        $tax = 0;
        $taxrate = 0;
        $coupon = null;
        $couponerror = '';
        // Calculate subtotal from cart/order line snapshots.
        foreach ($cartitems as $item) {
            $unitprice = isset($item->unitprice) ? (float) $item->unitprice : (float) ($item->price ?? 0);
            $quantity = isset($item->quantity) ? max(1, (float) $item->quantity) : 1;
            $subtotal += $unitprice * $quantity;
        }
        // Apply coupon if provided.
        if ($couponcode) {
            $validation = coupon_api::validate_coupon_result($couponcode, $cartitems);
            if ($validation['valid']) {
                $coupon = $validation['coupon'];
                $discount = coupon_api::calculate_discount($coupon, $subtotal);
            } else {
                $couponerror = $validation['message'];
                if ($strictcoupon) {
                    throw new \moodle_exception($validation['messageid'], 'local_moderncommerce', '', $validation['a']);
                }
            }
        }
        // Calculate tax only if enabled in settings.
        $taxenabled = get_config('local_moderncommerce', 'enable_tax');
        if ($taxenabled) {
            $defaulttaxrate = get_config('local_moderncommerce', 'default_tax_rate') / 100;
            $taxableamount = $subtotal - $discount;
            $tax = $taxableamount * $defaulttaxrate;
            $taxrate = $defaulttaxrate;
        }

        $total = $subtotal - $discount + $tax;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'taxrate' => $taxrate,
            'total' => $total,
            'currency' => self::get_currency(),
            'coupon' => $coupon,
            'couponerror' => $couponerror,
        ];
    }
    /**
     * Set course pricing
     *
     * @param int $courseid
     * @param array $pricingdata
     * @return bool
     */
    public static function set_course_pricing($courseid, $pricingdata) {

        global $DB;
        $courseid = (int)$courseid;
        $pricingdata = self::normalise_pricing_data($pricingdata);
        if (
            $courseid <= 0 || !$DB->record_exists_select('course', 'id = :courseid AND id <> :siteid', [
            'courseid' => $courseid, 'siteid' => SITEID,
            ])
        ) {
            throw new \moodle_exception('error:invalidcourse', 'local_moderncommerce');
        }

        $transaction = $DB->start_delegated_transaction();
        $product = self::get_or_create_course_product($courseid, $pricingdata);
        self::update_course_product($product, $pricingdata);
        self::save_product_price((int)$product->id, $pricingdata);
        self::save_product_inventory((int)$product->id, $pricingdata);
        $transaction->allow_commit();
        return true;
    }
    /**
     * Get priced courses
     *
     * @param array $filters
     * @return array
     */
    public static function get_priced_courses($filters = []) {

        global $DB;
        $params = [
            'pricetype' => 'regular',
            'enabled' => 1,
            'status' => 'active',
            'visible' => 1,
            'relationtype' => 'included',
            'producttype' => 'course',
        ];
        $where = [
            'pr.enabled = :enabled',
            'p.status = :status',
            'p.visible = :visible',
            'pc.relationtype = :relationtype',
            'p.producttype = :producttype',
            'c.visible = 1',
            'c.id <> :siteid',
        ];
        $params['siteid'] = SITEID;
        if (!empty($filters['featured'])) {
            $where[] = 'p.featured = 1';
        }

        if (!empty($filters['category'])) {
            $where[] = 'c.category = :category';
            $params['category'] = $filters['category'];
        }

        $sql = "SELECT c.id as courseid, c.fullname, c.summary, c.category,
                       p.id AS productid, pr.amount AS price, pr.compareamount,
                       p.featured, inv.stock,
                       COALESCE((SELECT SUM(oi.quantity)
                                   FROM {local_moderncommerce_order_items} oi
                                   JOIN {local_moderncommerce_orders} o ON o.id = oi.orderid
                                  WHERE oi.productid = p.id
                                    AND o.status IN ('paid', 'completed')), 0) as sold
                FROM {course} c
                JOIN {local_moderncommerce_product_courses} pc ON pc.courseid = c.id
                JOIN {local_moderncommerce_products} p ON p.id = pc.productid
                JOIN {local_moderncommerce_product_prices} pr ON pr.productid = p.id
                                                               AND pr.pricetype = :pricetype
                LEFT JOIN {local_moderncommerce_product_inventory} inv ON inv.productid = p.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.featured DESC, c.fullname ASC";
        $courses = $DB->get_records_sql($sql, $params);

        // Add calculated pricing info.
        foreach ($courses as $course) {
            $pricing = self::get_course_price($course->courseid);
            $course->pricing = $pricing;
        }
        return $courses;
    }

    /**
     * Normalise legacy pricing payload keys into canonical product price data.
     *
     * @param array $pricingdata Submitted pricing data.
     * @return array
     */
    private static function normalise_pricing_data(array $pricingdata): array {

        $price = isset($pricingdata['price']) ? (float)$pricingdata['price'] : 0.0;
        $saleprice = $pricingdata['saleprice'] ?? ($pricingdata['sale_price'] ?? null);
        $saleprice = ($saleprice === null || $saleprice === '') ? null : (float)$saleprice;
        if ($price < 0) {
            $price = 0.0;
        }
        if ($saleprice !== null && ($saleprice < 0 || $saleprice >= $price)) {
            $saleprice = null;
        }

        return [
            'price' => $price,
            'saleprice' => $saleprice,
            'enabled' => array_key_exists('enabled', $pricingdata) ? !empty($pricingdata['enabled']) : true,
            'featured' => !empty($pricingdata['featured']),
            'taxable' => array_key_exists('taxable', $pricingdata) ? !empty($pricingdata['taxable']) : true,
            'stockmanaged' => !empty($pricingdata['stockmanaged']) || !empty($pricingdata['stock_managed']),
            'stock' => isset($pricingdata['stock']) ? max(0, (int)$pricingdata['stock']) : 0,
            'allowbackorder' => !empty($pricingdata['allowbackorder']) || !empty($pricingdata['allow_backorder']),
            'salestartdate' => empty($pricingdata['salestartdate']) ? null : (int)$pricingdata['salestartdate'],
            'saleenddate' => empty($pricingdata['saleenddate']) ? null : (int)$pricingdata['saleenddate'],
        ];
    }

    /**
     * Get or create the canonical course product for a Moodle course.
     *
     * @param int $courseid Course ID.
     * @param array $pricingdata Normalised pricing data.
     * @return \stdClass Product record.
     */
    private static function get_or_create_course_product(int $courseid, array $pricingdata): \stdClass {

        global $DB, $USER;
        $sql = "SELECT p.*
                  FROM {local_moderncommerce_product_courses} pc
                  JOIN {local_moderncommerce_products} p ON p.id = pc.productid
                 WHERE pc.courseid = :courseid
                   AND pc.relationtype = :relationtype
                   AND p.producttype = :producttype
              ORDER BY CASE WHEN p.status = 'active' THEN 0 ELSE 1 END ASC,
                       p.visible DESC,
                       p.id ASC";
        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid, 'relationtype' => 'included', 'producttype' => 'course',
        ], 0, 1);
        if ($records) {
            return reset($records);
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $now = time();
        $record = (object)[
            'producttype' => 'course',
            'name' => $course->fullname,
            'slug' => self::unique_product_field('slug', self::slugify($course->shortname ?: $course->fullname)),
            'sku' => self::unique_product_field('sku', 'COURSE-' . $courseid),
            'status' => $pricingdata['enabled'] ? 'active' : 'draft',
            'visible' => $pricingdata['enabled'] ? 1 : 0,
            'featured' => $pricingdata['featured'] ? 1 : 0,
            'shortdescription' => trim(strip_tags((string)$course->summary)),
            'description' => (string)$course->summary,
            'imageurl' => null,
            'taxable' => $pricingdata['taxable'] ? 1 : 0,
            'taxcategory' => 'standard',
            'enrolduration' => 0,
            'maxenrollment' => null,
            'currentenrollment' => 0,
            'displayorder' => 0,
            'createdby' => $USER->id ?? null,
            'modifiedby' => $USER->id ?? null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $productid = (int)$DB->insert_record('local_moderncommerce_products', $record);
        $DB->insert_record('local_moderncommerce_product_courses', (object)[
            'productid' => $productid,
            'courseid' => $courseid,
            'relationtype' => 'included',
            'sortorder' => 10,
            'required' => 1,
            'timecreated' => $now,
        ]);
        return $DB->get_record('local_moderncommerce_products', ['id' => $productid], '*', MUST_EXIST);
    }

    /**
     * Update product-level flags managed by legacy pricing UI.
     *
     * @param \stdClass $product Product record.
     * @param array $pricingdata Normalised pricing data.
     */
    private static function update_course_product(\stdClass $product, array $pricingdata): void {

        global $DB, $USER;
        $DB->update_record('local_moderncommerce_products', (object)[
            'id' => $product->id,
            'status' => $pricingdata['enabled'] ? 'active' : (string)$product->status,
            'visible' => $pricingdata['enabled'] ? 1 : (int)$product->visible,
            'featured' => $pricingdata['featured'] ? 1 : 0,
            'taxable' => $pricingdata['taxable'] ? 1 : 0,
            'modifiedby' => $USER->id ?? null,
            'timemodified' => time(),
        ]);
    }

    /**
     * Save the regular product price row.
     *
     * @param int $productid Product ID.
     * @param array $pricingdata Normalised pricing data.
     */
    private static function save_product_price(int $productid, array $pricingdata): void {

        global $DB;
        $now = time();
        $hassale = $pricingdata['saleprice'] !== null;
        $record = (object)[
            'productid' => $productid,
            'pricetype' => 'regular',
            'amount' => $hassale ? $pricingdata['saleprice'] : $pricingdata['price'],
            'compareamount' => $hassale ? $pricingdata['price'] : null,
            'minquantity' => 1,
            'maxquantity' => null,
            'startdate' => $hassale ? $pricingdata['salestartdate'] : null,
            'enddate' => $hassale ? $pricingdata['saleenddate'] : null,
            'enabled' => $pricingdata['enabled'] ? 1 : 0,
            'timemodified' => $now,
        ];
        $records = $DB->get_records('local_moderncommerce_product_prices', [
            'productid' => $productid, 'pricetype' => 'regular',
        ], 'id ASC', '*', 0, 1);
        $existing = $records ? reset($records) : false;
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_moderncommerce_product_prices', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_moderncommerce_product_prices', $record);
        }
    }

    /**
     * Save inventory state for compatibility with legacy pricing forms.
     *
     * @param int $productid Product ID.
     * @param array $pricingdata Normalised pricing data.
     */
    private static function save_product_inventory(int $productid, array $pricingdata): void {

        global $DB;
        $existing = $DB->get_record('local_moderncommerce_product_inventory', ['productid' => $productid]);
        $record = (object)[
            'productid' => $productid,
            'stockmanaged' => $pricingdata['stockmanaged'] ? 1 : 0,
            'stock' => $pricingdata['stockmanaged'] ? $pricingdata['stock'] : null,
            'reservedstock' => $existing ? (int)$existing->reservedstock : 0,
            'allowbackorder' => $pricingdata['allowbackorder'] ? 1 : 0,
            'timemodified' => time(),
        ];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_moderncommerce_product_inventory', $record);
        } else {
            $DB->insert_record('local_moderncommerce_product_inventory', $record);
        }
    }

    /**
     * Generate a URL-safe slug.
     *
     * @param string $value Source value.
     * @return string
     */
    private static function slugify(string $value): string {

        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $slug = trim((string)$slug, '-_');
        return $slug !== '' ? $slug : 'course';
    }

    /**
     * Build a unique product slug or SKU.
     *
     * @param string $field Product field name.
     * @param string $base Base value.
     * @return string
     */
    private static function unique_product_field(string $field, string $base): string {

        global $DB;
        $field = in_array($field, ['slug', 'sku'], true) ? $field : 'slug';
        $base = trim($base) !== '' ? trim($base) : 'course';
        $candidate = $base;
        $counter = 2;
        while ($DB->record_exists('local_moderncommerce_products', [$field => $candidate])) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }
}
