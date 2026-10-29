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
 * Access to a trainer's course: groups, and when each module opens (after the previous one, on a date,
 * some days after enrolment) and for whom (groups).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\learning_service;

$creator = creator_service::require_current_creator(new moodle_url('/local/oriskill/access.php'), 'menu:access');
$course = creator_service::selected_course($creator);
$url = new moodle_url('/local/oriskill/access.php', $course ? ['courseid' => $course->id] : []);
$PAGE->set_url($url);

if ($course && ($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    $message = get_string('changessaved');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    try {
        if ($action === 'addgroup') {
            learning_service::create_group($course, required_param('name', PARAM_TEXT));
        } else if ($action === 'deletegroup') {
            learning_service::delete_group($course, required_param('groupid', PARAM_INT));
        } else if ($action === 'rules') {
            $timings = optional_param_array('timing', [], PARAM_ALPHA);
            $dates = optional_param_array('date', [], PARAM_RAW_TRIMMED);
            $days = optional_param_array('days', [], PARAM_INT);
            $rules = learning_service::section_rules($course);
            $errors = [];
            foreach ($rules as $num => $rule) {
                $timing = $timings[$num] ?? 'keep';
                if ($timing === 'keep') {
                    continue;
                }
                $groupids = optional_param_array('groups' . $num, [], PARAM_INT);
                $date = 0;
                if ($timing === 'date') {
                    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dates[$num] ?? '', $m)) {
                        $errors[] = $rule->name;
                        continue;
                    }
                    $date = make_timestamp((int) $m[1], (int) $m[2], (int) $m[3]);
                }
                try {
                    learning_service::set_section_rule($course, $num, $timing, $date, (int) ($days[$num] ?? 0), $groupids);
                } catch (moodle_exception $e) {
                    $errors[] = $rule->name . ' (' . $e->getMessage() . ')';
                }
            }
            if ($errors) {
                $message = get_string('error:rules', 'local_oriskill', implode(', ', $errors));
                $type = \core\output\notification::NOTIFY_ERROR;
            }
        }
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect($url, $message, null, $type);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/access.php', get_string('menu:access', 'local_oriskill'));
if (!$course) {
    echo html_writer::div('<i class="fa fa-lock-open" aria-hidden="true"></i>' . get_string('nocoursesyet', 'local_oriskill',
        (new moodle_url('/course/edit.php', ['category' => $creator->categoryid]))->out(false)), 'oriskill-card oriskill-empty');
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}
echo creator_service::course_picker($creator, $course, new moodle_url('/local/oriskill/access.php'));
echo html_writer::tag('p', get_string('access_intro', 'local_oriskill'), ['class' => 'text-muted']);

$groups = groups_get_all_groups($course->id);
$sesskey = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$hiddenaction = fn(string $action) => html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action',
    'value' => $action]);

