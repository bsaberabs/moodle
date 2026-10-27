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
 * Report data builder for Modern Commerce admin reports and CSV export.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

use context_system;
use local_moderncommerce\localisation;
use moodle_url;

/**
 * Centralises report filtering, columns, tables, metrics, charts, and export rows.
 */
class report_service {
    /** @var string[] Report types. */
    public const TYPES = ['sales', 'courses', 'coupons'];

    /** @var string[] Period buckets. */
    public const PERIODS = ['daily', 'weekly', 'monthly', 'yearly'];

    /** @var int Maximum rows returned for one table page. */
    private const MAX_PER_PAGE = 100;

    /** @var int[] Allowed 12-grid spans. */
    private const SIZES = [12, 6, 4, 3];

    /**
     * Normalise report request values.
     *
     * @param string $type Report type.
     * @param string $period Period bucket.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @return array{type: string, period: string, from: int, to: int}
     */
    public static function normalise_request(string $type, string $period, int $from, int $to): array {
        $type = in_array($type, self::TYPES, true) ? $type : 'sales';
        $period = in_array($period, self::PERIODS, true) ? $period : 'monthly';
        $to = $to > 0 ? $to : time();
        $from = $from > 0 ? $from : strtotime('-30 days', $to);

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['type' => $type, 'period' => $period, 'from' => $from, 'to' => $to];
    }

    /**
     * Parse CSV or array column request input.
     *
     * @param array $columns Array input, usually columns[]=...
     * @param string $csv CSV input, usually columns=a,b,c
     * @return string[]
     */
    public static function parse_columns(array $columns = [], string $csv = ''): array {
        $out = [];
        foreach ($columns as $column) {
            $out[] = (string) $column;
        }
        if ($csv !== '') {
            foreach (explode(',', $csv) as $column) {
                $out[] = trim($column);
            }
        }
        return $out;
    }

    /**
     * Build the full report API payload.
     *
     * @param string $type Report type.
     * @param string $period Bucket.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context System context.
     * @param array $selectedcolumns Requested selected columns.
     * @param int $page Zero-based table page.
     * @param int $perpage Rows per page.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @return array
     */
    public static function get_report(
        string $type,
        string $period,
        int $from,
        int $to,
        context_system $context,
        array $selectedcolumns = [],
        int $page = 0,
        int $perpage = 10,
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = ''
    ): array {
        $request = self::normalise_request($type, $period, $from, $to);
        $type = $request['type'];
        $period = $request['period'];
        $from = $request['from'];
        $to = $request['to'];
        $columns = self::clean_columns($type, $selectedcolumns);
        $page = max(0, $page);
        $perpage = min(self::MAX_PER_PAGE, max(1, $perpage));
        $productsearch = self::normalise_search($productsearch);
        $coursesearch = self::normalise_search($coursesearch);
        $tablesearch = self::normalise_search($tablesearch);
        $table = self::get_table(
            $type,
            $from,
            $to,
            $context,
            $columns,
            $perpage,
            $page * $perpage,
            $productsearch,
            $coursesearch,
            $tablesearch
        );

        return [
            'type' => $type,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'stats' => self::get_stats($from, $to, $productsearch, $coursesearch),
            'metrics' => self::get_metrics($from, $to, $productsearch, $coursesearch),
            'charts' => self::get_charts($type, $period, $from, $to, $context, $productsearch, $coursesearch),
            'sales' => $type === 'sales'
                ? self::get_sales($period, $from, $to, $productsearch, $coursesearch)
                : self::empty_sales(),
            'courses' => $type === 'courses'
                ? self::get_courses($from, $to, $context, 20, 0, $productsearch, $coursesearch)
                : [],
            'coupons' => $type === 'coupons' ? self::get_coupons($from, $to, $productsearch, $coursesearch) : [],
            'availablecolumns' => array_values(self::column_catalog($type)),
            'selectedcolumns' => $columns,
            'tablerows' => $table['rows'],
            'tabletotal' => $table['total'],
            'tablepage' => $page,
            'tableperpage' => $perpage,
            'tabletruncated' => $table['truncated'],
            'warnings' => [],
        ];
    }

