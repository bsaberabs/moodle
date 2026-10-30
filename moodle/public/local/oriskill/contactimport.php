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
 * Import contacts from a CSV file: upload, map the columns, follow the import.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\contact_import as ci;
use local_oriskill\contact_service as cs;
use local_oriskill\creator_service;

$id = optional_param('id', 0, PARAM_INT);
$url = new moodle_url('/local/oriskill/contactimport.php', $id ? ['id' => $id] : []);
$creator = creator_service::require_current_creator($url, 'import:title');
$import = $id ? $DB->get_record(ci::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST) : null;
$courses = $DB->get_records('course', ['category' => $creator->categoryid], 'fullname', 'id, fullname');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    try {
        if ($action === 'upload') {
            if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
                throw new moodle_exception('import:nofile', 'local_oriskill');
            }
            $import = ci::prepare($creator, $_FILES['csv']['tmp_name'], clean_filename($_FILES['csv']['name']));
            redirect(new moodle_url('/local/oriskill/contactimport.php', ['id' => $import->id]));
        } else if ($import && $action === 'start') {
            [$courseid, $groupid] = array_map('intval', array_pad(explode(':', optional_param('course', '', PARAM_RAW_TRIMMED)), 2, 0));
            ci::start($import, optional_param_array('map', [], PARAM_ALPHA), [
                'tags' => cs::parse_tags(optional_param('tags', '', PARAM_RAW_TRIMMED)),
                'courseid' => $courseid, 'groupid' => $groupid,
                'sendcredentials' => optional_param('sendcredentials', 0, PARAM_BOOL),
            ]);
            redirect($url);
        } else if ($import && $action === 'cancel') {
            ci::cancel($import);
            redirect($url);
        }
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Follow an import being processed: the page reloads itself.
if ($import && in_array($import->status, ['pending', 'running'], true)) {
    $PAGE->set_periodic_refresh_delay(5);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/contacts.php', get_string('import:title', 'local_oriskill'));
echo html_writer::div(html_writer::link(new moodle_url('/local/oriskill/contacts.php'),
    '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' . get_string('contacts:back', 'local_oriskill')), 'mb-3');
$hidden = fn(string $action) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);

