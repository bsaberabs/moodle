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
 * External API for the admin customers list.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\customers;

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
 * List commerce customers for the admin customers screen.
 */
class list_customers extends external_api {
    /** @var string[] Supported account status filters. */
    private const ACCOUNT_STATUSES = ['active', 'suspended'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search customer name or email.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Moodle account status filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort key.', VALUE_DEFAULT, 'lastorder'),
            'direction' => new external_value(PARAM_ALPHA, 'Sort direction.', VALUE_DEFAULT, 'DESC'),
        ]);
    }

    /**
     * Execute the customer listing.
     *
     * @param string $search Search term.
     * @param string $status Account status filter.
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
        string $sort = 'lastorder',
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
        [$sortkey, $orderbysql, $sortdirection] = self::get_order_by_sql($params['sort'], $params['direction']);

        $countsql = "SELECT COUNT(DISTINCT u.id)
                       FROM {user} u
                       JOIN {local_moderncommerce_orders} o ON o.userid = u.id
                      {$wheresql}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $selectsql = "SELECT u.id,
                             u.firstname,
                             u.lastname,
                             u.firstnamephonetic,
                             u.lastnamephonetic,
                             u.middlename,
                             u.alternatename,
                             u.email,
                             u.suspended,
                             COUNT(o.id) AS ordercount,
                             SUM(CASE WHEN o.status IN ('paid', 'completed') THEN 1 ELSE 0 END) AS paidorders,
                             SUM(CASE WHEN o.status IN ('pending', 'processing') THEN 1 ELSE 0 END) AS pendingorders,
                             SUM(CASE WHEN o.status = 'refunded' THEN 1 ELSE 0 END) AS refundedorders,
                             SUM(CASE WHEN o.status IN ('paid', 'completed') THEN o.total ELSE 0 END) AS totalspent,
                             SUM(COALESCE(o.refundedtotal, 0)) AS refundedtotal,
                             MIN(o.timecreated) AS firstorder,
                             MAX(o.timecreated) AS lastorder
                        FROM {user} u
                        JOIN {local_moderncommerce_orders} o ON o.userid = u.id
                       {$wheresql}
                    GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                             u.middlename, u.alternatename, u.email, u.suspended
                    ORDER BY {$orderbysql}, u.id DESC";

        $records = $DB->get_records_sql(
            $selectsql,
            $sqlparams,
            $params['page'] * $params['perpage'],
            $params['perpage']
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_customer($record);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'sort' => $sortkey,
            'direction' => $sortdirection,
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
            'items' => new external_multiple_structure(self::customer_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching customers.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Applied sort key.'),
            'direction' => new external_value(PARAM_ALPHA, 'Applied sort direction.'),
            'stats' => self::stats_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Customer row return structure.
     *
     * @return external_single_structure
     */
    private static function customer_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Customer user ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Customer full name.'),
            'email' => new external_value(PARAM_TEXT, 'Customer email.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Account status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Account status badge class.'),
            'ordercount' => new external_value(PARAM_INT, 'Total order count.'),
            'paidorders' => new external_value(PARAM_INT, 'Paid/completed order count.'),
            'pendingorders' => new external_value(PARAM_INT, 'Pending/processing order count.'),
            'refundedorders' => new external_value(PARAM_INT, 'Refunded order count.'),
            'totalspent' => new external_value(PARAM_FLOAT, 'Raw paid/completed spend.'),
            'displaytotalspent' => new external_value(PARAM_TEXT, 'Formatted paid/completed spend.'),
            'refundedtotal' => new external_value(PARAM_FLOAT, 'Raw refunded total.'),
            'displayrefundedtotal' => new external_value(PARAM_TEXT, 'Formatted refunded total.'),
            'firstorder' => new external_value(PARAM_TEXT, 'Formatted first order date.'),
            'lastorder' => new external_value(PARAM_TEXT, 'Formatted last order date.'),
            'customerurl' => new external_value(PARAM_URL, 'Modern Commerce customer detail URL.'),
        ]);
    }

    /**
     * Stats return structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'totalcustomers' => new external_value(PARAM_INT, 'Total commerce customers.'),
            'totalorders' => new external_value(PARAM_INT, 'Total commerce orders.'),
            'paidorders' => new external_value(PARAM_INT, 'Paid/completed order count.'),
            'pendingorders' => new external_value(PARAM_INT, 'Pending/processing order count.'),
            'refundedorders' => new external_value(PARAM_INT, 'Refunded order count.'),
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

        if (!in_array($params['status'], self::ACCOUNT_STATUSES, true)) {
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

        $where = ['u.deleted = 0'];
        $sqlparams = [];

        if ($params['status'] === 'active') {
            $where[] = 'u.suspended = :suspendedactive';
            $sqlparams['suspendedactive'] = 0;
        } else if ($params['status'] === 'suspended') {
            $where[] = 'u.suspended = :suspendedinactive';
            $sqlparams['suspendedinactive'] = 1;
        }

        if ($params['search'] !== '') {
            $search = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' .
                $DB->sql_like('u.firstname', ':searchfirst', false) . ' OR ' .
                $DB->sql_like('u.lastname', ':searchlast', false) . ' OR ' .
                $DB->sql_like('u.email', ':searchemail', false) . ' OR ' .
                $DB->sql_like('o.customeremail', ':searchorderemail', false) .
            ')';
            $sqlparams['searchfirst'] = $search;
            $sqlparams['searchlast'] = $search;
            $sqlparams['searchemail'] = $search;
            $sqlparams['searchorderemail'] = $search;
        }

        return ['WHERE ' . implode(' AND ', $where), $sqlparams];
    }

    /**
     * Convert sort input into safe SQL.
     *
     * @param string $sort Sort key.
     * @param string $direction Direction.
     * @return array [key, order by SQL, direction]
     */
    private static function get_order_by_sql(string $sort, string $direction): array {
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $sortkey = in_array($sort, [
            'customer',
            'email',
            'ordercount',
            'totalspent',
            'refundedorders',
            'lastorder',
        ], true) ? $sort : 'lastorder';

        $sortmap = [
            'customer' => "u.lastname {$direction}, u.firstname {$direction}",
            'email' => "u.email {$direction}",
            'ordercount' => "COUNT(o.id) {$direction}",
            'totalspent' => "SUM(CASE WHEN o.status IN ('paid', 'completed') THEN o.total ELSE 0 END) {$direction}",
            'refundedorders' => "SUM(CASE WHEN o.status = 'refunded' THEN 1 ELSE 0 END) {$direction}",
            'lastorder' => "MAX(o.timecreated) {$direction}",
        ];

        return [$sortkey, $sortmap[$sortkey], $direction];
    }

    /**
     * Format one customer row for the response.
     *
     * @param \stdClass $record Customer aggregate record.
     * @return array
     */
    private static function format_customer(\stdClass $record): array {
        $customerid = (int) $record->id;
        $suspended = !empty($record->suspended);
        $totalspent = (float) ($record->totalspent ?? 0);
        $refundedtotal = (float) ($record->refundedtotal ?? 0);

        return [
            'id' => $customerid,
            'fullname' => fullname($record),
            'email' => (string) $record->email,
            'statuslabel' => $suspended ? get_string('suspended', 'local_moderncommerce') : get_string('active'),
            'statusclass' => $suspended ? 'danger' : 'success',
            'ordercount' => (int) ($record->ordercount ?? 0),
            'paidorders' => (int) ($record->paidorders ?? 0),
            'pendingorders' => (int) ($record->pendingorders ?? 0),
            'refundedorders' => (int) ($record->refundedorders ?? 0),
            'totalspent' => $totalspent,
            'displaytotalspent' => pricing_service::format_price($totalspent),
            'refundedtotal' => $refundedtotal,
            'displayrefundedtotal' => pricing_service::format_price($refundedtotal),
            'firstorder' => self::format_time((int) ($record->firstorder ?? 0), false),
            'lastorder' => self::format_time((int) ($record->lastorder ?? 0), false),
            'customerurl' => (new moodle_url('/local/moderncommerce/admin/customer.php', ['id' => $customerid]))->out(false),
        ];
    }

    /**
     * Get summary statistics.
     *
     * @return array
     */
    private static function get_stats(): array {
        global $DB;

        $sql = "SELECT COUNT(o.id) AS totalorders,
                       COUNT(DISTINCT o.userid) AS totalcustomers,
                       SUM(CASE WHEN o.status IN ('paid', 'completed') THEN 1 ELSE 0 END) AS paidorders,
                       SUM(CASE WHEN o.status IN ('pending', 'processing') THEN 1 ELSE 0 END) AS pendingorders,
                       SUM(CASE WHEN o.status = 'refunded' THEN 1 ELSE 0 END) AS refundedorders,
                       SUM(CASE WHEN o.status IN ('paid', 'completed') THEN o.total ELSE 0 END) AS revenue
                  FROM {local_moderncommerce_orders} o
                  JOIN {user} u ON u.id = o.userid
                 WHERE u.deleted = 0";
        $stats = $DB->get_record_sql($sql) ?: (object) [];

        return [
            'totalcustomers' => (int) ($stats->totalcustomers ?? 0),
            'totalorders' => (int) ($stats->totalorders ?? 0),
            'paidorders' => (int) ($stats->paidorders ?? 0),
            'pendingorders' => (int) ($stats->pendingorders ?? 0),
            'refundedorders' => (int) ($stats->refundedorders ?? 0),
            'displayrevenue' => pricing_service::format_price((float) ($stats->revenue ?? 0)),
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
}
