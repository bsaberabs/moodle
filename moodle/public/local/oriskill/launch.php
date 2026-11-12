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
 * A training locked until its launch date, as its learners see it: "Opens on dd/mm/yyyy", in the trainer's brand.
 * Once the date has come (and the release dates are moved), it leads to the training.
 *
 * Only the login to the site is required here: requiring the course would send the learner back to this page.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\launch_service;
use local_oriskill\seller_service;

$id = required_param('id', PARAM_INT);
$url = new moodle_url('/local/oriskill/launch.php', ['id' => $id]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
require_login(null, false);

$course = $DB->get_record('course', ['id' => $id]);
if (!$course || $course->id == SITEID) {
    throw new moodle_exception('invalidcourseid');
}
$context = context_course::instance($course->id);
$staff = launch_service::is_staff($context);
$enrolled = is_enrolled($context, $USER, '', true);
// The trainer previewing the training as a learner (switched role: only the team may switch roles), even hidden.
$preview = is_role_switched($course->id);
// A learner of the training, or anyone for a training shown in the showcase (on sale before its launch); a hidden
// training only for the team.
if (!$staff && !$preview && !$course->visible) {
    throw new moodle_exception('coursehidden', '', (new moodle_url('/'))->out(false));
}
$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
if (!launch_service::is_locked((int) $course->id)) {
    redirect($courseurl);
}
// The date has come: the release dates of the learners enrolled before it are moved, then the training opens.
$until = (int) launch_service::get((int) $course->id)->launchdate;
$due = $until <= \core\di::get(\core\clock::class)->time();
if ($due && launch_service::try_process((int) $course->id)) {
    redirect($courseurl);
}

// Same brand and header menu as the pages of the training (context of the site: nothing of the course is opened here).
$PAGE->set_course($course);
$PAGE->set_show_course_index(false);
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('oriskill-launch-page');
$name = format_string($course->fullname, true, ['context' => $context]);
$PAGE->set_title($name);
$PAGE->set_heading($name);

$date = launch_service::format_date($until);
$buttons = html_writer::link(new moodle_url('/local/moderncommerce/learner/index.php'), get_string('launch:back', 'local_oriskill'),
    ['class' => 'btn btn-primary']);
$creator = seller_service::owner_of_course((int) $course->id);
if (!$enrolled && !$staff && !$preview && $creator) {
    $buttons .= ' ' . html_writer::link(creator_service::course_page_url($creator, (int) $course->id),
        get_string('launch:seecourse', 'local_oriskill'), ['class' => 'btn btn-outline-secondary']);
}
if ($preview) {
    // The trainer previewing the training as a learner: back to their own role (and to the content).
    $buttons .= ' ' . html_writer::link(new moodle_url('/course/switchrole.php', ['id' => $course->id, 'switchrole' => 0,
        'sesskey' => sesskey(), 'returnurl' => $courseurl->out_as_local_url(false)]),
        get_string('launch:switchback', 'local_oriskill'), ['class' => 'btn btn-outline-secondary']);
} else if ($staff) {
    $buttons .= ' ' . html_writer::link($courseurl, get_string('launch:open', 'local_oriskill'),
        ['class' => 'btn btn-outline-secondary']);
}

echo $OUTPUT->header();
echo html_writer::div(
    html_writer::div('<i class="fa fa-calendar-days fa-2x" aria-hidden="true"></i>', 'mb-2') .
    html_writer::tag('p', s(get_string('launch:opens', 'local_oriskill', $date)), ['class' => 'h3 fw-bold mb-3']) .
    html_writer::tag('p', s(get_string($enrolled || $staff || $preview ? 'launch:intro' : 'launch:notenrolled', 'local_oriskill',
        $date))) .
    // A hidden training (seen by the team only) does not open on its date: it waits until it is shown.
    (!$course->visible ? $OUTPUT->notification(get_string('launch:hiddennote', 'local_oriskill'), 'warning', false)
        : ($due ? $OUTPUT->notification(get_string('launch:opening', 'local_oriskill'), 'info', false) : '')) .
    ($staff ? html_writer::tag('p', get_string('launch:staffnote', 'local_oriskill'), ['class' => 'small text-muted']) : '') .
    html_writer::div($buttons, 'd-flex flex-wrap justify-content-center gap-2 mt-3'),
    'oriskill-card oriskill-launch text-center mx-auto', ['style' => 'max-width: 40rem']);
echo $OUTPUT->footer();
