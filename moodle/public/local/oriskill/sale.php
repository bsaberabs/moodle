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
 * One sale of the trainer: buyer, products, payment and schedule, every transaction, documents, access; refund,
 * credit note, cancel a subscription, suspend or restore the access.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\billing_service;
use local_oriskill\creator_service;
use local_oriskill\instalment_service;
use local_oriskill\learning_service;
use local_oriskill\log_service;
use local_oriskill\orderform_service;
use local_oriskill\stats_service as st;
use local_oriskill\team_service;
use local_oriskill\transaction_service as ts;
use local_secureandpay\gateway;
use local_secureandpay\refund_service;

$id = required_param('id', PARAM_INT);
$url = new moodle_url('/local/oriskill/sale.php', ['id' => $id]);
$creator = creator_service::require_current_creator($url, 'sales');
$order = ts::require_order($creator, $id);
$timezone = st::timezone($creator);
$canchange = team_service::can_change(creator_service::current_role(), '/local/oriskill/sale.php');
$money = fn(float $amount) => pricing_service::format_price($amount, $order->currency);
$invoices = $DB->get_records('local_oriskill_invoices', ['orderid' => $order->id], 'timecreated ASC, id ASC');
$invoice = null;
foreach ($invoices as $document) {
    if ($document->type === 'invoice') {
        $invoice = $invoice ?? $document;
    }
}
$schedule = $DB->get_record('local_oriskill_instalments', ['orderid' => $order->id]);
$sap = (bool) $DB->record_exists('local_moderncommerce_payment_events', ['orderid' => $order->id, 'gateway' => 'secureandpay']);
$courseids = array_unique(array_map('intval', $DB->get_fieldset_select('local_moderncommerce_order_items', 'courseid',
    'orderid = ? AND courseid IS NOT NULL', [$order->id])));

// Actions.
if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    $reason = trim(optional_param('reason', '', PARAM_TEXT));
    try {
        if ($action === 'refund' && $invoice) {
            // SecureAndPay refunds by itself; another gateway is refunded in its own account, here only the credit note.
            $amount = (float) unformat_float(required_param('amount', PARAM_RAW_TRIMMED), true);
            if ($sap) {
                refund_service::request($order, $amount, $reason);
                log_service::add($creator, 'refund_requested', ['amount' => $money($amount), 'invoice' => $invoice->number],
                    null, (int) $order->userid);
                $message = get_string('refund:requested', 'local_oriskill');
            } else {
                $credit = billing_service::issue_credit_note($invoice, $amount, $reason);
                log_service::add($creator, 'credit_issued', ['number' => $credit->number, 'invoice' => $invoice->number],
                    null, (int) $order->userid);
                $message = get_string('sale:refundrecorded', 'local_oriskill', $credit->number);
            }
        } else if ($action === 'credit' && $invoice) {
            $credit = billing_service::issue_credit_note($invoice,
                (float) unformat_float(required_param('amount', PARAM_RAW_TRIMMED), true), $reason);
            log_service::add($creator, 'credit_issued', ['number' => $credit->number, 'invoice' => $invoice->number], null,
                (int) $order->userid);
            $message = get_string('credit:issued', 'local_oriskill', $credit->number);
        } else if ($action === 'cancel' && $schedule && !$schedule->count) {
            $confirmed = instalment_service::cancel_subscription($schedule);
            $message = get_string($confirmed ? 'subscription:cancelledtrainer' : 'subscription:cancelledmanual', 'local_oriskill',
                userdate(instalment_service::paid_until($schedule), get_string('strftimedate', 'langconfig')));
        } else if (($action === 'suspend' || $action === 'restore') && in_array($courseid = required_param('courseid', PARAM_INT),
                $courseids, true)) {
            $course = get_course($courseid);
            learning_service::set_suspended($course, (int) $order->userid, $action === 'suspend');
            log_service::add($creator, $action === 'suspend' ? 'learner_suspend' : 'learner_reactivate', [], $courseid,
                (int) $order->userid, [get_string('sale:access', 'local_oriskill') => [
                    get_string($action === 'suspend' ? 'sale:accessactive' : 'sale:accesssuspended', 'local_oriskill'),
                    get_string($action === 'suspend' ? 'sale:accesssuspended' : 'sale:accessactive', 'local_oriskill')]]);
            $message = get_string('changessaved');
        } else if ($action === 'mail' && ($document = $invoices[required_param('documentid', PARAM_INT)] ?? null)) {
            $sent = billing_service::mail_invoice($document, true);
            redirect($url, get_string($sent ? 'invoicemail:sent' : 'invoicemail:notsent', 'local_oriskill', $document->number),
                null, $sent ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
        }
        redirect($url, $message ?? '', null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$buyer = core_user::get_user($order->userid);
$billing = orderform_service::billing_of_order((int) $order->id);
$notes = json_decode((string) $order->notes, true) ?: [];
$transactions = ts::of_order($creator, $order);
$collected = array_sum(array_map(fn($t) => $t->type === 'failed' ? 0 : $t->amount, $transactions));
$plan = gateway::instalment_plan($order);
$card = fn(string $title, string $content, string $class = '') => html_writer::div(
    html_writer::tag('h3', $title, ['class' => 'h6 text-uppercase text-muted mb-3']) . $content, 'oriskill-card ' . $class);
$facts = function(array $rows): string {
    $html = '';
    foreach ($rows as $label => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $html .= html_writer::tag('dt', $label, ['class' => 'col-5 fw-normal text-muted']) .
            html_writer::tag('dd', $value, ['class' => 'col-7 mb-2']);
    }
    return html_writer::tag('dl', $html, ['class' => 'row mb-0 small']);
};
$post = fn(string $action, string $inner, array $hidden = [], string $confirm = '') => html_writer::tag('form',
    html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => $action] + $hidden)) . $inner,
    ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline'] +
    ($confirm !== '' ? ['onsubmit' => 'return confirm(' . json_encode($confirm) . ')'] : []));

