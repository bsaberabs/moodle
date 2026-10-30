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
 * Abandoned orders of an order form: prospects who did not pay (list, export) and the e-mails that remind them.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\abandon_service;
use local_oriskill\creator_service;

$id = required_param('id', PARAM_INT);
$state = optional_param('state', 'open', PARAM_ALPHA);
$state = in_array($state, abandon_service::STATES, true) ? $state : '';
$url = new moodle_url('/local/oriskill/abandons.php', ['id' => $id, 'state' => $state ?: 'all']);
$creator = creator_service::require_current_creator($url, 'abandon:title');
$form = $DB->get_record('local_oriskill_orderforms', ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);

if (optional_param('savesequence', 0, PARAM_BOOL) && confirm_sesskey()) {
    $steps = [];
    for ($i = 0; $i < abandon_service::STEPS; $i++) {
        $steps[] = ['delay' => optional_param('delay' . $i, 1, PARAM_INT),
            'subject' => optional_param('subject' . $i, '', PARAM_TEXT), 'body' => optional_param('body' . $i, '', PARAM_TEXT)];
    }
    abandon_service::save_sequence($creator, $form, optional_param('enabled', 0, PARAM_BOOL), $steps);
    redirect(new moodle_url($url, ['#' => 'sequence']), get_string('abandon:saved', 'local_oriskill'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$prospects = abandon_service::prospects($form, $state);
$dateformat = get_string('strftimedatetimeshort', 'langconfig');

// CSV export of what is shown.
if (optional_param('download', '', PARAM_ALPHA) === 'csv') {
    $rows = [[get_string('date'), get_string('lastname'), get_string('firstname'), get_string('email'),
        get_string('abandon:offer', 'local_oriskill'), get_string('abandon:amount', 'local_oriskill'),
        get_string('abandon:state', 'local_oriskill'), get_string('abandon:reminders', 'local_oriskill')]];
    foreach ($prospects as $p) {
        $rows[] = [userdate($p->timecreated, '%Y-%m-%d %H:%M'), $p->user->lastname ?? '', $p->user->firstname ?? '',
            $p->user->email ?? '', $p->offer, format_float($p->amount, 2) . ' ' . $p->currency,
            get_string('abandon:state_' . $p->state, 'local_oriskill'), (int) $p->sent];
    }
    csv_export_writer::download_array(clean_filename(get_string('abandon:title', 'local_oriskill') . '-' . $form->slug), $rows);
    exit;
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/orderforms.php',
    get_string('abandon:title', 'local_oriskill') . ' · ' . format_string($form->title));
echo html_writer::div(html_writer::link(new moodle_url('/local/oriskill/orderforms.php'),
    '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' . get_string('menu:orderforms', 'local_oriskill')), 'mb-3');
echo html_writer::tag('p', get_string('abandon:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Key figures.
$counts = abandon_service::counts($form);
$total = array_sum($counts);
$stats = '';
foreach ([
    ['fa-cart-arrow-down', $counts['open'], get_string('abandon:opencount', 'local_oriskill')],
    ['fa-check', $counts['recovered'], get_string('abandon:recoveredcount', 'local_oriskill')],
    ['fa-percent', $total ? format_float(100 * $counts['recovered'] / $total, 1, true, true) . ' %' : '–',
        get_string('abandon:rate', 'local_oriskill')],
] as [$icon, $value, $label]) {
    $stats .= html_writer::div(
        html_writer::span('<i class="fa ' . $icon . '" aria-hidden="true"></i>', 'oriskill-stat-icon') .
        html_writer::div(html_writer::div(s($value), 'oriskill-stat-value') . html_writer::div($label, 'oriskill-stat-label')),
        'oriskill-card oriskill-stat');
}
echo html_writer::div($stats, 'oriskill-stats');

// Prospects, by state.
$filters = '';
foreach (array_merge(['all'], abandon_service::STATES) as $key) {
    $active = ($state ?: 'all') === $key;
    $filters .= html_writer::link(new moodle_url('/local/oriskill/abandons.php', ['id' => $form->id, 'state' => $key]),
        get_string('abandon:state_' . $key, 'local_oriskill') . ' (' . ($key === 'all' ? $total : $counts[$key]) . ')',
        ['class' => 'btn btn-sm ' . ($active ? 'btn-primary' : 'btn-outline-secondary')]) . ' ';
}
$filters .= html_writer::link(new moodle_url($url, ['download' => 'csv']),
    '<i class="fa fa-download me-1" aria-hidden="true"></i>' . get_string('exportcsv', 'local_oriskill'),
    ['class' => 'btn btn-sm btn-outline-secondary ms-auto']);
echo html_writer::div($filters, 'd-flex flex-wrap gap-2 mb-3');

if (!$prospects) {
    echo html_writer::div('<i class="fa fa-cart-shopping" aria-hidden="true"></i>' .
        get_string('abandon:none', 'local_oriskill'), 'oriskill-card oriskill-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('learner', 'local_oriskill'), get_string('abandon:offer', 'local_oriskill'),
        get_string('abandon:state', 'local_oriskill'), get_string('abandon:reminders', 'local_oriskill')];
    foreach ($prospects as $p) {
        $badge = ['open' => 'is-warn', 'recovered' => 'is-good', 'stopped' => ''][$p->state];
        $table->data[] = [
            ($p->user ? s(fullname($p->user)) . html_writer::div(s($p->user->email), 'small text-muted') : '?') .
                html_writer::div(userdate($p->timecreated, $dateformat), 'small text-muted'),
            s($p->offer) . html_writer::div(pricing_service::format_price((float) $p->amount, $p->currency), 'small'),
            html_writer::span(get_string('abandon:state_' . $p->state, 'local_oriskill'), 'oriskill-badge ' . $badge) .
                ($p->timerecovered ? html_writer::div(userdate($p->timerecovered, $dateformat), 'small text-muted') : ''),
            (int) $p->sent ? get_string('abandon:sent', 'local_oriskill', (object) ['count' => (int) $p->sent,
                'date' => userdate($p->lastsent, $dateformat)]) : '–',
        ];
    }
    echo html_writer::div(html_writer::div(html_writer::table($table), 'table-responsive'), 'oriskill-card');
}

// The reminder sequence.
$steps = abandon_service::sequence($form);
$fields = '';
for ($i = 0; $i < abandon_service::STEPS; $i++) {
    $step = $steps[$i] ?? (object) ['delay' => [1, 24, 72][$i], 'subject' => '', 'body' => ''];
    $fields .= html_writer::tag('fieldset',
        html_writer::tag('legend', get_string('abandon:step', 'local_oriskill', $i + 1), ['class' => 'h6']) .
        html_writer::div(
            html_writer::label(get_string('abandon:delay', 'local_oriskill'), 'delay' . $i, true, ['class' => 'form-label small']) .
            html_writer::empty_tag('input', ['type' => 'number', 'min' => 1, 'max' => 720, 'name' => 'delay' . $i,
                'id' => 'delay' . $i, 'value' => (int) $step->delay, 'class' => 'form-control', 'style' => 'max-width: 8rem']),
            'mb-2') .
        html_writer::div(
            html_writer::label(get_string('abandon:subject', 'local_oriskill'), 'subject' . $i, true, ['class' => 'form-label small']) .
            html_writer::empty_tag('input', ['type' => 'text', 'maxlength' => 200, 'name' => 'subject' . $i, 'id' => 'subject' . $i,
                'value' => $step->subject, 'class' => 'form-control']), 'mb-2') .
        html_writer::div(
            html_writer::label(get_string('abandon:body', 'local_oriskill'), 'body' . $i, true, ['class' => 'form-label small']) .
            html_writer::tag('textarea', s($step->body), ['name' => 'body' . $i, 'id' => 'body' . $i, 'rows' => 6,
                'class' => 'form-control'])),
        ['class' => 'oriskill-card mb-3']);
}
echo html_writer::tag('h3', get_string('abandon:sequence', 'local_oriskill'), ['class' => 'h5 mt-4', 'id' => 'sequence']);
echo html_writer::tag('p', get_string('abandon:sequenceintro', 'local_oriskill', implode(' ', abandon_service::PLACEHOLDERS)),
    ['class' => 'text-muted small']);
echo html_writer::tag('form',
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savesequence', 'value' => 1]) .
    html_writer::div(
        html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'enabled', 'value' => 1, 'id' => 'abandonon',
            'class' => 'form-check-input'] + ($form->abandonon ? ['checked' => 'checked'] : [])) .
        html_writer::label(get_string('abandon:enabled', 'local_oriskill'), 'abandonon', true, ['class' => 'form-check-label']),
        'form-check mb-3') .
    $fields .
    html_writer::tag('p', get_string('abandon:emptystep', 'local_oriskill'), ['class' => 'small text-muted']) .
    html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']),
    ['method' => 'post', 'action' => (new moodle_url('/local/oriskill/abandons.php', ['id' => $form->id]))->out(false)]);

echo creator_service::space_end();
echo $OUTPUT->footer();
