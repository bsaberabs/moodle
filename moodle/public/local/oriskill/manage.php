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
 * Trainer applications and spaces: approve, reject, suspend, reactivate.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_oriskill\creator_service;

admin_externalpage_setup('local_oriskill_manage');
$url = new moodle_url('/local/oriskill/manage.php');

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    $id = required_param('id', PARAM_INT);
    switch ($action) {
        case 'approve':
            creator_service::approve($id);
            break;
        case 'reject':
            creator_service::reject($id);
            break;
        case 'suspend':
            creator_service::suspend($id);
            break;
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$table = new html_table();
$table->head = [get_string('field:displayname', 'local_oriskill'), get_string('user'),
    get_string('field:slug', 'local_oriskill'), get_string('field:motivation', 'local_oriskill'),
    get_string('status'), get_string('actions')];
$table->attributes['class'] = 'generaltable';

$creators = $DB->get_records('local_oriskill_creators', null,
    "CASE status WHEN 'pending' THEN 0 WHEN 'active' THEN 1 ELSE 2 END, timecreated DESC");
foreach ($creators as $creator) {
    $user = core_user::get_user($creator->userid);
    $link = function(string $action, string $label, string $class) use ($url, $creator) {
        return html_writer::link(new moodle_url($url, ['action' => $action, 'id' => $creator->id, 'sesskey' => sesskey()]),
            get_string($label, 'local_oriskill'), ['class' => "btn btn-sm $class me-1"]);
    };
    $actions = match ($creator->status) {
        'pending' => $link('approve', 'approve', 'btn-primary') . $link('reject', 'reject', 'btn-outline-danger'),
        'active' => $link('suspend', 'suspend', 'btn-outline-danger'),
        'suspended', 'rejected' => $link('approve', 'reactivate', 'btn-outline-primary'),
        default => '',
    };
    $table->data[] = [
        format_string($creator->displayname),
        html_writer::link(new moodle_url('/user/profile.php', ['id' => $user->id]), fullname($user)) . '<br>' . s($user->email),
        $creator->status === 'active'
            ? html_writer::link(creator_service::showcase_url($creator), '/f/' . s($creator->slug)) : '/f/' . s($creator->slug),
        format_text($creator->motivation, FORMAT_PLAIN),
        get_string('status:' . $creator->status, 'local_oriskill'),
        $actions,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecreators', 'local_oriskill'));
echo html_writer::tag('p', get_string('managecreators_intro', 'local_oriskill', (new moodle_url('/local/oriskill/apply.php'))->out(false)));
echo $creators ? html_writer::table($table) : $OUTPUT->notification(get_string('nocreators', 'local_oriskill'), 'info');
echo $OUTPUT->footer();
