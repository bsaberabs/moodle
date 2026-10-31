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

// The trainer's own sending server.
if (($smtpaction = optional_param('smtpaction', '', PARAM_ALPHA)) && confirm_sesskey()) {
    $message = get_string('changessaved');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    try {
        \local_oriskill\trainer_mailer::save($creator, (object) [
            'enabled' => optional_param('smtpenabled', 0, PARAM_BOOL), 'host' => optional_param('smtphost', '', PARAM_HOST),
            'port' => optional_param('smtpport', 587, PARAM_INT), 'secure' => optional_param('smtpsecure', 'tls', PARAM_ALPHA),
            'username' => optional_param('smtpuser', '', PARAM_RAW_TRIMMED), 'password' => optional_param('smtppass', '', PARAM_RAW),
            'fromemail' => optional_param('smtpfrom', '', PARAM_RAW_TRIMMED)]);
        log_service::add($creator, 'smtp_saved', ['state' => get_string(optional_param('smtpenabled', 0, PARAM_BOOL) ? 'yes' : 'no')]);
        if ($smtpaction === 'test') {
            $trainer = core_user::get_user($creator->userid);
            if (\local_oriskill\trainer_mailer::test($creator)) {
                $message = get_string('smtp:testok', 'local_oriskill', s($trainer->email));
            } else {
                $message = get_string('smtp:testfailed', 'local_oriskill');
                $type = \core\output\notification::NOTIFY_ERROR;
            }
        }
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect(new moodle_url($url, [], 'smtp'), $message, null, $type);
}

// Webhooks: add, test, turn on or off, remove.
if (($action = optional_param('hookaction', '', PARAM_ALPHA)) && confirm_sesskey()) {
    $message = get_string('changessaved');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    try {
        if ($action === 'add') {
            $hook = webhook_service::add($creator, required_param('hookurl', PARAM_RAW_TRIMMED),
                optional_param_array('hookevents', [], PARAM_RAW_TRIMMED), optional_param_array('hookcourses', [], PARAM_INT),
                optional_param_array('hookforms', [], PARAM_INT));
            log_service::add($creator, 'webhook_added', ['url' => $hook->url]);
        } else {
            $hook = $DB->get_record(webhook_service::TABLE, ['id' => required_param('hookid', PARAM_INT),
                'creatorid' => $creator->id], '*', MUST_EXIST);
            if ($action === 'remove') {
                webhook_service::remove($creator, (int) $hook->id);
                log_service::add($creator, 'webhook_removed', ['url' => $hook->url]);
            } else if ($action === 'toggle') {
                webhook_service::set_enabled($hook, !$hook->enabled);
                log_service::add($creator, $hook->enabled ? 'webhook_off' : 'webhook_on', ['url' => $hook->url]);
            } else if ($action === 'test') {
                // Sent now, once, and kept in the history.
                $sentid = webhook_service::record($hook, 'test', json_encode(['event' => 'test', 'time' => time(),
                    'data' => ['trainer' => ['id' => (int) $creator->id, 'name' => format_string($creator->displayname)]]]));
                webhook_service::attempt($sentid);
                $sent = $DB->get_record(webhook_service::SENT, ['id' => $sentid]);
                if ($sent->status !== 'ok') {
                    $DB->set_field(webhook_service::SENT, 'status', 'failed', ['id' => $sentid]);
                    throw new moodle_exception('error:webhookdelivery', 'local_oriskill', '',
                        $sent->httpcode ? 'HTTP ' . $sent->httpcode : get_string('webhook:unreachable', 'local_oriskill'));
                }
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

// Sending server.
$smtp = \local_oriskill\trainer_mailer::settings($creator);
$input = fn(string $name, string $label, string $value, string $type = 'text', array $extra = []) => html_writer::div(
    html_writer::tag('label', $label, ['for' => 'smtp-' . $name, 'class' => 'form-label small mb-1']) .
    html_writer::empty_tag('input', ['type' => $type, 'name' => $name, 'id' => 'smtp-' . $name, 'value' => $value,
        'class' => 'form-control form-control-sm'] + $extra), 'col-sm-6 col-lg-4');
echo html_writer::start_div('oriskill-card mt-4', ['id' => 'smtp']);
echo html_writer::tag('h3', get_string('smtp:title', 'local_oriskill'), ['class' => 'h5']);
echo html_writer::tag('p', get_string('smtp:intro', 'local_oriskill'), ['class' => 'text-muted']);
echo html_writer::tag('form',
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'smtpenabled', 'value' => 1, 'id' => 'smtp-on',
        'class' => 'form-check-input', 'checked' => !empty($smtp->enabled) ? 'checked' : null]) .
        html_writer::tag('label', get_string('smtp:enabled', 'local_oriskill'), ['for' => 'smtp-on', 'class' => 'form-check-label']),
        'form-check mb-2') .
    html_writer::div(
        $input('smtpfrom', get_string('smtp:from', 'local_oriskill'), (string) ($smtp->fromemail ?? ''), 'email',
            ['placeholder' => 'contact@votre-domaine.com']) .
        $input('smtphost', get_string('smtp:host', 'local_oriskill'), (string) ($smtp->host ?? ''), 'text',
            ['placeholder' => 'smtp-relay.brevo.com']) .
        $input('smtpport', get_string('smtp:port', 'local_oriskill'), (string) ($smtp->port ?? 587), 'number') .
        html_writer::div(html_writer::tag('label', get_string('smtp:secure', 'local_oriskill'), ['for' => 'smtp-secure',
            'class' => 'form-label small mb-1']) . html_writer::select(['tls' => 'TLS (587)', 'ssl' => 'SSL (465)',
            '' => get_string('none')], 'smtpsecure', $smtp->secure ?? 'tls', false, ['class' => 'form-select form-select-sm',
            'id' => 'smtp-secure']), 'col-sm-6 col-lg-4') .
        $input('smtpuser', get_string('smtp:user', 'local_oriskill'), (string) ($smtp->username ?? ''), 'text',
            ['autocomplete' => 'off']) .
        $input('smtppass', get_string('smtp:password', 'local_oriskill'), '', 'password', ['autocomplete' => 'new-password',
            'placeholder' => !empty($smtp->password) ? '••••••••' : '']),
        'row g-2 mb-2') .
    html_writer::div(get_string('smtp:dns', 'local_oriskill'), 'small text-muted mb-2') .
    html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'name' => 'smtpaction', 'value' => 'save',
        'class' => 'btn btn-primary btn-sm']) . ' ' .
    html_writer::tag('button', get_string('smtp:test', 'local_oriskill'), ['type' => 'submit', 'name' => 'smtpaction', 'value' => 'test',
        'class' => 'btn btn-outline-secondary btn-sm']),
    ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::end_div();

// Webhooks.
$hidden = fn(string $action, int $id = 0) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'hookaction', 'value' => $action]) .
    ($id ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'hookid', 'value' => $id]) : '');
