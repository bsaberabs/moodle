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
 * History of one webhook's deliveries: date, event, answer, tries; send one again.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\webhook_service;

$id = required_param('id', PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$url = new moodle_url('/local/oriskill/webhooks.php', ['id' => $id] + ($status !== '' ? ['status' => $status] : []));
$creator = creator_service::require_current_creator($url, 'menu:integrations');
$hook = $DB->get_record(webhook_service::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
$statuses = ['ok', 'retrying', 'failed', 'pending'];
if ($status !== '' && !in_array($status, $statuses, true)) {
    $status = '';
}

if (optional_param('action', '', PARAM_ALPHA) === 'resend' && confirm_sesskey()) {
    $sentid = required_param('sentid', PARAM_INT);
    webhook_service::resend($creator, $sentid);
    \local_oriskill\log_service::add($creator, 'webhook_resent', ['url' => $hook->url,
        'event' => $DB->get_field(webhook_service::SENT, 'event', ['id' => $sentid])]);
    redirect($url, get_string('webhook:resent', 'local_oriskill'), null, \core\output\notification::NOTIFY_SUCCESS);
}

[$deliveries, $total] = webhook_service::history($hook, $status, $page);
$badges = ['ok' => 'is-good', 'retrying' => 'is-warn', 'failed' => 'is-bad', 'pending' => ''];

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/integrations.php', get_string('webhook:history', 'local_oriskill'));
echo html_writer::div(html_writer::link(new moodle_url('/local/oriskill/integrations.php', [], 'webhooks'),
    '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' . get_string('webhook:back', 'local_oriskill')), 'mb-3');
echo html_writer::div(html_writer::tag('code', s($hook->url)) .
    ($hook->enabled ? '' : ' ' . html_writer::span(get_string('webhook:off', 'local_oriskill'), 'oriskill-badge is-warn')),
    'mb-2 text-break');
echo html_writer::tag('p', get_string('webhook:history_intro', 'local_oriskill', (object) [
    'days' => webhook_service::KEEP_DAYS, 'tries' => webhook_service::MAX_ATTEMPTS]), ['class' => 'text-muted small']);

// Status filter.
$filters = '';
foreach (array_merge([''], $statuses) as $value) {
    $filters .= html_writer::link(new moodle_url('/local/oriskill/webhooks.php', ['id' => $id] + ($value !== '' ? ['status' => $value] : [])),
        get_string($value === '' ? 'all' : 'webhook:status_' . $value, $value === '' ? 'moodle' : 'local_oriskill'),
        ['class' => 'btn btn-sm ' . ($value === $status ? 'btn-primary' : 'btn-outline-secondary')]);
}
echo html_writer::div($filters, 'd-flex flex-wrap gap-1 mb-3');

if (!$deliveries) {
    echo html_writer::div(get_string('webhook:history_empty', 'local_oriskill'), 'oriskill-card text-muted');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('date'), get_string('webhook:event', 'local_oriskill'),
        get_string('status'), get_string('webhook:answer', 'local_oriskill'), ''];
    $format = get_string('strftimedatetimeshort', 'langconfig');
    foreach ($deliveries as $sent) {
        $event = get_string_manager()->string_exists('webhook:event_' . str_replace('.', '_', $sent->event), 'local_oriskill')
            ? get_string('webhook:event_' . str_replace('.', '_', $sent->event), 'local_oriskill') : $sent->event;
        $answer = $sent->httpcode === null ? '–' : ($sent->httpcode ? 'HTTP ' . $sent->httpcode
            : get_string('webhook:unreachable', 'local_oriskill'));
        $table->data[] = [
            userdate($sent->timecreated, $format) .
                ($sent->attempts > 1 || $sent->timemodified > $sent->timecreated + 60 ? html_writer::div(
                    get_string('webhook:lasttry', 'local_oriskill', userdate($sent->timemodified, $format)), 'small text-muted') : ''),
            s($event) . html_writer::div(html_writer::tag('code', s($sent->event)), 'small'),
            html_writer::span(get_string('webhook:status_' . $sent->status, 'local_oriskill'),
                'oriskill-badge ' . $badges[$sent->status]) .
                html_writer::div(get_string('webhook:tries', 'local_oriskill', (int) $sent->attempts), 'small text-muted'),
            html_writer::div(s($answer), 'fw-semibold') .
                ($sent->answer !== null && $sent->answer !== '' ? html_writer::div(s($sent->answer), 'small text-muted text-break',
                    ['style' => 'white-space: normal; max-width: 22rem']) : ''),
            html_writer::tag('details', html_writer::tag('summary', get_string('webhook:content', 'local_oriskill'),
                ['class' => 'small']) . html_writer::tag('pre', s(json_encode(json_decode($sent->body),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                ['class' => 'small mt-1', 'style' => 'max-width: 28rem; max-height: 20rem; overflow: auto; white-space: pre-wrap'])) .
            ($sent->event === 'test' ? '' : html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'resend']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sentid', 'value' => $sent->id]) .
                html_writer::tag('button', get_string('webhook:resend', 'local_oriskill'),
                    ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary mt-1']),
                ['method' => 'post', 'action' => $url->out(false)])),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card');
    echo $OUTPUT->paging_bar($total, $page, 50, $url);
}
echo creator_service::space_end();
echo $OUTPUT->footer();
