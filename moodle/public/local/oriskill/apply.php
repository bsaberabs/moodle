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
require_once(__DIR__ . '/lib.php');

use local_oriskill\creator_service;
use local_oriskill\form\creator_form;

$context = context_system::instance();
$url = new moodle_url('/local/oriskill/apply.php');
$PAGE->set_context($context);
$PAGE->set_url($url);

if (!isloggedin() || isguestuser()) {
    $PAGE->set_title(get_string('becometrainer', 'local_oriskill'));
    $PAGE->set_heading(get_string('becometrainer', 'local_oriskill'));
    local_oriskill_visitor_page($url, html_writer::tag('p', get_string('becometrainer_intro', 'local_oriskill'),
        ['class' => 'lead']));
}
require_login(null, false);
require_capability('local/oriskill:apply', $context);

$space = \local_oriskill\team_service::current_space();
if ($space && \local_oriskill\team_service::can($space['role'], '/local/oriskill/apply.php')) {
    $creator = $space['creator'];
} else {
    $creator = $DB->get_record('local_oriskill_creators', ['userid' => $USER->id]);
}
if ($creator && $creator->status === 'active') {
    // Approved trainer: this page is "My showcase" in the trainer space.
    $PAGE->set_title(get_string('menu:profile', 'local_oriskill'));
    creator_service::prepare_space_page();
} else {
    $PAGE->set_title(get_string('becometrainer', 'local_oriskill'));
    $PAGE->set_heading(get_string('becometrainer', 'local_oriskill'));
}

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
    $bannerdraft = file_get_submitted_draft_itemid('banner_filemanager');
    file_prepare_draft_area($bannerdraft, $context->id, 'local_oriskill', 'banner', $creator->id,
        creator_form::BANNER_OPTIONS);
    $style = creator_service::showcase_style($creator);
    $form->set_data([
        'displayname' => $creator->displayname,
        'tagline' => $creator->tagline,
        'bio_editor' => ['text' => $creator->bio, 'format' => $creator->bioformat],
        'logo_filemanager' => $draftitemid,
        'primarycolor' => $style['primary'],
        'accentcolor' => $style['accent'],
        'fontstyle' => $creator->fontstyle ?: 'modern',
        'banner_filemanager' => $bannerdraft,
        'customdomain' => $creator->customdomain,
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
            'primarycolor' => creator_service::valid_color($data->primarycolor),
            'accentcolor' => creator_service::valid_color($data->accentcolor),
            'fontstyle' => isset(creator_service::FONTS[$data->fontstyle]) ? $data->fontstyle : 'modern',
            'customdomain' => creator_service::normalise_domain((string) $data->customdomain),
            'timemodified' => time(),
        ]);
        $creatorid = (int) $creator->id;
        file_save_draft_area_files($data->banner_filemanager, $context->id, 'local_oriskill', 'banner', $creatorid,
            creator_form::BANNER_OPTIONS);
        \local_oriskill\log_service::add($creator, 'showcase_saved');
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
    echo creator_service::space_start($creator, '/local/oriskill/apply.php', get_string('menu:profile', 'local_oriskill'));
    if ($blocker = \local_oriskill\seller_service::selling_blocker($creator)) {
        echo $OUTPUT->notification(get_string($blocker, 'local_oriskill', (object) [
            'payment' => (new moodle_url('/local/oriskill/payment.php'))->out(false),
            'plan' => (new moodle_url('/local/moderncommerce/subscribe.php'))->out(false),
        ]), 'warning');
    }
    // The trainer's account controls where their sales are paid: recommend the 2FA until they set it up.
    if (get_config('tool_mfa', 'enabled') && !$DB->record_exists_select('tool_mfa',
            "userid = ? AND factor IN ('totp', 'webauthn') AND revoked = 0", [$USER->id])) {
        echo $OUTPUT->notification(get_string('mfarecommended', 'local_oriskill',
            (new moodle_url('/admin/tool/mfa/user_preferences.php'))->out(false)), 'info');
    }
}
$form->display();
if ($creator) {
    echo creator_service::space_end();
}
echo $OUTPUT->footer();
