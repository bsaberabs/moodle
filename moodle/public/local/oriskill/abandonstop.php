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
 * Link at the bottom of the abandon reminders: the prospect asks for no more of them (no login needed).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\abandon_service;

$id = required_param('id', PARAM_INT);
$key = required_param('key', PARAM_ALPHANUM);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oriskill/abandonstop.php', ['id' => $id, 'key' => $key]));
$PAGE->set_pagelayout('embedded');
$PAGE->set_title(get_string('abandon:stoptitle', 'local_oriskill'));

// Mail scanners open links: only a click on the button stops the reminders.
$posted = optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey();
$done = $posted && abandon_service::stop($id, $key);
if ($posted && !$done) {
    throw new moodle_exception('invalidrecord', 'error');
}

echo $OUTPUT->header();
echo html_writer::start_div('card mx-auto my-5', ['style' => 'max-width: 32rem']);
echo html_writer::start_div('card-body');
echo html_writer::tag('h2', get_string('abandon:stoptitle', 'local_oriskill'), ['class' => 'h5']);
if ($done) {
    echo html_writer::tag('p', get_string('abandon:stopped', 'local_oriskill'));
} else {
    echo html_writer::tag('p', get_string('abandon:stopask', 'local_oriskill'));
    echo html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'key', 'value' => $key]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]) .
        html_writer::tag('button', get_string('abandon:stopbutton', 'local_oriskill'), ['type' => 'submit',
            'class' => 'btn btn-primary']),
        ['method' => 'post', 'action' => (new moodle_url('/local/oriskill/abandonstop.php'))->out(false)]);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
