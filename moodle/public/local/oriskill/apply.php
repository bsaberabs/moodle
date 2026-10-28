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
 * "Become a trainer" application; once approved, the trainer edits their showcase here.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\form\creator_form;

$context = context_system::instance();
$url = new moodle_url('/local/oriskill/apply.php');
$PAGE->set_context($context);
$PAGE->set_url($url);

require_login(null, false);
if (isguestuser()) {
    require_logout();
    redirect(get_login_url());
}
require_capability('local/oriskill:apply', $context);

$creator = $DB->get_record('local_oriskill_creators', ['userid' => $USER->id]);
$title = get_string($creator && $creator->status === 'active' ? 'editshowcase' : 'becometrainer', 'local_oriskill');
$PAGE->set_title($title);
$PAGE->set_heading($title);

// Only an approved trainer edits their space; other states just show where the application stands.
if ($creator && $creator->status !== 'active') {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('applicationstatus:' . $creator->status, 'local_oriskill'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$form = new creator_form($url, ['creator' => $creator ?: null]);
$draftitemid = file_get_submitted_draft_itemid('logo_filemanager');
file_prepare_draft_area($draftitemid, $context->id, 'local_oriskill', 'logo', $creator->id ?? 0, creator_form::LOGO_OPTIONS);
if ($creator) {
    $form->set_data([
        'displayname' => $creator->displayname,
        'tagline' => $creator->tagline,
        'bio_editor' => ['text' => $creator->bio, 'format' => $creator->bioformat],
        'logo_filemanager' => $draftitemid,
    ]);
} else {
    $form->set_data(['logo_filemanager' => $draftitemid]);
}

if ($data = $form->get_data()) {
    if ($creator) {
        $DB->update_record('local_oriskill_creators', (object) [
            'id' => $creator->id,
            'displayname' => $data->displayname,
            'tagline' => $data->tagline,
            'bio' => $data->bio_editor['text'],
            'bioformat' => $data->bio_editor['format'],
            'timemodified' => time(),
        ]);
        $creatorid = (int) $creator->id;
    } else {
        $data->slug = strtolower($data->slug);
        $creatorid = creator_service::apply((int) $USER->id, $data);
    }
    file_save_draft_area_files($data->logo_filemanager, $context->id, 'local_oriskill', 'logo', $creatorid,
        creator_form::LOGO_OPTIONS);
    redirect($url, get_string($creator ? 'changessaved' : 'applicationsent', $creator ? 'moodle' : 'local_oriskill'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
if (!$creator) {
    echo html_writer::tag('p', get_string('becometrainer_intro', 'local_oriskill'), ['class' => 'lead']);
} else {
    echo creator_service::space_menu($creator, '/local/oriskill/apply.php');
    if ($blocker = \local_oriskill\seller_service::selling_blocker($creator)) {
        echo $OUTPUT->notification(get_string($blocker, 'local_oriskill', (object) [
            'payment' => (new moodle_url('/local/oriskill/payment.php'))->out(false),
            'plan' => (new moodle_url('/local/moderncommerce/subscribe.php'))->out(false),
        ]), 'warning');
    }
    echo html_writer::tag('p', html_writer::link(new moodle_url('/course/edit.php', ['category' => $creator->categoryid]),
        get_string('createcourse', 'local_oriskill'), ['class' => 'btn btn-outline-primary']) . ' ' .
        html_writer::link(new moodle_url('/course/index.php', ['categoryid' => $creator->categoryid]),
        get_string('mycourses', 'local_oriskill'), ['class' => 'btn btn-outline-secondary']));
}
$form->display();
echo $OUTPUT->footer();
