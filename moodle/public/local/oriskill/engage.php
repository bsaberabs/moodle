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
 * Pop-ups and social proof of the trainer's public pages.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\engage_service as es;
use local_oriskill\log_service;

$url = new moodle_url('/local/oriskill/engage.php');
$creator = creator_service::require_current_creator($url, 'menu:engage');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    try {
        if ($action === 'save') {
            $popup = es::save_popup($creator, (object) [
                'id' => optional_param('id', 0, PARAM_INT), 'name' => required_param('name', PARAM_TEXT),
                'enabled' => optional_param('enabled', 0, PARAM_BOOL), 'title' => required_param('title', PARAM_TEXT),
                'body' => optional_param('body', '', PARAM_TEXT), 'buttonlabel' => optional_param('buttonlabel', '', PARAM_TEXT),
                'buttonurl' => optional_param('buttonurl', '', PARAM_RAW_TRIMMED), 'trigger' => optional_param('trigger', '', PARAM_ALPHA),
                'triggervalue' => optional_param('triggervalue', 5, PARAM_INT),
                'pages' => optional_param_array('pages', [], PARAM_ALPHA), 'frequency' => optional_param('frequency', 7, PARAM_INT)]);
            log_service::add($creator, 'popup_saved', ['name' => $popup->name]);
        } else if ($action === 'toggle' || $action === 'delete') {
            $popup = $DB->get_record(es::POPUPS, ['id' => required_param('id', PARAM_INT), 'creatorid' => $creator->id], '*', MUST_EXIST);
            if ($action === 'toggle') {
                $DB->set_field(es::POPUPS, 'enabled', $popup->enabled ? 0 : 1, ['id' => $popup->id]);
            } else {
                $DB->delete_records(es::POPUPS, ['id' => $popup->id]);
                log_service::add($creator, 'popup_deleted', ['name' => $popup->name]);
            }
        } else if ($action === 'proof') {
            es::save_proof($creator, optional_param_array('proofpages', [], PARAM_ALPHA), optional_param('anonymous', 0, PARAM_BOOL));
            log_service::add($creator, 'proof_saved');
        }
        redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$popups = es::popups($creator);
$editing = $popups[optional_param('edit', 0, PARAM_INT)] ?? null;
$pagenames = array_combine(es::PAGES, array_map(fn($p) => get_string('engage:page_' . $p, 'local_oriskill'), es::PAGES));
$post = fn(string $action, string $inner, array $hidden = [], string $confirm = '') => html_writer::tag('form',
    html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => $action] + $hidden)) . $inner,
    ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline'] +
    ($confirm !== '' ? ['onsubmit' => 'return confirm(' . json_encode($confirm) . ')'] : []));
