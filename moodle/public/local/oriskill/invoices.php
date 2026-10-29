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

/**
 * Trainer's invoices and credit notes, and what their invoices state about them.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\billing_service;
use local_oriskill\creator_service;
use local_oriskill\form\billing_form;
use local_secureandpay\refund_service;

$url = new moodle_url('/local/oriskill/invoices.php');
$creator = creator_service::require_current_creator($url, 'menu:invoices');

// Refund through SecureAndPay of an order of one of the trainer's invoices.
if (optional_param('action', '', PARAM_ALPHA) === 'refund' && confirm_sesskey()) {
    $invoice = $DB->get_record('local_oriskill_invoices', ['id' => required_param('invoiceid', PARAM_INT),
        'creatorid' => $creator->id, 'type' => 'invoice'], '*', MUST_EXIST);
    $order = $DB->get_record('local_moderncommerce_orders', ['id' => $invoice->orderid], '*', MUST_EXIST);
    $amount = (float) unformat_float(required_param('amount', PARAM_RAW_TRIMMED), true);
    try {
        refund_service::request($order, $amount, trim(optional_param('reason', '', PARAM_TEXT)));
        \local_oriskill\log_service::add($creator, 'refund_requested', ['amount' =>
            pricing_service::format_price($amount, $order->currency), 'invoice' => $invoice->number], null,
            (int) $invoice->userid);
        redirect($url, get_string('refund:requested', 'local_oriskill'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Credit note on one of the trainer's invoices.
if (optional_param('action', '', PARAM_ALPHA) === 'credit' && confirm_sesskey()) {
    $invoice = $DB->get_record('local_oriskill_invoices', ['id' => required_param('invoiceid', PARAM_INT),
        'creatorid' => $creator->id, 'type' => 'invoice'], '*', MUST_EXIST);
    $amount = unformat_float(required_param('amount', PARAM_RAW_TRIMMED), true);
    try {
        $credit = billing_service::issue_credit_note($invoice, (float) $amount,
            trim(optional_param('reason', '', PARAM_TEXT)));
        \local_oriskill\log_service::add($creator, 'credit_issued', ['number' => $credit->number,
            'invoice' => $invoice->number], null, (int) $invoice->userid);
        redirect($url, get_string('credit:issued', 'local_oriskill', $credit->number), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$form = new billing_form($url);
$form->set_data([
    'legalname' => $creator->legalname,
    'legaladdress' => $creator->legaladdress,
    'siret' => $creator->siret,
    'vatnumber' => $creator->vatnumber,
    'vatrate' => (string) (float) $creator->vatrate,
    'invoicenotes' => $creator->invoicenotes,
]);
if ($data = $form->get_data()) {
    billing_service::save_details($creator, $data);
    \local_oriskill\log_service::add($creator, 'billing_saved');
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/invoices.php', get_string('menu:invoices', 'local_oriskill'));
echo html_writer::tag('p', get_string('invoices:intro', 'local_oriskill'));

// Documents.
$documents = billing_service::documents($creator);
echo html_writer::start_div('oriskill-card mb-4');
echo html_writer::tag('h3', get_string('invoices:documents', 'local_oriskill'), ['class' => 'h5']);
if (!$documents) {
    echo html_writer::tag('p', get_string('invoices:none', 'local_oriskill'), ['class' => 'text-muted mb-0']);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle oriskill-documents';
    $table->head = [get_string('invoice:number', 'local_oriskill'), get_string('invoices:client', 'local_oriskill'), get_string('invoice:order', 'local_oriskill'),
        get_string('invoice:total', 'local_oriskill'), ''];
    foreach ($documents as $document) {
        $data = json_decode($document->data, true);
        $iscredit = $document->type === 'credit';
        $actions = html_writer::link(billing_service::url($document),
            '<i class="fa fa-download me-1" aria-hidden="true"></i>PDF', ['class' => 'btn btn-sm btn-outline-secondary']);
        if (!$iscredit && ($left = billing_service::creditable($document)) > 0) {
            $actions .= ' ' . html_writer::tag('details',
                html_writer::tag('summary', get_string('credit:issue', 'local_oriskill'), ['class' => 'btn btn-sm btn-link']) .
                html_writer::tag('form',
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'credit']) .
                    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'invoiceid', 'value' => $document->id]) .
                    html_writer::tag('label', get_string('credit:amount', 'local_oriskill'), ['class' => 'form-label small']) .
                    html_writer::empty_tag('input', ['type' => 'text', 'name' => 'amount', 'inputmode' => 'decimal',
                        'class' => 'form-control form-control-sm mb-1', 'value' => format_float($left, 2, true)]) .
                    html_writer::tag('label', get_string('credit:reason', 'local_oriskill'), ['class' => 'form-label small']) .
                    html_writer::empty_tag('input', ['type' => 'text', 'name' => 'reason', 'maxlength' => 200,
                        'class' => 'form-control form-control-sm mb-2']) .
                    html_writer::tag('button', get_string('credit:confirm', 'local_oriskill'),
                        ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']),
                    ['method' => 'post', 'action' => $url->out(false), 'class' => 'mt-2', 'style' => 'max-width: 16rem',
                     'onsubmit' => 'return confirm(' . json_encode(get_string('credit:confirmtext', 'local_oriskill')) . ')']),
                ['class' => 'd-inline-block']);
        }
        // Refund through SecureAndPay, for an order it took, and the refunds already asked.
        if (!$iscredit && ($order = $DB->get_record('local_moderncommerce_orders', ['id' => $document->orderid]))) {
            $refundable = refund_service::refundable($order);
            if ($refundable > 0) {
                $actions .= ' ' . html_writer::tag('details',
                    html_writer::tag('summary', get_string('refund:action', 'local_oriskill'), ['class' => 'btn btn-sm btn-link']) .
                    html_writer::tag('form',
                        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'refund']) .
                        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'invoiceid', 'value' => $document->id]) .
                        html_writer::tag('label', get_string('credit:amount', 'local_oriskill'), ['class' => 'form-label small']) .
                        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'amount', 'inputmode' => 'decimal',
                            'class' => 'form-control form-control-sm mb-1', 'value' => format_float($refundable, 2, true)]) .
                        html_writer::tag('label', get_string('credit:reason', 'local_oriskill'), ['class' => 'form-label small']) .
                        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'reason', 'maxlength' => 200,
                            'class' => 'form-control form-control-sm mb-2']) .
                        html_writer::div(get_string('refund:help', 'local_oriskill'), 'small text-muted mb-2') .
                        html_writer::tag('button', get_string('refund:confirm', 'local_oriskill'),
                            ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']),
                        ['method' => 'post', 'action' => $url->out(false), 'class' => 'mt-2', 'style' => 'max-width: 18rem',
                         'onsubmit' => 'return confirm(' . json_encode(get_string('refund:confirmtext', 'local_oriskill')) . ')']),
                    ['class' => 'd-inline-block']);
            }
            foreach (refund_service::requests($order) as $request) {
                $actions .= html_writer::div(get_string('refund:status_' . $request->status, 'local_oriskill',
                    pricing_service::format_price((float) $request->amount, $request->currency)) .
                    ($request->status === 'refused' ? ' (' . s($request->answer) . ')' : ''), 'small text-muted w-100');
            }
        }
        $table->data[] = [
            html_writer::span($document->number, 'fw-semibold') . ' ' .
                html_writer::span(get_string($iscredit ? 'credit:title' : 'invoice:title', 'local_oriskill'),
                    'oriskill-badge ' . ($iscredit ? 'is-warn' : '')) .
                ($iscredit && $data['parentnumber'] ? html_writer::div(get_string('credit:ofinvoiceshort', 'local_oriskill',
                    $data['parentnumber']), 'small text-muted') : '') .
                html_writer::div(userdate($document->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                    'small text-muted'),
            s($data['buyer']['name']) . html_writer::div(s($data['buyer']['email']), 'small text-muted'),
            s($data['ordernumber']),
            ($iscredit ? '-' : '') . pricing_service::format_price((float) $document->total, $document->currency),
            html_writer::div($actions, 'd-flex flex-wrap align-items-start gap-1'),
        ];
    }
    echo html_writer::table($table);
}
echo html_writer::end_div();

// What the invoices state about the trainer.
echo html_writer::start_div('oriskill-card');
echo html_writer::tag('h3', get_string('billing:title', 'local_oriskill'), ['class' => 'h5']);
if (!billing_service::details_complete($creator)) {
    echo $OUTPUT->notification(get_string('billing:incomplete', 'local_oriskill'), 'warning');
}
echo html_writer::tag('p', get_string('billing:intro', 'local_oriskill'), ['class' => 'text-muted']);
$form->display();
echo html_writer::end_div();

echo creator_service::space_end();
echo $OUTPUT->footer();