// State of the sale.
if ($order->status === 'refunded') {
    $statebadge = html_writer::span(get_string('sale:state_refunded', 'local_oriskill'), 'oriskill-badge is-warn');
} else if ($schedule) {
    $statebadge = instalment_service::status_badge($schedule);
} else {
    $paidstate = in_array($order->status, ['paid', 'completed'], true);
    $statebadge = html_writer::span($paidstate ? get_string('sale:state_paid', 'local_oriskill') : s($order->status),
        'oriskill-badge ' . ($paidstate ? 'is-good' : ''));
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/sales.php', get_string('sale:title', 'local_oriskill',
    s($order->ordernumber)));
echo html_writer::link(new moodle_url('/local/oriskill/sales.php'), '← ' . get_string('sale:back', 'local_oriskill'),
    ['class' => 'd-inline-block mb-3']);

// Summary strip.
$stat = fn(string $value, string $label) => html_writer::div(html_writer::div(html_writer::div($value, 'oriskill-stat-value') .
    html_writer::div($label, 'oriskill-stat-label')), 'oriskill-card oriskill-stat');
echo html_writer::div(
    $stat($money((float) $order->total), get_string('sale:total', 'local_oriskill')) .
    $stat($money($collected), get_string('sale:collected', 'local_oriskill')) .
    $stat($statebadge,
        get_string('sale:since', 'local_oriskill', userdate((int) ($order->timepaid ?: $order->timecreated),
            get_string('strftimedatetimeshort', 'langconfig'), $timezone))),
    'oriskill-stats mb-3');

// Buyer.
$contactid = $DB->get_field('local_oriskill_contacts', 'id', ['creatorid' => $creator->id, 'userid' => $order->userid]);
$name = $buyer ? fullname($buyer) : '?';
if ($contactid && team_service::can(creator_service::current_role(), '/local/oriskill/contact.php')) {
    $name = html_writer::link(new moodle_url('/local/oriskill/contact.php', ['id' => $contactid]), $name);
}
$countries = get_string_manager()->get_list_of_countries();
$address = trim(implode("\n", array_filter([$billing['address'] ?? '', trim(($billing['postcode'] ?? '') . ' ' .
    ($billing['city'] ?? '')), $countries[$billing['country'] ?? ''] ?? ($billing['country'] ?? '')])));