$checks = fn(string $name, array $checked) => implode('', array_map(fn($p) => html_writer::div(
    html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => $name . '[]', 'value' => $p, 'id' => $name . '-' . $p,
        'class' => 'form-check-input'] + (in_array($p, $checked, true) ? ['checked' => 'checked'] : [])) .
    html_writer::label($pagenames[$p], $name . '-' . $p, true, ['class' => 'form-check-label']), 'form-check form-check-inline'),
    es::PAGES));

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/engage.php', get_string('menu:engage', 'local_oriskill'));
echo html_writer::tag('p', get_string('engage:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Pop-ups.
if ($popups) {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('name'), get_string('engage:trigger', 'local_oriskill'), get_string('engage:pages', 'local_oriskill'),
        get_string('engage:views', 'local_oriskill'), get_string('engage:clicks', 'local_oriskill'), ''];
    foreach ($popups as $p) {
        $table->data[] = [
            html_writer::span(s($p->name), 'fw-semibold') . ' ' . html_writer::span(get_string($p->enabled ? 'courses:on'
                : 'courses:off', 'local_oriskill'), 'oriskill-badge ' . ($p->enabled ? 'is-good' : '')),
            get_string('engage:trigger_' . $p->opentrigger, 'local_oriskill', $p->triggervalue),
            s(implode(', ', array_map(fn($x) => $pagenames[$x] ?? $x, explode(',', $p->pages)))),
            $p->views,
            $p->clicks . ($p->views ? html_writer::span(' (' . format_float(100 * $p->clicks / $p->views, 1, true, true) . ' %)',
                'small text-muted') : ''),
            html_writer::link(new moodle_url($url, ['edit' => $p->id], 'popupform'), get_string('edit'),
                ['class' => 'btn btn-sm btn-outline-primary']) . ' ' .
            $post('toggle', html_writer::tag('button', get_string($p->enabled ? 'engage:turnoff' : 'engage:turnon', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']), ['id' => $p->id]) . ' ' .
            $post('delete', html_writer::tag('button', get_string('delete'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']),
                ['id' => $p->id], get_string('engage:deleteconfirm', 'local_oriskill')),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card table-responsive mb-3');
}
$field = fn(string $label, string $control, string $class = 'col-md-6') => html_writer::div(
    html_writer::tag('label', $label, ['class' => 'form-label small fw-semibold']) . $control, $class);
$input = fn(string $name, string $value, array $extra = []) => html_writer::empty_tag('input', ['type' => 'text', 'name' => $name,
    'value' => $value, 'class' => 'form-control', 'aria-label' => $name] + $extra);
echo html_writer::div(
    html_writer::tag('h3', get_string($editing ? 'engage:edit' : 'engage:new', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => 'save',
        'id' => $editing->id ?? 0])) .
        html_writer::div(
            $field(get_string('engage:name', 'local_oriskill'), $input('name', $editing->name ?? '', ['required' => 'required',
                'maxlength' => 100])) .
            $field(get_string('engage:title', 'local_oriskill'), $input('title', $editing->title ?? '', ['required' => 'required',
                'maxlength' => 255])) .
            $field(get_string('engage:body', 'local_oriskill'), html_writer::tag('textarea', s($editing->body ?? ''), ['name' => 'body',
                'rows' => 3, 'class' => 'form-control', 'aria-label' => 'body']), 'col-12') .
            $field(get_string('engage:buttonlabel', 'local_oriskill'), $input('buttonlabel', $editing->buttonlabel ?? '',
                ['maxlength' => 100])) .
            $field(get_string('engage:buttonurl', 'local_oriskill'), $input('buttonurl', $editing->buttonurl ?? '',
                ['placeholder' => 'https://'])) .
            $field(get_string('engage:trigger', 'local_oriskill'), html_writer::select(array_combine(es::TRIGGERS,
                array_map(fn($t) => get_string('engage:triggerchoice_' . $t, 'local_oriskill'), es::TRIGGERS)), 'trigger',
                $editing->opentrigger ?? 'delay', false, ['class' => 'form-select'])) .
            $field(get_string('engage:triggervalue', 'local_oriskill'), $input('triggervalue', (string) ($editing->triggervalue ?? 5),
                ['type' => 'number', 'min' => 0, 'max' => 100]), 'col-md-3') .
            $field(get_string('engage:frequency', 'local_oriskill'), $input('frequency', (string) ($editing->frequency ?? 7),
                ['type' => 'number', 'min' => 0, 'max' => 365]), 'col-md-3') .
            $field(get_string('engage:pages', 'local_oriskill'), $checks('pages', explode(',', $editing->pages ?? 'orderform')),
                'col-12') .
            html_writer::div(html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'enabled', 'value' => 1,
                'id' => 'popup-enabled', 'class' => 'form-check-input'] + (($editing->enabled ?? 1) ? ['checked' => 'checked'] : [])) .
                html_writer::label(get_string('engage:enabled', 'local_oriskill'), 'popup-enabled', true,
                    ['class' => 'form-check-label']), 'form-check') .
                html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-2']), 'col-12'),
            'row g-3'),
        ['method' => 'post', 'action' => $url->out(false)]),
    'oriskill-card mb-4', ['id' => 'popupform']);

// Social proof.
$proof = es::proof_settings($creator);
$latest = es::recent_purchases($creator);
echo html_writer::div(
    html_writer::tag('h3', get_string('engage:proof', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('p', get_string('engage:proof_intro', 'local_oriskill', es::PROOF_DAYS), ['class' => 'small text-muted']) .
    $post('proof', html_writer::div($checks('proofpages', $proof->pages), 'mb-2') .
        html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'anonymous', 'value' => 1, 'id' => 'proof-anon',
            'class' => 'form-check-input'] + ($proof->anonymous ? ['checked' => 'checked'] : [])) .
            html_writer::label(get_string('engage:anonymous', 'local_oriskill'), 'proof-anon', true, ['class' => 'form-check-label']),
            'form-check mb-2') .
        html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary'])) .
    html_writer::div(get_string('engage:preview', 'local_oriskill') . ' ' . ($latest
        ? s($latest[0]->who . ' ' . $latest[0]->product . ' · ' . $latest[0]->ago) : get_string('engage:nopurchase', 'local_oriskill')),
        'small text-muted mt-3'),
    'oriskill-card');

echo creator_service::space_end();
echo $OUTPUT->footer();
