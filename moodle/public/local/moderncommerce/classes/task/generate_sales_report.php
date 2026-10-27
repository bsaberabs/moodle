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

namespace local_moderncommerce\task;


/**
 * Scheduled task to generate daily sales reports
 *
 * Aggregates sales data and stores in a reporting table for dashboard.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_sales_report extends \core\task\scheduled_task {
    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:generate_sales_report', 'local_moderncommerce');
    }

    /**
     * Execute task
     */
    public function execute() {

        global $DB;
        // Generate report for yesterday.
        $reportdate = strtotime('yesterday midnight');
        $today = strtotime('today midnight');
        $now = time();
        [$statussql, $statusparams] = $DB->get_in_or_equal(['paid', 'completed'], SQL_PARAMS_NAMED, 'orderstatus');
        $params = array_merge($statusparams, [
            'start' => $reportdate, 'end' => $today,
        ]);
        $dailyrows = $DB->get_records_sql("SELECT currency,
                    COUNT(id) AS ordercount,
                    COUNT(id) AS paidorders,
                    COALESCE(SUM(total), 0) AS gross,
                    COALESCE(SUM(discount), 0) AS discount,
                    COALESCE(SUM(tax), 0) AS tax,
                    COALESCE(SUM(total) - SUM(refundedtotal), 0) AS net
                FROM {local_moderncommerce_orders}
                WHERE status {$statussql}
                AND timecreated >= :start
                AND timecreated < :end
                GROUP BY currency", $params);
        foreach ($dailyrows as $row) {
            $record = (object) [
                'reportdate' => $reportdate,
                'currency' => $row->currency,
                'orders' => (int)$row->ordercount,
                'paidorders' => (int)$row->paidorders,
                'refunds' => 0,
                'gross' => (float)$row->gross,
                'discount' => (float)$row->discount,
                'tax' => (float)$row->tax,
                'net' => (float)$row->net,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $this->upsert_record('local_moderncommerce_report_daily', $record, [
                'reportdate' => $reportdate, 'currency' => $row->currency,
            ]);
        }

        $productrows = $DB->get_records_sql("SELECT i.productid,
                    o.currency,
                    COALESCE(SUM(i.quantity), 0) AS quantity,
                    COALESCE(SUM(i.subtotal), 0) AS gross,
                    COALESCE(SUM(i.discount), 0) AS discount,
                    COALESCE(SUM(i.total), 0) AS net
                FROM {local_moderncommerce_order_items} i
                JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
                WHERE o.status {$statussql}
                AND o.timecreated >= :start
                AND o.timecreated < :end
                AND i.productid IS NOT NULL
                GROUP BY i.productid, o.currency", $params);
        foreach ($productrows as $row) {
            $record = (object) [
                'reportdate' => $reportdate,
                'productid' => (int)$row->productid,
                'currency' => $row->currency,
                'quantity' => (float)$row->quantity,
                'gross' => (float)$row->gross,
                'discount' => (float)$row->discount,
                'net' => (float)$row->net,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $this->upsert_record('local_moderncommerce_report_products', $record, [
                'reportdate' => $reportdate, 'productid' => (int)$row->productid, 'currency' => $row->currency,
            ]);
        }

        $gatewayrows = $DB->get_records_sql("SELECT gateway,
                    currency,
                    COUNT(id) AS attempts,
                    COALESCE(SUM(CASE WHEN status IN ('success', 'paid', 'completed') THEN 1 ELSE 0 END), 0) AS successful,
                    COALESCE(SUM(CASE WHEN status IN ('failed', 'cancelled', 'error') THEN 1 ELSE 0 END), 0) AS failed,
                    COALESCE(SUM(CASE WHEN status IN ('success', 'paid', 'completed') THEN amount ELSE 0 END), 0) AS amount
                FROM {local_moderncommerce_payment_attempts}
                WHERE timecreated >= :start
                AND timecreated < :end
                GROUP BY gateway, currency", [
            'start' => $reportdate,
            'end' => $today,
        ]);
        foreach ($gatewayrows as $row) {
            $record = (object) [
                'reportdate' => $reportdate,
                'gateway' => (string)$row->gateway,
                'currency' => (string)$row->currency,
                'attempts' => (int)$row->attempts,
                'successful' => (int)$row->successful,
                'failed' => (int)$row->failed,
                'amount' => (float)$row->amount,
                'fees' => 0.0,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $this->upsert_record('local_moderncommerce_report_gateways', $record, [
                'reportdate' => $reportdate, 'gateway' => (string)$row->gateway, 'currency' => (string)$row->currency,
            ]);
        }

        mtrace("Generated sales snapshots for " . date('Y-m-d', $reportdate));
        mtrace("  Currencies: " . count($dailyrows) . ", Products: " . count($productrows)
            . ", Gateways: " . count($gatewayrows));
    }

    /**
     * Inserts or updates a snapshot row.
     *
     * @param string $tablename Table name without braces.
     * @param \stdClass $record Snapshot record.
     * @param array $conditions Natural key fields.
     */
    private function upsert_record(string $tablename, \stdClass $record, array $conditions): void {

        global $DB;
        $existing = $DB->get_record($tablename, $conditions, 'id, timecreated', IGNORE_MULTIPLE);
        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record($tablename, $record);
            return;
        }

        $DB->insert_record($tablename, $record);
    }
}
