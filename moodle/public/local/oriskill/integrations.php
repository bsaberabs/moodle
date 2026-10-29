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
 * Trainer's integrations: ActiveCampaign after payment.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\activecampaign_service;
use local_oriskill\creator_service;
use local_oriskill\form\activecampaign_form;
use local_oriskill\log_service;
use local_oriskill\webhook_service;

$url = new moodle_url('/local/oriskill/integrations.php');
$creator = creator_service::require_current_creator($url, 'menu:integrations');
$account = activecampaign_service::account($creator);

// Webhooks: add, test, remove.
if (($action = optional_param('hookaction', '', PARAM_ALPHA)) && confirm_sesskey()) {
    $message = get_string('changessaved');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    try {
        if ($action === 'add') {
            $hook = webhook_service::add($creator, required_param('hookurl', PARAM_RAW_TRIMMED),
                optional_param_array('hookevents', [], PARAM_RAW_TRIMMED));
            log_service::add($creator, 'webhook_added', ['url' => $hook->url]);
        } else {
            $hook = $DB->get_record(webhook_service::TABLE, ['id' => required_param('hookid', PARAM_INT),
                'creatorid' => $creator->id], '*', MUST_EXIST);
            if ($action === 'remove') {
                webhook_service::remove($creator, (int) $hook->id);
                log_service::add($creator, 'webhook_removed', ['url' => $hook->url]);
            } else if ($action === 'test') {
                webhook_service::deliver($hook, 'test', json_encode(['event' => 'test', 'time' => time(),
                    'data' => ['trainer' => ['id' => (int) $creator->id, 'name' => format_string($creator->displayname)]]]));
                $message = get_string('webhook:testok', 'local_oriskill');
            }
        }
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect(new moodle_url($url, [], 'webhooks'), $message, null, $type);
}

// Lists loaded by "Test", kept for the session so the list can be picked by name.
$listskey = 'local_oriskill_aclists_' . $creator->id;
$lists = $SESSION->$listskey ?? [];
$form = new activecampaign_form($url, ['haskey' => !empty($account->apikey), 'lists' => $lists]);
$form->set_data([
    'enabled' => $account->enabled ?? 0,
    'apiurl' => $account->apiurl ?? '',
    'listid' => $account->listid ?? 0,
    'tags' => $account->tags ?? '',
    'coursetags' => $account->coursetags ?? 1,
    'unpaidtag' => $account->unpaidtag ?? '',
]);

if ($data = $form->get_data()) {
    activecampaign_service::save($creator, $data);
    log_service::add($creator, 'activecampaign_saved', ['state' => get_string(!empty($data->enabled) ? 'yes' : 'no')]);
    if (!empty($data->testbutton)) {
        try {
            $SESSION->$listskey = activecampaign_service::lists(activecampaign_service::account($creator));
            redirect($url, get_string('ac:testok', 'local_oriskill', count($SESSION->$listskey)), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $e) {
            redirect($url, get_string('ac:testfailed', 'local_oriskill', $e->getMessage()), null,
                \core\output\notification::NOTIFY_ERROR);
        }
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/integrations.php', get_string('menu:integrations', 'local_oriskill'));
echo html_writer::start_div('oriskill-card');
echo html_writer::tag('h3', 'ActiveCampaign', ['class' => 'h5']);
echo html_writer::tag('p', get_string('ac:intro', 'local_oriskill'), ['class' => 'text-muted']);
if (!empty($account->lastresult)) {
    echo html_writer::div(s($account->lastresult), 'small mb-3 ' .
        (str_starts_with($account->lastresult, '✓') ? 'text-success' : 'text-danger'));
}
$form->display();
echo html_writer::end_div();

// Webhooks.
$hidden = fn(string $action, int $id = 0) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'hookaction', 'value' => $action]) .
    ($id ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'hookid', 'value' => $id]) : '');
echo html_writer::start_div('oriskill-card mt-4', ['id' => 'webhooks']);
echo html_writer::tag('h3', get_string('webhook:title', 'local_oriskill'), ['class' => 'h5']);
echo html_writer::tag('p', get_string('webhook:intro', 'local_oriskill'), ['class' => 'text-muted']);
foreach (webhook_service::hooks($creator) as $hook) {
    $events = implode(', ', array_map(fn($e) => get_string('webhook:event_' . str_replace('.', '_', $e), 'local_oriskill'),
        explode(',', $hook->events)));
    echo html_writer::div(
        html_writer::div(html_writer::tag('code', s($hook->url)), 'fw-semibold text-break') .
        html_writer::div($events, 'small text-muted') .
        html_writer::tag('details', html_writer::tag('summary', get_string('webhook:showsecret', 'local_oriskill'),
            ['class' => 'small']) . html_writer::tag('code', s(webhook_service::secret($hook))), ['class' => 'my-1']) .
        ($hook->lastresult ? html_writer::div(s($hook->lastresult), 'small ' .
            (str_starts_with($hook->lastresult, '✓') ? 'text-success' : 'text-danger')) : '') .
        html_writer::div(
            html_writer::tag('form', $hidden('test', (int) $hook->id) . html_writer::tag('button',
                get_string('webhook:test', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']),
                ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']) . ' ' .
            html_writer::tag('form', $hidden('remove', (int) $hook->id) . html_writer::tag('button', get_string('delete'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']),
                ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline',
                 'onsubmit' => 'return confirm(' . json_encode(get_string('webhook:removeconfirm', 'local_oriskill')) . ')']),
            'mt-2'),
        'border rounded p-3 mb-3');
}
$checks = '';
foreach (webhook_service::EVENTS as $event) {
    $id = 'hook-' . str_replace('.', '-', $event);
    $checks .= html_writer::div(
        html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'hookevents[]', 'value' => $event, 'id' => $id,
            'class' => 'form-check-input', 'checked' => 'checked']) .
        html_writer::tag('label', get_string('webhook:event_' . str_replace('.', '_', $event), 'local_oriskill'),
            ['for' => $id, 'class' => 'form-check-label']), 'form-check form-check-inline');
}
echo html_writer::tag('form', $hidden('add') .
    html_writer::tag('label', get_string('webhook:url', 'local_oriskill'), ['for' => 'hookurl', 'class' => 'form-label']) .
    html_writer::empty_tag('input', ['type' => 'url', 'name' => 'hookurl', 'id' => 'hookurl', 'required' => 'required',
        'class' => 'form-control mb-2', 'placeholder' => 'https://hooks.zapier.com/...']) .
    html_writer::div($checks, 'mb-2') .
    html_writer::tag('button', get_string('webhook:add', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::end_div();
echo creator_service::space_end();
echo $OUTPUT->footer();
