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
 * Badges of a course of the trainer, made in one click.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\gamification_service as gs;

$creator = creator_service::require_current_creator(new moodle_url('/local/oriskill/gamification.php'), 'menu:gamification');
$course = creator_service::selected_course($creator);
$url = new moodle_url('/local/oriskill/gamification.php', $course ? ['courseid' => $course->id] : []);
$PAGE->set_url($url);

if ($course && ($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    try {
        if ($action === 'create') {
            $made = gs::create($creator, $course, optional_param('modules', 0, PARAM_BOOL));
            redirect($url, get_string('gamif:made', 'local_oriskill', $made), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'toggle') {
            gs::toggle($course, required_param('id', PARAM_INT));
        }
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/gamification.php', get_string('menu:gamification', 'local_oriskill'));
if (!$course) {
    echo html_writer::div(get_string('nocoursesyet', 'local_oriskill',
        (new moodle_url('/course/edit.php', ['category' => $creator->categoryid]))->out(false)), 'oriskill-card oriskill-empty');
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}
echo creator_service::course_picker($creator, $course, new moodle_url('/local/oriskill/gamification.php'));
echo html_writer::tag('p', get_string('gamif:intro', 'local_oriskill'), ['class' => 'text-muted']);

$sections = gs::completable($course);
$badges = gs::badges($course);
$rows = '';
foreach ($badges as $badge) {
    $active = in_array((int) $badge->status, [1, 3], true);
    $rows .= html_writer::tag('tr', html_writer::tag('td', s($badge->name)) .
        html_writer::tag('td', html_writer::span(get_string($active ? 'courses:on' : 'courses:off', 'local_oriskill'),
            'oriskill-badge ' . ($active ? 'is-good' : ''))) .
        html_writer::tag('td', get_string('gamif:awarded', 'local_oriskill', $badge->awarded)) .
        html_writer::tag('td', html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(),
            'action' => 'toggle', 'id' => $badge->id])) . html_writer::tag('button', get_string($active ? 'engage:turnoff' : 'engage:turnon',
            'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']), ['method' => 'post',
            'action' => $url->out(false), 'class' => 'd-inline']) . ' ' .
            html_writer::link(new moodle_url('/badges/overview.php', ['id' => $badge->id]), get_string('gamif:details', 'local_oriskill'),
                ['class' => 'btn btn-sm btn-link'])));
}
echo html_writer::div(html_writer::tag('h3', get_string('gamif:badges', 'local_oriskill'), ['class' => 'h5']) .
    ($rows ? html_writer::tag('table', $rows, ['class' => 'table align-middle']) : html_writer::div(get_string('gamif:none',
        'local_oriskill'), 'text-muted mb-3')) .
    ($sections ? html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(),
        'action' => 'create'])) . html_writer::div(html_writer::checkbox('modules', 1, true,
        get_string('gamif:withmodules', 'local_oriskill', count($sections)), ['class' => 'form-check-input']), 'form-check mb-2') .
        html_writer::tag('button', get_string('gamif:create', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']),
        ['method' => 'post', 'action' => $url->out(false)])
        : html_writer::div(get_string('gamif:nocompletion', 'local_oriskill'), 'alert alert-info')),
    'oriskill-card');

echo creator_service::space_end();
echo $OUTPUT->footer();
