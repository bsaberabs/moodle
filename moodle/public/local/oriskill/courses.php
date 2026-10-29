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
 * The trainer's courses, as cards: sale state, price, learners and shortcuts.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use core_course\external\course_summary_exporter;
use local_moderncommerce\services\pricing_service;
use local_oriskill\creator_service;
use local_oriskill\seller_service;

$url = new moodle_url('/local/oriskill/courses.php');
$creator = creator_service::require_current_creator($url, 'mycourses');

$courses = seller_service::priced_courses($creator);
$learners = seller_service::learner_counts($creator, array_keys($courses));
$newcourseurl = new moodle_url('/course/edit.php', ['category' => $creator->categoryid]);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/courses.php', get_string('mycourses', 'local_oriskill'));

echo html_writer::div(
    html_writer::tag('p', get_string('courses_intro', 'local_oriskill'), ['class' => 'mb-0 text-muted']) .
    html_writer::link($newcourseurl, '<i class="fa fa-plus me-1" aria-hidden="true"></i>' .
        get_string('createcourse', 'local_oriskill'), ['class' => 'btn btn-primary']),
    'd-flex flex-wrap justify-content-between align-items-center gap-3 mb-4');

if (!$courses) {
    echo html_writer::div('<i class="fa fa-graduation-cap" aria-hidden="true"></i>' .
        get_string('nocoursesyet', 'local_oriskill', $newcourseurl->out(false)), 'oriskill-card oriskill-empty');
}

$cards = '';
foreach ($courses as $courseid => $row) {
    $image = course_summary_exporter::get_course_image($row->course) ?: $OUTPUT->get_generated_image_for_id($courseid);
    $meta = [
        '<i class="fa fa-user-graduate me-1" aria-hidden="true"></i>' .
            get_string('learnerscount', 'local_oriskill', (int) ($learners[$courseid] ?? 0)),
    ];
    if ($row->amount !== null) {
        $meta[] = '<i class="fa fa-tag me-1" aria-hidden="true"></i>' . pricing_service::format_price($row->amount) .
            ($row->compareamount ? ' <s>' . pricing_service::format_price($row->compareamount) . '</s>' : '');
    }
    if (!$row->course->visible) {
        $meta[] = '<i class="fa fa-eye-slash me-1" aria-hidden="true"></i>' . get_string('coursestatus:hidden', 'local_oriskill');
    }
    $cards .= html_writer::div(
        html_writer::div(creator_service::course_status_badge($row), 'oriskill-course-image',
            ['style' => 'background-image: url("' . s($image) . '")']) .
        html_writer::div(
            html_writer::div(format_string($row->course->fullname), 'oriskill-course-title') .
            html_writer::div(implode('', array_map(fn($m) => html_writer::span($m), $meta)), 'oriskill-course-meta') .
            html_writer::div(
                html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]),
                    get_string('course:open', 'local_oriskill'), ['class' => 'btn btn-sm btn-primary']) .
                html_writer::link(new moodle_url('/course/edit.php', ['id' => $courseid]),
                    get_string('course:settings', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary']) .
                html_writer::link(new moodle_url('/local/oriskill/pricing.php'),
                    get_string('pricing', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary']),
                'oriskill-course-actions'),
            'oriskill-course-body'),
        'oriskill-course');
}
echo html_writer::div($cards, 'oriskill-courses');

echo creator_service::space_end();
echo $OUTPUT->footer();