$buyerhtml = $facts([
    get_string('transactions:buyer', 'local_oriskill') => $name,
    get_string('email') => $buyer ? html_writer::link('mailto:' . $buyer->email, s($buyer->email)) : '',
    get_string('phone') => s($billing['phone'] ?? ($buyer->phone1 ?? '')),
    get_string('address') => $address !== '' ? nl2br(s($address)) : '',
]);

// Payment.
$form = !empty($notes['orderform']) ? $DB->get_record('local_oriskill_orderforms', ['id' => $notes['orderform']]) : null;
$offer = !empty($notes['offer']) ? $DB->get_field('local_oriskill_formoffers', 'label', ['id' => $notes['offer']]) : false;
$planlabel = get_string('sale:once', 'local_oriskill');
if ($plan && $plan['subscription']) {
    $planlabel = get_string('sale:subscription', 'local_oriskill', $money($plan['amount']));
} else if ($plan) {
    $planlabel = get_string('sale:instalments', 'local_oriskill', (object) ['count' => $plan['count'],
        'amount' => $money($plan['amount'])]) . ($plan['first'] != $plan['amount']
        ? ' · ' . get_string('sale:firstpayment', 'local_oriskill', $money($plan['first'])) : '');
}
$progress = '';
$nextdue = '';
if ($schedule) {
    $record = instalment_service::trainer_records($creator)[$schedule->id] ?? null;
    $paid = (int) $schedule->paid + (int) $schedule->extrapaid;
    $progress = $schedule->count ? get_string('sale:paidof', 'local_oriskill', (object) ['paid' => $paid,
        'count' => (int) $schedule->count]) : get_string('sale:monthspaid', 'local_oriskill', $paid);
    if ($record && $record->nextdue) {
        $nextdue = userdate($record->nextdue, get_string('strftimedate', 'langconfig'), $timezone);
    }
}
$commission = $DB->get_record_sql("SELECT c.*, u.firstname, u.lastname FROM {local_oriskill_commissions} c
                                     JOIN {local_oriskill_affiliates} a ON a.id = c.affiliateid
                                     JOIN {user} u ON u.id = a.userid
                                    WHERE c.orderid = ? AND c.level <= 1", [$order->id], IGNORE_MULTIPLE);
$paymenthtml = $facts([
    get_string('transactions:method', 'local_oriskill') => s($transactions ? end($transactions)->method
        : billing_service::payment_method($order)),
    get_string('sale:plan', 'local_oriskill') => $planlabel,
    get_string('sale:progress', 'local_oriskill') => $progress,
    get_string('sale:nextdue', 'local_oriskill') => $nextdue,
    get_string('sale:refundedtotal', 'local_oriskill') => (float) $order->refundedtotal > 0
        ? $money((float) $order->refundedtotal) : '',
    get_string('sale:orderform', 'local_oriskill') => $form ? format_string($form->name) .
        ($offer !== false ? ' · ' . format_string($offer) : '') : '',
    get_string('sale:coupon', 'local_oriskill') => s((string) $order->couponcode),
    get_string('sale:affiliate', 'local_oriskill') => $commission ? fullname($commission) . ' · ' .
        pricing_service::format_price((float) $commission->amount, $commission->currency) : '',
]);
echo html_writer::div($card(get_string('sale:buyer', 'local_oriskill'), $buyerhtml) .
    $card(get_string('sale:payment', 'local_oriskill'), $paymenthtml), 'oriskill-grid-2 mb-3');

// Products.
$table = new html_table();
$table->attributes['class'] = 'table align-middle mb-0';
$table->head = [get_string('stats:product', 'local_oriskill'), get_string('sale:quantity', 'local_oriskill'),
    get_string('sale:unitprice', 'local_oriskill'), get_string('transactions:amount', 'local_oriskill')];
foreach ($DB->get_records('local_moderncommerce_order_items', ['orderid' => $order->id], 'id') as $item) {
    $table->data[] = [format_string($item->itemname), format_float((float) $item->quantity, 0),
        $money((float) $item->unitprice), $money((float) $item->total)];
}
if ((float) $order->discount > 0) {
    $table->data[] = [get_string('sale:discount', 'local_oriskill'), '', '', '− ' . $money((float) $order->discount)];
}
$table->data[] = [html_writer::tag('strong', get_string('sale:total', 'local_oriskill')), '', '',
    html_writer::tag('strong', $money((float) $order->total))];
echo $card(get_string('sale:products', 'local_oriskill'), html_writer::div(html_writer::table($table), 'table-responsive'),
    'mb-3');

// Every payment, refusal and refund.
echo $card(get_string('sale:transactions', 'local_oriskill'), $transactions
    ? html_writer::div(ts::table(array_reverse($transactions), $creator, $timezone, false), 'table-responsive')
    : html_writer::div(get_string('nosales', 'local_oriskill'), 'text-muted small'), 'mb-3');

// Documents.
$rows = '';
foreach ($invoices as $document) {
    $iscredit = $document->type === 'credit';
    $rows .= html_writer::div(
        html_writer::div(html_writer::link(billing_service::url($document), '<i class="fa fa-file-pdf me-1" aria-hidden="true"></i>' .
            s($document->number), ['class' => 'fw-semibold']) . ' ' .
            html_writer::span(get_string($iscredit ? 'credit:title' : 'invoice:title', 'local_oriskill'),
                'oriskill-badge ' . ($iscredit ? 'is-warn' : '')) .
            html_writer::div(userdate($document->timecreated, get_string('strftimedatetimeshort', 'langconfig'), $timezone),
                'small text-muted')) .
        html_writer::div(html_writer::span(($iscredit ? '− ' : '') . $money((float) $document->total), 'fw-semibold me-2') .
            ($canchange ? $post('mail', html_writer::tag('button', '<i class="fa fa-envelope" aria-hidden="true"></i>',
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary',
                    'title' => get_string('invoicemail:send', 'local_oriskill'),
                    'aria-label' => get_string('invoicemail:send', 'local_oriskill')]), ['documentid' => $document->id]) : ''),
            'd-flex align-items-center'),
        'd-flex justify-content-between align-items-center gap-2 py-2 border-bottom');
}
foreach ($sap ? refund_service::requests($order) : [] as $request) {
    $rows .= html_writer::div(get_string('refund:status_' . $request->status, 'local_oriskill',
        $money((float) $request->amount)) . ($request->status === 'refused' ? ' (' . s($request->answer) . ')' : '') .
        ' · ' . userdate($request->timecreated, get_string('strftimedatetimeshort', 'langconfig'), $timezone),
        'small text-muted py-2');
}
$documentshtml = $rows ?: html_writer::div(get_string('sale:nodocument', 'local_oriskill'), 'text-muted small');

