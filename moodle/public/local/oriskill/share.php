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
 * Share codes: give a course or an order form to another trainer, or copy one shared with you.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\share_service as ss;

$url = new moodle_url('/local/oriskill/share.php');
$creator = creator_service::require_current_creator($url, 'menu:share');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    try {
        if ($action === 'create') {
            [$type, $id] = array_pad(explode(':', clean_param(required_param('item', PARAM_RAW_TRIMMED), PARAM_NOTAGS)), 2, 0);
            $record = ss::create($creator, $type, (int) $id);
            redirect($url, get_string('share:created', 'local_oriskill', $record->code), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'toggle' || $action === 'delete') {
            ss::change($creator, required_param('id', PARAM_INT), $action);
        } else if ($action === 'import') {
            core_php_time_limit::raise(600);
            [$type, $newid] = ss::import($creator, required_param('code', PARAM_TEXT));
            redirect($type === 'course' ? new moodle_url('/course/view.php', ['id' => $newid])
                : new moodle_url('/local/oriskill/orderform.php', ['id' => $newid]),
                get_string('share:imported_' . $type, 'local_oriskill'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$post = fn(string $action, string $inner, array $hidden = [], string $class = 'd-inline') => html_writer::tag('form',
    html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => $action] + $hidden)) . $inner,
    ['method' => 'post', 'action' => $url->out(false), 'class' => $class]);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/share.php', get_string('menu:share', 'local_oriskill'));
echo html_writer::tag('p', get_string('share:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Copy something shared with me.
echo html_writer::div(html_writer::tag('h3', get_string('share:import', 'local_oriskill'), ['class' => 'h5']) .
    $post('import', html_writer::div(html_writer::empty_tag('input', ['type' => 'text', 'name' => 'code', 'required' => 'required',
        'class' => 'form-control', 'placeholder' => 'ABCD-EFGH-IJKL', 'aria-label' => get_string('share:code', 'local_oriskill'),
        'style' => 'max-width: 16rem']) . html_writer::tag('button', get_string('share:importbutton', 'local_oriskill'),
        ['type' => 'submit', 'class' => 'btn btn-primary']), 'd-flex gap-2') .
        html_writer::div(get_string('share:import_help', 'local_oriskill'), 'form-text'), [], ''),
    'oriskill-card mb-4');

// My codes.
$options = [];
foreach ($DB->get_records('course', ['category' => $creator->categoryid], 'fullname', 'id, fullname') as $course) {
    $options[get_string('categories:type_course', 'local_oriskill')]['course:' . $course->id] = format_string($course->fullname);
}
foreach ($DB->get_records('local_oriskill_orderforms', ['creatorid' => $creator->id], 'title', 'id, title') as $form) {
    $options[get_string('categories:type_orderform', 'local_oriskill')]['orderform:' . $form->id] = format_string($form->title);
}
// Groups of the select: one per kind ([[group => options], ...] for html_writer::select).
$options = array_map(fn($group, $items) => [$group => $items], array_keys($options), array_values($options));
$list = '';
foreach (ss::codes($creator) as $record) {
    $list .= html_writer::div(
        html_writer::div(html_writer::tag('code', s($record->code), ['class' => 'fs-5']) . ' ' .
            html_writer::span(get_string($record->enabled ? 'courses:on' : 'courses:off', 'local_oriskill'),
                'oriskill-badge ' . ($record->enabled ? 'is-good' : '')) .
            html_writer::div(get_string('categories:type_' . $record->itemtype, 'local_oriskill') . ' : ' .
                s(ss::item_name($record)) . ' · ' . get_string('share:uses', 'local_oriskill', $record->uses), 'small text-muted')) .
        html_writer::div($post('toggle', html_writer::tag('button', get_string($record->enabled ? 'engage:turnoff' : 'engage:turnon',
            'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']), ['id' => $record->id]) . ' ' .
            $post('delete', html_writer::tag('button', get_string('delete'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']),
                ['id' => $record->id]), 'text-nowrap'),
        'd-flex justify-content-between align-items-start gap-3 py-2 border-bottom');
}
echo html_writer::div(html_writer::tag('h3', get_string('share:mine', 'local_oriskill'), ['class' => 'h5']) . $list .
    ($options ? $post('create', html_writer::div(html_writer::select($options, 'item', '', false, ['class' => 'form-select',
        'aria-label' => get_string('share:what', 'local_oriskill'), 'style' => 'max-width: 28rem']) .
        html_writer::tag('button', get_string('share:create', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-outline-primary']),
        'd-flex gap-2 mt-3'), [], '') : ''),
    'oriskill-card');

echo creator_service::space_end();
echo $OUTPUT->footer();
