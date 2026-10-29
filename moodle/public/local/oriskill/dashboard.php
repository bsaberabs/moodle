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
 * Trainer dashboard: key figures, steps to start selling, latest sales and courses.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\creator_service;
use local_oriskill\seller_service;

$url = new moodle_url('/local/oriskill/dashboard.php');
$creator = creator_service::require_current_creator($url, 'menu:dashboard');

$courses = seller_service::priced_courses($creator);
$learners = seller_service::learner_counts($creator, array_keys($courses));
$sales = seller_service::sales($creator);
$paid = array_filter($sales, fn($sale) => $sale->status !== 'refunded');
$revenue = [];
foreach ($paid as $sale) {
    $revenue[$sale->currency] = ($revenue[$sale->currency] ?? 0) + (float) $sale->total;
}
$revenuetext = [];
foreach ($revenue as $currency => $amount) {
    $revenuetext[] = pricing_service::format_price($amount, $currency);
}
$onsale = count(array_filter($courses, fn($row) => $row->onsale));
$newcourseurl = new moodle_url('/course/edit.php', ['category' => $creator->categoryid]);

// Steps to start selling, in order.
$steps = [
    [trim((string) $creator->tagline) !== '' && trim(html_to_text((string) $creator->bio, 0)) !== '',
        'step:profile', new moodle_url('/local/oriskill/apply.php')],
    [(bool) $courses, 'step:course', $newcourseurl],
    [seller_service::merchant($creator) !== null || seller_service::usable_gateways($creator), 'step:payment',
        new moodle_url('/local/oriskill/payment.php')],
];
if (seller_service::creator_plan()) {
    $steps[] = [seller_service::selling_blocker($creator) !== 'blocker:nosubscription', 'step:plan',
        new moodle_url('/local/moderncommerce/subscribe.php')];
}
$steps[] = [$onsale > 0, 'step:onsale', new moodle_url('/local/oriskill/pricing.php')];
if (get_config('tool_mfa', 'enabled')) {
    $steps[] = [$DB->record_exists_select('tool_mfa', "userid = ? AND factor IN ('totp', 'webauthn') AND revoked = 0",
        [$USER->id]), 'step:mfa', new moodle_url('/admin/tool/mfa/user_preferences.php')];
}
$done = count(array_filter($steps, fn($step) => $step[0]));

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/dashboard.php', get_string('menu:dashboard', 'local_oriskill'));

// Welcome banner.
echo html_writer::div(
    html_writer::div(
        html_writer::tag('h3', get_string('dashboard:hello', 'local_oriskill', s($USER->firstname))) .
        html_writer::tag('p', get_string('dashboard:intro', 'local_oriskill'))) .
    html_writer::div(
        html_writer::link($newcourseurl, '<i class="fa fa-plus me-1" aria-hidden="true"></i>' .
            get_string('createcourse', 'local_oriskill'), ['class' => 'btn btn-light'])),
    'oriskill-hello');

// Key figures.
$stats = '';
foreach ([
    ['fa-graduation-cap', count($courses), get_string('stat:courses', 'local_oriskill', $onsale)],
    ['fa-user-graduate', array_sum($learners), get_string('stat:learners', 'local_oriskill')],
    ['fa-cart-shopping', count($paid), get_string('stat:sales', 'local_oriskill')],
    ['fa-euro-sign', $revenuetext ? implode(' + ', $revenuetext) : '0', get_string('stat:revenue', 'local_oriskill')],
] as [$icon, $value, $label]) {
    $stats .= html_writer::div(
        html_writer::span('<i class="fa ' . $icon . '" aria-hidden="true"></i>', 'oriskill-stat-icon') .
        html_writer::div(html_writer::div(s($value), 'oriskill-stat-value') . html_writer::div($label, 'oriskill-stat-label')),
        'oriskill-card oriskill-stat');
}
echo html_writer::div($stats, 'oriskill-stats');