// Access to the courses bought.
$access = '';
foreach ($courseids as $courseid) {
    if (!$course = $DB->get_record('course', ['id' => $courseid])) {
        continue;
    }
    $ue = $DB->get_record_sql("SELECT ue.status, ue.timeend FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                                WHERE e.courseid = ? AND ue.userid = ? ORDER BY ue.status ASC", [$courseid, $order->userid],
        IGNORE_MULTIPLE);
    $suspended = $ue && (int) $ue->status === ENROL_USER_SUSPENDED;
    $status = !$ue ? html_writer::span(get_string('sale:accessnone', 'local_oriskill'), 'oriskill-badge')
        : html_writer::span(get_string($suspended ? 'sale:accesssuspended' : 'sale:accessactive', 'local_oriskill'),
            'oriskill-badge ' . ($suspended ? 'is-bad' : 'is-good')) . ((int) $ue->timeend
            ? html_writer::div(get_string('sale:accessuntil', 'local_oriskill', userdate($ue->timeend,
                get_string('strftimedate', 'langconfig'), $timezone)), 'small text-muted') : '');
    $toggle = $ue && $canchange ? $post($suspended ? 'restore' : 'suspend', html_writer::tag('button',
        get_string($suspended ? 'sale:restore' : 'sale:suspend', 'local_oriskill'), ['type' => 'submit',
            'class' => 'btn btn-sm btn-link']), ['courseid' => $courseid],
        $suspended ? '' : get_string('sale:suspendconfirm', 'local_oriskill')) : '';
    $learner = team_service::can(creator_service::current_role(), '/local/oriskill/learner.php')
        ? html_writer::link(new moodle_url('/local/oriskill/learner.php', ['courseid' => $courseid, 'userid' => $order->userid]),
            format_string($course->fullname)) : format_string($course->fullname);
    $access .= html_writer::div(html_writer::div($learner) . html_writer::div($status . ' ' . $toggle, 'text-end'),
        'd-flex justify-content-between align-items-center gap-2 py-2 border-bottom');
}
echo html_writer::div($card(get_string('sale:documents', 'local_oriskill'), $documentshtml) .
    $card(get_string('sale:accesstitle', 'local_oriskill'), $access ?: html_writer::div('–', 'text-muted')),
    'oriskill-grid-2 mb-3');

