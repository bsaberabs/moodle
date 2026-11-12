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
 * Import of a PDF with bookmarks: modules and lessons added to an existing training (course).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\menu_service;
use local_oriskill\pdf_import_service as pis;
use local_oriskill\team_service;

$url = new moodle_url('/local/oriskill/pdfimport.php');
$creator = creator_service::require_current_creator($url, 'menu:pdfimport');
$courses = new moodle_url('/local/oriskill/courses.php');
$token = optional_param('token', '', PARAM_ALPHANUM);
$courseid = optional_param('courseid', 0, PARAM_INT);
$mycourses = menu_service::trainer_courses($creator);

if ($action = optional_param('action', '', PARAM_ALPHA)) {
    require_sesskey();
    try {
        // A read-only role sees the page but starts nothing.
        if (in_array($action, ['upload', 'create'], true)
                && !team_service::can_change(creator_service::current_role(), '/local/oriskill/courses.php')) {
            throw new moodle_exception('error:notallowed', 'local_oriskill');
        }
        if ($action === 'upload') {
            // The destination training is checked before anything is read.
            pis::destination($creator, (int) $USER->id, $courseid);
            $file = $_FILES['pdf'] ?? null;
            if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
                throw new moodle_exception('pdfimport:err:nofile', 'local_oriskill');
            }
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || $file['size'] > pis::MAXBYTES) {
                throw new moodle_exception('pdfimport:err:upload', 'local_oriskill', '', display_size(pis::MAXBYTES));
            }
            $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);
            $mime = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name'])
                : 'application/pdf';
            if ($head !== '%PDF-' || $mime !== 'application/pdf') {
                throw new moodle_exception('pdfimport:err:notpdf', 'local_oriskill');
            }
            $token = pis::stash((int) $USER->id, $file['tmp_name'], (string) $file['name']);
            redirect(new moodle_url($url, ['token' => $token, 'courseid' => $courseid]));
        } else if ($action === 'cancel') {
            if (pis::is_queued((int) $USER->id, $token)) {
                throw new moodle_exception('pdfimport:err:queued', 'local_oriskill');
            }
            pis::discard((int) $USER->id, $token);
            redirect($url);
        } else if ($action === 'create') {
            // One import per token at a time: a double click (or a second tab) must not import twice.
            $lock = \core\lock\lock_config::get_lock_factory('local_oriskill')->get_lock("pdfimport_{$USER->id}_{$token}", 0);
            if (!$lock) {
                throw new moodle_exception('pdfimport:err:running', 'local_oriskill');
            }
            $queued = false;
            try {
                if (pis::is_queued((int) $USER->id, $token)) {
                    throw new moodle_exception('pdfimport:err:queued', 'local_oriskill');
                }
                // After a first success the files of the token are gone: load() then refuses a second import.
                $plan = pis::load((int) $USER->id, $token);
                $kept = pis::apply_edits($plan,
                    optional_param_array('mt', [], PARAM_TEXT), optional_param_array('mx', [], PARAM_INT),
                    optional_param_array('lt', [], PARAM_TEXT), optional_param_array('lx', [], PARAM_INT));
                $course = pis::destination($creator, (int) $USER->id, $courseid);
                if (!$kept->modules) {
                    throw new moodle_exception('pdfimport:err:nothing', 'local_oriskill');
                }
                $options = pis::options((bool) optional_param('visible', 0, PARAM_BOOL),
                    optional_param('unlock', 'none', PARAM_ALPHANUM), optional_param('unlockdays', 0, PARAM_INT),
                    optional_param('startday', 0, PARAM_INT));
                if (pis::is_large($kept)) {
                    pis::queue($creator, (int) $USER->id, $token, $kept, (int) $course->id, $options);
                    $queued = true;
                } else {
                    pis::import_modules($creator, (int) $USER->id, $token, $kept, (int) $course->id, $options);
                    pis::discard((int) $USER->id, $token);
                }
            } finally {
                $lock->release();
            }
            if ($queued) {
                redirect($courses, get_string('pdfimport:queued', 'local_oriskill'), null,
                    \core\output\notification::NOTIFY_INFO);
            }
            redirect(new moodle_url('/course/view.php', ['id' => $course->id]),
                get_string('pdfimport:created', 'local_oriskill'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (moodle_exception $e) {
        redirect($action === 'create' && $token ? new moodle_url($url, ['token' => $token, 'courseid' => $courseid]) : $url, $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/pdfimport.php', get_string('menu:pdfimport', 'local_oriskill'));

$plan = null;
if ($token) {
    try {
        $plan = pis::load((int) $USER->id, $token);
    } catch (moodle_exception $e) {
        echo $OUTPUT->notification($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
}

if (!$plan) {
    echo html_writer::tag('p', get_string('pdfimport:intro1', 'local_oriskill'), ['class' => 'text-muted']);
    if ($problem = pis::tools_problem()) {
        echo $OUTPUT->notification(get_string($problem, 'local_oriskill'), \core\output\notification::NOTIFY_ERROR);
    } else if (!$mycourses) {
        echo $OUTPUT->notification(get_string('pdfimport:nocourse', 'local_oriskill'),
            \core\output\notification::NOTIFY_WARNING);
        echo html_writer::link($courses, get_string('pdfimport:createcourse', 'local_oriskill'), ['class' => 'btn btn-primary']);
    } else {
        $options = '';
        foreach ($mycourses as $id => $mine) {
            $options .= html_writer::tag('option', format_string($mine->fullname), ['value' => $id]);
        }
        echo html_writer::tag('form',
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'upload']) .
            html_writer::div(
                html_writer::label(get_string('pdfimport:destination', 'local_oriskill'), 'pdfimport-course', true,
                    ['class' => 'form-label']) .
                html_writer::tag('select', $options, ['name' => 'courseid', 'id' => 'pdfimport-course',
                    'class' => 'form-select', 'required' => 'required']) .
                html_writer::div(get_string('pdfimport:destinationhelp', 'local_oriskill'), 'small text-muted mt-1'), 'mb-3') .
            html_writer::div(
                html_writer::label(get_string('pdfimport:file', 'local_oriskill'), 'pdfimport-file', true,
                    ['class' => 'form-label']) .
                html_writer::empty_tag('input', ['type' => 'file', 'name' => 'pdf', 'id' => 'pdfimport-file',
                    'accept' => 'application/pdf,.pdf', 'class' => 'form-control', 'required' => 'required']) .
                html_writer::div(get_string('pdfimport:filehelp', 'local_oriskill', display_size(pis::MAXBYTES)),
                    'small text-muted mt-1'), 'mb-3') .
            html_writer::tag('button', get_string('pdfimport:analyse', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-primary']),
            ['method' => 'post', 'action' => $url->out(false), 'enctype' => 'multipart/form-data',
             'class' => 'oriskill-card']);
    }
} else {
    // Preview: the destination training, and the tree of modules and lessons to check.
    try {
        $destination = pis::destination($creator, (int) $USER->id, $courseid);
    } catch (moodle_exception $e) {
        $destination = null;
    }
    $lessons = 0;
    foreach ($plan->modules as $module) {
        $lessons += count($module->lessons);
    }
    if (!$destination) {
        echo $OUTPUT->notification(get_string('pdfimport:err:course', 'local_oriskill'),
            \core\output\notification::NOTIFY_ERROR);
        echo html_writer::link($url, get_string('cancel'), ['class' => 'btn btn-outline-secondary']);
        echo creator_service::space_end();
        echo $OUTPUT->footer();
        exit;
    }
    echo html_writer::tag('p', get_string('pdfimport:into', 'local_oriskill', format_string($destination->fullname)),
        ['class' => 'fw-semibold']);
    echo html_writer::tag('p', get_string('pdfimport:preview', 'local_oriskill',
        (object) ['modules' => count($plan->modules), 'lessons' => $lessons, 'pages' => $plan->pages]),
        ['class' => 'text-muted']);
    if (pis::is_large($plan)) {
        echo $OUTPUT->notification(get_string('pdfimport:largenote', 'local_oriskill'),
            \core\output\notification::NOTIFY_INFO);
    }
    $input = fn(string $name, string $value, string $class, string $label) =>
        html_writer::empty_tag('input', ['type' => 'text', 'name' => $name, 'value' => $value, 'class' => $class,
            'maxlength' => 250, 'aria-label' => $label]);
    $skip = fn(string $name) => html_writer::tag('label',
        html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => $name, 'value' => 1,
            'class' => 'form-check-input me-1']) . get_string('pdfimport:exclude', 'local_oriskill'),
        ['class' => 'small text-muted text-nowrap ms-2']);

    $tree = '';
    foreach ($plan->modules as $m => $module) {
        $rows = '';
        foreach ($module->lessons as $l => $lesson) {
            $rows .= html_writer::div(
                html_writer::div($input("lt[{$m}_{$l}]", $lesson->title, 'form-control form-control-sm',
                    get_string('pdfimport:lessontitle', 'local_oriskill')), 'flex-grow-1') .
                html_writer::span(get_string('pdfimport:pages', 'local_oriskill', pis::page_count($lesson)),
                    'small text-muted text-nowrap ms-2') . $skip("lx[{$m}_{$l}]"),
                'd-flex align-items-center mb-1 ms-4');
        }
        $tree .= html_writer::div(
            html_writer::div(
                html_writer::div($input("mt[$m]", $module->title, 'form-control fw-bold',
                    get_string('pdfimport:moduletitle', 'local_oriskill')), 'flex-grow-1') . $skip("mx[$m]"),
                'd-flex align-items-center mb-1') . $rows, 'mb-3');
    }

    echo html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'token', 'value' => $token]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $destination->id]) .
        html_writer::tag('h3', get_string('pdfimport:structure', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::div($tree, 'mb-3') .
        html_writer::div(
            html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'visible', 'value' => 1, 'id' => 'pdfimport-visible',
                'class' => 'form-check-input']) .
            html_writer::label(get_string('pdfimport:visiblenow', 'local_oriskill'), 'pdfimport-visible', true,
                ['class' => 'form-check-label']) .
            html_writer::div(get_string('pdfimport:hiddennote', 'local_oriskill'), 'small text-muted'), 'form-check mb-3') .
        html_writer::tag('h3', get_string('pdfimport:unlock', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::div(get_string('pdfimport:unlockhelp', 'local_oriskill'), 'small text-muted mb-2') .
        html_writer::div(html_writer::select([
                'none' => get_string('pdfimport:unlock:none', 'local_oriskill'),
                '7' => get_string('pdfimport:unlock:7', 'local_oriskill'),
                '14' => get_string('pdfimport:unlock:14', 'local_oriskill'),
                'custom' => get_string('pdfimport:unlock:custom', 'local_oriskill')], 'unlock', 'none', false,
                ['class' => 'form-select', 'id' => 'pdfimport-unlock', 'aria-label' => get_string('pdfimport:unlock', 'local_oriskill')]),
            'mb-1', ['style' => 'max-width: 28rem']) .
        html_writer::div(get_string('pdfimport:unlocknote', 'local_oriskill'), 'small text-muted mb-2') .
        html_writer::div(
            html_writer::label(get_string('pdfimport:unlockdays', 'local_oriskill'), 'pdfimport-days', true, ['class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'number', 'name' => 'unlockdays', 'id' => 'pdfimport-days', 'min' => 1,
                'max' => 3650, 'value' => 7, 'class' => 'form-control']), 'mb-2', ['style' => 'max-width: 14rem']) .
        html_writer::div(
            html_writer::label(get_string('pdfimport:startday', 'local_oriskill'), 'pdfimport-start', true, ['class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'number', 'name' => 'startday', 'id' => 'pdfimport-start', 'min' => 0,
                'max' => 3650, 'value' => 0, 'class' => 'form-control']), 'mb-3', ['style' => 'max-width: 14rem']) .
        html_writer::tag('button', get_string('pdfimport:create', 'local_oriskill'),
            ['type' => 'submit', 'name' => 'action', 'value' => 'create', 'class' => 'btn btn-primary me-2']) .
        html_writer::tag('button', get_string('cancel'),
            ['type' => 'submit', 'name' => 'action', 'value' => 'cancel', 'class' => 'btn btn-outline-secondary',
             'formnovalidate' => 'formnovalidate']),
        ['method' => 'post', 'action' => $url->out(false), 'class' => 'oriskill-card']);
}
echo creator_service::space_end();
echo $OUTPUT->footer();
