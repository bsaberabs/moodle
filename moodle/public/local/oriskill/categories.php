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
 * Categories of the trainer, common to courses, order forms and capture pages.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\category_service as cs;
use local_oriskill\creator_service;

$url = new moodle_url('/local/oriskill/categories.php');
$creator = creator_service::require_current_creator($url, 'menu:categories');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    try {
        if ($action === 'save') {
            $category = cs::save($creator, required_param('name', PARAM_TEXT), optional_param('id', 0, PARAM_INT));
            cs::set_items($creator, (int) $category->id, ['course' => optional_param_array('course', [], PARAM_INT),
                'orderform' => optional_param_array('orderform', [], PARAM_INT),
                'capturepage' => optional_param_array('capturepage', [], PARAM_INT)]);
            \local_oriskill\log_service::add($creator, 'category_saved', ['name' => $category->name]);
        } else if ($action === 'delete') {
            cs::delete($creator, required_param('id', PARAM_INT));
        }
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$things = [
    'course' => array_map(fn($c) => format_string($c->fullname), $DB->get_records('course', ['category' => $creator->categoryid],
        'fullname', 'id, fullname')),
    'orderform' => array_map(fn($f) => format_string($f->title), $DB->get_records('local_oriskill_orderforms',
        ['creatorid' => $creator->id], 'title', 'id, title')),
    'capturepage' => array_map(fn($p) => format_string($p->title), $DB->get_records('local_oriskill_capturepages',
        ['creatorid' => $creator->id], 'title', 'id, title')),
];
$categories = cs::categories($creator);
$editing = $categories[optional_param('edit', 0, PARAM_INT)] ?? null;
$current = $editing ? cs::items((int) $editing->id) : array_fill_keys(array_keys($things), []);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/categories.php', get_string('menu:categories', 'local_oriskill'));
echo html_writer::tag('p', get_string('categories:intro', 'local_oriskill'), ['class' => 'text-muted']);

foreach ($categories as $category) {
    $items = cs::items((int) $category->id);
    $names = [];
    foreach ($items as $type => $ids) {
        foreach ($ids as $id) {
            if (isset($things[$type][$id])) {
                $names[] = $things[$type][$id];
            }
        }
    }
    echo html_writer::div(
        html_writer::div(html_writer::tag('strong', format_string($category->name)) .
            html_writer::div(s(implode(', ', $names)) ?: get_string('categories:emptycat', 'local_oriskill'), 'small text-muted')) .
        html_writer::div(html_writer::link(new moodle_url($url, ['edit' => $category->id], 'catform'), get_string('edit'),
            ['class' => 'btn btn-sm btn-outline-primary']) . ' ' .
            html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(),
                'action' => 'delete', 'id' => $category->id])) . html_writer::tag('button', get_string('delete'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']), ['method' => 'post',
                'action' => $url->out(false), 'class' => 'd-inline',
                'onsubmit' => 'return confirm(' . json_encode(get_string('categories:deleteconfirm', 'local_oriskill')) . ')']),
            'text-nowrap'),
        'oriskill-card d-flex justify-content-between align-items-start gap-3 mb-2');
}

$boxes = '';
foreach ($things as $type => $list) {
    if (!$list) {
        continue;
    }
    $checks = '';
    foreach ($list as $id => $name) {
        $checks .= html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => $type . '[]', 'value' => $id,
            'id' => "$type-$id", 'class' => 'form-check-input'] + (in_array($id, $current[$type], true) ? ['checked' => 'checked'] : [])) .
            html_writer::label(s($name), "$type-$id", true, ['class' => 'form-check-label']), 'form-check');
    }
    $boxes .= html_writer::div(html_writer::tag('h4', get_string('categories:type_' . $type, 'local_oriskill'), ['class' => 'h6']) .
        $checks, 'col-md-4');
}
echo html_writer::div(
    html_writer::tag('h3', get_string($editing ? 'categories:edit' : 'categories:new', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => 'save',
        'id' => $editing->id ?? 0])) .
        html_writer::div(html_writer::label(get_string('name'), 'cat-name', true, ['class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'id' => 'cat-name', 'required' => 'required',
                'maxlength' => 100, 'class' => 'form-control', 'value' => $editing->name ?? '']), 'mb-3', ['style' => 'max-width: 28rem']) .
        html_writer::div($boxes, 'row g-3 mb-3') .
        html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']),
        ['method' => 'post', 'action' => $url->out(false)]),
    'oriskill-card mt-4', ['id' => 'catform']);

echo creator_service::space_end();
echo $OUTPUT->footer();
