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
    $saved = null;
    try {
        if ($action === 'save') {
            // No module ticked: every module of the training ("All modules" only counts when nothing else is ticked).
            $courseid = required_param('courseid', PARAM_INT);
            $id = ms::save($creator, optional_param('id', 0, PARAM_INT), required_param('label', PARAM_TEXT),
                optional_param('sortorder', 0, PARAM_INT), (bool) optional_param('visible', 0, PARAM_BOOL), $courseid,
                optional_param_array('sec', [], PARAM_INT));
            $saved = $courseid;
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
        redirect(new moodle_url($url, ['course' => $saved ?? optional_param('course', 0, PARAM_INT)]),
            get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect(new moodle_url($url, ['course' => optional_param('course', optional_param('courseid', 0, PARAM_INT), PARAM_INT)]),
            $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$allentries = ms::entries($creator);
$mycourses = ms::trainer_courses($creator);
$editing = $allentries[optional_param('edit', 0, PARAM_INT)] ?? null;
// Training being managed: the one chosen, else the one of the entry being edited, else the first.
$courseid = optional_param('course', 0, PARAM_INT);
if ($editing && $editing->courseid && isset($mycourses[$editing->courseid]) && !isset($mycourses[$courseid])) {
    $courseid = (int) $editing->courseid;
}
if (!isset($mycourses[$courseid])) {
    $courseid = $mycourses ? (int) array_key_first($mycourses) : 0;
}
$entries = array_filter($allentries, fn($e) => (int) $e->courseid === $courseid);
$unattached = array_filter($allentries, fn($e) => !isset($mycourses[$e->courseid]));

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

// Training chosen first: the entries below are those of that training only.
$picker = '';
foreach ($mycourses as $id => $mine) {
    $picker .= html_writer::tag('option', format_string($mine->fullname),
        ['value' => $id] + ($id === $courseid ? ['selected' => 'selected'] : []));
}
if ($picker) {
    echo html_writer::div(
        html_writer::tag('form',
            html_writer::label(get_string('menus:course', 'local_oriskill'), 'menus-course', true, ['class' => 'form-label me-2']) .
            html_writer::tag('select', $picker, ['name' => 'course', 'id' => 'menus-course', 'class' => 'form-select d-inline-block w-auto',
                'onchange' => 'this.form.submit()']) .
            html_writer::tag('noscript', html_writer::tag('button', get_string('menus:choose', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-primary ms-2'])),
            ['method' => 'get', 'action' => $url->out(false)]),
        'oriskill-card mb-3');
}

$entrycard = function (stdClass $entry, bool $attached) use ($url, $mycourses, $courseid) {
    $count = $attached ? count(reset($entry->courses) ?: []) : 0;
    return html_writer::div(
        html_writer::div(html_writer::tag('strong', format_string($entry->label)) . ' ' .
            html_writer::span(get_string($entry->visible ? 'menus:shown' : 'menus:hidden', 'local_oriskill'),
                'badge ' . ($entry->visible ? 'bg-success' : 'bg-light text-dark')) .
            html_writer::div($attached
                ? html_writer::span($count ? get_string('menus:nbmodules', 'local_oriskill', $count)
                    : get_string('menus:allmodules', 'local_oriskill'), 'badge rounded-pill bg-secondary me-1')
                : get_string('menus:unattached', 'local_oriskill'), 'mt-1')) .
        html_writer::div(html_writer::link(new moodle_url($url, ['edit' => $entry->id, 'course' => $courseid], 'menuform'),
            get_string('edit'), ['class' => 'btn btn-sm btn-outline-primary']) . ' ' .
            html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(),
                'action' => 'delete', 'id' => $entry->id, 'course' => $courseid])) . html_writer::tag('button', get_string('delete'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']), ['method' => 'post',
                'action' => $url->out(false), 'class' => 'd-inline',
                'onsubmit' => 'return confirm(' . json_encode(get_string('menus:deleteconfirm', 'local_oriskill')) . ')']),
            'text-nowrap'),
        'oriskill-card d-flex justify-content-between align-items-start gap-3 mb-2');
};
foreach ($entries as $entry) {
    echo $entrycard($entry, true);
}
if ($unattached) {
    echo html_writer::tag('h3', get_string('menus:unattachedtitle', 'local_oriskill'), ['class' => 'h6 mt-3']);
    echo html_writer::div(get_string('menus:unattachedhelp', 'local_oriskill'), 'small text-muted mb-2');
    foreach ($unattached as $entry) {
        echo $entrycard($entry, false);
    }
}

// Form: label, order, shown, then the modules of the chosen training (those of PDF imports and made by hand alike).
$boxes = '';
// Modules already used in another entry of this training (allowed, only mentioned): section id => labels.
$usedelsewhere = [];
foreach ($entries as $other) {
    if ($editing && (int) $other->id === (int) $editing->id) {
        continue;
    }
    $othersections = $other->courses[$courseid] ?? [];
    if (!$othersections && $courseid) {
        $othersections = array_keys(ms::course_sections(get_course($courseid)));
    }
    foreach ($othersections as $othersectionid) {
        $usedelsewhere[$othersectionid][] = format_string($other->label);
    }
}
if ($courseid) {
    $picked = $editing && (int) $editing->courseid === $courseid ? ($editing->courses[$courseid] ?? []) : [];
    $sectionboxes = '';
    // In the order of the modules in the training.
    foreach (ms::course_sections(get_course($courseid)) as $sectionid => $sectionname) {
        $sectionboxes .= html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox',
            'name' => 'sec[]', 'value' => $sectionid, 'id' => "sec-$sectionid", 'class' => 'form-check-input',
            'onclick' => "document.getElementById('all-modules').checked = !document.querySelector('input[name=\"sec[]\"]:checked');"]
            + (in_array($sectionid, $picked, true) ? ['checked' => 'checked'] : [])) .
            html_writer::label(s($sectionname), "sec-$sectionid", true, ['class' => 'form-check-label']) .
            (!empty($usedelsewhere[$sectionid]) ? html_writer::span(get_string('menus:usedin', 'local_oriskill',
                implode(', ', array_unique($usedelsewhere[$sectionid]))), 'text-muted fst-italic ms-1 small') : ''),
            'form-check');
    }
    $boxes = html_writer::div(
        html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'value' => 1, 'id' => 'all-modules',
            'class' => 'form-check-input', 'disabled' => 'disabled'] + ($picked ? [] : ['checked' => 'checked'])) .
            html_writer::label(get_string('menus:allmodules', 'local_oriskill'), 'all-modules', true,
                ['class' => 'form-check-label']), 'form-check') .
        ($sectionboxes ? html_writer::div(
            html_writer::tag('button', get_string('menus:checkall', 'local_oriskill'), ['type' => 'button',
                'class' => 'btn btn-sm btn-link p-0 me-2', 'onclick' => "document.querySelectorAll('input[name=\"sec[]\"]')"
                . ".forEach(function (b) { b.checked = true; }); document.getElementById('all-modules').checked = false;"]) .
            html_writer::tag('button', get_string('menus:uncheckall', 'local_oriskill'), ['type' => 'button',
                'class' => 'btn btn-sm btn-link p-0', 'onclick' => "document.querySelectorAll('input[name=\"sec[]\"]')"
                . ".forEach(function (b) { b.checked = false; }); document.getElementById('all-modules').checked = true;"]) .
            html_writer::div($sectionboxes, 'mt-1'), 'ms-4 small') : ''),
        'border rounded-3 p-2 mb-2');
}
if (!$courseid) {
    echo $OUTPUT->notification(get_string('menus:nocourses', 'local_oriskill'), \core\output\notification::NOTIFY_INFO);
} else {
    echo html_writer::div(
        html_writer::tag('h3', get_string($editing ? 'menus:edit' : 'menus:new', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => 'save',
            'id' => $editing->id ?? 0, 'courseid' => $courseid, 'course' => $courseid])) .
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
            $boxes .
            html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-2']),
            ['method' => 'post', 'action' => $url->out(false)]),
        'oriskill-card mt-4', ['id' => 'menuform']);
}

echo creator_service::space_end();
echo $OUTPUT->footer();
