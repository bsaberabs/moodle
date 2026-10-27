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
 * Course access checks for Modern Commerce storefront pages.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

use context_course;
use moodle_url;
use xmldb_table;

/**
 * Shared access resolver for purchase, subscription, and enrolment state.
 */
class access_service {
    /** @var array Subscription states that grant access. */
    private const ACTIVE_SUBSCRIPTION_STATES = ['active', 'trial', 'grace'];

    /** @var array Product types that should land in the learner account area. */
    private const DASHBOARD_PRODUCT_TYPES = ['bundle', 'program', 'subscription', 'plan'];

    /**
     * Check if a user can access a Moodle course through commerce or enrolment.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool True when the user already has usable course access.
     */
    public static function user_has_course_access(int $userid, int $courseid): bool {
        if (!self::valid_user_course($userid, $courseid)) {
            return false;
        }

        return self::has_active_entitlement($userid, $courseid)
            || self::has_paid_order_access($userid, $courseid)
            || self::has_active_subscription_course_access($userid, $courseid)
            || self::is_enrolled($userid, $courseid);
    }

    /**
     * Resolve the destination for an entitled course details page visitor.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return moodle_url|null Course URL, or null when the user has no access.
     */
    public static function resolve_course_access_url(int $userid, int $courseid): ?moodle_url {
        return self::user_has_course_access($userid, $courseid) ? self::course_view_url($courseid) : null;
    }

    /**
     * Resolve where an entitled user should go for a commerce product.
     *
     * Learners who already have access should land in the most direct learner destination from commerce surfaces.
     *
     * @param int $userid User ID.
     * @param int $productid Product ID.
     * @param int|null $courseid Optional known course ID for course products.
     * @return moodle_url|null Destination URL, or null when no redirect should happen.
     */
    public static function resolve_product_access_url(int $userid, int $productid, ?int $courseid = null): ?moodle_url {
        if (!self::valid_user_product($userid, $productid)) {
            return null;
        }

        $product = self::get_product($productid);
        if (!$product) {
            return null;
        }

        $producttype = self::normalise_product_type((string)$product->producttype);
        if ($producttype === 'course') {
            $resolvedcourseid = $courseid ?: self::get_first_product_courseid($productid);
            if ($resolvedcourseid > 0 && self::user_has_course_access($userid, $resolvedcourseid)) {
                return self::course_view_url($resolvedcourseid);
            }

            return null;
        }

        if (!in_array($producttype, self::DASHBOARD_PRODUCT_TYPES, true)) {
            return null;
        }

        if (
            self::user_has_product_access($userid, $product)
            || self::user_has_all_product_courses_access($userid, $productid)
        ) {
            if (in_array($producttype, ['bundle', 'program'], true)) {
                return self::learner_product_access_url($productid, $producttype);
            }

            return self::learner_dashboard_url();
        }

        return null;
    }

    /**
     * Check whether a user already has enough access that a product should not be purchased again.
     *
     * @param int $userid User ID.
     * @param int $productid Product ID.
     * @return bool True when the product should be blocked from purchase.
     */
    public static function user_has_product_purchase_access(int $userid, int $productid): bool {
        if (!self::valid_user_product($userid, $productid)) {
            return false;
        }

        $product = self::get_product($productid);
        if (!$product) {
            return false;
        }

        $producttype = self::normalise_product_type((string)$product->producttype);
        if ($producttype === 'course') {
            $courseid = self::get_first_product_courseid($productid);
            return $courseid > 0 && self::user_has_course_access($userid, $courseid);
        }

        if (!in_array($producttype, self::DASHBOARD_PRODUCT_TYPES, true)) {
            return false;
        }

        return self::user_has_product_access($userid, $product)
            || self::user_has_all_product_courses_access($userid, $productid);
    }

    /**
     * Return the Moodle course view URL.
     *
     * @param int $courseid Course ID.
     * @return moodle_url Course URL.
     */
    public static function course_view_url(int $courseid): moodle_url {
        return new moodle_url('/course/view.php', ['id' => $courseid]);
    }