    /**
     * Build CSV export data.
     *
     * @param string $type Report type.
     * @param string $period Bucket.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context System context.
     * @param array $selectedcolumns Requested selected columns.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @return array{filename: string, headers: array, rows: array}
     */
    public static function get_export(
        string $type,
        string $period,
        int $from,
        int $to,
        context_system $context,
        array $selectedcolumns = [],
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = ''
    ): array {
        $request = self::normalise_request($type, $period, $from, $to);
        $type = $request['type'];
        $from = $request['from'];
        $to = $request['to'];
        $columns = self::clean_columns($type, $selectedcolumns);
        $catalog = self::column_catalog($type);
        $productsearch = self::normalise_search($productsearch);
        $coursesearch = self::normalise_search($coursesearch);
        $tablesearch = self::normalise_search($tablesearch);
        $table = self::get_table($type, $from, $to, $context, $columns, 0, 0, $productsearch, $coursesearch, $tablesearch);
        $headers = [];

        foreach ($columns as $column) {
            $headers[] = $catalog[$column]['label'] ?? $column;
        }

        $rows = [];
        foreach ($table['rows'] as $row) {
            $values = [];
            foreach ($row['cells'] as $cell) {
                $values[] = $cell['exportvalue'] ?? $cell['value'];
            }
            $rows[] = $values;
        }

        return [
            'filename' => sprintf(
                'moderncommerce_%s_report_%s_%s.csv',
                $type,
                date('Y-m-d', $from),
                date('Y-m-d', $to)
            ),
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * Report column catalog.
     *
     * @param string $type Report type.
     * @return array<string, array{key: string, label: string, default: bool, align: string}>
     */
    public static function column_catalog(string $type): array {
        switch ($type) {
            case 'courses':
                return self::columns([
                    ['rank', get_string('rank', 'local_moderncommerce'), true, 'left'],
                    ['course', get_string('course'), true, 'left'],
                    ['orders', get_string('orders', 'local_moderncommerce'), true, 'right'],
                    ['units', get_string('unitssold', 'local_moderncommerce'), true, 'right'],
                    ['revenue', get_string('revenue', 'local_moderncommerce'), true, 'right'],
                ]);

            case 'coupons':
                return self::columns([
                    ['coupon', get_string('coupon', 'local_moderncommerce'), true, 'left'],
                    ['type', get_string('type', 'local_moderncommerce'), true, 'left'],
                    ['value', get_string('value', 'local_moderncommerce'), true, 'right'],
                    ['usage', get_string('usage', 'local_moderncommerce'), true, 'right'],
                    ['totaldiscount', get_string('totaldiscount', 'local_moderncommerce'), true, 'right'],
                ]);

            case 'sales':
            default:
                return self::columns([
                    ['orderdate', get_string('orderdate', 'local_moderncommerce'), true, 'left'],
                    ['ordernumber', get_string('ordernumber', 'local_moderncommerce'), true, 'left'],
                    ['customername', get_string('customername', 'local_moderncommerce'), true, 'left'],
                    ['customeremail', get_string('customeremail', 'local_moderncommerce'), true, 'left'],
                    ['product', get_string('product', 'local_moderncommerce'), true, 'left'],
                    ['course', get_string('course'), true, 'left'],
                    ['quantity', get_string('quantity', 'local_moderncommerce'), true, 'right'],
                    ['unitprice', get_string('unitprice', 'local_moderncommerce'), true, 'right'],
                    ['linetotal', get_string('linetotal', 'local_moderncommerce'), true, 'right'],
                    ['status', get_string('status'), true, 'left'],
                    ['userid', get_string('userid', 'local_moderncommerce'), false, 'right'],
                    ['producttype', get_string('producttype', 'local_moderncommerce'), false, 'left'],
                    ['sku', get_string('sku', 'local_moderncommerce'), false, 'left'],
                    ['courseid', get_string('courseid', 'local_moderncommerce'), false, 'right'],
                    ['subtotal', get_string('subtotal', 'local_moderncommerce'), false, 'right'],
                    ['discount', get_string('discount', 'local_moderncommerce'), false, 'right'],
                    ['tax', get_string('tax', 'local_moderncommerce'), false, 'right'],
                    ['ordertotal', get_string('ordertotal', 'local_moderncommerce'), false, 'right'],
                    ['currency', get_string('currency', 'local_moderncommerce'), false, 'left'],
                    ['couponcode', get_string('couponcode', 'local_moderncommerce'), false, 'left'],
                    ['paymentgateway', get_string('paymentgateway', 'local_moderncommerce'), false, 'left'],
                    ['transactionreference', get_string('transactionreference', 'local_moderncommerce'), false, 'left'],
                    ['billingcity', get_string('billingcity', 'local_moderncommerce'), false, 'left'],
                    ['billingcountry', get_string('billingcountry', 'local_moderncommerce'), false, 'left'],
                    ['paiddate', get_string('paiddate', 'local_moderncommerce'), false, 'left'],
                ]);
        }
    }

    /**
     * Clean requested columns against the report catalog.
     *
     * @param string $type Report type.
     * @param array $columns Requested columns.
     * @return string[]
     */
    public static function clean_columns(string $type, array $columns): array {
        $catalog = self::column_catalog($type);
        $out = [];
        foreach ($columns as $column) {
            $column = (string) $column;
            if (isset($catalog[$column]) && !in_array($column, $out, true)) {
                $out[] = $column;
            }
        }

        if (!empty($out)) {
            return $out;
        }

        return array_values(array_filter(array_map(static function (array $column): ?string {
            return $column['default'] ? $column['key'] : null;
        }, $catalog)));
    }

    /**
     * Convert compact column rows to keyed catalog rows.
     *
     * @param array $rows Compact rows.
     * @return array<string, array{key: string, label: string, default: bool, align: string}>
     */
    private static function columns(array $rows): array {
        $columns = [];
        foreach ($rows as $row) {
            [$key, $label, $default, $align] = $row;
            $columns[$key] = [
                'key' => $key,
                'label' => $label,
                'default' => (bool) $default,
                'align' => $align,
            ];
        }
        return $columns;
    }

    /**
     * Normalise free-text report search input.
     *
     * @param string $value Raw search value.
     * @return string
     */
    private static function normalise_search(string $value): string {
        return trim(substr($value, 0, 120));
    }

    /**
     * SQL LIKE pattern for a user-entered search value.
     *
     * @param string $value Search value.
     * @return string
     */
    private static function search_pattern(string $value): string {
        global $DB;

        return '%' . $DB->sql_like_escape($value) . '%';
    }

    /**
     * Build a reusable item-level product/course search clause.
     *
     * @param array $params SQL params, mutated in place.
     * @param string $productsearch Product search value.
     * @param string $coursesearch Course search value.
     * @param string $prefix Unique SQL parameter prefix.
     * @param string $itemalias Order item alias.
     * @param string $productalias Product alias.
     * @param string $coursealias Course alias.
     * @return string SQL fragment beginning with AND when filters exist.
     */
    private static function item_filter_sql(
        array &$params,
        string $productsearch,
        string $coursesearch,
        string $prefix,
        string $itemalias = 'i',
        string $productalias = 'p',
        string $coursealias = 'c'
    ): string {
        global $DB;

        $clauses = [];
        if ($productsearch !== '') {
            $pattern = self::search_pattern($productsearch);
            $fields = [
                "{$itemalias}.itemname",
                "{$itemalias}.sku",
                "{$productalias}.name",
                "{$productalias}.sku",
                "{$productalias}.slug",
            ];
            $likes = [];
            foreach ($fields as $index => $field) {
                $param = "{$prefix}product{$index}";
                $likes[] = $DB->sql_like($field, ':' . $param, false);
                $params[$param] = $pattern;
            }
            $clauses[] = '(' . implode(' OR ', $likes) . ')';
        }

        if ($coursesearch !== '') {
            $pattern = self::search_pattern($coursesearch);
            $fields = [
                "{$coursealias}.fullname",
                "{$coursealias}.shortname",
                "{$coursealias}.idnumber",
            ];
            $likes = [];
            foreach ($fields as $index => $field) {
                $param = "{$prefix}course{$index}";
                $likes[] = $DB->sql_like($field, ':' . $param, false);
                $params[$param] = $pattern;
            }

            $linkedparams = [
                "{$prefix}linkedcoursefullname",
                "{$prefix}linkedcourseshortname",
                "{$prefix}linkedcourseidnumber",
            ];
            $linkedidparam = "{$prefix}linkedcourseid";
            $linkedcourse = "EXISTS (
                SELECT 1
                  FROM {local_moderncommerce_product_courses} {$prefix}pc
                  JOIN {course} {$prefix}linkedc ON {$prefix}linkedc.id = {$prefix}pc.courseid
                 WHERE {$prefix}pc.productid = {$itemalias}.productid
                   AND (" .
                        $DB->sql_like("{$prefix}linkedc.fullname", ':' . $linkedparams[0], false) . ' OR ' .
                        $DB->sql_like("{$prefix}linkedc.shortname", ':' . $linkedparams[1], false) . ' OR ' .
                        $DB->sql_like("{$prefix}linkedc.idnumber", ':' . $linkedparams[2], false) .
                    ')
            )';
            foreach ($linkedparams as $linkedparam) {
                $params[$linkedparam] = $pattern;
            }
            $likes[] = $linkedcourse;

            if (ctype_digit($coursesearch)) {
                $likes[] = "{$itemalias}.courseid = :{$prefix}courseid";
                $likes[] = "EXISTS (
                    SELECT 1
                      FROM {local_moderncommerce_product_courses} {$prefix}pcid
                     WHERE {$prefix}pcid.productid = {$itemalias}.productid
                       AND {$prefix}pcid.courseid = :{$linkedidparam}
                )";
                $params["{$prefix}courseid"] = (int) $coursesearch;
                $params[$linkedidparam] = (int) $coursesearch;
            }

            $clauses[] = '(' . implode(' OR ', $likes) . ')';
        }

        return empty($clauses) ? '' : ' AND ' . implode(' AND ', $clauses);
    }

    /**
     * Build an order-level product/course search clause using matching line items.
     *
     * @param array $params SQL params, mutated in place.
     * @param string $productsearch Product search value.
     * @param string $coursesearch Course search value.
     * @param string $prefix Unique SQL parameter prefix.
     * @param string $orderalias Order alias.
     * @return string SQL fragment beginning with AND when filters exist.
     */
    private static function order_filter_sql(
        array &$params,
        string $productsearch,
        string $coursesearch,
        string $prefix,
        string $orderalias = 'o'
    ): string {
        if ($productsearch === '' && $coursesearch === '') {
            return '';
        }

        $itemfilter = self::item_filter_sql($params, $productsearch, $coursesearch, $prefix, 'fi', 'fp', 'fc');
        return " AND EXISTS (
            SELECT 1
              FROM {local_moderncommerce_order_items} fi
         LEFT JOIN {local_moderncommerce_products} fp ON fp.id = fi.productid
         LEFT JOIN {course} fc ON fc.id = fi.courseid
             WHERE fi.orderid = {$orderalias}.id
                   {$itemfilter}
        )";
    }

