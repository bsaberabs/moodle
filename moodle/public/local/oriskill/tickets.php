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
 * Support tickets of the learners, for the trainer's team: the list, a conversation, answer, internal note, close.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\team_service;
use local_oriskill\ticket_service as ts;

$id = optional_param('id', 0, PARAM_INT);
$status = optional_param('status', 'open', PARAM_ALPHA);
$search = optional_param('q', '', PARAM_TEXT);
$page = optional_param('page', 0, PARAM_INT);
$baseurl = new moodle_url('/local/oriskill/tickets.php');
$creator = creator_service::require_current_creator($baseurl, 'menu:tickets');
$canchange = team_service::can_change(creator_service::current_role(), '/local/oriskill/tickets.php');

if ($id && ($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    $ticket = ts::require_for_team($creator, $id);
    $back = new moodle_url($baseurl, ['id' => $id]);
    try {
        if ($action === 'reply' || $action === 'note') {
            ts::reply($ticket, (int) $USER->id, required_param('message', PARAM_TEXT), true, $action === 'note');
            if ($action === 'reply' && optional_param('andclose', 0, PARAM_BOOL)) {
                ts::set_status($ticket, 'closed');
            }
            $message = get_string($action === 'note' ? 'tickets:noted' : 'tickets:answered', 'local_oriskill');
        } else if ($action === 'close' || $action === 'reopen') {
            ts::set_status($ticket, $action === 'close' ? 'closed' : 'open');
            $message = get_string('changessaved');
        }
        redirect($back, $message ?? '', null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($back, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$hidden = fn(string $action) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
$badge = fn(string $s) => html_writer::span(get_string('tickets:status_' . $s, 'local_oriskill'),
    'oriskill-badge ' . ['open' => 'is-warn', 'answered' => 'is-good', 'closed' => ''][$s]);
$date = fn(int $time) => userdate($time, get_string('strftimedatetimeshort', 'langconfig'));
$coursename = fn($courseid) => $courseid
    ? format_string((string) $DB->get_field('course', 'fullname', ['id' => $courseid])) : '';

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/tickets.php', get_string('menu:tickets', 'local_oriskill'));

if ($id) {
    // One ticket.
    $ticket = ts::require_for_team($creator, $id);
    $learner = core_user::get_user($ticket->userid);
    $contactid = $DB->get_field('local_oriskill_contacts', 'id', ['creatorid' => $creator->id, 'userid' => $ticket->userid]);
    $who = $learner ? fullname($learner) . ' · ' . s($learner->email) : '?';
    if ($contactid && team_service::can(creator_service::current_role(), '/local/oriskill/contact.php')) {
        $who = html_writer::link(new moodle_url('/local/oriskill/contact.php', ['id' => $contactid]), $who);
    }
    echo html_writer::link(new moodle_url($baseurl, ['status' => $ticket->status]), '← ' .
        get_string('tickets:back', 'local_oriskill'), ['class' => 'd-inline-block mb-3']);
    echo html_writer::div(
        html_writer::div(html_writer::tag('h3', '#' . $ticket->id . ' · ' . s($ticket->subject), ['class' => 'h5 mb-1']) .
            html_writer::div($who . ($ticket->courseid ? ' · ' . $coursename($ticket->courseid) : '') . ' · ' .
                get_string('tickets:opened', 'local_oriskill', $date((int) $ticket->timecreated)), 'small text-muted')) .
        $badge($ticket->status), 'oriskill-card mb-3 d-flex justify-content-between align-items-start gap-3');

    $authors = [];
    foreach (ts::messages($ticket, true) as $message) {
        $authors[$message->userid] = $authors[$message->userid] ?? core_user::get_user($message->userid);
        $fromlearner = (int) $message->userid === (int) $ticket->userid;
        $style = $message->internal ? 'background: #fef9c3; border-color: #facc15'
            : ($fromlearner ? '' : 'background: #f5f3ff; border-color: #c4b5fd');
        echo html_writer::div(
            html_writer::div(($authors[$message->userid] ? fullname($authors[$message->userid]) : '?') . ' · ' .
                $date((int) $message->timecreated) . ($message->internal
                    ? ' · ' . html_writer::tag('strong', get_string('tickets:internal', 'local_oriskill')) : ''),
                'small text-muted mb-1') .
            html_writer::div(nl2br(s($message->message)), '', ['style' => 'white-space: normal']),
            'oriskill-card mb-2 ' . ($fromlearner ? 'me-md-5' : 'ms-md-5'), ['style' => $style]);
    }

    if ($canchange) {
        echo html_writer::tag('form', $hidden('reply') .
            html_writer::tag('label', get_string('tickets:answer', 'local_oriskill'), ['for' => 'message', 'class' => 'form-label']) .
            html_writer::tag('textarea', '', ['name' => 'message', 'id' => 'message', 'rows' => 6, 'required' => 'required',
                'class' => 'form-control mb-2']) .
            html_writer::div(
                html_writer::tag('button', get_string('tickets:send', 'local_oriskill'), ['type' => 'submit',
                    'class' => 'btn btn-primary']) .
                html_writer::tag('button', get_string('tickets:sendclose', 'local_oriskill'), ['type' => 'submit',
                    'name' => 'andclose', 'value' => 1, 'class' => 'btn btn-outline-primary']) .
                html_writer::tag('button', get_string('tickets:addnote', 'local_oriskill'), ['type' => 'submit',
                    'name' => 'action', 'value' => 'note', 'class' => 'btn btn-outline-secondary']),
                'd-flex flex-wrap gap-2') .
            html_writer::div(get_string('tickets:note_help', 'local_oriskill'), 'small text-muted mt-2'),
            ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'oriskill-card mt-3']);
        echo html_writer::tag('form', $hidden($ticket->status === 'closed' ? 'reopen' : 'close') .
            html_writer::tag('button', get_string($ticket->status === 'closed' ? 'tickets:reopen' : 'tickets:close',
                'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-link px-0']),
            ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'mt-2']);
    }
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}

// The list: one tab per status.
$tabs = '';
foreach (array_merge(ts::STATUSES, ['all']) as $tab) {
    $label = get_string('tickets:tab_' . $tab, 'local_oriskill');
    if ($tab === 'open' && ($open = ts::open_count($creator))) {
        $label .= ' (' . $open . ')';
    }
    $tabs .= html_writer::tag('li', html_writer::link(new moodle_url($baseurl, ['status' => $tab]), $label,
        ['class' => 'nav-link' . ($tab === $status ? ' active' : '')]), ['class' => 'nav-item']);
}
echo html_writer::tag('ul', $tabs, ['class' => 'nav nav-tabs mb-3']);
echo html_writer::tag('form',
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'status', 'value' => $status]) .
    html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $search, 'class' => 'form-control',
        'placeholder' => get_string('tickets:search', 'local_oriskill')]) .
    html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-outline-secondary']),
    ['method' => 'get', 'action' => $baseurl->out(false), 'class' => 'd-flex gap-2 mb-3', 'style' => 'max-width: 32rem']);

[$tickets, $total] = ts::list($creator, $status === 'all' ? '' : $status, $search, $page);
if (!$tickets) {
    echo html_writer::div('<i class="fa fa-life-ring" aria-hidden="true"></i>' .
        get_string('tickets:none', 'local_oriskill'), 'oriskill-card oriskill-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('subject'), get_string('tickets:learner', 'local_oriskill'), get_string('course'),
        get_string('tickets:updated', 'local_oriskill'), get_string('status')];
    foreach ($tickets as $ticket) {
        $table->data[] = [
            html_writer::link(new moodle_url($baseurl, ['id' => $ticket->id]), '#' . $ticket->id . ' · ' . s($ticket->subject),
                ['class' => 'fw-semibold']),
            s(fullname($ticket)) . html_writer::div(s($ticket->email), 'small text-muted'),
            $coursename($ticket->courseid),
            $date((int) $ticket->timemodified),
            $badge($ticket->status),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card table-responsive');
    echo $OUTPUT->paging_bar($total, $page, ts::PERPAGE, new moodle_url($baseurl, ['status' => $status, 'q' => $search]));
}
echo creator_service::space_end();
echo $OUTPUT->footer();
