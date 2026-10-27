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
 * Coupon API for Modern Commerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\api;


use core_text;
use local_moderncommerce\services\pricing_service;

/**
 * Canonical coupon validation and discount calculation service.
 */
class coupon_api {
    /**
     * Validate a coupon and return the coupon record when usable.
     *
     * This method keeps the legacy object|null contract. New checkout code should
     * use validate_coupon_result() when it needs the failure message.
     *
     * @param string $code Coupon code.
     * @param array|int $cartitems Cart line items, or legacy user id.
     * @param int|null $userid User id, defaults to current user.
     * @return object|null
     */
    public static function validate_coupon($code, $cartitems = [], ?int $userid = null) {
        $result = self::validate_coupon_result($code, $cartitems, $userid);

        return $result['valid'] ? $result['coupon'] : null;
    }

    /**
     * Validate a coupon and return a structured result.
     *
     * @param string $code Coupon code.
     * @param array|int $cartitems Cart line items, or legacy user id.
     * @param int|null $userid User id, defaults to current user.
     * @return array
     */
    public static function validate_coupon_result($code, $cartitems = [], ?int $userid = null): array {
        global $DB, $USER;

        if (!is_array($cartitems)) {
            if ($userid === null && is_numeric($cartitems)) {
                $userid = (int) $cartitems;
            }
            $cartitems = [];
        }

        $userid = $userid ?? (int) ($USER->id ?? 0);
        $code = self::normalise_code((string) $code);
        if ($code === '') {
            return self::failure('entercouponcode');
        }

        $coupon = $DB->get_record('local_moderncommerce_coupons', [
            'code' => $code,
            'status' => 'active',
        ]);

        if (!$coupon) {
            return self::failure('invalidcoupon');
        }

        $now = time();
        if (!empty($coupon->startdate) && (int) $coupon->startdate > $now) {
            return self::failure('invalidcoupon');
        }

        if (!empty($coupon->enddate) && (int) $coupon->enddate < $now) {
            return self::failure('invalidcoupon');
        }

        if (self::coupon_is_depleted($coupon)) {
            return self::failure('invalidcoupon');
        }

        if ($userid > 0 && self::user_limit_reached($coupon, $userid)) {
            return self::failure('couponuserlimitreached');
        }

        $context = self::build_cart_context($cartitems);
        if ($context['hasitems']) {
            if (!empty($coupon->minpurchase) && $context['subtotal'] < (float) $coupon->minpurchase) {
                return self::failure(
                    'couponminpurchase',
                    pricing_service::format_price((float) $coupon->minpurchase)
                );
            }

            if (!empty($coupon->minitems) && $context['itemcount'] < (int) $coupon->minitems) {
                return self::failure('couponminitems', (int) $coupon->minitems);
            }

            $targetresult = self::resolve_target_discount_base($coupon, $context);
            if (!$targetresult['valid']) {
                return self::failure('couponnotapplicable');
            }

            $coupon->discountbase = $targetresult['discountbase'];
            $coupon->eligibleitemcount = $targetresult['eligibleitemcount'];
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'messageid' => '',
            'message' => '',
            'a' => null,
            'subtotal' => $context['subtotal'],
            'itemcount' => $context['itemcount'],
            'discountbase' => isset($coupon->discountbase) ? (float) $coupon->discountbase : $context['subtotal'],
        ];
    }

    /**
     * Calculate discount amount.
     *
     * @param object $coupon Coupon record.
     * @param float $subtotal Full order subtotal.
     * @return float Discount amount.
     */
    public static function calculate_discount($coupon, $subtotal) {
        $subtotal = max(0, (float) $subtotal);
        $discountbase = isset($coupon->discountbase) ? max(0, (float) $coupon->discountbase) : $subtotal;
        $discountbase = min($discountbase, $subtotal);
        $discount = 0.0;
        $type = $coupon->discounttype ?? $coupon->type ?? 'percentage';
        $value = max(0, (float) ($coupon->value ?? 0));

        if ($type === 'percentage') {
            $discount = ($discountbase * $value) / 100;
            if (!empty($coupon->maxdiscount) && $discount > (float) $coupon->maxdiscount) {
                $discount = (float) $coupon->maxdiscount;
            }
        } else if ($type === 'fixed') {
            $discount = min($value, $discountbase);
        }

        return min(round($discount, 6), $subtotal);
    }

