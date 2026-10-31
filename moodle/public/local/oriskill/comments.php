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
 * Comments of the learners under the lessons, for the team: to handle, answer, mark done, hide, delete; turn the
 * comments on or off course by course.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\comment_service as cs;
use local_oriskill\creator_service;
use local_oriskill\team_service;

$view = optional_param('view', 'new', PARAM_ALPHA);
$view = in_array($view, cs::VIEWS, true) ? $view : 'new';
$courseid = optional_param('courseid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$url = new moodle_url('/local/oriskill/comments.php', array_filter(['view' => $view, 'courseid' => $courseid]));
$creator = creator_service::require_current_creator($url, 'menu:comments');
$role = creator_service::current_role();
$canchange = team_service::can_change($role, cs::PAGE);
// Turning the comments on or off is for those who set up the courses.
$cansetup = team_service::can($role, '/local/oriskill/access.php');
$courses = $DB->get_records('course', ['category' => $creator->categoryid], 'sortorder', 'id, fullname, category');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if ($action === 'switch') {
        $course = $courses[required_param('course', PARAM_INT)] ?? null;
        if (!$cansetup || !$course) {
            throw new moodle_exception('error:notallowed', 'local_oriskill');
        }
        cs::set_enabled($creator, $course, (bool) required_param('on', PARAM_BOOL));
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    $comment = cs::require_for_team($creator, required_param('id', PARAM_INT));
    try {
        if ($action === 'reply') {
            $course = get_course($comment->courseid);
            $cm = get_fast_modinfo($course)->get_cm($comment->cmid);
            cs::add($creator, $course, $cm, (int) $USER->id, required_param('message', PARAM_TEXT), (int) $comment->id);
            $message = get_string('comments:answered', 'local_oriskill');
        } else if ($action === 'done' || $action === 'todo') {
            cs::set_status($comment, $action === 'done' ? 'done' : 'new');
        } else if ($action === 'hide' || $action === 'show') {
            cs::set_hidden($comment, $action === 'hide');
        } else if ($action === 'delete') {
            cs::delete($comment);
            \local_oriskill\log_service::add($creator, 'comment_deleted', [], (int) $comment->courseid, (int) $comment->userid,
                [get_string('comments:title', 'local_oriskill') => [$comment->message, '–']]);
        }
        redirect(new moodle_url($url, ['page' => $page]), $message ?? get_string('changessaved'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$post = fn(stdClass $c, string $action, string $label, string $class, string $confirm = '') => html_writer::tag('form',
    html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => $action, 'id' => $c->id,
        'page' => $page])) .
    html_writer::tag('button', $label, ['type' => 'submit', 'class' => 'btn btn-sm ' . $class]),
    ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline'] +
    ($confirm !== '' ? ['onsubmit' => 'return confirm(' . json_encode($confirm) . ')'] : []));
$date = fn(int $time) => userdate($time, get_string('strftimedatetimeshort', 'langconfig'));
$users = [];
$name = function(stdClass $c) use (&$users, $creator) {
    $users[$c->userid] = $users[$c->userid] ?? core_user::get_user($c->userid);
    return $c->fromteam ? cs::team_name($creator, $users[$c->userid]) : ($users[$c->userid] ? fullname($users[$c->userid]) : '?');
};

echo $OUTPUT->header();
echo creator_service::space_start($creator, cs::PAGE, get_string('menu:comments', 'local_oriskill'));
echo html_writer::tag('p', get_string('comments:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Views and course filter.
$pending = cs::pending($creator);
$tabs = '';
foreach (cs::VIEWS as $tab) {
    $label = get_string('comments:view_' . $tab, 'local_oriskill') . ($tab === 'new' && $pending ? ' (' . $pending . ')' : '');
    $tabs .= html_writer::tag('li', html_writer::link(new moodle_url($url, ['view' => $tab]), $label,
        ['class' => 'nav-link' . ($tab === $view ? ' active' : '')]), ['class' => 'nav-item']);
}
echo html_writer::tag('ul', $tabs, ['class' => 'nav nav-tabs mb-3']);
echo html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url), ['courseid']) .
    html_writer::select(array_map(fn($c) => format_string($c->fullname), $courses), 'courseid', $courseid,
        ['' => get_string('comments:allcourses', 'local_oriskill')], ['class' => 'form-select', 'onchange' => 'this.form.submit()',
            'aria-label' => get_string('course')]),
    ['method' => 'get', 'action' => $url->out(false), 'class' => 'mb-3', 'style' => 'max-width: 28rem']);

[$comments, $total] = cs::list($creator, $view, $courseid, $page);
if (!$comments) {
    echo html_writer::div('<i class="fa fa-comments" aria-hidden="true"></i>' . get_string('comments:none', 'local_oriskill'),
        'oriskill-card oriskill-empty');
}
$modinfos = [];
foreach ($comments as $c) {
    $modinfos[$c->courseid] = $modinfos[$c->courseid] ?? get_fast_modinfo($c->courseid);
    $cm = $modinfos[$c->courseid]->cms[$c->cmid] ?? null;
    $where = html_writer::link(cs::lesson_url((int) $c->cmid), format_string($courses[$c->courseid]->fullname ?? '') .
        ' › ' . ($cm ? format_string($cm->name) : '?'), ['target' => '_blank']);
    $answers = '';
    foreach ($c->answers as $a) {
        $answers .= html_writer::div(html_writer::div(html_writer::span(s($name($a)), 'fw-semibold') .
            ($a->fromteam ? ' ' . html_writer::span(get_string('comments:team', 'local_oriskill'), 'oriskill-badge is-good') : '') .
            html_writer::span(' · ' . $date((int) $a->timecreated), 'small text-muted')) .
            html_writer::div(nl2br(s($a->message))), 'border-start ps-3 ms-3 mt-2');
    }
    $actions = '';
    if ($canchange) {
        $actions = html_writer::tag('details', html_writer::tag('summary', get_string('comments:reply', 'local_oriskill'),
                ['class' => 'btn btn-sm btn-primary']) .
            html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(),
                'action' => 'reply', 'id' => $c->id, 'page' => $page])) .
                html_writer::tag('label', get_string('comments:reply', 'local_oriskill'), ['for' => 'reply-' . $c->id,
                    'class' => 'visually-hidden']) .
                html_writer::tag('textarea', '', ['name' => 'message', 'id' => 'reply-' . $c->id, 'rows' => 3,
                    'required' => 'required', 'class' => 'form-control mb-2']) .
                html_writer::div(get_string('comments:reply_help', 'local_oriskill'), 'small text-muted mb-2') .
                html_writer::tag('button', get_string('comments:send', 'local_oriskill'), ['type' => 'submit',
                    'class' => 'btn btn-sm btn-primary']),
                ['method' => 'post', 'action' => $url->out(false), 'class' => 'mt-2']), ['class' => 'd-inline-block me-1']) .
            ($c->status === 'new' ? $post($c, 'done', get_string('comments:markdone', 'local_oriskill'), 'btn-outline-secondary')
                : $post($c, 'todo', get_string('comments:marktodo', 'local_oriskill'), 'btn-outline-secondary')) . ' ' .
            $post($c, $c->hidden ? 'show' : 'hide', get_string($c->hidden ? 'comments:show' : 'comments:hide', 'local_oriskill'),
                'btn-outline-secondary') . ' ' .
            $post($c, 'delete', get_string('delete'), 'btn-outline-danger', get_string('comments:deleteconfirm', 'local_oriskill'));
    }
    echo html_writer::div(
        html_writer::div(html_writer::div(html_writer::span(s($name($c)), 'fw-semibold') . html_writer::span(' · ' .
            $date((int) $c->timecreated), 'small text-muted') . html_writer::div($where, 'small')) .
            html_writer::div(($c->hidden ? html_writer::span(get_string('comments:hiddenbadge', 'local_oriskill'), 'oriskill-badge')
                : html_writer::span(get_string('comments:status_' . $c->status, 'local_oriskill'),
                    'oriskill-badge ' . ($c->status === 'new' ? 'is-warn' : 'is-good')))),
            'd-flex justify-content-between align-items-start gap-2 mb-2') .
        html_writer::div(nl2br(s($c->message))) . $answers .
        ($actions ? html_writer::div($actions, 'mt-3 d-flex flex-wrap align-items-start gap-1') : ''),
        'oriskill-card mb-2');
}
echo $OUTPUT->paging_bar($total, $page, cs::PERPAGE, $url);

