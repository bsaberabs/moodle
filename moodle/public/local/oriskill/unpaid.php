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
 * Trainer's unpaid instalments: suspend or restore the access, send a payment link, settle or abandon with a reason.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\creator_service;
use local_oriskill\seller_service;
use local_oriskill\unpaid_service;

$state = optional_param('state', 'open', PARAM_ALPHA);
$state = in_array($state, unpaid_service::STATES, true) ? $state : '';
$courseid = optional_param('courseid', 0, PARAM_INT);
$search = trim(optional_param('q', '', PARAM_TEXT));
$sort = optional_param('sort', 'desc', PARAM_ALPHA) === 'asc' ? 'asc' : 'desc';
$url = new moodle_url('/local/oriskill/unpaid.php', array_filter(['state' => $state ?: 'all', 'courseid' => $courseid,
    'q' => $search, 'sort' => $sort === 'asc' ? 'asc' : '']));
$creator = creator_service::require_current_creator($url, 'menu:unpaid');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    $case = unpaid_service::require_case($creator, required_param('id', PARAM_INT));
    if ($action === 'remind') {
        unpaid_service::remind($case);
        $message = get_string('unpaid:reminded', 'local_oriskill');
    } else if ($action === 'suspend' || $action === 'restore') {
        unpaid_service::set_access($case, $action === 'suspend');
        $message = get_string('unpaid:access_' . $action, 'local_oriskill');
    } else if ($action === 'classify') {
        unpaid_service::classify($case, required_param('outcome', PARAM_ALPHA), optional_param('reason', '', PARAM_TEXT));
        $message = get_string('unpaid:classified', 'local_oriskill');
    } else {
        throw new moodle_exception('invalidaction', 'error');
    }
    redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$filters = ['state' => $state, 'courseid' => $courseid, 'search' => $search, 'sort' => $sort];
$cases = unpaid_service::trainer_cases($creator, $filters);
$dateformat = get_string('strftimedate', 'langconfig');
$type = fn($c) => $c->count ? get_string('unpaid:type_instalments', 'local_oriskill', $c->count)
    : get_string('unpaid:type_subscription', 'local_oriskill');

