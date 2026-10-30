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
 * Log of the trainer space: who changed what, and when.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\log_service;

$page = optional_param('page', 0, PARAM_INT);
$url = new moodle_url('/local/oriskill/log.php');
$creator = creator_service::require_current_creator($url, 'menu:log');

$day = function(string $param, bool $end) {
    $value = optional_param($param, '', PARAM_RAW_TRIMMED);
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)
        ? make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], $end ? 23 : 0, $end ? 59 : 0, $end ? 59 : 0) : 0;
};
$filters = [
    'category' => optional_param('category', '', PARAM_ALPHA),
    'userid' => optional_param('author', -1, PARAM_INT),
    'from' => $day('from', false),
    'to' => $day('to', true),
    'learner' => optional_param('learner', '', PARAM_TEXT),
];
$url->params(array_filter(['category' => $filters['category'], 'author' => $filters['userid'] >= 0 ? $filters['userid'] : null,
    'from' => $filters['from'] ? date('Y-m-d', $filters['from']) : null, 'to' => $filters['to'] ? date('Y-m-d', $filters['to']) : null,
    'learner' => $filters['learner']], fn($v) => $v !== null && $v !== ''));
[$entries, $total] = log_service::entries($creator, $page, $filters);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/log.php', get_string('menu:log', 'local_oriskill'));
echo html_writer::tag('p', get_string('log:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Filters.
$categories = ['' => get_string('log:allcategories', 'local_oriskill')];
foreach (array_merge(array_keys(log_service::CATEGORIES), ['settings']) as $category) {
    $categories[$category] = get_string('log:cat_' . $category, 'local_oriskill');
}
$field = fn(string $label, string $control) => html_writer::div(
    html_writer::tag('label', $label, ['class' => 'form-label small mb-1']) . $control);
$date = fn(string $name) => html_writer::empty_tag('input', ['type' => 'date', 'name' => $name,
    'value' => $filters[$name] ? date('Y-m-d', $filters[$name]) : '', 'class' => 'form-control form-control-sm']);
$filtered = $filters['category'] !== '' || $filters['userid'] >= 0 || $filters['from'] || $filters['to'] || $filters['learner'] !== '';
echo html_writer::tag('form', html_writer::div(
    $field(get_string('log:category', 'local_oriskill'), html_writer::select($categories, 'category', $filters['category'], false,
        ['class' => 'form-select form-select-sm'])) .
    $field(get_string('log:who', 'local_oriskill'), html_writer::select(log_service::authors($creator), 'author',
        $filters['userid'], [-1 => get_string('log:everyone', 'local_oriskill')], ['class' => 'form-select form-select-sm'])) .
    $field(get_string('log:from', 'local_oriskill'), $date('from')) .
    $field(get_string('log:to', 'local_oriskill'), $date('to')) .
    $field(get_string('log:learner', 'local_oriskill'), html_writer::empty_tag('input', ['type' => 'search', 'name' => 'learner',
        'value' => $filters['learner'], 'class' => 'form-control form-control-sm',
        'placeholder' => get_string('log:learner_placeholder', 'local_oriskill')])) .
    html_writer::div(html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']) .
        ($filtered ? ' ' . html_writer::link(new moodle_url('/local/oriskill/log.php'), get_string('log:clearfilters', 'local_oriskill'),
            ['class' => 'btn btn-sm btn-link']) : ''), 'align-self-end'),
    'd-flex flex-wrap gap-2'), ['method' => 'get', 'action' => (new moodle_url('/local/oriskill/log.php'))->out(false),
    'class' => 'oriskill-card mb-3']);

if (!$entries) {
    echo html_writer::div(get_string('log:empty', 'local_oriskill'), 'oriskill-card text-muted');
} else {
    $users = [];
    // What changed, before → after.
    $changes = function(stdClass $entry) {
        $items = '';
        foreach (log_service::changes($entry) as [$label, $before, $after]) {
            $items .= html_writer::tag('li', html_writer::span(s($label) . ' : ', 'text-muted') .
                html_writer::tag('del', s($before), ['class' => 'text-muted']) . ' → ' . html_writer::tag('strong', s($after)));
        }
        return $items ? html_writer::tag('ul', $items, ['class' => 'small mb-0 mt-1 ps-3']) : '';
    };
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('date'), get_string('log:who', 'local_oriskill'), get_string('log:what', 'local_oriskill')];
    foreach ($entries as $entry) {
        if (!$entry->userid) {
            $who = html_writer::span(get_string('log:automatic', 'local_oriskill'), 'oriskill-badge');
        } else {
            $users[$entry->userid] = $users[$entry->userid] ?? core_user::get_user($entry->userid);
            $who = $users[$entry->userid] ? fullname($users[$entry->userid]) : '?';
        }
        $table->data[] = [
            userdate($entry->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            $who,
            html_writer::div(s(log_service::describe($entry)) . $changes($entry), '',
                ['style' => 'white-space: normal; min-width: 18rem']),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card');
    echo $OUTPUT->paging_bar($total, $page, log_service::PERPAGE, $url);
}
echo creator_service::space_end();
echo $OUTPUT->footer();
