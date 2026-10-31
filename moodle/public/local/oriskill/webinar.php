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
 * Public page of a webinar: sign-up (?w=), then the personal page of the person signed up (?t=): the room when it is
 * open, the offer during the live, the replay afterwards. No account needed.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\webinar_service as ws;

$slug = optional_param('w', '', PARAM_ALPHANUM);
$token = optional_param('t', '', PARAM_ALPHANUM);
$reg = $token !== '' ? $DB->get_record(ws::REGS, ['token' => $token]) : null;
$webinar = $reg ? $DB->get_record(ws::TABLE, ['id' => $reg->webinarid])
    : ($slug !== '' ? $DB->get_record(ws::TABLE, ['slug' => $slug]) : null);
$creator = $webinar ? $DB->get_record('local_oriskill_creators', ['id' => $webinar->creatorid, 'status' => 'active']) : null;
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oriskill/webinar.php', $reg ? ['t' => $token] : ['w' => $slug]));
$PAGE->set_pagelayout('embedded');
if (!$webinar || !$creator) {
    send_header_404();
    $PAGE->set_title(get_string('webinar:notfound', 'local_oriskill'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('webinar:notfound', 'local_oriskill'), 'info');
    echo $OUTPUT->footer();
    exit;
}
$PAGE->set_title(format_string($webinar->title) . ' – ' . format_string($creator->displayname), false);
$timezone = \local_oriskill\stats_service::timezone($creator);
$end = $webinar->starttime + $webinar->duration * 60;

// Sign-up.
if (!$reg && optional_param('signup', 0, PARAM_BOOL) && confirm_sesskey()) {
    try {
        $reg = ws::register($webinar, required_param('email', PARAM_RAW_TRIMMED), required_param('firstname', PARAM_TEXT),
            ['direct' => optional_param('consentdirect', 0, PARAM_BOOL), 'partners' => optional_param('consentpartners', 0, PARAM_BOOL)]);
        redirect(ws::room_url($reg));
    } catch (moodle_exception $e) {
        redirect($PAGE->url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}
$youtube = $webinar->platform === 'youtube';
// Into the room (BigBlueButton; the YouTube live plays on this page).
if ($reg && optional_param('join', 0, PARAM_BOOL)) {
    if (!ws::is_open($webinar) || $youtube) {
        redirect(ws::room_url($reg));
    }
    if (!$reg->joined) {
        $DB->set_field(ws::REGS, 'joined', time(), ['id' => $reg->id]);
    }
    try {
        redirect(ws::join_url($webinar, $reg->firstname, false));
    } catch (moodle_exception $e) {
        redirect(ws::room_url($reg), $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$date = userdate($webinar->starttime, get_string('strftimedaydatetime', 'langconfig'), $timezone);
$content = html_writer::tag('p', html_writer::tag('strong', format_string($creator->displayname)), ['class' => 'text-muted mb-1']) .
    html_writer::tag('h1', format_string($webinar->title), ['class' => 'h3']) .
    html_writer::tag('p', '<i class="fa fa-calendar me-2" aria-hidden="true"></i>' . s($date) . ' · ' .
        get_string('webinar:minutes', 'local_oriskill', $webinar->duration), ['class' => 'fw-semibold']) .
    ($webinar->description !== '' ? html_writer::tag('p', nl2br(s($webinar->description))) : '');

if (!$reg) {
    // The sign-up form.
    $content .= html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'w', 'value' => $slug]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'signup', 'value' => 1]) .
        html_writer::div(html_writer::tag('label', get_string('firstname'), ['for' => 'w-first', 'class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'firstname', 'id' => 'w-first', 'required' => 'required',
                'class' => 'form-control']), 'mb-2') .
        html_writer::div(html_writer::tag('label', get_string('email'), ['for' => 'w-email', 'class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'email', 'name' => 'email', 'id' => 'w-email', 'required' => 'required',
                'class' => 'form-control']), 'mb-2') .
        \local_oriskill\privacy_service::consent_boxes($creator) .
        html_writer::tag('button', get_string('webinar:signup', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary btn-lg mt-2']),
        ['method' => 'post', 'action' => (new moodle_url('/local/oriskill/webinar.php'))->out(false), 'class' => 'mt-3']);
} else if (time() > $end) {
    // After the live: the replay while it can be watched.
    $replay = $webinar->replaydays > 0 && time() < $end + $webinar->replaydays * DAYSECS ? ws::replay_url($webinar) : '';
    $wide = $youtube && $replay !== '';
    $content .= $wide ? ws::youtube_embed($webinar, false, $PAGE->url->get_host()) : ($replay !== '' ? html_writer::link($replay, '<i class="fa fa-play me-2" aria-hidden="true"></i>' .
        get_string('webinar:watchreplay', 'local_oriskill'), ['class' => 'btn btn-primary btn-lg', 'target' => '_blank'])
        : html_writer::div(get_string('webinar:ended', 'local_oriskill'), 'alert alert-info'));
} else if ($youtube && ws::is_open($webinar)) {
    // The YouTube live and its chat, here: being on the page counts as attending.
    if (!$reg->joined) {
        $DB->set_field(ws::REGS, 'joined', time(), ['id' => $reg->id]);
    }
    $wide = true;
    $content .= ws::youtube_embed($webinar, true, $PAGE->url->get_host());
} else {
    $content .= html_writer::div(get_string('webinar:hello', 'local_oriskill', s($reg->firstname)), 'alert alert-success') .
        (ws::is_open($webinar) ? html_writer::link(new moodle_url($PAGE->url, ['join' => 1]),
            '<i class="fa fa-video me-2" aria-hidden="true"></i>' . get_string('webinar:join', 'local_oriskill'),
            ['class' => 'btn btn-primary btn-lg', 'target' => '_blank'])
            : html_writer::div(get_string('webinar:opensat', 'local_oriskill', userdate($webinar->starttime - ws::EARLY,
                get_string('strftimetime', 'langconfig'), $timezone)), 'text-muted') .
            html_writer::div('', 'fs-4 fw-bold mt-2', ['id' => 'webinar-countdown']));
}
// The offer during the live, from the minute chosen, and on the replay page.
if ($reg && $webinar->offerurl !== '') {
    $content .= html_writer::div(html_writer::link($webinar->offerurl, s($webinar->offerlabel ?: get_string('webinar:offerdefault',
        'local_oriskill')), ['class' => 'btn btn-warning btn-lg w-100 fw-bold', 'target' => '_blank']), 'mt-4',
        ['id' => 'webinar-offer', 'hidden' => 'hidden']);
}
$PAGE->requires->js_init_code('(function() {
    var start = ' . (int) $webinar->starttime . ' * 1000, offer = start + ' . (int) $webinar->offerafter . ' * 60000,
        skew = Date.now() - ' . time() . ' * 1000, box = document.getElementById("webinar-countdown"),
        offerbox = document.getElementById("webinar-offer");
    function tick() {
        var now = Date.now() - skew, left = Math.max(0, Math.floor((start - now) / 1000));
        if (box) {
            box.textContent = left > 0 ? Math.floor(left / 86400) + " j " + new Date(left * 1000).toISOString().substr(11, 8) : "";
            if (left <= ' . ws::EARLY . ' && left > 0 && !box.dataset.reloaded) { box.dataset.reloaded = 1; location.reload(); }
        }
        if (offerbox && now >= offer) { offerbox.hidden = false; }
    }
    tick();
    setInterval(tick, 1000);
})();');

echo $OUTPUT->header();
echo html_writer::div(html_writer::div($content, 'card-body p-4'), 'card mx-auto my-5',
    ['style' => 'max-width: ' . (!empty($wide) ? '72rem' : '40rem')]);
echo \local_oriskill\privacy_service::banner($creator, 'funnel');
echo $OUTPUT->footer();
