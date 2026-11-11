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
 * Header menu of the learner space of the trainer, and the switch for their brand on it.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\menu_service as ms;

$url = new moodle_url('/local/oriskill/menus.php');
$creator = creator_service::require_current_creator($url, 'menu:menus');

if ($action = optional_param('action', '', PARAM_ALPHA)) {
    require_sesskey();
    try {
        if ($action === 'save') {
            $courses = [];
            foreach (optional_param_array('course', [], PARAM_INT) as $courseid) {
                // No module ticked: every module of the course ("All modules" only counts when nothing else is ticked).
                $courses[$courseid] = optional_param_array("sec_$courseid", [], PARAM_INT);
            }
            $id = ms::save($creator, optional_param('id', 0, PARAM_INT), required_param('label', PARAM_TEXT),
                optional_param('sortorder', 0, PARAM_INT), (bool) optional_param('visible', 0, PARAM_BOOL), $courses);
            \local_oriskill\log_service::add($creator, 'menu_saved', ['id' => $id]);
        } else if ($action === 'delete') {
            $id = required_param('id', PARAM_INT);
            ms::delete($creator, $id);
            \local_oriskill\log_service::add($creator, 'menu_deleted', ['id' => $id]);
        } else if ($action === 'skin') {
            $on = (bool) optional_param('studentskin', 0, PARAM_BOOL);
            ms::set_skin($creator, $on);
            \local_oriskill\log_service::add($creator, $on ? 'menu_skin_on' : 'menu_skin_off');
        }
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$entries = ms::entries($creator);
$editing = $entries[optional_param('edit', 0, PARAM_INT)] ?? null;
$mycourses = ms::trainer_courses($creator);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/menus.php', get_string('menu:menus', 'local_oriskill'));
echo html_writer::tag('p', get_string('menus:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Brand on the learner space: off until the trainer turns it on.
echo html_writer::div(
    html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => 'skin'])) .
        html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'studentskin', 'value' => 1,
            'id' => 'menus-skin', 'class' => 'form-check-input'] + (ms::skin_enabled($creator) ? ['checked' => 'checked'] : [])) .
            html_writer::label(get_string('menus:skin', 'local_oriskill'), 'menus-skin', true, ['class' => 'form-check-label']),
            'form-check mb-2') .
        html_writer::div(get_string('menus:skinhelp', 'local_oriskill'), 'small text-muted mb-2') .
        html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']),
        ['method' => 'post', 'action' => $url->out(false)]),
    'oriskill-card mb-3');

foreach ($entries as $entry) {
    $names = [];
    foreach ($entry->courses as $courseid => $sectionids) {
        if (isset($mycourses[$courseid])) {
            $names[] = html_writer::span(format_string($mycourses[$courseid]->fullname) .
                ($sectionids ? ' (' . count($sectionids) . ')' : ''), 'badge rounded-pill bg-secondary me-1');
        }
    }
    echo html_writer::div(
        html_writer::div(html_writer::tag('strong', format_string($entry->label)) . ' ' .
            html_writer::span(get_string($entry->visible ? 'menus:shown' : 'menus:hidden', 'local_oriskill'),
                'badge ' . ($entry->visible ? 'bg-success' : 'bg-light text-dark')) .
            html_writer::div($names ? implode('', $names) : get_string('menus:nocourse', 'local_oriskill'), 'mt-1')) .
        html_writer::div(html_writer::link(new moodle_url($url, ['edit' => $entry->id], 'menuform'), get_string('edit'),
            ['class' => 'btn btn-sm btn-outline-primary']) . ' ' .
            html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(),
                'action' => 'delete', 'id' => $entry->id])) . html_writer::tag('button', get_string('delete'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']), ['method' => 'post',
                'action' => $url->out(false), 'class' => 'd-inline',
                'onsubmit' => 'return confirm(' . json_encode(get_string('menus:deleteconfirm', 'local_oriskill')) . ')']),
            'text-nowrap'),
        'oriskill-card d-flex justify-content-between align-items-start gap-3 mb-2');
}

