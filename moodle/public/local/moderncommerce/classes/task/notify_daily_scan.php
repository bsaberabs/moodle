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
 * Daily notification scan: invoice due/overdue reminders + the admin sales digest.
 *
 * Time-based notifications that have no single trigger event are scanned here and
 * dispatched through the core notification subsystem.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_daily_scan extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return 'Modern Commerce daily notification scan';
    }

    /**
     * Run the scan.
     */
    public function execute() {
        $this->scan_invoices();
        $this->scan_keys();
        $this->send_payment_failure_alert();
        $this->send_renewal_forecast();
        $this->send_winback();
        $this->send_sales_digest();
    }

    /**
     * Enrollment-key reminders to the buyer/manager who created them.
     *
     * Expiry is aggregated per batch (one notice, not one per key); pool-low fires
     * for multi-seat keys nearing exhaustion. Both addressed to the creator.
     *
     * @return void
     */
    private function scan_keys(): void {
        global $DB, $CFG;

        $now = time();
        $dashurl = $CFG->wwwroot . '/local/moderncommerce/index.php';

        // Expiring batches (fire on the 3-day and 1-day marks), aggregated per creator+batch.
        $expiring = $DB->get_records_select(
            'local_moderncommerce_enrollkeys',
            "status = 'active' AND expirydate > :now AND expirydate <= :soon",
            ['now' => $now, 'soon' => $now + 4 * DAYSECS]
        );
        $groups = [];
        foreach ($expiring as $k) {
            $days = (int) ceil(($k->expirydate - $now) / DAYSECS);
            if ($days !== 3 && $days !== 1) {
                continue;
            }
            $gkey = $k->createdby . '|' . ($k->batchid ?: $k->id) . '|' . $days;
            if (!isset($groups[$gkey])) {
                $groups[$gkey] = [
                    'createdby' => (int) $k->createdby,
                    'batchname' => $k->batchname ?: '',
                    'expiry' => $k->expirydate,
                    'batchid' => (int) ($k->batchid ?: $k->id),
                    'count' => 0,
                ];
            }
            $groups[$gkey]['count']++;
        }
        foreach ($groups as $g) {
            if ($g['createdby'] <= 0) {
                continue;
            }
            $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'key_expiring'))
                ->category('reminder')
                ->template('moderncommerce_key_expiring')
                ->to_user($g['createdby'])
                ->placeholders([
                    'key_target_name' => $g['batchname'] ?: get_string('enrollmentkeys', 'local_moderncommerce'),
                    'key_code' => $g['count'] . ' key(s)',
                    'key_expiry' => userdate($g['expiry']),
                    'redeem_url' => $dashurl,
                ])
                ->context_url($dashurl)
                ->related($g['batchid']);
            \local_moderncommerce\notifications\api::notify($notification);
        }

        // Pool-low: multi-seat keys nearly exhausted.
        $threshold = (int) (get_config('local_moderncommerce', 'keypool_low_threshold') ?: 3);
        $lowkeys = $DB->get_records_select(
            'local_moderncommerce_enrollkeys',
            "status = 'active' AND maxuses > 1 AND (maxuses - usedcount) > 0 AND (maxuses - usedcount) <= :thr",
            ['thr' => max(1, $threshold)]
        );
        foreach ($lowkeys as $k) {
            if ((int) $k->createdby <= 0) {
                continue;
            }
            $remaining = (int) $k->maxuses - (int) $k->usedcount;
            $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'key_pool_low'))
                ->category('reminder')
                ->template('moderncommerce_key_pool_low')
                ->to_user((int) $k->createdby)
                ->placeholders([
                    'key_target_name' => $k->batchname ?: ('Key ' . $k->keycode),
                    'seats_remaining' => $remaining,
                    'seats_total' => (int) $k->maxuses,
                    'seats_used' => (int) $k->usedcount,
                    'manager_dashboard_url' => $dashurl,
                    'organisation_name' => '',
                ])
                ->context_url($dashurl)
                ->related((int) $k->id);
            \local_moderncommerce\notifications\api::notify($notification);
        }

        if (!empty($groups) || !empty($lowkeys)) {
            mtrace('notify_daily_scan: ' . count($groups) . ' key-expiry, ' . count($lowkeys) . ' pool-low reminders.');
        }
    }

    /**
     * Win-back: re-engage learners whose subscription was cancelled ~7 days ago.
     *
     * Marketing category, so the unsubscribe + suppression list apply; one send per
     * subscription (dedupe tag). The discount/coupon come from plugin config.
     *
     * @return void
     */
    private function send_winback(): void {
        global $DB, $CFG;

        if (!$DB->get_manager()->table_exists('local_moderncommerce_user_subscriptions')) {
            return;
        }

        $now = time();
        $from = $now - 8 * DAYSECS;
        $to = $now - 7 * DAYSECS;
        try {
            $subs = $DB->get_records_select(
                'local_moderncommerce_user_subscriptions',
                "status = 'cancelled' AND cancelled_at >= :from AND cancelled_at < :to",
                ['from' => $from, 'to' => $to]
            );
        } catch (\Throwable $e) {
            return;
        }
        if (empty($subs)) {
            return;
        }

        $coupon = (string) get_config('local_moderncommerce', 'winback_coupon');
        $discount = (string) (get_config('local_moderncommerce', 'winback_discount') ?: '20%');

        foreach ($subs as $sub) {
            $url = (new \moodle_url('/local/moderncommerce/learner/subscription.php', ['id' => $sub->id]))->out(false);
            $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'winback'))
                ->category('marketing')
                ->template('modernsubscription_winback')
                ->to_user((int) $sub->userid)
                ->placeholders([
                    'winback_discount' => $discount,
                    'winback_coupon' => $coupon,
                    'reactivate_url' => $url,
                ])
                ->context_url($url)
                ->related((int) $sub->id)
                ->dedup_tag('winback');
            \local_moderncommerce\notifications\api::notify($notification);
        }
        mtrace('notify_daily_scan: queued ' . count($subs) . ' win-back emails.');
    }

    /**
     * Alert admins when payment failures spike in the last 24 hours.
     *
     * @return void
     */
    private function send_payment_failure_alert(): void {
        global $DB, $CFG;

        $cutoff = time() - DAYSECS;
        try {
            $failed = $DB->get_records_select(
                'local_moderncommerce_payment_events',
                "status = 'failed' AND timecreated >= :cutoff",
                ['cutoff' => $cutoff],
                '',
                'id, gateway'
            );
        } catch (\Throwable $e) {
            return;
        }

        $count = count($failed);
        $threshold = (int) (get_config('local_moderncommerce', 'paymentfailure_alert_threshold') ?: 3);
        if ($count < max(1, $threshold)) {
            return;
        }

        $gateways = [];
        foreach ($failed as $f) {
            $g = $f->gateway ?: 'unknown';
            $gateways[$g] = ($gateways[$g] ?? 0) + 1;
        }
        arsort($gateways);
        $topgateway = (string) array_key_first($gateways);
        $url = $CFG->wwwroot . '/local/moderncommerce/index.php';

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'payment_failures'))
            ->category('operational')
            ->template('ops_payment_failures')
            ->placeholders([
                'failed_count' => $count,
                'period_label' => 'last 24 hours',
                'gateway_name' => $topgateway,
                'error_detail' => 'Multiple gateway declines/errors',
                'admin_dashboard_url' => $url,
            ])
            ->context_url($url);

        \local_moderncommerce\notifications\api::notify_admins($notification);
    }

    /**
     * Send admins the upcoming-renewal forecast (subscriptions due in the next 7 days).
     *
     * @return void
     */
    private function send_renewal_forecast(): void {
        global $DB, $CFG;

        if (!$DB->get_manager()->table_exists('local_moderncommerce_user_subscriptions')) {
            return;
        }

        $now = time();
        $week = $now + 7 * DAYSECS;
        try {
            $rows = $DB->get_records_sql(
                "SELECT s.id, p.price
                   FROM {local_moderncommerce_user_subscriptions} s
                   JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                  WHERE s.status IN ('active', 'trial', 'grace') AND s.auto_renew = 1
                    AND s.end_date > :now AND s.end_date <= :week",
                ['now' => $now, 'week' => $week]
            );
            $mrr = (float) $DB->get_field_sql(
                "SELECT COALESCE(SUM(p.price), 0)
                   FROM {local_moderncommerce_user_subscriptions} s
                   JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                  WHERE s.status = 'active'"
            );
        } catch (\Throwable $e) {
            return;
        }

        $count = count($rows);
        if ($count === 0) {
            return;
        }
        $value = 0.0;
        foreach ($rows as $r) {
            $value += (float) $r->price;
        }

        $haspricing = class_exists('\local_moderncommerce\services\pricing_service');
        $valuedisplay = $haspricing
            ? \local_moderncommerce\services\pricing_service::format_price($value)
            : number_format($value, 2);
        $mrrdisplay = $haspricing ? \local_moderncommerce\services\pricing_service::format_price($mrr) : number_format($mrr, 2);
        $url = $CFG->wwwroot . '/local/moderncommerce/index.php';

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'renewal_forecast'))
            ->category('operational')
            ->template('ops_renewal_forecast')
            ->placeholders([
                'upcoming_renewals_count' => $count,
                'upcoming_renewals_value' => $valuedisplay,
                'mrr_total' => $mrrdisplay,
                'period_label' => 'next 7 days',
                'ops_report_url' => $url,
            ])
            ->context_url($url);

        \local_moderncommerce\notifications\api::notify_admins($notification);
    }

    /**
     * Invoice reminders: due-soon (3 and 1 days out) and overdue.
     *
     * @return void
     */
    private function scan_invoices(): void {
        global $DB;

        $now = time();

        // Due soon: still 'sent', fire on the 3-day and 1-day marks (one per day via dedupe bucket).
        $due = $DB->get_records_select(
            'local_moderncommerce_invoices',
            "status = 'sent' AND duedate > :now",
            ['now' => $now]
        );
        foreach ($due as $invoice) {
            $days = (int) ceil(($invoice->duedate - $now) / DAYSECS);
            if ($days === 3 || $days === 1) {
                $this->notify_invoice($invoice, 'invoice_due_soon', 'moderncommerce_invoice_due_soon', 'reminder', null);
            }
        }

        // Overdue: past due and still 'sent' — notify once, then flip status so it is not re-scanned.
        $overdue = $DB->get_records_select(
            'local_moderncommerce_invoices',
            "status = 'sent' AND duedate > 0 AND duedate < :now",
            ['now' => $now]
        );
        foreach ($overdue as $invoice) {
            $this->notify_invoice($invoice, 'invoice_overdue', 'moderncommerce_invoice_overdue', 'reminder', 'overdue');
            $DB->update_record('local_moderncommerce_invoices', (object) [
                'id' => $invoice->id,
                'status' => 'overdue',
                'timemodified' => $now,
            ]);
        }

        if (!empty($due) || !empty($overdue)) {
            mtrace('notify_daily_scan: processed ' . count($due) . ' due-soon, ' . count($overdue) . ' overdue invoices.');
        }
    }

    /**
     * Dispatch one invoice reminder through the hub.
     *
     * @param \stdClass $invoice Invoice record.
     * @param string $eventkey Event key.
     * @param string $templatekey Template key.
     * @param string $category Category.
     * @param string|null $deduptag Optional dedupe discriminator.
     * @return void
     */
    private function notify_invoice(
        \stdClass $invoice,
        string $eventkey,
        string $templatekey,
        string $category,
        ?string $deduptag
    ): void {
        global $CFG;

        $userid = !empty($invoice->customerid) ? (int) $invoice->customerid
            : (!empty($invoice->userid) ? (int) $invoice->userid : 0);
        if ($userid <= 0) {
            return;
        }

        $total = (float) ($invoice->total ?? 0);
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            $totaldisplay = \local_moderncommerce\services\pricing_service::format_price($total);
        } else {
            $totaldisplay = number_format($total, 2);
        }
        $url = $CFG->wwwroot . '/local/moderncommerce/download_invoice.php?id=' . $invoice->id;

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', $eventkey))
            ->category($category)
            ->template($templatekey)
            ->to_user($userid)
            ->placeholders([
                'invoice_number' => $invoice->invoicenumber ?? ('#' . $invoice->id),
                'invoice_total' => $totaldisplay,
                'invoice_duedate' => !empty($invoice->duedate) ? userdate($invoice->duedate) : '',
                'pay_invoice_url' => $url,
                'invoice_url' => $url,
                'invoice_pdf_url' => $url,
                'organisation_name' => '',
            ])
            ->context_url($url)
            ->related((int) $invoice->id);
        if ($deduptag !== null) {
            $notification->dedup_tag($deduptag);
        }

        \local_moderncommerce\notifications\api::notify($notification);
    }

    /**
     * Send the daily sales digest to store admins (skipped when there was no activity).
     *
     * @return void
     */
    private function send_sales_digest(): void {
        global $DB, $CFG;

        $cutoff = time() - DAYSECS;

        $orders = $DB->get_records_select(
            'local_moderncommerce_orders',
            "status IN ('paid', 'completed') AND timecreated >= :cutoff",
            ['cutoff' => $cutoff],
            '',
            'id, total'
        );
        $ordercount = count($orders);
        $revenue = 0.0;
        foreach ($orders as $order) {
            $revenue += (float) $order->total;
        }

        $refunds = $DB->count_records_select(
            'local_moderncommerce_refunds',
            'timerequested >= :cutoff',
            ['cutoff' => $cutoff]
        );

        $newsubs = 0;
        $churn = 0;
        if ($DB->get_manager()->table_exists('local_moderncommerce_user_subscriptions')) {
            $newsubs = $DB->count_records_select(
                'local_moderncommerce_user_subscriptions',
                'start_date >= :cutoff',
                ['cutoff' => $cutoff]
            );
            try {
                $churn = $DB->count_records_select(
                    'local_moderncommerce_user_subscriptions',
                    "status = 'cancelled' AND timemodified >= :cutoff",
                    ['cutoff' => $cutoff]
                );
            } catch (\Throwable $e) {
                $churn = 0;
            }
        }

        if ($ordercount === 0 && $refunds === 0 && $newsubs === 0) {
            return; // Nothing happened; don't send an empty digest.
        }

        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            $revenuedisplay = \local_moderncommerce\services\pricing_service::format_price($revenue);
        } else {
            $revenuedisplay = number_format($revenue, 2);
        }
        $reporturl = $CFG->wwwroot . '/local/moderncommerce/index.php';

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'sales_digest'))
            ->category('operational')
            ->template('ops_sales_digest')
            ->placeholders([
                'period_label' => 'last 24 hours',
                'revenue_total' => $revenuedisplay,
                'orders_count' => $ordercount,
                'refunds_count' => $refunds,
                'new_subs_count' => $newsubs,
                'churn_count' => $churn,
                'ops_report_url' => $reporturl,
            ])
            ->context_url($reporturl);

        \local_moderncommerce\notifications\api::notify_admins($notification);
        mtrace("notify_daily_scan: sales digest sent ({$ordercount} orders, {$refunds} refunds).");
    }
}
