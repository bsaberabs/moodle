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
 * External API returning Modern Commerce admin dashboard chart datasets.
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
use local_moderncommerce\services\dashboard_layout_service;
use local_moderncommerce\services\pricing_service;

/**
 * Build the dashboard chart datasets (Phase 1: snapshot-backed charts).
 */
class get_dashboard_charts extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'range' => new external_value(PARAM_ALPHANUMEXT, 'Window: 7d|30d|90d|12m|ytd.', VALUE_DEFAULT, '30d'),
            'granularity' => new external_value(PARAM_ALPHA, 'Override bucket: day|week|month.', VALUE_DEFAULT, ''),
            'charts' => new external_value(PARAM_TEXT, 'Optional CSV of chart ids to return.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $range Window key.
     * @param string $granularity Optional bucket override.
     * @param string $charts Optional CSV filter.
     * @return array
     */
    public static function execute(string $range = '30d', string $granularity = '', string $charts = ''): array {
        [$range, $granularity, $charts] = array_values(self::validate_parameters(self::execute_parameters(), [
            'range' => $range, 'granularity' => $granularity, 'charts' => $charts,
        ]));

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewreports', $context);

        $currency = pricing_service::get_currency_config();
        [$from, $to, $gran] = self::resolve_window($range);
        if (in_array($granularity, ['day', 'week', 'month'], true)) {
            $gran = $granularity;
        }

        $wanted = array_filter(array_map('trim', explode(',', $charts)));
        $builders = [
            'revenue_trend' => 'build_revenue_trend',
            'orders_conversion' => 'build_orders_conversion',
            'aov_trend' => 'build_aov_trend',
            'top_products' => 'build_top_products',
            'revenue_mix' => 'build_revenue_mix',
            'gateway_success' => 'build_gateway_success',
            'leakage_trend' => 'build_leakage_trend',
            'cart_funnel' => 'build_cart_funnel',
            'new_vs_returning' => 'build_new_vs_returning',
            'sales_heatmap' => 'build_sales_heatmap',
            'tax_trend' => 'build_tax_trend',
            'coupon_roi' => 'build_coupon_roi',
            'key_redemption' => 'build_key_redemption',
            'time_to_payment' => 'build_time_to_payment',
            'wishlist_demand' => 'build_wishlist_demand',
            'geo_revenue' => 'build_geo_revenue',
            'recent_orders' => 'build_recent_orders',
            'top_products_table' => 'build_top_products_table',
        ];

        // Only enabled charts, in the admin-configured order, each carrying its grid size.
        $layout = dashboard_layout_service::enabled_in_order();

        $out = [];
        foreach ($layout as $id => $size) {
            if (!isset($builders[$id])) {
                continue;
            }
            if ($wanted && !in_array($id, $wanted, true)) {
                continue;
            }
            $method = $builders[$id];
            $chart = self::$method($currency, $from, $to, $gran);
            $chart['size'] = (int) $size;
            $out[] = $chart;
        }

        return [
            'currency' => [
                'code' => $currency->currency,
                'symbol' => $currency->symbol,
                'position' => $currency->position,
                'decimals' => (int) $currency->decimals,
            ],
            'range' => $range,
            'granularity' => $gran,
            'charts' => $out,
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
            'currency' => new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.'),
                'symbol' => new external_value(PARAM_RAW, 'Currency symbol.'),
                'position' => new external_value(PARAM_ALPHA, 'before|after.'),
                'decimals' => new external_value(PARAM_INT, 'Decimal places.'),
            ]),
            'range' => new external_value(PARAM_ALPHANUMEXT, 'Resolved range.'),
            'granularity' => new external_value(PARAM_ALPHA, 'Resolved granularity.'),
            'charts' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Chart id.'),
                'type' => new external_value(PARAM_ALPHA, 'Renderer type.'),
                'title' => new external_value(PARAM_TEXT, 'Title.'),
                'subtitle' => new external_value(PARAM_TEXT, 'Subtitle.'),
                'formattype' => new external_value(PARAM_ALPHA, 'currency|number|percent.'),
                'total' => new external_value(PARAM_TEXT, 'Pre-formatted headline value.'),
                'empty' => new external_value(PARAM_BOOL, 'Whether the chart has no data.'),
                'size' => new external_value(PARAM_INT, '12-grid span: 12|6|4|3.', VALUE_OPTIONAL),
                'stacked' => new external_value(PARAM_BOOL, 'Whether bar series stack.', VALUE_OPTIONAL),
                'labels' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Category label.')),
                'series' => new external_multiple_structure(new external_single_structure([
                    'key' => new external_value(PARAM_ALPHANUMEXT, 'Series key.'),
                    'label' => new external_value(PARAM_TEXT, 'Series label.'),
                    'charttype' => new external_value(PARAM_ALPHA, 'line|bar.'),
                    'axis' => new external_value(PARAM_ALPHA, 'left|right.'),
                    'data' => new external_multiple_structure(new external_value(PARAM_FLOAT, 'Value.')),
                ])),
                'matrix' => new external_single_structure([
                    'rows' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Row label.')),
                    'cols' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Column label.')),
                    'values' => new external_multiple_structure(
                        new external_multiple_structure(new external_value(PARAM_FLOAT, 'Cell value.'))
                    ),
                ], 'Heatmap matrix.', VALUE_OPTIONAL),
                'links' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'Drill-in URL for the matching label (empty for none).'),
                    'Optional per-label drill links.',
                    VALUE_OPTIONAL
                ),
                'table' => new external_single_structure([
                    'columns' => new external_multiple_structure(new external_single_structure([
                        'label' => new external_value(PARAM_TEXT, 'Column label.'),
                        'align' => new external_value(PARAM_ALPHA, 'left|right|center.'),
                    ])),
                    'rows' => new external_multiple_structure(new external_single_structure([
                        'cells' => new external_multiple_structure(new external_single_structure([
                            'value' => new external_value(PARAM_TEXT, 'Cell value.'),
                            'badge' => new external_value(PARAM_BOOL, 'Render as badge.', VALUE_OPTIONAL),
                            'badgeclass' => new external_value(PARAM_ALPHANUMEXT, 'Badge variant.', VALUE_OPTIONAL),
                            'href' => new external_value(PARAM_RAW, 'Optional cell link.', VALUE_OPTIONAL),
                        ])),
                    ])),
                ], 'Table payload (type=table).', VALUE_OPTIONAL),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Resolve a range key to [from, to, granularity].
     *
     * @param string $range Window key.
     * @return array
     */
    private static function resolve_window(string $range): array {
        $today = strtotime('today midnight');
        $now = time();
        switch ($range) {
            case '7d':
                return [$today - 6 * 86400, $now, 'day'];
            case '90d':
                return [$today - 89 * 86400, $now, 'week'];
            case '12m':
                return [strtotime('-11 months', $today), $now, 'month'];
            case 'ytd':
                return [strtotime(date('Y-01-01', $today)), $now, 'month'];
            case '30d':
            default:
                return [$today - 29 * 86400, $now, 'day'];
        }
    }

    /**
     * Bucket key + label for a timestamp at the given granularity.
     *
     * @param int $ts Timestamp.
     * @param string $gran day|week|month.
     * @return array [int key, string label]
     */
    private static function bucket(int $ts, string $gran): array {
        if ($gran === 'month') {
            $k = strtotime(date('Y-m-01', $ts));
            return [$k, userdate($k, '%b %Y')];
        }
        if ($gran === 'week') {
            $dow = (int) date('N', $ts); // 1=Mon..7=Sun.
            $k = strtotime(date('Y-m-d', $ts)) - ($dow - 1) * 86400;
            return [$k, userdate($k, '%b %d')];
        }
        $k = strtotime(date('Y-m-d', $ts));
        return [$k, userdate($k, '%b %d')];
    }

    /**
     * Fetch daily snapshot rows in window.
     *
     * @param \stdClass $cur Currency config.
     * @param int $from From ts.
     * @param int $to To ts.
     * @return array
     */
    private static function daily_rows(\stdClass $cur, int $from, int $to): array {
        global $DB;
        // Single-currency design: do NOT filter by the per-row currency (it goes stale
        // when the admin changes the global currency). Sum all and format via the site helper.
        return $DB->get_records_sql(
            "SELECT id, reportdate, orders, paidorders, refunds, gross, discount, tax, net
               FROM {local_moderncommerce_report_daily}
              WHERE reportdate >= :from AND reportdate <= :to
           ORDER BY reportdate ASC",
            ['from' => $from, 'to' => $to]
        );
    }

    /**
     * Chart 1: revenue trend (net + gross lines).
     */
    private static function build_revenue_trend(\stdClass $cur, int $from, int $to, string $gran): array {
        $rows = self::daily_rows($cur, $from, $to);
        $labels = [];
        $net = [];
        $gross = [];
        $order = [];
        foreach ($rows as $r) {
            [$k, $label] = self::bucket((int) $r->reportdate, $gran);
            if (!isset($order[$k])) {
                $order[$k] = $label;
                $net[$k] = 0;
                $gross[$k] = 0;
            }
            $net[$k] += (float) $r->net;
            $gross[$k] += (float) $r->gross;
        }
        ksort($order);
        foreach ($order as $k => $label) {
            $labels[] = $label;
        }
        $netvals = array_map(fn($k) => round($net[$k], 2), array_keys($order));
        $grossvals = array_map(fn($k) => round($gross[$k], 2), array_keys($order));
        $totalnet = array_sum($netvals);

        return [
            'id' => 'revenue_trend',
            'type' => 'line',
            'title' => get_string('chart_revenue_trend', 'local_moderncommerce'),
            'subtitle' => get_string('chart_revenue_trend_sub', 'local_moderncommerce'),
            'formattype' => 'currency',
            'total' => pricing_service::format_price($totalnet),
            'empty' => empty($labels) || $totalnet <= 0,
            'labels' => $labels,
            'series' => [
                self::series('net', get_string('chart_net', 'local_moderncommerce'), 'line', 'left', $netvals),
                self::series('gross', get_string('chart_gross', 'local_moderncommerce'), 'line', 'left', $grossvals),
            ],
        ];
    }

    /**
     * Chart 3: orders vs paid + conversion rate.
     */
    private static function build_orders_conversion(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        // The daily snapshot stores paid-only counts, so total orders are read live from
        // the raw orders table (all statuses) to give a real checkout-conversion rate.
        $rows = $DB->get_records_sql(
            "SELECT id, timecreated, status
               FROM {local_moderncommerce_orders}
              WHERE timecreated >= :from AND timecreated <= :to",
            ['from' => $from, 'to' => $to]
        );
        $order = [];
        $ord = [];
        $paid = [];
        foreach ($rows as $r) {
            [$k, $label] = self::bucket((int) $r->timecreated, $gran);
            if (!isset($order[$k])) {
                $order[$k] = $label;
                $ord[$k] = 0;
                $paid[$k] = 0;
            }
            $ord[$k]++;
            if (in_array($r->status, ['paid', 'completed'], true)) {
                $paid[$k]++;
            }
        }
        ksort($order);
        $labels = array_values($order);
        $ordvals = array_map(fn($k) => (float) $ord[$k], array_keys($order));
        $paidvals = array_map(fn($k) => (float) $paid[$k], array_keys($order));
        $ratevals = [];
        foreach (array_keys($order) as $k) {
            $ratevals[] = $ord[$k] > 0 ? round($paid[$k] / $ord[$k] * 100, 1) : 0.0;
        }
        $totalord = array_sum($ordvals);
        $totalpaid = array_sum($paidvals);
        $rate = $totalord > 0 ? round($totalpaid / $totalord * 100) : 0;

        return [
            'id' => 'orders_conversion',
            'type' => 'combo',
            'title' => get_string('chart_orders_conversion', 'local_moderncommerce'),
            'subtitle' => get_string('chart_orders_conversion_sub', 'local_moderncommerce'),
            'formattype' => 'number',
            'total' => $rate . '%',
            'empty' => $totalord <= 0,
            'labels' => $labels,
            'series' => [
                self::series('orders', get_string('chart_orders', 'local_moderncommerce'), 'bar', 'left', $ordvals),
                self::series('paid', get_string('chart_paid', 'local_moderncommerce'), 'bar', 'left', $paidvals),
                self::series('rate', get_string('chart_rate', 'local_moderncommerce'), 'line', 'right', $ratevals),
            ],
        ];
    }

    /**
     * Chart 5: top products by net revenue.
     */
    private static function build_top_products(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $context = context_system::instance();
        $records = $DB->get_records_sql(
            "SELECT rp.productid, p.name, SUM(rp.net) AS net
               FROM {local_moderncommerce_report_products} rp
               JOIN {local_moderncommerce_products} p ON p.id = rp.productid
              WHERE rp.reportdate >= :from AND rp.reportdate <= :to
           GROUP BY rp.productid, p.name
           ORDER BY net DESC",
            ['from' => $from, 'to' => $to],
            0,
            10
        );
        $labels = [];
        $vals = [];
        foreach ($records as $r) {
            $labels[] = format_string($r->name, true, ['context' => $context]);
            $vals[] = round((float) $r->net, 2);
        }

        return [
            'id' => 'top_products',
            'type' => 'hbar',
            'title' => get_string('chart_top_products', 'local_moderncommerce'),
            'subtitle' => get_string('chart_top_products_sub', 'local_moderncommerce'),
            'formattype' => 'currency',
            'total' => pricing_service::format_price(array_sum($vals)),
            'empty' => empty($labels),
            'labels' => $labels,
            'series' => [
                self::series('net', get_string('chart_revenue', 'local_moderncommerce'), 'bar', 'left', $vals),
            ],
        ];
    }

    /**
     * Chart 6: revenue mix by product type.
     */
    private static function build_revenue_mix(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT oi.itemtype, SUM(oi.total) AS total
               FROM {local_moderncommerce_order_items} oi
               JOIN {local_moderncommerce_orders} o ON o.id = oi.orderid
              WHERE o.status IN ('paid', 'completed')
                AND o.timecreated >= :from AND o.timecreated <= :to
           GROUP BY oi.itemtype
           ORDER BY total DESC",
            ['from' => $from, 'to' => $to]
        );
        $labels = [];
        $vals = [];
        foreach ($records as $r) {
            $labels[] = self::producttype_label((string) $r->itemtype);
            $vals[] = round((float) $r->total, 2);
        }

        return [
            'id' => 'revenue_mix',
            'type' => 'donut',
            'title' => get_string('chart_revenue_mix', 'local_moderncommerce'),
            'subtitle' => get_string('chart_revenue_mix_sub', 'local_moderncommerce'),
            'formattype' => 'currency',
            'total' => pricing_service::format_price(array_sum($vals)),
            'empty' => empty($labels),
            'labels' => $labels,
            'series' => [
                self::series('revenue', get_string('chart_revenue', 'local_moderncommerce'), 'bar', 'left', $vals),
            ],
        ];
    }

    /**
     * Chart 7: gateway success (successful vs failed attempts).
     */
    private static function build_gateway_success(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT gateway, SUM(attempts) AS attempts, SUM(successful) AS successful,
                    SUM(failed) AS failed, SUM(fees) AS fees
               FROM {local_moderncommerce_report_gateways}
              WHERE reportdate >= :from AND reportdate <= :to
           GROUP BY gateway
           ORDER BY attempts DESC",
            ['from' => $from, 'to' => $to]
        );
        $labels = [];
        $ok = [];
        $fail = [];
        $totalok = 0;
        $totalatt = 0;
        foreach ($records as $r) {
            $labels[] = ucfirst((string) $r->gateway);
            $ok[] = (float) $r->successful;
            $fail[] = (float) $r->failed;
            $totalok += (int) $r->successful;
            $totalatt += (int) $r->attempts;
        }
        $rate = $totalatt > 0 ? round($totalok / $totalatt * 100) : 0;

        return [
            'id' => 'gateway_success',
            'type' => 'bar',
            'title' => get_string('chart_gateway_success', 'local_moderncommerce'),
            'subtitle' => get_string('chart_gateway_success_sub', 'local_moderncommerce'),
            'formattype' => 'number',
            'total' => $rate . '%',
            'empty' => empty($labels),
            'labels' => $labels,
            'series' => [
                self::series('successful', get_string('chart_successful', 'local_moderncommerce'), 'bar', 'left', $ok),
                self::series('failed', get_string('chart_failed', 'local_moderncommerce'), 'bar', 'left', $fail),
            ],
        ];
    }

    /**
     * Chart 2: average order value trend.
     */
    private static function build_aov_trend(\stdClass $cur, int $from, int $to, string $gran): array {
        $rows = self::daily_rows($cur, $from, $to);
        $order = [];
        $net = [];
        $paid = [];
        foreach ($rows as $r) {
            [$k, $label] = self::bucket((int) $r->reportdate, $gran);
            if (!isset($order[$k])) {
                $order[$k] = $label;
                $net[$k] = 0;
                $paid[$k] = 0;
            }
            $net[$k] += (float) $r->net;
            $paid[$k] += (int) $r->paidorders;
        }
        ksort($order);
        $labels = array_values($order);
        $aov = array_map(static fn($k) => $paid[$k] > 0 ? round($net[$k] / $paid[$k], 2) : 0.0, array_keys($order));
        $totnet = array_sum(array_map(static fn($k) => $net[$k], array_keys($order)));
        $totpaid = array_sum(array_map(static fn($k) => $paid[$k], array_keys($order)));

        return [
            'id' => 'aov_trend',
            'type' => 'line',
            'title' => get_string('chart_aov', 'local_moderncommerce'),
            'subtitle' => get_string('chart_aov_sub', 'local_moderncommerce'),
            'formattype' => 'currency',
            'total' => pricing_service::format_price($totpaid > 0 ? $totnet / $totpaid : 0),
            'empty' => $totpaid <= 0,
            'labels' => $labels,
            'series' => [
                self::series('aov', get_string('averageorder', 'local_moderncommerce'), 'line', 'left', $aov),
            ],
        ];
    }

    /**
     * Chart 8: refund & discount leakage (percent of sales).
     */
    private static function build_leakage_trend(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT id, timecreated, status, total, discount, refundedtotal
               FROM {local_moderncommerce_orders}
              WHERE status IN ('paid', 'completed', 'refunded')
                AND timecreated >= :from AND timecreated <= :to",
            ['from' => $from, 'to' => $to]
        );
        $order = [];
        $gp = [];
        $dp = [];
        $ga = [];
        $rf = [];
        foreach ($rows as $r) {
            [$k, $label] = self::bucket((int) $r->timecreated, $gran);
            if (!isset($order[$k])) {
                $order[$k] = $label;
                $gp[$k] = 0;
                $dp[$k] = 0;
                $ga[$k] = 0;
                $rf[$k] = 0;
            }
            if (in_array($r->status, ['paid', 'completed'], true)) {
                $gp[$k] += (float) $r->total;
                $dp[$k] += (float) $r->discount;
            }
            $ga[$k] += (float) $r->total;
            $rf[$k] += (float) $r->refundedtotal;
        }
        ksort($order);
        $labels = array_values($order);
        $share = array_map(static fn($k) => $gp[$k] > 0 ? round($dp[$k] / $gp[$k] * 100, 1) : 0.0, array_keys($order));
        $refrate = array_map(static fn($k) => $ga[$k] > 0 ? round($rf[$k] / $ga[$k] * 100, 1) : 0.0, array_keys($order));
        $totgp = array_sum(array_map(static fn($k) => $gp[$k], array_keys($order)));
        $totdp = array_sum(array_map(static fn($k) => $dp[$k], array_keys($order)));

        return [
            'id' => 'leakage_trend',
            'type' => 'line',
            'title' => get_string('chart_leakage', 'local_moderncommerce'),
            'subtitle' => get_string('chart_leakage_sub', 'local_moderncommerce'),
            'formattype' => 'percent',
            'total' => ($totgp > 0 ? round($totdp / $totgp * 100) : 0) . '%',
            'empty' => empty($labels),
            'labels' => $labels,
            'series' => [
                self::series('share', get_string('chart_discount_share', 'local_moderncommerce'), 'line', 'left', $share),
                self::series('refund', get_string('chart_refund_rate', 'local_moderncommerce'), 'line', 'left', $refrate),
            ],
        ];
    }

    /**
     * Chart 4: cart-to-purchase funnel.
     */
    private static function build_cart_funnel(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $carts = (int) $DB->count_records_select(
            'local_moderncommerce_carts',
            'timecreated >= ? AND timecreated <= ?',
            [$from, $to]
        );
        $orders = (int) $DB->count_records_select(
            'local_moderncommerce_orders',
            'timecreated >= ? AND timecreated <= ?',
            [$from, $to]
        );
        $paid = (int) $DB->count_records_select(
            'local_moderncommerce_orders',
            "status IN ('paid', 'completed') AND timecreated >= ? AND timecreated <= ?",
            [$from, $to]
        );

        return [
            'id' => 'cart_funnel',
            'type' => 'funnel',
            'title' => get_string('chart_cart_funnel', 'local_moderncommerce'),
            'subtitle' => get_string('chart_cart_funnel_sub', 'local_moderncommerce'),
            'formattype' => 'number',
            'total' => ($carts > 0 ? round($paid / $carts * 100) : 0) . '%',
            'empty' => $carts <= 0 && $orders <= 0,
            'labels' => [
                get_string('chart_carts', 'local_moderncommerce'),
                get_string('chart_orders', 'local_moderncommerce'),
                get_string('chart_paid', 'local_moderncommerce'),
            ],
            'series' => [
                self::series(
                    'count',
                    get_string('chart_orders', 'local_moderncommerce'),
                    'bar',
                    'left',
                    [(float) $carts, (float) $orders, (float) $paid]
                ),
            ],
            'links' => [
                '',
                (new \moodle_url('/local/moderncommerce/admin/orders.php'))->out(false),
                (new \moodle_url('/local/moderncommerce/admin/orders.php', ['status' => 'paid']))->out(false),
            ],
        ];
    }

    /**
     * Chart 9: new vs returning customers (stacked) + repeat rate.
     */
    private static function build_new_vs_returning(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        // Full paid history to determine each user's first purchase, then bucket the window.
        $rows = $DB->get_records_sql(
            "SELECT id, userid, timecreated
               FROM {local_moderncommerce_orders}
              WHERE status IN ('paid', 'completed')
           ORDER BY userid ASC, timecreated ASC, id ASC"
        );
        $firsttime = [];
        foreach ($rows as $r) {
            if (!isset($firsttime[$r->userid])) {
                $firsttime[$r->userid] = (int) $r->timecreated;
            }
        }
        $order = [];
        $new = [];
        $ret = [];
        foreach ($rows as $r) {
            $tc = (int) $r->timecreated;
            if ($tc < $from || $tc > $to) {
                continue;
            }
            [$k, $label] = self::bucket($tc, $gran);
            if (!isset($order[$k])) {
                $order[$k] = $label;
                $new[$k] = 0;
                $ret[$k] = 0;
            }
            if ($tc === $firsttime[$r->userid]) {
                $new[$k]++;
            } else {
                $ret[$k]++;
            }
        }
        ksort($order);
        $labels = array_values($order);
        $newv = array_map(static fn($k) => (float) $new[$k], array_keys($order));
        $retv = array_map(static fn($k) => (float) $ret[$k], array_keys($order));
        $rate = array_map(static function ($k) use ($new, $ret) {
            $t = $new[$k] + $ret[$k];
            return $t > 0 ? round($ret[$k] / $t * 100, 1) : 0.0;
        }, array_keys($order));
        $tnew = array_sum($newv);
        $tret = array_sum($retv);

        return [
            'id' => 'new_vs_returning',
            'type' => 'combo',
            'stacked' => true,
            'title' => get_string('chart_new_returning', 'local_moderncommerce'),
            'subtitle' => get_string('chart_new_returning_sub', 'local_moderncommerce'),
            'formattype' => 'number',
            'total' => (($tnew + $tret) > 0 ? round($tret / ($tnew + $tret) * 100) : 0) . '%',
            'empty' => ($tnew + $tret) <= 0,
            'labels' => $labels,
            'series' => [
                self::series('new', get_string('chart_new', 'local_moderncommerce'), 'bar', 'left', $newv),
                self::series('returning', get_string('chart_returning', 'local_moderncommerce'), 'bar', 'left', $retv),
                self::series('rate', get_string('chart_repeat_rate', 'local_moderncommerce'), 'line', 'right', $rate),
            ],
        ];
    }

    /**
     * Chart 10: sales heatmap (weekday x hour) of paid revenue.
     */
    private static function build_sales_heatmap(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT id, timecreated, total
               FROM {local_moderncommerce_orders}
              WHERE status IN ('paid', 'completed') AND timecreated >= :from AND timecreated <= :to",
            ['from' => $from, 'to' => $to]
        );
        $values = array_fill(0, 7, array_fill(0, 24, 0.0));
        $total = 0;
        foreach ($rows as $r) {
            $d = usergetdate((int) $r->timecreated);
            $row = (((int) $d['wday']) + 6) % 7; // Mon=0 .. Sun=6.
            $hour = (int) $d['hours'];
            $values[$row][$hour] += (float) $r->total;
            $total += (float) $r->total;
        }
        foreach ($values as $i => $rowvals) {
            foreach ($rowvals as $j => $v) {
                $values[$i][$j] = round($v, 2);
            }
        }
        $cols = [];
        for ($h = 0; $h < 24; $h++) {
            $cols[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT);
        }

        return [
            'id' => 'sales_heatmap',
            'type' => 'heatmap',
            'title' => get_string('chart_heatmap', 'local_moderncommerce'),
            'subtitle' => get_string('chart_heatmap_sub', 'local_moderncommerce'),
            'formattype' => 'currency',
            'total' => pricing_service::format_price($total),
            'empty' => $total <= 0,
            'labels' => [],
            'series' => [],
            'matrix' => [
                'rows' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'cols' => $cols,
                'values' => $values,
            ],
        ];
    }

    /**
     * Chart 11: tax collected trend.
     */
    private static function build_tax_trend(\stdClass $cur, int $from, int $to, string $gran): array {
        $rows = self::daily_rows($cur, $from, $to);
        $order = [];
        $tax = [];
        foreach ($rows as $r) {
            [$k, $label] = self::bucket((int) $r->reportdate, $gran);
            if (!isset($order[$k])) {
                $order[$k] = $label;
                $tax[$k] = 0;
            }
            $tax[$k] += (float) $r->tax;
        }
        ksort($order);
        $labels = array_values($order);
        $vals = array_map(static fn($k) => round($tax[$k], 2), array_keys($order));

        return [
            'id' => 'tax_trend',
            'type' => 'bar',
            'title' => get_string('chart_tax', 'local_moderncommerce'),
            'subtitle' => get_string('chart_tax_sub', 'local_moderncommerce'),
            'formattype' => 'currency',
            'total' => pricing_service::format_price(array_sum($vals)),
            'empty' => array_sum($vals) <= 0,
            'labels' => $labels,
            'series' => [
                self::series('tax', get_string('chart_tax', 'local_moderncommerce'), 'bar', 'left', $vals),
            ],
        ];
    }

    /**
     * Chart 12: coupon ROI (revenue vs discount per coupon).
     */
    private static function build_coupon_roi(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT c.id, c.code,
                    COUNT(cu.id) AS redemptions,
                    SUM(cu.discountamount) AS discount,
                    SUM(o.total) AS revenue
               FROM {local_moderncommerce_coupon_usage} cu
               JOIN {local_moderncommerce_coupons} c ON c.id = cu.couponid
               JOIN {local_moderncommerce_orders} o ON o.id = cu.orderid
              WHERE o.status IN ('paid', 'completed')
                AND cu.timecreated >= :from AND cu.timecreated <= :to
           GROUP BY c.id, c.code
           ORDER BY revenue DESC",
            ['from' => $from, 'to' => $to],
            0,
            8
        );
        $labels = [];
        $rev = [];
        $disc = [];
        foreach ($records as $r) {
            $labels[] = (string) $r->code;
            $rev[] = round((float) $r->revenue, 2);
            $disc[] = round((float) $r->discount, 2);
        }

        return [
            'id' => 'coupon_roi',
            'type' => 'bar',
            'title' => get_string('chart_coupon_roi', 'local_moderncommerce'),
            'subtitle' => get_string('chart_coupon_roi_sub', 'local_moderncommerce'),
            'formattype' => 'currency',
            'total' => pricing_service::format_price(array_sum($rev)),
            'empty' => empty($labels),
            'labels' => $labels,
            'series' => [
                self::series('revenue', get_string('chart_revenue', 'local_moderncommerce'), 'bar', 'left', $rev),
                self::series('discount', get_string('chart_discount', 'local_moderncommerce'), 'bar', 'left', $disc),
            ],
        ];
    }

    /**
     * Chart 13: enrolment key status distribution (current snapshot).
     */
    private static function build_key_redemption(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT status, COUNT(*) AS n
               FROM {local_moderncommerce_enrollkeys}
           GROUP BY status
           ORDER BY n DESC"
        );
        $labels = [];
        $vals = [];
        foreach ($records as $r) {
            $labels[] = localisation::status_label((string) $r->status);
            $vals[] = (float) $r->n;
        }

        return [
            'id' => 'key_redemption',
            'type' => 'donut',
            'title' => get_string('chart_key_redemption', 'local_moderncommerce'),
            'subtitle' => get_string('chart_key_redemption_sub', 'local_moderncommerce'),
            'formattype' => 'number',
            'total' => number_format(array_sum($vals)),
            'empty' => empty($labels),
            'labels' => $labels,
            'series' => [
                self::series('keys', get_string('chart_key_redemption', 'local_moderncommerce'), 'bar', 'left', $vals),
            ],
        ];
    }

    /**
     * Chart 14: time-to-payment distribution (histogram).
     */
    private static function build_time_to_payment(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT id, timecreated, timepaid
               FROM {local_moderncommerce_orders}
              WHERE status IN ('paid', 'completed') AND timepaid IS NOT NULL AND timepaid > 0
                AND timecreated >= :from AND timecreated <= :to",
            ['from' => $from, 'to' => $to]
        );
        $bins = [0, 0, 0, 0, 0, 0];
        foreach ($rows as $r) {
            $d = (int) $r->timepaid - (int) $r->timecreated;
            if ($d < 300) {
                $bins[0]++;
            } else if ($d < 1800) {
                $bins[1]++;
            } else if ($d < 3600) {
                $bins[2]++;
            } else if ($d < 21600) {
                $bins[3]++;
            } else if ($d < 86400) {
                $bins[4]++;
            } else {
                $bins[5]++;
            }
        }

        return [
            'id' => 'time_to_payment',
            'type' => 'bar',
            'title' => get_string('chart_time_to_pay', 'local_moderncommerce'),
            'subtitle' => get_string('chart_time_to_pay_sub', 'local_moderncommerce'),
            'formattype' => 'number',
            'total' => number_format(array_sum($bins)),
            'empty' => array_sum($bins) <= 0,
            'labels' => ['< 5 min', '5-30 min', '30-60 min', '1-6 h', '6-24 h', '> 24 h'],
            'series' => [
                self::series(
                    'orders',
                    get_string('chart_orders', 'local_moderncommerce'),
                    'bar',
                    'left',
                    array_map('floatval', $bins)
                ),
            ],
        ];
    }

    /**
     * Chart 15: wishlist demand (top wishlisted products).
     */
    private static function build_wishlist_demand(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $context = context_system::instance();
        $records = $DB->get_records_sql(
            "SELECT w.productid, p.name, COUNT(*) AS saves
               FROM {local_moderncommerce_wishlist} w
               JOIN {local_moderncommerce_products} p ON p.id = w.productid
              WHERE w.timecreated >= :from AND w.timecreated <= :to
           GROUP BY w.productid, p.name
           ORDER BY saves DESC",
            ['from' => $from, 'to' => $to],
            0,
            10
        );
        $labels = [];
        $vals = [];
        foreach ($records as $r) {
            $labels[] = format_string($r->name, true, ['context' => $context]);
            $vals[] = (float) $r->saves;
        }

        return [
            'id' => 'wishlist_demand',
            'type' => 'hbar',
            'title' => get_string('chart_wishlist', 'local_moderncommerce'),
            'subtitle' => get_string('chart_wishlist_sub', 'local_moderncommerce'),
            'formattype' => 'number',
            'total' => number_format(array_sum($vals)),
            'empty' => empty($labels),
            'labels' => $labels,
            'series' => [
                self::series('saves', get_string('chart_saves', 'local_moderncommerce'), 'bar', 'left', $vals),
            ],
        ];
    }

    /**
     * Chart 16: revenue by billing country.
     */
    private static function build_geo_revenue(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT a.country, SUM(o.total) AS revenue
               FROM {local_moderncommerce_order_addresses} a
               JOIN {local_moderncommerce_orders} o ON o.id = a.orderid
              WHERE a.addresstype = 'billing'
                AND o.status IN ('paid', 'completed')
                AND o.timecreated >= :from AND o.timecreated <= :to
                AND a.country IS NOT NULL AND a.country <> ''
           GROUP BY a.country
           ORDER BY revenue DESC",
            ['from' => $from, 'to' => $to],
            0,
            10
        );
        $labels = [];
        $vals = [];
        foreach ($records as $r) {
            $labels[] = self::country_label((string) $r->country);
            $vals[] = round((float) $r->revenue, 2);
        }

        return [
            'id' => 'geo_revenue',
            'type' => 'hbar',
            'title' => get_string('chart_geo', 'local_moderncommerce'),
            'subtitle' => get_string('chart_geo_sub', 'local_moderncommerce'),
            'formattype' => 'currency',
            'total' => pricing_service::format_price(array_sum($vals)),
            'empty' => empty($labels),
            'labels' => $labels,
            'series' => [
                self::series('revenue', get_string('chart_revenue', 'local_moderncommerce'), 'bar', 'left', $vals),
            ],
        ];
    }

    /**
     * Localised country name from an ISO-2 code.
     *
     * @param string $code ISO-2 country code.
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
     * Table widget: recent orders.
     */
    private static function build_recent_orders(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $records = $DB->get_records('local_moderncommerce_orders', null, 'timecreated DESC, id DESC', '*', 0, 8);
        $rows = [];
        if ($records) {
            $userids = array_values(array_unique(array_map(static fn($r) => (int) $r->userid, $records)));
            $users = $DB->get_records_list('user', 'id', $userids);
            foreach ($records as $rec) {
                $user = $users[$rec->userid] ?? null;
                $status = (string) $rec->status;
                $rows[] = ['cells' => [
                    ['value' => (string) $rec->ordernumber, 'href' => (new \moodle_url(
                        '/local/moderncommerce/admin/order_view.php',
                        ['id' => $rec->id]
                    ))->out(false)],
                    ['value' => $user ? fullname($user) : get_string('unknownuser', 'local_moderncommerce')],
                    ['value' => pricing_service::format_order_price($rec->total, $rec)],
                    ['value' => self::status_label($status), 'badge' => true, 'badgeclass' => self::status_class($status)],
                    ['value' => userdate((int) $rec->timecreated, get_string('strftimedatetimeshort', 'langconfig'))],
                ]];
            }
        }

        return [
            'id' => 'recent_orders',
            'type' => 'table',
            'title' => get_string('chart_recent_orders', 'local_moderncommerce'),
            'subtitle' => get_string('chart_recent_orders_sub', 'local_moderncommerce'),
            'formattype' => 'number',
            'total' => '',
            'empty' => empty($rows),
            'labels' => [],
            'series' => [],
            'table' => [
                'columns' => [
                    ['label' => get_string('order', 'local_moderncommerce'), 'align' => 'left'],
                    ['label' => get_string('customer', 'local_moderncommerce'), 'align' => 'left'],
                    ['label' => get_string('total', 'local_moderncommerce'), 'align' => 'right'],
                    ['label' => get_string('status'), 'align' => 'left'],
                    ['label' => get_string('date', 'local_moderncommerce'), 'align' => 'right'],
                ],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * Table widget: top products by revenue (rank/units/revenue).
     */
    private static function build_top_products_table(\stdClass $cur, int $from, int $to, string $gran): array {
        global $DB;
        $context = context_system::instance();
        $records = $DB->get_records_sql(
            "SELECT p.id, p.name,
                    COALESCE(SUM(oi.quantity), 0) AS sold,
                    COALESCE(SUM(oi.total), 0) AS revenue
               FROM {local_moderncommerce_order_items} oi
               JOIN {local_moderncommerce_orders} o ON o.id = oi.orderid
               JOIN {local_moderncommerce_products} p ON p.id = oi.productid
              WHERE o.status IN ('paid', 'completed') AND o.timecreated >= :from AND o.timecreated <= :to
           GROUP BY p.id, p.name
           ORDER BY revenue DESC",
            ['from' => $from, 'to' => $to],
            0,
            8
        );
        $rows = [];
        $rank = 1;
        foreach ($records as $rec) {
            $rows[] = ['cells' => [
                ['value' => (string) $rank++],
                ['value' => format_string($rec->name, true, ['context' => $context])],
                ['value' => number_format((int) round((float) $rec->sold))],
                ['value' => pricing_service::format_price((float) $rec->revenue)],
            ]];
        }

        return [
            'id' => 'top_products_table',
            'type' => 'table',
            'title' => get_string('chart_top_products_table', 'local_moderncommerce'),
            'subtitle' => get_string('chart_top_products_table_sub', 'local_moderncommerce'),
            'formattype' => 'number',
            'total' => '',
            'empty' => empty($rows),
            'labels' => [],
            'series' => [],
            'table' => [
                'columns' => [
                    ['label' => '#', 'align' => 'left'],
                    ['label' => get_string('product', 'local_moderncommerce'), 'align' => 'left'],
                    ['label' => get_string('sold', 'local_moderncommerce'), 'align' => 'right'],
                    ['label' => get_string('revenue', 'local_moderncommerce'), 'align' => 'right'],
                ],
                'rows' => $rows,
            ],
        ];
    }

    /**
     * Localised order status label.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_label(string $status): string {
        return localisation::status_label($status, ['orderstatus']);
    }

    /**
     * Status badge variant.
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

    /**
     * Build a series row.
     *
     * @param string $key Key.
     * @param string $label Label.
     * @param string $charttype line|bar.
     * @param string $axis left|right.
     * @param array $data Float values.
     * @return array
     */
    private static function series(string $key, string $label, string $charttype, string $axis, array $data): array {
        return [
            'key' => $key,
            'label' => $label,
            'charttype' => $charttype,
            'axis' => $axis,
            'data' => array_map(static fn($v) => (float) $v, array_values($data)),
        ];
    }

    /**
     * Localised product-type label.
     *
     * @param string $type Product type.
     * @return string
     */
    private static function producttype_label(string $type): string {
        switch ($type) {
            case 'course':
                return get_string('course');
            case 'bundle':
                return get_string('bundle', 'local_moderncommerce');
            case 'program':
                return get_string('program', 'local_moderncommerce');
            case 'subscription':
                return get_string('subscription', 'local_moderncommerce');
            case 'digital':
                return get_string('digitalproduct', 'local_moderncommerce');
            default:
                return ucfirst($type);
        }
    }
}