    /**
     * Build a product catalog search clause for product-linked widgets such as wishlist demand.
     *
     * @param array $params SQL params, mutated in place.
     * @param string $productsearch Product search value.
     * @param string $coursesearch Course search value.
     * @param string $prefix Unique SQL parameter prefix.
     * @param string $productalias Product alias.
     * @return string SQL fragment beginning with AND when filters exist.
     */
    private static function product_filter_sql(
        array &$params,
        string $productsearch,
        string $coursesearch,
        string $prefix,
        string $productalias = 'p'
    ): string {
        global $DB;

        $clauses = [];
        if ($productsearch !== '') {
            $pattern = self::search_pattern($productsearch);
            $fields = ["{$productalias}.name", "{$productalias}.sku", "{$productalias}.slug"];
            $likes = [];
            foreach ($fields as $index => $field) {
                $param = "{$prefix}product{$index}";
                $likes[] = $DB->sql_like($field, ':' . $param, false);
                $params[$param] = $pattern;
            }
            $clauses[] = '(' . implode(' OR ', $likes) . ')';
        }

        if ($coursesearch !== '') {
            $pattern = self::search_pattern($coursesearch);
            $courseparams = [
                "{$prefix}coursefullname",
                "{$prefix}courseshortname",
                "{$prefix}courseidnumber",
            ];
            $courseidparam = "{$prefix}courseid";
            $courseconditions = [
                $DB->sql_like("{$prefix}c.fullname", ':' . $courseparams[0], false),
                $DB->sql_like("{$prefix}c.shortname", ':' . $courseparams[1], false),
                $DB->sql_like("{$prefix}c.idnumber", ':' . $courseparams[2], false),
            ];
            foreach ($courseparams as $courseparam) {
                $params[$courseparam] = $pattern;
            }
            if (ctype_digit($coursesearch)) {
                $courseconditions[] = "{$prefix}pc.courseid = :{$courseidparam}";
                $params[$courseidparam] = (int) $coursesearch;
            }
            $courseclause = "EXISTS (
                SELECT 1
                  FROM {local_moderncommerce_product_courses} {$prefix}pc
                  JOIN {course} {$prefix}c ON {$prefix}c.id = {$prefix}pc.courseid
                 WHERE {$prefix}pc.productid = {$productalias}.id
                   AND (" . implode(' OR ', $courseconditions) . ")
            )";
            $clauses[] = $courseclause;
        }

