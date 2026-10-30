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
use local_oriskill\team_service;
use local_secureandpay\refund_service;

$search = trim(optional_param('q', '', PARAM_TEXT));
$url = new moodle_url('/local/oriskill/invoices.php', $search !== '' ? ['q' => $search] : []);
$creator = creator_service::require_current_creator($url, 'menu:invoices');

// For the accountant: a period's journal and PDFs (zip), or the journal of every document (CSV).
if (($export = optional_param('export', '', PARAM_ALPHA)) !== '') {
    if ($export === 'period') {
        $from = make_timestamp(required_param('fromyear', PARAM_INT), required_param('frommonth', PARAM_INT), 1);
        $to = make_timestamp(required_param('toyear', PARAM_INT), required_param('tomonth', PARAM_INT) + 1, 1);
        if ($to <= $from) {
            redirect($url, get_string('journal:badperiod', 'local_oriskill'), null, \core\output\notification::NOTIFY_ERROR);
        }
        require_once($CFG->libdir . '/filelib.php');
        \local_oriskill\log_service::add($creator, 'accounting_exported', ['from' => userdate($from, '%m/%Y'),
            'to' => userdate($to - 1, '%m/%Y')]);
        send_file(billing_service::export_zip($creator, $from, $to), clean_filename(get_string('journal:filename',
            'local_oriskill') . '-' . userdate($from, '%Y-%m') . '-' . userdate($to - 1, '%Y-%m') . '.zip'), 0, 0, false, true,
            'application/zip');
    }
    require_once($CFG->libdir . '/csvlib.class.php');
    csv_export_writer::download_array(clean_filename(get_string('menu:invoices', 'local_oriskill') . '-' . date('Y-m-d')),
        billing_service::journal($creator, 0, time() + 1), 'semicolon');
    exit;
}

