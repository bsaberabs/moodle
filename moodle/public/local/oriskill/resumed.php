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
 * Payment schedules started on another platform and taken over: import them, attach or ignore the charges SecureAndPay
 * notified that no schedule explains, and see the schedules taken over.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\instalment_service;
use local_oriskill\resume_service as rs;
use local_oriskill\seller_service;
use local_oriskill\team_service;

$url = new moodle_url('/local/oriskill/resumed.php');
$creator = creator_service::require_current_creator($url, 'resume:title');
$canchange = team_service::can_change(creator_service::current_role(), '/local/oriskill/resumed.php');
$result = null;

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    if (!$canchange) {
        throw new moodle_exception('error:notallowed', 'local_oriskill');
    }
    try {
        if ($action === 'import') {
            if (empty($_FILES['csv']['tmp_name']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
                throw new moodle_exception('import:nofile', 'local_oriskill');
            }
            // Shown on this page, with the rows in error: importing the same file again creates nothing twice.
            $result = rs::import($creator, $_FILES['csv']['tmp_name'], optional_param('courseid', 0, PARAM_INT),
                optional_param('cut', 1, PARAM_INT), (bool) optional_param('enrol', 0, PARAM_BOOL));
        } else if ($action === 'attach') {
            $applied = rs::attach($creator, required_param('id', PARAM_INT), required_param('schedule', PARAM_INT));
            redirect($url, get_string('resume:attached', 'local_oriskill', $applied), null,
                \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'ignore') {
            rs::ignore($creator, required_param('id', PARAM_INT));
            redirect($url, get_string('resume:ignored', 'local_oriskill'), null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
$hidden = function(string $action, array $fields = []): string {
    $inputs = '';
    foreach (['sesskey' => sesskey(), 'action' => $action] + $fields as $name => $value) {
        $inputs .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    return $inputs;
};
$button = fn(string $label, string $class) => html_writer::tag('button', $label,
    ['type' => 'submit', 'class' => 'btn btn-sm ' . $class]);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/schedules.php', get_string('resume:title', 'local_oriskill'));
echo html_writer::link(new moodle_url('/local/oriskill/schedules.php'), '← ' . get_string('menu:schedules', 'local_oriskill'),
    ['class' => 'd-inline-block mb-3']);
echo html_writer::tag('p', get_string('resume:intro', 'local_oriskill'), ['class' => 'text-muted']);

// What the import just did.
if ($result) {
    echo $OUTPUT->notification(get_string('resume:result', 'local_oriskill', $result), $result->failed
        ? \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS);
    $errors = '';
    foreach ($result->errors as $error) {
        $errors .= html_writer::tag('li', get_string('import:row', 'local_oriskill') . ' ' . (int) $error['row'] . ' · ' .
            s($error['email']) . ' : ' . s($error['error']));
    }
    if ($errors !== '') {
        echo html_writer::div(html_writer::tag('ul', $errors, ['class' => 'small mb-0']), 'oriskill-card mb-3');
    }
}

// Charges notified by SecureAndPay that no schedule explains.
if ($held = rs::held($creator)) {
    $options = rs::attachable($creator);
    $statuses = ['00' => 'paid', '02' => 'paid', '05' => 'refused', '06' => 'stopped', '03' => 'refunded', '04' => 'refunded'];
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('date'), get_string('learner', 'local_oriskill'), get_string('unpaid:product', 'local_oriskill'),
        get_string('unpaid:amount', 'local_oriskill'), get_string('unpaid:state', 'local_oriskill'), get_string('actions')];
    foreach ($held as $h) {
        $status = $statuses[$h->transstatus] ?? 'other';
        $actions = '';
        if ($canchange && $options) {
            $actions .= html_writer::tag('form', $hidden('attach', ['id' => $h->id]) .
                html_writer::select($options, 'schedule', '', ['' => get_string('resume:choose', 'local_oriskill')],
                    ['class' => 'form-select form-select-sm', 'required' => 'required', 'style' => 'max-width: 22rem',
                        'aria-label' => get_string('resume:attachto', 'local_oriskill')]) .
                $button(get_string('resume:attach', 'local_oriskill'), 'btn-primary'),
                ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-flex gap-1 mb-1']);
        }
        if ($canchange) {
            $actions .= html_writer::tag('form', $hidden('ignore', ['id' => $h->id]) .
                $button(get_string('resume:ignore', 'local_oriskill'), 'btn-link text-danger p-0'),
                ['method' => 'post', 'action' => $url->out(false), 'onsubmit' => 'return confirm(' .
                    json_encode(get_string('resume:ignoreconfirm', 'local_oriskill')) . ')']);
        }
        $table->data[] = [
            userdate($h->timecreated, $dateformat),
            s($h->buyer) . html_writer::div(s($h->email), 'small text-muted'),
            s($h->label) . html_writer::div(s($h->reference) . ($h->number ? ' · ' .
                get_string('resume:number', 'local_oriskill', $h->number) : ''), 'small text-muted'),
            format_float((float) $h->amount, 2),
            html_writer::span(get_string('resume:status_' . $status, 'local_oriskill', s($h->transstatus)),
                'oriskill-badge ' . ($status === 'paid' ? 'is-good' : 'is-warn')),
            $actions,
        ];
    }
    echo html_writer::div(html_writer::tag('h3', get_string('resume:held', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::tag('p', get_string('resume:held_intro', 'local_oriskill'), ['class' => 'small text-muted']) .
        html_writer::table($table), 'oriskill-card table-responsive mb-4');
}

// Import a file of schedules.
if ($canchange) {
    $courses = [0 => get_string('resume:nocourse', 'local_oriskill')];
    foreach (seller_service::priced_courses($creator) as $id => $priced) {
        $courses[$id] = format_string($priced->course->fullname);
    }
    $cuts = [];
    foreach (instalment_service::CUTS as $cut) {
        $cuts[$cut] = get_string('resume:cut_n', 'local_oriskill', $cut);
    }
    $field = fn(string $label, string $input) => html_writer::div(
        html_writer::tag('label', $label, ['class' => 'form-label small fw-semibold']) . $input, 'mb-3');
    echo html_writer::div(html_writer::tag('h3', get_string('resume:import', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::tag('p', get_string('resume:import_help', 'local_oriskill'), ['class' => 'small text-muted']) .
        html_writer::tag('form', $hidden('import') .
            $field(get_string('file'), html_writer::empty_tag('input', ['type' => 'file', 'name' => 'csv',
                'accept' => '.csv,text/csv,text/plain', 'required' => 'required', 'class' => 'form-control',
                'aria-label' => get_string('file')])) .
            $field(get_string('resume:course', 'local_oriskill'), html_writer::select($courses, 'courseid', 0, false,
                ['class' => 'form-select', 'aria-label' => get_string('resume:course', 'local_oriskill')])) .
            $field(get_string('resume:cut', 'local_oriskill'), html_writer::select($cuts, 'cut', 1, false,
                ['class' => 'form-select', 'aria-label' => get_string('resume:cut', 'local_oriskill')])) .
            html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'enrol', 'value' => 1,
                'id' => 'resume-enrol', 'checked' => 'checked', 'class' => 'form-check-input']) .
                html_writer::tag('label', get_string('resume:enrol', 'local_oriskill'), ['for' => 'resume-enrol',
                    'class' => 'form-check-label small']), 'form-check mb-3') .
            html_writer::tag('button', get_string('resume:start', 'local_oriskill'), ['type' => 'submit',
                'class' => 'btn btn-primary']),
            ['method' => 'post', 'action' => $url->out(false), 'enctype' => 'multipart/form-data',
                'style' => 'max-width: 40rem']), 'oriskill-card mb-4');
}

// The schedules taken over.
$schedules = rs::schedules($creator);
if (!$schedules) {
    echo html_writer::div('<i class="fa fa-calendar" aria-hidden="true"></i>' . get_string('resume:none', 'local_oriskill'),
        'oriskill-card oriskill-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('learner', 'local_oriskill'), get_string('schedules:what', 'local_oriskill'),
        get_string('schedules:progress', 'local_oriskill'), get_string('resume:reference', 'local_oriskill')];
    foreach ($schedules as $s) {
        $table->data[] = [
            ($s->user ? html_writer::link(new moodle_url('/local/oriskill/learner.php', ['courseid' => $s->courseid,
                'userid' => $s->userid]), fullname($s->user)) : '?') . html_writer::div(s($s->email), 'small text-muted'),
            format_string($s->coursename) . html_writer::div(html_writer::link(new moodle_url('/local/oriskill/sale.php',
                ['id' => $s->orderid]), s($s->ordernumber)) . ' · ' . userdate($s->timecreated,
                get_string('strftimedate', 'langconfig')), 'small text-muted'),
            instalment_service::status_badge($s) . html_writer::div($s->count . ' × ' . format_float((float) $s->amount, 2) .
                ' ' . s($s->currency) . ' · ' . get_string('resume:paidbefore', 'local_oriskill', (int) $s->paidbefore),
                'small text-muted mt-1'),
            $s->reference !== null ? s($s->reference)
                : html_writer::span(get_string('resume:waiting', 'local_oriskill'), 'small text-muted'),
        ];
    }
    echo html_writer::div(html_writer::tag('h3', get_string('resume:list', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::table($table), 'oriskill-card table-responsive');
}

echo creator_service::space_end();
echo $OUTPUT->footer();