// Comments turned on, course by course.
if ($cansetup && $courses) {
    $rows = '';
    foreach ($courses as $course) {
        $on = cs::enabled((int) $course->id);
        $rows .= html_writer::tag('li', html_writer::div(format_string($course->fullname), 'oriskill-row-main') .
            html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(),
                'action' => 'switch', 'course' => $course->id, 'on' => $on ? 0 : 1])) .
                html_writer::span(get_string($on ? 'comments:on' : 'comments:off', 'local_oriskill'),
                    'oriskill-badge me-2 ' . ($on ? 'is-good' : '')) .
                html_writer::tag('button', get_string($on ? 'comments:turnoff' : 'comments:turnon', 'local_oriskill'),
                    ['type' => 'submit', 'class' => 'btn btn-sm ' . ($on ? 'btn-outline-secondary' : 'btn-primary')]),
                ['method' => 'post', 'action' => $url->out(false)]));
    }
    echo html_writer::div(html_writer::tag('h3', get_string('comments:settings', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::tag('p', get_string('comments:settings_help', 'local_oriskill'), ['class' => 'text-muted small']) .
        html_writer::tag('ul', $rows, ['class' => 'oriskill-rows']), 'oriskill-card mt-4');
}

echo creator_service::space_end();
echo $OUTPUT->footer();