    /**
     * Return the learner dashboard URL.
     *
     * @return moodle_url Learner dashboard URL.
     */
    public static function learner_dashboard_url(): moodle_url {
        return new moodle_url('/local/moderncommerce/learner/index.php');
    }

    /**
     * Return a learner-shell URL for an owned product's included courses.
     *
     * @param int $productid Product ID.
     * @param string $producttype Product type.
     * @return moodle_url Learner product access URL.
     */
    public static function learner_product_access_url(int $productid, string $producttype): moodle_url {
        $route = self::normalise_product_type($producttype) === 'program' ? 'program' : 'bundle';
        $url = new moodle_url('/local/moderncommerce/learner/index.php');
        $url->set_anchor('/access/' . $route . '/' . $productid);

        return $url;
    }

    /**
     * Legacy no-op kept so older call sites do not launch a course for a bundle/program.
     *
     * @param int $userid User ID.
     * @param int $bundleid Bundle product ID.
     * @return int Always 0.
     */
    public static function resolve_bundle_launch_courseid(int $userid, int $bundleid): int {
        return 0;
    }

    /**
     * Check direct product-level commerce access.
     *
     * @param int $userid User ID.
     * @param \stdClass $product Product record.
     * @return bool True when the user has product access.
     */
    private static function user_has_product_access(int $userid, \stdClass $product): bool {
        $productid = (int)$product->id;
        $producttype = self::normalise_product_type((string)$product->producttype);

        return self::has_active_product_entitlement($userid, $productid)
            || self::has_paid_product_order_access($userid, $productid)
            || self::has_active_subscription_product_access($userid, $productid, $producttype);
    }

