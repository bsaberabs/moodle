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
 * External API returning the Modern Commerce admin dashboard dataset.
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
use local_moderncommerce\localisation;
use local_moderncommerce\services\dashboard_panel_service;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * Build dashboard KPIs, recent orders, top products, and setup alerts.
 */
class get_dashboard extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute.
     *
     * @return array
     */
    public static function execute(): array {
        self::validate_parameters(self::execute_parameters(), []);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewreports', $context);

        return [
            'metrics' => self::ordered_metrics(),
            'recentorders' => self::get_recent_orders(),
            'topproducts' => self::get_top_products($context),
            'alerts' => self::get_alerts(),
            'warnings' => [],
        ];
    }

    /**
     * KPI metric tiles filtered and ordered by the current admin's panel layout.
     *
     * @return array
     */
    private static function ordered_metrics(): array {
        $bykey = [];
        foreach (self::get_metrics() as $metric) {
            $bykey[$metric['key']] = $metric;
        }

        $ordered = [];
        foreach (dashboard_panel_service::enabled_in_order() as $key => $size) {
            if (isset($bykey[$key])) {
                $metric = $bykey[$key];
                $metric['size'] = (int) $size;
                $ordered[] = $metric;
            }
        }

        return $ordered;
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'metrics' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'Metric key.'),
                'label' => new external_value(PARAM_TEXT, 'Metric label.'),
                'value' => new external_value(PARAM_TEXT, 'Formatted value.'),
                'variant' => new external_value(PARAM_ALPHA, 'Tile variant.'),
                'icon' => new external_value(PARAM_TEXT, 'Bootstrap icon class.'),
                'hasdelta' => new external_value(PARAM_BOOL, 'Whether a delta is shown.'),
                'delta' => new external_value(PARAM_TEXT, 'Delta label.'),
                'deltaup' => new external_value(PARAM_BOOL, 'Whether the delta is positive.'),
                'deltadown' => new external_value(PARAM_BOOL, 'Whether the delta is negative.'),
                'size' => new external_value(PARAM_INT, '12-grid span: 12|6|4|3.', VALUE_OPTIONAL),
            ])),
            'recentorders' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Order ID.'),
                'ordernumber' => new external_value(PARAM_TEXT, 'Order number.'),
                'customername' => new external_value(PARAM_TEXT, 'Customer name.'),
                'displaytotal' => new external_value(PARAM_TEXT, 'Formatted total.'),
                'status' => new external_value(PARAM_ALPHA, 'Status.'),
                'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
                'statusclass' => new external_value(PARAM_ALPHA, 'Status badge class.'),
                'date' => new external_value(PARAM_TEXT, 'Formatted date.'),
                'viewurl' => new external_value(PARAM_URL, 'Order detail URL.'),
            ])),
            'topproducts' => new external_multiple_structure(new external_single_structure([
                'rank' => new external_value(PARAM_INT, 'Rank.'),
                'name' => new external_value(PARAM_TEXT, 'Product name.'),
                'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
                'sold' => new external_value(PARAM_INT, 'Units sold.'),
                'displayrevenue' => new external_value(PARAM_TEXT, 'Formatted revenue.'),
            ])),
            'alerts' => new external_multiple_structure(new external_single_structure([
                'level' => new external_value(PARAM_ALPHA, 'Alert level (warning, info).'),
                'message' => new external_value(PARAM_TEXT, 'Alert message.'),
                'actionlabel' => new external_value(PARAM_TEXT, 'Action label.'),
                'actionurl' => new external_value(PARAM_URL, 'Action URL.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Build the KPI metric tiles.
     *
     * @return array
     */
    private static function get_metrics(): array {
        global $DB;

        $paidwhere = "status IN ('paid', 'completed')";
        $monthstart = strtotime('first day of this month 00:00:00');
        $lastmonthstart = strtotime('first day of last month 00:00:00');

        $revenue = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(total), 0) FROM {local_moderncommerce_orders} WHERE {$paidwhere}"
        );
        $thismonth = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(total), 0) FROM {local_moderncommerce_orders}
              WHERE {$paidwhere} AND timecreated >= :start",
            ['start' => $monthstart]
        );
        $lastmonth = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(total), 0) FROM {local_moderncommerce_orders}
              WHERE {$paidwhere} AND timecreated >= :laststart AND timecreated < :thisstart",
            ['laststart' => $lastmonthstart, 'thisstart' => $monthstart]
        );

        $totalorders = (int) $DB->count_records('local_moderncommerce_orders');
        $pendingorders = (int) $DB->count_records_select(
            'local_moderncommerce_orders',
            "status IN ('pending', 'processing')"
        );
        $activeproducts = (int) $DB->count_records('local_moderncommerce_products', ['status' => 'active']);

        [$deltatext, $up, $down] = self::delta($thismonth, $lastmonth);

        return [
            [
                'key' => 'revenue',
                'label' => get_string('totalrevenue', 'local_moderncommerce'),
                'value' => pricing_service::format_price($revenue),
                'variant' => 'primary',
                'icon' => 'bi-cash-stack',
                'hasdelta' => true,
                'delta' => $deltatext,
                'deltaup' => $up,
                'deltadown' => $down,
            ],
            [
                'key' => 'orders',
                'label' => get_string('totalorders', 'local_moderncommerce'),
                'value' => self::count_label($totalorders),
                'variant' => 'success',
                'icon' => 'bi-bag-check',
                'hasdelta' => false,
                'delta' => '',
                'deltaup' => false,
                'deltadown' => false,
            ],
            [
                'key' => 'pending',
                'label' => get_string('pendingorders', 'local_moderncommerce'),
                'value' => self::count_label($pendingorders),
                'variant' => 'warning',
                'icon' => 'bi-hourglass-split',
                'hasdelta' => false,
                'delta' => '',
                'deltaup' => false,
                'deltadown' => false,
            ],
            [
                'key' => 'products',
                'label' => get_string('activeproducts', 'local_moderncommerce'),
                'value' => self::count_label($activeproducts),
                'variant' => 'info',
                'icon' => 'bi-box-seam',
                'hasdelta' => false,
                'delta' => '',
                'deltaup' => false,
                'deltadown' => false,
            ],
        ];
    }

    /**
     * Compute a month-over-month delta label.
     *
     * @param float $current Current period value.
     * @param float $previous Previous period value.
     * @return array [label, up, down]
     */
    private static function delta(float $current, float $previous): array {
        if ($previous <= 0) {
            if ($current > 0) {
                return ['+100%', true, false];
            }
            return ['0%', false, false];
        }

        $percent = (int) round((($current - $previous) / $previous) * 100);
        $sign = $percent > 0 ? '+' : '';

        return [$sign . $percent . '%', $percent > 0, $percent < 0];
    }

    /**
     * Localised number label.
     *
     * @param int $value Value.
     * @return string
     */
    private static function count_label(int $value): string {
        return number_format($value);
    }

    /**
     * Recent orders for the activity panel.
     *
     * @return array
     */
    private static function get_recent_orders(): array {
        global $DB;

        $records = $DB->get_records('local_moderncommerce_orders', null, 'timecreated DESC, id DESC', '*', 0, 8);
        if (empty($records)) {
            return [];
        }

        $userids = array_values(array_unique(array_map(static function ($r): int {
            return (int) $r->userid;
        }, $records)));
        $users = $DB->get_records_list('user', 'id', $userids);

        $orders = [];
        foreach ($records as $record) {
            $user = $users[$record->userid] ?? null;
            $status = (string) $record->status;
            $orders[] = [
                'id' => (int) $record->id,
                'ordernumber' => (string) $record->ordernumber,
                'customername' => $user ? fullname($user) : get_string('unknownuser', 'local_moderncommerce'),
                'displaytotal' => pricing_service::format_order_price($record->total, $record),
                'status' => $status,
                'statuslabel' => self::status_label($status),
                'statusclass' => self::status_class($status),
                'date' => userdate((int) $record->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                'viewurl' => (new moodle_url('/local/moderncommerce/admin/order_view.php', ['id' => $record->id]))->out(false),
            ];
        }

        return $orders;
    }

    /**
     * Top selling products by revenue.
     *
     * @param context_system $context System context.
     * @return array
     */
    private static function get_top_products(context_system $context): array {
        global $DB;

        $sql = "SELECT p.id, p.name, p.producttype,
                       COALESCE(SUM(oi.quantity), 0) AS sold,
                       COALESCE(SUM(oi.total), 0) AS revenue
                  FROM {local_moderncommerce_order_items} oi
                  JOIN {local_moderncommerce_orders} o ON o.id = oi.orderid
                  JOIN {local_moderncommerce_products} p ON p.id = oi.productid
                 WHERE o.status IN ('paid', 'completed')
              GROUP BY p.id, p.name, p.producttype
              ORDER BY revenue DESC";

        $records = $DB->get_records_sql($sql, [], 0, 5);

        $rows = [];
        $rank = 1;
        foreach ($records as $record) {
            $rows[] = [
                'rank' => $rank++,
                'name' => format_string($record->name, true, ['context' => $context]),
                'producttype' => (string) $record->producttype,
                'sold' => (int) round((float) $record->sold),
                'displayrevenue' => pricing_service::format_price((float) $record->revenue),
            ];
        }

        return $rows;
    }

    /**
     * Setup/health alerts.
     *
     * @return array
     */
    private static function get_alerts(): array {
        global $DB;

        $alerts = [];

        // Active products without a regular enabled price.
        $unpriced = (int) $DB->count_records_sql(
            "SELECT COUNT(p.id)
               FROM {local_moderncommerce_products} p
              WHERE p.status = 'active'
                AND p.producttype = 'course'
                AND NOT EXISTS (
                    SELECT 1 FROM {local_moderncommerce_product_prices} pr
                     WHERE pr.productid = p.id AND pr.pricetype = 'regular' AND pr.enabled = 1
                )"
        );
        if ($unpriced > 0) {
            $alerts[] = [
                'level' => 'warning',
                'message' => get_string('alertunpriced', 'local_moderncommerce', $unpriced),
                'actionlabel' => get_string('coursesandpricing', 'local_moderncommerce'),
                'actionurl' => (new moodle_url(
                    '/local/moderncommerce/admin/pricing.php',
                    ['pricingstatus' => 'unpriced']
                ))->out(false),
            ];
        }

        // No enabled payment gateway.
        $enabledgateways = $DB->get_manager()->table_exists('local_moderncommerce_gateways')
            ? (int) $DB->count_records('local_moderncommerce_gateways', ['enabled' => 1])
            : 0;
        if ($enabledgateways === 0) {
            $alerts[] = [
                'level' => 'warning',
                'message' => get_string('alertnogateway', 'local_moderncommerce'),
                'actionlabel' => get_string('paymentgateways', 'local_moderncommerce'),
                'actionurl' => (new moodle_url('/local/moderncommerce/admin/gateways.php'))->out(false),
            ];
        }

        return $alerts;
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
     * Status badge class.
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
            case 'failed':
            case 'cancelled':
                return 'danger';
            case 'refunded':
                return 'info';
            default:
                return 'neutral';
        }
    }
}
