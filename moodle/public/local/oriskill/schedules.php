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
 * Trainer's payment schedules: every purchase in instalments, what was collected, what is left, unpaid ones.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\creator_service;
use local_oriskill\instalment_service;

const LOCAL_ORISKILL_SCHEDULE_STATUSES = ['active', 'late', 'suspended', 'stopped', 'completed', 'cancelled'];

$status = optional_param('status', '', PARAM_ALPHA);
$status = in_array($status, LOCAL_ORISKILL_SCHEDULE_STATUSES, true) ? $status : '';
$url = new moodle_url('/local/oriskill/schedules.php', $status ? ['status' => $status] : []);
$creator = creator_service::require_current_creator($url, 'menu:schedules');

if (($cancel = optional_param('cancel', 0, PARAM_INT)) && confirm_sesskey()) {
    $record = null;
    foreach (instalment_service::trainer_records($creator) as $r) {
        if ((int) $r->id === $cancel && !$r->count) {
            $record = $r;
        }
    }
    if (!$record) {
        throw new moodle_exception('invalidrecord', 'error');
    }
    $confirmed = instalment_service::cancel_subscription($record);
    redirect($url, get_string($confirmed ? 'subscription:cancelledtrainer' : 'subscription:cancelledmanual', 'local_oriskill',
        userdate(instalment_service::paid_until($record), get_string('strftimedate', 'langconfig'))), null,
        \core\output\notification::NOTIFY_SUCCESS);
}
$all = instalment_service::trainer_records($creator);
$records = $status === '' ? $all : array_filter($all, fn($r) => $r->status === $status);
$dateformat = get_string('strftimedate', 'langconfig');

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/schedules.php', get_string('menu:schedules', 'local_oriskill'));
echo html_writer::tag('p', get_string('schedules:intro', 'local_oriskill', instalment_service::GRACE_DAYS),
    ['class' => 'text-muted']);

// Key figures, over all schedules.
$currency = pricing_service::get_currency_config()->currency;
$running = array_filter($all, fn($r) => in_array($r->status, ['active', 'late', 'suspended'], true));
$unpaid = array_filter($all, fn($r) => in_array($r->status, ['late', 'suspended', 'stopped'], true));
$stats = '';
foreach ([
    ['fa-calendar-check', count($running), get_string('schedules:running', 'local_oriskill')],
    ['fa-triangle-exclamation', count($unpaid), get_string('schedules:unpaid', 'local_oriskill')],
    ['fa-euro-sign', pricing_service::format_price(array_sum(array_column($all, 'collected')), $currency),
        get_string('schedules:collected', 'local_oriskill')],
    ['fa-hourglass-half', pricing_service::format_price(array_sum(array_column($running, 'remaining')), $currency),
        get_string('schedules:tocollect', 'local_oriskill')],
] as [$icon, $value, $label]) {
    $stats .= html_writer::div(
        html_writer::span('<i class="fa ' . $icon . '" aria-hidden="true"></i>', 'oriskill-stat-icon') .
        html_writer::div(html_writer::div(s($value), 'oriskill-stat-value') . html_writer::div($label, 'oriskill-stat-label')),
        'oriskill-card oriskill-stat');
}
echo html_writer::div($stats, 'oriskill-stats');

// Filter by status.
$filters = html_writer::link(new moodle_url('/local/oriskill/schedules.php'),
    get_string('schedules:all', 'local_oriskill') . ' (' . count($all) . ')',
    ['class' => 'btn btn-sm ' . ($status === '' ? 'btn-primary' : 'btn-outline-secondary')]);
foreach (LOCAL_ORISKILL_SCHEDULE_STATUSES as $key) {
    $count = count(array_filter($all, fn($r) => $r->status === $key));
    $filters .= ' ' . html_writer::link(new moodle_url('/local/oriskill/schedules.php', ['status' => $key]),
        get_string('schedules:status_' . $key, 'local_oriskill') . " ($count)",
        ['class' => 'btn btn-sm ' . ($status === $key ? 'btn-primary' : 'btn-outline-secondary')]);
}
echo html_writer::div($filters, 'd-flex flex-wrap gap-2 mb-3');

if (!$records) {
    echo html_writer::div('<i class="fa fa-calendar" aria-hidden="true"></i>' .
        get_string('schedules:none', 'local_oriskill'), 'oriskill-card oriskill-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle oriskill-schedules';
    $table->head = [get_string('learner', 'local_oriskill'), get_string('schedules:what', 'local_oriskill'),
        get_string('schedules:progress', 'local_oriskill'), get_string('schedules:amounts', 'local_oriskill'),
        get_string('schedules:next', 'local_oriskill')];
    foreach ($records as $r) {
        $percent = (int) round(100 * $r->paid / max(1, $r->count));
        $learnerurl = new moodle_url('/local/oriskill/learner.php', ['courseid' => $r->courseid, 'userid' => $r->userid]);
        $next = $r->nextdue ? userdate($r->nextdue, $dateformat) : '–';
        if ($r->latesince) {
            $next .= html_writer::div(get_string('schedules:latesince', 'local_oriskill',
                userdate($r->latesince, $dateformat)), 'small text-danger');
        }
        $table->data[] = [
            ($r->user ? html_writer::link($learnerurl, fullname($r->user)) .
                html_writer::div(s($r->user->email), 'small text-muted') : '?'),
            s($r->label) . html_writer::div(s($r->ordernumber) . ' · ' .
                userdate($r->timecreated, $dateformat), 'small text-muted'),
            ($r->count ? html_writer::div(get_string('schedules:paidof', 'local_oriskill', (object) ['paid' => $r->paid,
                'count' => $r->count, 'amount' => pricing_service::format_price((float) $r->amount, $r->currency)]) .
                ((float) $r->firstamount > 0 ? ' ' . get_string('schedules:firstamount', 'local_oriskill',
                    pricing_service::format_price((float) $r->firstamount, $r->currency)) : ''), 'small') .
                html_writer::div(html_writer::span('', '', ['style' => "width: {$percent}%"]), 'oriskill-progress')
                : html_writer::div(get_string('subscription:permonth', 'local_oriskill',
                    pricing_service::format_price((float) $r->amount, $r->currency)) . ' · ' .
                    get_string('subscription:months', 'local_oriskill', $r->paid), 'small')) .
                html_writer::div(instalment_service::status_badge($r), 'mt-1'),
            html_writer::div(get_string('schedules:collectedamount', 'local_oriskill',
                pricing_service::format_price($r->collected, $r->currency))) .
                ($r->remaining > 0 ? html_writer::div(get_string('schedules:remainingamount', 'local_oriskill',
                    pricing_service::format_price($r->remaining, $r->currency)), 'small text-muted') : ''),
            $next . (!$r->count && in_array($r->status, ['active', 'late', 'suspended'], true) ? html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cancel', 'value' => $r->id]) .
                html_writer::tag('button', get_string('subscription:cancel', 'local_oriskill'),
                    ['type' => 'submit', 'class' => 'btn btn-sm btn-link text-danger p-0']),
                ['method' => 'post', 'action' => $url->out(false), 'onsubmit' => 'return confirm(' .
                    json_encode(get_string('subscription:cancelconfirmtrainer', 'local_oriskill')) . ')']) : ''),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card');
    echo html_writer::tag('p', get_string('schedules:estimate', 'local_oriskill'), ['class' => 'small text-muted mt-2']);
}
echo creator_service::space_end();
echo $OUTPUT->footer();
