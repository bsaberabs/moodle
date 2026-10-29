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
 * Guide for trainers: putting training videos in a course (upload, or YouTube / Vimeo link).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;

$url = new moodle_url('/local/oriskill/videos.php');
$creator = creator_service::require_current_creator($url, 'menu:videos');

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/videos.php', get_string('menu:videos', 'local_oriskill'));

$a = (object) ['maxsize' => display_size(get_max_upload_file_size($CFG->maxbytes))];
echo html_writer::tag('p', get_string('videos:intro', 'local_oriskill'), ['class' => 'lead']);
echo html_writer::div(
    html_writer::div(html_writer::tag('h3', '<i class="fa fa-cloud-arrow-up" aria-hidden="true"></i>' .
        get_string('videos:upload_title', 'local_oriskill')) . get_string('videos:upload', 'local_oriskill', $a),
        'oriskill-card') .
    html_writer::div(html_writer::tag('h3', '<i class="fa fa-link" aria-hidden="true"></i>' .
        get_string('videos:link_title', 'local_oriskill')) . get_string('videos:link', 'local_oriskill'),
        'oriskill-card'),
    'oriskill-grid-2 oriskill-guide');
echo html_writer::div(html_writer::tag('h3', '<i class="fa fa-lightbulb" aria-hidden="true"></i>' .
    get_string('videos:tips_title', 'local_oriskill')) . get_string('videos:tips', 'local_oriskill', $a),
    'oriskill-card oriskill-guide');

echo creator_service::space_end();
echo $OUTPUT->footer();
