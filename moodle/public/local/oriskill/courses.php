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
 * The trainer's courses, one line each: content, state (turned on or off here), members, sales, tickets to handle,
 * favourites; preview as a learner, edit the content, settings, duplicate, delete.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core_course\external\course_summary_exporter;
use local_moderncommerce\services\pricing_service;
use local_oriskill\course_list;
use local_oriskill\creator_service;
use local_oriskill\stats_service;
use local_oriskill\team_service;

$filters = [
    'q' => optional_param('q', '', PARAM_TEXT),
    'state' => optional_param('state', '', PARAM_ALPHA),
    'favourites' => optional_param('favourites', 0, PARAM_BOOL),
    'sort' => optional_param('sort', 'name', PARAM_ALPHA),
    'dir' => optional_param('dir', 'asc', PARAM_ALPHA),
];
$url = new moodle_url('/local/oriskill/courses.php', array_filter($filters, fn($v) => $v !== '' && $v !== 0 && $v !== false));
$creator = creator_service::require_current_creator($url, 'mycourses');
$role = creator_service::current_role();
$canchange = team_service::can_change($role, '/local/oriskill/courses.php');
$candelete = in_array($role, ['owner', 'admin'], true);

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    $course = course_list::require_course($creator, required_param('courseid', PARAM_INT));
    // Favourites are one's own: anyone who sees the list pins courses.
    if ($action === 'favourite') {
        course_list::toggle_favourite((int) $course->id);
        redirect($url);
    }
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    $message = get_string('changessaved');
    try {
        switch ($action) {
            case 'show':
            case 'hide':
                course_list::set_visible($creator, $course, $action === 'show');
                break;
            case 'duplicate':
            case 'duplicatemembers':
                core_php_time_limit::raise(600);
                course_list::duplicate($creator, $course, $action === 'duplicatemembers');
                $message = get_string('courses:duplicated', 'local_oriskill', format_string($course->fullname));
                break;
            case 'delete':
                if (!$candelete) {
                    throw new moodle_exception('error:notallowed', 'local_oriskill');
                }
                core_php_time_limit::raise(600);
                course_list::delete($creator, $course);
                $message = get_string('courses:deleted', 'local_oriskill', format_string($course->fullname));
                break;
        }
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$rows = course_list::rows($creator, $filters);
$newcourseurl = new moodle_url('/course/edit.php', ['category' => $creator->categoryid]);
$studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);
$currency = stats_service::currency();
$post = fn(string $action, int $courseid, string $label, string $class, ?string $confirm = null) =>
    html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]) .
        html_writer::tag('button', $label, ['type' => 'submit', 'class' => $class]),
        ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline',
         'onsubmit' => $confirm ? 'return confirm(' . json_encode($confirm) . ')' : null]);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/courses.php', get_string('mycourses', 'local_oriskill'));

// Search, filters, new course.
$states = ['' => get_string('courses:allstates', 'local_oriskill'), 'on' => get_string('courses:on', 'local_oriskill'),
    'off' => get_string('courses:off', 'local_oriskill')];
echo html_writer::div(
    html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $filters['q'], 'class' => 'form-control',
            'placeholder' => get_string('courses:search', 'local_oriskill'), 'aria-label' => get_string('search')]) .
        html_writer::select($states, 'state', $filters['state'], false, ['class' => 'form-select', 'style' => 'width: auto',
            'aria-label' => get_string('courses:state', 'local_oriskill')]) .
        html_writer::div(html_writer::checkbox('favourites', 1, (bool) $filters['favourites'],
            get_string('courses:favouritesonly', 'local_oriskill'), ['class' => 'form-check-input']), 'form-check mb-0') .
        html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-outline-secondary']),
        ['method' => 'get', 'action' => (new moodle_url('/local/oriskill/courses.php'))->out(false),
         'class' => 'd-flex flex-wrap align-items-center gap-2']) .
    html_writer::link($newcourseurl, '<i class="fa fa-plus me-1" aria-hidden="true"></i>' .
        get_string('createcourse', 'local_oriskill'), ['class' => 'btn btn-primary']),
    'd-flex flex-wrap justify-content-between align-items-center gap-3 mb-3');

