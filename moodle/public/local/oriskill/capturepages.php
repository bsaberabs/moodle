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
 * Trainer's capture pages: list, address, visitors and sign-ups, export of the sign-ups.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_oriskill\capture_service;
use local_oriskill\creator_service;

$url = new moodle_url('/local/oriskill/capturepages.php');
$creator = creator_service::require_current_creator($url, 'menu:capturepages');

// Export of a page's sign-ups.
if ($exportid = optional_param('export', 0, PARAM_INT)) {
    $page = $DB->get_record(capture_service::TABLE, ['id' => $exportid, 'creatorid' => $creator->id], '*', MUST_EXIST);
    \local_oriskill\team_service::export_allowed($creator, format_string($page->title));
    $csv = new csv_export_writer();
    $csv->set_filename('inscrits-' . $page->slug);
    $csv->add_data([get_string('firstname'), get_string('lastname'), get_string('email'), get_string('date'),
        get_string('capture:consentcol', 'local_oriskill')]);
    foreach (capture_service::leads($page) as $lead) {
        $user = core_user::get_user($lead->userid);
        $csv->add_data([$lead->firstname, $user->lastname ?? '', $lead->email, userdate($lead->timecreated, '%Y-%m-%d %H:%M'),
            $lead->consent ? get_string('yes') : get_string('no')]);
    }
    $csv->download_file();
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/capturepages.php', get_string('menu:capturepages', 'local_oriskill'));
echo html_writer::div(
    html_writer::tag('p', get_string('capture:intro', 'local_oriskill'), ['class' => 'mb-0 text-muted']) .
    html_writer::link(new moodle_url('/local/oriskill/capturepage.php'), '<i class="fa fa-plus me-1" aria-hidden="true"></i>' .
        get_string('capture:new', 'local_oriskill'), ['class' => 'btn btn-primary']),
    'd-flex flex-wrap justify-content-between align-items-center gap-3 mb-4');

$pages = capture_service::pages($creator);
if (!$pages) {
    echo html_writer::div('<i class="fa fa-envelope-open-text" aria-hidden="true"></i>' .
        get_string('capture:none', 'local_oriskill'), 'oriskill-card oriskill-empty');
} else {
    $counts = capture_service::lead_counts($creator);
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle oriskill-documents';
    $table->head = [get_string('orderform:title', 'local_oriskill'), get_string('orderform:address', 'local_oriskill'),
        get_string('stats:views', 'local_oriskill'), get_string('capture:signups', 'local_oriskill'),
        get_string('stats:conversion', 'local_oriskill'), ''];
    foreach ($pages as $page) {
        $published = $page->status === 'published';
        $public = capture_service::url($page, $creator)->out(false);
        $leads = (int) ($counts[$page->id] ?? 0);
        $table->data[] = [
            html_writer::span(format_string($page->title), 'fw-semibold') . ' ' .
                html_writer::span(get_string('orderform:' . $page->status, 'local_oriskill'),
                    'oriskill-badge ' . ($published ? 'is-good' : '')),
            $published ? html_writer::link($public, get_string('capture:open', 'local_oriskill') .
                ' <i class="fa fa-arrow-up-right-from-square" aria-hidden="true"></i>',
                ['target' => '_blank', 'rel' => 'noopener', 'title' => $public, 'class' => 'small'])
                : html_writer::span(get_string('orderform:notpublished', 'local_oriskill'), 'small text-muted'),
            (int) $page->views,
            $leads,
            $page->views ? format_float(min(100, 100 * $leads / $page->views), 1, true, true) . ' %' : '–',
            ($leads ? html_writer::link(new moodle_url($url, ['export' => $page->id]), get_string('capture:export', 'local_oriskill'),
                ['class' => 'btn btn-sm btn-outline-secondary']) . ' ' : '') .
            html_writer::link(new moodle_url('/local/oriskill/capturepage.php', ['id' => $page->id]), get_string('edit'),
                ['class' => 'btn btn-sm btn-outline-secondary']),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card');
}
echo creator_service::space_end();
echo $OUTPUT->footer();