// Steps to start selling.
$items = '';
foreach ($steps as [$isdone, $key, $link]) {
    $items .= html_writer::tag('li',
        html_writer::span('<i class="fa fa-check" aria-hidden="true"></i>', 'oriskill-step-mark') .
        html_writer::div(
            html_writer::span(get_string($key, 'local_oriskill'), 'oriskill-step-text') .
            ($isdone ? '' : html_writer::div(html_writer::link($link, get_string($key . '_action', 'local_oriskill')),
                'small'))),
        ['class' => $isdone ? 'done' : '']);
}
$percent = (int) round(100 * $done / count($steps));
$stepscard = html_writer::tag('h3', get_string('dashboard:steps', 'local_oriskill', (object) ['done' => $done,
        'total' => count($steps)])) .
    html_writer::div(html_writer::span('', '', ['style' => "width: {$percent}%"]), 'oriskill-progress',
        ['role' => 'progressbar', 'aria-valuenow' => $percent, 'aria-valuemin' => 0, 'aria-valuemax' => 100]) .
    html_writer::tag('ul', $items, ['class' => 'oriskill-steps']);

// Latest sales.
$rows = '';
foreach (array_slice($sales, 0, 5) as $sale) {
    $rows .= html_writer::tag('li',
        html_writer::div(html_writer::div(format_string($sale->itemname), 'oriskill-row-title') .
            html_writer::div(s($sale->firstname . ' ' . $sale->lastname) . ' · ' .
                userdate($sale->timepaid, get_string('strftimedatetimeshort', 'langconfig')), 'oriskill-row-sub'),
            'oriskill-row-main') .
        html_writer::span(pricing_service::format_price($sale->total, $sale->currency),
            'oriskill-badge ' . ($sale->status === 'refunded' ? '' : 'is-good')));
}
$salescard = html_writer::tag('h3', get_string('dashboard:latestsales', 'local_oriskill')) .
    ($rows ? html_writer::tag('ul', $rows, ['class' => 'oriskill-rows']) .
        html_writer::div(html_writer::link(new moodle_url('/local/oriskill/sales.php'),
            get_string('seeall', 'local_oriskill')), 'mt-2 small')
    : html_writer::div('<i class="fa fa-chart-line" aria-hidden="true"></i>' . get_string('nosales', 'local_oriskill'),
        'oriskill-empty'));
echo html_writer::div(html_writer::div($stepscard, 'oriskill-card') . html_writer::div($salescard, 'oriskill-card'),
    'oriskill-grid-2');

// Courses.
$rows = '';
foreach (array_slice($courses, 0, 5, true) as $courseid => $row) {
    $rows .= html_writer::tag('li',
        html_writer::div(html_writer::div(html_writer::link(new moodle_url('/course/view.php', ['id' => $courseid]),
            format_string($row->course->fullname)), 'oriskill-row-title') .
            html_writer::div(get_string('learnerscount', 'local_oriskill', (int) ($learners[$courseid] ?? 0)) .
                ($row->amount !== null ? ' · ' . pricing_service::format_price($row->amount) : ''), 'oriskill-row-sub'),
            'oriskill-row-main') .
        creator_service::course_status_badge($row));
}
echo html_writer::div(html_writer::tag('h3', get_string('mycourses', 'local_oriskill')) .
    ($rows ? html_writer::tag('ul', $rows, ['class' => 'oriskill-rows']) .
        html_writer::div(html_writer::link(new moodle_url('/local/oriskill/courses.php'),
            get_string('seeall', 'local_oriskill')), 'mt-2 small')
    : html_writer::div('<i class="fa fa-graduation-cap" aria-hidden="true"></i>' .
        get_string('nocoursesyet', 'local_oriskill', $newcourseurl->out(false)), 'oriskill-empty')),
    'oriskill-card');

echo creator_service::space_end();
echo $OUTPUT->footer();
