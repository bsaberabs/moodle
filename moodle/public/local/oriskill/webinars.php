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
 * Live webinars of the trainer: create, sign-up link, people signed up, start the room as host.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_oriskill\creator_service;
use local_oriskill\stats_service;
use local_oriskill\team_service;
use local_oriskill\webinar_service as ws;

$url = new moodle_url('/local/oriskill/webinars.php');
$creator = creator_service::require_current_creator($url, 'menu:webinars');
$timezone = stats_service::timezone($creator);
$webinar = ($id = optional_param('id', 0, PARAM_INT))
    ? $DB->get_record(ws::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST) : null;

if ($webinar && optional_param('host', 0, PARAM_BOOL) && confirm_sesskey()) {
    try {
        redirect(ws::join_url($webinar, fullname($USER), true));
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}
if ($webinar && optional_param('download', '', PARAM_ALPHA) === 'csv') {
    team_service::export_allowed($creator, get_string('menu:webinars', 'local_oriskill') . ' – ' . $webinar->title);
    $rows = [[get_string('firstname'), get_string('email'), get_string('webinar:signedup', 'local_oriskill'),
        get_string('webinar:attended', 'local_oriskill'), get_string('webinar:sessioncol', 'local_oriskill')]];
    foreach ($DB->get_records(ws::REGS, ['webinarid' => $webinar->id], 'timecreated') as $r) {
        $rows[] = [$r->firstname, $r->email, userdate($r->timecreated, '%Y-%m-%d %H:%M', $timezone, false),
            $r->joined ? get_string('yes') : get_string('no'),
            $r->sessiontime ? userdate($r->sessiontime, '%Y-%m-%d %H:%M', $timezone, false) : ''];
    }
    csv_export_writer::download_array(clean_filename('webinaire-' . $webinar->title), $rows);
    exit;
}
if (optional_param('action', '', PARAM_ALPHA) === 'save' && confirm_sesskey()) {
    $local = optional_param('start', '', PARAM_RAW_TRIMMED);
    $start = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $local)
        ? (new DateTime($local, core_date::get_user_timezone_object($timezone)))->getTimestamp() : 0;
    try {
        $saved = ws::save($creator, (object) ['id' => optional_param('id', 0, PARAM_INT), 'title' => required_param('title', PARAM_TEXT),
            'description' => optional_param('description', '', PARAM_TEXT), 'starttime' => $start,
            'duration' => optional_param('duration', 60, PARAM_INT), 'offerurl' => optional_param('offerurl', '', PARAM_RAW_TRIMMED),
            'offerlabel' => optional_param('offerlabel', '', PARAM_TEXT), 'offerafter' => optional_param('offerafter', 45, PARAM_INT),
            'replaydays' => optional_param('replaydays', 7, PARAM_INT), 'platform' => optional_param('platform', 'bbb', PARAM_ALPHA),
            'streamurl' => optional_param('streamurl', '', PARAM_RAW_TRIMMED), 'days' => optional_param_array('days', [], PARAM_INT),
            'times' => optional_param('times', '', PARAM_TEXT), 'jit' => optional_param('jit', 0, PARAM_BOOL)]);
        redirect(new moodle_url($url, ['id' => $saved->id]), get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/webinars.php', get_string('menu:webinars', 'local_oriskill'));
echo html_writer::tag('p', get_string('webinar:intro', 'local_oriskill'), ['class' => 'text-muted']);
if (!ws::available()) {
    echo $OUTPUT->notification(get_string('webinar:noserver', 'local_oriskill'), 'warning');
}

// The webinars.
$list = '';
foreach (ws::webinars($creator) as $w) {
    $past = $w->platform !== 'auto' && $w->starttime + $w->duration * 60 < time();
    $public = ws::public_url($w)->out(false);
    $list .= html_writer::div(
        html_writer::div(html_writer::link(new moodle_url($url, ['id' => $w->id]), format_string($w->title), ['class' => 'fw-semibold']) .
            ($past ? ' ' . html_writer::span(get_string('webinar:past', 'local_oriskill'), 'oriskill-badge') : '') .
            html_writer::div(($w->platform === 'auto' ? get_string('webinar:auto', 'local_oriskill') . ' · ' . ws::schedule_summary($w)
                : userdate($w->starttime, get_string('strftimedaydatetime', 'langconfig'), $timezone)) . ' · ' .
                get_string('webinar:counts', 'local_oriskill', (object) ['registered' => $w->registered, 'attended' => $w->attended]),
                'small text-muted') .
            html_writer::div(html_writer::tag('code', s($public), ['class' => 'small user-select-all']), 'mt-1')) .
        html_writer::div(
            (!$past && $w->platform === 'youtube' ? html_writer::link('https://studio.youtube.com/video/' .
                ws::youtube_id($w->streamurl) . '/livestreaming', '<i class="fa fa-video me-1" aria-hidden="true"></i>' .
                get_string('webinar:studio', 'local_oriskill'), ['class' => 'btn btn-sm btn-primary', 'target' => '_blank']) . ' '
            : '') .
            (!$past && $w->platform === 'bbb' ? html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['id' => $w->id, 'host' => 1,
                'sesskey' => sesskey()])) . html_writer::tag('button', '<i class="fa fa-video me-1" aria-hidden="true"></i>' .
                get_string('webinar:host', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']),
                ['method' => 'post', 'action' => $url->out(false), 'target' => '_blank', 'class' => 'd-inline']) . ' ' : '') .
            html_writer::link(new moodle_url($url, ['id' => $w->id]), get_string('edit'), ['class' => 'btn btn-sm btn-outline-secondary']),
            'text-nowrap'),
        'oriskill-card d-flex justify-content-between align-items-start gap-3 mb-2');
}
echo $list ?: html_writer::div(get_string('webinar:none', 'local_oriskill'), 'oriskill-card oriskill-empty');

// People signed up to the webinar opened.
if ($webinar) {
    $regs = $DB->get_records(ws::REGS, ['webinarid' => $webinar->id], 'timecreated DESC');
    $rows = '';
    foreach ($regs as $r) {
        $rows .= html_writer::tag('tr', html_writer::tag('td', s($r->firstname)) . html_writer::tag('td', s($r->email)) .
            html_writer::tag('td', userdate($r->timecreated, get_string('strftimedatetimeshort', 'langconfig'), $timezone) .
                ($r->sessiontime ? html_writer::div(get_string('webinar:sessionat', 'local_oriskill', userdate($r->sessiontime,
                    get_string('strftimedatetimeshort', 'langconfig'), $timezone)), 'small text-muted') : '')) .
            html_writer::tag('td', $r->joined ? html_writer::span(get_string('yes'), 'oriskill-badge is-good') : '–'));
    }
    echo html_writer::div(html_writer::div(html_writer::tag('h3', get_string('webinar:registrations', 'local_oriskill', count($regs)),
        ['class' => 'h5 mb-0']) . ($regs && team_service::can_export() ? html_writer::link(new moodle_url($url, ['id' => $webinar->id,
        'download' => 'csv']), get_string('exportcsv', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary']) : ''),
        'd-flex justify-content-between align-items-center mb-2') .
        ($rows ? html_writer::tag('table', html_writer::tag('tr', html_writer::tag('th', get_string('firstname')) .
            html_writer::tag('th', get_string('email')) . html_writer::tag('th', get_string('webinar:signedup', 'local_oriskill')) .
            html_writer::tag('th', get_string('webinar:attended', 'local_oriskill'))) . $rows, ['class' => 'table table-sm'])
            : html_writer::div(get_string('webinar:noregistration', 'local_oriskill'), 'text-muted small')),
        'oriskill-card mt-3 table-responsive');
}

// Create or edit.
$field = fn(string $label, string $control, string $class = 'col-md-6') => html_writer::div(
    html_writer::tag('label', $label, ['class' => 'form-label small fw-semibold']) . $control, $class);
$input = fn(string $name, string $value, array $extra = []) => html_writer::empty_tag('input', ['type' => 'text', 'name' => $name,
    'value' => $value, 'class' => 'form-control', 'aria-label' => $name] + $extra);
$start = $webinar && $webinar->platform !== 'auto' ? userdate($webinar->starttime, '%Y-%m-%dT%H:%M', $timezone, false) : '';
$schedule = json_decode((string) ($webinar->schedule ?? ''), true) ?: [];
$weekdays = \core_calendar\type_factory::get_calendar_instance()->get_weekdays();
echo html_writer::div(html_writer::tag('h3', get_string($webinar ? 'webinar:edit' : 'webinar:new', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, ['sesskey' => sesskey(), 'action' => 'save',
        'id' => $webinar->id ?? 0])) . html_writer::div(
        $field(get_string('webinar:title', 'local_oriskill'), $input('title', $webinar->title ?? '', ['required' => 'required',
            'maxlength' => 255]), 'col-12') .
        $field(get_string('webinar:description', 'local_oriskill'), html_writer::tag('textarea', s($webinar->description ?? ''),
            ['name' => 'description', 'rows' => 3, 'class' => 'form-control', 'aria-label' => 'description']), 'col-12') .
        $field(get_string('webinar:platform', 'local_oriskill'), html_writer::select(['bbb' => get_string('webinar:platform_bbb',
            'local_oriskill'), 'youtube' => get_string('webinar:platform_youtube', 'local_oriskill'),
            'auto' => get_string('webinar:platform_auto', 'local_oriskill')], 'platform',
            $webinar->platform ?? (ws::available() ? 'bbb' : 'youtube'), false, ['class' => 'form-select', 'id' => 'webinar-platform'])) .
        $field(get_string('webinar:streamurl', 'local_oriskill'), $input('streamurl', $webinar->streamurl ?? '',
            ['placeholder' => 'https://www.youtube.com/live/…']) .
            html_writer::div(get_string('webinar:streamurl_help', 'local_oriskill'), 'form-text')) .
        $field(get_string('webinar:schedule', 'local_oriskill'), html_writer::div(implode(' ', array_map(fn($d, $day) =>
            html_writer::div(html_writer::checkbox('days[]', $d, in_array($d, $schedule['days'] ?? [], true), ' ' .
                $day['shortname'], ['class' => 'form-check-input', 'id' => 'webinar-day-' . $d]), 'form-check form-check-inline'),
            array_keys($weekdays), $weekdays))) .
            $input('times', implode(', ', $schedule['times'] ?? []), ['placeholder' => get_string('webinar:times', 'local_oriskill')]) .
            html_writer::div(html_writer::checkbox('jit', 1, !empty($schedule['jit']), ' ' . get_string('webinar:jit', 'local_oriskill'),
                ['class' => 'form-check-input']), 'form-check mt-2') .
            html_writer::div(get_string('webinar:schedule_help', 'local_oriskill'), 'form-text'), 'col-12') .
        $field(get_string('webinar:start', 'local_oriskill', $timezone), $input('start', $start, ['type' => 'datetime-local'])) .
        $field(get_string('webinar:duration', 'local_oriskill'), $input('duration', (string) ($webinar->duration ?? 60),
            ['type' => 'number', 'min' => 15, 'max' => 480]), 'col-md-3') .
        $field(get_string('webinar:replaydays', 'local_oriskill'), $input('replaydays', (string) ($webinar->replaydays ?? 7),
            ['type' => 'number', 'min' => 0, 'max' => 365]), 'col-md-3') .
        $field(get_string('webinar:offerurl', 'local_oriskill'), $input('offerurl', $webinar->offerurl ?? '',
            ['placeholder' => 'https://'])) .
        $field(get_string('webinar:offerlabel', 'local_oriskill'), $input('offerlabel', $webinar->offerlabel ?? '',
            ['maxlength' => 100]), 'col-md-3') .
        $field(get_string('webinar:offerafter', 'local_oriskill'), $input('offerafter', (string) ($webinar->offerafter ?? 45),
            ['type' => 'number', 'min' => 0, 'max' => 480]), 'col-md-3') .
        html_writer::div(html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']), 'col-12'),
        'row g-3'), ['method' => 'post', 'action' => $url->out(false)]), 'oriskill-card mt-4');

echo creator_service::space_end();
echo $OUTPUT->footer();
