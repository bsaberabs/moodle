<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_oriskill;

use local_secureandpay\gateway;

/**
 * Transactions of a trainer: every amount actually collected, refused or refunded.
 *
 * - SecureAndPay: each notification, so each instalment on the day it is collected and each refusal with its reason;
 * - Stripe, PayPal and the manual payment of the shop: the payment of the order;
 * - a manual invoice (a sale outside the platform): a payment on its date;
 * - a credit note: a refund on its date.
 * An order invoiced without any payment recorded (paid before payments were recorded) counts on its invoice date.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transaction_service {

    /** Kinds of transaction. */
    const TYPES = ['payment', 'failed', 'refund'];

    /** SecureAndPay notifications of an amount collected, and of a refusal. */
    const SAP_PAID = ['secureandpay.00', 'secureandpay.02'];
    const SAP_FAILED = 'secureandpay.05';

    /** Rows per page. */
    const PERPAGE = [10, 25, 50, 100];

    /**
     * SQL of the orders of a trainer's courses (or invoiced in their name), for "orderid IN (...)".
     *
     * @param \stdClass $creator Trainer.
     * @return array [sql, params]
     */
    private static function orders_sql(\stdClass $creator): array {
        global $DB;

        $path = (string) $DB->get_field('course_categories', 'path', ['id' => $creator->categoryid]);
        return ["SELECT oi.orderid
                   FROM {local_moderncommerce_order_items} oi
                   JOIN {course} c ON c.id = oi.courseid
                   JOIN {course_categories} cc ON cc.id = c.category
                  WHERE cc.id = :tcat OR " . $DB->sql_like('cc.path', ':tpath') . "
                  UNION
                 SELECT orderid FROM {local_oriskill_invoices} WHERE creatorid = :tcreator AND orderid > 0",
            ['tcat' => (int) $creator->categoryid, 'tpath' => ($path ?: '/-1') . '/%', 'tcreator' => (int) $creator->id]];
    }

    /**
     * An order of the trainer's courses (or invoiced in their name), or an exception.
     *
     * @param \stdClass $creator Trainer.
     * @param int $orderid Order.
     * @return \stdClass
     */
    public static function require_order(\stdClass $creator, int $orderid): \stdClass {
        global $DB;

        [$orders, $params] = self::orders_sql($creator);
        $order = $DB->get_record_sql("SELECT * FROM {local_moderncommerce_orders} WHERE id = :id AND id IN ($orders)",
            ['id' => $orderid] + $params);
        if (!$order) {
            throw new \moodle_exception('error:notallowed', 'local_oriskill');
        }
        return $order;
    }

    /**
     * Transactions of one order, oldest first.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $order Order.
     * @return array See rows().
     */
    public static function of_order(\stdClass $creator, \stdClass $order): array {
        return self::rows($creator, 0, time(), true, (int) $order->id);
    }

    /**
     * Transactions of a period, oldest first.
     *
     * @param \stdClass $creator Trainer.
     * @param int $from Start (included).
     * @param int $to End (included).
     * @param bool $detail With the buyer, products, method, country and documents (false: time, type, amount only).
     * @param int $orderid Only the transactions of this order (0: all).
     * @return array of objects {key, time, type (payment, failed, refund), amount (negative for a refund), currency,
     *               userid, orderid, and with $detail: buyer, email, lines [name => amount], method, country, instalment
     *               (2/6, empty), reason (of a refusal), number and documentid (invoice or credit note, 0 when none)}
     */
    public static function rows(\stdClass $creator, int $from, int $to, bool $detail = true, int $orderid = 0): array {
        global $DB;

        [$orders, $params] = $orderid ? ['SELECT id FROM {local_moderncommerce_orders} WHERE id = :tonly', ['tonly' => $orderid]]
            : self::orders_sql($creator);
        $params += ['from' => $from, 'to' => $to];
        $rows = [];

        // SecureAndPay: every notification of a payment or a refusal.
        [$insql, $inparams] = $DB->get_in_or_equal(array_merge(self::SAP_PAID, [self::SAP_FAILED]), SQL_PARAMS_NAMED, 'ev');
        foreach ($DB->get_recordset_sql("SELECT e.id, e.orderid, e.eventtype, e.amount, e.currency, e.timecreated, e.rawpayload
                                           FROM {local_moderncommerce_payment_events} e
                                          WHERE e.gateway = 'secureandpay' AND e.eventtype $insql AND e.amount > 0
                                                AND e.timecreated >= :from AND e.timecreated <= :to
                                                AND e.orderid IN ($orders)", $params + $inparams) as $e) {
            $failed = $e->eventtype === self::SAP_FAILED;
            $raw = $failed ? (json_decode((string) $e->rawpayload, true) ?: []) : [];
            $rows[] = (object) ['key' => 'e' . $e->id, 'source' => 'sap', 'eventid' => (int) $e->id,
                'time' => (int) $e->timecreated, 'type' => $failed ? 'failed' : 'payment', 'amount' => round((float) $e->amount, 2),
                'currency' => (string) $e->currency, 'orderid' => (int) $e->orderid, 'method' => 'SecureAndPay',
                'reason' => trim(($raw['codeRefus'] ?? '') . ' ' . ($raw['detailsRefus'] ?? ''))];
        }

        // Other gateways: the payment of the order (or its failure).
        foreach ($DB->get_recordset_sql("SELECT a.id, a.orderid, a.gateway, a.status, a.amount, a.currency, a.errormessage,
                                                COALESCE(a.timecompleted, a.timemodified) AS time
                                           FROM {local_moderncommerce_payment_attempts} a
                                          WHERE a.gateway <> 'secureandpay' AND a.status IN ('success', 'failed') AND a.amount > 0
                                                AND COALESCE(a.timecompleted, a.timemodified) >= :from
                                                AND COALESCE(a.timecompleted, a.timemodified) <= :to
                                                AND a.orderid IN ($orders)", $params) as $a) {
            $rows[] = (object) ['key' => 'a' . $a->id, 'source' => 'attempt', 'time' => (int) $a->time,
                'type' => $a->status === 'failed' ? 'failed' : 'payment', 'amount' => round((float) $a->amount, 2),
                'currency' => (string) $a->currency, 'orderid' => (int) $a->orderid,
                'method' => ['stripe' => 'Stripe', 'paypal' => 'PayPal', 'manual' => get_string('stats:manual', 'local_oriskill')][
                    $a->gateway] ?? ucfirst((string) $a->gateway),
                'reason' => trim((string) $a->errormessage)];
        }

        // Invoices: manual sales, credit notes, and orders invoiced with no payment recorded.
        foreach ($DB->get_recordset_sql("SELECT d.*
                                           FROM {local_oriskill_invoices} d
                                          WHERE d.creatorid = :creator AND d.type IN ('invoice', 'credit')
                                                AND (:onlyorder = 0 OR d.orderid = :onlyorder2)
                                                AND d.timecreated >= :from AND d.timecreated <= :to
                                                AND (d.type = 'credit' OR d.orderid = 0 OR (
                                                     NOT EXISTS (SELECT 1 FROM {local_moderncommerce_payment_events} e
                                                                  WHERE e.orderid = d.orderid AND e.gateway = 'secureandpay')
                                                     AND NOT EXISTS (SELECT 1 FROM {local_moderncommerce_payment_attempts} a
                                                                      WHERE a.orderid = d.orderid AND a.gateway <> 'secureandpay'
                                                                            AND a.status = 'success')
                                                     AND d.id = (SELECT MIN(f.id) FROM {local_oriskill_invoices} f
                                                                  WHERE f.orderid = d.orderid AND f.type = 'invoice')))",
                ['creator' => (int) $creator->id, 'from' => $from, 'to' => $to, 'onlyorder' => $orderid,
                    'onlyorder2' => $orderid]) as $d) {
            $data = json_decode((string) $d->data, true) ?: [];
            if ($d->type === 'invoice' && !$d->orderid && array_key_exists('paid', $data) && !$data['paid']) {
                // A manual invoice not paid yet.
                continue;
            }
            $credit = $d->type === 'credit';
            $rows[] = (object) ['key' => 'd' . $d->id, 'source' => 'document', 'document' => $d, 'data' => $data,
                'time' => (int) $d->timecreated, 'type' => $credit ? 'refund' : 'payment',
                'amount' => ($credit ? -1 : 1) * round((float) $d->total, 2), 'currency' => (string) $d->currency,
                'orderid' => (int) $d->orderid, 'userid' => (int) $d->userid,
                'method' => (string) ($data['paymentmethod'] ?? $data['method'] ?? get_string('stats:manual', 'local_oriskill')),
                'reason' => ''];
        }

        usort($rows, fn($a, $b) => [$a->time, $a->key] <=> [$b->time, $b->key]);
        $orderids = array_values(array_unique(array_filter(array_map(fn($r) => $r->orderid, $rows))));
        $orderrecords = [];
        foreach (array_chunk($orderids, 1000) as $chunk) {
            $orderrecords += $DB->get_records_list('local_moderncommerce_orders', 'id', $chunk);
        }
        foreach ($rows as $row) {
            $order = $orderrecords[$row->orderid] ?? null;
            $row->userid = $row->userid ?? ($order ? (int) $order->userid : 0);
        }
        return $detail ? self::detail($creator, $rows, $orderrecords) : $rows;
    }

    /**
     * Buyer, products, method, country, instalment and document of each transaction.
     *
     * @param \stdClass $creator Trainer.
     * @param array $rows Transactions.
     * @param array $orders Orders by id.
     * @return array
     */
    private static function detail(\stdClass $creator, array $rows, array $orders): array {
        global $DB;

        $orderids = array_keys($orders);
        $items = [];
        $invoices = [];
        $sappaid = [];
        foreach (array_chunk($orderids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            foreach ($DB->get_recordset_select('local_moderncommerce_order_items', "orderid $insql", $params, 'id',
                    'id, orderid, itemname, total') as $item) {
                $items[$item->orderid][] = $item;
            }
            foreach ($DB->get_recordset_select('local_oriskill_invoices', "orderid $insql AND type = 'invoice'", $params, 'id',
                    'id, orderid, number') as $invoice) {
                $invoices[$invoice->orderid][] = $invoice;
            }
            [$evsql, $evparams] = $DB->get_in_or_equal(self::SAP_PAID, SQL_PARAMS_NAMED, 'ev');
            foreach ($DB->get_recordset_select('local_moderncommerce_payment_events', "orderid $insql AND gateway = 'secureandpay'
                    AND eventtype $evsql AND amount > 0", $params + $evparams, 'id', 'id, orderid') as $e) {
                $sappaid[$e->orderid][] = (int) $e->id;
            }
        }
        $userids = array_values(array_unique(array_filter(array_map(fn($r) => $r->userid, $rows))));
        $users = [];
        foreach (array_chunk($userids, 1000) as $chunk) {
            $users += $DB->get_records_list('user', 'id', $chunk, '', 'id, firstname, lastname, email, country, ' .
                implode(', ', \core_user\fields::get_name_fields()));
        }
        $countries = get_string_manager()->get_list_of_countries();
        $billings = [];
        $plans = [];

        foreach ($rows as $row) {
            $order = $orders[$row->orderid] ?? null;
            $user = $users[$row->userid] ?? null;
            $data = $row->data ?? [];
            $row->buyer = (string) ($data['buyer']['name'] ?? ($user ? fullname($user) : ''));
            $row->email = (string) ($data['buyer']['email'] ?? ($user->email ?? ''));

            // Products: the amount of this transaction spread over the products in proportion.
            $lines = [];
            $source = $order && empty($data['lines']) ? array_map(fn($i) => ['name' => $i->itemname, 'total' => $i->total],
                $items[$order->id] ?? []) : ($data['lines'] ?? []);
            $sum = array_sum(array_map(fn($l) => (float) ($l['total'] ?? 0), $source));
            foreach ($source as $line) {
                $name = trim((string) ($line['name'] ?? '')) ?: '–';
                $lines[$name] = ($lines[$name] ?? 0) + ($sum > 0 ? $row->amount * (float) $line['total'] / $sum : 0);
            }
            $row->lines = $lines ?: ['–' => $row->amount];

            // Which instalment, for a purchase in several payments.
            $row->instalment = '';
            if ($order && $row->source === 'sap') {
                $plan = $plans[$order->id] ??= (gateway::instalment_plan($order) ?: []);
                $paid = $sappaid[$order->id] ?? [];
                $rank = count(array_filter($paid, fn($id) => $id <= $row->eventid)) + ($row->type === 'failed' ? 1 : 0);
                if ($plan && !empty($plan['subscription'])) {
                    $row->instalment = get_string('transactions:month', 'local_oriskill', max(1, $rank));
                } else if ($plan && (int) $plan['count'] > 1) {
                    $row->instalment = min(max(1, $rank), (int) $plan['count']) . '/' . (int) $plan['count'];
                }
            }

            // The invoice that goes with it (the month of a subscription, the invoice of the order), or the document itself.
            $row->documentid = 0;
            $row->number = '';
            if ($row->source === 'document') {
                $row->documentid = (int) $row->document->id;
                $row->number = (string) $row->document->number;
            } else if ($order && !empty($invoices[$order->id]) && $row->type === 'payment') {
                $list = $invoices[$order->id];
                $index = !empty($plans[$order->id]['subscription'])
                    ? max(0, count(array_filter($sappaid[$order->id] ?? [], fn($id) => $id <= $row->eventid)) - 1) : 0;
                $invoice = $list[min($index, count($list) - 1)];
                $row->documentid = (int) $invoice->id;
                $row->number = (string) $invoice->number;
            }

            if ($order && $row->source === 'document') {
                // A refund, or an order invoiced with no payment recorded: how the order was paid.
                $row->method = billing_service::payment_method($order);
            }
            $billing = $order ? ($billings[$order->id] ??= orderform_service::billing_of_order((int) $order->id)) : [];
            $code = (string) ($billing['country'] ?? '') ?: (string) ($user->country ?? '');
            $row->country = $code !== '' ? ($countries[$code] ?? $code) : '–';
            unset($row->document, $row->data);
        }
        return $rows;
    }

    /**
     * Transactions matching the filters of the Transactions page, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @param array $filters from, to (timestamps), type, search (name, e-mail, invoice or order number), product, method
     * @param array|null $rows Transactions of the period already read (rows()), to filter further.
     * @return array
     */
    public static function search(\stdClass $creator, array $filters, ?array $rows = null): array {
        global $DB;

        $rows = $rows ?? self::rows($creator, (int) ($filters['from'] ?? 0), (int) ($filters['to'] ?? time()));
        $search = \core_text::strtolower(trim((string) ($filters['search'] ?? '')));
        $numbers = [];
        if ($search !== '') {
            $orderids = array_unique(array_filter(array_map(fn($r) => $r->orderid, $rows)));
            foreach (array_chunk($orderids, 1000) as $chunk) {
                $numbers += $DB->get_records_list('local_moderncommerce_orders', 'id', $chunk, '', 'id, ordernumber');
            }
        }
        $rows = array_filter($rows, function($row) use ($filters, $search, $numbers) {
            if (!empty($filters['type']) && $row->type !== $filters['type']) {
                return false;
            }
            if (!empty($filters['product']) && !isset($row->lines[$filters['product']])) {
                return false;
            }
            if (!empty($filters['method']) && $row->method !== $filters['method']) {
                return false;
            }
            if ($search !== '') {
                $haystack = \core_text::strtolower(implode(' ', [$row->buyer, $row->email, $row->number,
                    $numbers[$row->orderid]->ordernumber ?? '']));
                return str_contains($haystack, $search);
            }
            return true;
        });
        return array_reverse(array_values($rows));
    }

    /**
     * Amount collected (refunds taken off) and number of payments, per currency.
     *
     * @param array $rows Transactions.
     * @return array currency => {amount, count, refunds, failed}
     */
    public static function totals(array $rows): array {
        $totals = [];
        foreach ($rows as $row) {
            $t = $totals[$row->currency] ?? (object) ['amount' => 0.0, 'count' => 0, 'refunds' => 0.0, 'failed' => 0];
            if ($row->type === 'failed') {
                $t->failed++;
            } else if ($row->type === 'refund') {
                $t->amount += $row->amount;
                $t->refunds -= $row->amount;
            } else {
                $t->amount += $row->amount;
                $t->count++;
            }
            $totals[$row->currency] = $t;
        }
        return $totals;
    }

    /**
     * Table of transactions: date, buyer (link to the CRM), product, payment method, amount, state, invoice.
     *
     * @param array $rows Transactions.
     * @param \stdClass $creator Trainer.
     * @param string $timezone Time zone of the trainer.
     * @param bool $links With a link to the detail of each sale.
     * @return string HTML
     */
    public static function table(array $rows, \stdClass $creator, string $timezone, bool $links = true): string {
        global $DB;

        $userids = array_values(array_unique(array_filter(array_map(fn($r) => $r->userid, $rows))));
        $contacts = [];
        if ($userids && team_service::can(creator_service::current_role(), '/local/oriskill/contact.php')) {
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $contacts = $DB->get_records_select_menu('local_oriskill_contacts', "creatorid = :creator AND userid $insql",
                ['creator' => $creator->id] + $params, '', 'userid, id');
        }
        $tones = ['payment' => 'is-good', 'failed' => 'is-bad', 'refund' => 'is-warn'];
        $table = new \html_table();
        $table->attributes['class'] = 'table align-middle oriskill-txlist';
        $table->head = [get_string('date'), get_string('transactions:buyer', 'local_oriskill'),
            get_string('stats:product', 'local_oriskill'), get_string('transactions:method', 'local_oriskill'),
            get_string('transactions:amount', 'local_oriskill'), get_string('status'), get_string('invoice:number', 'local_oriskill')];
        if ($links) {
            $table->head[] = '';
        }
        foreach ($rows as $row) {
            $buyer = s($row->buyer ?: '?');
            if (isset($contacts[$row->userid])) {
                $buyer = \html_writer::link(new \moodle_url('/local/oriskill/contact.php', ['id' => $contacts[$row->userid]]),
                    $buyer);
            }
            $table->data[] = [
                userdate($row->time, get_string('strftimedatetimeshort', 'langconfig'), $timezone),
                $buyer . \html_writer::div(s($row->email), 'small text-muted'),
                s(implode(', ', array_keys($row->lines))) . ($row->instalment
                    ? \html_writer::div(get_string('transactions:instalmentof', 'local_oriskill', $row->instalment),
                        'small text-muted') : ''),
                s($row->method),
                \html_writer::tag('strong', \local_moderncommerce\services\pricing_service::format_price($row->amount,
                    $row->currency), $row->type === 'failed' ? ['class' => 'text-muted text-decoration-line-through'] : []),
                \html_writer::span(get_string('transactions:type_' . $row->type, 'local_oriskill'),
                    'oriskill-badge ' . $tones[$row->type]) .
                    ($row->reason !== '' ? \html_writer::div(s($row->reason), 'small text-muted') : ''),
                $row->documentid ? \html_writer::link(new \moodle_url('/local/oriskill/invoice.php', ['id' => $row->documentid]),
                    '<i class="fa fa-file-lines me-1" aria-hidden="true"></i>' . s($row->number)) : '',
            ];
            if ($links) {
                $table->data[array_key_last($table->data)][] = $row->orderid
                    ? \html_writer::link(new \moodle_url('/local/oriskill/sale.php', ['id' => $row->orderid]),
                        get_string('transactions:detail', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-primary'])
                    : '';
            }
        }
        return \html_writer::table($table);
    }

    /**
     * CSV lines of transactions (export, filters applied).
     *
     * @param array $rows Transactions.
     * @param string $timezone Time zone of the trainer.
     * @return array of rows, the header first
     */
    public static function csv(array $rows, string $timezone): array {
        $lines = [[get_string('date'), get_string('transactions:buyer', 'local_oriskill'), get_string('email'),
            get_string('stats:product', 'local_oriskill'), get_string('transactions:instalment', 'local_oriskill'),
            get_string('transactions:method', 'local_oriskill'), get_string('transactions:amount', 'local_oriskill'),
            get_string('currency'), get_string('status'), get_string('transactions:reason', 'local_oriskill'),
            get_string('invoice:number', 'local_oriskill'), get_string('country')]];
        foreach ($rows as $row) {
            $lines[] = [userdate($row->time, '%Y-%m-%d %H:%M:%S', $timezone, false, false), $row->buyer, $row->email,
                implode(', ', array_keys($row->lines)), $row->instalment, $row->method, format_float($row->amount, 2, false),
                $row->currency, get_string('transactions:type_' . $row->type, 'local_oriskill'), $row->reason, $row->number,
                $row->country];
        }
        return $lines;
    }
}
