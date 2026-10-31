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
 * Comments under a lesson, sent from its page: write or answer one; the team hides or shows one. Back to the lesson.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\comment_service as cs;

require_sesskey();
$action = required_param('action', PARAM_ALPHA);

if ($action === 'add') {
    $cmid = required_param('cmid', PARAM_INT);
    [$course, $cm] = get_course_and_cm_from_cmid($cmid);
    require_login($course, false, $cm);
    $creator = cs::creator_of($course);
    if (!$creator) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    cs::add($creator, $course, $cm, (int) $USER->id, required_param('message', PARAM_TEXT),
        optional_param('parentid', 0, PARAM_INT));
    redirect(cs::lesson_url($cmid), get_string('comments:sent', 'local_oriskill'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Hide or show: the team of the course's trainer only.
$comment = $DB->get_record(cs::TABLE, ['id' => required_param('id', PARAM_INT)], '*', MUST_EXIST);
[$course, $cm] = get_course_and_cm_from_cmid($comment->cmid);
require_login($course, false, $cm);
$creator = cs::creator_of($course);
if (!$creator || cs::team_role($creator, (int) $USER->id) === null || !in_array($action, ['hide', 'show'], true)) {
    throw new moodle_exception('error:notallowed', 'local_oriskill');
}
cs::set_hidden($comment, $action === 'hide');
redirect(cs::lesson_url((int) $comment->cmid));