// CSV export of what is shown.
if (optional_param('download', '', PARAM_ALPHA) === 'csv') {
    \local_oriskill\team_service::export_allowed($creator, get_string('menu:unpaid', 'local_oriskill'));
    $rows = [[get_string('date'), get_string('lastname'), get_string('firstname'), get_string('email'),
        get_string('unpaid:product', 'local_oriskill'), get_string('unpaid:type', 'local_oriskill'),
        get_string('unpaid:cause', 'local_oriskill'), get_string('unpaid:amount', 'local_oriskill'),
        get_string('unpaid:state', 'local_oriskill'), get_string('unpaid:closedon', 'local_oriskill'),
        get_string('unpaid:reason', 'local_oriskill'), get_string('unpaid:reminders', 'local_oriskill')]];
    foreach ($cases as $c) {
        $rows[] = [userdate($c->timecreated, '%Y-%m-%d'), $c->user->lastname ?? '', $c->user->firstname ?? '',
            $c->user->email ?? '', $c->label . ' (' . $c->ordernumber . ')', $type($c),
            get_string('unpaid:cause_' . $c->cause, 'local_oriskill'), format_float($c->amount, 2) . ' ' . $c->currency,
            get_string('unpaid:state_' . $c->state, 'local_oriskill'),
            $c->timeclosed ? userdate($c->timeclosed, '%Y-%m-%d') : '', (string) $c->reason, (int) $c->reminders];
    }
    csv_export_writer::download_array(clean_filename(get_string('menu:unpaid', 'local_oriskill') . '-' . date('Y-m-d')),
        $rows);
    exit;
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/unpaid.php', get_string('menu:unpaid', 'local_oriskill'));
echo html_writer::tag('p', get_string('unpaid:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Key figures, over all open cases.
$open = unpaid_service::trainer_cases($creator, ['state' => 'open']);
$currency = pricing_service::get_currency_config()->currency;
$stats = '';
foreach ([
    ['fa-triangle-exclamation', count($open), get_string('unpaid:opencount', 'local_oriskill')],
    ['fa-euro-sign', pricing_service::format_price(array_sum(array_column($open, 'amount')), $currency),
        get_string('unpaid:owed', 'local_oriskill')],
    ['fa-lock', count(array_filter($open, fn($c) => $c->suspended)), get_string('unpaid:suspendedcount', 'local_oriskill')],
] as [$icon, $value, $label]) {
    $stats .= html_writer::div(
        html_writer::span('<i class="fa ' . $icon . '" aria-hidden="true"></i>', 'oriskill-stat-icon') .
        html_writer::div(html_writer::div(s($value), 'oriskill-stat-value') . html_writer::div($label, 'oriskill-stat-label')),
        'oriskill-card oriskill-stat');
}
echo html_writer::div($stats, 'oriskill-stats');

// Filters: state, course, name or e-mail, order.
$courses = ['0' => get_string('unpaid:allcourses', 'local_oriskill')];
foreach (seller_service::priced_courses($creator) as $id => $priced) {
    $courses[$id] = format_string($priced->course->fullname);
}
$states = ['all' => get_string('unpaid:state_all', 'local_oriskill')];
foreach (unpaid_service::STATES as $key) {
    $states[$key] = get_string('unpaid:state_' . $key, 'local_oriskill');
}
echo html_writer::tag('form',
    html_writer::select($states, 'state', $state ?: 'all', false, ['class' => 'form-select form-select-sm w-auto',
        'aria-label' => get_string('unpaid:state', 'local_oriskill')]) .
    html_writer::select($courses, 'courseid', $courseid, false, ['class' => 'form-select form-select-sm w-auto',
        'style' => 'max-width: 16rem', 'aria-label' => get_string('course')]) .
    html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $search,
        'class' => 'form-control form-control-sm w-auto', 'placeholder' => get_string('unpaid:search', 'local_oriskill')]) .
    html_writer::select(['desc' => get_string('unpaid:newest', 'local_oriskill'),
        'asc' => get_string('unpaid:oldest', 'local_oriskill')], 'sort', $sort, false,
        ['class' => 'form-select form-select-sm w-auto', 'aria-label' => get_string('sortby')]) .
    html_writer::tag('button', get_string('filter'), ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']) .
    html_writer::link(new moodle_url($url, ['download' => 'csv']), '<i class="fa fa-download me-1" aria-hidden="true"></i>' .
        get_string('exportcsv', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary ms-auto']),
    ['method' => 'get', 'action' => (new moodle_url('/local/oriskill/unpaid.php'))->out(false),
        'class' => 'd-flex flex-wrap gap-2 align-items-center mb-3']);

if (!$cases) {
    echo html_writer::div('<i class="fa fa-circle-check" aria-hidden="true"></i>' .
        get_string('unpaid:none', 'local_oriskill'), 'oriskill-card oriskill-empty');
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}

// A small form posting one action on a case.
$post = function(int $id, string $action, string $content, array $fields = [], string $confirm = '') use ($url) {
    $inputs = '';
    foreach (['sesskey' => sesskey(), 'id' => $id, 'action' => $action] + $fields as $name => $value) {
        $inputs .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    return html_writer::tag('form', $inputs . $content, ['method' => 'post', 'action' => $url->out(false),
        'class' => 'd-inline'] + ($confirm !== '' ? ['onsubmit' => 'return confirm(' . json_encode($confirm) . ')'] : []));
};
$button = fn(string $label, string $class) => html_writer::tag('button', $label,
    ['type' => 'submit', 'class' => 'btn btn-sm ' . $class]);

$table = new html_table();
$table->attributes['class'] = 'table align-middle oriskill-unpaid';
$table->head = [get_string('learner', 'local_oriskill'), get_string('unpaid:product', 'local_oriskill'),
    get_string('unpaid:state', 'local_oriskill'), get_string('actions')];
foreach ($cases as $c) {
    $learnerurl = new moodle_url('/local/oriskill/learner.php', ['courseid' => $c->courseid, 'userid' => $c->userid]);
    $statebadge = html_writer::span(get_string('unpaid:state_' . $c->state, 'local_oriskill'), 'oriskill-badge ' .
        ['open' => 'is-warn', 'settled' => 'is-good', 'abandoned' => ''][$c->state]);
    $details = '';
    if ($c->state !== 'open') {
        $details .= html_writer::div(get_string('unpaid:closed_' . $c->how, 'local_oriskill',
            userdate($c->timeclosed, $dateformat)), 'small text-muted');
        if (trim((string) $c->reason) !== '') {
            $details .= html_writer::div(get_string('unpaid:reasonis', 'local_oriskill', s($c->reason)), 'small');
        }
    }
    if ($c->reminders) {
        $details .= html_writer::div(get_string('unpaid:reminderssent', 'local_oriskill', (object) ['count' => $c->reminders,
            'date' => userdate($c->lastreminder, $dateformat)]), 'small text-muted');
    }

    $actions = '';
    if ($c->suspended) {
        $actions .= $post($c->id, 'restore', $button(get_string('unpaid:restore', 'local_oriskill'), 'btn-outline-secondary'));
    } else if ($c->latest && !in_array($c->status, ['completed', 'cancelled'], true)) {
        $actions .= $post($c->id, 'suspend', $button(get_string('unpaid:suspend', 'local_oriskill'), 'btn-outline-secondary'),
            [], get_string('unpaid:suspendconfirm', 'local_oriskill'));
    }
    if ($c->state === 'open') {
        $actions .= $post($c->id, 'remind', $button(get_string('unpaid:remind', 'local_oriskill'), 'btn-primary'));
        // Close with a reason, kept in the history.
        $actions .= html_writer::tag('details',
            html_writer::tag('summary', get_string('unpaid:classify', 'local_oriskill'), ['class' => 'btn btn-sm btn-link p-0']) .
            $post($c->id, 'classify',
                html_writer::div(
                    html_writer::select(['settled' => get_string('unpaid:outcome_settled', 'local_oriskill'),
                        'abandoned' => get_string('unpaid:outcome_abandoned', 'local_oriskill')], 'outcome', 'settled', false,
                        ['class' => 'form-select form-select-sm', 'aria-label' => get_string('unpaid:state', 'local_oriskill')]) .
                    html_writer::empty_tag('input', ['type' => 'text', 'name' => 'reason', 'required' => 'required',
                        'maxlength' => 255, 'class' => 'form-control form-control-sm',
                        'placeholder' => get_string('unpaid:reasonplaceholder', 'local_oriskill'),
                        'aria-label' => get_string('unpaid:reason', 'local_oriskill')]) .
                    $button(get_string('unpaid:classifysave', 'local_oriskill'), 'btn-outline-primary'),
                    'd-flex flex-column gap-1 mt-1')),
            ['class' => 'w-100']);
    }

    $table->data[] = [
        ($c->user ? html_writer::link($learnerurl, fullname($c->user)) .
            html_writer::div(s($c->user->email), 'small text-muted') : '?') .
            html_writer::div(userdate($c->timecreated, $dateformat), 'small text-muted'),
        s($c->label) . html_writer::div(s($c->ordernumber) . ' · ' . $type($c) . ' · ' .
            get_string('unpaid:cause_' . $c->cause, 'local_oriskill'), 'small text-muted'),
        html_writer::div(pricing_service::format_price((float) $c->amount, $c->currency), 'fw-semibold') .
            html_writer::div($statebadge . ($c->suspended ? ' ' . html_writer::span(
            get_string('unpaid:accesssuspended', 'local_oriskill'), 'oriskill-badge is-warn') : ''), 'mt-1') . $details,
        html_writer::div($actions, 'd-flex flex-wrap gap-1 align-items-start'),
    ];
}
echo html_writer::div(html_writer::div(html_writer::table($table), 'table-responsive'), 'oriskill-card');
echo creator_service::space_end();
echo $OUTPUT->footer();
