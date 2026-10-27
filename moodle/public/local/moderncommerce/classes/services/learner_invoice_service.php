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
 * Learner-facing manual invoice helper.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

use local_moderncommerce\localisation;
use moodle_url;
use xmldb_table;

/**
 * Provides learner-safe manual invoice rows.
 */
class learner_invoice_service {
    /** @var string[] Statuses learners are allowed to see. */
    public const VISIBLE_STATUSES = ['sent', 'overdue', 'paid'];

    /** @var string[] Statuses counted as outstanding. */
    private const OUTSTANDING_STATUSES = ['sent', 'overdue'];

    /**
     * Get recent manual invoices visible to a learner.
     *
     * @param int $userid User ID.
     * @param int $limit Result limit.
     * @return array Invoice rows.
     */
    public static function recent_for_user(int $userid, int $limit = 5): array {
        return self::list_for_user($userid, 0, $limit)['items'];
    }

    /**
     * Get manual invoices visible to a learner.
     *
     * @param int $userid User ID.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @return array
     */
    public static function list_for_user(int $userid, int $page = 0, int $perpage = 10): array {
        global $DB;

        $page = max(0, $page);
        $perpage = min(50, max(1, $perpage));

        if ($userid <= 0 || !self::table_exists('local_moderncommerce_invoices')) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

        [$wheresql, $params] = self::visible_where_sql($userid, self::VISIBLE_STATUSES);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(1) FROM {local_moderncommerce_invoices} i WHERE {$wheresql}",
            $params
        );

        $records = $DB->get_records_sql(
            "SELECT i.*
               FROM {local_moderncommerce_invoices} i
              WHERE {$wheresql}
           ORDER BY COALESCE(i.issuedat, i.timecreated) DESC, i.id DESC",
            $params,
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
        ];
    }

    /**
     * Get visible invoice statistics for a learner.
     *
     * @param int $userid User ID.
     * @return array
     */
    public static function stats_for_user(int $userid): array {
        global $DB;

        $empty = [
            'total' => 0,
            'paid' => 0,
            'outstanding' => 0,
            'displayoutstanding' => pricing_service::format_price(0),
        ];

        if ($userid <= 0 || !self::table_exists('local_moderncommerce_invoices')) {
            return $empty;
        }

        [$visiblesql, $visibleparams] = self::visible_where_sql($userid, self::VISIBLE_STATUSES);
        [$outstandingsql, $outstandingparams] = self::visible_where_sql($userid, self::OUTSTANDING_STATUSES);

        $outstandingamount = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(i.total), 0)
               FROM {local_moderncommerce_invoices} i
              WHERE {$outstandingsql}",
            $outstandingparams
        );

        return [
            'total' => (int) $DB->count_records_sql(
                "SELECT COUNT(1) FROM {local_moderncommerce_invoices} i WHERE {$visiblesql}",
                $visibleparams
            ),
            'paid' => (int) $DB->count_records_sql(
                "SELECT COUNT(1)
                   FROM {local_moderncommerce_invoices} i
                  WHERE {$visiblesql} AND i.status = :paidstatus",
                $visibleparams + ['paidstatus' => 'paid']
            ),
            'outstanding' => (int) $DB->count_records_sql(
                "SELECT COUNT(1) FROM {local_moderncommerce_invoices} i WHERE {$outstandingsql}",
                $outstandingparams
            ),
            'displayoutstanding' => pricing_service::format_price($outstandingamount),
        ];
    }

    /**
     * Build shared visible invoice WHERE SQL.
     *
     * @param int $userid User ID.
     * @param array $statuses Visible statuses.
     * @return array [SQL, params]
     */
    private static function visible_where_sql(int $userid, array $statuses): array {
        global $DB;

        [$statussql, $statusparams] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'linvstatus');

        return [
            "i.userid = :linvuserid
                 AND (i.orderid IS NULL OR i.orderid = 0)
                 AND i.status {$statussql}",
            ['linvuserid' => $userid] + $statusparams,
        ];
    }

    /**
     * Format one invoice row for learner-facing APIs.
     *
     * @param \stdClass $invoice Invoice record.
     * @return array
     */
    private static function format_invoice(\stdClass $invoice): array {
        $status = (string) $invoice->status;
        $issued = (int) ($invoice->issuedat ?: $invoice->timecreated);

        return [
            'id' => (int) $invoice->id,
            'invoicenumber' => (string) $invoice->invoicenumber,
            'date' => $issued > 0 ? userdate($issued, get_string('strftimedateshort')) : '',
            'datetime' => $issued > 0 ? userdate($issued, get_string('strftimedatetime')) : '',
            'duedate' => !empty($invoice->duedate)
                ? userdate((int) $invoice->duedate, get_string('strftimedateshort'))
                : '-',
            'total' => pricing_service::format_order_price((float) $invoice->total, $invoice),
            'status' => $status,
            'statuslabel' => self::status_label($status),
            'statusclass' => self::status_class($status),
            'downloadurl' => (new moodle_url('/local/moderncommerce/download_invoice.php', [
                'id' => (int) $invoice->id,
            ]))->out(false),
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
            default:
                return 'neutral';
        }
    }

    /**
     * Check whether a table exists.
     *
     * @param string $table Table name.
     * @return bool
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }
}
