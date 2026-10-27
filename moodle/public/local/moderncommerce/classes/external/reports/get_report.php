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
 * External API returning Modern Commerce report data.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\reports;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\report_service;

/**
 * Return analytics, metrics, charts, and preview rows for the admin reports screen.
 */
class get_report extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHA, 'Report type: sales, courses, or coupons.', VALUE_DEFAULT, 'sales'),
            'period' => new external_value(PARAM_ALPHA, 'Bucket: daily, weekly, monthly, yearly.', VALUE_DEFAULT, 'monthly'),
            'from' => new external_value(PARAM_INT, 'Start timestamp.', VALUE_DEFAULT, 0),
            'to' => new external_value(PARAM_INT, 'End timestamp.', VALUE_DEFAULT, 0),
            'page' => new external_value(PARAM_INT, 'Zero-based table page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per table page.', VALUE_DEFAULT, 10),
            'productsearch' => new external_value(PARAM_TEXT, 'Product name, SKU, or slug search.', VALUE_DEFAULT, ''),
            'coursesearch' => new external_value(PARAM_TEXT, 'Course name, shortname, idnumber, or id search.', VALUE_DEFAULT, ''),
            'tablesearch' => new external_value(PARAM_TEXT, 'Table row search.', VALUE_DEFAULT, ''),
            'columns' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Selected column key.'),
                'Selected report columns.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $type Report type.
     * @param string $period Bucket.
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @param int $page Zero-based table page.
     * @param int $perpage Rows per table page.
     * @param string $productsearch Product search text.
     * @param string $coursesearch Course search text.
     * @param string $tablesearch Table-only search text.
     * @param array $columns Selected columns.
     * @return array
     */
    public static function execute(
        string $type = 'sales',
        string $period = 'monthly',
        int $from = 0,
        int $to = 0,
        int $page = 0,
        int $perpage = 10,
        string $productsearch = '',
        string $coursesearch = '',
        string $tablesearch = '',
        array $columns = []
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'type' => $type,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'page' => $page,
            'perpage' => $perpage,
            'productsearch' => $productsearch,
            'coursesearch' => $coursesearch,
            'tablesearch' => $tablesearch,
            'columns' => $columns,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewreports', $context);

        return report_service::get_report(
            $params['type'],
            $params['period'],
            $params['from'],
            $params['to'],
            $context,
            $params['columns'],
            $params['page'],
            $params['perpage'],
            $params['productsearch'],
            $params['coursesearch'],
            $params['tablesearch']
        );
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'type' => new external_value(PARAM_ALPHA, 'Applied report type.'),
            'period' => new external_value(PARAM_ALPHA, 'Applied bucket.'),
            'from' => new external_value(PARAM_INT, 'Applied start timestamp.'),
            'to' => new external_value(PARAM_INT, 'Applied end timestamp.'),
            'stats' => self::stats_structure(),
            'metrics' => new external_multiple_structure(self::metric_structure()),
            'charts' => new external_multiple_structure(self::chart_structure()),
            'sales' => self::sales_structure(),
            'courses' => new external_multiple_structure(self::course_structure()),
            'coupons' => new external_multiple_structure(self::coupon_structure()),
            'availablecolumns' => new external_multiple_structure(self::column_structure()),
            'selectedcolumns' => new external_multiple_structure(new external_value(PARAM_ALPHANUMEXT, 'Selected column key.')),
            'tablerows' => new external_multiple_structure(self::table_row_structure()),
            'tabletotal' => new external_value(PARAM_INT, 'Total matching table rows.'),
            'tablepage' => new external_value(PARAM_INT, 'Applied zero-based table page.'),
            'tableperpage' => new external_value(PARAM_INT, 'Applied rows per table page.'),
            'tabletruncated' => new external_value(PARAM_BOOL, 'Whether the preview table is truncated.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'displayrevenue' => new external_value(PARAM_TEXT, 'Formatted revenue.'),
            'totalorders' => new external_value(PARAM_INT, 'Order count.'),
            'displayaverage' => new external_value(PARAM_TEXT, 'Formatted average order value.'),
            'couponsused' => new external_value(PARAM_INT, 'Coupon redemptions.'),
        ]);
    }

    /**
     * Metric structure.
     *
     * @return external_single_structure
     */
    private static function metric_structure(): external_single_structure {
        return new external_single_structure([
            'key' => new external_value(PARAM_ALPHANUMEXT, 'Metric key.'),
            'label' => new external_value(PARAM_TEXT, 'Metric label.'),
            'value' => new external_value(PARAM_TEXT, 'Formatted value.'),
            'variant' => new external_value(PARAM_ALPHA, 'Tile variant.'),
            'icon' => new external_value(PARAM_TEXT, 'Bootstrap icon class.'),
            'hasdelta' => new external_value(PARAM_BOOL, 'Whether a delta is shown.'),
            'delta' => new external_value(PARAM_TEXT, 'Delta label.'),
            'deltaup' => new external_value(PARAM_BOOL, 'Whether the delta is positive.'),
            'deltadown' => new external_value(PARAM_BOOL, 'Whether the delta is negative.'),
            'size' => new external_value(PARAM_INT, '12-grid span: 12|6|4|3.'),
        ]);
    }

    /**
     * Chart structure.
     *
     * @return external_single_structure
     */
    private static function chart_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_ALPHANUMEXT, 'Chart id.'),
            'type' => new external_value(PARAM_ALPHA, 'Renderer type.'),
            'title' => new external_value(PARAM_TEXT, 'Title.'),
            'subtitle' => new external_value(PARAM_TEXT, 'Subtitle.'),
            'formattype' => new external_value(PARAM_ALPHA, 'currency|number|percent.'),
            'total' => new external_value(PARAM_TEXT, 'Pre-formatted headline value.'),
            'empty' => new external_value(PARAM_BOOL, 'Whether the chart has no data.'),
            'size' => new external_value(PARAM_INT, '12-grid span: 12|6|4|3.'),
            'labels' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Category label.')),
            'series' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'Series key.'),
                'label' => new external_value(PARAM_TEXT, 'Series label.'),
                'charttype' => new external_value(PARAM_ALPHA, 'line|bar.'),
                'axis' => new external_value(PARAM_ALPHA, 'left|right.'),
                'data' => new external_multiple_structure(new external_value(PARAM_FLOAT, 'Value.')),
            ])),
        ]);
    }

    /**
     * Sales bucket structure.
     *
     * @return external_single_structure
     */
    private static function sales_structure(): external_single_structure {
        return new external_single_structure([
            'maxrevenue' => new external_value(PARAM_FLOAT, 'Largest bucket revenue.'),
            'rows' => new external_multiple_structure(new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Bucket label.'),
                'rawrevenue' => new external_value(PARAM_FLOAT, 'Raw revenue.'),
                'displayrevenue' => new external_value(PARAM_TEXT, 'Formatted revenue.'),
                'orders' => new external_value(PARAM_INT, 'Orders in bucket.'),
                'displayaverage' => new external_value(PARAM_TEXT, 'Formatted average.'),
            ])),
        ]);
    }

    /**
     * Course row structure.
     *
     * @return external_single_structure
     */
    private static function course_structure(): external_single_structure {
        return new external_single_structure([
            'rank' => new external_value(PARAM_INT, 'Rank.'),
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Course name.'),
            'orders' => new external_value(PARAM_INT, 'Order count.'),
            'enrollments' => new external_value(PARAM_INT, 'Units sold.'),
            'rawrevenue' => new external_value(PARAM_FLOAT, 'Raw revenue.'),
            'displayrevenue' => new external_value(PARAM_TEXT, 'Formatted revenue.'),
        ]);
    }

    /**
     * Coupon row structure.
     *
     * @return external_single_structure
     */
    private static function coupon_structure(): external_single_structure {
        return new external_single_structure([
            'code' => new external_value(PARAM_TEXT, 'Coupon code.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Discount type label.'),
            'valueformatted' => new external_value(PARAM_TEXT, 'Formatted value.'),
            'usages' => new external_value(PARAM_INT, 'Redemption count.'),
            'displaytotaldiscount' => new external_value(PARAM_TEXT, 'Formatted total discount.'),
        ]);
    }

    /**
     * Column structure.
     *
     * @return external_single_structure
     */
    private static function column_structure(): external_single_structure {
        return new external_single_structure([
            'key' => new external_value(PARAM_ALPHANUMEXT, 'Column key.'),
            'label' => new external_value(PARAM_TEXT, 'Column label.'),
            'default' => new external_value(PARAM_BOOL, 'Whether the column is selected by default.'),
            'align' => new external_value(PARAM_ALPHA, 'left|right|center.'),
        ]);
    }

    /**
     * Generic table row structure.
     *
     * @return external_single_structure
     */
    private static function table_row_structure(): external_single_structure {
        return new external_single_structure([
            'cells' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'Column key.'),
                'value' => new external_value(PARAM_TEXT, 'Display value.'),
                'exportvalue' => new external_value(PARAM_TEXT, 'CSV value.'),
                'badge' => new external_value(PARAM_BOOL, 'Render as badge.'),
                'badgeclass' => new external_value(PARAM_ALPHANUMEXT, 'Badge variant.'),
                'href' => new external_value(PARAM_RAW, 'Optional cell link.'),
            ])),
        ]);
    }
}
