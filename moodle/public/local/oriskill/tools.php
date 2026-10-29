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
 * Teaching tools of a course: forum, satisfaction questionnaire, certificate, and shortcuts to quizzes, H5P,
 * assignments and SCORM packages.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\log_service;
use local_oriskill\pedagogy_service;

$creator = creator_service::require_current_creator(new moodle_url('/local/oriskill/tools.php'), 'menu:tools');
$course = creator_service::selected_course($creator);
$url = new moodle_url('/local/oriskill/tools.php', $course ? ['courseid' => $course->id] : []);
$PAGE->set_url($url);

if ($course && ($add = optional_param('add', '', PARAM_ALPHA)) && confirm_sesskey()) {
    try {
        if ($add === 'forum') {
            pedagogy_service::add_forum($course);
        } else if ($add === 'feedback') {
            pedagogy_service::add_feedback($course);
        } else if ($add === 'certificate') {
            pedagogy_service::add_certificate($course, $creator);
        } else {
            throw new moodle_exception('invaliddata', 'error');
        }
        log_service::add($creator, 'tool_added', ['tool' => get_string('tools:' . $add, 'local_oriskill')], (int) $course->id);
        redirect($url, get_string('tools:added', 'local_oriskill'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/tools.php', get_string('menu:tools', 'local_oriskill'));
if (!$course) {
    echo html_writer::div(get_string('nocoursesyet', 'local_oriskill',
        (new moodle_url('/course/edit.php', ['category' => $creator->categoryid]))->out(false)), 'oriskill-card oriskill-empty');
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}
echo creator_service::course_picker($creator, $course, new moodle_url('/local/oriskill/tools.php'));
echo html_writer::tag('p', get_string('tools:intro', 'local_oriskill'), ['class' => 'text-muted']);

$overview = pedagogy_service::overview($course);
$addbutton = fn(string $tool) => html_writer::tag('form',
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'add', 'value' => $tool]) .
    html_writer::tag('button', get_string('tools:add_' . $tool, 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    ['method' => 'post', 'action' => $url->out(false)]);
$card = fn(string $icon, string $title, string $text, string $actions) => html_writer::div(
    html_writer::tag('h3', '<i class="bi ' . $icon . ' me-2" aria-hidden="true"></i>' . $title, ['class' => 'h5']) .
    html_writer::tag('p', $text, ['class' => 'text-muted small']) . html_writer::div($actions, 'mt-auto'),
    'oriskill-card d-flex flex-column');

// The three tools built for the trainer.
$cards = '';
foreach ([['forum', 'forum', '/mod/forum/view.php'], ['feedback', 'feedback', '/mod/feedback/analysis.php'],
        ['certificate', 'coursecertificate', '/mod/coursecertificate/view.php']] as [$tool, $module, $path]) {
    $state = $overview[$module];
    $actions = $state->count
        ? html_writer::div(get_string('tools:' . $tool . '_done', 'local_oriskill', $state->responses), 'small mb-2') .
            html_writer::link(new moodle_url($path, ['id' => $state->cmid]), get_string('tools:open', 'local_oriskill'),
                ['class' => 'btn btn-outline-secondary'])
        : $addbutton($tool);
    $cards .= $card(pedagogy_service::ACTIVITIES[$module], get_string('tools:' . $tool, 'local_oriskill'),
        get_string('tools:' . $tool . '_desc', 'local_oriskill'), $actions);
}
echo html_writer::div($cards, 'oriskill-grid-3 mb-4');

// Shortcuts to Moodle's activities.
$rows = '';
foreach (['quiz', 'h5pactivity', 'assign', 'scorm'] as $module) {
    $rows .= html_writer::tag('li',
        html_writer::div(html_writer::div('<i class="bi ' . pedagogy_service::ACTIVITIES[$module] . ' me-2" aria-hidden="true"></i>' .
            get_string('modulenameplural', $module), 'oriskill-row-title') .
            html_writer::div(get_string('tools:' . $module . '_desc', 'local_oriskill') . ' · ' .
                get_string('tools:incourse', 'local_oriskill', $overview[$module]->count), 'oriskill-row-sub'), 'oriskill-row-main') .
        html_writer::link(pedagogy_service::add_url($course, $module), get_string('tools:addactivity', 'local_oriskill'),
            ['class' => 'btn btn-sm btn-outline-secondary']));
}
echo html_writer::div(html_writer::tag('h3', get_string('tools:more', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('ul', $rows, ['class' => 'oriskill-rows']), 'oriskill-card');

echo creator_service::space_end();
echo $OUTPUT->footer();
