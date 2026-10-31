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
 * Frequently asked questions of the trainer's courses: one list per course, in the order given.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\faq_service;
use local_oriskill\team_service;

$creator = creator_service::require_current_creator(new moodle_url('/local/oriskill/faq.php'), 'menu:faq');
$course = creator_service::selected_course($creator);
$url = new moodle_url('/local/oriskill/faq.php', $course ? ['courseid' => $course->id] : []);
$PAGE->set_url($url);
$canchange = team_service::can_change(creator_service::current_role(), '/local/oriskill/faq.php');

if ($course && optional_param('action', '', PARAM_ALPHA) === 'save' && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    $questions = optional_param_array('question', [], PARAM_TEXT);
    $answers = optional_param_array('answer', [], PARAM_TEXT);
    $orders = optional_param_array('order', [], PARAM_INT);
    $rows = [];
    foreach ($questions as $i => $question) {
        $rows[] = ['question' => $question, 'answer' => $answers[$i] ?? '', 'order' => $orders[$i] ?? $i];
    }
    usort($rows, fn($a, $b) => $a['order'] <=> $b['order']);
    $kept = faq_service::save($creator, $course, $rows);
    redirect($url, get_string('faq:saved', 'local_oriskill', $kept), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/faq.php', get_string('menu:faq', 'local_oriskill'));
if (!$course) {
    echo html_writer::div(get_string('nocoursesyet', 'local_oriskill',
        (new moodle_url('/course/edit.php', ['category' => $creator->categoryid]))->out(false)), 'oriskill-card oriskill-empty');
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}
echo creator_service::course_picker($creator, $course, new moodle_url('/local/oriskill/faq.php'));
echo html_writer::tag('p', get_string('faq:intro', 'local_oriskill'), ['class' => 'text-muted']);

$items = faq_service::items((int) $course->id);
// The questions, then three empty rows to add more.
$rows = '';
foreach (array_merge($items, array_fill(0, $canchange ? 3 : 0, null)) as $i => $item) {
    $rows .= html_writer::div(
        html_writer::div(
            html_writer::tag('label', get_string('faq:question', 'local_oriskill') . ' ' . ($i + 1), ['for' => "q$i",
                'class' => 'form-label small fw-semibold']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => "question[$i]", 'id' => "q$i", 'maxlength' => 255,
                'value' => $item->question ?? '', 'class' => 'form-control mb-2'] + ($canchange ? [] : ['readonly' => 'readonly'])) .
            html_writer::tag('label', get_string('faq:answer', 'local_oriskill'), ['for' => "a$i", 'class' => 'visually-hidden']) .
            html_writer::tag('textarea', s($item->answer ?? ''), ['name' => "answer[$i]", 'id' => "a$i", 'rows' => 3,
                'class' => 'form-control', 'placeholder' => get_string('faq:answer', 'local_oriskill')] +
                ($canchange ? [] : ['readonly' => 'readonly'])), 'flex-grow-1') .
        html_writer::div(html_writer::tag('label', get_string('faq:order', 'local_oriskill'), ['for' => "o$i",
            'class' => 'form-label small']) . html_writer::empty_tag('input', ['type' => 'number', 'name' => "order[$i]", 'id' => "o$i",
            'value' => $i + 1, 'min' => 1, 'class' => 'form-control', 'style' => 'width: 5rem'])),
        'd-flex gap-3 py-3 border-bottom');
}
echo html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => 'save'])) .
    $rows . ($canchange ? html_writer::div(get_string('faq:help', 'local_oriskill'), 'small text-muted mt-2') .
    html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-3']) : ''),
    ['method' => 'post', 'action' => $url->out(false), 'class' => 'oriskill-card']);

echo creator_service::space_end();
echo $OUTPUT->footer();
