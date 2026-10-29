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
 * A learner's subscriptions to trainers' offers: what they pay, until when they have access, cancel.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\instalment_service;

$url = new moodle_url('/local/oriskill/mysubscriptions.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
require_login(null, false);
$PAGE->set_title(get_string('subscription:mine', 'local_oriskill'));
$PAGE->set_heading(get_string('subscription:mine', 'local_oriskill'));

if (($cancel = optional_param('cancel', 0, PARAM_INT)) && confirm_sesskey()) {
    $record = $DB->get_record('local_oriskill_instalments', ['id' => $cancel, 'userid' => $USER->id, 'count' => 0], '*',
        MUST_EXIST);
    $confirmed = instalment_service::cancel_subscription($record);
    redirect($url, get_string($confirmed ? 'subscription:cancelled' : 'subscription:cancelledmanual', 'local_oriskill',
        userdate(instalment_service::paid_until($record), get_string('strftimedate', 'langconfig'))), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
$subscriptions = instalment_service::user_subscriptions((int) $USER->id);
if (!$subscriptions) {
    echo $OUTPUT->notification(get_string('subscription:none', 'local_oriskill'), 'info');
}
foreach ($subscriptions as $sub) {
    $creator = \local_oriskill\seller_service::owner_of_course((int) $sub->courseid);
    $active = in_array($sub->status, ['active', 'late', 'suspended'], true);
    echo html_writer::div(
        html_writer::div(
            html_writer::tag('h3', s($sub->label), ['class' => 'h5 mb-1']) .
            html_writer::div(($creator ? format_string($creator->displayname) . ' · ' : '') .
                get_string('subscription:permonth', 'local_oriskill', pricing_service::format_price((float) $sub->amount,
                $sub->currency)) . ' · ' . get_string('subscription:since', 'local_oriskill',
                userdate($sub->timecreated, get_string('strftimedate', 'langconfig'))), 'text-muted small') .
            html_writer::div(instalment_service::status_badge($sub), 'mt-2')) .
        ($active ? html_writer::tag('form',
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cancel', 'value' => $sub->id]) .
            html_writer::tag('button', get_string('subscription:cancel', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-outline-danger']),
            ['method' => 'post', 'action' => $url->out(false), 'onsubmit' => 'return confirm(' .
                json_encode(get_string('subscription:cancelconfirm', 'local_oriskill', userdate($sub->paiduntil,
                get_string('strftimedate', 'langconfig')))) . ')']) : ''),
        'card card-body d-flex flex-row flex-wrap justify-content-between align-items-center gap-3 mb-3');
}
echo $OUTPUT->footer();
