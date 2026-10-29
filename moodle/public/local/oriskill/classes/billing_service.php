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

use local_moderncommerce\services\pricing_service;
use local_secureandpay\gateway;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/pdflib.php');

/**
 * Invoices and credit notes in a trainer's name.
 *
 * A trainer is paid on their own account, so they are the seller: each paid order of their courses gets an
 * invoice numbered in the trainer's own continuous sequence (F-000001...), and a refund a credit note in a
 * second sequence (AV-000001...). A document keeps a snapshot of the seller, the buyer and the lines: it
 * never changes once issued. Prices include VAT; for a trainer subject to VAT, the VAT is shown at their rate.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class billing_service {

    /** Number prefix per document type. */
    const PREFIX = ['invoice' => 'F', 'credit' => 'AV'];

    /**
     * Whether the trainer filled in what an invoice must state about the seller.
     *
     * @param \stdClass $creator Trainer.
     * @return bool
     */
    public static function details_complete(\stdClass $creator): bool {
        return trim((string) $creator->legalname) !== '' && trim((string) $creator->legaladdress) !== ''
            && trim((string) $creator->siret) !== '';
    }

    /**
     * Save the trainer's billing details (used by the documents issued from now on).
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data legalname, legaladdress, siret, vatnumber, vatrate (0 = not subject to VAT), invoicenotes.
     */
    public static function save_details(\stdClass $creator, \stdClass $data): void {
        global $DB;

        $DB->update_record('local_oriskill_creators', (object) [
            'id' => $creator->id,
            'legalname' => trim($data->legalname),
            'legaladdress' => trim($data->legaladdress),
            'siret' => trim($data->siret),
            'vatnumber' => trim($data->vatnumber),
            'vatrate' => round(max(0, (float) $data->vatrate), 2),
            'invoicenotes' => trim($data->invoicenotes),
            'timemodified' => time(),
        ]);
    }

    /**
     * Issue the invoice of a paid order of a trainer's courses, once.
     *
     * @param int $orderid Order.
     * @return \stdClass|null The invoice, or null for an order that is not a trainer's (or not paid).
     */
    public static function issue_invoice(int $orderid): ?\stdClass {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        if (!$order || !in_array($order->status, ['paid', 'completed', 'refunded'], true)) {
            return null;
        }
        if ($invoice = $DB->get_record('local_oriskill_invoices', ['orderid' => $orderid, 'type' => 'invoice'])) {
            return $invoice;
        }
        $sellers = seller_service::purchase_sellers($order, (int) $order->userid);
        $creator = count($sellers) === 1 ? reset($sellers) : null;
        if (!$creator) {
            return null;
        }

        $vatrate = (float) $creator->vatrate;
        $lines = [];
        $tax = 0.0;
        foreach (\local_moderncommerce\api\order_api::get_order_items($orderid) as $item) {
            $total = round((float) $item->total, 2);
            $linetax = $vatrate > 0 ? round($total - $total / (1 + $vatrate / 100), 2) : 0.0;
            $tax += $linetax;
            $lines[] = ['name' => $item->itemname ?: ($item->coursename ?? ''), 'quantity' => (float) $item->quantity,
                'unitprice' => round((float) $item->unitprice, 2), 'total' => $total, 'tax' => $linetax];
        }
        // An order discount (coupon) is spread over the VAT in proportion.
        $total = round((float) $order->total, 2);
        $gross = array_sum(array_column($lines, 'total'));
        if ($gross > 0 && abs($gross - $total) >= 0.01) {
            $tax = round($tax * $total / $gross, 2);
        }
        $mentions = [];
        $plan = gateway::instalment_plan($order);
        if ($plan) {
            $mentions[] = get_string('invoice:instalments', 'local_oriskill', (object) ['count' => $plan['count'],
                'amount' => pricing_service::format_price($plan['amount'], $order->currency)]);
        }
        return self::issue($creator, $order, 'invoice', null, $total, round($tax, 2), [
            'lines' => $lines,
            'discount' => round(max(0, $gross - $total), 2),
            // Paid in instalments: the invoice is settled only once the last one is received.
            'paid' => !$plan,
            'mentions' => $mentions,
        ]);
    }

    /**
     * Issue a credit note cancelling all or part of an invoice.
     *
     * @param \stdClass $invoice Invoice.
     * @param float $amount Amount including VAT, at most what the earlier credit notes left.
     * @param string $reason Reason printed on the credit note.
     * @return \stdClass Credit note.
     */
    public static function issue_credit_note(\stdClass $invoice, float $amount, string $reason): \stdClass {
        global $DB;

        $amount = round($amount, 2);
        if ($invoice->type !== 'invoice' || $amount <= 0 || $amount > self::creditable($invoice) + 0.001) {
            throw new \moodle_exception('error:creditamount', 'local_oriskill');
        }
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $invoice->creatorid], '*', MUST_EXIST);
        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $invoice->orderid], '*', MUST_EXIST);
        $data = json_decode($invoice->data, true);
        $tax = (float) $invoice->total > 0 ? round((float) $invoice->tax * $amount / (float) $invoice->total, 2) : 0.0;
        $credit = self::issue($creator, $order, 'credit', $invoice, $amount, $tax, [
            'lines' => [['name' => get_string('credit:line', 'local_oriskill', $invoice->number) .
                ($reason !== '' ? ' – ' . $reason : ''), 'quantity' => 1, 'unitprice' => $amount, 'total' => $amount,
                'tax' => $tax]],
            'discount' => 0,
            'paid' => false,
            'mentions' => [],
            'vatrate' => $data['seller']['vatrate'] ?? 0,
        ]);

        // The buyer gets the credit note.
        if ($buyer = $DB->get_record('user', ['id' => $credit->userid, 'deleted' => 0])) {
            $a = (object) ['number' => $credit->number, 'invoice' => $invoice->number,
                'trainer' => format_string($creator->displayname), 'url' => self::url($credit)->out(false),
                'amount' => pricing_service::format_price($amount, $credit->currency)];
            email_to_user($buyer, \core_user::get_noreply_user(), get_string('credit:mail_subject', 'local_oriskill', $a),
                get_string('credit:mail_body', 'local_oriskill', $a));
        }
        return $credit;
    }

    /**
     * Amount of an invoice not yet cancelled by credit notes.
     *
     * @param \stdClass $invoice Invoice.
     * @return float
     */
    public static function creditable(\stdClass $invoice): float {
        global $DB;

        $credited = (float) $DB->get_field_sql("SELECT COALESCE(SUM(total), 0) FROM {local_oriskill_invoices}
                                                 WHERE parentid = ? AND type = 'credit'", [$invoice->id]);
        return round((float) $invoice->total - $credited, 2);
    }

    /**
     * Credit notes for orders refunded through the store, for what their invoice still shows. Run by a task.
     *
     * @return int Credit notes issued.
     */
    public static function credit_refunded_orders(): int {
        global $DB;

        $invoices = $DB->get_records_sql(
            "SELECT i.* FROM {local_oriskill_invoices} i
               JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
              WHERE i.type = 'invoice' AND o.status = 'refunded'");
        $issued = 0;
        foreach ($invoices as $invoice) {
            if (($left = self::creditable($invoice)) > 0) {
                self::issue_credit_note($invoice, $left, get_string('credit:refunded', 'local_oriskill'));
                $issued++;
            }
        }
        return $issued;
    }

    /**
     * Documents of a trainer, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function documents(\stdClass $creator): array {
        global $DB;

        return $DB->get_records('local_oriskill_invoices', ['creatorid' => $creator->id], 'timecreated DESC, id DESC');
    }

    /**
     * Whether the current user may open a document: its buyer, its trainer or an administrator.
     *
     * @param \stdClass $document Invoice or credit note.
     * @return bool
     */
    public static function can_view(\stdClass $document): bool {
        global $DB, $USER;

        if ((int) $document->userid === (int) $USER->id
                || has_capability('local/moderncommerce:viewallorders', \context_system::instance())) {
            return true;
        }
        return $DB->record_exists('local_oriskill_creators', ['id' => $document->creatorid, 'userid' => $USER->id]);
    }

    /**
     * Address of a document.
     *
     * @param \stdClass $document Invoice or credit note.
     * @return moodle_url
     */
    public static function url(\stdClass $document): moodle_url {
        return new moodle_url('/local/oriskill/invoice.php', ['id' => $document->id]);
    }

    /**
     * Number the document in the trainer's sequence and store it with its snapshot.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $order Order.
     * @param string $type invoice or credit.
     * @param \stdClass|null $parent Invoice a credit note cancels.
     * @param float $total Amount including VAT.
     * @param float $tax VAT.
     * @param array $content lines, discount, paid, mentions.
     * @return \stdClass
     */
    private static function issue(\stdClass $creator, \stdClass $order, string $type, ?\stdClass $parent, float $total,
            float $tax, array $content): \stdClass {
        global $DB;

        $buyer = $DB->get_record('user', ['id' => $order->userid], '*', MUST_EXIST);
        $billing = json_decode((string) ($order->billingaddress ?? ''), true) ?: [];
        $country = $billing['country'] ?? $buyer->country;
        $country = get_string_manager()->get_list_of_countries()[$country] ?? $country;
        $data = $content + [
            'seller' => [
                'name' => trim((string) $creator->legalname) ?: format_string($creator->displayname),
                'brand' => format_string($creator->displayname),
                'address' => (string) $creator->legaladdress,
                'siret' => (string) $creator->siret,
                'vatnumber' => (string) $creator->vatnumber,
                'vatrate' => (float) $creator->vatrate,
                'notes' => (string) $creator->invoicenotes,
                'email' => (string) $DB->get_field('user', 'email', ['id' => $creator->userid]),
            ],
            'buyer' => [
                'name' => fullname($buyer),
                'email' => $buyer->email,
                'address' => trim(implode("\n", array_filter([$billing['address'] ?? $buyer->address,
                    trim(($billing['zipcode'] ?? '') . ' ' . ($billing['city'] ?? $buyer->city)),
                    $country]))),
            ],
            'ordernumber' => $order->ordernumber,
            'parentnumber' => $parent->number ?? null,
            'parentdate' => $parent->timecreated ?? null,
        ];
        $data['vatrate'] = $content['vatrate'] ?? $data['seller']['vatrate'];

        // The sequence must have no gap and no duplicate, even when two payments are notified at once.
        $lock = \core\lock\lock_config::get_lock_factory('local_oriskill_invoices')
            ->get_lock('creator' . $creator->id, 30);
        if (!$lock) {
            throw new \moodle_exception('locktimeout');
        }
        try {
            $sequence = 1 + (int) $DB->get_field_sql("SELECT MAX(sequence) FROM {local_oriskill_invoices}
                                                       WHERE creatorid = ? AND type = ?", [$creator->id, $type]);
            $record = (object) [
                'creatorid' => $creator->id,
                'orderid' => $order->id,
                'userid' => $order->userid,
                'type' => $type,
                'sequence' => $sequence,
                'number' => self::PREFIX[$type] . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'parentid' => $parent->id ?? null,
                'total' => $total,
                'tax' => $tax,
                'currency' => $order->currency,
                'data' => json_encode($data),
                'timecreated' => time(),
            ];
            $record->id = $DB->insert_record('local_oriskill_invoices', $record);
        } finally {
            $lock->release();
        }
        return $record;
    }

    /**
     * PDF of a document.
     *
     * @param \stdClass $document Invoice or credit note.
     * @return array [file name, PDF content]
     */
    public static function pdf(\stdClass $document): array {
        $data = json_decode($document->data, true);
        $seller = $data['seller'];
        $currency = $document->currency;
        $money = fn(float $value) => pricing_service::format_price($value, $currency);
        $iscredit = $document->type === 'credit';
        $title = get_string($iscredit ? 'credit:title' : 'invoice:title', 'local_oriskill');

        $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator($seller['brand']);
        $pdf->SetAuthor($seller['name']);
        $pdf->SetTitle($title . ' ' . $document->number);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        // Trainer's logo, then the seller on the left and the document on the right.
        $creator = (object) ['id' => $document->creatorid];
        $y = 15;
        if ($logo = self::logo_file($creator)) {
            $path = $logo->copy_content_to_temp();
            $pdf->Image($path, 15, 15, 0, 18, '', '', '', true, 300, '', false, false, 0, 'LT');
            @unlink($path);
            $y = 37;
        }
        $pdf->SetFont('freesans', 'B', 20);
        $pdf->SetXY(110, 15);
        $pdf->Cell(85, 10, \core_text::strtoupper($title), 0, 1, 'R');
        $pdf->SetFont('freesans', '', 10);
        foreach ([
            get_string('invoice:number', 'local_oriskill') . ' ' . $document->number,
            get_string('invoice:date', 'local_oriskill') . ' ' . userdate($document->timecreated, get_string('strftimedate')),
            get_string('invoice:order', 'local_oriskill') . ' ' . $data['ordernumber'],
        ] as $line) {
            $pdf->SetX(110);
            $pdf->Cell(85, 5, $line, 0, 1, 'R');
        }
        if ($iscredit && $data['parentnumber']) {
            $pdf->SetX(110);
            $pdf->Cell(85, 5, get_string('credit:ofinvoice', 'local_oriskill', (object) ['number' => $data['parentnumber'],
                'date' => userdate($data['parentdate'], get_string('strftimedate'))]), 0, 1, 'R');
        }

        $pdf->SetXY(15, max($y, 45));
        $pdf->SetFont('freesans', 'B', 11);
        $pdf->Cell(90, 6, $seller['name'], 0, 1);
        $pdf->SetFont('freesans', '', 9);
        $sellerlines = array_filter([
            $seller['brand'] !== $seller['name'] ? $seller['brand'] : '',
            $seller['address'],
            $seller['siret'] ? get_string('invoice:siret', 'local_oriskill', $seller['siret']) : '',
            $seller['vatnumber'] ? get_string('invoice:vatnumber', 'local_oriskill', $seller['vatnumber']) : '',
            $seller['email'],
        ]);
        $pdf->MultiCell(90, 4.5, implode("\n", $sellerlines), 0, 'L', false, 1);

        $pdf->SetXY(115, max($y, 45));
        $pdf->SetFont('freesans', 'B', 10);
        $pdf->Cell(80, 6, get_string('invoice:billedto', 'local_oriskill'), 0, 2);
        $pdf->SetFont('freesans', '', 9);
        $pdf->MultiCell(80, 4.5, implode("\n", array_filter([$data['buyer']['name'], $data['buyer']['address'],
            $data['buyer']['email']])), 0, 'L', false, 1, 115);
        $pdf->SetY(max($pdf->GetY(), 95));

        // Lines.
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('freesans', 'B', 9);
        $pdf->Cell(100, 7, get_string('invoice:description', 'local_oriskill'), 1, 0, 'L', true);
        $pdf->Cell(20, 7, get_string('invoice:quantity', 'local_oriskill'), 1, 0, 'C', true);
        $pdf->Cell(30, 7, get_string('invoice:unitprice', 'local_oriskill'), 1, 0, 'R', true);
        $pdf->Cell(30, 7, get_string('invoice:linetotal', 'local_oriskill'), 1, 1, 'R', true);
        $pdf->SetFont('freesans', '', 9);
        foreach ($data['lines'] as $line) {
            $pdf->MultiCell(100, 7, $line['name'], 1, 'L', false, 0);
            $pdf->Cell(20, 7, format_float($line['quantity'], 0), 1, 0, 'C');
            $pdf->Cell(30, 7, $money($line['unitprice']), 1, 0, 'R');
            $pdf->Cell(30, 7, $money($line['total']), 1, 1, 'R');
        }
        $pdf->Ln(3);

        // Totals: excluding VAT, VAT, including VAT.
        $total = (float) $document->total;
        $tax = (float) $document->tax;
        $sign = $iscredit ? '-' : '';
        $rows = [];
        if (!empty($data['discount'])) {
            $rows[] = [get_string('invoice:discount', 'local_oriskill'), '-' . $money($data['discount'])];
        }
        if ($data['vatrate'] > 0) {
            $rows[] = [get_string('invoice:totalexcl', 'local_oriskill'), $sign . $money($total - $tax)];
            $rows[] = [get_string('invoice:vat', 'local_oriskill', format_float($data['vatrate'], 2, true, true)),
                $sign . $money($tax)];
        }
        foreach ($rows as [$label, $value]) {
            $pdf->SetX(105);
            $pdf->Cell(60, 6, $label, 0, 0, 'R');
            $pdf->Cell(30, 6, $value, 0, 1, 'R');
        }
        $pdf->SetFont('freesans', 'B', 11);
        $pdf->SetX(105);
        $pdf->Cell(60, 8, get_string($data['vatrate'] > 0 ? 'invoice:totalincl' : 'invoice:total', 'local_oriskill'),
            0, 0, 'R');
        $pdf->Cell(30, 8, $sign . $money($total), 0, 1, 'R');
        if (!empty($data['paid'])) {
            $pdf->SetFont('freesans', 'B', 9);
            $pdf->SetTextColor(0, 128, 0);
            $pdf->Cell(0, 6, get_string('invoice:paid', 'local_oriskill'), 0, 1, 'R');
            $pdf->SetTextColor(0, 0, 0);
        }

        // Mentions.
        $pdf->Ln(6);
        $pdf->SetFont('freesans', '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $mentions = $data['mentions'];
        if ($data['vatrate'] <= 0) {
            $mentions[] = get_string('invoice:novat', 'local_oriskill');
        }
        if ($seller['notes'] !== '') {
            $mentions[] = $seller['notes'];
        }
        $pdf->MultiCell(0, 4, implode("\n", $mentions), 0, 'L');
        $pdf->SetTextColor(0, 0, 0);

        return [$document->number . '.pdf', $pdf->Output($document->number . '.pdf', 'S')];
    }

    /**
     * Trainer's logo file, if any.
     *
     * @param \stdClass $creator Trainer (id).
     * @return \stored_file|null
     */
    private static function logo_file(\stdClass $creator): ?\stored_file {
        $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'local_oriskill', 'logo',
            $creator->id, 'filename', false);
        foreach ($files as $file) {
            // TCPDF reads raster images; an SVG logo is left out.
            if (in_array($file->get_mimetype(), ['image/png', 'image/jpeg'], true)) {
                return $file;
            }
        }
        return null;
    }
}
