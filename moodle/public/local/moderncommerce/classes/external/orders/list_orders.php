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
 * External API for the admin orders list.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\orders;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\localisation;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * List orders for the admin orders screen.
 */
class list_orders extends external_api {
    /** @var string[] Selectable order statuses. */
    private const STATUSES = ['pending', 'processing', 'paid', 'completed', 'failed', 'cancelled', 'refunded'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search order number or customer email.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Order status filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort key.', VALUE_DEFAULT, 'timecreated'),
            'direction' => new external_value(PARAM_ALPHA, 'Sort direction.', VALUE_DEFAULT, 'DESC'),
        ]);
    }

    /**
     * Execute the order listing.
     *
     * @param string $search Search term.
     * @param string $status Status filter.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @param string $sort Sort key.
     * @param string $direction Sort direction.
     * @return array
     */
    public static function execute(
        string $search = '',
        string $status = '',
        int $page = 0,
        int $perpage = 10,
        string $sort = 'timecreated',
        string $direction = 'DESC'
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'perpage' => $perpage,
            'sort' => $sort,
            'direction' => $direction,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewallorders', $context);

        $params = self::normalise_parameters($params);
        [$wheresql, $sqlparams] = self::build_filter_sql($params);
        [$sortkey, $sortsql, $sortdirection] = self::get_sort_sql($params['sort'], $params['direction']);

        $countsql = "SELECT COUNT(o.id)
                       FROM {local_moderncommerce_orders} o
                      {$wheresql}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $selectsql = "SELECT o.*,
                             (SELECT COALESCE(SUM(oi.quantity), 0)
                                FROM {local_moderncommerce_order_items} oi
                               WHERE oi.orderid = o.id) AS itemcount
                        FROM {local_moderncommerce_orders} o
                       {$wheresql}
                    ORDER BY {$sortsql} {$sortdirection}, o.id DESC";

        $records = $DB->get_records_sql(
            $selectsql,
            $sqlparams,
            $params['page'] * $params['perpage'],
            $params['perpage']
        );

        $users = self::get_users($records);
        $paymentmethods = self::get_payment_methods(array_keys($records));
        $canmanage = has_capability('local/moderncommerce:manageorders', $context);

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_order($record, $users, $paymentmethods, $canmanage);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'sort' => $sortkey,
            'direction' => $sortdirection,
            'canmanage' => $canmanage,
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
            'items' => new external_multiple_structure(self::order_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching orders.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Applied sort key.'),
            'direction' => new external_value(PARAM_ALPHA, 'Applied sort direction.'),
            'canmanage' => new external_value(PARAM_BOOL, 'Whether the user can change order status.'),
            'stats' => self::stats_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Order row return structure.
     *
     * @return external_single_structure
     */
    private static function order_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Order ID.'),
            'ordernumber' => new external_value(PARAM_TEXT, 'Human readable order number.'),
            'ordertype' => new external_value(PARAM_ALPHANUMEXT, 'Order type.'),
            'status' => new external_value(PARAM_ALPHA, 'Order status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Localised status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Badge class for the status.'),
            'customerid' => new external_value(PARAM_INT, 'Customer user ID.'),
            'customername' => new external_value(PARAM_TEXT, 'Customer display name.'),
            'customeremail' => new external_value(PARAM_TEXT, 'Customer email.'),
            'customerurl' => new external_value(PARAM_URL, 'Modern Commerce customer detail URL.'),
            'itemcount' => new external_value(PARAM_INT, 'Number of purchased units.'),
            'rawtotal' => new external_value(PARAM_FLOAT, 'Raw order total.'),
            'displaytotal' => new external_value(PARAM_TEXT, 'Formatted order total.'),
            'paymentmethod' => new external_value(PARAM_TEXT, 'Latest payment method label.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'displaydate' => new external_value(PARAM_TEXT, 'Formatted creation date.'),
            'viewurl' => new external_value(PARAM_URL, 'Admin order detail URL.'),
        ]);
    }

    /**
     * Stats return structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'totalorders' => new external_value(PARAM_INT, 'Total orders.'),
            'paidorders' => new external_value(PARAM_INT, 'Paid orders.'),
            'pendingorders' => new external_value(PARAM_INT, 'Pending orders.'),
            'refundedorders' => new external_value(PARAM_INT, 'Refunded orders.'),
            'displayrevenue' => new external_value(PARAM_TEXT, 'Formatted paid revenue.'),
        ]);
    }

    /**
     * Normalise and whitelist request parameters.
     *
     * @param array $params Validated parameters.
     * @return array
     */
    private static function normalise_parameters(array $params): array {
        $params['search'] = trim((string) $params['search']);
        $params['page'] = max(0, (int) $params['page']);
        $params['perpage'] = min(100, max(5, (int) $params['perpage']));

        if (!in_array($params['status'], self::STATUSES, true)) {
            $params['status'] = '';
        }

        return $params;
    }

    /**
     * Build filter SQL.
     *
     * @param array $params Normalised parameters.
     * @return array [where, params]
     */
    private static function build_filter_sql(array $params): array {
        global $DB;

        $where = ['1 = 1'];
        $sqlparams = [];

        if ($params['status'] !== '') {
            $where[] = 'o.status = :status';
            $sqlparams['status'] = $params['status'];
        }

        if ($params['search'] !== '') {
            $where[] = '(' .
                $DB->sql_like('o.ordernumber', ':searchnumber', false) . ' OR ' .
                $DB->sql_like('o.customeremail', ':searchemail', false) .
            ')';
            $search = '%' . $DB->sql_like_escape($params['search']) . '%';
            $sqlparams['searchnumber'] = $search;
            $sqlparams['searchemail'] = $search;
        }

        return ['WHERE ' . implode(' AND ', $where), $sqlparams];
    }

    /**
     * Convert sort input into safe SQL.
     *
     * @param string $sort Sort key.
     * @param string $direction Direction.
     * @return array [key, field, direction]
     */
    private static function get_sort_sql(string $sort, string $direction): array {
        $sortmap = [
            'ordernumber' => 'o.ordernumber',
            'total' => 'o.total',
            'status' => 'o.status',
            'timecreated' => 'o.timecreated',
        ];

        $sortkey = array_key_exists($sort, $sortmap) ? $sort : 'timecreated';
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return [$sortkey, $sortmap[$sortkey], $direction];
    }

    /**
     * Fetch user records for the order page.
     *
     * @param array $records Order records.
     * @return array User records keyed by ID.
     */
    private static function get_users(array $records): array {
        global $DB;

        $userids = array_values(array_unique(array_map(static function ($record): int {
            return (int) $record->userid;
        }, $records)));

        if (empty($userids)) {
            return [];
        }

        return $DB->get_records_list('user', 'id', $userids);
    }

    /**
     * Fetch the latest payment method label per order.
     *
     * @param array $orderids Order IDs.
     * @return array Gateway labels keyed by order ID.
     */
    private static function get_payment_methods(array $orderids): array {
        global $DB;

        if (empty($orderids) || !$DB->get_manager()->table_exists('local_moderncommerce_payment_attempts')) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($orderids, SQL_PARAMS_NAMED, 'pa');
        $sql = "SELECT id, orderid, gateway
                  FROM {local_moderncommerce_payment_attempts}
                 WHERE orderid {$insql}
              ORDER BY orderid ASC, timecreated DESC, id DESC";

        $methods = [];
        foreach ($DB->get_records_sql($sql, $inparams) as $attempt) {
            $orderid = (int) $attempt->orderid;
            if (!isset($methods[$orderid])) {
                $methods[$orderid] = ucfirst((string) $attempt->gateway);
            }
        }

        return $methods;
    }

    /**
     * Format one order record for the response.
     *
     * @param \stdClass $record Order record.
     * @param array $users User records keyed by ID.
     * @param array $paymentmethods Gateway labels keyed by order ID.
     * @param bool $canmanage Whether the user can manage orders.
     * @return array
     */
    private static function format_order(\stdClass $record, array $users, array $paymentmethods, bool $canmanage): array {
        $orderid = (int) $record->id;
        $user = $users[$record->userid] ?? null;

        return [
            'id' => $orderid,
            'ordernumber' => (string) $record->ordernumber,
            'ordertype' => (string) $record->ordertype,
            'status' => (string) $record->status,
            'statuslabel' => self::status_label((string) $record->status),
            'statusclass' => self::status_class((string) $record->status),
            'customerid' => $user ? (int) $user->id : 0,
            'customername' => $user ? fullname($user) : get_string('unknownuser', 'local_moderncommerce'),
            'customeremail' => $user ? $user->email : (string) ($record->customeremail ?? ''),
            'customerurl' => $user
                ? (new moodle_url('/local/moderncommerce/admin/customer.php', ['id' => $user->id]))->out(false)
                : (new moodle_url('/local/moderncommerce/admin/orders.php'))->out(false),
            'itemcount' => (int) round((float) $record->itemcount),
            'rawtotal' => (float) $record->total,
            'displaytotal' => pricing_service::format_order_price($record->total, $record),
            'paymentmethod' => $paymentmethods[$orderid] ?? '-',
            'timecreated' => (int) $record->timecreated,
            'displaydate' => userdate((int) $record->timecreated, get_string('strftimedatetime', 'langconfig')),
            'viewurl' => (new moodle_url('/local/moderncommerce/admin/order_view.php', ['id' => $orderid]))->out(false),
        ];
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
     * Badge class for an order status.
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
     * Get order summary statistics.
     *
     * @return array
     */
    private static function get_stats(): array {
        global $DB;

        $revenue = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(total), 0)
               FROM {local_moderncommerce_orders}
              WHERE status IN ('paid', 'completed')"
        );

        return [
            'totalorders' => (int) $DB->count_records('local_moderncommerce_orders'),
            'paidorders' => (int) $DB->count_records_select(
                'local_moderncommerce_orders',
                "status IN ('paid', 'completed')"
            ),
            'pendingorders' => (int) $DB->count_records_select(
                'local_moderncommerce_orders',
                "status IN ('pending', 'processing')"
            ),
            'refundedorders' => (int) $DB->count_records('local_moderncommerce_orders', ['status' => 'refunded']),
            'displayrevenue' => pricing_service::format_price($revenue),
        ];
    }
}
