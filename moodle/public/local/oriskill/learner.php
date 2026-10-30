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
// A coach opens only the learners they follow.
$mine = \local_oriskill\team_service::visible_learners($creator);
if (!$learner || ($mine !== null && !in_array($userid, $mine, true))) {
    throw new moodle_exception('invaliduserid');
}
$backurl = new moodle_url('/local/oriskill/learners.php', ['courseid' => $course->id]);
$url = new moodle_url('/local/oriskill/learner.php', ['courseid' => $course->id, 'userid' => $userid]);
$dateformat = get_string('strftimedate', 'langconfig');
$canchange = \local_oriskill\team_service::can_change(creator_service::current_role(), '/local/oriskill/learners.php');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    $message = get_string('changessaved');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    $details = [];
    try {
        switch ($action) {
            case 'forcemodules':
                $before = \availability_forcedaccess\condition::opened_sections((int) $course->id, $userid);
                learning_service::set_forced_modules($course, $userid, optional_param_array('sectionids', [], PARAM_INT));
                $after = \availability_forcedaccess\condition::opened_sections((int) $course->id, $userid);
                if ($before == $after) {
                    redirect($url);
                }
                $names = [];
                foreach (learning_service::section_rules($course) as $rule) {
                    if (in_array((int) $rule->section->id, $after)) {
                        $names[] = $rule->name;
                    }
                }
                $details['modules'] = $names ? implode(', ', $names) : get_string('none');
                break;
            case 'setstart':
                $value = optional_param('timestart', '', PARAM_RAW_TRIMMED);
                $start = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) ? make_timestamp($m[1], $m[2], $m[3]) : 0;
                learning_service::set_timestart($course, $userid, $start);
                $details['date'] = userdate($start, $dateformat);
                break;
            case 'resetprogress':
                learning_service::reset_progress($course, $userid);
                $message = get_string('learner:progressreset', 'local_oriskill');
                break;
            default:
                throw new moodle_exception('invalidparameter', 'debug');
        }
        \local_oriskill\log_service::add($creator, 'learner_' . $action, $details, (int) $course->id, $userid);
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect($url, $message, null, $type);
}
$hidden = fn(string $action) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
$instalments = \local_oriskill\instalment_service::course_records((int) $course->id);