    /**
     * Record coupon usage.
     *
     * @param int $couponid Coupon id.
     * @param int $userid User id.
     * @param int $orderid Order id.
     * @param float $discountamount Discount amount.
     * @return bool
     */
    public static function record_usage($couponid, $userid, $orderid, $discountamount) {
        global $DB;

        $couponid = (int) $couponid;
        $userid = (int) $userid;
        $orderid = (int) $orderid;

        if ($couponid <= 0 || $userid <= 0 || $orderid <= 0) {
            return false;
        }

        if (
            $DB->record_exists('local_moderncommerce_coupon_usage', [
            'couponid' => $couponid,
            'orderid' => $orderid,
            ])
        ) {
            return true;
        }

        $transaction = $DB->start_delegated_transaction();

        $usage = (object) [
            'couponid' => $couponid,
            'userid' => $userid,
            'orderid' => $orderid,
            'discountamount' => max(0, (float) $discountamount),
            'timecreated' => time(),
        ];
        $DB->insert_record('local_moderncommerce_coupon_usage', $usage);

        $DB->execute(
            "UPDATE {local_moderncommerce_coupons}
                SET usedcount = usedcount + 1,
                    timemodified = :timemodified
              WHERE id = :couponid",
            [
                'couponid' => $couponid,
                'timemodified' => time(),
            ]
        );

        $transaction->allow_commit();

        \local_moderncommerce\audit\audit_service::record('coupon_used', 'coupon', $couponid, [
            'actoruserid' => $userid,
            'subjectuserid' => $userid,
            'newdata' => [
                'couponid' => $couponid,
                'userid' => $userid,
                'orderid' => $orderid,
                'discountamount' => max(0, (float) $discountamount),
            ],
            'source' => 'checkout',
        ]);

        return true;
    }