    /**
     * Check active commerce entitlement records.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool True if an active entitlement exists.
     */
    private static function has_active_entitlement(int $userid, int $courseid): bool {
        global $DB;

        if (!self::table_exists('local_moderncommerce_entitlements')) {
            return false;
        }

        $now = time();
        return $DB->record_exists_select(
            'local_moderncommerce_entitlements',
            "userid = :userid
                 AND courseid = :courseid
                 AND status = :status
                 AND (timestart IS NULL OR timestart = 0 OR timestart <= :nowstart)
                 AND (timeend IS NULL OR timeend = 0 OR timeend >= :nowend)",
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'status' => 'active',
                'nowstart' => $now,
                'nowend' => $now,
            ]
        );
    }

    /**
     * Check active product-level entitlement records.
     *
     * @param int $userid User ID.
     * @param int $productid Product ID.
     * @return bool True if an active product entitlement exists.
     */
    private static function has_active_product_entitlement(int $userid, int $productid): bool {
        global $DB;

        if (!self::table_exists('local_moderncommerce_entitlements')) {
            return false;
        }

        $now = time();
        return $DB->record_exists_select(
            'local_moderncommerce_entitlements',
            "userid = :userid
                 AND productid = :productid
                 AND status = :status
                 AND (timestart IS NULL OR timestart = 0 OR timestart <= :nowstart)
                 AND (timeend IS NULL OR timeend = 0 OR timeend >= :nowend)",
            [
                'userid' => $userid,
                'productid' => $productid,
                'status' => 'active',
                'nowstart' => $now,
                'nowend' => $now,
            ]
        );
    }

    /**
     * Check paid/completed order rows, including bundle or program products that contain the course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool True when a paid order grants this course.
     */
    private static function has_paid_order_access(int $userid, int $courseid): bool {
        global $DB;

        if (
            !self::table_exists('local_moderncommerce_orders')
            || !self::table_exists('local_moderncommerce_order_items')
        ) {
            return false;
        }

        $sql = "SELECT 1
                  FROM {local_moderncommerce_orders} o
                  JOIN {local_moderncommerce_order_items} oi ON oi.orderid = o.id
             LEFT JOIN {local_moderncommerce_product_courses} pc
                    ON pc.productid = oi.productid
                   AND pc.relationtype = :relationtype
             LEFT JOIN {local_moderncommerce_products} includedproduct
                    ON includedproduct.id = pc.productid
                 WHERE o.userid = :userid
                   AND o.status IN ('paid', 'completed')
                   AND (
                        oi.courseid = :directcourseid
                        OR (pc.courseid = :bundlecourseid AND includedproduct.producttype <> :programtype)
                   )";

        return $DB->record_exists_sql($sql, [
            'relationtype' => 'included',
            'userid' => $userid,
            'directcourseid' => $courseid,
            'bundlecourseid' => $courseid,
            'programtype' => 'program',
        ]);
    }

    /**
     * Check paid/completed direct product order rows.
     *
     * @param int $userid User ID.
     * @param int $productid Product ID.
     * @return bool True when a paid order includes the product.
     */
    private static function has_paid_product_order_access(int $userid, int $productid): bool {
        global $DB;

        if (
            !self::table_exists('local_moderncommerce_orders')
            || !self::table_exists('local_moderncommerce_order_items')
        ) {
            return false;
        }

        $sql = "SELECT 1
                  FROM {local_moderncommerce_orders} o
                  JOIN {local_moderncommerce_order_items} oi ON oi.orderid = o.id
                 WHERE o.userid = :userid
                   AND oi.productid = :productid
                   AND o.status IN ('paid', 'completed')";

        return $DB->record_exists_sql($sql, [
            'userid' => $userid,
            'productid' => $productid,
        ]);
    }

    /**
     * Check active subscription plan access rules when the optional subscription add-on exists.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool True when an active subscription grants this course.
     */
    private static function has_active_subscription_course_access(int $userid, int $courseid): bool {
        global $DB;

        if (
            !self::subscription_plugin_available()
            || !self::table_exists('local_moderncommerce_user_subscriptions')
            || !self::table_exists('local_moderncommerce_subscription_access_rules')
        ) {
            return false;
        }

        $categoryid = (int)$DB->get_field('course', 'category', ['id' => $courseid]);
        [$statussql, $params] = $DB->get_in_or_equal(self::ACTIVE_SUBSCRIPTION_STATES, SQL_PARAMS_NAMED, 'substatus');
        $params['userid'] = $userid;
        $params['courseid'] = $courseid;
        $params['categoryid'] = $categoryid;
        $params['now'] = time();
        $params['relationtype'] = 'included';

        $sql = "SELECT 1
                  FROM {local_moderncommerce_user_subscriptions} us
                  JOIN {local_moderncommerce_subscription_access_rules} par ON par.planid = us.planid
             LEFT JOIN {local_moderncommerce_product_courses} pc
                    ON pc.productid = par.target_id
                   AND pc.relationtype = :relationtype
                 WHERE us.userid = :userid
                   AND us.status {$statussql}
                   AND (us.end_date IS NULL OR us.end_date = 0 OR us.end_date > :now)
                   AND (
                        (par.access_type = 'course' AND par.target_id = :courseid)
                        OR (par.access_type = 'category' AND par.target_id = :categoryid)
                        OR (par.access_type IN ('bundle', 'program', 'product') AND pc.courseid = :courseid2)
                   )";
        $params['courseid2'] = $courseid;

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Check active subscription plan access rules for a product.
     *
     * @param int $userid User ID.
     * @param int $productid Product ID.
     * @param string $producttype Product type.
     * @return bool True when an active plan grants this product.
     */
    private static function has_active_subscription_product_access(
        int $userid,
        int $productid,
        string $producttype
    ): bool {
        global $DB;

        if (
            !self::subscription_plugin_available()
            || !self::table_exists('local_moderncommerce_user_subscriptions')
            || !self::table_exists('local_moderncommerce_subscription_access_rules')
        ) {
            return false;
        }

        [$statussql, $params] = $DB->get_in_or_equal(self::ACTIVE_SUBSCRIPTION_STATES, SQL_PARAMS_NAMED, 'substatus');
        $params['userid'] = $userid;
        $params['productid'] = $productid;
        $params['producttype'] = $producttype;
        $params['now'] = time();

        $sql = "SELECT 1
                  FROM {local_moderncommerce_user_subscriptions} us
                  JOIN {local_moderncommerce_subscription_access_rules} par ON par.planid = us.planid
                 WHERE us.userid = :userid
                   AND us.status {$statussql}
                   AND (us.end_date IS NULL OR us.end_date = 0 OR us.end_date > :now)
                   AND (
                        (par.access_type = 'product' AND par.target_id = :productid)
                        OR (par.access_type = :producttype AND par.target_id = :productid2)
                   )";
        $params['productid2'] = $productid;

        return $DB->record_exists_sql($sql, $params);
    }

    /**
     * Check whether all courses mapped to a product are accessible to the user.
     *
     * @param int $userid User ID.
     * @param int $productid Product ID.
     * @return bool True if every included course is accessible.
     */
    private static function user_has_all_product_courses_access(int $userid, int $productid): bool {
        global $DB;

        if (!self::table_exists('local_moderncommerce_product_courses')) {
            return false;
        }

        $courseids = $DB->get_fieldset_select(
            'local_moderncommerce_product_courses',
            'courseid',
            'productid = :productid AND relationtype = :relationtype',
            [
                'productid' => $productid,
                'relationtype' => 'included',
            ]
        );

        if (!$courseids) {
            return false;
        }

        foreach ($courseids as $courseid) {
            if (!self::user_has_course_access($userid, (int)$courseid)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check native Moodle enrolment state.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool True if the user is currently enrolled.
     */
    private static function is_enrolled(int $userid, int $courseid): bool {
        $context = context_course::instance($courseid, IGNORE_MISSING);
        return $context ? is_enrolled($context, $userid, '', true) : false;
    }

    /**
     * Validate user and course identifiers.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool True for real non-guest users and course IDs.
     */
    private static function valid_user_course(int $userid, int $courseid): bool {
        return $userid > 0 && $courseid > 0 && !self::is_guest_user($userid);
    }

    /**
     * Validate user and product identifiers.
     *
     * @param int $userid User ID.
     * @param int $productid Product ID.
     * @return bool True for real non-guest users and product IDs.
     */
    private static function valid_user_product(int $userid, int $productid): bool {
        return $userid > 0 && $productid > 0 && !self::is_guest_user($userid);
    }

    /**
     * Load a commerce product.
     *
     * @param int $productid Product ID.
     * @return \stdClass|false Product record.
     */
    private static function get_product(int $productid) {
        global $DB;

        if (!self::table_exists('local_moderncommerce_products')) {
            return false;
        }

        return $DB->get_record('local_moderncommerce_products', ['id' => $productid], '*', IGNORE_MISSING);
    }

    /**
     * Get the first included course for a product.
     *
     * @param int $productid Product ID.
     * @return int Course ID, or 0.
     */
    private static function get_first_product_courseid(int $productid): int {
        global $DB;

        if (!self::table_exists('local_moderncommerce_product_courses')) {
            return 0;
        }

        $sql = "SELECT courseid
                  FROM {local_moderncommerce_product_courses}
                 WHERE productid = :productid
                   AND relationtype = :relationtype
              ORDER BY sortorder ASC, id ASC";
        $courseid = $DB->get_field_sql($sql, [
            'productid' => $productid,
            'relationtype' => 'included',
        ]);

        return $courseid ? (int)$courseid : 0;
    }

    /**
     * Normalise equivalent product type names.
     *
     * @param string $producttype Product type.
     * @return string Normalised product type.
     */
    private static function normalise_product_type(string $producttype): string {
        $producttype = strtolower(trim($producttype));
        return $producttype === 'membership' ? 'subscription' : $producttype;
    }

    /**
     * Check whether a user ID is the site guest account.
     *
     * @param int $userid User ID.
     * @return bool True for the guest user.
     */
    private static function is_guest_user(int $userid): bool {
        $guest = guest_user();
        return $guest && (int)$guest->id === $userid;
    }

    /**
     * Check whether an optional table exists.
     *
     * @param string $table Table name without prefix.
     * @return bool True when the table exists.
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }

    /**
     * Check whether the optional subscription add-on is installed and upgraded.
     *
     * @return bool True when subscription integration can be queried.
     */
    private static function subscription_plugin_available(): bool {
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('local_moderncommerce');
        return $plugininfo !== null && $plugininfo->is_installed_and_upgraded();
    }
}
