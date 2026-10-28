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
 * Affiliate programme: join, links to share, visits, commissions and payout details.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\affiliate_service;

$url = new moodle_url('/local/oriskill/affiliate.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$title = get_string('affiliateprogram', 'local_oriskill');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$terms = (object) [
    'rate' => format_float((float) get_config('local_oriskill', 'platformrate'), 2, true, true),
    'days' => (int) get_config('local_oriskill', 'cookiedays'),
    'validation' => (int) get_config('local_oriskill', 'validationdays'),
];
if (affiliate_service::enabled()) {
    local_oriskill_visitor_page($url, html_writer::tag('p', get_string('affiliate_intro', 'local_oriskill', $terms),
        ['class' => 'lead']) . html_writer::tag('p', get_string('affiliate_terms', 'local_oriskill', $terms)));
}
require_login(null, false);

$affiliate = affiliate_service::get_for_user((int) $USER->id);

if (data_submitted() && confirm_sesskey() && affiliate_service::enabled()) {
    if (optional_param('join', 0, PARAM_BOOL)) {
        affiliate_service::join((int) $USER->id);
    } else if ($affiliate && optional_param('savepayout', 0, PARAM_BOOL)) {
        $DB->update_record('local_oriskill_affiliates', (object) ['id' => $affiliate->id,
            'payoutdetails' => trim(optional_param('payoutdetails', '', PARAM_TEXT)), 'timemodified' => time()]);
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
if (!affiliate_service::enabled()) {
    echo $OUTPUT->notification(get_string('affiliatedisabled', 'local_oriskill'), 'info');
    echo $OUTPUT->footer();
    exit;
}

if (!$affiliate) {
    echo html_writer::tag('p', get_string('affiliate_intro', 'local_oriskill', $terms), ['class' => 'lead']);
    echo html_writer::tag('p', get_string('affiliate_terms', 'local_oriskill', $terms));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('button', get_string('joinaffiliate', 'local_oriskill'),
        ['type' => 'submit', 'name' => 'join', 'value' => 1, 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

if ($affiliate->status !== 'active') {
    echo $OUTPUT->notification(get_string('affiliatesuspended', 'local_oriskill'), 'warning');
}

$copyfield = fn(string $value) => html_writer::empty_tag('input', ['type' => 'text', 'readonly' => 'readonly',
    'value' => $value, 'class' => 'form-control', 'onclick' => 'this.select()']);

// Main link and figures.
$commissions = affiliate_service::commissions(['affiliateid' => $affiliate->id]);
$totals = affiliate_service::totals($commissions);
$orders = $DB->count_records('local_oriskill_referrals', ['affiliateid' => $affiliate->id]);
$sum = function(string $status) use ($totals) {
    $parts = [];
    foreach ($totals[$status] ?? [] as $currency => $amount) {
        $parts[] = pricing_service::format_price($amount, $currency);
    }
    return $parts ? implode(' + ', $parts) : '0';
};

echo html_writer::tag('h3', get_string('affiliatelink', 'local_oriskill'), ['class' => 'h5']);
echo html_writer::tag('p', get_string('affiliatelink_desc', 'local_oriskill', $terms));
echo html_writer::div($copyfield(affiliate_service::link($affiliate)->out(false)), 'mb-4', ['style' => 'max-width: 36rem']);

$cards = '';
foreach ([
    'stat:clicks' => (int) $affiliate->clicks,
    'stat:orders' => $orders,
    'commission:pending' => $sum('pending'),
    'commission:approved' => $sum('approved'),
    'commission:paid' => $sum('paid'),
] as $key => $value) {
    $cards .= html_writer::div(html_writer::div(
        html_writer::div(s($value), 'h4 mb-1') . html_writer::div(get_string($key, 'local_oriskill'), 'text-muted small'),
        'card-body'), 'card text-center', ['style' => 'flex: 1 1 9rem']);
}
echo html_writer::div($cards, 'd-flex flex-wrap gap-3 mb-4');

// Courses to recommend.
echo html_writer::tag('h3', get_string('promotablecourses', 'local_oriskill'), ['class' => 'h5']);
$courses = affiliate_service::promotable_courses($affiliate);
if ($courses) {
    $table = new html_table();
    $table->head = [get_string('course'), get_string('pricing', 'local_oriskill'),
        get_string('ratecol', 'local_oriskill'), get_string('affiliatelink', 'local_oriskill')];
    foreach ($courses as $course) {
        $table->data[] = [$course->name, $course->price, $course->rate . ' %', $copyfield($course->url)];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nopromotablecourses', 'local_oriskill'), 'info');
}

// Commissions.
echo html_writer::tag('h3', get_string('mycommissions', 'local_oriskill'), ['class' => 'h5 mt-4']);
echo html_writer::tag('p', get_string('commissions_explained', 'local_oriskill', $terms), ['class' => 'small text-muted']);
if ($commissions) {
    $table = new html_table();
    $table->head = [get_string('date'), get_string('course'), get_string('amountcol', 'local_oriskill'),
        get_string('ratecol', 'local_oriskill'), get_string('commissioncol', 'local_oriskill'), get_string('status')];
    foreach ($commissions as $commission) {
        $table->data[] = [
            userdate($commission->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            format_string($commission->itemname),
            pricing_service::format_price($commission->base, $commission->currency),
            format_float($commission->rate, 2, true, true) . ' %',
            pricing_service::format_price($commission->amount, $commission->currency),
            get_string('commission:' . $commission->status, 'local_oriskill'),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nocommissions', 'local_oriskill'), 'info');
}

// Payout details.
echo html_writer::tag('h3', get_string('payoutdetails', 'local_oriskill'), ['class' => 'h5 mt-4']);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false), 'style' => 'max-width: 36rem']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('label', get_string('payoutdetails_desc', 'local_oriskill'), ['for' => 'payoutdetails',
    'class' => 'form-label']);
echo html_writer::tag('textarea', s($affiliate->payoutdetails ?? ''), ['id' => 'payoutdetails', 'name' => 'payoutdetails',
    'rows' => 3, 'class' => 'form-control mb-2']);
echo html_writer::tag('button', get_string('savechanges'),
    ['type' => 'submit', 'name' => 'savepayout', 'value' => 1, 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
