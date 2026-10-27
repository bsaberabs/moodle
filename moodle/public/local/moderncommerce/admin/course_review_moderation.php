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
 * Modern Commerce course review moderation route.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\output\course_reviews_page;

$context = context_system::instance();
require_login();

$mode = 'reviews';
$courseid = optional_param('courseid', 0, PARAM_INT);
course_reviews_page::require_access($context);
if ($courseid > 0) {
    get_course($courseid);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/course_review_moderation.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(course_reviews_page::title($mode, $courseid));

$shellhtml = admin_shell::create('reviewmoderation')
    ->set_title(course_reviews_page::title($mode, $courseid))
    ->set_subtitle(course_reviews_page::subtitle($mode))
    ->set_actions(course_reviews_page::actions($mode))
    ->set_content(course_reviews_page::content($OUTPUT, $mode, $courseid))
    ->render($OUTPUT);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
