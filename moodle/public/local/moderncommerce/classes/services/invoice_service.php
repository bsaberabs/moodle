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

namespace local_moderncommerce\services;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/pdflib.php');

use pdf;
use local_moderncommerce\api\order_api;
use local_moderncommerce\services\pricing_service;
/**
 * PDF Invoice/Receipt Generator Service
 *
 * Generates PDF invoices and receipts for orders.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invoice_service {
    /** @var pdf The PDF document */
    protected $pdf;

    /** @var object The order */
    protected $order;

    /** @var array Order items */
    protected $items;

    /** @var object User info */
    protected $user;

    /** @var string Document type: 'invoice' or 'receipt' */
    protected $doctype;

    /**
     * Generate invoice PDF for an order.
     *
     * @param int $orderid Order ID
     * @param string $type Document type: 'invoice' or 'receipt'
     * @return string PDF content
     */
    public static function generate($orderid, $type = 'invoice') {
        global $DB;

        $order = order_api::get_order((int) $orderid);
        $items = order_api::get_order_items((int) $orderid);
        $user = $DB->get_record('user', ['id' => $order->userid], '*', MUST_EXIST);
        $service = new self();
        return $service->generate_pdf($order, $items, $user, $type);
    }

    /**
     * Generate and output PDF directly to browser.
     *
     * @param int $orderid Order ID
     * @param string $type Document type: 'invoice' or 'receipt'
     */
    public static function download($orderid, $type = 'invoice') {
        global $DB;

        $order = order_api::get_order((int) $orderid);
        $items = order_api::get_order_items((int) $orderid);
        $user = $DB->get_record('user', ['id' => $order->userid], '*', MUST_EXIST);
        $service = new self();
        $service->generate_pdf($order, $items, $user, $type, true);
    }

    /**
     * Generate and output a manual invoice PDF directly to browser.
     *
     * @param int $invoiceid Manual invoice ID.
     * @return void
     */
    public static function download_manual_invoice(int $invoiceid): void {
        global $DB;

        $invoice = $DB->get_record('local_moderncommerce_invoices', ['id' => $invoiceid], '*', MUST_EXIST);
        $items = $DB->get_records(
            'local_moderncommerce_invoice_items',
            ['invoiceid' => $invoiceid],
            'id ASC'
        );
        $user = $DB->get_record('user', ['id' => $invoice->userid], '*', MUST_EXIST);
        $service = new self();
        $service->generate_pdf(
            self::invoice_to_order_adapter($invoice),
            self::invoice_items_to_order_items($items),
            $user,
            'invoice',
            true
        );
    }

    /**
     * Adapt a manual invoice record to the order-shaped object used by the PDF renderer.
     *
     * @param \stdClass $invoice Invoice record.
     * @return \stdClass
     */
    private static function invoice_to_order_adapter(\stdClass $invoice): \stdClass {
        $order = clone $invoice;
        $order->id = (int) $invoice->id;
        $order->ordernumber = (string) $invoice->invoicenumber;
        $order->invoicenumber = (string) $invoice->invoicenumber;
        $order->discount = 0;
        $order->timecreated = (int) ($invoice->issuedat ?: $invoice->timecreated);
        $order->billingaddress = '';

        return $order;
    }

    /**
     * Adapt manual invoice items to the order item shape used by the PDF renderer.
     *
     * @param array $items Invoice item records.
     * @return array
     */
    private static function invoice_items_to_order_items(array $items): array {
        $out = [];
        foreach ($items as $item) {
            $out[] = (object) [
                'coursename' => (string) $item->description,
                'quantity' => (float) $item->quantity,
                'unitprice' => (float) $item->unitprice,
                'price' => (float) $item->unitprice,
                'linetotal' => (float) $item->total,
                'bundleid' => 0,
            ];
        }

        return $out;
    }

    /**
     * Generate PDF document.
     *
     * @param object $order Order record
     * @param array $items Order items
     * @param object $user User record
     * @param string $type Document type
     * @param bool $download Whether to output directly
     * @return string|void PDF content or void if downloading
     */
    protected function generate_pdf($order, $items, $user, $type = 'invoice', $download = false) {
        global $CFG, $SITE;

        $this->order = $order;
        $this->items = $items;
        $this->user = $user;
        $this->doctype = $type;

        // Create new PDF.
        $this->pdf = new pdf('P', 'mm', 'A4', true, 'UTF-8');

        // Set document information.
        $title = $type === 'receipt'
            ? get_string('receipt', 'local_moderncommerce')
            : get_string('invoice', 'local_moderncommerce');

        $this->pdf->SetCreator($SITE->fullname);
        $this->pdf->SetAuthor($SITE->fullname);
        $this->pdf->SetTitle($title . ' - ' . $order->ordernumber);
        $this->pdf->SetSubject($title);

        // Set margins.
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 25);

        // Remove default header/footer.
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        // Add a page.
        $this->pdf->AddPage();

        // Build content.
        $this->add_header();
        $this->add_billing_info();
        $this->add_order_items();
        $this->add_totals();
        $this->add_footer();

        // Output.
        $filename = strtolower($type) . '_' . $order->ordernumber . '.pdf';

        if ($download) {
            $this->pdf->Output($filename, 'D');
            exit;
        }

        return $this->pdf->Output($filename, 'S');
    }

    /**
     * Add document header with logo and company info.
     */
    protected function add_header() {

        global $SITE;
        $title = $this->doctype === 'receipt'
            ? get_string('receipt', 'local_moderncommerce')
            : get_string('invoice', 'local_moderncommerce');
        $settings = commerce_settings_service::get_admin_settings();
        $businessname = $settings->businessname ?: format_string($SITE->fullname);
        // Company logo (if available).
        $logo = get_config('local_moderncommerce', 'invoice_logo');
        if (!empty($logo)) {
            // Get logo from file storage.
            $fs = get_file_storage();
            $context = \context_system::instance();
            $files = $fs->get_area_files($context->id, 'local_moderncommerce', 'invoicelogo', 0, 'id DESC', false);
            if ($files) {
                $file = reset($files);
                $logopath = $file->copy_content_to_temp();
                $this->pdf->Image($logopath, 15, 15, 40, 0, '', '', '', true);
                @unlink($logopath);
                $this->pdf->SetY(35);
            }
        } else {
            $this->pdf->SetY(15);
        }

        // Document title.
        $this->pdf->SetFont('freesans', 'B', 20);
        $this->pdf->Cell(0, 10, strtoupper($title), 0, 1, 'R');

        // Document number.
        $this->pdf->SetFont('freesans', '', 10);
        $docnum = $this->doctype === 'receipt'
            ? $this->order->ordernumber
            : ($this->order->invoicenumber ?? $this->order->ordernumber);
        $this->pdf->Cell(0, 6, get_string('documentnumber', 'local_moderncommerce') . ': ' . $docnum, 0, 1, 'R');

        // Date.
        $this->pdf->Cell(
            0,
            6,
            get_string('date', 'local_moderncommerce') . ': ' .
                userdate($this->order->timecreated, get_string('strftimedate')),
            0,
            1,
            'R'
        );

        if ($this->doctype === 'invoice' && !empty($this->order->duedate)) {
            $this->pdf->Cell(
                0,
                6,
                get_string('duedate', 'local_moderncommerce') . ': ' .
                    userdate((int) $this->order->duedate, get_string('strftimedate')),
                0,
                1,
                'R'
            );
        }

        // Order number (if different from invoice number).
        if (
            $this->doctype === 'invoice'
            && !empty($this->order->invoicenumber)
            && $this->order->invoicenumber !== $this->order->ordernumber
        ) {
            $this->pdf->Cell(0, 6, get_string('ordernumber', 'local_moderncommerce') . ': ' . $this->order->ordernumber, 0, 1, 'R');
        }

        // Company info (left side).
        $this->pdf->SetY(50);
        $this->pdf->SetFont('freesans', 'B', 12);
        $this->pdf->Cell(0, 6, $businessname, 0, 1, 'L');
        $companyaddress = get_config('local_moderncommerce', 'company_address');
        if ($companyaddress) {
            $this->pdf->SetFont('freesans', '', 10);
            $this->pdf->MultiCell(90, 5, $companyaddress, 0, 'L', false, 1);
        }

        $companyemail = get_config('local_moderncommerce', 'company_email') ?: $settings->supportemail;
        if ($companyemail) {
            $this->pdf->Cell(90, 5, get_string('email') . ': ' . $companyemail, 0, 1, 'L');
        }
        $this->pdf->Ln(5);
    }

    /**
     * Add billing information section.
     */
    protected function add_billing_info() {
        $this->pdf->SetFont('freesans', 'B', 11);
        $this->pdf->Cell(0, 8, get_string('billedto', 'local_moderncommerce'), 0, 1, 'L');

        $this->pdf->SetFont('freesans', '', 10);
        $this->pdf->Cell(0, 5, fullname($this->user), 0, 1, 'L');
        $this->pdf->Cell(0, 5, $this->user->email, 0, 1, 'L');

        // Billing address from order if available.
        if (!empty($this->order->billingaddress)) {
            $billing = json_decode($this->order->billingaddress);
            if ($billing) {
                if (!empty($billing->address)) {
                    $this->pdf->Cell(0, 5, $billing->address, 0, 1, 'L');
                }
                $citystate = [];
                if (!empty($billing->city)) {
                    $citystate[] = $billing->city;
                }
                if (!empty($billing->state)) {
                    $citystate[] = $billing->state;
                }
                if (!empty($billing->zipcode)) {
                    $citystate[] = $billing->zipcode;
                }
                if ($citystate) {
                    $this->pdf->Cell(0, 5, implode(', ', $citystate), 0, 1, 'L');
                }
                if (!empty($billing->country)) {
                    $this->pdf->Cell(0, 5, $billing->country, 0, 1, 'L');
                }
            }
        }

        $this->pdf->Ln(10);
    }

    /**
     * Add order items table.
     */
    protected function add_order_items() {
        // Table header.
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->SetFont('freesans', 'B', 10);

        $this->pdf->Cell(90, 8, get_string('description', 'local_moderncommerce'), 1, 0, 'L', true);
        $this->pdf->Cell(25, 8, get_string('quantity', 'local_moderncommerce'), 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, get_string('unitprice', 'local_moderncommerce'), 1, 0, 'R', true);
        $this->pdf->Cell(35, 8, get_string('amount', 'local_moderncommerce'), 1, 1, 'R', true);

        // Table body.
        $this->pdf->SetFont('freesans', '', 10);

        foreach ($this->items as $item) {
            if (!empty($item->bundleid)) {
                $name = $item->bundlename ?: ($item->coursename ?? get_string('bundle', 'local_moderncommerce'));
            } else {
                $name = $item->coursename ?? get_string('course');
            }
            $qty = $item->quantity ?? 1;
            $unitprice = $item->unitprice ?? $item->price ?? 0;
            $linetotal = $item->linetotal ?? ($unitprice * $qty);

            $this->pdf->Cell(90, 7, $name, 1, 0, 'L');
            $this->pdf->Cell(25, 7, $qty, 1, 0, 'C');
            $this->pdf->Cell(30, 7, pricing_service::format_order_price($unitprice, $this->order), 1, 0, 'R');
            $this->pdf->Cell(35, 7, pricing_service::format_order_price($linetotal, $this->order), 1, 1, 'R');
        }

        $this->pdf->Ln(5);
    }

    /**
     * Add totals section.
     */
    protected function add_totals() {
        $rightcol = 145;
        $labelwidth = 25;
        $valuewidth = 35;

        $this->pdf->SetFont('freesans', '', 10);

        // Subtotal.
        $this->pdf->SetX($rightcol);
        $this->pdf->Cell($labelwidth, 6, get_string('subtotal', 'local_moderncommerce') . ':', 0, 0, 'R');
        $subtotal = $this->order->subtotal ?? $this->order->total;
        $this->pdf->Cell($valuewidth, 6, pricing_service::format_order_price($subtotal, $this->order), 0, 1, 'R');
        // Discount if applicable.
        if (!empty($this->order->discount) && $this->order->discount > 0) {
            $this->pdf->SetX($rightcol);
            $this->pdf->Cell($labelwidth, 6, get_string('discount', 'local_moderncommerce') . ':', 0, 0, 'R');
            $this->pdf->Cell(
                $valuewidth,
                6,
                '-' . pricing_service::format_order_price($this->order->discount, $this->order),
                0,
                1,
                'R'
            );
        }

        // Tax if applicable.
        if (!empty($this->order->tax) && $this->order->tax > 0) {
            $this->pdf->SetX($rightcol);
            $this->pdf->Cell($labelwidth, 6, get_string('tax', 'local_moderncommerce') . ':', 0, 0, 'R');
            $this->pdf->Cell($valuewidth, 6, pricing_service::format_order_price($this->order->tax, $this->order), 0, 1, 'R');
        }

        // Total.
        $this->pdf->SetFont('freesans', 'B', 11);
        $this->pdf->SetX($rightcol);
        $this->pdf->Cell($labelwidth, 8, get_string('total', 'local_moderncommerce') . ':', 0, 0, 'R');
        $this->pdf->Cell($valuewidth, 8, pricing_service::format_order_price($this->order->total, $this->order), 0, 1, 'R');
        // Payment status.
        if ($this->doctype === 'receipt' && $this->order->status === 'paid') {
            $this->pdf->Ln(3);
            $this->pdf->SetFont('freesans', 'B', 10);
            $this->pdf->SetTextColor(0, 128, 0);
            $this->pdf->Cell(0, 8, get_string('paid', 'local_moderncommerce'), 0, 1, 'R');
            $this->pdf->SetTextColor(0, 0, 0);
        }
    }

    /**
     * Add footer with payment info and notes.
     */
    protected function add_footer() {
        $this->pdf->Ln(15);

        // Payment information.
        if ($this->doctype === 'invoice' && $this->order->status !== 'paid') {
            $paymentinfo = get_config('local_moderncommerce', 'invoice_payment_info');
            if ($paymentinfo) {
                $this->pdf->SetFont('freesans', 'B', 10);
                $this->pdf->Cell(0, 6, get_string('paymentinstructions', 'local_moderncommerce'), 0, 1, 'L');
                $this->pdf->SetFont('freesans', '', 9);
                $this->pdf->MultiCell(0, 5, $paymentinfo, 0, 'L');
                $this->pdf->Ln(5);
            }
        }

        // Terms and conditions.
        if (!empty($this->order->notes)) {
            $this->pdf->SetFont('freesans', 'B', 10);
            $this->pdf->Cell(0, 6, get_string('notes', 'local_moderncommerce'), 0, 1, 'L');
            $this->pdf->SetFont('freesans', '', 9);
            $this->pdf->MultiCell(0, 5, (string) $this->order->notes, 0, 'L');
            $this->pdf->Ln(5);
        }

        $terms = !empty($this->order->terms)
            ? (string) $this->order->terms
            : get_config('local_moderncommerce', 'invoice_terms');
        if ($terms) {
            $this->pdf->SetFont('freesans', 'I', 8);
            $this->pdf->SetTextColor(100, 100, 100);
            $this->pdf->MultiCell(0, 4, $terms, 0, 'L');
            $this->pdf->SetTextColor(0, 0, 0);
        }

        // Transaction ID (for receipts).
        if ($this->doctype === 'receipt') {
            global $DB;
            $transaction = false;
            if ($DB->get_manager()->table_exists('local_moderncommerce_payment_attempts')) {
                $transaction = $DB->get_record(
                    'local_moderncommerce_payment_attempts',
                    ['orderid' => $this->order->id, 'status' => 'success'],
                    '*',
                    IGNORE_MULTIPLE
                );
            } else if ($DB->get_manager()->table_exists('local_moderncommerce_transactions')) {
                $transaction = $DB->get_record(
                    'local_moderncommerce_transactions',
                    ['orderid' => $this->order->id, 'status' => 'success'],
                    '*',
                    IGNORE_MULTIPLE
                );
            }
            $transactionid = $transaction->gatewaytransactionid ?? ($transaction->transactionid ?? '');
            if ($transaction && !empty($transactionid)) {
                $this->pdf->Ln(3);
                $this->pdf->SetFont('freesans', '', 8);
                $this->pdf->Cell(0, 5, get_string('transactionid', 'local_moderncommerce') . ': ' . $transactionid, 0, 1, 'L');
            }
        }
    }
}