// Last delivery of a webhook, and how many failed in the last 7 days.
$last = function(stdClass $hook) use ($DB): string {
    $sent = $DB->get_records(webhook_service::SENT, ['hookid' => $hook->id], 'timecreated DESC, id DESC', '*', 0, 1);
    if (!$sent = reset($sent)) {
        return '';
    }
    $failed = $DB->count_records_select(webhook_service::SENT, "hookid = ? AND status IN ('retrying', 'failed')
        AND timecreated > ?", [$hook->id, time() - 7 * DAYSECS]);
    return html_writer::div(get_string('webhook:last', 'local_oriskill', (object) [
        'time' => userdate($sent->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'status' => get_string('webhook:status_' . $sent->status, 'local_oriskill')]) .
        ($failed ? ' · ' . html_writer::span(get_string('webhook:failedweek', 'local_oriskill', $failed), 'text-danger') : ''),
        'small ' . ($sent->status === 'ok' ? 'text-success' : 'text-muted'));
};
// Courses and order forms a webhook can be limited to.
$hookcourses = array_map(fn($c) => format_string($c->fullname), $DB->get_records('course', ['category' => $creator->categoryid],
    'sortorder', 'id, fullname'));
$hookforms = array_map(fn($f) => format_string($f->title), $DB->get_records('local_oriskill_orderforms',
    ['creatorid' => $creator->id], 'title', 'id, title'));
$filters = function(stdClass $hook) use ($hookcourses, $hookforms): string {
    $names = fn(string $ids, array $all) => implode(', ', array_filter(array_map(fn($id) => $all[(int) $id] ?? null,
        array_filter(explode(',', $ids)))));
    $parts = array_filter([
        ($c = $names((string) $hook->courseids, $hookcourses)) !== '' ? get_string('webhook:onlycourses', 'local_oriskill', $c) : '',
        ($f = $names((string) $hook->formids, $hookforms)) !== '' ? get_string('webhook:onlyforms', 'local_oriskill', $f) : '',
    ]);
    return $parts ? html_writer::div('<i class="fa fa-filter me-1" aria-hidden="true"></i>' . s(implode(' · ', $parts)),
        'small') : '';
};
echo html_writer::start_div('oriskill-card mt-4', ['id' => 'webhooks']);
echo html_writer::tag('h3', get_string('webhook:title', 'local_oriskill'), ['class' => 'h5']);
echo html_writer::tag('p', get_string('webhook:intro', 'local_oriskill'), ['class' => 'text-muted']);
foreach (webhook_service::hooks($creator) as $hook) {
    $events = implode(', ', array_map(fn($e) => get_string('webhook:event_' . str_replace('.', '_', $e), 'local_oriskill'),
        explode(',', $hook->events)));
    echo html_writer::div(
        html_writer::div(html_writer::tag('code', s($hook->url)) . ($hook->enabled ? '' : ' ' .
            html_writer::span(get_string('webhook:off', 'local_oriskill'), 'oriskill-badge is-warn')), 'fw-semibold text-break') .
        html_writer::div($events, 'small text-muted') .
        $filters($hook) .
        html_writer::tag('details', html_writer::tag('summary', get_string('webhook:showsecret', 'local_oriskill'),
            ['class' => 'small']) . html_writer::tag('code', s(webhook_service::secret($hook))), ['class' => 'my-1']) .
        $last($hook) .
        html_writer::div(
            html_writer::link(new moodle_url('/local/oriskill/webhooks.php', ['id' => $hook->id]),
                get_string('webhook:history', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-primary']) . ' ' .
            html_writer::tag('form', $hidden('toggle', (int) $hook->id) . html_writer::tag('button',
                get_string($hook->enabled ? 'webhook:turnoff' : 'webhook:turnon', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']),
                ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']) . ' ' .
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
    html_writer::tag('details', html_writer::tag('summary', get_string('webhook:filters', 'local_oriskill'), ['class' => 'small mb-2']) .
        html_writer::div(get_string('webhook:filters_help', 'local_oriskill'), 'small text-muted mb-2') .
        ($hookcourses ? html_writer::tag('label', get_string('webhook:filtercourses', 'local_oriskill'), ['for' => 'hookcourses',
            'class' => 'form-label small']) . html_writer::select($hookcourses, 'hookcourses[]', [], false,
            ['multiple' => 'multiple', 'id' => 'hookcourses', 'class' => 'form-select mb-2', 'size' => min(6, count($hookcourses))]) : '') .
        ($hookforms ? html_writer::tag('label', get_string('webhook:filterforms', 'local_oriskill'), ['for' => 'hookforms',
            'class' => 'form-label small']) . html_writer::select($hookforms, 'hookforms[]', [], false,
            ['multiple' => 'multiple', 'id' => 'hookforms', 'class' => 'form-select mb-2', 'size' => min(6, count($hookforms))]) : ''),
        ['class' => 'mb-2']) .
    html_writer::tag('button', get_string('webhook:add', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::end_div();
echo creator_service::space_end();
echo $OUTPUT->footer();
