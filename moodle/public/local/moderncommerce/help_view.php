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
 * Modern Commerce course help viewer.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$doc = required_param('doc', PARAM_ALPHANUMEXT);
$courseid = required_param('courseid', PARAM_INT);

if ($courseid <= 0 || $courseid == SITEID) {
    throw new moodle_exception('invalidcourseid', 'error');
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/help_view.php', ['doc' => $doc, 'courseid' => $courseid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('helptitle', 'local_moderncommerce', format_string($course->fullname)));
$PAGE->set_heading(get_string('helpdocumentation', 'local_moderncommerce'));
$PAGE->set_secondary_navigation(false);
$PAGE->add_body_class('drawer-ease');
$PAGE->add_body_class('drawer-open-right');
$PAGE->add_body_class('hide-nav-drawer');

$docsdir = __DIR__ . '/docs';
$filepath = $docsdir . '/' . $doc . '.md';
if (!file_exists($filepath)) {
    throw new moodle_exception('filenotfound', 'error');
}

// Prepare course-aware links.
$courseurl = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
$completionurl = (new moodle_url('/course/completion.php', ['id' => $courseid]))->out(false);

$raw = file_get_contents($filepath);
$raw = str_replace(['{{courseurl}}', '{{completionurl}}'], [$courseurl, $completionurl], $raw);

echo $OUTPUT->header();
// Simple render: use format_text with Markdown.
$rendered = format_text($raw, FORMAT_MARKDOWN, ['trusted' => true, 'noclean' => true]);
echo html_writer::start_div('container');
echo html_writer::tag('h2', get_string('helpcoursecertificates', 'local_moderncommerce'));
// CTA buttons.
echo html_writer::start_div('mb-3');
echo html_writer::link($courseurl, get_string('openthiscourse', 'local_moderncommerce'), [
    'class' => 'mc-button btn-mc-primary me-2',
    'data-mc-button' => 'primary',
]);
echo html_writer::link(
    $completionurl,
    get_string('coursecompletionsettings', 'local_moderncommerce'),
    ['class' => 'mc-button mc-btn-soft', 'data-mc-button' => 'soft']
);
echo html_writer::end_div();
echo html_writer::div($rendered, 'card card-body');

echo html_writer::end_div();

echo $OUTPUT->footer();