// Quotes: turn into an invoice once paid, cancel, send.
if (in_array($action = optional_param('action', '', PARAM_ALPHA), ['quoteinvoice', 'quotecancel', 'quotemail'], true)
        && confirm_sesskey()) {
    $quote = $DB->get_record('local_oriskill_quotes', ['id' => required_param('quoteid', PARAM_INT),
        'creatorid' => $creator->id], '*', MUST_EXIST);
    $quote->type = 'quote';
    if (!team_service::can(creator_service::current_role(), '/local/oriskill/document.php')) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    if ($action === 'quoteinvoice') {
        $invoice = billing_service::invoice_quote($quote, required_param('method', PARAM_ALPHA));
        $message = get_string('quote:invoiced', 'local_oriskill', (object) ['quote' => $quote->number,
            'invoice' => $invoice->number]);
    } else if ($action === 'quotecancel') {
        billing_service::cancel_quote($quote);
        $message = get_string('quote:cancelled', 'local_oriskill', $quote->number);
    } else {
        $sent = billing_service::mail_invoice($quote, true);
        redirect($url, get_string($sent ? 'invoicemail:sent' : 'invoicemail:notsent', 'local_oriskill', $quote->number), null,
            $sent ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
    }
    redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

// E-mail a document to its buyer again, PDF attached.
if (optional_param('action', '', PARAM_ALPHA) === 'mail' && confirm_sesskey()) {
    $document = $DB->get_record('local_oriskill_invoices', ['id' => required_param('invoiceid', PARAM_INT),
        'creatorid' => $creator->id], '*', MUST_EXIST);
    $sent = billing_service::mail_invoice($document, true);
    redirect($url, get_string($sent ? 'invoicemail:sent' : 'invoicemail:notsent', 'local_oriskill', $document->number), null,
        $sent ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
}

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
    'invoicemail' => (int) $creator->invoicemail,
    'invoicemailsubject' => $creator->invoicemailsubject ?: get_string('invoicemail:defaultsubject', 'local_oriskill'),
    'invoicemailbody' => $creator->invoicemailbody ?: get_string('invoicemail:defaultbody', 'local_oriskill'),
]);
if ($data = $form->get_data()) {
    billing_service::save_details($creator, $data);
    \local_oriskill\log_service::add($creator, 'billing_saved');
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/invoices.php', get_string('menu:invoices', 'local_oriskill'));
echo html_writer::tag('p', get_string('invoices:intro', 'local_oriskill'));
$canissue = team_service::can(creator_service::current_role(), '/local/oriskill/document.php');
if ($canissue) {
    echo html_writer::div(
        html_writer::link(new moodle_url('/local/oriskill/document.php', ['type' => 'invoice']),
            '<i class="fa fa-plus me-1" aria-hidden="true"></i>' . get_string('document:new_invoice', 'local_oriskill'),
            ['class' => 'btn btn-primary']) .
        html_writer::link(new moodle_url('/local/oriskill/document.php', ['type' => 'quote']),
            '<i class="fa fa-file-signature me-1" aria-hidden="true"></i>' . get_string('document:new_quote', 'local_oriskill'),
            ['class' => 'btn btn-outline-primary']),
        'd-flex flex-wrap gap-2 mb-3');
}

// Documents, found by number or buyer.
$documents = billing_service::documents($creator);
if ($search !== '') {
    $needle = core_text::strtolower($search);
    $documents = array_filter($documents, function($document) use ($needle) {
        $buyer = json_decode($document->data, true)['buyer'];
        return str_contains(core_text::strtolower($document->number . ' ' . $buyer['name'] . ' ' . $buyer['email']), $needle);
    });
}
echo html_writer::start_div('oriskill-card mb-4');
echo html_writer::tag('h3', get_string('invoices:documents', 'local_oriskill'), ['class' => 'h5']);

// Search, export, accountant's documents.
$months = [];
for ($m = 1; $m <= 12; $m++) {
    $months[$m] = userdate(make_timestamp(2000, $m, 1), '%B');
}
$years = [];
for ($y = (int) date('Y'); $y >= 2025; $y--) {
    $years[$y] = $y;
}
$lastmonth = strtotime('first day of last month');
$plain = new moodle_url('/local/oriskill/invoices.php');
echo html_writer::div(
    html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $search,
            'class' => 'form-control form-control-sm w-auto', 'placeholder' => get_string('invoices:search', 'local_oriskill')]) .
        html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']),
        ['method' => 'get', 'action' => $plain->out(false), 'class' => 'd-flex gap-2']) .
    html_writer::link(new moodle_url($plain, ['export' => 'all']), '<i class="fa fa-download me-1" aria-hidden="true"></i>' .
        get_string('exportcsv', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary ms-auto']) .
    html_writer::tag('details',
        html_writer::tag('summary', '<i class="fa fa-file-zipper me-1" aria-hidden="true"></i>' .
            get_string('journal:title', 'local_oriskill'), ['class' => 'btn btn-sm btn-primary']) .
        html_writer::tag('form',
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'export', 'value' => 'period']) .
            html_writer::div(get_string('journal:help', 'local_oriskill'), 'small text-muted mb-2') .
            html_writer::div(
                html_writer::span(get_string('journal:from', 'local_oriskill'), 'small') .
                html_writer::select($months, 'frommonth', (int) date('n', $lastmonth), false,
                    ['class' => 'form-select form-select-sm w-auto', 'aria-label' => get_string('journal:from', 'local_oriskill')]) .
                html_writer::select($years, 'fromyear', (int) date('Y', $lastmonth), false,
                    ['class' => 'form-select form-select-sm w-auto', 'aria-label' => get_string('year')]), 'd-flex gap-1 align-items-center mb-1') .
            html_writer::div(
                html_writer::span(get_string('journal:to', 'local_oriskill'), 'small') .
                html_writer::select($months, 'tomonth', (int) date('n', $lastmonth), false,
                    ['class' => 'form-select form-select-sm w-auto', 'aria-label' => get_string('journal:to', 'local_oriskill')]) .
                html_writer::select($years, 'toyear', (int) date('Y', $lastmonth), false,
                    ['class' => 'form-select form-select-sm w-auto', 'aria-label' => get_string('year')]), 'd-flex gap-1 align-items-center mb-2') .
            html_writer::tag('button', get_string('journal:download', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']),
            ['method' => 'get', 'action' => $plain->out(false), 'class' => 'oriskill-card mt-2 position-absolute end-0',
                'style' => 'z-index: 5; min-width: 20rem']),
        ['class' => 'position-relative']),
    'd-flex flex-wrap gap-2 align-items-center mb-3');
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
            '<i class="fa fa-download me-1" aria-hidden="true"></i>PDF', ['class' => 'btn btn-sm btn-outline-secondary']) . ' ' .
            html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'mail']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'invoiceid', 'value' => $document->id]) .
                html_writer::tag('button', '<i class="fa fa-envelope" aria-hidden="true"></i>', ['type' => 'submit',
                    'class' => 'btn btn-sm btn-outline-secondary', 'title' => get_string('invoicemail:send', 'local_oriskill'),
                    'aria-label' => get_string('invoicemail:send', 'local_oriskill')]),
                ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']);
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
            $data['ordernumber'] !== '' ? s($data['ordernumber'])
                : html_writer::span(get_string('document:manual', 'local_oriskill'), 'small text-muted'),
            ($iscredit ? '-' : '') . pricing_service::format_price((float) $document->total, $document->currency),
            html_writer::div($actions, 'd-flex flex-wrap align-items-start gap-1'),
        ];
    }
    echo html_writer::table($table);
}
echo html_writer::end_div();

