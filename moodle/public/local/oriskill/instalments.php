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
 * Buy a trainer course in monthly instalments: the buyer confirms, then pays the first one at SecureAndPay.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_oriskill\instalment_service;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
$url = new moodle_url('/local/oriskill/instalments.php', ['courseid' => $courseid]);
$detailsurl = new moodle_url('/local/moderncommerce/course_details.php', ['id' => $courseid]);

$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_title(get_string('instalments:buytitle', 'local_oriskill', format_string($course->fullname)));
$PAGE->set_heading(format_string($course->fullname));

$offer = instalment_service::buyable_offer($courseid);
if (!$offer) {
    redirect($detailsurl, get_string('error:noinstalments', 'local_oriskill'), null,
        \core\output\notification::NOTIFY_INFO);
}
$summary = get_string('instalments:summary', 'local_oriskill', $offer);
if (!isloggedin() || isguestuser()) {
    local_oriskill_visitor_page($url, html_writer::tag('p', $summary, ['class' => 'lead']));
}
require_login(null, false);

if (is_enrolled(context_course::instance($courseid), $USER, '', true)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
    [$order, $reference] = instalment_service::create_order((int) $USER->id, $courseid);
    redirect(new moodle_url('/local/secureandpay/pay.php', ['orderid' => $order->id, 'ref' => $reference]));
}

echo $OUTPUT->header();
echo html_writer::start_div('card mx-auto', ['style' => 'max-width: 36rem']);
echo html_writer::start_div('card-body');
echo html_writer::tag('p', $summary, ['class' => 'lead']);
echo html_writer::tag('p', get_string('instalments:howitworks', 'local_oriskill', $offer));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
echo html_writer::tag('button', get_string('instalments:pay', 'local_oriskill', $offer),
    ['type' => 'submit', 'class' => 'btn btn-primary btn-lg']);
echo ' ' . html_writer::link($detailsurl, get_string('instalments:payatonce', 'local_oriskill'), ['class' => 'btn btn-link']);
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
