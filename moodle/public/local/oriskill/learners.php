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
 * Learners of a trainer's course: access (offer, extend, suspend, remove), group, progress, CSV export.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_oriskill\creator_service;
use local_oriskill\learning_service;

$creator = creator_service::require_current_creator(new moodle_url('/local/oriskill/learners.php'), 'menu:learners');
$course = creator_service::selected_course($creator);
$url = new moodle_url('/local/oriskill/learners.php', $course ? ['courseid' => $course->id] : []);
$PAGE->set_url($url);

/**
 * Timestamp of the end of a day entered in a date field (yyyy-mm-dd), 0 when empty.
 *
 * @param string $value Field value.
 * @return int
 */
function local_oriskill_end_of_day(string $value): int {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return 0;
    }
    return make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], 23, 59, 59);
}

$role = creator_service::current_role();
$canchange = \local_oriskill\team_service::can_change($role, '/local/oriskill/learners.php');
$canexport = \local_oriskill\team_service::can_export();
$coaches = \local_oriskill\team_service::coaches($creator);
$coachmap = \local_oriskill\team_service::coach_map($creator);

if ($course && ($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    $userid = optional_param('userid', 0, PARAM_INT);
    $before = $userid ? (learning_service::learners($creator, $course)[$userid] ?? null) : null;
    if ($userid && !$before) {
        throw new moodle_exception('invaliduserid');
    }
    // What the log keeps of a learner's access, to show it before and after.
    $state = fn(?stdClass $learner) => $learner ? [
        get_string('change:access', 'local_oriskill') => get_string($learner->suspended ? 'change:suspended' : 'change:active',
            'local_oriskill'),
        get_string('change:end', 'local_oriskill') => $learner->timeend
            ? userdate($learner->timeend, get_string('strftimedate', 'langconfig')) : get_string('learner:unlimited', 'local_oriskill'),
        get_string('groups') => implode(', ', array_map(fn($g) => format_string($g->name), $learner->groups)) ?: '–',
    ] : [get_string('change:access', 'local_oriskill') => get_string('change:noaccess', 'local_oriskill')];
    $message = get_string('changessaved');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    try {
        switch ($action) {
            case 'offer':
                $email = trim(core_text::strtolower(required_param('email', PARAM_RAW_TRIMMED)));
                $before = ($existing = $DB->get_record('user', ['email' => $email, 'deleted' => 0], 'id', IGNORE_MULTIPLE))
                    ? (learning_service::learners($creator, $course)[$existing->id] ?? null) : null;
                [$user, $created] = learning_service::offer_access($course, required_param('email', PARAM_RAW_TRIMMED),
                    optional_param('firstname', '', PARAM_NOTAGS), optional_param('lastname', '', PARAM_NOTAGS),
                    local_oriskill_end_of_day(optional_param('timeend', '', PARAM_RAW_TRIMMED)),
                    optional_param('groupid', 0, PARAM_INT));
                $message = get_string($created ? 'offered:newaccount' : 'offered', 'local_oriskill', fullname($user));
                break;
            case 'suspend':
            case 'reactivate':
                learning_service::set_suspended($course, $userid, $action === 'suspend');
                break;
            case 'setend':
                learning_service::set_timeend($course, $userid,
                    local_oriskill_end_of_day(optional_param('timeend', '', PARAM_RAW_TRIMMED)));
                break;
            case 'setgroup':
                learning_service::set_learner_group($course, $userid, optional_param('groupid', 0, PARAM_INT));
                break;
            case 'setcoach':
                // Logged by set_coach().
                \local_oriskill\team_service::set_coach($creator, $userid, optional_param('coachid', 0, PARAM_INT));
                redirect($url, $message, null, $type);
            case 'remove':
                learning_service::remove_offered($course, $userid);
                $message = get_string('accessremoved', 'local_oriskill');
                break;
            case 'loginas':
                // Moodle's own "log in as", limited to this course, with its banner and its event in the logs.
                require_capability('moodle/user:loginas', context_course::instance($course->id));
                \local_oriskill\log_service::add($creator, 'learner_loginas', [], (int) $course->id, $userid);
                redirect(new moodle_url('/course/loginas.php', ['id' => $course->id, 'user' => $userid,
                    'sesskey' => sesskey()]));
        }
        $logged = $action === 'offer' ? (int) $user->id : $userid;
        \local_oriskill\log_service::add($creator, 'learner_' . $action, $action === 'setend'
            ? ['date' => ($end = local_oriskill_end_of_day(optional_param('timeend', '', PARAM_RAW_TRIMMED)))
                ? userdate($end, get_string('strftimedate', 'langconfig')) : get_string('learner:unlimited', 'local_oriskill')]
            : [], (int) $course->id, $logged, \local_oriskill\log_service::diff($state($before),
                $state(learning_service::learners($creator, $course)[$logged] ?? null)));
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect($url, $message, null, $type);
}

$learners = $course ? learning_service::learners($creator, $course) : [];
// A coach sees only the learners they follow.
if (($mine = \local_oriskill\team_service::visible_learners($creator)) !== null) {
    $learners = array_intersect_key($learners, array_flip($mine));
}
$groups = $course ? groups_get_all_groups($course->id) : [];
$dateformat = get_string('strftimedate', 'langconfig');
$canloginas = $course && has_capability('moodle/user:loginas', context_course::instance($course->id));

// CSV export.
if ($course && optional_param('download', '', PARAM_ALPHA) === 'csv') {
    \local_oriskill\team_service::export_allowed($creator, get_string('menu:learners', 'local_oriskill') . ' – ' .
        format_string($course->fullname));
    $rows = [[get_string('lastname'), get_string('firstname'), get_string('email'), get_string('learner:access', 'local_oriskill'),
        get_string('status'), get_string('learner:since', 'local_oriskill'), get_string('learner:until', 'local_oriskill'),
        get_string('group'), get_string('learner:progress', 'local_oriskill'), get_string('learner:grade', 'local_oriskill'),
        get_string('learner:lastaccess', 'local_oriskill'), get_string('learner:completed', 'local_oriskill'),
        get_string('studytime:column', 'local_oriskill')]];
    foreach ($learners as $learner) {
        $rows[] = [$learner->lastname, $learner->firstname, $learner->email,
            get_string('source:' . $learner->source, 'local_oriskill'),
            get_string($learner->suspended ? 'learner:suspended' : ($learner->expired ? 'learner:expired' : 'learner:active'),
                'local_oriskill'),
            userdate($learner->timestart, $dateformat),
            $learner->timeend ? userdate($learner->timeend, $dateformat) : get_string('learner:unlimited', 'local_oriskill'),
            implode(', ', array_map(fn($g) => format_string($g->name), $learner->groups)),
            $learner->progress === null ? '' : round($learner->progress) . ' %',
            $learner->grade ?? '',
            $learner->lastaccess ? userdate($learner->lastaccess, $dateformat) : get_string('never'),
            $learner->completed ? userdate($learner->completed, $dateformat) : '',
            \local_oriskill\studytime_service::format($learner->studytime)];
    }
    csv_export_writer::download_array(clean_filename(get_string('menu:learners', 'local_oriskill') . '-' .
        $course->shortname), $rows);
    exit;
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/learners.php', get_string('menu:learners', 'local_oriskill'));
if (!$course) {
    echo html_writer::div('<i class="fa fa-users" aria-hidden="true"></i>' . get_string('nocoursesyet', 'local_oriskill',
        (new moodle_url('/course/edit.php', ['category' => $creator->categoryid]))->out(false)), 'oriskill-card oriskill-empty');
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}
echo creator_service::course_picker($creator, $course, new moodle_url('/local/oriskill/learners.php'));

// Key figures.
$active = array_filter($learners, fn($l) => !$l->suspended && !$l->expired);
$progresses = array_filter(array_map(fn($l) => $l->progress, $active), fn($p) => $p !== null);
$stats = '';
foreach ([
    ['fa-users', count($learners), get_string('stat:learnerstotal', 'local_oriskill')],
    ['fa-user-check', count($active), get_string('stat:learnersactive', 'local_oriskill')],
    ['fa-chart-simple', $progresses ? round(array_sum($progresses) / count($progresses)) . ' %' : '–',
        get_string('stat:avgprogress', 'local_oriskill')],
    ['fa-award', count(array_filter($learners, fn($l) => $l->completed)), get_string('stat:completed', 'local_oriskill')],
] as [$icon, $value, $label]) {
    $stats .= html_writer::div(
        html_writer::span('<i class="fa ' . $icon . '" aria-hidden="true"></i>', 'oriskill-stat-icon') .
        html_writer::div(html_writer::div(s($value), 'oriskill-stat-value') . html_writer::div($label, 'oriskill-stat-label')),
        'oriskill-card oriskill-stat');
}
echo html_writer::div($stats, 'oriskill-stats');

$groupoptions = [0 => get_string('learner:nogroup', 'local_oriskill')];
foreach ($groups as $group) {
    $groupoptions[$group->id] = format_string($group->name);
}

if ($canchange) {
    // Offer an access.
    $field =fn(string $label, string $input, string $class = 'col-md-6') =>
        html_writer::div(html_writer::tag('label', $label, ['class' => 'form-label small fw-semibold']) . $input, $class);
    $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'class' => 'row g-3']) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'offer']) .
        $field(get_string('email'), html_writer::empty_tag('input', ['type' => 'email', 'name' => 'email', 'required' => 'required',
            'class' => 'form-control'])) .
        $field(get_string('learner:until', 'local_oriskill'), html_writer::empty_tag('input', ['type' => 'date', 'name' => 'timeend',
            'class' => 'form-control']) . html_writer::div(get_string('learner:until_help', 'local_oriskill'), 'form-text'),
            'col-md-3') .
        $field(get_string('group'), html_writer::select($groupoptions, 'groupid', 0, false, ['class' => 'form-select']), 'col-md-3') .
        $field(get_string('firstname'), html_writer::empty_tag('input', ['type' => 'text', 'name' => 'firstname',
            'class' => 'form-control'])) .
        $field(get_string('lastname'), html_writer::empty_tag('input', ['type' => 'text', 'name' => 'lastname',
            'class' => 'form-control'])) .
        html_writer::div(html_writer::div(get_string('offer_help', 'local_oriskill'), 'form-text mb-2') .
            html_writer::tag('button', '<i class="fa fa-user-plus me-1" aria-hidden="true"></i>' .
                get_string('offeraccess', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']), 'col-12') .
        html_writer::end_tag('form');
    echo html_writer::tag('details',
        html_writer::tag('summary', '<i class="fa fa-user-plus me-2" aria-hidden="true"></i>' .
            get_string('offeraccess', 'local_oriskill'), ['class' => 'fw-semibold']) .
        html_writer::div($form, 'mt-3'),
        ['class' => 'oriskill-card mb-4', 'open' => $learners ? null : 'open']);
}

if (!$learners) {
    echo html_writer::div('<i class="fa fa-user-graduate" aria-hidden="true"></i>' .
        get_string('nolearners', 'local_oriskill'), 'oriskill-card oriskill-empty');
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}

// Learners table.
$hidden = fn(string $action, int $userid) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'userid', 'value' => $userid]);
$table = new html_table();
$table->attributes['class'] = 'table oriskill-learners align-middle';
$table->head = [get_string('learner', 'local_oriskill'), get_string('learner:access', 'local_oriskill'),
    get_string('learner:until', 'local_oriskill'), get_string('group'), get_string('learner:progress', 'local_oriskill'),
    get_string('learner:grade', 'local_oriskill'), get_string('learner:lastaccess', 'local_oriskill'), ''];