if (!$import) {
    // 1. Upload.
    echo html_writer::div(
        html_writer::tag('h3', get_string('import:step1', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::tag('p', get_string('import:intro', 'local_oriskill'), ['class' => 'text-muted']) .
        html_writer::tag('form', $hidden('upload') .
            html_writer::div(html_writer::empty_tag('input', ['type' => 'file', 'name' => 'csv', 'accept' => '.csv,text/csv,text/plain',
                'required' => 'required', 'class' => 'form-control', 'aria-label' => get_string('file')]) .
                html_writer::tag('button', get_string('import:upload', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']),
                'd-flex gap-2', ['style' => 'max-width: 40rem']),
            ['method' => 'post', 'action' => $url->out(false), 'enctype' => 'multipart/form-data']),
        'oriskill-card mb-4');
    // Previous imports.
    $previous = ci::imports($creator);
    if ($previous) {
        $rows = '';
        foreach ($previous as $p) {
            $rows .= html_writer::tag('li', html_writer::div(
                html_writer::link(new moodle_url('/local/oriskill/contactimport.php', ['id' => $p->id]), s($p->filename),
                    ['class' => 'oriskill-row-title']) .
                html_writer::div(userdate($p->timecreated, get_string('strftimedatetimeshort', 'langconfig')) . ' · ' .
                    get_string('import:summary', 'local_oriskill', $p), 'oriskill-row-sub'), 'oriskill-row-main') .
                html_writer::span(get_string('import:status_' . $p->status, 'local_oriskill'), 'oriskill-badge'));
        }
        echo html_writer::div(html_writer::tag('h3', get_string('import:previous', 'local_oriskill'), ['class' => 'h5']) .
            html_writer::tag('ul', $rows, ['class' => 'oriskill-rows']), 'oriskill-card');
    }
} else if ($import->status === 'draft') {
    // 2. Map the columns.
    $headers = json_decode($import->headers, true);
    $guess = ci::guess($headers['headers']);
    $fieldoptions = [];
    foreach (ci::FIELDS as $field) {
        $fieldoptions[$field] = get_string('import:field_' . ($field ?: 'ignore'), 'local_oriskill');
    }
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('import:column', 'local_oriskill'), get_string('import:examples', 'local_oriskill'),
        get_string('import:goesto', 'local_oriskill')];
    foreach ($headers['headers'] as $i => $header) {
        $examples = array_filter(array_map(fn($row) => trim((string) ($row[$i] ?? '')), $headers['samples']), fn($v) => $v !== '');
        $table->data[] = [
            html_writer::span(s($header !== '' ? $header : '#' . ($i + 1)), 'fw-semibold'),
            html_writer::div(s(core_text::substr(implode(' · ', $examples), 0, 90)), 'small text-muted',
                ['style' => 'white-space: normal; max-width: 24rem']),
            html_writer::select($fieldoptions, "map[$i]", $guess[$i], false, ['class' => 'form-select form-select-sm']),
        ];
    }
    $courseoptions = ['' => get_string('import:nocourse', 'local_oriskill')];
    foreach ($courses as $course) {
        $courseoptions[$course->id] = format_string($course->fullname);
        foreach (groups_get_all_groups($course->id) as $group) {
            $courseoptions[$course->id . ':' . $group->id] = format_string($course->fullname) . ' — ' . format_string($group->name);
        }
    }
    echo html_writer::tag('form', $hidden('start') .
        html_writer::div(
            html_writer::tag('h3', get_string('import:step2', 'local_oriskill', s($import->filename)), ['class' => 'h5']) .
            html_writer::tag('p', get_string('import:mapping_help', 'local_oriskill'), ['class' => 'text-muted small']) .
            html_writer::div(html_writer::table($table), 'table-responsive'), 'oriskill-card mb-3') .
        html_writer::div(
            html_writer::tag('h3', get_string('import:options', 'local_oriskill'), ['class' => 'h5']) .
            html_writer::div(html_writer::tag('label', get_string('import:tags', 'local_oriskill'), ['class' => 'form-label', 'for' => 'imp-tags']) .
                html_writer::empty_tag('input', ['type' => 'text', 'name' => 'tags', 'id' => 'imp-tags', 'class' => 'form-control',
                    'placeholder' => get_string('contacts:tags_help', 'local_oriskill')]), 'mb-3', ['style' => 'max-width: 32rem']) .
            html_writer::div(html_writer::tag('label', get_string('import:course', 'local_oriskill'), ['class' => 'form-label', 'for' => 'imp-course']) .
                html_writer::select($courseoptions, 'course', '', false, ['class' => 'form-select', 'id' => 'imp-course']) .
                html_writer::div(get_string('import:course_help', 'local_oriskill'), 'form-text'), 'mb-3', ['style' => 'max-width: 32rem']) .
            html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'sendcredentials', 'value' => 1,
                'id' => 'imp-send', 'class' => 'form-check-input']) .
                html_writer::tag('label', get_string('import:sendcredentials', 'local_oriskill'), ['for' => 'imp-send',
                    'class' => 'form-check-label']), 'form-check mb-3') .
            html_writer::tag('button', get_string('import:start', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']) . ' ' .
            html_writer::link(new moodle_url('/local/oriskill/contactimport.php'), get_string('cancel'), ['class' => 'btn btn-outline-secondary']),
            'oriskill-card'),
        ['method' => 'post', 'action' => $url->out(false)]);
} else {
    // 3. Follow the import.
    $running = in_array($import->status, ['pending', 'running'], true);
    $facts = '';
    foreach (['processed', 'created', 'updated', 'enrolled', 'failed'] as $key) {
        $facts .= html_writer::div(html_writer::div(get_string('import:count_' . $key, 'local_oriskill'), 'oriskill-stat-label') .
            html_writer::div(number_format((int) $import->$key, 0, ',', ' '), 'fw-semibold'));
    }
    echo html_writer::div(
        html_writer::div(html_writer::tag('h3', s($import->filename), ['class' => 'h5 mb-0']) .
            html_writer::span(get_string('import:status_' . $import->status, 'local_oriskill'),
                'oriskill-badge ' . ($import->status === 'done' ? 'is-good' : ($running ? '' : 'is-warn'))),
            'd-flex justify-content-between align-items-center gap-2 mb-2') .
        ($running ? html_writer::tag('p', get_string('import:running', 'local_oriskill'), ['class' => 'text-muted small']) : '') .
        html_writer::div($facts, 'oriskill-facts') .
        ($running ? html_writer::tag('form', $hidden('cancel') . html_writer::tag('button', get_string('import:cancel', 'local_oriskill'),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger mt-3']), ['method' => 'post', 'action' => $url->out(false),
            'onsubmit' => 'return confirm(' . json_encode(get_string('import:cancel_confirm', 'local_oriskill')) . ')']) :
            html_writer::div(html_writer::link(new moodle_url('/local/oriskill/contacts.php', ['sort' => 'created']),
                get_string('import:see', 'local_oriskill'), ['class' => 'btn btn-sm btn-primary']) . ' ' .
                html_writer::link(new moodle_url('/local/oriskill/contactimport.php'), get_string('import:another', 'local_oriskill'),
                    ['class' => 'btn btn-sm btn-outline-secondary']), 'mt-3')),
        'oriskill-card mb-3');
    $errors = json_decode((string) $import->errors, true) ?: [];
    if ($errors) {
        $table = new html_table();
        $table->attributes['class'] = 'table table-sm';
        $table->head = [get_string('import:row', 'local_oriskill'), get_string('email'), get_string('error')];
        foreach ($errors as $error) {
            $table->data[] = [(int) $error['row'], s($error['email']), s($error['error'])];
        }
        echo html_writer::div(html_writer::tag('h3', get_string('import:errors', 'local_oriskill', count($errors)), ['class' => 'h6']) .
            html_writer::div(html_writer::table($table), 'table-responsive', ['style' => 'max-height: 24rem; overflow: auto']),
            'oriskill-card');
    }
}
echo creator_service::space_end();
echo $OUTPUT->footer();
