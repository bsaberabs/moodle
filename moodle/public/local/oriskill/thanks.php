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
 * After paying on an order form: the trainer's thank-you message and the courses now open.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\orderform_service;

$orderid = required_param('orderid', PARAM_INT);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oriskill/thanks.php', ['orderid' => $orderid]));
require_login(null, false);

$order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid, 'userid' => $USER->id], '*', MUST_EXIST);
$form = orderform_service::form_of_order($order);
if (!$form) {
    redirect(new moodle_url('/local/moderncommerce/success.php', ['orderid' => $orderid]));
}
$PAGE->set_title(format_string($form->title));
$PAGE->set_heading(format_string($form->title));
$paid = in_array($order->status, ['paid', 'completed'], true);

// The buyer takes the offer after payment: pay it (with the saved card on SecureAndPay).
if ($paid && optional_param('upsell', 0, PARAM_BOOL) && confirm_sesskey()) {
    [, $payurl] = orderform_service::take_upsell($order);
    redirect($payurl);
}
$upsell = $paid ? orderform_service::upsell_for_order($order) : null;
// The trainer's own page after a payment (after the offer that follows the payment, if any).
$redirect = trim((string) ($form->redirecturl ?? ''));
if ($paid && $redirect !== '' && !$upsell) {
    redirect(new moodle_url($redirect));
}

echo $OUTPUT->header();
echo html_writer::start_div('card mx-auto', ['style' => 'max-width: 38rem']);
echo html_writer::start_div('card-body');
if ($paid) {
    echo html_writer::tag('h2', get_string('orderform:thanks', 'local_oriskill'), ['class' => 'h4']);
    if (trim((string) $form->thankyou) !== '') {
        echo html_writer::tag('p', nl2br(s($form->thankyou)));
    }
    $links = '';
    foreach ($DB->get_records('local_moderncommerce_order_items', ['orderid' => $order->id]) as $item) {
        if ($item->courseid) {
            $links .= html_writer::tag('li', html_writer::link(new moodle_url('/course/view.php', ['id' => $item->courseid]),
                format_string($item->itemname)));
        }
    }
    echo html_writer::tag('p', get_string('orderform:accessopen', 'local_oriskill')) . html_writer::tag('ul', $links);
    if ($upsell) {
        [, $offer] = $upsell;
        $currency = $order->currency;
        echo html_writer::div(
            html_writer::tag('h3', get_string('orderform:upselltitle', 'local_oriskill'), ['class' => 'h5']) .
            html_writer::tag('p', html_writer::tag('strong', s($offer->label))) .
            ($offer->text !== '' ? html_writer::tag('p', nl2br(s($offer->text))) : '') .
            html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'upsell', 'value' => 1]) .
                html_writer::tag('button', get_string('orderform:upsellyes', 'local_oriskill',
                    \local_moderncommerce\services\pricing_service::format_price($offer->price, $currency)),
                    ['type' => 'submit', 'class' => 'btn btn-primary']) . ' ' .
                html_writer::link($redirect !== '' ? new moodle_url($redirect) : new moodle_url('/local/moderncommerce/learner/index.php'),
                    get_string('orderform:upsellno', 'local_oriskill'), ['class' => 'btn btn-link']),
                ['method' => 'post', 'action' => $PAGE->url->out(false)]) .
            html_writer::div(get_string('orderform:upsellcard', 'local_oriskill'), 'small text-muted mt-2'),
            'oriskill-upsell mt-4');
    }
} else {
    // SecureAndPay notifies the payment on its own, usually within seconds of the buyer's return.
    echo html_writer::tag('p', get_string('orderform:waiting', 'local_oriskill'));
    echo html_writer::link($PAGE->url, get_string('orderform:refresh', 'local_oriskill'), ['class' => 'btn btn-secondary']);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
