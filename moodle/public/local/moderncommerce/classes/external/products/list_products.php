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
 * External API for listing catalog products.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\products;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\pricing_service;

/**
 * List products for the admin pricing/catalog screen.
 */
class list_products extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search product name, slug, or SKU.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Product status filter.', VALUE_DEFAULT, ''),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type filter.', VALUE_DEFAULT, ''),
            'pricingstatus' => new external_value(PARAM_ALPHANUMEXT, 'Pricing state filter.', VALUE_DEFAULT, ''),
            'categoryid' => new external_value(PARAM_INT, 'Product category ID filter.', VALUE_DEFAULT, 0),
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort key.', VALUE_DEFAULT, 'timecreated'),
            'direction' => new external_value(PARAM_ALPHA, 'Sort direction.', VALUE_DEFAULT, 'DESC'),
        ]);
    }

    /**
     * Execute the product listing.
     *
     * @param string $search Search term.
     * @param string $status Product status filter.
     * @param string $producttype Product type filter.
     * @param string $pricingstatus Pricing status filter.
     * @param int $categoryid Category filter.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @param string $sort Sort key.
     * @param string $direction Sort direction.
     * @return array
     */
    public static function execute(
        string $search = '',
        string $status = '',
        string $producttype = '',
        string $pricingstatus = '',
        int $categoryid = 0,
        int $page = 0,
        int $perpage = 10,
        string $sort = 'timecreated',
        string $direction = 'DESC'
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'status' => $status,
            'producttype' => $producttype,
            'pricingstatus' => $pricingstatus,
            'categoryid' => $categoryid,
            'page' => $page,
            'perpage' => $perpage,
            'sort' => $sort,
            'direction' => $direction,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $params = self::normalise_parameters($params);
        [$joinsql, $wheresql, $sqlparams] = self::build_filter_sql($params);
        [$sortkey, $sortsql, $sortdirection] = self::get_sort_sql($params['sort'], $params['direction']);

        $countsql = "SELECT COUNT(DISTINCT p.id)
                       FROM {local_moderncommerce_products} p
                            {$joinsql}
                      {$wheresql}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $selectsql = "SELECT p.id,
                             p.producttype,
                             p.name,
                             p.slug,
                             p.sku,
                             p.status,
                             p.visible,
                             p.featured,
                             p.shortdescription,
                             p.displayorder,
                             p.timecreated,
                             p.timemodified,
                             pr.id AS priceid,
                             pr.amount AS regularprice,
                             pr.compareamount,
                             inv.stockmanaged,
                             inv.stock,
                             inv.reservedstock,
                             inv.allowbackorder,
                             (SELECT MIN(pccourse.courseid)
                                FROM {local_moderncommerce_product_courses} pccourse
                               WHERE pccourse.productid = p.id
                                 AND pccourse.relationtype = 'included') AS primarycourseid,
                             (SELECT c.fullname
                                FROM {local_moderncommerce_product_courses} pccourse
                                JOIN {course} c ON c.id = pccourse.courseid
                               WHERE pccourse.productid = p.id
                                 AND pccourse.relationtype = 'included'
                                 AND pccourse.id = (
                                     SELECT MIN(pcfirst.id)
                                       FROM {local_moderncommerce_product_courses} pcfirst
                                      WHERE pcfirst.productid = p.id
                                        AND pcfirst.relationtype = 'included'
                                        AND pcfirst.sortorder = (
                                            SELECT MIN(pcsort.sortorder)
                                              FROM {local_moderncommerce_product_courses} pcsort
                                             WHERE pcsort.productid = p.id
                                               AND pcsort.relationtype = 'included'
                                        )
                                 )) AS primarycoursename,
                             (SELECT c.shortname
                                FROM {local_moderncommerce_product_courses} pccourse
                                JOIN {course} c ON c.id = pccourse.courseid
                               WHERE pccourse.productid = p.id
                                 AND pccourse.relationtype = 'included'
                                 AND pccourse.id = (
                                     SELECT MIN(pcfirst.id)
                                       FROM {local_moderncommerce_product_courses} pcfirst
                                      WHERE pcfirst.productid = p.id
                                        AND pcfirst.relationtype = 'included'
                                        AND pcfirst.sortorder = (
                                            SELECT MIN(pcsort.sortorder)
                                              FROM {local_moderncommerce_product_courses} pcsort
                                             WHERE pcsort.productid = p.id
                                               AND pcsort.relationtype = 'included'
                                        )
                                 )) AS primarycourseshortname,
                             (SELECT cc.name
                                FROM {local_moderncommerce_product_courses} pccourse
                                JOIN {course} c ON c.id = pccourse.courseid
                                JOIN {course_categories} cc ON cc.id = c.category
                               WHERE pccourse.productid = p.id
                                 AND pccourse.relationtype = 'included'
                                 AND pccourse.id = (
                                     SELECT MIN(pcfirst.id)
                                       FROM {local_moderncommerce_product_courses} pcfirst
                                      WHERE pcfirst.productid = p.id
                                        AND pcfirst.relationtype = 'included'
                                        AND pcfirst.sortorder = (
                                            SELECT MIN(pcsort.sortorder)
                                              FROM {local_moderncommerce_product_courses} pcsort
                                             WHERE pcsort.productid = p.id
                                               AND pcsort.relationtype = 'included'
                                        )
                                 )) AS primarycoursecategory,
                             (SELECT c.visible
                                FROM {local_moderncommerce_product_courses} pccourse
                                JOIN {course} c ON c.id = pccourse.courseid
                               WHERE pccourse.productid = p.id
                                 AND pccourse.relationtype = 'included'
                                 AND pccourse.id = (
                                     SELECT MIN(pcfirst.id)
                                       FROM {local_moderncommerce_product_courses} pcfirst
                                      WHERE pcfirst.productid = p.id
                                        AND pcfirst.relationtype = 'included'
                                        AND pcfirst.sortorder = (
                                            SELECT MIN(pcsort.sortorder)
                                              FROM {local_moderncommerce_product_courses} pcsort
                                             WHERE pcsort.productid = p.id
                                               AND pcsort.relationtype = 'included'
                                        )
                                 )) AS primarycoursevisible,
                             (SELECT COUNT(1)
                                FROM {local_moderncommerce_product_courses} pc
                               WHERE pc.productid = p.id) AS coursecount,
                             (SELECT COALESCE(SUM(oi.quantity), 0)
                                FROM {local_moderncommerce_order_items} oi
                                JOIN {local_moderncommerce_orders} o ON o.id = oi.orderid
                               WHERE oi.productid = p.id
                                 AND o.status IN ('paid', 'completed')) AS soldcount
                        FROM {local_moderncommerce_products} p
                             {$joinsql}
                       {$wheresql}
                    ORDER BY {$sortsql} {$sortdirection}, p.id ASC";

        $records = $DB->get_records_sql(
            $selectsql,
            $sqlparams,
            $params['page'] * $params['perpage'],
            $params['perpage']
        );

        $productids = array_map(static function ($record): int {
            return (int) $record->id;
        }, $records);
        [$categorynames, $primarycategoryids] = self::get_product_categories($productids, $context);

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_product($record, $categorynames, $primarycategoryids, $context);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'sort' => $sortkey,
            'direction' => $sortdirection,
            'currency' => self::get_currency_data(),
            'stats' => self::get_stats(),
            'categories' => self::get_filter_categories($context),
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
            'items' => new external_multiple_structure(self::product_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching products.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Applied sort key.'),
            'direction' => new external_value(PARAM_ALPHA, 'Applied sort direction.'),
            'currency' => self::currency_structure(),
            'stats' => self::stats_structure(),
            'categories' => new external_multiple_structure(self::category_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Product return structure.
     *
     * @return external_single_structure
     */
    private static function product_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Product ID.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
            'name' => new external_value(PARAM_TEXT, 'Product name.'),
            'slug' => new external_value(PARAM_TEXT, 'Product slug.'),
            'sku' => new external_value(PARAM_TEXT, 'Product SKU.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Product status.'),
            'visible' => new external_value(PARAM_BOOL, 'Whether the product is visible.'),
            'featured' => new external_value(PARAM_BOOL, 'Whether the product is featured.'),
            'shortdescription' => new external_value(PARAM_TEXT, 'Short product description.'),
            'displayorder' => new external_value(PARAM_INT, 'Catalog display order.'),
            'primarycategoryid' => new external_value(PARAM_INT, 'Primary category ID.'),
            'primarycourseid' => new external_value(PARAM_INT, 'Primary linked Moodle course ID.'),
            'primarycoursename' => new external_value(PARAM_TEXT, 'Primary linked Moodle course name.'),
            'primarycourseshortname' => new external_value(PARAM_TEXT, 'Primary linked Moodle course shortname.'),
            'primarycoursecategory' => new external_value(PARAM_TEXT, 'Primary linked Moodle course category.'),
            'primarycoursevisible' => new external_value(PARAM_BOOL, 'Whether primary linked Moodle course is visible.'),
            'categorynames' => new external_value(PARAM_TEXT, 'Comma-separated category names.'),
            'hasprice' => new external_value(PARAM_BOOL, 'Whether a regular enabled price exists.'),
            'onsale' => new external_value(PARAM_BOOL, 'Whether compare-at price is above regular price.'),
            'rawprice' => new external_value(PARAM_FLOAT, 'Raw regular price.'),
            'rawcompareamount' => new external_value(PARAM_FLOAT, 'Raw compare-at amount.'),
            'displayprice' => new external_value(PARAM_TEXT, 'Formatted regular price.'),
            'displaycompareamount' => new external_value(PARAM_TEXT, 'Formatted compare-at amount.'),
            'stockmanaged' => new external_value(PARAM_BOOL, 'Whether inventory is stock-managed.'),
            'stock' => new external_value(PARAM_INT, 'Stock quantity.'),
            'reservedstock' => new external_value(PARAM_INT, 'Reserved stock quantity.'),
            'allowbackorder' => new external_value(PARAM_BOOL, 'Whether backorders are allowed.'),
            'stocklabel' => new external_value(PARAM_TEXT, 'Display stock label.'),
            'coursecount' => new external_value(PARAM_INT, 'Number of linked Moodle courses.'),
            'soldcount' => new external_value(PARAM_FLOAT, 'Paid/completed quantity sold.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
        ]);
    }

    /**
     * Currency return structure.
     *
     * @return external_single_structure
     */
    private static function currency_structure(): external_single_structure {
        return new external_single_structure([
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.'),
            'symbol' => new external_value(PARAM_TEXT, 'Currency symbol.'),
            'position' => new external_value(PARAM_ALPHA, 'Symbol position.'),
            'decimals' => new external_value(PARAM_INT, 'Decimal places.'),
        ]);
    }

    /**
     * Statistics return structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'totalproducts' => new external_value(PARAM_INT, 'Total products.'),
            'activeproducts' => new external_value(PARAM_INT, 'Active products.'),
            'draftproducts' => new external_value(PARAM_INT, 'Draft products.'),
            'visibleproducts' => new external_value(PARAM_INT, 'Visible products.'),
            'featuredproducts' => new external_value(PARAM_INT, 'Featured products.'),
            'courseproducts' => new external_value(PARAM_INT, 'Course products.'),
            'bundleproducts' => new external_value(PARAM_INT, 'Bundle products.'),
            'pricedproducts' => new external_value(PARAM_INT, 'Products with a regular enabled price.'),
            'unpricedproducts' => new external_value(PARAM_INT, 'Products without a regular enabled price.'),
            'onsaleproducts' => new external_value(PARAM_INT, 'Products with compare-at pricing.'),
        ]);
    }

    /**
     * Category return structure.
     *
     * @return external_single_structure
     */
    private static function category_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Category ID.'),
            'name' => new external_value(PARAM_TEXT, 'Category name.'),
            'visible' => new external_value(PARAM_BOOL, 'Whether the category is visible.'),
            'productcount' => new external_value(PARAM_INT, 'Mapped product count.'),
        ]);
    }

    /**
     * Normalise and whitelist request parameters.
     *
     * @param array $params Raw validated parameters.
     * @return array Normalised parameters.
     */
    private static function normalise_parameters(array $params): array {
        $params['search'] = trim((string) $params['search']);
        $params['page'] = max(0, (int) $params['page']);
        $params['perpage'] = min(100, max(5, (int) $params['perpage']));
        $params['categoryid'] = max(0, (int) $params['categoryid']);

        $allowedstatuses = ['', 'active', 'draft', 'inactive', 'archived'];
        if (!in_array($params['status'], $allowedstatuses, true)) {
            $params['status'] = '';
        }

        $allowedtypes = ['', 'course', 'bundle', 'program', 'subscription', 'digital'];
        if (!in_array($params['producttype'], $allowedtypes, true)) {
            $params['producttype'] = '';
        }

        $allowedpricingstatuses = ['', 'priced', 'unpriced', 'onsale'];
        if (!in_array($params['pricingstatus'], $allowedpricingstatuses, true)) {
            $params['pricingstatus'] = '';
        }

        return $params;
    }

    /**
     * Build filter SQL fragments.
     *
     * @param array $params Normalised parameters.
     * @return array SQL join, where, and params.
     */
    private static function build_filter_sql(array $params): array {
        global $DB;

        $joinsql = "LEFT JOIN {local_moderncommerce_product_prices} pr ON pr.id = (
                         SELECT MIN(prmin.id)
                           FROM {local_moderncommerce_product_prices} prmin
                          WHERE prmin.productid = p.id
                            AND prmin.pricetype = :regularpricetype
                            AND prmin.enabled = 1
                     )
                    LEFT JOIN {local_moderncommerce_product_inventory} inv ON inv.productid = p.id";
        $where = ['1 = 1'];
        $sqlparams = [
            'regularpricetype' => 'regular',
        ];

        if ($params['search'] !== '') {
            $where[] = '(' .
                $DB->sql_like('p.name', ':searchname', false) . ' OR ' .
                $DB->sql_like('p.slug', ':searchslug', false) . ' OR ' .
                $DB->sql_like('p.sku', ':searchsku', false) .
            ')';
            $search = '%' . $DB->sql_like_escape($params['search']) . '%';
            $sqlparams['searchname'] = $search;
            $sqlparams['searchslug'] = $search;
            $sqlparams['searchsku'] = $search;
        }

        if ($params['status'] !== '') {
            $where[] = 'p.status = :status';
            $sqlparams['status'] = $params['status'];
        }

        if ($params['producttype'] !== '') {
            $where[] = 'p.producttype = :producttype';
            $sqlparams['producttype'] = $params['producttype'];
        }

        if ($params['categoryid'] > 0) {
            $where[] = "EXISTS (
                SELECT 1
                  FROM {local_moderncommerce_product_category_map} pcmfilter
                 WHERE pcmfilter.productid = p.id
                   AND pcmfilter.categoryid = :categoryid
            )";
            $sqlparams['categoryid'] = $params['categoryid'];
        }

        if ($params['pricingstatus'] === 'priced') {
            $where[] = 'pr.id IS NOT NULL';
        } else if ($params['pricingstatus'] === 'unpriced') {
            $where[] = 'pr.id IS NULL';
        } else if ($params['pricingstatus'] === 'onsale') {
            $where[] = 'pr.compareamount IS NOT NULL AND pr.compareamount > pr.amount';
        }

        return [
            $joinsql,
            'WHERE ' . implode(' AND ', $where),
            $sqlparams,
        ];
    }

    /**
     * Convert sort input into safe SQL.
     *
     * @param string $sort Sort key.
     * @param string $direction Direction.
     * @return array Applied key, SQL field, direction.
     */
    private static function get_sort_sql(string $sort, string $direction): array {
        $sortmap = [
            'name' => 'p.name',
            'sku' => 'p.sku',
            'producttype' => 'p.producttype',
            'status' => 'p.status',
            'visible' => 'p.visible',
            'featured' => 'p.featured',
            'price' => 'pr.amount',
            'stock' => 'inv.stock',
            'sold' => 'soldcount',
            'displayorder' => 'p.displayorder',
            'timecreated' => 'p.timecreated',
            'timemodified' => 'p.timemodified',
        ];

        $sortkey = array_key_exists($sort, $sortmap) ? $sort : 'timecreated';
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return [$sortkey, $sortmap[$sortkey], $direction];
    }

    /**
     * Get category names for a set of products.
     *
     * @param array $productids Product IDs.
     * @param context_system $context System context.
     * @return array Category names and primary category IDs by product ID.
     */
    private static function get_product_categories(array $productids, context_system $context): array {
        global $DB;

        if (empty($productids)) {
            return [[], []];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($productids, SQL_PARAMS_NAMED, 'productid');
        $sql = "SELECT pcm.id,
                       pcm.productid,
                       pcm.categoryid,
                       pcm.isprimary,
                       pc.name
                  FROM {local_moderncommerce_product_category_map} pcm
                  JOIN {local_moderncommerce_product_categories} pc ON pc.id = pcm.categoryid
                 WHERE pcm.productid {$insql}
              ORDER BY pcm.productid ASC, pcm.isprimary DESC, pc.sortorder ASC, pc.name ASC";
        $records = $DB->get_records_sql($sql, $inparams);

        $names = [];
        $primaryids = [];
        foreach ($records as $record) {
            $productid = (int) $record->productid;
            if (!isset($names[$productid])) {
                $names[$productid] = [];
            }
            $names[$productid][] = format_string($record->name, true, ['context' => $context]);

            if (!isset($primaryids[$productid]) || (int) $record->isprimary === 1) {
                $primaryids[$productid] = (int) $record->categoryid;
            }
        }

        $categorynames = [];
        foreach ($names as $productid => $productnames) {
            $categorynames[$productid] = implode(', ', $productnames);
        }

        return [$categorynames, $primaryids];
    }

    /**
     * Format one product record for the webservice response.
     *
     * @param \stdClass $record Product record.
     * @param array $categorynames Category names by product.
     * @param array $primarycategoryids Primary category IDs by product.
     * @param context_system $context System context.
     * @return array
     */
    private static function format_product(
        \stdClass $record,
        array $categorynames,
        array $primarycategoryids,
        context_system $context
    ): array {
        $productid = (int) $record->id;
        $hasprice = !empty($record->priceid);
        $rawprice = $hasprice ? (float) $record->regularprice : 0.0;
        $rawcompareamount = $hasprice && $record->compareamount !== null ? (float) $record->compareamount : 0.0;
        $onsale = $hasprice && $rawcompareamount > $rawprice;
        $stockmanaged = !empty($record->stockmanaged);
        $stock = $record->stock === null ? 0 : (int) $record->stock;
        $reservedstock = $record->reservedstock === null ? 0 : (int) $record->reservedstock;

        return [
            'id' => $productid,
            'producttype' => (string) $record->producttype,
            'name' => format_string($record->name, true, ['context' => $context]),
            'slug' => (string) $record->slug,
            'sku' => (string) $record->sku,
            'status' => (string) $record->status,
            'visible' => (bool) $record->visible,
            'featured' => (bool) $record->featured,
            'shortdescription' => trim(strip_tags((string) $record->shortdescription)),
            'displayorder' => (int) $record->displayorder,
            'primarycategoryid' => $primarycategoryids[$productid] ?? 0,
            'primarycourseid' => $record->primarycourseid === null ? 0 : (int) $record->primarycourseid,
            'primarycoursename' => $record->primarycoursename === null
                ? ''
                : format_string($record->primarycoursename, true, ['context' => $context]),
            'primarycourseshortname' => $record->primarycourseshortname === null
                ? ''
                : format_string($record->primarycourseshortname, true, ['context' => $context]),
            'primarycoursecategory' => $record->primarycoursecategory === null
                ? ''
                : format_string($record->primarycoursecategory, true, ['context' => $context]),
            'primarycoursevisible' => $record->primarycoursevisible === null ? false : !empty($record->primarycoursevisible),
            'categorynames' => $categorynames[$productid] ?? '',
            'hasprice' => $hasprice,
            'onsale' => $onsale,
            'rawprice' => $rawprice,
            'rawcompareamount' => $rawcompareamount,
            'displayprice' => $hasprice ? pricing_service::format_price($rawprice) : '',
            'displaycompareamount' => $onsale ? pricing_service::format_price($rawcompareamount) : '',
            'stockmanaged' => $stockmanaged,
            'stock' => $stock,
            'reservedstock' => $reservedstock,
            'allowbackorder' => (bool) $record->allowbackorder,
            'stocklabel' => $stockmanaged
                ? (string) max(0, $stock - $reservedstock)
                : get_string('unlimited', 'local_moderncommerce'),
            'coursecount' => (int) $record->coursecount,
            'soldcount' => (float) $record->soldcount,
            'timecreated' => (int) $record->timecreated,
            'timemodified' => (int) $record->timemodified,
        ];
    }

    /**
     * Get configured currency data.
     *
     * @return array
     */
    private static function get_currency_data(): array {
        $config = pricing_service::get_currency_config();

        return [
            'code' => (string) $config->currency,
            'symbol' => (string) $config->symbol,
            'position' => (string) $config->position,
            'decimals' => (int) $config->decimals,
        ];
    }

    /**
     * Get product summary statistics.
     *
     * @return array
     */
    private static function get_stats(): array {
        global $DB;

        $total = (int) $DB->count_records('local_moderncommerce_products');
        $priced = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT p.id)
               FROM {local_moderncommerce_products} p
               JOIN {local_moderncommerce_product_prices} pr ON pr.productid = p.id
              WHERE pr.pricetype = :pricetype
                AND pr.enabled = 1",
            ['pricetype' => 'regular']
        );
        $onsale = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT p.id)
               FROM {local_moderncommerce_products} p
               JOIN {local_moderncommerce_product_prices} pr ON pr.productid = p.id
              WHERE pr.pricetype = :pricetype
                AND pr.enabled = 1
                AND pr.compareamount IS NOT NULL
                AND pr.compareamount > pr.amount",
            ['pricetype' => 'regular']
        );

        return [
            'totalproducts' => $total,
            'activeproducts' => (int) $DB->count_records('local_moderncommerce_products', ['status' => 'active']),
            'draftproducts' => (int) $DB->count_records('local_moderncommerce_products', ['status' => 'draft']),
            'visibleproducts' => (int) $DB->count_records('local_moderncommerce_products', ['visible' => 1]),
            'featuredproducts' => (int) $DB->count_records('local_moderncommerce_products', ['featured' => 1]),
            'courseproducts' => (int) $DB->count_records('local_moderncommerce_products', ['producttype' => 'course']),
            'bundleproducts' => (int) $DB->count_records('local_moderncommerce_products', ['producttype' => 'bundle']),
            'pricedproducts' => $priced,
            'unpricedproducts' => max(0, $total - $priced),
            'onsaleproducts' => $onsale,
        ];
    }

    /**
     * Get category filter options.
     *
     * @param context_system $context System context.
     * @return array
     */
    private static function get_filter_categories(context_system $context): array {
        global $DB;

        $sql = "SELECT pc.id,
                       pc.name,
                       pc.visible,
                       COUNT(pcm.productid) AS productcount
                  FROM {local_moderncommerce_product_categories} pc
             LEFT JOIN {local_moderncommerce_product_category_map} pcm ON pcm.categoryid = pc.id
              GROUP BY pc.id, pc.name, pc.visible, pc.sortorder
              ORDER BY pc.sortorder ASC, pc.name ASC";
        $records = $DB->get_records_sql($sql);
        $categories = [];

        foreach ($records as $record) {
            $categories[] = [
                'id' => (int) $record->id,
                'name' => format_string($record->name, true, ['context' => $context]),
                'visible' => (bool) $record->visible,
                'productcount' => (int) $record->productcount,
            ];
        }

        return $categories;
    }
}