// Form: label, order, shown, then every course of the trainer with "All modules" and one box per module.
$boxes = '';
// Modules already used in another entry (allowed, only mentioned): section id => labels of those entries.
$usedelsewhere = [];
foreach ($entries as $other) {
    if ($editing && (int) $other->id === (int) $editing->id) {
        continue;
    }
    foreach ($other->courses as $othercourseid => $othersections) {
        if (!$othersections && isset($mycourses[$othercourseid])) {
            $othersections = array_keys(ms::course_sections(get_course($othercourseid)));
        }
        foreach ($othersections as $othersectionid) {
            $usedelsewhere[$othersectionid][] = format_string($other->label);
        }
    }
}
foreach ($mycourses as $courseid => $mycourse) {
    $chosen = $editing && isset($editing->courses[$courseid]);
    $picked = $chosen ? $editing->courses[$courseid] : [];
    $allmodules = !$chosen || !$picked;
    $sectionboxes = '';
    foreach (ms::course_sections(get_course($courseid)) as $sectionid => $sectionname) {
        $sectionboxes .= html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox',
            'name' => "sec_{$courseid}[]", 'value' => $sectionid, 'id' => "sec-$courseid-$sectionid",
            'class' => 'form-check-input',
            'onclick' => "document.getElementById('all-$courseid').checked = !document.querySelector("
                . "'input[name=\"sec_{$courseid}[]\"]:checked'); if (this.checked) { document.getElementById('course-$courseid')"
                . ".checked = true; }"]
            + (in_array($sectionid, $picked, true) ? ['checked' => 'checked'] : [])) .
            html_writer::label(s($sectionname), "sec-$courseid-$sectionid", true, ['class' => 'form-check-label']) .
            (!empty($usedelsewhere[$sectionid]) ? html_writer::span(get_string('menus:usedin', 'local_oriskill',
                implode(', ', array_unique($usedelsewhere[$sectionid]))), 'text-muted fst-italic ms-1 small') : ''),
            'form-check');
    }
    $boxes .= html_writer::div(
        html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'course[]', 'value' => $courseid,
            'id' => "course-$courseid", 'class' => 'form-check-input'] + ($chosen ? ['checked' => 'checked'] : [])) .
            html_writer::label(format_string($mycourse->fullname),"course-$courseid", true,
                ['class' => 'form-check-label fw-semibold']), 'form-check') .
        html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'value' => 1,
            'id' => "all-$courseid", 'class' => 'form-check-input', 'disabled' => 'disabled'] + ($allmodules ? ['checked' => 'checked'] : [])) .
            html_writer::label(get_string('menus:allmodules', 'local_oriskill'), "all-$courseid", true,
                ['class' => 'form-check-label']), 'form-check ms-4') .
        ($sectionboxes ? html_writer::div(
            html_writer::tag('button', get_string('menus:checkall', 'local_oriskill'), ['type' => 'button',
                'class' => 'btn btn-sm btn-link p-0 me-2', 'onclick' => "document.querySelectorAll('input[name=\"sec_{$courseid}[]\"]')"
                . ".forEach(function (b) { b.checked = true; }); document.getElementById('all-$courseid').checked = false;"
                . " document.getElementById('course-$courseid').checked = true;"]) .
            html_writer::tag('button', get_string('menus:uncheckall', 'local_oriskill'), ['type' => 'button',
                'class' => 'btn btn-sm btn-link p-0', 'onclick' => "document.querySelectorAll('input[name=\"sec_{$courseid}[]\"]')"
                . ".forEach(function (b) { b.checked = false; }); document.getElementById('all-$courseid').checked = true;"]) .
            html_writer::div($sectionboxes, 'mt-1'), 'ms-4 small') : ''),
        'border rounded-3 p-2 mb-2');
}
echo html_writer::div(
    html_writer::tag('h3', get_string($editing ? 'menus:edit' : 'menus:new', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => 'save',
        'id' => $editing->id ?? 0])) .
        html_writer::div(html_writer::label(get_string('menus:label', 'local_oriskill'), 'menu-label', true,
                ['class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'label', 'id' => 'menu-label', 'required' => 'required',
                'maxlength' => 100, 'class' => 'form-control', 'value' => $editing->label ?? '']), 'mb-3',
            ['style' => 'max-width: 28rem']) .
        html_writer::div(html_writer::label(get_string('menus:order', 'local_oriskill'), 'menu-order', true,
                ['class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'number', 'name' => 'sortorder', 'id' => 'menu-order', 'min' => 0,
                'max' => 99999, 'class' => 'form-control', 'value' => $editing->sortorder ?? count($entries) + 1]), 'mb-3',
            ['style' => 'max-width: 10rem']) .
        html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'visible', 'value' => 1,
            'id' => 'menu-visible', 'class' => 'form-check-input'] + (!$editing || $editing->visible ? ['checked' => 'checked'] : [])) .
            html_writer::label(get_string('menus:visible', 'local_oriskill'), 'menu-visible', true,
                ['class' => 'form-check-label']), 'form-check mb-3') .
        html_writer::tag('h4', get_string('menus:modules', 'local_oriskill'), ['class' => 'h6']) .
        html_writer::div(get_string('menus:freehelp', 'local_oriskill'), 'small text-muted mb-2') .
        ($boxes ?: html_writer::div(get_string('menus:nocourses', 'local_oriskill'), 'text-muted mb-3')) .
        html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-2']),
        ['method' => 'post', 'action' => $url->out(false)]),
    'oriskill-card mt-4', ['id' => 'menuform']);

echo creator_service::space_end();
echo $OUTPUT->footer();