        return empty($clauses) ? '' : ' AND ' . implode(' AND ', $clauses);
    }

    /**
     * Build a reusable text search clause for report table rows.
     *
     * @param array $params SQL params, mutated in place.
     * @param string $search Search value.
     * @param string $prefix Unique SQL parameter prefix.
     * @param array $fields SQL fields to search.
     * @return string SQL fragment beginning with AND when a search exists.
     */
    private static function table_search_sql(array &$params, string $search, string $prefix, array $fields): string {
        global $DB;

        if ($search === '' || empty($fields)) {
            return '';
        }

        $pattern = self::search_pattern($search);
        $likes = [];
        foreach ($fields as $index => $field) {
            $param = "{$prefix}table{$index}";
            $likes[] = $DB->sql_like($field, ':' . $param, false);
            $params[$param] = $pattern;
        }

        return ' AND (' . implode(' OR ', $likes) . ')';
    }

    /**
     * Summary statistics for the period.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function get_stats(int $from, int $to, string $productsearch = '', string $coursesearch = ''): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $orderfilter = self::order_filter_sql($params, $productsearch, $coursesearch, 'stats');
        $stats = $DB->get_record_sql(
            "SELECT COUNT(*) AS totalorders,
                    COALESCE(SUM(o.total), 0) AS totalrevenue,
                    COALESCE(AVG(o.total), 0) AS averageorder
               FROM {local_moderncommerce_orders} o
              WHERE o.status IN ('paid', 'completed')
                AND o.timecreated BETWEEN :from AND :to
                    {$orderfilter}",
            $params
        );

        $couponparams = ['from' => $from, 'to' => $to];
        $couponfilter = self::order_filter_sql($couponparams, $productsearch, $coursesearch, 'statscoupon');
        $couponsused = (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {local_moderncommerce_coupon_usage} cu
               JOIN {local_moderncommerce_orders} o ON o.id = cu.orderid
              WHERE o.timecreated BETWEEN :from AND :to
                    {$couponfilter}",
            $couponparams
        );

        return [
            'displayrevenue' => pricing_service::format_price((float) ($stats->totalrevenue ?? 0)),
            'totalorders' => (int) ($stats->totalorders ?? 0),
            'displayaverage' => pricing_service::format_price((float) ($stats->averageorder ?? 0)),
            'couponsused' => $couponsused,
        ];
    }

    /**
     * KPI metric tiles.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function get_metrics(int $from, int $to, string $productsearch = '', string $coursesearch = ''): array {
        global $DB;

        $paidwhere = "o.status IN ('paid', 'completed')";
        $params = ['from' => $from, 'to' => $to];
        $orderfilter = self::order_filter_sql($params, $productsearch, $coursesearch, 'metrics');
        $stats = $DB->get_record_sql(
            "SELECT COUNT(*) AS paidorders,
                    COUNT(DISTINCT o.userid) AS buyers,
                    COALESCE(SUM(o.subtotal), 0) AS gross,
                    COALESCE(SUM(o.total - o.refundedtotal), 0) AS net,
                    COALESCE(SUM(o.discount), 0) AS discount,
                    COALESCE(SUM(o.tax), 0) AS tax,
                    COALESCE(AVG(o.total), 0) AS averageorder
               FROM {local_moderncommerce_orders} o
              WHERE {$paidwhere}
                AND o.timecreated BETWEEN :from AND :to
                    {$orderfilter}",
            $params
        );
        $unitparams = ['from' => $from, 'to' => $to];
        $itemfilter = self::item_filter_sql($unitparams, $productsearch, $coursesearch, 'metricunits');
        $units = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(i.quantity), 0)
               FROM {local_moderncommerce_order_items} i
               JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
          LEFT JOIN {local_moderncommerce_products} p ON p.id = i.productid
          LEFT JOIN {course} c ON c.id = i.courseid
              WHERE o.status IN ('paid', 'completed')
                AND o.timecreated BETWEEN :from AND :to
                    {$itemfilter}",
            $unitparams
        );

        return [
            self::metric(
                'gross',
                get_string('grossrevenue', 'local_moderncommerce'),
                pricing_service::format_price((float) ($stats->gross ?? 0)),
                'primary',
                'bi-cash-stack',
                3
            ),
            self::metric(
                'net',
                get_string('netrevenue', 'local_moderncommerce'),
                pricing_service::format_price((float) ($stats->net ?? 0)),
                'success',
                'bi-graph-up-arrow',
                3
            ),
            self::metric(
                'paidorders',
                get_string('paidorders', 'local_moderncommerce'),
                number_format((int) ($stats->paidorders ?? 0)),
                'info',
                'bi-bag-check',
                3
            ),
            self::metric(
                'units',
                get_string('unitssold', 'local_moderncommerce'),
                number_format((int) round($units)),
                'warning',
                'bi-box-seam',
                3
            ),
            self::metric(
                'buyers',
                get_string('uniquebuyers', 'local_moderncommerce'),
                number_format((int) ($stats->buyers ?? 0)),
                'neutral',
                'bi-people',
                3
            ),
            self::metric(
                'aov',
                get_string('averageordervalue', 'local_moderncommerce'),
                pricing_service::format_price((float) ($stats->averageorder ?? 0)),
                'primary',
                'bi-receipt',
                3
            ),
            self::metric(
                'discount',
                get_string('discount', 'local_moderncommerce'),
                pricing_service::format_price((float) ($stats->discount ?? 0)),
                'danger',
                'bi-ticket-perforated',
                3
            ),
            self::metric(
                'tax',
                get_string('tax', 'local_moderncommerce'),
                pricing_service::format_price((float) ($stats->tax ?? 0)),
                'info',
                'bi-bank',
                3
            ),
        ];
    }

    /**
     * Build one metric tile row.
     *
     * @param string $key Metric key.
     * @param string $label Label.
     * @param string $value Formatted value.
     * @param string $variant Tile variant.
     * @param string $icon Bootstrap icon.
     * @param int $size Grid span.
     * @return array
     */
    private static function metric(string $key, string $label, string $value, string $variant, string $icon, int $size): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'variant' => $variant,
            'icon' => $icon,
            'hasdelta' => false,
            'delta' => '',
            'deltaup' => false,
            'deltadown' => false,
            'size' => self::clamp_size($size, 3),
        ];
    }

    /**
     * Build report charts.
     *
     * @param string $type Report type.
     * @param string $period Period.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context Context.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function get_charts(
        string $type,
        string $period,
        int $from,
        int $to,
        context_system $context,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        if ($type === 'coupons') {
            return [
                self::chart_coupon_roi($from, $to, 12, $productsearch, $coursesearch),
                self::chart_revenue_trend($period, $from, $to, 6, $productsearch, $coursesearch),
            ];
        }

        if ($type === 'courses') {
            return [
                self::chart_top_courses($from, $to, $context, 12, $productsearch, $coursesearch),
                self::chart_revenue_trend($period, $from, $to, 6, $productsearch, $coursesearch),
                self::chart_top_products($from, $to, $context, 6, $productsearch, $coursesearch),
            ];
        }

        return [
            self::chart_revenue_trend($period, $from, $to, 12, $productsearch, $coursesearch),
            self::chart_orders_conversion($period, $from, $to, 12, $productsearch, $coursesearch),
            self::chart_top_products($from, $to, $context, 6, $productsearch, $coursesearch),
            self::chart_revenue_mix($from, $to, 6, $productsearch, $coursesearch),
            self::chart_top_courses($from, $to, $context, 6, $productsearch, $coursesearch),
            self::chart_coupon_roi($from, $to, 6, $productsearch, $coursesearch),
            self::chart_new_returning($period, $from, $to, 12, $productsearch, $coursesearch),
            self::chart_wishlist_demand($from, $to, $context, 6, $productsearch, $coursesearch),
            self::chart_geo_revenue($from, $to, 6, $productsearch, $coursesearch),
        ];
    }

    /**
     * Revenue grouped by period bucket.
     *
     * @param string $period Bucket.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function get_sales(
        string $period,
        int $from,
        int $to,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $orderfilter = self::order_filter_sql($params, $productsearch, $coursesearch, 'sales');
        $sql = "SELECT o.timecreated,
                       o.total
                  FROM {local_moderncommerce_orders} o
                 WHERE o.status IN ('paid', 'completed')
                   AND o.timecreated BETWEEN :from AND :to
                       {$orderfilter}
              ORDER BY o.timecreated ASC";

        $records = $DB->get_records_sql($sql, $params);
        $buckets = [];
        $maxrevenue = 0.0;
        foreach ($records as $record) {
            [$bucket, $label] = self::bucket_label($period, (int) $record->timecreated);
            if (!isset($buckets[$bucket])) {
                $buckets[$bucket] = ['label' => $label, 'revenue' => 0.0, 'orders' => 0];
            }
            $buckets[$bucket]['revenue'] += (float) $record->total;
            $buckets[$bucket]['orders']++;
        }

        ksort($buckets);
        $rows = [];
        foreach ($buckets as $bucket) {
            $revenue = (float) $bucket['revenue'];
            $maxrevenue = max($maxrevenue, $revenue);
            $rows[] = [
                'label' => (string) $bucket['label'],
                'rawrevenue' => $revenue,
                'displayrevenue' => pricing_service::format_price($revenue),
                'orders' => (int) $bucket['orders'],
                'displayaverage' => pricing_service::format_price($revenue / max(1, (int) $bucket['orders'])),
            ];
        }

        return ['maxrevenue' => $maxrevenue, 'rows' => $rows];
    }

    /**
     * Empty sales payload.
     *
     * @return array
     */
    private static function empty_sales(): array {
        return ['maxrevenue' => 0.0, 'rows' => []];
    }

    /**
     * Top courses by revenue.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context System context.
     * @param int $limit Limit.
     * @param int $offset Offset.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @return array
     */
    private static function get_courses(
        int $from,
        int $to,
        context_system $context,
        int $limit = 20,
        int $offset = 0,
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $itemfilter = self::item_filter_sql($params, $productsearch, $coursesearch, 'courses');
        $tablefilter = self::table_search_sql($params, $tablesearch, 'coursestable', [
            'c.fullname',
            'c.shortname',
            'c.idnumber',
            'p.name',
            'p.sku',
            'p.slug',
        ]);
        $sql = "SELECT c.id, c.fullname,
                       COUNT(DISTINCT i.orderid) AS orders,
                       SUM(i.quantity) AS enrollments,
                       SUM(i.total) AS revenue
                  FROM {local_moderncommerce_order_items} i
                  JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
             LEFT JOIN {local_moderncommerce_products} p ON p.id = i.productid
                  JOIN {course} c ON c.id = i.courseid
                 WHERE o.status IN ('paid', 'completed')
                   AND i.courseid IS NOT NULL
                   AND o.timecreated BETWEEN :from AND :to
                       {$itemfilter}
                       {$tablefilter}
              GROUP BY c.id, c.fullname
              ORDER BY revenue DESC";

        $records = $DB->get_records_sql($sql, $params, $offset, $limit);
        $rows = [];
        $rank = $offset + 1;
        foreach ($records as $record) {
            $revenue = (float) $record->revenue;
            $rows[] = [
                'rank' => $rank++,
                'courseid' => (int) $record->id,
                'fullname' => format_string($record->fullname, true, ['context' => $context]),
                'orders' => (int) $record->orders,
                'enrollments' => (int) round((float) $record->enrollments),
                'rawrevenue' => $revenue,
                'displayrevenue' => pricing_service::format_price($revenue),
            ];
        }

        return $rows;
    }

    /**
     * Count course summary rows within the period.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @return int
     */
    private static function count_courses(
        int $from,
        int $to,
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = ''
    ): int {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $itemfilter = self::item_filter_sql($params, $productsearch, $coursesearch, 'coursecount');
        $tablefilter = self::table_search_sql($params, $tablesearch, 'coursecounttable', [
            'c.fullname',
            'c.shortname',
            'c.idnumber',
            'p.name',
            'p.sku',
            'p.slug',
        ]);
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM (
                    SELECT c.id
                      FROM {local_moderncommerce_order_items} i
                      JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
                 LEFT JOIN {local_moderncommerce_products} p ON p.id = i.productid
                      JOIN {course} c ON c.id = i.courseid
                     WHERE o.status IN ('paid', 'completed')
                       AND i.courseid IS NOT NULL
                       AND o.timecreated BETWEEN :from AND :to
                           {$itemfilter}
                           {$tablefilter}
                  GROUP BY c.id
                    ) coursecounts",
            $params
        );
    }

    /**
     * Coupon usage within the period.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @return array
     */
    private static function get_coupons(
        int $from,
        int $to,
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $orderfilter = self::order_filter_sql($params, $productsearch, $coursesearch, 'coupons');
        $tablefilter = self::table_search_sql($params, $tablesearch, 'couponstable', [
            'c.code',
            'c.discounttype',
            'o.ordernumber',
            'o.customeremail',
            'o.couponcode',
            'o.status',
        ]);
        $sql = "SELECT c.id, c.code, c.discounttype, c.value,
                       COUNT(cu.id) AS usages,
                       COALESCE(SUM(cu.discountamount), 0) AS totaldiscount
                  FROM {local_moderncommerce_coupons} c
                  JOIN {local_moderncommerce_coupon_usage} cu ON cu.couponid = c.id
                  JOIN {local_moderncommerce_orders} o ON o.id = cu.orderid
                 WHERE o.timecreated BETWEEN :from AND :to
                       {$orderfilter}
                       {$tablefilter}
              GROUP BY c.id, c.code, c.discounttype, c.value
              ORDER BY usages DESC";

        $records = $DB->get_records_sql($sql, $params);
        $rows = [];
        foreach ($records as $record) {
            $type = $record->discounttype ?: 'percentage';
            $valueformatted = $type === 'percentage'
                ? rtrim(rtrim(number_format((float) $record->value, 2), '0'), '.') . '%'
                : pricing_service::format_price((float) $record->value);
            $typekey = 'coupontype_' . $type;

            $rows[] = [
                'code' => (string) $record->code,
                'typelabel' => get_string_manager()->string_exists($typekey, 'local_moderncommerce')
                    ? get_string($typekey, 'local_moderncommerce')
                    : ucfirst($type),
                'valueformatted' => $valueformatted,
                'usages' => (int) $record->usages,
                'displaytotaldiscount' => pricing_service::format_price((float) $record->totaldiscount),
            ];
        }

        return $rows;
    }

    /**
     * Build the generic table.
     *
     * @param string $type Report type.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context Context.
     * @param array $columns Selected columns.
     * @param int $limit Maximum rows to return. Zero means no limit.
     * @param int $offset Row offset.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @return array{rows: array, total: int, truncated: bool}
     */
    private static function get_table(
        string $type,
        int $from,
        int $to,
        context_system $context,
        array $columns,
        int $limit,
        int $offset,
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = ''
    ): array {
        switch ($type) {
            case 'courses':
                return self::get_courses_table(
                    $from,
                    $to,
                    $context,
                    $columns,
                    $limit,
                    $offset,
                    $productsearch,
                    $coursesearch,
                    $tablesearch
                );
            case 'coupons':
                return self::get_coupons_table($from, $to, $columns, $limit, $offset, $productsearch, $coursesearch, $tablesearch);
            case 'sales':
            default:
                return self::get_sales_table(
                    $from,
                    $to,
                    $context,
                    $columns,
                    $limit,
                    $offset,
                    $productsearch,
                    $coursesearch,
                    $tablesearch
                );
        }
    }

    /**
     * Sales detail table: one row per purchased line item.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context Context.
     * @param array $columns Selected columns.
     * @param int $limit Maximum rows to return. Zero means no limit.
     * @param int $offset Row offset.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @return array
     */
    private static function get_sales_table(
        int $from,
        int $to,
        context_system $context,
        array $columns,
        int $limit,
        int $offset,
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $itemfilter = self::item_filter_sql($params, $productsearch, $coursesearch, 'salestable');
        $tablefilter = self::table_search_sql($params, $tablesearch, 'salestable', [
            'o.ordernumber',
            'o.status',
            'o.customeremail',
            'o.couponcode',
            'i.itemname',
            'i.sku',
            'p.name',
            'p.sku',
            'p.slug',
            'c.fullname',
            'c.shortname',
            'c.idnumber',
            'u.firstname',
            'u.lastname',
            'u.email',
        ]);
        $countsql = "SELECT COUNT(i.id)
                       FROM {local_moderncommerce_order_items} i
                       JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
                  LEFT JOIN {local_moderncommerce_products} p ON p.id = i.productid
                  LEFT JOIN {course} c ON c.id = i.courseid
                  LEFT JOIN {user} u ON u.id = o.userid
                      WHERE o.status IN ('paid', 'completed')
                        AND o.timecreated BETWEEN :from AND :to
                            {$itemfilter}
                            {$tablefilter}";
        $total = (int) $DB->count_records_sql($countsql, $params);

        $sql = "SELECT i.id AS lineid,
                       o.id AS orderid,
                       o.userid,
                       o.ordernumber,
                       o.status AS orderstatus,
                       o.subtotal AS ordersubtotal,
                       o.discount AS orderdiscount,
                       o.tax AS ordertax,
                       o.total AS ordertotal,
                       o.refundedtotal,
                       o.currency,
                       o.currency AS ordercurrency,
                       o.currencysymbol,
                       o.currencyposition,
                       o.decimalplaces,
                       o.thousandseparator,
                       o.decimalseparator,
                       o.couponcode,
                       o.customeremail,
                       o.timecreated AS ordertimecreated,
                       o.timepaid,
                       i.productid,
                       i.priceid,
                       i.courseid,
                       i.itemtype,
                       i.itemname,
                       i.sku,
                       i.unitprice,
                       i.quantity,
                       i.subtotal,
                       i.discount,
                       i.tax,
                       i.total,
                       p.name AS productname,
                       p.producttype,
                       c.fullname AS coursename
                 FROM {local_moderncommerce_order_items} i
                  JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
             LEFT JOIN {local_moderncommerce_products} p ON p.id = i.productid
             LEFT JOIN {course} c ON c.id = i.courseid
             LEFT JOIN {user} u ON u.id = o.userid
                 WHERE o.status IN ('paid', 'completed')
                   AND o.timecreated BETWEEN :from AND :to
                       {$itemfilter}
                       {$tablefilter}
              ORDER BY o.timecreated DESC, o.id DESC, i.id DESC";

        $records = $DB->get_records_sql($sql, $params, $offset, $limit);
        $orderids = array_values(array_unique(array_map(static fn($record): int => (int) $record->orderid, $records)));
        $userids = array_values(array_unique(array_map(static fn($record): int => (int) $record->userid, $records)));
        $users = empty($userids) ? [] : $DB->get_records_list('user', 'id', $userids);
        $attempts = self::get_latest_payment_attempts($orderids);
        $addresses = self::get_billing_addresses($orderids);
        $rows = [];

        foreach ($records as $record) {
            $data = self::sales_row_data(
                $record,
                $users[(int) $record->userid] ?? null,
                $attempts[(int) $record->orderid] ?? null,
                $addresses[(int) $record->orderid] ?? null,
                $context
            );
            $rows[] = self::row_from_data($columns, $data);
        }

        return ['rows' => $rows, 'total' => $total, 'truncated' => false];
    }

    /**
     * Course summary table.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context Context.
     * @param array $columns Selected columns.
     * @param int $limit Maximum rows to return. Zero means no limit.
     * @param int $offset Row offset.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @return array
     */
    private static function get_courses_table(
        int $from,
        int $to,
        context_system $context,
        array $columns,
        int $limit,
        int $offset,
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = ''
    ): array {
        $courses = self::get_courses($from, $to, $context, $limit, $offset, $productsearch, $coursesearch, $tablesearch);
        $rows = [];
        foreach ($courses as $course) {
            $data = [
                'rank' => self::cell('#' . $course['rank'], (string) $course['rank']),
                'course' => self::cell($course['fullname']),
                'orders' => self::cell(number_format((int) $course['orders']), (string) $course['orders']),
                'units' => self::cell(number_format((int) $course['enrollments']), (string) $course['enrollments']),
                'revenue' => self::cell($course['displayrevenue'], self::decimal_export((float) $course['rawrevenue'])),
            ];
            $rows[] = self::row_from_data($columns, $data);
        }

        return [
            'rows' => $rows,
            'total' => self::count_courses($from, $to, $productsearch, $coursesearch, $tablesearch),
            'truncated' => false,
        ];
    }

    /**
     * Coupon summary table.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param array $columns Selected columns.
     * @param int $limit Maximum rows to return. Zero means no limit.
     * @param int $offset Row offset.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @return array
     */
    private static function get_coupons_table(
        int $from,
        int $to,
        array $columns,
        int $limit,
        int $offset,
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = ''
    ): array {
        $coupons = self::get_coupons($from, $to, $productsearch, $coursesearch, $tablesearch);
        $total = count($coupons);
        if ($limit > 0 || $offset > 0) {
            $coupons = array_slice($coupons, $offset, $limit > 0 ? $limit : null);
        }
        $rows = [];
        foreach ($coupons as $coupon) {
            $data = [
                'coupon' => self::cell($coupon['code']),
                'type' => self::cell($coupon['typelabel'], $coupon['typelabel'], true, 'neutral'),
                'value' => self::cell($coupon['valueformatted']),
                'usage' => self::cell(number_format((int) $coupon['usages']), (string) $coupon['usages']),
                'totaldiscount' => self::cell($coupon['displaytotaldiscount']),
            ];
            $rows[] = self::row_from_data($columns, $data);
        }

        return ['rows' => $rows, 'total' => $total, 'truncated' => false];
    }

    /**
     * Build keyed sales row data.
     *
     * @param \stdClass $record SQL record.
     * @param \stdClass|null $user User record.
     * @param \stdClass|null $attempt Latest payment attempt.
     * @param \stdClass|null $address Billing address.
     * @param context_system $context Context.
     * @return array
     */
    private static function sales_row_data(
        \stdClass $record,
        ?\stdClass $user,
        ?\stdClass $attempt,
        ?\stdClass $address,
        context_system $context
    ): array {
        $customeremail = $user ? (string) $user->email : (string) ($record->customeremail ?? '');
        $productname = (string) ($record->productname ?: $record->itemname);
        $coursename = (string) ($record->coursename ?: '');
        $status = (string) $record->orderstatus;
        $transaction = $attempt->gatewaytransactionid ?? $attempt->reference ?? '';

        return [
            'orderdate' => self::cell(
                userdate((int) $record->ordertimecreated, get_string('strftimedate', 'langconfig')),
                date('Y-m-d H:i:s', (int) $record->ordertimecreated)
            ),
            'ordernumber' => self::cell(
                (string) $record->ordernumber,
                (string) $record->ordernumber,
                false,
                '',
                (new moodle_url('/local/moderncommerce/admin/order_view.php', ['id' => $record->orderid]))->out(false)
            ),
            'customername' => self::cell($user ? fullname($user) : get_string('unknownuser', 'local_moderncommerce')),
            'customeremail' => self::cell($customeremail),
            'product' => self::cell(format_string($productname, true, ['context' => $context])),
            'course' => self::cell($coursename !== '' ? format_string($coursename, true, ['context' => $context]) : '-'),
            'quantity' => self::cell(
                number_format((int) round((float) $record->quantity)),
                (string) (float) $record->quantity
            ),
            'unitprice' => self::cell(
                pricing_service::format_order_price((float) $record->unitprice, $record),
                self::decimal_export((float) $record->unitprice)
            ),
            'linetotal' => self::cell(
                pricing_service::format_order_price((float) $record->total, $record),
                self::decimal_export((float) $record->total)
            ),
            'status' => self::cell(self::status_label($status), self::status_label($status), true, self::status_class($status)),
            'userid' => self::cell((string) (int) $record->userid),
            'producttype' => self::cell(self::product_type_label((string) ($record->producttype ?: $record->itemtype))),
            'sku' => self::cell((string) ($record->sku ?? '')),
            'courseid' => self::cell((string) (int) $record->courseid),
            'subtotal' => self::cell(
                pricing_service::format_order_price((float) $record->subtotal, $record),
                self::decimal_export((float) $record->subtotal)
            ),
            'discount' => self::cell(
                pricing_service::format_order_price((float) $record->discount, $record),
                self::decimal_export((float) $record->discount)
            ),
            'tax' => self::cell(
                pricing_service::format_order_price((float) $record->tax, $record),
                self::decimal_export((float) $record->tax)
            ),
            'ordertotal' => self::cell(
                pricing_service::format_order_price((float) $record->ordertotal, $record),
                self::decimal_export((float) $record->ordertotal)
            ),
            'currency' => self::cell((string) $record->ordercurrency),
            'couponcode' => self::cell((string) ($record->couponcode ?? '')),
            'paymentgateway' => self::cell($attempt ? ucfirst((string) $attempt->gateway) : '-'),
            'transactionreference' => self::cell((string) $transaction),
            'billingcity' => self::cell((string) ($address->city ?? '')),
            'billingcountry' => self::cell(self::country_label((string) ($address->country ?? ''))),
            'paiddate' => self::cell(
                !empty($record->timepaid) ? userdate((int) $record->timepaid, get_string('strftimedate', 'langconfig')) : '',
                !empty($record->timepaid) ? date('Y-m-d H:i:s', (int) $record->timepaid) : ''
            ),
        ];
    }

    /**
     * Build a cell.
     *
     * @param string $value Display value.
     * @param string|null $exportvalue Export value.
     * @param bool $badge Whether cell is a badge.
     * @param string $badgeclass Badge class.
     * @param string $href Optional href.
     * @return array
     */
    private static function cell(
        string $value,
        ?string $exportvalue = null,
        bool $badge = false,
        string $badgeclass = '',
        string $href = ''
    ): array {
        return [
            'value' => $value,
            'exportvalue' => $exportvalue ?? $value,
            'badge' => $badge,
            'badgeclass' => $badgeclass,
            'href' => $href,
        ];
    }

    /**
     * Convert keyed cell data into a row.
     *
     * @param array $columns Selected columns.
     * @param array $data Row data.
     * @return array
     */
    private static function row_from_data(array $columns, array $data): array {
        $cells = [];
        foreach ($columns as $column) {
            $cell = $data[$column] ?? self::cell('');
            $cell['key'] = $column;
            $cells[] = $cell;
        }
        return ['cells' => $cells];
    }

    /**
     * Latest payment attempts by order.
     *
     * @param array $orderids Order ids.
     * @return array<int, \stdClass>
     */
    private static function get_latest_payment_attempts(array $orderids): array {
        global $DB;

        if (empty($orderids) || !$DB->get_manager()->table_exists('local_moderncommerce_payment_attempts')) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($orderids, SQL_PARAMS_NAMED, 'pa');
        $records = $DB->get_records_sql(
            "SELECT id, orderid, gateway, reference, gatewaytransactionid, status, timecreated
               FROM {local_moderncommerce_payment_attempts}
              WHERE orderid {$insql}
           ORDER BY orderid ASC, timecreated DESC, id DESC",
            $params
        );
        $out = [];
        foreach ($records as $record) {
            $orderid = (int) $record->orderid;
            if (!isset($out[$orderid])) {
                $out[$orderid] = $record;
            }
        }
        return $out;
    }

    /**
     * Billing addresses by order.
     *
     * @param array $orderids Order ids.
     * @return array<int, \stdClass>
     */
    private static function get_billing_addresses(array $orderids): array {
        global $DB;

        if (empty($orderids) || !$DB->get_manager()->table_exists('local_moderncommerce_order_addresses')) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($orderids, SQL_PARAMS_NAMED, 'oa');
        $params['billing'] = 'billing';
        $records = $DB->get_records_sql(
            "SELECT id, orderid, city, state, country, email, phone
               FROM {local_moderncommerce_order_addresses}
              WHERE orderid {$insql}
                AND addresstype = :billing
           ORDER BY orderid ASC, id DESC",
            $params
        );
        $out = [];
        foreach ($records as $record) {
            $orderid = (int) $record->orderid;
            if (!isset($out[$orderid])) {
                $out[$orderid] = $record;
            }
        }
        return $out;
    }

    /**
     * Chart: revenue trend.
     *
     * @param string $period Period.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param int $size Grid size.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function chart_revenue_trend(
        string $period,
        int $from,
        int $to,
        int $size,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        $sales = self::get_sales($period, $from, $to, $productsearch, $coursesearch);
        $labels = [];
        $revenue = [];
        $orders = [];
        foreach ($sales['rows'] as $row) {
            $labels[] = $row['label'];
            $revenue[] = round((float) $row['rawrevenue'], 2);
            $orders[] = (float) $row['orders'];
        }

        return self::chart(
            'revenue_trend',
            'combo',
            get_string('chart_revenue_trend', 'local_moderncommerce'),
            get_string('chart_revenue_trend_sub', 'local_moderncommerce'),
            'currency',
            pricing_service::format_price(array_sum($revenue)),
            self::clamp_size($size, 12),
            $labels,
            [
                self::series('revenue', get_string('revenue', 'local_moderncommerce'), 'bar', 'left', $revenue),
                self::series('orders', get_string('orders', 'local_moderncommerce'), 'line', 'right', $orders),
            ]
        );
    }

    /**
     * Chart: orders placed vs paid.
     *
     * @param string $period Period.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param int $size Grid size.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function chart_orders_conversion(
        string $period,
        int $from,
        int $to,
        int $size,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $orderfilter = self::order_filter_sql($params, $productsearch, $coursesearch, 'orderschart');
        $records = $DB->get_records_sql(
            "SELECT o.timecreated,
                    o.status
               FROM {local_moderncommerce_orders} o
              WHERE o.timecreated BETWEEN :from AND :to
                    {$orderfilter}
           ORDER BY o.timecreated ASC",
            $params
        );
        $buckets = [];
        foreach ($records as $record) {
            [$bucket, $label] = self::bucket_label($period, (int) $record->timecreated);
            if (!isset($buckets[$bucket])) {
                $buckets[$bucket] = ['label' => $label, 'orders' => 0, 'paid' => 0];
            }
            $buckets[$bucket]['orders']++;
            if (in_array($record->status, ['paid', 'completed'], true)) {
                $buckets[$bucket]['paid']++;
            }
        }

        ksort($buckets);
        $labels = [];
        $orders = [];
        $paid = [];
        $rates = [];
        foreach ($buckets as $bucket) {
            $labels[] = (string) $bucket['label'];
            $orders[] = (float) $bucket['orders'];
            $paid[] = (float) $bucket['paid'];
            $rates[] = (int) $bucket['orders'] > 0 ? round(((float) $bucket['paid'] / (float) $bucket['orders']) * 100, 1) : 0;
        }
        $totalorders = array_sum($orders);
        $totalpaid = array_sum($paid);
        $rate = $totalorders > 0 ? round($totalpaid / $totalorders * 100) : 0;

        return self::chart(
            'orders_conversion',
            'combo',
            get_string('chart_orders_conversion', 'local_moderncommerce'),
            get_string('chart_orders_conversion_sub', 'local_moderncommerce'),
            'number',
            $rate . '%',
            self::clamp_size($size, 12),
            $labels,
            [
                self::series('orders', get_string('orders', 'local_moderncommerce'), 'bar', 'left', $orders),
                self::series('paid', get_string('chart_paid', 'local_moderncommerce'), 'bar', 'left', $paid),
                self::series('rate', get_string('chart_rate', 'local_moderncommerce'), 'line', 'right', $rates),
            ]
        );
    }

    /**
     * Chart: top products by revenue.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context Context.
     * @param int $size Grid size.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function chart_top_products(
        int $from,
        int $to,
        context_system $context,
        int $size,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $itemfilter = self::item_filter_sql($params, $productsearch, $coursesearch, 'topproducts');
        $records = $DB->get_records_sql(
            "SELECT p.id, p.name, SUM(i.total) AS revenue
               FROM {local_moderncommerce_order_items} i
               JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
               JOIN {local_moderncommerce_products} p ON p.id = i.productid
          LEFT JOIN {course} c ON c.id = i.courseid
              WHERE o.status IN ('paid', 'completed')
                AND o.timecreated BETWEEN :from AND :to
                    {$itemfilter}
           GROUP BY p.id, p.name
           ORDER BY revenue DESC",
            $params,
            0,
            10
        );
        $labels = [];
        $values = [];
        foreach ($records as $record) {
            $labels[] = format_string((string) $record->name, true, ['context' => $context]);
            $values[] = round((float) $record->revenue, 2);
        }

        return self::chart(
            'top_products',
            'hbar',
            get_string('chart_top_products', 'local_moderncommerce'),
            get_string('chart_top_products_sub', 'local_moderncommerce'),
            'currency',
            pricing_service::format_price(array_sum($values)),
            self::clamp_size($size, 6),
            $labels,
            [self::series('revenue', get_string('revenue', 'local_moderncommerce'), 'bar', 'left', $values)]
        );
    }

    /**
     * Chart: revenue by product type.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param int $size Grid size.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function chart_revenue_mix(
        int $from,
        int $to,
        int $size,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $itemfilter = self::item_filter_sql($params, $productsearch, $coursesearch, 'revenuemix');
        $records = $DB->get_records_sql(
            "SELECT i.itemtype, SUM(i.total) AS revenue
               FROM {local_moderncommerce_order_items} i
               JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
          LEFT JOIN {local_moderncommerce_products} p ON p.id = i.productid
          LEFT JOIN {course} c ON c.id = i.courseid
              WHERE o.status IN ('paid', 'completed')
                AND o.timecreated BETWEEN :from AND :to
                    {$itemfilter}
           GROUP BY i.itemtype
           ORDER BY revenue DESC",
            $params
        );
        $labels = [];
        $values = [];
        foreach ($records as $record) {
            $labels[] = self::product_type_label((string) $record->itemtype);
            $values[] = round((float) $record->revenue, 2);
        }

        return self::chart(
            'revenue_mix',
            'donut',
            get_string('chart_revenue_mix', 'local_moderncommerce'),
            get_string('chart_revenue_mix_sub', 'local_moderncommerce'),
            'currency',
            pricing_service::format_price(array_sum($values)),
            self::clamp_size($size, 6),
            $labels,
            [self::series('revenue', get_string('revenue', 'local_moderncommerce'), 'bar', 'left', $values)]
        );
    }

    /**
     * Chart: top courses.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context Context.
     * @param int $size Grid size.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function chart_top_courses(
        int $from,
        int $to,
        context_system $context,
        int $size,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        $courses = self::get_courses($from, $to, $context, 10, 0, $productsearch, $coursesearch);
        $labels = [];
        $values = [];
        foreach ($courses as $course) {
            $labels[] = $course['fullname'];
            $values[] = round((float) $course['rawrevenue'], 2);
        }

        return self::chart(
            'top_courses',
            'hbar',
            get_string('topcourses', 'local_moderncommerce'),
            get_string('chart_top_products_table_sub', 'local_moderncommerce'),
            'currency',
            pricing_service::format_price(array_sum($values)),
            self::clamp_size($size, 6),
            $labels,
            [self::series('revenue', get_string('revenue', 'local_moderncommerce'), 'bar', 'left', $values)]
        );
    }

    /**
     * Chart: coupon ROI.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param int $size Grid size.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function chart_coupon_roi(
        int $from,
        int $to,
        int $size,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $orderfilter = self::order_filter_sql($params, $productsearch, $coursesearch, 'couponroi');
        $records = $DB->get_records_sql(
            "SELECT c.id, c.code,
                    COALESCE(SUM(cu.discountamount), 0) AS discount,
                    COALESCE(SUM(o.total), 0) AS revenue
               FROM {local_moderncommerce_coupon_usage} cu
               JOIN {local_moderncommerce_coupons} c ON c.id = cu.couponid
               JOIN {local_moderncommerce_orders} o ON o.id = cu.orderid
              WHERE o.status IN ('paid', 'completed')
                AND o.timecreated BETWEEN :from AND :to
                    {$orderfilter}
           GROUP BY c.id, c.code
           ORDER BY revenue DESC",
            $params,
            0,
            8
        );
        $labels = [];
        $revenue = [];
        $discount = [];
        foreach ($records as $record) {
            $labels[] = (string) $record->code;
            $revenue[] = round((float) $record->revenue, 2);
            $discount[] = round((float) $record->discount, 2);
        }

        return self::chart(
            'coupon_roi',
            'bar',
            get_string('chart_coupon_roi', 'local_moderncommerce'),
            get_string('chart_coupon_roi_sub', 'local_moderncommerce'),
            'currency',
            pricing_service::format_price(array_sum($revenue)),
            self::clamp_size($size, 6),
            $labels,
            [
                self::series('revenue', get_string('revenue', 'local_moderncommerce'), 'bar', 'left', $revenue),
                self::series('discount', get_string('discount', 'local_moderncommerce'), 'bar', 'left', $discount),
            ]
        );
    }

    /**
     * Chart: new vs returning buyers.
     *
     * @param string $period Period.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param int $size Grid size.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function chart_new_returning(
        string $period,
        int $from,
        int $to,
        int $size,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        global $DB;

        $params = [];
        $orderfilter = self::order_filter_sql($params, $productsearch, $coursesearch, 'newreturning');
        $rows = $DB->get_records_sql(
            "SELECT o.id, o.userid, o.timecreated
               FROM {local_moderncommerce_orders} o
              WHERE o.status IN ('paid', 'completed')
                    {$orderfilter}
           ORDER BY o.userid ASC, o.timecreated ASC, o.id ASC",
            $params
        );
        $firsttime = [];
        foreach ($rows as $row) {
            if (!isset($firsttime[$row->userid])) {
                $firsttime[$row->userid] = (int) $row->timecreated;
            }
        }
        $buckets = [];
        $new = [];
        $returning = [];
        foreach ($rows as $row) {
            $timecreated = (int) $row->timecreated;
            if ($timecreated < $from || $timecreated > $to) {
                continue;
            }
            [$key, $label] = self::bucket_label($period, $timecreated);
            if (!isset($buckets[$key])) {
                $buckets[$key] = $label;
                $new[$key] = 0;
                $returning[$key] = 0;
            }
            if ($timecreated === $firsttime[$row->userid]) {
                $new[$key]++;
            } else {
                $returning[$key]++;
            }
        }
        ksort($buckets);
        $labels = array_values($buckets);
        $keys = array_keys($buckets);
        $newvalues = array_map(static fn($key): float => (float) $new[$key], $keys);
        $returningvalues = array_map(static fn($key): float => (float) $returning[$key], $keys);
        $totalnew = array_sum($newvalues);
        $totalreturning = array_sum($returningvalues);
        $rate = ($totalnew + $totalreturning) > 0 ? round($totalreturning / ($totalnew + $totalreturning) * 100) : 0;

        return self::chart(
            'new_vs_returning',
            'bar',
            get_string('chart_new_returning', 'local_moderncommerce'),
            get_string('chart_new_returning_sub', 'local_moderncommerce'),
            'number',
            $rate . '%',
            self::clamp_size($size, 12),
            $labels,
            [
                self::series('new', get_string('chart_new', 'local_moderncommerce'), 'bar', 'left', $newvalues),
                self::series('returning', get_string('chart_returning', 'local_moderncommerce'), 'bar', 'left', $returningvalues),
            ]
        );
    }

    /**
     * Chart: wishlist demand.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param context_system $context Context.
     * @param int $size Grid size.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function chart_wishlist_demand(
        int $from,
        int $to,
        context_system $context,
        int $size,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $productfilter = self::product_filter_sql($params, $productsearch, $coursesearch, 'wishlist');
        $records = $DB->get_records_sql(
            "SELECT w.productid, p.name, COUNT(*) AS saves
               FROM {local_moderncommerce_wishlist} w
               JOIN {local_moderncommerce_products} p ON p.id = w.productid
              WHERE w.timecreated BETWEEN :from AND :to
                    {$productfilter}
           GROUP BY w.productid, p.name
           ORDER BY saves DESC",
            $params,
            0,
            10
        );
        $labels = [];
        $values = [];
        foreach ($records as $record) {
            $labels[] = format_string((string) $record->name, true, ['context' => $context]);
            $values[] = (float) $record->saves;
        }

        return self::chart(
            'wishlist_demand',
            'hbar',
            get_string('chart_wishlist', 'local_moderncommerce'),
            get_string('chart_wishlist_sub', 'local_moderncommerce'),
            'number',
            number_format(array_sum($values)),
            self::clamp_size($size, 6),
            $labels,
            [self::series('saves', get_string('chart_saves', 'local_moderncommerce'), 'bar', 'left', $values)]
        );
    }

    /**
     * Chart: geo revenue.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param int $size Grid size.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @return array
     */
    private static function chart_geo_revenue(
        int $from,
        int $to,
        int $size,
        string $productsearch = '',
        string $coursesearch = ''
    ): array {
        global $DB;

        $params = ['from' => $from, 'to' => $to];
        $orderfilter = self::order_filter_sql($params, $productsearch, $coursesearch, 'georevenue');
        $records = $DB->get_records_sql(
            "SELECT a.country, SUM(o.total) AS revenue
               FROM {local_moderncommerce_order_addresses} a
               JOIN {local_moderncommerce_orders} o ON o.id = a.orderid
              WHERE a.addresstype = 'billing'
                AND o.status IN ('paid', 'completed')
                AND o.timecreated BETWEEN :from AND :to
                AND a.country IS NOT NULL
                AND a.country <> ''
                    {$orderfilter}
           GROUP BY a.country
           ORDER BY revenue DESC",
            $params,
            0,
            10
        );
        $labels = [];
        $values = [];
        foreach ($records as $record) {
            $labels[] = self::country_label((string) $record->country);
            $values[] = round((float) $record->revenue, 2);
        }

        return self::chart(
            'geo_revenue',
            'hbar',
            get_string('chart_geo', 'local_moderncommerce'),
            get_string('chart_geo_sub', 'local_moderncommerce'),
            'currency',
            pricing_service::format_price(array_sum($values)),
            self::clamp_size($size, 6),
            $labels,
            [self::series('revenue', get_string('revenue', 'local_moderncommerce'), 'bar', 'left', $values)]
        );
    }

    /**
     * Build chart payload.
     *
     * @param string $id Chart id.
     * @param string $type Renderer type.
     * @param string $title Title.
     * @param string $subtitle Subtitle.
     * @param string $formattype Format type.
     * @param string $total Formatted total.
     * @param int $size Grid size.
     * @param array $labels Labels.
     * @param array $series Series.
     * @return array
     */
    private static function chart(
        string $id,
        string $type,
        string $title,
        string $subtitle,
        string $formattype,
        string $total,
        int $size,
        array $labels,
        array $series
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'subtitle' => $subtitle,
            'formattype' => $formattype,
            'total' => $total,
            'empty' => empty($labels),
            'size' => self::clamp_size($size, 6),
            'labels' => array_values($labels),
            'series' => array_values($series),
        ];
    }

    /**
     * Build chart series.
     *
     * @param string $key Series key.
     * @param string $label Series label.
     * @param string $charttype line|bar.
     * @param string $axis left|right.
     * @param array $data Numeric values.
     * @return array
     */
    private static function series(string $key, string $label, string $charttype, string $axis, array $data): array {
        return [
            'key' => $key,
            'label' => $label,
            'charttype' => $charttype,
            'axis' => $axis,
            'data' => array_map('floatval', $data),
        ];
    }

    /**
     * PHP bucket label for a timestamp.
     *
     * @param string $period Period.
     * @param int $timestamp Timestamp.
     * @return array{int, string}
     */
    private static function bucket_label(string $period, int $timestamp): array {
        switch ($period) {
            case 'daily':
                $key = strtotime(date('Y-m-d', $timestamp));
                return [$key, date('Y-m-d', $timestamp)];
            case 'weekly':
                $dow = (int) date('N', $timestamp);
                $key = strtotime(date('Y-m-d', $timestamp)) - ($dow - 1) * DAYSECS;
                return [$key, date('o-\WW', $timestamp)];
            case 'yearly':
                $key = strtotime(date('Y-01-01', $timestamp));
                return [$key, date('Y', $timestamp)];
            case 'monthly':
            default:
                $key = strtotime(date('Y-m-01', $timestamp));
                return [$key, date('Y-m', $timestamp)];
        }
    }

    /**
     * Localised status label.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_label(string $status): string {
        return localisation::status_label($status, ['orderstatus']);
    }

    /**
     * Badge class for status.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_class(string $status): string {
        switch ($status) {
            case 'paid':
            case 'completed':
                return 'success';
            case 'pending':
            case 'processing':
                return 'warning';
            case 'refunded':
                return 'info';
            case 'failed':
            case 'cancelled':
                return 'danger';
            default:
                return 'neutral';
        }
    }

    /**
     * Product type label.
     *
     * @param string $type Type.
     * @return string
     */
    private static function product_type_label(string $type): string {
        $type = trim($type);
        if ($type === '') {
            return '-';
        }
        $keys = ['producttype_' . $type, 'itemtype_' . $type];
        foreach ($keys as $key) {
            if (get_string_manager()->string_exists($key, 'local_moderncommerce')) {
                return get_string($key, 'local_moderncommerce');
            }
        }
        return ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Country label.
     *
     * @param string $code Country code.
     * @return string
     */
    private static function country_label(string $code): string {
        $code = strtoupper(trim($code));
        if ($code !== '' && get_string_manager()->string_exists($code, 'core_countries')) {
            return get_string($code, 'core_countries');
        }
        return $code;
    }

    /**
     * Decimal export value.
     *
     * @param float $value Value.
     * @return string
     */
    private static function decimal_export(float $value): string {
        return number_format($value, 2, '.', '');
    }

    /**
     * Clamp grid size.
     *
     * @param int $size Size.
     * @param int $default Default.
     * @return int
     */
    private static function clamp_size(int $size, int $default): int {
        return in_array($size, self::SIZES, true) ? $size : $default;
    }
}
