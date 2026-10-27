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
 * External API for learner order history.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\api\order_api;
use local_moderncommerce\localisation;
use local_moderncommerce\services\learner_invoice_service;
use local_moderncommerce\services\pricing_service;
use moodle_url;
use xmldb_table;

/**
 * Returns the logged-in learner's orders.
 */
class list_orders extends external_api {
    /** @var array Allowed order statuses for filter. */
    private const ALLOWED_STATUSES = ['', 'paid', 'completed', 'pending', 'processing', 'failed', 'cancelled', 'refunded'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Order status filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Orders per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $status Order status filter.
     * @param int $page Zero-based page number.
     * @param int $perpage Orders per page.
     * @return array
     */
    public static function execute(string $status = '', int $page = 0, int $perpage = 10): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'status' => $status,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        $params = self::normalise_params($params);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewownorders', $context);

        if (!self::table_exists('local_moderncommerce_orders')) {
            return self::empty_response($params, (int)$USER->id);
        }

        $where = ['userid = :userid'];
        $sqlparams = ['userid' => (int)$USER->id];
        if ($params['status'] !== '') {
            $where[] = 'status = :status';
            $sqlparams['status'] = $params['status'];
        }

        $wheresql = implode(' AND ', $where);
        $total = $DB->count_records_select('local_moderncommerce_orders', $wheresql, $sqlparams);
        $orders = $DB->get_records_select(
            'local_moderncommerce_orders',
            $wheresql,
            $sqlparams,
            'timecreated DESC',
            '*',
            $params['page'] * $params['perpage'],
            $params['perpage']
        );

        $rows = [];
        foreach ($orders as $order) {
            $rows[] = self::normalise_order($order);
        }
        $manualinvoices = learner_invoice_service::list_for_user((int)$USER->id, 0, 25);
        $invoicestats = learner_invoice_service::stats_for_user((int)$USER->id);

        return [
            'success' => true,
            'message' => '',
            'orders' => $rows,
            'manualinvoices' => $manualinvoices['items'],
            'manualinvoicestotal' => $manualinvoices['total'],
            'invoicestats' => $invoicestats,
            'stats' => self::stats((int)$USER->id),
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'totalpages' => max(1, (int)ceil($total / $params['perpage'])),
            'hasprevious' => $params['page'] > 0,
            'hasnext' => (($params['page'] + 1) * $params['perpage']) < $total,
            'filters' => [
                'status' => $params['status'],
            ],
            'urls' => [
                'catalog' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
                'orders' => self::learner_app_url('orders'),
            ],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether orders loaded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'orders' => new external_multiple_structure(self::order_structure()),
            'manualinvoices' => new external_multiple_structure(self::invoice_structure()),
            'manualinvoicestotal' => new external_value(PARAM_INT, 'Total visible manual invoices.'),
            'invoicestats' => self::invoice_stats_structure(),
            'stats' => self::stats_structure(),
            'total' => new external_value(PARAM_INT, 'Total filtered orders.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Orders per page.'),
            'totalpages' => new external_value(PARAM_INT, 'Total pages.'),
            'hasprevious' => new external_value(PARAM_BOOL, 'Whether previous page exists.'),
            'hasnext' => new external_value(PARAM_BOOL, 'Whether next page exists.'),
            'filters' => new external_single_structure([
                'status' => new external_value(PARAM_ALPHANUMEXT, 'Status filter.'),
            ]),
            'urls' => new external_single_structure([
                'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
                'orders' => new external_value(PARAM_RAW, 'Orders URL.'),
            ]),
        ]);
    }

    /**
     * Normalise request params.
     *
     * @param array $params Raw params.
     * @return array Normalised params.
     */
    private static function normalise_params(array $params): array {
        $params['status'] = strtolower(trim((string)$params['status']));
        if (!in_array($params['status'], self::ALLOWED_STATUSES, true)) {
            $params['status'] = '';
        }
        $params['page'] = max(0, (int)$params['page']);
        $params['perpage'] = min(50, max(1, (int)$params['perpage']));

        return $params;
    }

    /**
     * Build an empty response.
     *
     * @param array $params Normalised params.
     * @param int $userid User ID.
     * @return array
     */
    private static function empty_response(array $params, int $userid): array {
        $manualinvoices = learner_invoice_service::list_for_user($userid, 0, 25);
        $invoicestats = learner_invoice_service::stats_for_user($userid);

        return [
            'success' => true,
            'message' => '',
            'orders' => [],
            'manualinvoices' => $manualinvoices['items'],
            'manualinvoicestotal' => $manualinvoices['total'],
            'invoicestats' => $invoicestats,
            'stats' => [
                'total' => 0,
                'paid' => 0,
                'pending' => 0,
                'cancelled' => 0,
                'totalspent' => '',
            ],
            'total' => 0,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'totalpages' => 1,
            'hasprevious' => false,
            'hasnext' => false,
            'filters' => [
                'status' => $params['status'],
            ],
            'urls' => [
                'catalog' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
                'orders' => self::learner_app_url('orders'),
            ],
        ];
    }

    /**
     * Normalise one order.
     *
     * @param \stdClass $order Order record.
     * @return array
     */
    private static function normalise_order(\stdClass $order): array {
        $items = self::order_item_summary((int)$order->id);

        return [
            'id' => (int)$order->id,
            'ordernumber' => (string)$order->ordernumber,
            'date' => userdate((int)$order->timecreated, get_string('strftimedateshort')),
            'datetime' => userdate((int)$order->timecreated, get_string('strftimedatetime')),
            'relativedate' => format_time(time() - (int)$order->timecreated),
            'itemcount' => $items['itemcount'],
            'itemstext' => $items['itemcount'] === 1
                ? get_string('item', 'local_moderncommerce')
                : get_string('items', 'local_moderncommerce'),
            'firstitemname' => $items['firstitemname'],
            'total' => pricing_service::format_order_price((float)$order->total, $order),
            'status' => (string)$order->status,
            'statuslabel' => self::status_label((string)$order->status),
            'statusclass' => self::status_class((string)$order->status),
            'ispaid' => in_array((string)$order->status, ['paid', 'completed'], true),
            'ispending' => (string)$order->status === 'pending',
            'viewurl' => self::learner_app_url('orders/' . (int)$order->id),
            'continueurl' => self::learner_app_url('checkout?orderid=' . (int)$order->id),
            'invoiceurl' => (new moodle_url('/local/moderncommerce/download_invoice.php', [
                'orderid' => $order->id,
                'type' => 'invoice',
            ]))->out(false),
        ];
    }

    /**
     * Build a learner app hash route URL.
     *
     * @param string $route Route without leading hash.
     * @return string
     */
    private static function learner_app_url(string $route): string {
        return (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/' . ltrim($route, '/');
    }

    /**
     * Build order statistics.
     *
     * @param int $userid User ID.
     * @return array
     */
    private static function stats(int $userid): array {
        global $DB;

        $stats = [
            'total' => 0,
            'paid' => 0,
            'pending' => 0,
            'cancelled' => 0,
            'totalspent' => '',
        ];
        $orders = $DB->get_records('local_moderncommerce_orders', ['userid' => $userid]);
        $totalspent = 0.0;
        $lastpaidorder = null;

        foreach ($orders as $order) {
            $stats['total']++;
            if (in_array((string)$order->status, ['paid', 'completed'], true)) {
                $stats['paid']++;
                $totalspent += (float)$order->total;
                $lastpaidorder = $order;
            } else if ((string)$order->status === 'pending') {
                $stats['pending']++;
            } else if (in_array((string)$order->status, ['cancelled', 'failed'], true)) {
                $stats['cancelled']++;
            }
        }

        $stats['totalspent'] = $lastpaidorder
            ? pricing_service::format_order_price($totalspent, $lastpaidorder)
            : pricing_service::format_price(0);

        return $stats;
    }

    /**
     * Summarise order items.
     *
     * @param int $orderid Order ID.
     * @return array
     */
    private static function order_item_summary(int $orderid): array {
        $items = order_api::get_order_items($orderid);
        $firstitem = $items ? reset($items) : null;

        return [
            'itemcount' => count($items),
            'firstitemname' => $firstitem ? format_string((string)$firstitem->coursename) : '',
        ];
    }

    /**
     * Status label.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_label(string $status): string {
        return localisation::status_label($status, ['orderstatus']);
    }

    /**
     * Status class.
     *
     * @param string $status Status.
     * @return string
     */
    public static function status_class(string $status): string {
        if (in_array($status, ['paid', 'completed'], true)) {
            return 'success';
        }
        if (in_array($status, ['pending', 'processing'], true)) {
            return 'warning';
        }
        if (in_array($status, ['failed', 'cancelled'], true)) {
            return 'danger';
        }
        if ($status === 'refunded') {
            return 'info';
        }

        return 'neutral';
    }

    /**
     * Check whether table exists.
     *
     * @param string $table Table name without prefix.
     * @return bool
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }

    /**
     * Order structure.
     *
     * @return external_single_structure
     */
    private static function order_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Order ID.'),
            'ordernumber' => new external_value(PARAM_TEXT, 'Order number.'),
            'date' => new external_value(PARAM_TEXT, 'Short date.'),
            'datetime' => new external_value(PARAM_TEXT, 'Full date.'),
            'relativedate' => new external_value(PARAM_TEXT, 'Relative date.'),
            'itemcount' => new external_value(PARAM_INT, 'Item count.'),
            'itemstext' => new external_value(PARAM_TEXT, 'Item count label.'),
            'firstitemname' => new external_value(PARAM_TEXT, 'First item name.'),
            'total' => new external_value(PARAM_TEXT, 'Formatted total.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status key.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Status class.'),
            'ispaid' => new external_value(PARAM_BOOL, 'Whether paid.'),
            'ispending' => new external_value(PARAM_BOOL, 'Whether pending.'),
            'viewurl' => new external_value(PARAM_RAW, 'View URL.'),
            'continueurl' => new external_value(PARAM_RAW, 'Continue payment URL.'),
            'invoiceurl' => new external_value(PARAM_RAW, 'Invoice URL.'),
        ]);
    }

    /**
     * Manual invoice structure.
     *
     * @return external_single_structure
     */
    private static function invoice_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Invoice ID.'),
            'invoicenumber' => new external_value(PARAM_TEXT, 'Invoice number.'),
            'date' => new external_value(PARAM_TEXT, 'Short invoice date.'),
            'datetime' => new external_value(PARAM_TEXT, 'Full invoice date/time.'),
            'duedate' => new external_value(PARAM_TEXT, 'Due date.'),
            'total' => new external_value(PARAM_TEXT, 'Formatted invoice total.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Invoice status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Invoice status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Invoice status class.'),
            'downloadurl' => new external_value(PARAM_RAW, 'Invoice download URL.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total orders.'),
            'paid' => new external_value(PARAM_INT, 'Paid orders.'),
            'pending' => new external_value(PARAM_INT, 'Pending orders.'),
            'cancelled' => new external_value(PARAM_INT, 'Cancelled or failed orders.'),
            'totalspent' => new external_value(PARAM_TEXT, 'Formatted paid total.'),
        ]);
    }

    /**
     * Manual invoice stats structure.
     *
     * @return external_single_structure
     */
    private static function invoice_stats_structure(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total visible manual invoices.'),
            'paid' => new external_value(PARAM_INT, 'Paid manual invoices.'),
            'outstanding' => new external_value(PARAM_INT, 'Outstanding manual invoices.'),
            'displayoutstanding' => new external_value(PARAM_TEXT, 'Formatted outstanding amount.'),
        ]);
    }
}
