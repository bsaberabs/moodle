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
 * External API for the admin wishlist analytics screen.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\wishlists;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * List wishlist demand and recent activity for the admin analytics screen.
 */
class list_wishlists extends external_api {
    /** @var string[] Product type filters supported by the wishlist screen. */
    private const PRODUCT_TYPES = ['course', 'bundle', 'program', 'subscription', 'digital'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search product or customer fields.', VALUE_DEFAULT, ''),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type filter.', VALUE_DEFAULT, ''),
            'productpage' => new external_value(PARAM_INT, 'Zero-based top-products page.', VALUE_DEFAULT, 0),
            'activitypage' => new external_value(PARAM_INT, 'Zero-based recent-activity page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
            'productsort' => new external_value(PARAM_ALPHANUMEXT, 'Top-products sort key.', VALUE_DEFAULT, 'savedcount'),
            'productdirection' => new external_value(PARAM_ALPHA, 'Top-products sort direction.', VALUE_DEFAULT, 'DESC'),
            'productsearch' => new external_value(PARAM_TEXT, 'Top-products search term.', VALUE_DEFAULT, ''),
            'activitysearch' => new external_value(PARAM_TEXT, 'Recent-activity search term.', VALUE_DEFAULT, ''),
            'activitytype' => new external_value(PARAM_ALPHANUMEXT, 'Recent-activity product type filter.', VALUE_DEFAULT, ''),
            'productperpage' => new external_value(PARAM_INT, 'Top-products records per page.', VALUE_DEFAULT, 0),
            'activityperpage' => new external_value(PARAM_INT, 'Recent-activity records per page.', VALUE_DEFAULT, 0),
            'activitysort' => new external_value(PARAM_ALPHANUMEXT, 'Recent-activity sort key.', VALUE_DEFAULT, 'timecreated'),
            'activitydirection' => new external_value(PARAM_ALPHA, 'Recent-activity sort direction.', VALUE_DEFAULT, 'DESC'),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $search Search term.
     * @param string $producttype Product type filter.
     * @param int $productpage Top-products page.
     * @param int $activitypage Recent-activity page.
     * @param int $perpage Page size.
     * @param string $productsort Top-products sort key.
     * @param string $productdirection Top-products sort direction.
     * @param string $productsearch Top-products search term.
     * @param string $activitysearch Recent-activity search term.
     * @param string $activitytype Recent-activity product type filter.
     * @param int $productperpage Top-products page size.
     * @param int $activityperpage Recent-activity page size.
     * @param string $activitysort Recent-activity sort key.
     * @param string $activitydirection Recent-activity sort direction.
     * @return array
     */
    public static function execute(
        string $search = '',
        string $producttype = '',
        int $productpage = 0,
        int $activitypage = 0,
        int $perpage = 10,
        string $productsort = 'savedcount',
        string $productdirection = 'DESC',
        string $productsearch = '',
        string $activitysearch = '',
        string $activitytype = '',
        int $productperpage = 0,
        int $activityperpage = 0,
        string $activitysort = 'timecreated',
        string $activitydirection = 'DESC'
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'producttype' => $producttype,
            'productpage' => $productpage,
            'activitypage' => $activitypage,
            'perpage' => $perpage,
            'productsort' => $productsort,
            'productdirection' => $productdirection,
            'productsearch' => $productsearch,
            'activitysearch' => $activitysearch,
            'activitytype' => $activitytype,
            'productperpage' => $productperpage,
            'activityperpage' => $activityperpage,
            'activitysort' => $activitysort,
            'activitydirection' => $activitydirection,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewreports', $context);

        $params = self::normalise_parameters($params);
        $topproducts = self::get_top_products($params, $context);
        $activity = self::get_recent_activity($params, $context);

        return [
            'topproducts' => $topproducts['items'],
            'topproductstotal' => $topproducts['total'],
            'productpage' => $params['productpage'],
            'activity' => $activity['items'],
            'activitytotal' => $activity['total'],
            'activitypage' => $params['activitypage'],
            'perpage' => $params['perpage'],
            'productperpage' => $params['productperpage'],
            'activityperpage' => $params['activityperpage'],
            'productsort' => $topproducts['sort'],
            'productdirection' => $topproducts['direction'],
            'activitysort' => $activity['sort'],
            'activitydirection' => $activity['direction'],
            'stats' => self::get_stats(),
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'topproducts' => new external_multiple_structure(self::top_product_structure()),
            'topproductstotal' => new external_value(PARAM_INT, 'Total matching wishlisted products.'),
            'productpage' => new external_value(PARAM_INT, 'Current top-products page.'),
            'activity' => new external_multiple_structure(self::activity_structure()),
            'activitytotal' => new external_value(PARAM_INT, 'Total matching recent activity rows.'),
            'activitypage' => new external_value(PARAM_INT, 'Current recent-activity page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'productperpage' => new external_value(PARAM_INT, 'Top-products records per page.'),
            'activityperpage' => new external_value(PARAM_INT, 'Recent-activity records per page.'),
            'productsort' => new external_value(PARAM_ALPHANUMEXT, 'Applied top-products sort key.'),
            'productdirection' => new external_value(PARAM_ALPHA, 'Applied top-products sort direction.'),
            'activitysort' => new external_value(PARAM_ALPHANUMEXT, 'Applied recent-activity sort key.'),
            'activitydirection' => new external_value(PARAM_ALPHA, 'Applied recent-activity sort direction.'),
            'stats' => self::stats_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Top product row structure.
     *
     * @return external_single_structure
     */
    private static function top_product_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Product ID.'),
            'name' => new external_value(PARAM_TEXT, 'Product name.'),
            'sku' => new external_value(PARAM_TEXT, 'Product SKU.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Product type label.'),
            'savedcount' => new external_value(PARAM_INT, 'Wishlist save count.'),
            'customercount' => new external_value(PARAM_INT, 'Unique customers who saved this product.'),
            'amount' => new external_value(PARAM_FLOAT, 'Regular price amount.'),
            'hasprice' => new external_value(PARAM_BOOL, 'Whether the product has a regular price.'),
            'displayprice' => new external_value(PARAM_TEXT, 'Formatted regular price.'),
            'lastsaved' => new external_value(PARAM_INT, 'Latest saved timestamp.'),
            'displaylastsaved' => new external_value(PARAM_TEXT, 'Formatted latest saved date.'),
            'producturl' => new external_value(PARAM_URL, 'Admin pricing page URL for this product.'),
        ]);
    }

    /**
     * Activity row structure.
     *
     * @return external_single_structure
     */
    private static function activity_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Wishlist row ID.'),
            'customerid' => new external_value(PARAM_INT, 'Customer user ID.'),
            'customername' => new external_value(PARAM_TEXT, 'Customer full name.'),
            'customeremail' => new external_value(PARAM_TEXT, 'Customer email.'),
            'customerurl' => new external_value(PARAM_URL, 'Modern Commerce customer detail URL.'),
            'productid' => new external_value(PARAM_INT, 'Product ID.'),
            'productname' => new external_value(PARAM_TEXT, 'Product name.'),
            'sku' => new external_value(PARAM_TEXT, 'Product SKU.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Product type label.'),
            'timecreated' => new external_value(PARAM_INT, 'Saved timestamp.'),
            'displaydate' => new external_value(PARAM_TEXT, 'Formatted saved date.'),
            'producturl' => new external_value(PARAM_URL, 'Admin pricing page URL for this product.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'saveditems' => new external_value(PARAM_INT, 'Total saved wishlist rows.'),
            'productcount' => new external_value(PARAM_INT, 'Total unique products saved.'),
            'customercount' => new external_value(PARAM_INT, 'Total unique customers with saved products.'),
            'lastsaved' => new external_value(PARAM_TEXT, 'Formatted latest saved date.'),
        ]);
    }

    /**
     * Normalise parameters.
     *
     * @param array $params Validated parameters.
     * @return array
     */
    private static function normalise_parameters(array $params): array {
        $params['search'] = trim((string) $params['search']);
        $params['productsearch'] = trim((string) (
            $params['productsearch'] !== '' ? $params['productsearch'] : $params['search']
        ));
        $params['activitysearch'] = trim((string) (
            $params['activitysearch'] !== '' ? $params['activitysearch'] : $params['search']
        ));
        $params['productpage'] = max(0, (int) $params['productpage']);
        $params['activitypage'] = max(0, (int) $params['activitypage']);
        $params['perpage'] = min(100, max(5, (int) $params['perpage']));
        $params['productperpage'] = (int) $params['productperpage'] > 0
            ? min(100, max(5, (int) $params['productperpage']))
            : $params['perpage'];
        $params['activityperpage'] = (int) $params['activityperpage'] > 0
            ? min(100, max(5, (int) $params['activityperpage']))
            : $params['perpage'];

        if (!in_array($params['producttype'], self::PRODUCT_TYPES, true)) {
            $params['producttype'] = '';
        }
        if ($params['activitytype'] === '' && $params['producttype'] !== '') {
            $params['activitytype'] = $params['producttype'];
        }
        if (!in_array($params['activitytype'], self::PRODUCT_TYPES, true)) {
            $params['activitytype'] = '';
        }

        return $params;
    }

    /**
     * Get top wishlisted products.
     *
     * @param array $params Normalised params.
     * @param context_system $context System context.
     * @return array
     */
    private static function get_top_products(array $params, context_system $context): array {
        global $DB;

        [$wheresql, $sqlparams] = self::build_product_filter_sql($params);
        [$sortkey, $orderbysql, $direction] = self::get_top_product_order_by_sql(
            (string) $params['productsort'],
            (string) $params['productdirection']
        );

        $countsql = "SELECT COUNT(DISTINCT p.id)
                       FROM {local_moderncommerce_wishlist} w
                       JOIN {user} u ON u.id = w.userid
                       JOIN {local_moderncommerce_products} p ON p.id = w.productid
                      {$wheresql}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $now = time();
        $selectparams = $sqlparams + ['pricetype' => 'regular', 'nowstart' => $now, 'nowend' => $now];
        $selectsql = "SELECT p.id,
                             p.name,
                             p.sku,
                             p.producttype,
                             COUNT(w.id) AS savedcount,
                             COUNT(DISTINCT w.userid) AS customercount,
                             MAX(w.timecreated) AS lastsaved,
                             pr.amount
                        FROM {local_moderncommerce_wishlist} w
                        JOIN {user} u ON u.id = w.userid
                        JOIN {local_moderncommerce_products} p ON p.id = w.productid
                   LEFT JOIN {local_moderncommerce_product_prices} pr ON pr.id = (
                                 SELECT MIN(prmin.id)
                                   FROM {local_moderncommerce_product_prices} prmin
                                  WHERE prmin.productid = p.id
                                    AND prmin.pricetype = :pricetype
                                    AND prmin.enabled = 1
                                    AND (prmin.startdate IS NULL OR prmin.startdate = 0 OR prmin.startdate <= :nowstart)
                                    AND (prmin.enddate IS NULL OR prmin.enddate = 0 OR prmin.enddate >= :nowend)
                             )
                       {$wheresql}
                    GROUP BY p.id, p.name, p.sku, p.producttype, pr.amount
                    ORDER BY {$orderbysql}, p.name ASC, p.id DESC";

        $records = $DB->get_records_sql(
            $selectsql,
            $selectparams,
            $params['productpage'] * $params['productperpage'],
            $params['productperpage']
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_top_product($record, $context);
        }

        return [
            'items' => $items,
            'total' => $total,
            'sort' => $sortkey,
            'direction' => $direction,
        ];
    }

    /**
     * Get recent wishlist activity.
     *
     * @param array $params Normalised params.
     * @param context_system $context System context.
     * @return array
     */
    private static function get_recent_activity(array $params, context_system $context): array {
        global $DB;

        [$wheresql, $sqlparams] = self::build_activity_filter_sql($params);
        [$sortkey, $orderbysql, $direction] = self::get_activity_order_by_sql(
            (string) $params['activitysort'],
            (string) $params['activitydirection']
        );

        $countsql = "SELECT COUNT(w.id)
                       FROM {local_moderncommerce_wishlist} w
                       JOIN {user} u ON u.id = w.userid
                       JOIN {local_moderncommerce_products} p ON p.id = w.productid
                      {$wheresql}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $selectsql = "SELECT w.id,
                             w.timecreated,
                             p.id AS productid,
                             p.name,
                             p.sku,
                             p.producttype,
                             u.id AS userid,
                             u.firstname,
                             u.lastname,
                             u.firstnamephonetic,
                             u.lastnamephonetic,
                             u.middlename,
                             u.alternatename,
                             u.email
                        FROM {local_moderncommerce_wishlist} w
                        JOIN {user} u ON u.id = w.userid
                        JOIN {local_moderncommerce_products} p ON p.id = w.productid
                       {$wheresql}
                    ORDER BY {$orderbysql}, w.id DESC";

        $records = $DB->get_records_sql(
            $selectsql,
            $sqlparams,
            $params['activitypage'] * $params['activityperpage'],
            $params['activityperpage']
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_activity($record, $context);
        }

        return [
            'items' => $items,
            'total' => $total,
            'sort' => $sortkey,
            'direction' => $direction,
        ];
    }

    /**
     * Build filters for top product queries.
     *
     * @param array $params Normalised params.
     * @return array [where, params]
     */
    private static function build_product_filter_sql(array $params): array {
        global $DB;

        $where = ['u.deleted = 0'];
        $sqlparams = [];

        if ($params['producttype'] !== '') {
            $where[] = 'p.producttype = :producttype';
            $sqlparams['producttype'] = $params['producttype'];
        }

        if ($params['productsearch'] !== '') {
            $search = '%' . $DB->sql_like_escape($params['productsearch']) . '%';
            $where[] = '(' .
                $DB->sql_like('p.name', ':searchproductname', false) . ' OR ' .
                $DB->sql_like('p.sku', ':searchproductsku', false) . ' OR ' .
                $DB->sql_like('u.firstname', ':searchproductfirst', false) . ' OR ' .
                $DB->sql_like('u.lastname', ':searchproductlast', false) . ' OR ' .
                $DB->sql_like('u.email', ':searchproductemail', false) .
            ')';
            $sqlparams['searchproductname'] = $search;
            $sqlparams['searchproductsku'] = $search;
            $sqlparams['searchproductfirst'] = $search;
            $sqlparams['searchproductlast'] = $search;
            $sqlparams['searchproductemail'] = $search;
        }

        return ['WHERE ' . implode(' AND ', $where), $sqlparams];
    }

    /**
     * Build filters for recent activity queries.
     *
     * @param array $params Normalised params.
     * @return array [where, params]
     */
    private static function build_activity_filter_sql(array $params): array {
        global $DB;

        $where = ['u.deleted = 0'];
        $sqlparams = [];

        if ($params['activitytype'] !== '') {
            $where[] = 'p.producttype = :activityproducttype';
            $sqlparams['activityproducttype'] = $params['activitytype'];
        }

        if ($params['activitysearch'] !== '') {
            $search = '%' . $DB->sql_like_escape($params['activitysearch']) . '%';
            $where[] = '(' .
                $DB->sql_like('p.name', ':activityproductname', false) . ' OR ' .
                $DB->sql_like('p.sku', ':activityproductsku', false) . ' OR ' .
                $DB->sql_like('u.firstname', ':activityfirstname', false) . ' OR ' .
                $DB->sql_like('u.lastname', ':activitylastname', false) . ' OR ' .
                $DB->sql_like('u.email', ':activityemail', false) .
            ')';
            $sqlparams['activityproductname'] = $search;
            $sqlparams['activityproductsku'] = $search;
            $sqlparams['activityfirstname'] = $search;
            $sqlparams['activitylastname'] = $search;
            $sqlparams['activityemail'] = $search;
        }

        return ['WHERE ' . implode(' AND ', $where), $sqlparams];
    }

    /**
     * Convert top-products sort input into safe SQL.
     *
     * @param string $sort Sort key.
     * @param string $direction Direction.
     * @return array [key, order by SQL, direction]
     */
    private static function get_top_product_order_by_sql(string $sort, string $direction): array {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $sortkey = in_array($sort, ['name', 'producttype', 'savedcount', 'customercount', 'amount', 'lastsaved'], true)
            ? $sort
            : 'savedcount';

        $sortmap = [
            'name' => "p.name {$direction}",
            'producttype' => "p.producttype {$direction}",
            'savedcount' => "COUNT(w.id) {$direction}",
            'customercount' => "COUNT(DISTINCT w.userid) {$direction}",
            'amount' => "pr.amount {$direction}",
            'lastsaved' => "MAX(w.timecreated) {$direction}",
        ];

        return [$sortkey, $sortmap[$sortkey], $direction];
    }

    /**
     * Convert recent-activity sort input into safe SQL.
     *
     * @param string $sort Sort key.
     * @param string $direction Direction.
     * @return array [key, order by SQL, direction]
     */
    private static function get_activity_order_by_sql(string $sort, string $direction): array {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $sortkey = in_array($sort, ['customer', 'product', 'producttype', 'timecreated'], true)
            ? $sort
            : 'timecreated';

        $sortmap = [
            'customer' => "u.lastname {$direction}, u.firstname {$direction}, u.email {$direction}",
            'product' => "p.name {$direction}",
            'producttype' => "p.producttype {$direction}",
            'timecreated' => "w.timecreated {$direction}",
        ];

        return [$sortkey, $sortmap[$sortkey], $direction];
    }

    /**
     * Format one top product row.
     *
     * @param \stdClass $record Top product aggregate row.
     * @param context_system $context System context.
     * @return array
     */
    private static function format_top_product(\stdClass $record, context_system $context): array {
        $amount = $record->amount === null ? 0.0 : (float) $record->amount;
        $hasprice = $record->amount !== null;

        return [
            'id' => (int) $record->id,
            'name' => format_string((string) $record->name, true, ['context' => $context]),
            'sku' => (string) ($record->sku ?? ''),
            'producttype' => (string) $record->producttype,
            'typelabel' => self::product_type_label((string) $record->producttype),
            'savedcount' => (int) ($record->savedcount ?? 0),
            'customercount' => (int) ($record->customercount ?? 0),
            'amount' => $amount,
            'hasprice' => $hasprice,
            'displayprice' => $hasprice ? pricing_service::format_price($amount) : '-',
            'lastsaved' => (int) ($record->lastsaved ?? 0),
            'displaylastsaved' => self::format_time((int) ($record->lastsaved ?? 0), true),
            'producturl' => self::product_url($record),
        ];
    }

    /**
     * Format one recent activity row.
     *
     * @param \stdClass $record Wishlist activity row.
     * @param context_system $context System context.
     * @return array
     */
    private static function format_activity(\stdClass $record, context_system $context): array {
        $customerid = (int) $record->userid;

        return [
            'id' => (int) $record->id,
            'customerid' => $customerid,
            'customername' => fullname($record),
            'customeremail' => (string) $record->email,
            'customerurl' => (new moodle_url('/local/moderncommerce/admin/customer.php', ['id' => $customerid]))->out(false),
            'productid' => (int) $record->productid,
            'productname' => format_string((string) $record->name, true, ['context' => $context]),
            'sku' => (string) ($record->sku ?? ''),
            'producttype' => (string) $record->producttype,
            'typelabel' => self::product_type_label((string) $record->producttype),
            'timecreated' => (int) $record->timecreated,
            'displaydate' => self::format_time((int) $record->timecreated, true),
            'producturl' => self::product_url($record),
        ];
    }

    /**
     * Get summary statistics.
     *
     * @return array
     */
    private static function get_stats(): array {
        global $DB;

        $sql = "SELECT COUNT(w.id) AS saveditems,
                       COUNT(DISTINCT w.productid) AS productcount,
                       COUNT(DISTINCT w.userid) AS customercount,
                       MAX(w.timecreated) AS lastsaved
                  FROM {local_moderncommerce_wishlist} w
                  JOIN {user} u ON u.id = w.userid
                 WHERE u.deleted = 0";
        $stats = $DB->get_record_sql($sql) ?: (object) [];

        return [
            'saveditems' => (int) ($stats->saveditems ?? 0),
            'productcount' => (int) ($stats->productcount ?? 0),
            'customercount' => (int) ($stats->customercount ?? 0),
            'lastsaved' => self::format_time((int) ($stats->lastsaved ?? 0), true),
        ];
    }

    /**
     * Format a timestamp.
     *
     * @param int $timestamp Timestamp.
     * @param bool $datetime Whether to include time.
     * @return string
     */
    private static function format_time(int $timestamp, bool $datetime): string {
        if ($timestamp <= 0) {
            return '';
        }

        $format = $datetime ? get_string('strftimedatetime', 'langconfig') : get_string('strftimedate');
        return userdate($timestamp, $format);
    }

    /**
     * Localised product type label.
     *
     * @param string $producttype Product type.
     * @return string
     */
    private static function product_type_label(string $producttype): string {
        if (get_string_manager()->string_exists($producttype, 'local_moderncommerce')) {
            return get_string($producttype, 'local_moderncommerce');
        }
        if (get_string_manager()->string_exists($producttype, 'core')) {
            return get_string($producttype);
        }

        return ucfirst($producttype);
    }

    /**
     * Admin product URL for cross-navigation.
     *
     * @param \stdClass $record Product row.
     * @return string
     */
    private static function product_url(\stdClass $record): string {
        $search = trim((string) ($record->sku ?? ''));
        if ($search === '') {
            $search = (string) ($record->name ?? '');
        }

        return (new moodle_url('/local/moderncommerce/admin/pricing.php', ['search' => $search]))->out(false);
    }
}