// Actions.
if ($canchange) {
    $actions = '';
    $amountform = function(string $action, float $max, string $button, string $help, string $confirm) use ($post) {
        return $post($action,
            html_writer::tag('label', get_string('credit:amount', 'local_oriskill'), ['class' => 'form-label small']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'amount', 'inputmode' => 'decimal',
                'class' => 'form-control form-control-sm mb-2', 'value' => format_float($max, 2, true)]) .
            html_writer::tag('label', get_string('credit:reason', 'local_oriskill'), ['class' => 'form-label small']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'reason', 'maxlength' => 200,
                'class' => 'form-control form-control-sm mb-2']) .
            html_writer::div($help, 'small text-muted mb-2') .
            html_writer::tag('button', $button, ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']), [], $confirm);
    };
    if ($invoice) {
        $refundable = $sap ? refund_service::refundable($order) : billing_service::creditable($invoice);
        if ($refundable > 0) {
            $actions .= html_writer::div(html_writer::tag('h4', get_string('refund:action', 'local_oriskill'), ['class' => 'h6']) .
                $amountform('refund', $refundable, get_string('refund:confirm', 'local_oriskill'),
                    get_string($sap ? 'refund:help' : 'sale:refundother', 'local_oriskill'),
                    get_string('refund:confirmtext', 'local_oriskill')), 'oriskill-card');
        }
        if (($left = billing_service::creditable($invoice)) > 0) {
            $actions .= html_writer::div(html_writer::tag('h4', get_string('credit:issue', 'local_oriskill'), ['class' => 'h6']) .
                $amountform('credit', $left, get_string('credit:confirm', 'local_oriskill'),
                    get_string('sale:credithelp', 'local_oriskill'), get_string('credit:confirmtext', 'local_oriskill')),
                'oriskill-card');
        }
    }
    if ($schedule && !$schedule->count && $schedule->status !== 'cancelled') {
        $actions .= html_writer::div(html_writer::tag('h4', get_string('subscription:cancel', 'local_oriskill'), ['class' => 'h6']) .
            html_writer::div(get_string('sale:cancelhelp', 'local_oriskill', userdate(instalment_service::paid_until($schedule),
                get_string('strftimedate', 'langconfig'), $timezone)), 'small text-muted mb-2') .
            $post('cancel', html_writer::tag('button', get_string('subscription:cancel', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']), [],
                get_string('subscription:cancelconfirmtrainer', 'local_oriskill')), 'oriskill-card');
    }
    if ($actions) {
        echo html_writer::tag('h3', get_string('sale:actions', 'local_oriskill'), ['class' => 'h5 mt-4']);
        echo html_writer::div($actions, 'oriskill-grid-3');
    }
}

echo creator_service::space_end();
echo $OUTPUT->footer();
