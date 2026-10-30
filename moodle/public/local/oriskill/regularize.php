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
 * Link sent to a learner with unpaid instalments: what is missing, and pay it. Also the page they come back to.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\unpaid_service;

$id = optional_param('id', 0, PARAM_INT);
$orderid = optional_param('orderid', 0, PARAM_INT);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oriskill/regularize.php', $id ? ['id' => $id] : ['orderid' => $orderid]));
require_login(null, false);

if (!$id && $orderid) {
    // Back from the payment page.
    $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid, 'userid' => $USER->id], '*', MUST_EXIST);
    $id = (int) (json_decode((string) $order->notes, true)['unpaid'] ?? 0);
}
$case = $DB->get_record('local_oriskill_unpaid', ['id' => $id, 'userid' => $USER->id], '*', MUST_EXIST);
$record = $DB->get_record('local_oriskill_instalments', ['id' => $case->instalmentid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $case->courseid], '*', MUST_EXIST);
$PAGE->set_title(get_string('regularize:title', 'local_oriskill'));
$PAGE->set_heading(get_string('regularize:title', 'local_oriskill'));

if ($case->state === 'open' && optional_param('pay', 0, PARAM_BOOL) && confirm_sesskey()) {
    redirect(unpaid_service::payment_url($case));
}

echo $OUTPUT->header();
echo html_writer::start_div('card mx-auto', ['style' => 'max-width: 38rem']);
echo html_writer::start_div('card-body');
echo html_writer::tag('h2', format_string($course->fullname), ['class' => 'h4']);
if ($case->state === 'open') {
    $currency = (string) $DB->get_field('local_moderncommerce_orders', 'currency', ['id' => $record->orderid]);
    $amount = pricing_service::format_price(unpaid_service::amount_due($record, $case->cause), $currency);
    echo html_writer::tag('p', get_string('regularize:intro', 'local_oriskill', (object) [
        'missing' => unpaid_service::missing($record, $case->cause), 'amount' => $amount]));
    if ($record->status === 'suspended') {
        echo html_writer::tag('p', get_string('regularize:suspended', 'local_oriskill'), ['class' => 'text-muted']);
    }
    echo html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $case->id]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'pay', 'value' => 1]) .
        html_writer::tag('button', get_string('regularize:pay', 'local_oriskill', $amount),
            ['type' => 'submit', 'class' => 'btn btn-primary']),
        ['method' => 'post', 'action' => (new moodle_url('/local/oriskill/regularize.php'))->out(false)]);
    if ($orderid) {
        // Back from the payment page before the payment notification arrived, or after a refused payment.
        echo html_writer::tag('p', get_string('regularize:pending', 'local_oriskill'), ['class' => 'small text-muted mt-3']);
    }
} else {
    echo $OUTPUT->notification(get_string($case->state === 'settled' ? 'regularize:settled' : 'regularize:closed',
        'local_oriskill'), $case->state === 'settled' ? 'success' : 'info');
    echo html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('regularize:gotocourse', 'local_oriskill'), ['class' => 'btn btn-primary']);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
