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
 * Receives the time a learner's course page was used (sent every minute and when the page is left).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
// The sending must not count as course activity nor keep the session alive on its own.
define('NO_SESSION_UPDATE', true);

require_once(__DIR__ . '/../../config.php');

use local_oriskill\studytime_service;

$courseid = required_param('courseid', PARAM_INT);
$seconds = required_param('seconds', PARAM_INT);
require_sesskey();
if (!isloggedin() || isguestuser()) {
    http_response_code(401);
    die;
}
$course = get_course($courseid);
$PAGE->set_context(context_course::instance($course->id));
if (studytime_service::page_mode($course) === null) {
    http_response_code(403);
    die;
}
studytime_service::beat((int) $course->id, (int) $USER->id, $seconds);
http_response_code(204);
