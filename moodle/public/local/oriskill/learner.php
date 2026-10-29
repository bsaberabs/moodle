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
 * One learner in a trainer's course: access, and progress module by module.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\learning_service;

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$creator = creator_service::require_current_creator(new moodle_url('/local/oriskill/learner.php',
    ['courseid' => $courseid, 'userid' => $userid]), 'menu:learners');
$course = learning_service::require_own_course($creator, $courseid);
$learner = learning_service::learners($creator, $course)[$userid] ?? null;
if (!$learner) {
    throw new moodle_exception('invaliduserid');
}
$backurl = new moodle_url('/local/oriskill/learners.php', ['courseid' => $course->id]);
$dateformat = get_string('strftimedate', 'langconfig');

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/learners.php', fullname($learner));
echo html_writer::div(html_writer::link($backurl, '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' .
    get_string('backtolearners', 'local_oriskill', format_string($course->fullname))), 'mb-3');

// Summary.
$user = core_user::get_user($userid);
$summary = html_writer::div(
    $OUTPUT->user_picture($user, ['size' => 64, 'link' => false, 'class' => 'rounded-circle']) .
    html_writer::div(
        html_writer::div(fullname($learner), 'oriskill-course-title') .
        html_writer::div(s($learner->email), 'text-muted small') .
        html_writer::div(
            html_writer::span(get_string('source:' . $learner->source, 'local_oriskill'),
                'oriskill-badge ' . ($learner->source === 'purchase' ? 'is-good' : '')) . ' ' .
            ($learner->suspended ? html_writer::span(get_string('learner:suspended', 'local_oriskill'), 'oriskill-badge is-warn')
                : ($learner->expired ? html_writer::span(get_string('learner:expired', 'local_oriskill'),
                    'oriskill-badge is-warn') : '')) . ' ' .
            implode(' ', array_map(fn($g) => html_writer::span(format_string($g->name), 'oriskill-badge'), $learner->groups)),
            'mt-1')),
    'd-flex gap-3 align-items-center mb-3');
$facts = '';
foreach ([
    [get_string('learner:since', 'local_oriskill'), userdate($learner->timestart, $dateformat)],
    [get_string('learner:until', 'local_oriskill'), $learner->timeend ? userdate($learner->timeend, $dateformat)
        : get_string('learner:unlimited', 'local_oriskill')],
    [get_string('learner:progress', 'local_oriskill'), $learner->progress === null ? '–' : round($learner->progress) . ' %'],
    [get_string('learner:grade', 'local_oriskill'), $learner->grade ?? '–'],
    [get_string('learner:lastaccess', 'local_oriskill'), $learner->lastaccess ? userdate($learner->lastaccess) : get_string('never')],
    [get_string('learner:completed', 'local_oriskill'), $learner->completed ? userdate($learner->completed, $dateformat) : '–'],
] as [$label, $value]) {
    $facts .= html_writer::div(html_writer::div($label, 'oriskill-stat-label') . html_writer::div(s($value), 'fw-semibold'));
}
echo html_writer::div($summary . html_writer::div($facts, 'oriskill-facts'), 'oriskill-card mb-4');

// Modules.
$states = [
    'done' => ['fa-circle-check text-success', 'state:done'],
    'failed' => ['fa-circle-xmark text-danger', 'state:failed'],
    'todo' => ['fa-circle text-muted', 'state:todo'],
];
foreach (learning_service::learner_progress($course, $userid) as $module) {
    $rows = '';
    foreach ($module->activities as $activity) {
        $state = $activity->state ? $states[$activity->state] : null;
        $rows .= html_writer::tag('li',
            html_writer::div(
                ($state ? '<i class="fa ' . $state[0] . ' fa-fw me-2" title="' . s(get_string($state[1], 'local_oriskill')) .
                    '" aria-hidden="true"></i>' : '<i class="fa fa-minus fa-fw me-2 text-muted" aria-hidden="true"></i>') .
                ($activity->url ? html_writer::link($activity->url, $activity->name) : $activity->name),
                'oriskill-row-main') .
            html_writer::span(($activity->grade !== null ? s($activity->grade) . ' · ' : '') .
                ($state ? get_string($state[1], 'local_oriskill') : get_string('state:notracking', 'local_oriskill')),
                'small text-muted text-nowrap'));
    }
    $lock = $module->available
        ? html_writer::span('<i class="fa fa-lock-open me-1" aria-hidden="true"></i>' .
            get_string('module:open', 'local_oriskill'), 'oriskill-badge is-good')
        : html_writer::span('<i class="fa fa-lock me-1" aria-hidden="true"></i>' .
            get_string('module:locked', 'local_oriskill'), 'oriskill-badge is-warn');
    echo html_writer::div(
        html_writer::div(html_writer::tag('h3', s($module->name), ['class' => 'mb-0']) . $lock,
            'd-flex justify-content-between align-items-center gap-2 mb-2') .
        ($module->availableinfo ? html_writer::div($module->availableinfo, 'small text-muted mb-2') : '') .
        ($rows ? html_writer::tag('ul', $rows, ['class' => 'oriskill-rows']) : ''),
        'oriskill-card mb-3');
}

echo creator_service::space_end();
echo $OUTPUT->footer();