    /**
     * Create coupon.
     *
     * @param array $data Coupon data.
     * @return int Coupon ID.
     */
    public static function create_coupon($data) {
        global $DB, $USER;

        $now = time();
        $coupon = (object) [
            'code' => self::normalise_code($data['code'] ?? ''),
            'name' => $data['name'] ?? '',
            'discounttype' => $data['type'] ?? 'percentage',
            'value' => $data['value'] ?? 0,
            'maxdiscount' => $data['maxdiscount'] ?? null,
            'minpurchase' => $data['minpurchase'] ?? null,
            'minitems' => $data['minitems'] ?? null,
            'maxuses' => $data['maxuses'] ?? null,
            'usedcount' => 0,
            'maxusesperuser' => $data['maxusesperuser'] ?? null,
            'stackable' => $data['stackable'] ?? 0,
            'startdate' => $data['startdate'] ?? null,
            'enddate' => $data['enddate'] ?? null,
            'status' => $data['status'] ?? 'active',
            'createdby' => $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        return (int) $DB->insert_record('local_moderncommerce_coupons', $coupon);
    }

    /**
     * Check total coupon usage limits.
     *
     * @param object $coupon Coupon record.
     * @return bool
     */
    private static function coupon_is_depleted(object $coupon): bool {
        global $DB;

        $maxuses = empty($coupon->maxuses) ? 0 : (int) $coupon->maxuses;
        if ($maxuses <= 0) {
            return false;
        }

        $recorded = (int) $DB->count_records('local_moderncommerce_coupon_usage', ['couponid' => (int) $coupon->id]);
        $cached = empty($coupon->usedcount) ? 0 : (int) $coupon->usedcount;

        return max($recorded, $cached) >= $maxuses;
    }

    /**
     * Check per-user coupon usage limits.
     *
     * @param object $coupon Coupon record.
     * @param int $userid User id.
     * @return bool
     */
    private static function user_limit_reached(object $coupon, int $userid): bool {
        global $DB;

        $maxusesperuser = empty($coupon->maxusesperuser) ? 0 : (int) $coupon->maxusesperuser;
        if ($maxusesperuser <= 0) {
            return false;
        }

        $useruses = $DB->count_records('local_moderncommerce_coupon_usage', [
            'couponid' => (int) $coupon->id,
            'userid' => $userid,
        ]);

        return $useruses >= $maxusesperuser;
    }

    /**
     * Build normalized cart context for coupon rules.
     *
     * @param array $cartitems Cart items.
     * @return array
     */
    private static function build_cart_context(array $cartitems): array {
        global $DB;

        $productids = [];
        $explicitcourseids = [];
        $lines = [];

        foreach ($cartitems as $item) {
            if (!is_object($item)) {
                continue;
            }

            $quantity = isset($item->quantity) ? max(1, (float) $item->quantity) : 1;
            $unitprice = isset($item->unitprice) ? (float) $item->unitprice : (float) ($item->price ?? 0);
            $productid = isset($item->productid) ? (int) $item->productid : (int) ($item->bundleid ?? 0);
            $courseid = isset($item->courseid) ? (int) $item->courseid : 0;

            if ($productid > 0) {
                $productids[$productid] = $productid;
            }
            if ($courseid > 0) {
                $explicitcourseids[$courseid] = $courseid;
            }

            $lines[] = [
                'productid' => $productid,
                'courseids' => $courseid > 0 ? [$courseid] : [],
                'coursecategoryids' => [],
                'productcategoryids' => [],
                'producttype' => (string) ($item->producttype ?? $item->itemtype ?? ''),
                'sku' => (string) ($item->sku ?? ''),
                'quantity' => $quantity,
                'subtotal' => max(0, $unitprice * $quantity),
            ];
        }

        $products = self::load_products($productids);
        $productcourses = self::load_product_courses($productids);
        $productcategories = self::load_product_categories($productids);
        $courseids = $explicitcourseids;
        foreach ($productcourses as $ids) {
            foreach ($ids as $courseid) {
                $courseids[$courseid] = $courseid;
            }
        }
        $coursecategories = self::load_course_categories($courseids);

        $subtotal = 0.0;
        $itemcount = 0.0;
        foreach ($lines as $index => $line) {
            $productid = $line['productid'];
            if ($productid > 0 && isset($products[$productid])) {
                $lines[$index]['producttype'] = $products[$productid]->producttype ?: $line['producttype'];
                $lines[$index]['sku'] = $products[$productid]->sku ?: $line['sku'];
            }

            if ($productid > 0 && !empty($productcourses[$productid])) {
                $lines[$index]['courseids'] = array_values(array_unique(array_merge(
                    $line['courseids'],
                    $productcourses[$productid]
                )));
            }

            if ($productid > 0 && !empty($productcategories[$productid])) {
                $lines[$index]['productcategoryids'] = $productcategories[$productid];
            }

            foreach ($lines[$index]['courseids'] as $courseid) {
                if (!empty($coursecategories[$courseid])) {
                    $lines[$index]['coursecategoryids'][] = $coursecategories[$courseid];
                }
            }
            $lines[$index]['coursecategoryids'] = array_values(array_unique($lines[$index]['coursecategoryids']));

            $subtotal += $line['subtotal'];
            $itemcount += $line['quantity'];
        }

        return [
            'hasitems' => !empty($lines),
            'lines' => $lines,
            'subtotal' => $subtotal,
            'itemcount' => $itemcount,
        ];
    }

    /**
     * Resolve target rules and eligible discount subtotal.
     *
     * @param object $coupon Coupon record.
     * @param array $context Cart context.
     * @return array
     */
    private static function resolve_target_discount_base(object $coupon, array $context): array {
        global $DB;

        $targets = $DB->get_records('local_moderncommerce_coupon_targets', [
            'couponid' => (int) $coupon->id,
        ]);

        if (empty($targets)) {
            return [
                'valid' => true,
                'discountbase' => $context['subtotal'],
                'eligibleitemcount' => $context['itemcount'],
            ];
        }

        $includeexists = false;
        foreach ($targets as $target) {
            if (($target->includemode ?? 'include') === 'include') {
                $includeexists = true;
                break;
            }
        }

        $discountbase = 0.0;
        $eligibleitemcount = 0.0;

        foreach ($context['lines'] as $line) {
            $included = !$includeexists;
            $excluded = false;

            foreach ($targets as $target) {
                $matches = self::target_matches_line($target, $line);
                if (!$matches) {
                    continue;
                }

                if (($target->includemode ?? 'include') === 'exclude') {
                    $excluded = true;
                    continue;
                }

                $included = true;
            }

            if ($included && !$excluded) {
                $discountbase += $line['subtotal'];
                $eligibleitemcount += $line['quantity'];
            }
        }

        return [
            'valid' => $discountbase > 0,
            'discountbase' => $discountbase,
            'eligibleitemcount' => $eligibleitemcount,
        ];
    }

    /**
     * Check whether a target row matches a cart line.
     *
     * @param object $target Target row.
     * @param array $line Cart line.
     * @return bool
     */
    private static function target_matches_line(object $target, array $line): bool {
        $type = core_text::strtolower((string) $target->targettype);
        $targetid = empty($target->targetid) ? 0 : (int) $target->targetid;
        $targetvalue = core_text::strtolower(trim((string) ($target->targetvalue ?? '')));

        if ($type === 'all' || $type === 'cart' || $type === 'order') {
            return true;
        }

        if ($type === 'product') {
            return $targetid > 0 && (int) $line['productid'] === $targetid;
        }

        if ($type === 'course') {
            return $targetid > 0 && in_array($targetid, $line['courseids'], true);
        }

        if ($type === 'producttype' || $type === 'product_type') {
            return $targetvalue !== '' && core_text::strtolower((string) $line['producttype']) === $targetvalue;
        }

        if ($type === 'sku') {
            return $targetvalue !== '' && core_text::strtolower((string) $line['sku']) === $targetvalue;
        }

        if ($type === 'productcategory' || $type === 'product_category' || $type === 'category') {
            return $targetid > 0 && in_array($targetid, $line['productcategoryids'], true);
        }

        if ($type === 'coursecategory' || $type === 'course_category' || $type === 'moodlecategory') {
            return $targetid > 0 && in_array($targetid, $line['coursecategoryids'], true);
        }

        return false;
    }

    /**
     * Load product records.
     *
     * @param array $productids Product IDs.
     * @return array
     */
    private static function load_products(array $productids): array {
        global $DB;

        if (empty($productids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_values($productids), SQL_PARAMS_NAMED, 'productid');
        $records = $DB->get_records_select(
            'local_moderncommerce_products',
            "id {$insql}",
            $params,
            '',
            'id, producttype, sku'
        );

        return $records ?: [];
    }

    /**
     * Load included Moodle courses for products.
     *
     * @param array $productids Product IDs.
     * @return array
     */
    private static function load_product_courses(array $productids): array {
        global $DB;

        if (empty($productids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_values($productids), SQL_PARAMS_NAMED, 'pcproductid');
        $params['relationtype'] = 'included';
        $records = $DB->get_records_select(
            'local_moderncommerce_product_courses',
            "productid {$insql} AND relationtype = :relationtype",
            $params,
            'sortorder ASC, id ASC',
            'id, productid, courseid'
        );

        $map = [];
        foreach ($records as $record) {
            $map[(int) $record->productid][] = (int) $record->courseid;
        }

        return $map;
    }

    /**
     * Load product catalog category ids for products.
     *
     * @param array $productids Product IDs.
     * @return array
     */
    private static function load_product_categories(array $productids): array {
        global $DB;

        if (empty($productids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_values($productids), SQL_PARAMS_NAMED, 'catproductid');
        $records = $DB->get_records_select(
            'local_moderncommerce_product_category_map',
            "productid {$insql}",
            $params,
            'id ASC',
            'id, productid, categoryid'
        );

        $map = [];
        foreach ($records as $record) {
            $map[(int) $record->productid][] = (int) $record->categoryid;
        }

        return $map;
    }

    /**
     * Load Moodle course category ids.
     *
     * @param array $courseids Course IDs.
     * @return array
     */
    private static function load_course_categories(array $courseids): array {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_values($courseids), SQL_PARAMS_NAMED, 'courseid');
        $records = $DB->get_records_select('course', "id {$insql}", $params, '', 'id, category');
        $map = [];

        foreach ($records as $record) {
            $map[(int) $record->id] = (int) $record->category;
        }

        return $map;
    }

    /**
     * Normalise coupon code.
     *
     * @param string $code Raw code.
     * @return string Normalised code.
     */
    private static function normalise_code(string $code): string {
        return core_text::strtoupper(trim($code));
    }

    /**
     * Build a failed validation result.
     *
     * @param string $messageid Language string id.
     * @param mixed $a Optional lang placeholder.
     * @return array
     */
    private static function failure(string $messageid, $a = null): array {
        return [
            'valid' => false,
            'coupon' => null,
            'messageid' => $messageid,
            'message' => get_string($messageid, 'local_moderncommerce', $a),
            'a' => $a,
            'subtotal' => 0,
            'itemcount' => 0,
            'discountbase' => 0,
        ];
    }
}
