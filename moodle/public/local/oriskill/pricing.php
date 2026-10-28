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
 * Trainer sets the price of their courses and puts them on sale.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\creator_service;
use local_oriskill\seller_service;

$url = new moodle_url('/local/oriskill/pricing.php');
$creator = creator_service::require_current_creator($url, 'pricing');
$courses = seller_service::priced_courses($creator);

if (data_submitted() && confirm_sesskey()) {
    $prices = optional_param_array('price', [], PARAM_RAW_TRIMMED);
    $compares = optional_param_array('compare', [], PARAM_RAW_TRIMMED);
    $onsale = optional_param_array('onsale', [], PARAM_BOOL);
    $errors = [];
    foreach ($prices as $courseid => $price) {
        $courseid = (int) $courseid;
        if (!isset($courses[$courseid]) || $price === '') {
            continue;
        }
        $amount = unformat_float($price, true);
        $compare = ($compares[$courseid] ?? '') === '' ? null : unformat_float($compares[$courseid], true);
        if ($amount === false || $amount <= 0 || ($compare !== null && ($compare === false || $compare <= $amount))) {
            $errors[] = format_string($courses[$courseid]->course->fullname);
            continue;
        }
        seller_service::set_course_price($creator, $courseid, $amount, $compare, !empty($onsale[$courseid]));
    }
    redirect($url, $errors ? get_string('error:invalidprices', 'local_oriskill', implode(', ', $errors))
        : get_string('changessaved'), null,
        $errors ? \core\output\notification::NOTIFY_ERROR : \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_menu($creator, '/local/oriskill/pricing.php');
if ($blocker = seller_service::selling_blocker($creator)) {
    echo $OUTPUT->notification(get_string($blocker, 'local_oriskill', (object) [
        'payment' => (new moodle_url('/local/oriskill/payment.php'))->out(false),
        'plan' => (new moodle_url('/local/moderncommerce/subscribe.php'))->out(false),
    ]), 'warning');
}
if (!$courses) {
    echo $OUTPUT->notification(get_string('nocoursesyet', 'local_oriskill',
        (new moodle_url('/course/edit.php', ['category' => $creator->categoryid]))->out(false)), 'info');
    echo $OUTPUT->footer();
    exit;
}

$currency = pricing_service::get_currency_config()->currency;
$table = new html_table();
$table->head = [get_string('course'), get_string('pricecol', 'local_oriskill', $currency),
    get_string('comparecol', 'local_oriskill', $currency), get_string('onsale', 'local_oriskill')];
foreach ($courses as $courseid => $row) {
    $table->data[] = [
        html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), format_string($row->course->fullname)),
        html_writer::empty_tag('input', ['type' => 'text', 'name' => "price[$courseid]", 'size' => 8, 'class' => 'form-control',
            'inputmode' => 'decimal', 'value' => $row->amount !== null ? format_float($row->amount, 2, true) : '']),
        html_writer::empty_tag('input', ['type' => 'text', 'name' => "compare[$courseid]", 'size' => 8, 'class' => 'form-control',
            'inputmode' => 'decimal', 'value' => $row->compareamount ? format_float($row->compareamount, 2, true) : '']),
        html_writer::checkbox("onsale[$courseid]", 1, $row->onsale, '', ['class' => 'form-check-input']),
    ];
}
echo html_writer::tag('p', get_string('pricing_intro', 'local_oriskill'));
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::table($table);
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