if (!$rows) {
    echo html_writer::div('<i class="fa fa-graduation-cap" aria-hidden="true"></i>' . ($filters['q'] !== '' || $filters['state']
        || $filters['favourites'] ? get_string('courses:nomatch', 'local_oriskill')
        : get_string('nocoursesyet', 'local_oriskill', $newcourseurl->out(false))), 'oriskill-card oriskill-empty');
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}

// Column headers that sort the list.
$header = function(string $sort, string $label) use ($url, $filters) {
    $dir = $filters['sort'] === $sort && $filters['dir'] === 'asc' ? 'desc' : 'asc';
    $arrow = $filters['sort'] === $sort ? ($filters['dir'] === 'asc' ? ' ↑' : ' ↓') : '';
    return html_writer::link(new moodle_url($url, ['sort' => $sort, 'dir' => $dir]), $label . $arrow);
};
$table = new html_table();
$table->attributes['class'] = 'table align-middle oriskill-courselist';
$table->head = [$header('name', get_string('course')), get_string('courses:state', 'local_oriskill'),
    $header('members', get_string('courses:members', 'local_oriskill')),
    $header('revenue', get_string('courses:sales', 'local_oriskill')), get_string('courses:tohandle', 'local_oriskill'), ''];
foreach ($rows as $courseid => $row) {
    $course = $row->course;
    $image = course_summary_exporter::get_course_image($course) ?: $OUTPUT->get_generated_image_for_id($courseid);
    $name = format_string($course->fullname);
    $star = $post('favourite', $courseid, '<i class="fa' . ($row->favourite ? ' fa-star text-warning'
        : 'r fa-star text-muted') . '" aria-hidden="true"></i><span class="visually-hidden">' .
        get_string($row->favourite ? 'courses:unpin' : 'courses:pin', 'local_oriskill') . '</span>',
        'btn btn-link p-0 me-2');
    $avatars = '';
    foreach ($row->latest as $user) {
        $avatars .= $OUTPUT->user_picture($user, ['size' => 28, 'link' => false, 'class' => 'rounded-circle border border-2 border-white',
            'alttext' => true]);
    }
    $revenue = $row->revenue ? stats_service::money($row->revenue) : pricing_service::format_price(0, $currency);
    $preview = new moodle_url('/course/switchrole.php', ['id' => $courseid, 'switchrole' => $studentroleid,
        'returnurl' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out_as_local_url(false), 'sesskey' => sesskey()]);
    $menu = [
        [new moodle_url('/course/edit.php', ['id' => $courseid]), get_string('courses:settings', 'local_oriskill')],
        [new moodle_url('/local/oriskill/learners.php', ['courseid' => $courseid]), get_string('course:learners', 'local_oriskill')],
        [new moodle_url('/local/oriskill/access.php', ['courseid' => $courseid]), get_string('course:access', 'local_oriskill')],
        [new moodle_url('/local/oriskill/pricing.php'), get_string('pricing', 'local_oriskill')],
    ];
    $items = '';
    foreach ($menu as [$link, $label]) {
        if (team_service::can($role, $link->get_path(false)) || str_starts_with($link->get_path(false), '/course/')) {
            $items .= html_writer::tag('li', html_writer::link($link, $label, ['class' => 'dropdown-item']));
        }
    }
    if ($canchange) {
        $items .= html_writer::tag('li', html_writer::empty_tag('hr', ['class' => 'dropdown-divider'])) .
            html_writer::tag('li', $post('duplicate', $courseid, get_string('courses:duplicate', 'local_oriskill'), 'dropdown-item')) .
            html_writer::tag('li', $post('duplicatemembers', $courseid, get_string('courses:duplicatemembers', 'local_oriskill'),
                'dropdown-item'));
    }
    if ($candelete) {
        $items .= html_writer::tag('li', $post('delete', $courseid, get_string('delete'), 'dropdown-item text-danger',
            get_string('courses:delete_confirm', 'local_oriskill', (object) ['name' => $name, 'members' => $row->members])));
    }
    $table->data[] = [
        html_writer::div($star .
            html_writer::div('', 'oriskill-courselist-thumb', ['style' => 'background-image: url("' . s($image) . '")']) .
            html_writer::div(html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), $name, ['class' => 'fw-semibold']) .
                html_writer::div(get_string('courses:content', 'local_oriskill', (object) ['modules' => $row->modules,
                    'activities' => $row->activities]) . ($row->amount !== null ? ' · ' . pricing_service::format_price($row->amount) : ''),
                    'small text-muted')),
            'd-flex align-items-center gap-2'),
        $canchange ? $post($course->visible ? 'hide' : 'show', $courseid,
            get_string($course->visible ? 'courses:on' : 'courses:off', 'local_oriskill'),
            'oriskill-badge border-0 ' . ($course->visible ? 'is-good' : ''))
            : html_writer::span(get_string($course->visible ? 'courses:on' : 'courses:off', 'local_oriskill'),
                'oriskill-badge ' . ($course->visible ? 'is-good' : '')),
        html_writer::div(html_writer::div($avatars, 'oriskill-avatars') .
            html_writer::div(html_writer::link(new moodle_url('/local/oriskill/learners.php', ['courseid' => $courseid]),
                get_string('courses:memberscount', 'local_oriskill', $row->members)) . ' · ' .
                get_string('courses:groupscount', 'local_oriskill', $row->groups), 'small')),
        html_writer::div(s($revenue), 'fw-semibold') . html_writer::div(get_string('stats:sales', 'local_oriskill', $row->sales),
            'small text-muted'),
        // Tickets and comments under the lessons waiting for the team.
        ($row->tickets || $row->comments ? '' : html_writer::span('0', 'text-muted')) .
        ($row->tickets ? html_writer::div(html_writer::link(new moodle_url('/local/oriskill/tickets.php', ['status' => 'open']),
            html_writer::span($row->tickets, 'badge bg-danger') . ' ' . get_string('menu:tickets', 'local_oriskill')), 'small')
            : '') .
        ($row->comments ? html_writer::div(html_writer::link(new moodle_url('/local/oriskill/comments.php',
            ['courseid' => $courseid]), html_writer::span($row->comments, 'badge bg-danger') . ' ' .
            get_string('menu:comments', 'local_oriskill')), 'small') : ''),
        html_writer::div(
            html_writer::link($preview, '<i class="fa fa-eye" aria-hidden="true"></i>', ['class' => 'btn btn-sm btn-outline-secondary',
                'title' => get_string('courses:preview', 'local_oriskill'), 'aria-label' => get_string('courses:preview', 'local_oriskill')]) .
            html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]),
                '<i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>', ['class' => 'btn btn-sm btn-outline-secondary',
                'target' => '_blank', 'rel' => 'noopener', 'title' => get_string('courses:newtab', 'local_oriskill'),
                'aria-label' => get_string('courses:newtab', 'local_oriskill')]) .
            html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'setmode', 'value' => 1]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'context',
                    'value' => context_course::instance($courseid)->id]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'pageurl',
                    'value' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out_as_local_url(false)]) .
                html_writer::tag('button', '<i class="fa fa-pen" aria-hidden="true"></i>', ['type' => 'submit',
                    'class' => 'btn btn-sm btn-outline-secondary', 'title' => get_string('courses:editcontent', 'local_oriskill'),
                    'aria-label' => get_string('courses:editcontent', 'local_oriskill')]),
                ['method' => 'post', 'action' => (new moodle_url('/editmode.php'))->out(false), 'class' => 'd-inline']) .
            html_writer::div(
                html_writer::tag('button', '<i class="fa fa-ellipsis" aria-hidden="true"></i>', ['type' => 'button',
                    'class' => 'btn btn-sm btn-outline-secondary', 'data-bs-toggle' => 'dropdown', 'aria-expanded' => 'false', 'data-bs-popper-config' => '{"strategy":"fixed"}',
                    'aria-label' => get_string('courses:more', 'local_oriskill')]) .
                html_writer::tag('ul', $items, ['class' => 'dropdown-menu dropdown-menu-end']),
                'dropdown d-inline-block'),
            'd-flex gap-1 justify-content-end'),
    ];
}
echo html_writer::div(html_writer::table($table), 'oriskill-card table-responsive');

echo creator_service::space_end();
echo $OUTPUT->footer();
