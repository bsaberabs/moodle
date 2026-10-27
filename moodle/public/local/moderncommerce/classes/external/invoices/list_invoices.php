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
 * External API listing invoices for the admin invoices screen.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\invoices;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\localisation;
use local_moderncommerce\services\pricing_service;

/**
 * List invoices with customer, totals, and status.
 */
class list_invoices extends external_api {
    /** @var string[] Selectable statuses. */
    private const STATUSES = ['draft', 'sent', 'paid', 'overdue', 'cancelled', 'void'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search invoice number or customer email.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $search Search term.
     * @param string $status Status filter.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @return array
     */
    public static function execute(string $search = '', string $status = '', int $page = 0, int $perpage = 10): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:manageorders', $context);

        $page = max(0, $params['page']);
        $perpage = min(100, max(5, $params['perpage']));

        $where = ['1 = 1'];
        $sqlparams = [];
        if (in_array($params['status'], self::STATUSES, true)) {
            $where[] = 'i.status = :status';
            $sqlparams['status'] = $params['status'];
        }
        if (trim($params['search']) !== '') {
            $where[] = '(' . $DB->sql_like('i.invoicenumber', ':s1', false) . ' OR '
                . $DB->sql_like('u.email', ':s2', false) . ')';
            $term = '%' . $DB->sql_like_escape(trim($params['search'])) . '%';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
        }
        $wheresql = 'WHERE ' . implode(' AND ', $where);

        $from = "FROM {local_moderncommerce_invoices} i
                 JOIN {user} u ON u.id = i.userid";
        $total = (int) $DB->count_records_sql("SELECT COUNT(i.id) {$from} {$wheresql}", $sqlparams);

        $records = $DB->get_records_sql(
            "SELECT i.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                    u.middlename, u.alternatename, u.email,
                    (SELECT COUNT(1) FROM {local_moderncommerce_invoice_items} ii WHERE ii.invoiceid = i.id) AS itemcount
               {$from}
               {$wheresql}
           ORDER BY i.timecreated DESC, i.id DESC",
            $sqlparams,
            $page * $perpage,
            $perpage
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_invoice($record);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
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
            'items' => new external_multiple_structure(self::invoice_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching invoices.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'stats' => self::stats_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Invoice row structure.
     *
     * @return external_single_structure
     */
    private static function invoice_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Invoice ID.'),
            'invoicenumber' => new external_value(PARAM_TEXT, 'Invoice number.'),
            'customerid' => new external_value(PARAM_INT, 'Customer user ID.'),
            'customername' => new external_value(PARAM_TEXT, 'Customer name.'),
            'customeremail' => new external_value(PARAM_TEXT, 'Customer email.'),
            'rawtotal' => new external_value(PARAM_FLOAT, 'Raw total.'),
            'displaytotal' => new external_value(PARAM_TEXT, 'Formatted total.'),
            'status' => new external_value(PARAM_ALPHA, 'Status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Status badge class.'),
            'itemcount' => new external_value(PARAM_INT, 'Line item count.'),
            'duedate' => new external_value(PARAM_TEXT, 'Formatted due date.'),
            'created' => new external_value(PARAM_TEXT, 'Formatted created date.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total invoices.'),
            'paid' => new external_value(PARAM_INT, 'Paid invoices.'),
            'pending' => new external_value(PARAM_INT, 'Draft or sent invoices.'),
            'overdue' => new external_value(PARAM_INT, 'Overdue invoices.'),
            'displayoutstanding' => new external_value(PARAM_TEXT, 'Formatted outstanding amount.'),
        ]);
    }

    /**
     * Format one invoice record.
     *
     * @param \stdClass $record Invoice + user record.
     * @return array
     */
    private static function format_invoice(\stdClass $record): array {
        $status = (string) $record->status;

        return [
            'id' => (int) $record->id,
            'invoicenumber' => (string) $record->invoicenumber,
            'customerid' => (int) $record->userid,
            'customername' => fullname($record),
            'customeremail' => (string) $record->email,
            'rawtotal' => (float) $record->total,
            'displaytotal' => pricing_service::format_price((float) $record->total),
            'status' => $status,
            'statuslabel' => self::status_label($status),
            'statusclass' => self::status_class($status),
            'itemcount' => (int) $record->itemcount,
            'duedate' => $record->duedate
                ? userdate((int) $record->duedate, get_string('strftimedate', 'langconfig'))
                : '-',
            'created' => userdate((int) $record->timecreated, get_string('strftimedate', 'langconfig')),
        ];
    }

    /**
     * Localised status label.
     *
     * @param string $status Status.
     * @return string
     */
    public static function status_label(string $status): string {
        return localisation::status_label($status, ['invoicestatus']);
    }

    /**
     * Status badge class.
     *
     * @param string $status Status.
     * @return string
     */
    public static function status_class(string $status): string {
        switch ($status) {
            case 'paid':
                return 'success';
            case 'sent':
                return 'info';
            case 'overdue':
                return 'danger';
            case 'cancelled':
            case 'void':
                return 'neutral';
            default:
                return 'warning';
        }
    }

    /**
     * Invoice summary stats.
     *
     * @return array
     */
    private static function get_stats(): array {
        global $DB;

        $outstanding = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(total), 0)
               FROM {local_moderncommerce_invoices}
              WHERE status IN ('draft', 'sent', 'overdue')"
        );

        return [
            'total' => (int) $DB->count_records('local_moderncommerce_invoices'),
            'paid' => (int) $DB->count_records('local_moderncommerce_invoices', ['status' => 'paid']),
            'pending' => (int) $DB->count_records_select(
                'local_moderncommerce_invoices',
                "status IN ('draft', 'sent')"
            ),
            'overdue' => (int) $DB->count_records('local_moderncommerce_invoices', ['status' => 'overdue']),
            'displayoutstanding' => pricing_service::format_price($outstanding),
        ];
    }
}
