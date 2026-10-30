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
 * A learner's support tickets: write to the team of a trainer, follow the answers.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\ticket_service as ts;

$id = optional_param('id', 0, PARAM_INT);
$url = new moodle_url('/local/oriskill/support.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($id ? new moodle_url($url, ['id' => $id]) : $url);
require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('guest');
}
$PAGE->set_title(get_string('support:title', 'local_oriskill'));
$PAGE->set_heading(get_string('support:title', 'local_oriskill'));
$trainers = ts::trainers_of((int) $USER->id);

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    try {
        if ($action === 'create') {
            $creator = $trainers[required_param('creatorid', PARAM_INT)] ?? null;
            if (!$creator) {
                throw new moodle_exception('error:notallowed', 'local_oriskill');
            }
            $ticket = ts::create($creator, (int) $USER->id, required_param('subject', PARAM_TEXT),
                required_param('message', PARAM_TEXT), optional_param('courseid', 0, PARAM_INT));
            redirect(new moodle_url($url, ['id' => $ticket->id]), get_string('support:sent', 'local_oriskill'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        $ticket = ts::require_own((int) $USER->id, required_param('id', PARAM_INT));
        if ($action === 'reply') {
            ts::reply($ticket, (int) $USER->id, required_param('message', PARAM_TEXT), false);
            $message = get_string('support:sent', 'local_oriskill');
        } else if ($action === 'close') {
            ts::set_status($ticket, 'closed');
            $message = get_string('support:closed', 'local_oriskill');
        }
        redirect(new moodle_url($url, ['id' => $ticket->id]), $message ?? '', null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($id ? new moodle_url($url, ['id' => $id]) : $url, $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

$hidden = fn(string $action, int $ticketid = 0) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) .
    ($ticketid ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $ticketid]) : '');
$badge = fn(string $status) => html_writer::span(get_string('support:status_' . $status, 'local_oriskill'),
    'badge ' . ['open' => 'bg-secondary', 'answered' => 'bg-success', 'closed' => 'bg-light text-dark border'][$status]);
$date = fn(int $time) => userdate($time, get_string('strftimedatetimeshort', 'langconfig'));

echo $OUTPUT->header();

if ($id) {
    // One ticket: the conversation and the answer box.
    $ticket = ts::require_own((int) $USER->id, $id);
    $creator = $DB->get_record('local_oriskill_creators', ['id' => $ticket->creatorid], '*', MUST_EXIST);
    echo html_writer::link($url, '← ' . get_string('support:back', 'local_oriskill'), ['class' => 'd-inline-block mb-3']);
    echo html_writer::div(
        html_writer::tag('h3', s($ticket->subject), ['class' => 'h4 mb-1']) .
        html_writer::div(format_string($creator->displayname) . ($ticket->courseid
            ? ' · ' . format_string((string) $DB->get_field('course', 'fullname', ['id' => $ticket->courseid])) : '') .
            ' · ' . $badge($ticket->status), 'text-muted small'), 'mb-3');
    foreach (ts::messages($ticket, false) as $message) {
        $mine = (int) $message->userid === (int) $USER->id;
        $author = $mine ? get_string('support:you', 'local_oriskill') : format_string($creator->displayname);
        echo html_writer::div(
            html_writer::div(s($author) . ' · ' . $date((int) $message->timecreated), 'small text-muted mb-1') .
            html_writer::div(nl2br(s($message->message)), '', ['style' => 'white-space: normal']),
            'card card-body mb-2 ' . ($mine ? 'ms-md-5 bg-light' : 'me-md-5 border-primary'));
    }
    echo html_writer::tag('form', $hidden('reply', (int) $ticket->id) .
        html_writer::tag('label', get_string($ticket->status === 'closed' ? 'support:reopen' : 'support:answer', 'local_oriskill'),
            ['for' => 'message', 'class' => 'form-label mt-3']) .
        html_writer::tag('textarea', '', ['name' => 'message', 'id' => 'message', 'rows' => 5, 'required' => 'required',
            'class' => 'form-control mb-2']) .
        html_writer::tag('button', get_string('support:send', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']),
        ['method' => 'post', 'action' => $url->out(false)]);
    if ($ticket->status !== 'closed') {
        echo html_writer::tag('form', $hidden('close', (int) $ticket->id) .
            html_writer::tag('button', get_string('support:close', 'local_oriskill'), ['type' => 'submit',
                'class' => 'btn btn-link px-0']), ['method' => 'post', 'action' => $url->out(false), 'class' => 'mt-2']);
    }
    echo $OUTPUT->footer();
    exit;
}

// My tickets.
$tickets = ts::of_user((int) $USER->id);
if ($tickets) {
    $rows = '';
    foreach ($tickets as $ticket) {
        $trainer = $trainers[$ticket->creatorid] ?? $DB->get_record('local_oriskill_creators', ['id' => $ticket->creatorid]);
        $rows .= html_writer::link(new moodle_url($url, ['id' => $ticket->id]),
            html_writer::div(html_writer::div(s($ticket->subject), 'fw-semibold') .
                html_writer::div(($trainer ? format_string($trainer->displayname) . ' · ' : '') . $date((int) $ticket->timemodified),
                    'small text-muted')) . $badge($ticket->status),
            ['class' => 'list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-3']);
    }
    echo html_writer::tag('h3', get_string('support:mine', 'local_oriskill'), ['class' => 'h5']);
    echo html_writer::div($rows, 'list-group mb-4');
}

// A new ticket.
echo html_writer::start_div('card card-body');
echo html_writer::tag('h3', get_string('support:new', 'local_oriskill'), ['class' => 'h5']);
if (!$trainers) {
    echo html_writer::tag('p', get_string('support:notrainer', 'local_oriskill'), ['class' => 'text-muted mb-0']);
} else {
    $selected = optional_param('creator', 0, PARAM_INT) ?: (int) array_key_first($trainers);
    $courses = [0 => get_string('support:nocourse', 'local_oriskill')];
    foreach ($trainers as $trainer) {
        foreach (ts::courses_of($trainer, (int) $USER->id) as $courseid => $name) {
            $courses[$courseid] = count($trainers) > 1 ? format_string($trainer->displayname) . ' – ' . $name : $name;
        }
    }
    $field = fn(string $label, string $control, string $for) => html_writer::div(
        html_writer::tag('label', $label, ['class' => 'form-label', 'for' => $for]) . $control, 'mb-3');
    echo html_writer::tag('form', $hidden('create') .
        (count($trainers) > 1
            ? $field(get_string('support:trainer', 'local_oriskill'), html_writer::select(array_map(fn($t) =>
                format_string($t->displayname), $trainers), 'creatorid', $selected, false,
                ['class' => 'form-select', 'id' => 'creatorid']), 'creatorid')
            : html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'creatorid', 'value' => $selected])) .
        $field(get_string('support:course', 'local_oriskill'), html_writer::select($courses, 'courseid',
            optional_param('courseid', 0, PARAM_INT), false, ['class' => 'form-select', 'id' => 'courseid']), 'courseid') .
        $field(get_string('subject'), html_writer::empty_tag('input', ['type' => 'text', 'name' => 'subject', 'id' => 'subject',
            'maxlength' => 255, 'required' => 'required', 'class' => 'form-control']), 'subject') .
        $field(get_string('message', 'message'), html_writer::tag('textarea', '', ['name' => 'message', 'id' => 'message',
            'rows' => 6, 'required' => 'required', 'class' => 'form-control']), 'message') .
        html_writer::tag('button', get_string('support:send', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']),
        ['method' => 'post', 'action' => $url->out(false)]);
}
echo html_writer::end_div();
echo $OUTPUT->footer();
