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
 * Log of the trainer space: who changed what, and when.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\log_service;

$page = optional_param('page', 0, PARAM_INT);
$url = new moodle_url('/local/oriskill/log.php');
$creator = creator_service::require_current_creator($url, 'menu:log');

[$entries, $total] = log_service::entries($creator, $page);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/log.php', get_string('menu:log', 'local_oriskill'));
echo html_writer::tag('p', get_string('log:intro', 'local_oriskill'), ['class' => 'text-muted']);
if (!$entries) {
    echo html_writer::div(get_string('log:empty', 'local_oriskill'), 'oriskill-card text-muted');
} else {
    $users = [];
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('date'), get_string('log:who', 'local_oriskill'), get_string('log:what', 'local_oriskill')];
    foreach ($entries as $entry) {
        if (!$entry->userid) {
            $who = html_writer::span(get_string('log:automatic', 'local_oriskill'), 'oriskill-badge');
        } else {
            $users[$entry->userid] = $users[$entry->userid] ?? core_user::get_user($entry->userid);
            $who = $users[$entry->userid] ? fullname($users[$entry->userid]) : '?';
        }
        $table->data[] = [
            userdate($entry->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            $who,
            html_writer::div(s(log_service::describe($entry)), '', ['style' => 'white-space: normal; min-width: 18rem']),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card');
    echo $OUTPUT->paging_bar($total, $page, log_service::PERPAGE, $url);
}
echo creator_service::space_end();
echo $OUTPUT->footer();