$coachoptions = [0 => get_string('learner:nocoach', 'local_oriskill')] + array_map('fullname', $coaches);
if ($coaches) {
    array_splice($table->head, 4, 0, [get_string('role:coach', 'local_oriskill')]);
}
$instalments = \local_oriskill\instalment_service::course_records((int) $course->id);
foreach ($learners as $userid => $learner) {
    $ficheurl = new moodle_url('/local/oriskill/learner.php', ['courseid' => $course->id, 'userid' => $userid]);
    $badges = html_writer::span(get_string('source:' . $learner->source, 'local_oriskill'),
        'oriskill-badge ' . ($learner->source === 'purchase' ? 'is-good' : ''));
    if ($learner->suspended) {
        $badges .= ' ' . html_writer::span(get_string('learner:suspended', 'local_oriskill'), 'oriskill-badge is-warn');
    } else if ($learner->expired) {
        $badges .= ' ' . html_writer::span(get_string('learner:expired', 'local_oriskill'), 'oriskill-badge is-warn');
    }
    if ($learner->completed) {
        $badges .= ' ' . html_writer::span(get_string('learner:completed', 'local_oriskill'), 'oriskill-badge is-good');
    }
    if (isset($instalments[$userid])) {
        $badges .= ' ' . \local_oriskill\instalment_service::status_badge($instalments[$userid]);
    }
    $endform = html_writer::tag('form', $hidden('setend', $userid) .
        html_writer::empty_tag('input', ['type' => 'date', 'name' => 'timeend', 'class' => 'form-control form-control-sm',
            'value' => $learner->timeend ? userdate($learner->timeend, '%Y-%m-%d', 99, false) : '',
            'aria-label' => get_string('learner:until', 'local_oriskill'), 'onchange' => 'this.form.submit()']),
        ['method' => 'post', 'action' => $url->out(false)]);
    $current = $learner->groups ? (int) reset($learner->groups)->id : 0;
    $groupform = html_writer::tag('form', $hidden('setgroup', $userid) .
        html_writer::select($groupoptions, 'groupid', $current, false, ['class' => 'form-select form-select-sm',
            'aria-label' => get_string('group'), 'onchange' => 'this.form.submit()']),
        ['method' => 'post', 'action' => $url->out(false)]);
    $coachform = html_writer::tag('form', $hidden('setcoach', $userid) .
        html_writer::select($coachoptions, 'coachid', $coachmap[$userid] ?? 0, false, ['class' => 'form-select form-select-sm',
            'aria-label' => get_string('role:coach', 'local_oriskill'), 'onchange' => 'this.form.submit()']),
        ['method' => 'post', 'action' => $url->out(false)]);
    if (!$canchange) {
        // Looking only: the values, not the forms.
        $endform = $learner->timeend ? userdate($learner->timeend, $dateformat) : get_string('learner:unlimited', 'local_oriskill');
        $groupform = $learner->groups ? s(format_string(reset($learner->groups)->name)) : '–';
        $coachform = s($coachoptions[$coachmap[$userid] ?? 0] ?? '–');
    }
    $progress = $learner->progress === null ? '–' :
        html_writer::div(html_writer::span('', '', ['style' => 'width: ' . round($learner->progress) . '%']),
            'oriskill-progress mb-1') . html_writer::span(round($learner->progress) . ' %', 'small text-muted');
    if ($learner->studytime) {
        $progress .= html_writer::div('<i class="fa fa-stopwatch me-1" aria-hidden="true"></i>' .
            \local_oriskill\studytime_service::format($learner->studytime), 'small text-muted',
            ['title' => get_string('studytime:column', 'local_oriskill')]);
    }
    $actions = html_writer::link($ficheurl, get_string('learner:details', 'local_oriskill'),
        ['class' => 'btn btn-sm btn-outline-primary']);
    if ($canchange && $canloginas && !$learner->suspended && !is_siteadmin($userid)) {
        $actions .= html_writer::tag('form', $hidden('loginas', $userid) .
            html_writer::tag('button', get_string('learner:loginas', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']),
            ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline',
             'onsubmit' => 'return confirm(' . json_encode(get_string('learner:loginas_confirm', 'local_oriskill',
                 fullname($learner))) . ')']);
    }
    $toggle = $learner->suspended ? 'reactivate' : 'suspend';
    $actions .= !$canchange ? '' : html_writer::tag('form', $hidden($toggle, $userid) .
        html_writer::tag('button', get_string('learner:' . $toggle, 'local_oriskill'),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']),
        ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']);
    if ($canchange && $learner->source === 'offered') {
        $actions .= html_writer::tag('form', $hidden('remove', $userid) .
            html_writer::tag('button', get_string('learner:remove', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']),
            ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline',
             'onsubmit' => 'return confirm(' . json_encode(get_string('learner:remove_confirm', 'local_oriskill',
                 fullname($learner))) . ')']);
    }
    $table->data[] = [
        html_writer::link($ficheurl, fullname($learner), ['class' => 'fw-semibold']) .
            html_writer::div(s($learner->email), 'small text-muted') .
            html_writer::div(get_string('learner:sincedate', 'local_oriskill', userdate($learner->timestart, $dateformat)),
                'small text-muted'),
        $badges,
        $endform,
        $groupform,
        ...($coaches ? [$coachform] : []),
        $progress,
        $learner->grade ?? '–',
        $learner->lastaccess ? html_writer::span(format_time(time() - $learner->lastaccess), '',
            ['title' => userdate($learner->lastaccess)]) : get_string('never'),
        html_writer::div($actions, 'd-flex flex-wrap gap-1'),
    ];
}
echo html_writer::div(
    html_writer::div(html_writer::tag('h3', get_string('learnerscount', 'local_oriskill', count($learners)), ['class' => 'mb-0']) .
        ($canexport ? html_writer::link(new moodle_url($url, ['download' => 'csv']),
            '<i class="fa fa-download me-1" aria-hidden="true"></i>' . get_string('exportcsv', 'local_oriskill'),
            ['class' => 'btn btn-sm btn-outline-secondary']) : ''),
        'd-flex justify-content-between align-items-center mb-3') .
    html_writer::div(html_writer::table($table), 'table-responsive'),
    'oriskill-card');

echo creator_service::space_end();
echo $OUTPUT->footer();