// Module release rules.
$rules = learning_service::section_rules($course);
$cards = '';
foreach ($rules as $num => $rule) {
    $name = "timing[$num]";
    $radio = function(string $value, string $label, bool $enabled = true, string $extra = '') use ($name, $num, $rule) {
        $id = "timing-$num-$value";
        $checked = $rule->custom ? $value === 'keep' : $value === $rule->timing;
        return html_writer::div(
            html_writer::empty_tag('input', ['type' => 'radio', 'name' => $name, 'value' => $value, 'id' => $id,
                'class' => 'form-check-input', 'checked' => $checked ? 'checked' : null,
                'disabled' => $enabled ? null : 'disabled']) .
            html_writer::tag('label', $label, ['for' => $id, 'class' => 'form-check-label']) . $extra,
            'form-check d-flex flex-wrap align-items-center gap-2');
    };
    $timing = '';
    if ($rule->custom) {
        $timing .= $radio('keep', get_string('rule:custom', 'local_oriskill'), true,
            html_writer::link(new moodle_url('/course/editsection.php', ['id' => $rule->section->id]),
                get_string('rule:custom_edit', 'local_oriskill'), ['class' => 'small']));
    }
    $timing .= $radio('open', get_string('rule:open', 'local_oriskill'));
    $timing .= $radio('previous', get_string('rule:previous', 'local_oriskill'), (bool) $rule->hascompletion,
        $rule->hascompletion ? ($rule->stale && !$rule->custom
            ? html_writer::span(get_string('rule:previous_stale', 'local_oriskill'), 'small text-warning') : '')
        : html_writer::span(get_string($num > 1 ? 'rule:previous_none' : 'rule:previous_first', 'local_oriskill'),
            'small text-muted'));
    $timing .= $radio('date', get_string('rule:date', 'local_oriskill'), true,
        html_writer::empty_tag('input', ['type' => 'date', 'name' => "date[$num]", 'class' => 'form-control form-control-sm',
            'style' => 'width: auto', 'aria-label' => get_string('rule:date', 'local_oriskill'),
            'value' => $rule->date ? userdate($rule->date, '%Y-%m-%d', 99, false) : '']));
    $timing .= $radio('days', get_string('rule:days', 'local_oriskill'), true,
        html_writer::empty_tag('input', ['type' => 'number', 'min' => 0, 'step' => 1, 'name' => "days[$num]",
            'class' => 'form-control form-control-sm', 'style' => 'width: 6em',
            'aria-label' => get_string('rule:days', 'local_oriskill'), 'value' => $rule->days ?: 7]) .
        html_writer::span(get_string('rule:days_after', 'local_oriskill')));

    $groupboxes = '';
    foreach ($groups as $group) {
        $id = "group-$num-{$group->id}";
        $groupboxes .= html_writer::div(
            html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => "groups{$num}[]", 'value' => $group->id,
                'id' => $id, 'class' => 'form-check-input',
                'checked' => in_array((int) $group->id, $rule->groups) ? 'checked' : null]) .
            html_writer::tag('label', format_string($group->name), ['for' => $id, 'class' => 'form-check-label']),
            'form-check form-check-inline');
    }
    $who = $groups
        ? html_writer::div(get_string('rule:groups', 'local_oriskill'), 'small fw-semibold mb-1') . $groupboxes .
            html_writer::div(get_string('rule:groups_help', 'local_oriskill'), 'form-text')
        : html_writer::div(get_string('rule:nogroups', 'local_oriskill'), 'small text-muted');

    $cards .= html_writer::div(
        html_writer::tag('h3', s($rule->name)) .
        html_writer::div(
            html_writer::div(html_writer::div(get_string('rule:when', 'local_oriskill'), 'small fw-semibold mb-1') . $timing,
                'col-lg-7') .
            html_writer::div($who, 'col-lg-5'),
            'row g-3'),
        'oriskill-card mb-3');
}
if ($rules) {
    echo html_writer::tag('form', $sesskey . $hiddenaction('rules') . $cards .
        html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mb-4']),
        ['method' => 'post', 'action' => $url->out(false)]);
} else {
    echo html_writer::div('<i class="fa fa-layer-group" aria-hidden="true"></i>' . get_string('nomodules', 'local_oriskill'),
        'oriskill-card oriskill-empty mb-4');
}

// Groups.
$rows = '';
$members = learning_service::group_members($course);
foreach ($groups as $group) {
    $count = count(array_filter($members, fn($m) => (int) $m->groupid === (int) $group->id));
    $rows .= html_writer::tag('li',
        html_writer::div(html_writer::div(format_string($group->name), 'oriskill-row-title') .
            html_writer::div(get_string('learnerscount', 'local_oriskill', $count), 'oriskill-row-sub'), 'oriskill-row-main') .
        html_writer::tag('form', $sesskey . $hiddenaction('deletegroup') .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'groupid', 'value' => $group->id]) .
            html_writer::tag('button', get_string('delete'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']),
            ['method' => 'post', 'action' => $url->out(false), 'onsubmit' => 'return confirm(' .
                json_encode(get_string('group:delete_confirm', 'local_oriskill', format_string($group->name))) . ')']));
}
echo html_writer::div(
    html_writer::tag('h3', '<i class="fa fa-people-group me-2" aria-hidden="true"></i>' . get_string('groups')) .
    html_writer::tag('p', get_string('groups_intro', 'local_oriskill'), ['class' => 'small text-muted']) .
    ($rows ? html_writer::tag('ul', $rows, ['class' => 'oriskill-rows mb-3']) : '') .
    html_writer::tag('form', $sesskey . $hiddenaction('addgroup') .
        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'required' => 'required',
            'class' => 'form-control', 'placeholder' => get_string('group:name_placeholder', 'local_oriskill'),
            'aria-label' => get_string('groupname', 'group')]) .
        html_writer::tag('button', '<i class="fa fa-plus me-1" aria-hidden="true"></i>' . get_string('group:add', 'local_oriskill'),
            ['type' => 'submit', 'class' => 'btn btn-outline-primary text-nowrap']),
        ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-flex gap-2', 'style' => 'max-width: 32rem']),
    'oriskill-card');

echo creator_service::space_end();
echo $OUTPUT->footer();
