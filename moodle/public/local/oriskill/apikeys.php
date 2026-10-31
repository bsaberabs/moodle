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
 * API keys of the trainer: one per application, limited rights, shown once; documentation and log of the calls.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\api_service as api;
use local_oriskill\creator_service;

$url = new moodle_url('/local/oriskill/apikeys.php');
$creator = creator_service::require_current_creator($url, 'menu:api');
$newkey = '';

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    try {
        if ($action === 'create') {
            [, $newkey] = api::create($creator, required_param('name', PARAM_TEXT), optional_param_array('scopes', [], PARAM_RAW_TRIMMED));
        } else if ($action === 'revoke') {
            api::revoke($creator, required_param('id', PARAM_INT));
            redirect($url, get_string('api:revoked', 'local_oriskill'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$base = (new moodle_url('/local/oriskill/api.php'))->out(false);
echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/apikeys.php', get_string('menu:api', 'local_oriskill'));
echo html_writer::tag('p', get_string('api:intro', 'local_oriskill', api::PER_HOUR), ['class' => 'text-muted']);

// A key just made: shown this once.
if ($newkey !== '') {
    echo html_writer::div(html_writer::tag('strong', get_string('api:newkey', 'local_oriskill')) .
        html_writer::div(html_writer::tag('code', s($newkey), ['class' => 'fs-6 user-select-all']), 'my-2') .
        html_writer::tag('button', get_string('library:copylink', 'local_oriskill'), ['type' => 'button',
            'class' => 'btn btn-sm btn-outline-primary', 'onclick' => 'navigator.clipboard.writeText(' . json_encode($newkey) . ')']),
        'alert alert-warning');
}

// Keys.
$list = '';
foreach (api::keys($creator) as $key) {
    $list .= html_writer::div(
        html_writer::div(html_writer::tag('strong', s($key->name)) . ' ' . html_writer::tag('code', '…' . s($key->lastfour)) .
            html_writer::div(s(implode(', ', array_map(fn($s) => get_string('api:scope_' . str_replace(':', '_', $s), 'local_oriskill'),
                explode(',', $key->scopes)))), 'small text-muted') .
            html_writer::div(get_string('api:usage', 'local_oriskill', (object) ['calls' => $key->calls,
                'last' => $key->lastused ? userdate($key->lastused, get_string('strftimedatetimeshort', 'langconfig'))
                    : get_string('never')]), 'small text-muted')) .
        html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => 'revoke',
            'id' => $key->id])) . html_writer::tag('button', get_string('api:revoke', 'local_oriskill'), ['type' => 'submit',
            'class' => 'btn btn-sm btn-outline-danger']), ['method' => 'post', 'action' => $url->out(false),
            'onsubmit' => 'return confirm(' . json_encode(get_string('api:revokeconfirm', 'local_oriskill')) . ')']),
        'd-flex justify-content-between align-items-start gap-3 py-2 border-bottom');
}
$scopes = '';
foreach (api::SCOPES as $scope) {
    $id = 'scope-' . str_replace(':', '-', $scope);
    $scopes .= html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'scopes[]', 'value' => $scope,
        'id' => $id, 'class' => 'form-check-input']) . html_writer::label(get_string('api:scope_' . str_replace(':', '_', $scope),
        'local_oriskill'), $id, true, ['class' => 'form-check-label']), 'form-check');
}
echo html_writer::div(html_writer::tag('h3', get_string('api:keys', 'local_oriskill'), ['class' => 'h5']) . $list .
    html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => 'create'])) .
        html_writer::tag('h4', get_string('api:new', 'local_oriskill'), ['class' => 'h6 mt-3']) .
        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'required' => 'required', 'maxlength' => 100,
            'class' => 'form-control mb-2', 'style' => 'max-width: 24rem', 'placeholder' => get_string('api:name', 'local_oriskill'),
            'aria-label' => get_string('api:name', 'local_oriskill')]) .
        $scopes . html_writer::tag('button', get_string('api:create', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary mt-2']),
        ['method' => 'post', 'action' => $url->out(false)]),
    'oriskill-card mb-4');

// Documentation.
$doc = '';
foreach (api::ENDPOINTS as $endpoint => $scope) {
    $doc .= html_writer::tag('tr', html_writer::tag('td', html_writer::tag('code', s($endpoint))) .
        html_writer::tag('td', get_string('api:doc_' . preg_replace('/[^a-z]+/', '_', strtolower($endpoint)), 'local_oriskill')) .
        html_writer::tag('td', get_string('api:scope_' . str_replace(':', '_', $scope), 'local_oriskill'), ['class' => 'small']));
}
echo html_writer::div(html_writer::tag('h3', get_string('api:doc', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('p', get_string('api:doc_intro', 'local_oriskill'), ['class' => 'small text-muted']) .
    html_writer::tag('pre', s("curl -H \"Authorization: Bearer osk_...\" \"$base/contacts?q=martin&page=0&perpage=50\"\n" .
        "curl -X POST -H \"Authorization: Bearer osk_...\" -H \"Content-Type: application/json\" \\\n" .
        "     -d '{\"email\":\"lea@example.com\",\"firstname\":\"Léa\",\"tags\":[\"webinaire\"]}' \"$base/contacts\""),
        ['class' => 'small bg-light p-3 rounded']) .
    html_writer::tag('table', $doc, ['class' => 'table table-sm']), 'oriskill-card mb-4');

// Log of the calls.
$calls = api::calls($creator);
$rows = '';
foreach ($calls as $call) {
    $rows .= html_writer::tag('tr', html_writer::tag('td', userdate($call->timecreated, get_string('strftimedatetimeshort', 'langconfig'))) .
        html_writer::tag('td', s($call->name)) . html_writer::tag('td', html_writer::tag('code', s($call->method . ' ' . $call->endpoint))) .
        html_writer::tag('td', html_writer::span($call->status, 'oriskill-badge ' . ($call->status < 300 ? 'is-good' : 'is-bad'))) .
        html_writer::tag('td', s($call->ip), ['class' => 'small text-muted']));
}
echo html_writer::div(html_writer::tag('h3', get_string('api:log', 'local_oriskill', api::LOG_DAYS), ['class' => 'h5']) .
    ($rows ? html_writer::tag('table', $rows, ['class' => 'table table-sm']) : html_writer::div(get_string('api:nocall', 'local_oriskill'),
        'small text-muted')), 'oriskill-card table-responsive');

echo creator_service::space_end();
echo $OUTPUT->footer();
