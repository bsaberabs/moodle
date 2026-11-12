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
 * Import of a PDF with bookmarks: a new course, one module per chapter, one lesson per sub-chapter.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\pdf_import_service as pis;
use local_oriskill\team_service;

$url = new moodle_url('/local/oriskill/pdfimport.php');
$creator = creator_service::require_current_creator($url, 'menu:pdfimport');
$courses = new moodle_url('/local/oriskill/courses.php');
$token = optional_param('token', '', PARAM_ALPHANUM);

if ($action = optional_param('action', '', PARAM_ALPHA)) {
    require_sesskey();
    try {
        // A read-only role sees the page but starts nothing.
        if (in_array($action, ['upload', 'create'], true)
                && !team_service::can_change(creator_service::current_role(), '/local/oriskill/courses.php')) {
            throw new moodle_exception('error:notallowed', 'local_oriskill');
        }
        if ($action === 'upload') {
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
            redirect(new moodle_url($url, ['token' => $token]));
        } else if ($action === 'cancel') {
            if (pis::is_queued((int) $USER->id, $token)) {
                throw new moodle_exception('pdfimport:err:queued', 'local_oriskill');
            }
            pis::discard((int) $USER->id, $token);
            redirect($url);
        } else if ($action === 'create') {
            if (pis::is_queued((int) $USER->id, $token)) {
                throw new moodle_exception('pdfimport:err:queued', 'local_oriskill');
            }
            $plan = pis::load((int) $USER->id, $token);
            $kept = pis::apply_edits($plan,
                optional_param_array('mt', [], PARAM_TEXT), optional_param_array('mx', [], PARAM_INT),
                optional_param_array('lt', [], PARAM_TEXT), optional_param_array('lx', [], PARAM_INT));
            $fullname = pis::clean_title(required_param('fullname', PARAM_TEXT));
            $shortname = pis::clean_title(required_param('shortname', PARAM_TEXT));
            if (!$kept->modules) {
                throw new moodle_exception('pdfimport:err:nothing', 'local_oriskill');
            }
            if ($fullname === '' || $shortname === '') {
                throw new moodle_exception('pdfimport:err:names', 'local_oriskill');
            }
            if (!pis::shortname_free($shortname)) {
                throw new moodle_exception('pdfimport:err:shortname', 'local_oriskill');
            }
            if (pis::is_large($kept)) {
                pis::queue($creator, (int) $USER->id, $token, $kept, $fullname, $shortname);
                redirect($courses, get_string('pdfimport:queued', 'local_oriskill'), null,
                    \core\output\notification::NOTIFY_INFO);
            }
            $courseid = pis::create_course($creator, (int) $USER->id, $token, $kept, $fullname, $shortname);
            pis::discard((int) $USER->id, $token);
            if (pis::$pdfnotattached) {
                redirect(new moodle_url('/course/view.php', ['id' => $courseid]),
                    get_string('pdfimport:created', 'local_oriskill') . ' ' . get_string('pdfimport:attachfailed', 'local_oriskill'),
                    null, \core\output\notification::NOTIFY_WARNING);
            }
            redirect(new moodle_url('/course/view.php', ['id' => $courseid]), get_string('pdfimport:created', 'local_oriskill'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (moodle_exception $e) {
        redirect($action === 'create' && $token ? new moodle_url($url, ['token' => $token]) : $url, $e->getMessage(), null,
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
    } else {
        echo html_writer::tag('form',
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'upload']) .
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
    // Preview: names, and the tree of modules and lessons to check.
    $suggest = pis::clean_title(preg_replace('/\.pdf$/i', '', (string) ($plan->filename ?? '')));
    $short = core_text::strtoupper(core_text::substr(preg_replace('/[^A-Za-z0-9]+/', '', $suggest), 0, 20)) ?: 'PDF';
    for ($i = 2; !pis::shortname_free($short); $i++) {
        $short = core_text::substr($short, 0, 17) . $i;
    }
    $lessons = 0;
    foreach ($plan->modules as $module) {
        $lessons += count($module->lessons);
    }
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
        html_writer::div(
            html_writer::label(get_string('fullnamecourse'), 'pdfimport-full', true, ['class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'fullname', 'id' => 'pdfimport-full',
                'value' => $suggest, 'class' => 'form-control', 'maxlength' => 250, 'required' => 'required']), 'mb-3') .
        html_writer::div(
            html_writer::label(get_string('shortnamecourse'), 'pdfimport-short', true, ['class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'shortname', 'id' => 'pdfimport-short',
                'value' => $short, 'class' => 'form-control', 'maxlength' => 100, 'required' => 'required']), 'mb-3') .
        html_writer::tag('h3', get_string('pdfimport:structure', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::div($tree, 'mb-3') .
        html_writer::div(get_string('pdfimport:hiddennote', 'local_oriskill'), 'small text-muted mb-3') .
        html_writer::tag('button', get_string('pdfimport:create', 'local_oriskill'),
            ['type' => 'submit', 'name' => 'action', 'value' => 'create', 'class' => 'btn btn-primary me-2']) .
        html_writer::tag('button', get_string('cancel'),
            ['type' => 'submit', 'name' => 'action', 'value' => 'cancel', 'class' => 'btn btn-outline-secondary',
             'formnovalidate' => 'formnovalidate']),
        ['method' => 'post', 'action' => $url->out(false), 'class' => 'oriskill-card']);
}
echo creator_service::space_end();
echo $OUTPUT->footer();
