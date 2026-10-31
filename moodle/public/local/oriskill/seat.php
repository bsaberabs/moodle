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
 * Use a place code received from a buyer: the courses of the offer open.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$url = new moodle_url('/local/oriskill/seat.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title(get_string('seat:pagetitle', 'local_oriskill'));
$PAGE->set_heading(get_string('seat:pagetitle', 'local_oriskill'));
local_oriskill_visitor_page($url, html_writer::tag('p', get_string('seat:loginfirst', 'local_oriskill')));
require_login(null, false);

if (($code = optional_param('code', '', PARAM_TEXT)) !== '' && confirm_sesskey()) {
    try {
        $names = \local_oriskill\seat_service::redeem($USER, $code);
        redirect(new moodle_url('/local/moderncommerce/learner/index.php'), get_string('seat:redeemed', 'local_oriskill',
            implode(', ', $names)), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo html_writer::div(html_writer::div(
    html_writer::tag('p', get_string('seat:intro', 'local_oriskill')) .
    html_writer::tag('form', html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::div(html_writer::empty_tag('input', ['type' => 'text', 'name' => 'code', 'required' => 'required',
            'class' => 'form-control', 'placeholder' => 'ABCD-EFGH-IJKL', 'aria-label' => get_string('seat:code', 'local_oriskill')]) .
            html_writer::tag('button', get_string('seat:use', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary']),
            'd-flex gap-2'), ['method' => 'post', 'action' => $url->out(false)]), 'card-body'), 'card mx-auto',
    ['style' => 'max-width: 32rem']);
echo $OUTPUT->footer();
