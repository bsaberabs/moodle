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
 * Cart API for Modern Commerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\api;


use local_moderncommerce\services\pricing_service;
use local_moderncommerce\services\price_resolver;
use local_moderncommerce\services\access_service;

/**
 * Canonical cart API backed by carts/cart_items/product tables.
 */
class cart_api {
    /**
     * Add course to cart.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $quantity
     * @return bool
     */
    public static function add_to_cart($userid, $courseid, $quantity = 1) {
        global $DB;

        $userid = (int) $userid;
        $courseid = (int) $courseid;

        $price = price_resolver::resolve_for_course($courseid, 1, true);
        if (!$price || empty($price->productid)) {
            throw new \moodle_exception('error:invalidcourse', 'local_moderncommerce');
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        if (access_service::user_has_course_access($userid, $courseid)) {
            throw new \moodle_exception('coursealreadyaccessible', 'local_moderncommerce');
        }

        $context = \context_course::instance($course->id);
        if (is_enrolled($context, $userid)) {
            throw new \moodle_exception('alreadyenrolled', 'local_moderncommerce');
        }

        if (self::course_is_purchased($userid, $courseid, (int) $price->productid)) {
            throw new \moodle_exception('coursealreadypurchased', 'local_moderncommerce');
        }

        $cart = self::get_active_cart($userid, true);
        $exists = $DB->record_exists('local_moderncommerce_cart_items', [
            'cartid' => $cart->id,
            'productid' => (int) $price->productid,
        ]);
        if ($exists) {
            throw new \moodle_exception('coursealreadyincart', 'local_moderncommerce');
        }

        $now = time();
        $cartitem = (object) [
            'cartid' => $cart->id,
            'productid' => (int) $price->productid,
            'priceid' => (int) $price->priceid,
            'quantity' => 1,
            'unitprice' => (float) $price->unitprice,
            'currency' => $price->currency ?: self::get_currency(),
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $DB->insert_record('local_moderncommerce_cart_items', $cartitem);
        self::recalculate_cart((int) $cart->id);

        return true;
    }

    /**
     * Remove course from cart.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    public static function remove_from_cart($userid, $courseid) {
        global $DB;

        $cart = self::get_active_cart((int) $userid, false);
        if (!$cart) {
            return true;
        }

        $product = self::get_course_product((int) $courseid);
        if (!$product) {
            return true;
        }

        $result = $DB->delete_records('local_moderncommerce_cart_items', [
            'cartid' => $cart->id,
            'productid' => $product->id,
        ]);
        self::recalculate_cart((int) $cart->id);

        return $result;
    }

    /**
     * Update cart item quantity.
     *
     * Course and bundle enrollments are single-seat purchases for the current user.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $quantity
     * @return bool
     */
    public static function update_quantity($userid, $courseid, $quantity) {
        global $DB;

        if ((int) $quantity <= 0) {
            return self::remove_from_cart($userid, $courseid);
        }

        $cart = self::get_active_cart((int) $userid, false);
        $product = self::get_course_product((int) $courseid);
        if (!$cart || !$product) {
            throw new \moodle_exception('invalidcartitem', 'local_moderncommerce');
        }

        $cartitem = $DB->get_record('local_moderncommerce_cart_items', [
            'cartid' => $cart->id,
            'productid' => $product->id,
        ], '*', MUST_EXIST);

        $price = price_resolver::resolve_for_product((int) $product->id, 1, true);
        if (!$price) {
            throw new \moodle_exception('productnotavailable', 'local_moderncommerce');
        }

        $cartitem->quantity = 1;
        $cartitem->priceid = (int) $price->priceid;
        $cartitem->unitprice = (float) $price->unitprice;
        $cartitem->currency = $price->currency ?: self::get_currency();
        $cartitem->timemodified = time();

        $result = $DB->update_record('local_moderncommerce_cart_items', $cartitem);
        self::recalculate_cart((int) $cart->id);

        return $result;
    }

    /**
     * Get course cart items for user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_cart_items($userid) {
        global $DB;

        $cart = self::get_active_cart((int) $userid, false);
        if (!$cart) {
            return [];
        }

        $sql = "SELECT ci.id,
                       ci.cartid,
                       ci.productid,
                       ci.priceid,
                       ci.quantity,
                       ci.unitprice,
                       ci.unitprice AS price,
                       ci.currency,
                       ci.timecreated,
                       ci.timemodified,
                       p.name AS productname,
                       p.sku,
                       p.enrolduration,
                       pc.courseid,
                       co.fullname AS coursename,
                       co.shortname AS courseshortname
                  FROM {local_moderncommerce_cart_items} ci
                  JOIN {local_moderncommerce_products} p ON p.id = ci.productid
                  JOIN {local_moderncommerce_product_courses} pc
                    ON pc.productid = p.id
                   AND pc.relationtype = :relationtype
                  JOIN {course} co ON co.id = pc.courseid
                 WHERE ci.cartid = :cartid
                   AND p.producttype = :producttype
              ORDER BY ci.timecreated ASC, ci.id ASC";

        $items = $DB->get_records_sql($sql, [
            'cartid' => $cart->id,
            'relationtype' => 'included',
            'producttype' => 'course',
        ]);

        foreach ($items as $item) {
            self::normalise_cart_item($item, 'course');
        }

        return $items;
    }

    /**
     * Get cart item count.
     *
     * @param int $userid
     * @return int
     */
    public static function get_cart_count($userid) {
        global $DB;

        $cart = self::get_active_cart((int) $userid, false);
        if (!$cart) {
            return 0;
        }

        return (int) $DB->count_records('local_moderncommerce_cart_items', ['cartid' => $cart->id]);
    }

    /**
     * Add bundle or program to cart.
     *
     * @param int $userid
     * @param int $bundleid
     * @param int $quantity
     * @return bool
     */
    public static function add_bundle_to_cart($userid, $bundleid, $quantity = 1) {
        global $DB;

        $userid = (int) $userid;
        $bundleid = (int) $bundleid;

        $bundle = bundle_api::get($bundleid);
        if (!$bundle || $bundle->status !== 'active' || empty($bundle->visible)) {
            throw new \moodle_exception('bundlenotavailable', 'local_moderncommerce');
        }

        if (access_service::user_has_product_purchase_access($userid, $bundleid)) {
            throw new \moodle_exception('productalreadyaccessible', 'local_moderncommerce');
        }

        if (bundle_api::is_purchased($bundleid, $userid)) {
            throw new \moodle_exception('bundlealreadypurchased', 'local_moderncommerce');
        }

        $cart = self::get_active_cart($userid, true);
        $exists = $DB->record_exists('local_moderncommerce_cart_items', [
            'cartid' => $cart->id,
            'productid' => $bundleid,
        ]);
        if ($exists) {
            throw new \moodle_exception('bundlealreadyincart', 'local_moderncommerce');
        }

        $price = price_resolver::resolve_for_product($bundleid, 1, true);
        if (!$price) {
            throw new \moodle_exception('bundlenotavailable', 'local_moderncommerce');
        }

        $unitprice = self::get_adjusted_bundle_price($userid, $bundleid, (float) $price->unitprice);
        $now = time();

        $cartitem = (object) [
            'cartid' => $cart->id,
            'productid' => $bundleid,
            'priceid' => (int) $price->priceid,
            'quantity' => 1,
            'unitprice' => $unitprice,
            'currency' => $price->currency ?: self::get_currency(),
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $DB->insert_record('local_moderncommerce_cart_items', $cartitem);
        self::recalculate_cart((int) $cart->id);

        return true;
    }

    /**
     * Remove bundle or program from cart.
     *
     * @param int $userid
     * @param int $bundleid
     * @return bool
     */
    public static function remove_bundle_from_cart($userid, $bundleid) {
        global $DB;

        $cart = self::get_active_cart((int) $userid, false);
        if (!$cart) {
            return true;
        }

        $result = $DB->delete_records('local_moderncommerce_cart_items', [
            'cartid' => $cart->id,
            'productid' => (int) $bundleid,
        ]);
        self::recalculate_cart((int) $cart->id);

        return $result;
    }

    /**
     * Get bundle/program cart items for user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_bundle_cart_items($userid) {
        global $DB;

        $cart = self::get_active_cart((int) $userid, false);
        if (!$cart) {
            return [];
        }

        $sql = "SELECT ci.id,
                       ci.cartid,
                       ci.productid,
                       ci.productid AS bundleid,
                       ci.priceid,
                       ci.quantity,
                       ci.unitprice,
                       ci.unitprice AS price,
                       ci.currency,
                       ci.timecreated,
                       ci.timemodified,
                       p.producttype,
                       p.name AS productname,
                       p.name AS bundlename,
                       p.sku,
                       p.imageurl,
                       p.enrolduration,
                       pr.amount AS bundleprice
                  FROM {local_moderncommerce_cart_items} ci
                  JOIN {local_moderncommerce_products} p ON p.id = ci.productid
             LEFT JOIN {local_moderncommerce_product_prices} pr ON pr.id = ci.priceid
                 WHERE ci.cartid = :cartid
                   AND p.producttype IN (:bundle, :program)
              ORDER BY ci.timecreated ASC, ci.id ASC";

        $items = $DB->get_records_sql($sql, [
            'cartid' => $cart->id,
            'bundle' => 'bundle',
            'program' => 'program',
        ]);

        foreach ($items as $item) {
            self::normalise_cart_item($item, (string) $item->producttype);
        }

        return $items;
    }

    /**
     * Clear cart.
     *
     * @param int $userid
     * @return bool
     */
    public static function clear_cart($userid) {
        global $DB;

        $carts = $DB->get_records('local_moderncommerce_carts', [
            'userid' => (int) $userid,
            'status' => 'active',
        ]);

        foreach ($carts as $cart) {
            $DB->delete_records('local_moderncommerce_cart_items', ['cartid' => $cart->id]);
            $cart->status = 'converted';
            $cart->subtotal = 0;
            $cart->discount = 0;
            $cart->tax = 0;
            $cart->total = 0;
            $cart->timemodified = time();
            $DB->update_record('local_moderncommerce_carts', $cart);
        }

        return true;
    }

    /**
     * Refresh active cart line snapshots from the canonical price resolver.
     *
     * @param int $userid User ID.
     * @param bool $throw Throw when an item is no longer purchasable.
     * @return array Refresh summary.
     */
    public static function refresh_cart_prices(int $userid, bool $throw = true): array {
        global $DB;

        $cart = self::get_active_cart($userid, false);
        if (!$cart) {
            return [
                'updated' => 0,
                'unavailable' => [],
            ];
        }

        $items = $DB->get_records('local_moderncommerce_cart_items', ['cartid' => $cart->id], 'id ASC');
        $updated = 0;
        $unavailable = [];

        foreach ($items as $item) {
            if (access_service::user_has_product_purchase_access($userid, (int) $item->productid)) {
                $DB->delete_records('local_moderncommerce_cart_items', ['id' => (int) $item->id]);
                $unavailable[] = (int) $item->productid;
                $updated++;

                if ($throw) {
                    self::recalculate_cart((int) $cart->id);
                    throw new \moodle_exception('productalreadyaccessible', 'local_moderncommerce');
                }

                continue;
            }

            $quantity = max(1, (float) $item->quantity);
            $price = price_resolver::resolve_for_product((int) $item->productid, $quantity, true);

            if (!$price) {
                $unavailable[] = (int) $item->productid;
                if ($throw) {
                    throw new \moodle_exception('productnotavailable', 'local_moderncommerce');
                }
                continue;
            }

            $unitprice = (float) $price->unitprice;
            if (in_array($price->producttype, ['bundle', 'program'], true)) {
                $unitprice = self::get_adjusted_bundle_price($userid, (int) $item->productid, $unitprice);
            }

            $currency = $price->currency ?: self::get_currency();
            $currentpriceid = empty($item->priceid) ? null : (int) $item->priceid;
            $nextpriceid = (int) $price->priceid;

            if (
                $currentpriceid === $nextpriceid
                && (float) $item->unitprice === $unitprice
                && (string) $item->currency === (string) $currency
            ) {
                continue;
            }

            $item->priceid = $nextpriceid;
            $item->unitprice = $unitprice;
            $item->currency = $currency;
            $item->timemodified = time();
            $DB->update_record('local_moderncommerce_cart_items', $item);
            $updated++;
        }

        if ($updated > 0) {
            self::recalculate_cart((int) $cart->id);
        }

        return [
            'updated' => $updated,
            'unavailable' => $unavailable,
        ];
    }

    /**
     * Cleanup old carts.
     *
     * @param int $days
     * @return int Number of carts deleted
     */
    public static function cleanup_old_carts($days = 30) {
        global $DB;

        $cutoff = time() - ((int) $days * DAYSECS);
        $carts = $DB->get_records_select('local_moderncommerce_carts', 'timemodified < :cutoff', ['cutoff' => $cutoff]);
        if (!$carts) {
            return 0;
        }

        $ids = array_keys($carts);
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'cartid');
        $DB->delete_records_select('local_moderncommerce_cart_items', "cartid $insql", $params);

        return $DB->delete_records_select('local_moderncommerce_carts', "id $insql", $params) ? count($ids) : 0;
    }

    /**
     * Return the user's active cart.
     *
     * @param int $userid
     * @param bool $create
     * @return object|false
     */
    public static function get_active_cart(int $userid, bool $create = true) {
        global $DB, $SESSION;

        $records = $DB->get_records('local_moderncommerce_carts', [
            'userid' => $userid,
            'status' => 'active',
        ], 'timemodified DESC, id DESC', '*', 0, 1);

        if ($records) {
            return reset($records);
        }

        if (!$create) {
            return false;
        }

        $now = time();
        $cart = (object) [
            'userid' => $userid,
            'sessionid' => $SESSION->sessionid ?? sesskey(),
            'status' => 'active',
            'currency' => self::get_currency(),
            'couponcode' => null,
            'subtotal' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 0,
            'expiresat' => $now + (30 * DAYSECS),
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $cart->id = (int) $DB->insert_record('local_moderncommerce_carts', $cart);
        return $cart;
    }

    /**
     * Get product row for a Moodle course.
     *
     * @param int $courseid
     * @return object|false
     */
    private static function get_course_product(int $courseid) {
        global $DB;

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
            'courseid' => $courseid,
            'relationtype' => 'included',
            'producttype' => 'course',
        ], 0, 1);

        return $records ? reset($records) : false;
    }

    /**
     * Check whether the user already bought the course.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $productid
     * @return bool
     */
    private static function course_is_purchased(int $userid, int $courseid, int $productid): bool {
        global $DB;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {local_moderncommerce_orders} o
               JOIN {local_moderncommerce_order_items} oi ON oi.orderid = o.id
              WHERE o.userid = :userid
                AND o.status IN ('paid', 'completed')
                AND (oi.courseid = :courseid OR oi.productid = :productid)",
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'productid' => $productid,
            ]
        );
    }

    /**
     * Calculate adjusted bundle price after excluding already enrolled course value.
     *
     * @param int $userid
     * @param int $bundleid
     * @param float $baseprice
     * @return float
     */
    private static function get_adjusted_bundle_price(int $userid, int $bundleid, float $baseprice): float {
        $ownedcoursesvalue = 0.0;

        foreach (bundle_api::get_courses($bundleid) as $bundlecourse) {
            if (access_service::user_has_course_access((int) $userid, (int) $bundlecourse->courseid)) {
                $pricing = pricing_service::get_course_pricing((int) $bundlecourse->courseid);
                if ($pricing && empty($pricing->is_free)) {
                    $ownedcoursesvalue += (float) $pricing->final_price;
                }
            }
        }

        return max(0.0, $baseprice - $ownedcoursesvalue);
    }

    /**
     * Check course enrollment.
     *
     * @param int $userid
     * @param int $courseid
     * @return bool
     */
    private static function is_user_enrolled(int $userid, int $courseid): bool {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }

        return is_enrolled($context, $userid);
    }

    /**
     * Update cart totals from canonical line items.
     *
     * @param int $cartid
     */
    private static function recalculate_cart(int $cartid): void {
        global $DB;

        $subtotal = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(unitprice * quantity), 0)
               FROM {local_moderncommerce_cart_items}
              WHERE cartid = :cartid",
            ['cartid' => $cartid]
        );

        $cart = $DB->get_record('local_moderncommerce_carts', ['id' => $cartid]);
        if (!$cart) {
            return;
        }

        $cart->subtotal = $subtotal;
        $cart->discount = 0;
        $cart->tax = 0;
        $cart->total = $subtotal;
        $cart->currency = $cart->currency ?: self::get_currency();
        $cart->timemodified = time();
        $DB->update_record('local_moderncommerce_carts', $cart);
    }

    /**
     * Add legacy aliases expected by existing templates.
     *
     * @param object $item
     * @param string $itemtype
     */
    private static function normalise_cart_item(object $item, string $itemtype): void {
        $item->itemtype = $itemtype;
        $item->price = (float) $item->unitprice;
        $item->quantity = (float) $item->quantity;

        if ($itemtype === 'bundle' || $itemtype === 'program') {
            $item->bundleid = (int) $item->productid;
            $item->bundlename = $item->bundlename ?: $item->productname;
            $item->bundleprice = $item->bundleprice !== null ? (float) $item->bundleprice : (float) $item->unitprice;
        }
    }

    /**
     * Get configured currency.
     *
     * @return string
     */
    private static function get_currency(): string {
        return pricing_service::get_currency_config()->currency;
    }
}
