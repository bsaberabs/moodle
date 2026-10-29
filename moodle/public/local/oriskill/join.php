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
 * Invitation link: the person logs in (or creates their account), then joins the trainer's team.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_oriskill\team_service;

$token = required_param('token', PARAM_ALPHANUM);
$url = new moodle_url('/local/oriskill/join.php', ['token' => $token]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title(get_string('team:jointitle', 'local_oriskill'));
$PAGE->set_heading(get_string('team:jointitle', 'local_oriskill'));

$member = team_service::invitation($token);
if (!$member) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('team:invalidlink', 'local_oriskill'), 'warning');
    echo $OUTPUT->footer();
    exit;
}
$creator = $DB->get_record('local_oriskill_creators', ['id' => $member->creatorid], '*', MUST_EXIST);
$a = (object) ['trainer' => format_string($creator->displayname),
    'role' => get_string('role:' . $member->role, 'local_oriskill'),
    'desc' => get_string('role:' . $member->role . '_desc', 'local_oriskill')];
if (!isloggedin() || isguestuser()) {
    local_oriskill_visitor_page($url, html_writer::tag('p', get_string('team:joinintro', 'local_oriskill', $a), ['class' => 'lead']));
}
require_login(null, false);

if (optional_param('accept', 0, PARAM_BOOL) && confirm_sesskey()) {
    try {
        team_service::accept($member);
        redirect(new moodle_url('/local/oriskill/dashboard.php'), get_string('team:joined', 'local_oriskill', $a->trainer),
            null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect(new moodle_url('/local/oriskill/dashboard.php'), $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('card mx-auto', ['style' => 'max-width: 36rem']);
echo html_writer::start_div('card-body');
echo html_writer::tag('p', get_string('team:joinintro', 'local_oriskill', $a), ['class' => 'lead']);
echo html_writer::tag('p', get_string('team:joinas', 'local_oriskill', fullname($USER)), ['class' => 'text-muted']);
echo html_writer::tag('form',
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'accept', 'value' => 1]) .
    html_writer::tag('button', get_string('team:accept', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
