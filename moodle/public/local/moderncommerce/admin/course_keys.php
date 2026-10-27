<?php
// This file is part of Moodle and is licensed under the
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
 * Course-specific enrollment key management (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\keys_page;

$courseid = optional_param('courseid', 0, PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:generatekeys', $context);

// No course selected: fall back to the full keys list.
if (empty($courseid)) {
    redirect(new moodle_url('/local/moderncommerce/admin/keys.php'));
}

$course = $DB->get_record('course', ['id' => $courseid], 'id, fullname', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/course_keys.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('managekeys', 'local_moderncommerce') . ': ' . format_string($course->fullname));

$shellhtml = keys_page::render(
    $OUTPUT,
    'course',
    $courseid,
    format_string($course->fullname),
    'keys',
    get_string('managekeys', 'local_moderncommerce') . ': ' . format_string($course->fullname)
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
