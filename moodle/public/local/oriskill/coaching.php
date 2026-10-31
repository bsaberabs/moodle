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
 * Coaching: assignments to correct, credits of the learners and the sessions that spend them.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\coaching_service as cs;
use local_oriskill\creator_service;
use local_oriskill\team_service;

$url = new moodle_url('/local/oriskill/coaching.php');
$creator = creator_service::require_current_creator($url, 'menu:coaching');
$canchange = team_service::can_change(creator_service::current_role(), '/local/oriskill/coaching.php');
$visible = team_service::visible_learners($creator);

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    $email = core_text::strtolower(trim(required_param('email', PARAM_RAW_TRIMMED)));
    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0], '*', IGNORE_MULTIPLE);
    try {
        // A person of the trainer: in their CRM, or enrolled in one of their courses.
        $known = $user && ($DB->record_exists('local_oriskill_contacts', ['creatorid' => $creator->id, 'userid' => $user->id])
            || $DB->record_exists_sql("SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                                         JOIN {course} c ON c.id = e.courseid WHERE ue.userid = ? AND c.category = ?",
                [$user->id, $creator->categoryid]));
        if (!$known || ($visible !== null && !in_array((int) $user->id, $visible, true))) {
            throw new moodle_exception('coaching:unknown', 'local_oriskill');
        }
        $count = max(1, required_param('count', PARAM_INT));
        cs::add($creator, (int) $user->id, $action === 'give' ? $count : -$count, optional_param('note', '', PARAM_TEXT),
            (int) $USER->id);
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/coaching.php', get_string('menu:coaching', 'local_oriskill'));

// Assignments waiting for a correction.
$tograde = cs::to_grade($creator);
$rows = '';
foreach ($tograde as $row) {
    $rows .= html_writer::tag('tr', html_writer::tag('td', s($row->learner)) .
        html_writer::tag('td', s($row->assignment) . html_writer::div(s($row->course), 'small text-muted')) .
        html_writer::tag('td', userdate($row->submitted, get_string('strftimedatetimeshort', 'langconfig'))) .
        html_writer::tag('td', html_writer::link(new moodle_url('/mod/assign/view.php', ['id' => $row->cmid, 'action' => 'grader',
            'userid' => $row->userid]), get_string('coaching:grade', 'local_oriskill'), ['class' => 'btn btn-sm btn-primary'])));
}
echo html_writer::div(html_writer::tag('h3', get_string('coaching:tograde', 'local_oriskill', count($tograde)), ['class' => 'h5']) .
    html_writer::tag('p', get_string('coaching:tograde_help', 'local_oriskill'), ['class' => 'small text-muted']) .
    ($rows ? html_writer::tag('table', $rows, ['class' => 'table align-middle']) : html_writer::div(get_string('coaching:nothingtograde',
        'local_oriskill'), 'text-muted')), 'oriskill-card table-responsive mb-4');

// Credits.
$balances = cs::balances($creator);
if ($visible !== null) {
    $balances = array_intersect_key($balances, array_flip($visible));
}
$rows = '';
foreach ($balances as $userid => $credits) {
    $user = core_user::get_user($userid);
    $last = current(cs::history($creator, (int) $userid));
    $rows .= html_writer::tag('tr', html_writer::tag('td', ($user ? fullname($user) : '?') .
        html_writer::div(s($user->email ?? ''), 'small text-muted')) .
        html_writer::tag('td', html_writer::span($credits, 'oriskill-badge ' . ($credits > 0 ? 'is-good' : ''))) .
        html_writer::tag('td', $last ? s(($last->delta > 0 ? '+' : '') . $last->delta . ' · ' . $last->note) .
            html_writer::div(userdate($last->timecreated, get_string('strftimedatetimeshort', 'langconfig')), 'small text-muted') : ''));
}
$form = fn(string $action, string $button, string $class) => html_writer::tag('form',
    html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => $action])) .
    html_writer::div(
        html_writer::empty_tag('input', ['type' => 'email', 'name' => 'email', 'required' => 'required', 'class' => 'form-control',
            'placeholder' => get_string('email'), 'aria-label' => get_string('email')]) .
        html_writer::empty_tag('input', ['type' => 'number', 'name' => 'count', 'value' => 1, 'min' => 1, 'class' => 'form-control',
            'style' => 'width: 6rem', 'aria-label' => get_string('coaching:credits', 'local_oriskill')]) .
        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'note', 'maxlength' => 255, 'class' => 'form-control',
            'placeholder' => get_string('coaching:note', 'local_oriskill'), 'aria-label' => get_string('coaching:note', 'local_oriskill')]) .
        html_writer::tag('button', $button, ['type' => 'submit', 'class' => 'btn ' . $class . ' text-nowrap']), 'd-flex flex-wrap gap-2'),
    ['method' => 'post', 'action' => $url->out(false), 'class' => 'mb-2']);
echo html_writer::div(html_writer::tag('h3', get_string('coaching:creditstitle', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('p', get_string('coaching:credits_help', 'local_oriskill'), ['class' => 'small text-muted']) .
    ($canchange ? html_writer::tag('h4', get_string('coaching:session', 'local_oriskill'), ['class' => 'h6']) .
        $form('spend', get_string('coaching:spend', 'local_oriskill'), 'btn-primary') .
        html_writer::tag('h4', get_string('coaching:give', 'local_oriskill'), ['class' => 'h6 mt-3']) .
        $form('give', get_string('coaching:givebutton', 'local_oriskill'), 'btn-outline-primary') : '') .
    ($rows ? html_writer::tag('table', html_writer::tag('tr', html_writer::tag('th', get_string('learner', 'local_oriskill')) .
        html_writer::tag('th', get_string('coaching:credits', 'local_oriskill')) . html_writer::tag('th',
        get_string('coaching:last', 'local_oriskill'))) . $rows, ['class' => 'table align-middle mt-3'])
        : html_writer::div(get_string('coaching:nocredits', 'local_oriskill'), 'text-muted mt-3')),
    'oriskill-card table-responsive');

echo creator_service::space_end();
echo $OUTPUT->footer();