// Time statement (CPF): one line per day.
if (optional_param('download', '', PARAM_ALPHA) === 'studytime') {
    \local_oriskill\team_service::export_allowed($creator, get_string('studytime:statement', 'local_oriskill') . ' – ' .
        fullname($learner) . ' – ' . format_string($course->fullname));
    require_once($CFG->libdir . '/csvlib.class.php');
    $timeformat = get_string('strftimetime', 'langconfig');
    $days = \local_oriskill\studytime_service::days((int) $course->id, $userid);
    $rows = [[get_string('date'), get_string('studytime:first', 'local_oriskill'), get_string('studytime:last', 'local_oriskill'),
        get_string('studytime:column', 'local_oriskill'), get_string('studytime:minutescol', 'local_oriskill')]];
    foreach ($days as $day) {
        $rows[] = [userdate($day->day, $dateformat), userdate($day->first, $timeformat), userdate($day->last, $timeformat),
            \local_oriskill\studytime_service::format($day->seconds), round($day->seconds / 60)];
    }
    // Time spent on the previous platform, brought by the migration: its own line, without days.
    $prior = \local_oriskill\contact_import::prior((int) $course->id, $userid);
    if ($prior && $prior->seconds) {
        $rows[] = [get_string('studytime:prior', 'local_oriskill'), '', '', \local_oriskill\studytime_service::format((int) $prior->seconds),
            round($prior->seconds / 60)];
    }
    $total = $learner->studytime + (int) ($prior->seconds ?? 0);
    $rows[] = [get_string('total'), '', '', \local_oriskill\studytime_service::format($total), round($total / 60)];
    csv_export_writer::download_array(clean_filename(get_string('studytime:statement', 'local_oriskill') . '-' .
        fullname($learner) . '-' . $course->shortname), $rows);
    exit;
}

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
    [get_string('learner:since', 'local_oriskill'), !$canchange ? userdate($learner->timestart, $dateformat) :
        html_writer::tag('form', $hidden('setstart') .
            html_writer::empty_tag('input', ['type' => 'date', 'name' => 'timestart', 'required' => 'required',
                'class' => 'form-control form-control-sm', 'value' => userdate($learner->timestart, '%Y-%m-%d', 99, false),
                'aria-label' => get_string('learner:since', 'local_oriskill'), 'onchange' => 'this.form.submit()',
                'title' => get_string('learner:since_help', 'local_oriskill')]),
            ['method' => 'post', 'action' => $url->out(false)])],
    [get_string('learner:until', 'local_oriskill'), $learner->timeend ? userdate($learner->timeend, $dateformat)
        : get_string('learner:unlimited', 'local_oriskill')],
    [get_string('learner:progress', 'local_oriskill'), $learner->progress === null ? '–' : round($learner->progress) . ' %'],
    [get_string('learner:grade', 'local_oriskill'), $learner->grade ?? '–'],
    [get_string('learner:lastaccess', 'local_oriskill'), $learner->lastaccess ? userdate($learner->lastaccess) : get_string('never')],
    [get_string('learner:completed', 'local_oriskill'), $learner->completed ? userdate($learner->completed, $dateformat) : '–'],
    [get_string('studytime:column', 'local_oriskill'), \local_oriskill\studytime_service::format($learner->studytime)],
    ...(($prior = \local_oriskill\contact_import::prior((int) $course->id, $userid)) ? [[get_string('studytime:prior', 'local_oriskill'),
        \local_oriskill\studytime_service::format((int) $prior->seconds) . ($prior->progress !== null
            ? ' · ' . format_float((float) $prior->progress, 0) . ' %' : '')]] : []),
] as [$label, $value]) {
    $facts .= html_writer::div(html_writer::div($label, 'oriskill-stat-label') .
        html_writer::div(str_starts_with($value, '<form') ? $value : s($value), 'fw-semibold'));
}
if (isset($instalments[$userid])) {
    $facts .= html_writer::div(html_writer::div(get_string('learner:payment', 'local_oriskill'), 'oriskill-stat-label') .
        html_writer::div(\local_oriskill\instalment_service::status_badge($instalments[$userid])));
}
$reset = !$canchange ? '' : html_writer::tag('form', $hidden('resetprogress') .
    html_writer::tag('button', '<i class="fa fa-rotate-left me-1" aria-hidden="true"></i>' .
        get_string('learner:resetprogress', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']),
    ['method' => 'post', 'action' => $url->out(false), 'class' => 'mt-3',
     'onsubmit' => 'return confirm(' . json_encode(get_string('learner:resetprogress_confirm', 'local_oriskill',
         fullname($learner))) . ')']);
echo html_writer::div($summary . html_writer::div($facts, 'oriskill-facts') . $reset, 'oriskill-card mb-4');

// Time spent, day by day.
$days = \local_oriskill\studytime_service::days((int) $course->id, $userid);
if ($days) {
    $timeformat = get_string('strftimetime', 'langconfig');
    $table = new html_table();
    $table->attributes['class'] = 'table table-sm align-middle mb-0';
    $table->head = [get_string('date'), get_string('studytime:first', 'local_oriskill'),
        get_string('studytime:last', 'local_oriskill'), get_string('studytime:column', 'local_oriskill')];
    foreach (array_reverse($days) as $day) {
        $table->data[] = [userdate($day->day, $dateformat), userdate($day->first, $timeformat),
            userdate($day->last, $timeformat), \local_oriskill\studytime_service::format($day->seconds)];
    }
    $download = \local_oriskill\team_service::can_export() ? html_writer::link(new moodle_url($url,
        ['download' => 'studytime']), '<i class="fa fa-download me-1" aria-hidden="true"></i>' .
        get_string('studytime:download', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary']) : '';
    echo html_writer::tag('details',
        html_writer::tag('summary', html_writer::span(get_string('studytime:days', 'local_oriskill', (object) [
            'days' => count($days), 'total' => \local_oriskill\studytime_service::format($learner->studytime)]),
            'fw-semibold')) .
        html_writer::div(html_writer::div(get_string('studytime:mode_' .
            \local_oriskill\studytime_service::mode((int) $course->id) . '_help', 'local_oriskill'), 'small text-muted') .
            $download, 'd-flex flex-wrap justify-content-between align-items-center gap-2 my-2') .
        html_writer::div(html_writer::table($table), 'table-responsive', ['style' => 'max-height: 24rem; overflow: auto']),
        ['class' => 'oriskill-card mb-4']);
}

// Modules.
$states = [
    'done' => ['fa-circle-check text-success', 'state:done'],
    'failed' => ['fa-circle-xmark text-danger', 'state:failed'],
    'todo' => ['fa-circle text-muted', 'state:todo'],
];
$modules = learning_service::learner_progress($course, $userid);
$forceable = array_filter($modules, fn($m) => $m->restricted || $m->forced);
if ($canchange && $forceable) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'id' => 'oriskill-forcemodules']) .
        $hidden('forcemodules');
    echo html_writer::div(
        html_writer::div(get_string('learner:forcemodules_help', 'local_oriskill'), 'small text-muted') .
        html_writer::div(
            html_writer::tag('button', get_string('selectall'), ['type' => 'button', 'class' => 'btn btn-sm btn-outline-secondary',
                'onclick' => "document.querySelectorAll('#oriskill-forcemodules input[name=\"sectionids[]\"]')" .
                    ".forEach(c => c.checked = true)"]) .
            html_writer::tag('button', get_string('learner:forcemodules_save', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']),
            'd-flex gap-2 flex-shrink-0'),
        'oriskill-card mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2');
}
foreach ($modules as $module) {
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
    $lock = $module->forced
        ? html_writer::span('<i class="fa fa-key me-1" aria-hidden="true"></i>' .
            get_string('module:forced', 'local_oriskill'), 'oriskill-badge is-good')
        : ($module->available
            ? html_writer::span('<i class="fa fa-lock-open me-1" aria-hidden="true"></i>' .
                get_string('module:open', 'local_oriskill'), 'oriskill-badge is-good')
            : html_writer::span('<i class="fa fa-lock me-1" aria-hidden="true"></i>' .
                get_string('module:locked', 'local_oriskill'), 'oriskill-badge is-warn'));
    if ($canchange && ($module->restricted || $module->forced)) {
        $lock = html_writer::div($lock . html_writer::tag('label',
            html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'sectionids[]', 'value' => $module->sectionid,
                'class' => 'form-check-input me-1'] + ($module->forced ? ['checked' => 'checked'] : [])) .
            get_string('learner:forceaccess', 'local_oriskill'), ['class' => 'small text-nowrap mb-0']),
            'd-flex align-items-center gap-2');
    }
    echo html_writer::div(
        html_writer::div(html_writer::tag('h3', s($module->name), ['class' => 'mb-0']) . $lock,
            'd-flex justify-content-between align-items-center gap-2 mb-2') .
        ($module->availableinfo && !$module->forced ? html_writer::div($module->availableinfo, 'small text-muted mb-2') : '') .
        ($rows ? html_writer::tag('ul', $rows, ['class' => 'oriskill-rows']) : ''),
        'oriskill-card mb-3');
}
if ($canchange && $forceable) {
    echo html_writer::end_tag('form');
}

echo creator_service::space_end();
echo $OUTPUT->footer();
