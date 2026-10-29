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
 * Create or edit a capture page.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\capture_service;
use local_oriskill\creator_service;
use local_oriskill\form\capturepage_form;
use local_oriskill\log_service;
use local_oriskill\orderform_service;
use local_oriskill\seller_service;

$id = optional_param('id', 0, PARAM_INT);
$url = new moodle_url('/local/oriskill/capturepage.php', $id ? ['id' => $id] : []);
$creator = creator_service::require_current_creator($url, 'menu:capturepages');
$listurl = new moodle_url('/local/oriskill/capturepages.php');
$context = context_system::instance();

$page = $id ? $DB->get_record(capture_service::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST) : null;
$courses = array_map(fn($row) => format_string($row->course->fullname), seller_service::priced_courses($creator));
$forms = array_map(fn($f) => format_string($f->title), orderform_service::forms($creator));

$mform = new capturepage_form($url, ['creator' => $creator, 'pageid' => $id, 'courses' => $courses, 'forms' => $forms]);
$draftitemid = file_get_submitted_draft_itemid('image_filemanager');
file_prepare_draft_area($draftitemid, $context->id, 'local_oriskill', 'capturepage', $id, capture_service::IMAGE_OPTIONS);
$defaults = ['id' => $id, 'image_filemanager' => $draftitemid];
if ($page) {
    $defaults += (array) $page;
    $defaults['description_editor'] = ['text' => $page->description, 'format' => $page->descriptionformat];
    $defaults['courseids'] = array_filter(array_map('intval', explode(',', (string) $page->courseids)));
    $defaults['redirectformid'] = (int) $page->redirectformid;
}
$mform->set_data($defaults);

if ($mform->is_cancelled()) {
    redirect($listurl);
}
if ($data = $mform->get_data()) {
    $data->description = $data->description_editor['text'];
    $data->descriptionformat = $data->description_editor['format'];
    $saved = capture_service::save($creator, $data);
    file_save_draft_area_files($data->image_filemanager, $context->id, 'local_oriskill', 'capturepage', $saved->id,
        capture_service::IMAGE_OPTIONS);
    log_service::add($creator, 'capturepage_saved', ['title' => $saved->title,
        'status' => get_string('orderform:' . $saved->status, 'local_oriskill')]);
    redirect($listurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/capturepages.php',
    $page ? format_string($page->title) : get_string('capture:new', 'local_oriskill'));
echo html_writer::div(html_writer::link($listurl, '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' .
    get_string('menu:capturepages', 'local_oriskill')), 'mb-3');
echo html_writer::div($mform->render(), 'oriskill-card');
echo creator_service::space_end();
echo $OUTPUT->footer();
