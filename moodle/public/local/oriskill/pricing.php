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
use local_oriskill\instalment_service;
use local_oriskill\seller_service;

$url = new moodle_url('/local/oriskill/pricing.php');
$creator = creator_service::require_current_creator($url, 'pricing');
$courses = seller_service::priced_courses($creator);
$caninstalments = instalment_service::can_offer($creator);

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
        $before = $courses[$courseid];
        if ($before->amount === null || abs($before->amount - $amount) >= 0.005 || $before->compareamount != $compare
                || $before->onsale !== !empty($onsale[$courseid])) {
            seller_service::set_course_price($creator, $courseid, $amount, $compare, !empty($onsale[$courseid]));
            $price = fn($value) => $value === null ? '–' : format_float((float) $value, 2, true);
            \local_oriskill\log_service::add($creator, 'price_set', ['price' => format_float($amount, 2, true),
                'onsale' => get_string(!empty($onsale[$courseid]) ? 'yes' : 'no')], $courseid, null, [
                    get_string('change:price', 'local_oriskill') => [$price($before->amount), $price($amount)],
                    get_string('change:compareprice', 'local_oriskill') => [$price($before->compareamount), $price($compare)],
                    get_string('change:onsale', 'local_oriskill') => [get_string($before->onsale ? 'yes' : 'no'),
                        get_string(!empty($onsale[$courseid]) ? 'yes' : 'no')],
                ]);
        }
    }
    if ($caninstalments) {
        $counts = optional_param_array('instcount', [], PARAM_INT);
        $totals = optional_param_array('insttotal', [], PARAM_RAW_TRIMMED);
        $cuts = optional_param_array('instcut', [], PARAM_INT);
        foreach ($counts as $courseid => $count) {
            $courseid = (int) $courseid;
            if (!isset($courses[$courseid])) {
                continue;
            }
            $total = $count ? unformat_float($totals[$courseid] ?? '', true) : 0;
            if ($count && (!in_array($count, instalment_service::COUNTS, true) || !$total || $total <= 0)) {
                $errors[] = format_string($courses[$courseid]->course->fullname);
                continue;
            }
            $before = instalment_service::offer($courseid);
            $cut = (int) ($cuts[$courseid] ?? 1);
            if (($before->count ?? 0) != $count || ($count && abs(($before->total ?? 0) - $total) >= 0.01)
                    || ($count && (int) ($before->cut ?? 1) !== $cut)) {
                instalment_service::save_offer($creator, $courseid, $count, (float) $total, $cut);
                $offer = fn($n, $sum, $c) => $n ? get_string('change:instalmentsvalue', 'local_oriskill',
                    (object) ['count' => $n, 'total' => format_float((float) $sum, 2, true), 'cut' => $c]) : get_string('no');
                \local_oriskill\log_service::add($creator, $count ? 'instalments_set' : 'instalments_off',
                    ['count' => $count, 'total' => format_float((float) $total, 2, true)], $courseid, null,
                    [get_string('change:instalments', 'local_oriskill') => [$offer($before->count ?? 0, $before->total ?? 0,
                        (int) ($before->cut ?? 1)), $offer($count, $total, $cut)]]);
            }
        }
    }
    $rate = unformat_float(optional_param('affiliaterate', '0', PARAM_RAW_TRIMMED) ?: '0', true);
    if ($rate === false || $rate < 0 || $rate > 90) {
        $errors[] = get_string('affiliaterate', 'local_oriskill');
    } else {
        if (abs((float) $creator->affiliaterate - $rate) >= 0.005) {
            $DB->set_field('local_oriskill_creators', 'affiliaterate', $rate, ['id' => $creator->id]);
            \local_oriskill\log_service::add($creator, 'affiliaterate_set', ['rate' => format_float($rate, 2, true, true)], null,
                null, [get_string('affiliaterate', 'local_oriskill') => [format_float((float) $creator->affiliaterate, 2, true, true)
                    . ' %', format_float($rate, 2, true, true) . ' %']]);
        }
    }
    redirect($url, $errors ? get_string('error:invalidprices', 'local_oriskill', implode(', ', $errors))
        : get_string('changessaved'), null,
        $errors ? \core\output\notification::NOTIFY_ERROR : \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/pricing.php', get_string('pricing', 'local_oriskill'));
if ($blocker = seller_service::selling_blocker($creator)) {
    echo $OUTPUT->notification(get_string($blocker, 'local_oriskill', (object) [
        'payment' => (new moodle_url('/local/oriskill/payment.php'))->out(false),
        'plan' => (new moodle_url('/local/moderncommerce/subscribe.php'))->out(false),
    ]), 'warning');
}
if (!$courses) {
    echo $OUTPUT->notification(get_string('nocoursesyet', 'local_oriskill',
        (new moodle_url('/course/edit.php', ['category' => $creator->categoryid]))->out(false)), 'info');
    echo creator_service::space_end();
    echo $OUTPUT->footer();
    exit;
}

$currency = pricing_service::get_currency_config()->currency;
$table = new html_table();
$table->head = [get_string('course'), get_string('pricecol', 'local_oriskill', $currency),
    get_string('comparecol', 'local_oriskill', $currency), get_string('onsale', 'local_oriskill')];
if ($caninstalments) {
    $table->head[] = get_string('instalments:countcol', 'local_oriskill');
    $table->head[] = get_string('instalments:totalcol', 'local_oriskill', $currency);
    $table->head[] = get_string('instalments:cutcol', 'local_oriskill');
}
$cutoptions = [];
foreach (instalment_service::CUTS as $cut) {
    $cutoptions[$cut] = get_string('instalments:cut', 'local_oriskill', $cut);
}
$countoptions = [0 => get_string('instalments:none', 'local_oriskill')];
foreach (instalment_service::COUNTS as $count) {
    $countoptions[$count] = get_string('instalments:times', 'local_oriskill', $count);
}
foreach ($courses as $courseid => $row) {
    $cells = [
        html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]), format_string($row->course->fullname)),
        html_writer::empty_tag('input', ['type' => 'text', 'name' => "price[$courseid]", 'size' => 8, 'class' => 'form-control',
            'inputmode' => 'decimal', 'value' => $row->amount !== null ? format_float($row->amount, 2, true) : '']),
        html_writer::empty_tag('input', ['type' => 'text', 'name' => "compare[$courseid]", 'size' => 8, 'class' => 'form-control',
            'inputmode' => 'decimal', 'value' => $row->compareamount ? format_float($row->compareamount, 2, true) : '']),
        html_writer::checkbox("onsale[$courseid]", 1, $row->onsale, '', ['class' => 'form-check-input']),
    ];
    if ($caninstalments) {
        $offer = instalment_service::offer($courseid);
        $cells[] = html_writer::select($countoptions, "instcount[$courseid]", $offer->count ?? 0, false,
            ['class' => 'form-select']);
        $cells[] = html_writer::empty_tag('input', ['type' => 'text', 'name' => "insttotal[$courseid]", 'size' => 8,
            'class' => 'form-control', 'inputmode' => 'decimal',
            'value' => $offer ? format_float($offer->total, 2, true) : '']) .
            ($offer ? html_writer::div(get_string('instalments:preview', 'local_oriskill', (object) [
                'count' => $offer->count, 'amount' => pricing_service::format_price($offer->amount, $currency)]),
                'form-text') : '');
        $cells[] = html_writer::select($cutoptions, "instcut[$courseid]", (int) ($offer->cut ?? 1), false,
            ['class' => 'form-select']);
    }
    $table->data[] = $cells;
}
echo html_writer::tag('p', get_string('pricing_intro', 'local_oriskill'));
echo html_writer::tag('p', get_string($caninstalments ? 'instalments:intro' : 'instalments:needsap', 'local_oriskill',
    (object) ['days' => instalment_service::GRACE_DAYS,
        'payment' => (new moodle_url('/local/oriskill/payment.php'))->out(false)]), ['class' => 'text-muted']);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::table($table);
echo html_writer::div(
    html_writer::tag('label', get_string('affiliaterate', 'local_oriskill'), ['for' => 'affiliaterate', 'class' => 'form-label']) .
    html_writer::empty_tag('input', ['type' => 'text', 'id' => 'affiliaterate', 'name' => 'affiliaterate', 'size' => 5,
        'inputmode' => 'decimal', 'class' => 'form-control', 'style' => 'max-width: 7rem',
        'value' => format_float((float) $creator->affiliaterate, 2, true, true)]) .
    html_writer::div(get_string('affiliaterate_desc', 'local_oriskill', (object) [
        'link' => (new moodle_url('/local/oriskill/affiliate.php'))->out(false),
        'days' => (int) get_config('local_oriskill', 'validationdays')]), 'form-text'),
    'mb-3');
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo creator_service::space_end();
echo $OUTPUT->footer();