// Quotes.
$quotes = billing_service::quotes($creator);
if ($quotes) {
    $post = function(stdClass $quote, string $action, string $content, string $confirm = '') use ($url) {
        return html_writer::tag('form',
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'quoteid', 'value' => $quote->id]) . $content,
            ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline'] +
            ($confirm !== '' ? ['onsubmit' => 'return confirm(' . json_encode($confirm) . ')'] : []));
    };
    $methods = [];
    foreach (billing_service::METHODS as $method) {
        $methods[$method] = get_string('invoice:method_' . $method, 'local_oriskill');
    }
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle oriskill-documents';
    $table->head = [get_string('invoice:number', 'local_oriskill'), get_string('invoices:client', 'local_oriskill'),
        get_string('invoice:total', 'local_oriskill'), get_string('quote:state', 'local_oriskill'), ''];
    foreach ($quotes as $quote) {
        $data = json_decode($quote->data, true);
        $badge = ['open' => '', 'expired' => 'is-warn', 'invoiced' => 'is-good', 'cancelled' => ''][$quote->state];
        $state = html_writer::span(get_string('quote:state_' . $quote->state, 'local_oriskill'), 'oriskill-badge ' . $badge);
        if ($quote->state === 'invoiced' && ($invoice = $DB->get_record('local_oriskill_invoices', ['id' => $quote->invoiceid]))) {
            $state .= html_writer::div(get_string('quote:invoicedas', 'local_oriskill', $invoice->number), 'small text-muted');
        } else if ($quote->status === 'open') {
            $state .= html_writer::div(get_string('quote:validuntil', 'local_oriskill',
                userdate($quote->validuntil, get_string('strftimedate'))), 'small text-muted');
        }
        $actions = html_writer::link(billing_service::url($quote), '<i class="fa fa-download me-1" aria-hidden="true"></i>PDF',
            ['class' => 'btn btn-sm btn-outline-secondary']);
        if ($canissue && $quote->status === 'open') {
            $actions .= ' ' . $post($quote, 'quotemail', html_writer::tag('button',
                '<i class="fa fa-envelope" aria-hidden="true"></i>', ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary',
                'title' => get_string('invoicemail:send', 'local_oriskill'), 'aria-label' => get_string('invoicemail:send', 'local_oriskill')]));
            $actions .= ' ' . html_writer::tag('details',
                html_writer::tag('summary', get_string('quote:toinvoice', 'local_oriskill'), ['class' => 'btn btn-sm btn-link']) .
                $post($quote, 'quoteinvoice',
                    html_writer::div(get_string('quote:toinvoicehelp', 'local_oriskill'), 'small text-muted mb-1') .
                    html_writer::select($methods, 'method', 'transfer', false, ['class' => 'form-select form-select-sm mb-2',
                        'aria-label' => get_string('journal:method', 'local_oriskill')]) .
                    html_writer::tag('button', get_string('quote:toinvoiceconfirm', 'local_oriskill'),
                        ['type' => 'submit', 'class' => 'btn btn-sm btn-primary'])),
                ['class' => 'd-inline-block', 'style' => 'max-width: 16rem']);
            $actions .= ' ' . $post($quote, 'quotecancel', html_writer::tag('button', get_string('quote:cancel', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-link text-danger']), get_string('quote:cancelconfirm', 'local_oriskill'));
        }
        $table->data[] = [
            html_writer::span($quote->number, 'fw-semibold') .
                html_writer::div(userdate($quote->timecreated, get_string('strftimedatetimeshort', 'langconfig')), 'small text-muted'),
            s($data['buyer']['name']) . html_writer::div(s($data['buyer']['email']), 'small text-muted'),
            pricing_service::format_price((float) $quote->total, $quote->currency),
            $state,
            html_writer::div($actions, 'd-flex flex-wrap align-items-start gap-1'),
        ];
    }
    echo html_writer::div(html_writer::tag('h3', get_string('quote:list', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::div(html_writer::table($table), 'table-responsive'), 'oriskill-card mb-4');
}

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
