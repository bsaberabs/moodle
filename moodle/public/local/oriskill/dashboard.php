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
use local_oriskill\stats_service as st;

$url = new moodle_url('/local/oriskill/dashboard.php');
$creator = creator_service::require_current_creator($url, 'menu:dashboard');

$courses = seller_service::priced_courses($creator);
$learners = seller_service::learner_counts($creator, array_keys($courses));
$period = optional_param('period', 'today', PARAM_ALPHANUM);
$period = in_array($period, st::PERIODS, true) ? $period : 'today';
$onsale = count(array_filter($courses, fn($row) => $row->onsale));
$newcourseurl = new moodle_url('/course/edit.php', ['category' => $creator->categoryid]);
// A collaborator only gets the links of the pages their role opens.
$role = creator_service::current_role();
$can = fn(string $path) => \local_oriskill\team_service::can($role, $path);
// Links of the steps: a page of the trainer space, course creation (with the courses), the trainer's own
// subscription (owner only) or the user's own two-factor setup.
$linkallowed = function(moodle_url $link) use ($can, $role): bool {
    $path = $link->get_path(false);
    if (str_starts_with($path, '/local/oriskill/')) {
        return $can($path);
    }
    if ($path === '/course/edit.php') {
        return $can('/local/oriskill/courses.php');
    }
    return $role === 'owner' || $path === '/admin/tool/mfa/user_preferences.php';
};

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
    html_writer::div($can('/local/oriskill/courses.php')
        ? html_writer::link($newcourseurl, '<i class="fa fa-plus me-1" aria-hidden="true"></i>' .
            get_string('createcourse', 'local_oriskill'), ['class' => 'btn btn-light']) : ''),
    'oriskill-hello');

// The period: money collected, new customers, what happened (for the roles that see the sales).
if ($can('/local/oriskill/sales.php')) {
    [$from, $to, $pfrom, $pto] = st::period($creator, $period);
    $movements = st::movements($creator, $from, $to);
    $totals = st::totals($movements);
    $previous = st::totals(st::movements($creator, $pfrom, $pto));
    $main = st::currency();
    $tabs = '';
    foreach (st::PERIODS as $key) {
        $tabs .= html_writer::tag('li', html_writer::link(new moodle_url($url, ['period' => $key]),
            get_string('stats:period_' . $key, 'local_oriskill'), ['class' => 'nav-link' . ($key === $period ? ' active' : ''),
            'aria-current' => $key === $period ? 'page' : null]), ['class' => 'nav-item']);
    }
    echo html_writer::tag('ul', $tabs, ['class' => 'nav nav-pills mb-3']);

    // Difference with the period before, in the main currency.
    $delta = function(float $now, float $before, bool $money) use ($main): string {
        $diff = round($now - $before, 2);
        $text = ($diff > 0 ? '+' : ($diff < 0 ? '−' : '')) . ($money ? pricing_service::format_price(abs($diff), $main)
            : abs($diff));
        return html_writer::span(get_string('stats:delta', 'local_oriskill', $text),
            'small ' . ($diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-muted')));
    };
    $amount = (float) ($totals[$main]->amount ?? 0);
    $revenuecard = html_writer::div(
        html_writer::div(html_writer::tag('h3', get_string('stats:revenue_' . $period, 'local_oriskill'), ['class' => 'h6 mb-1']) .
            html_writer::div(st::money(array_map(fn($t) => $t->amount, $totals)), 'oriskill-stat-value') .
            html_writer::div($delta($amount, (float) ($previous[$main]->amount ?? 0), true) . ' · ' .
                get_string('stats:sales', 'local_oriskill', array_sum(array_map(fn($t) => $t->count, $totals))), 'mb-2')) .
        st::chart(st::series($creator, $movements, $from, $to), get_string('stats:revenue_' . $period, 'local_oriskill')) .
        ($can('/local/oriskill/stats.php') ? html_writer::div(html_writer::link(new moodle_url('/local/oriskill/stats.php'),
            get_string('stats:alltransactions', 'local_oriskill')), 'small mt-2') : ''),
        'oriskill-card');
    $new = count(st::new_customers($creator, $from, $to));
    $contactscount = $DB->count_records('local_oriskill_contacts', ['creatorid' => $creator->id]);
    $customerscard = html_writer::div(
        html_writer::tag('h3', get_string('stats:newcustomers_' . $period, 'local_oriskill'), ['class' => 'h6 mb-1']) .
        html_writer::div((string) $new, 'oriskill-stat-value') .
        html_writer::div($delta($new, count(st::new_customers($creator, $pfrom, $pto)), false), 'mb-2') .
        ($can('/local/oriskill/contacts.php') ? html_writer::div(html_writer::link(new moodle_url('/local/oriskill/contacts.php',
            ['view' => 'customers']), get_string('stats:seecustomers', 'local_oriskill')), 'small mb-3') : '') .
        html_writer::div(get_string('stats:contacts', 'local_oriskill', $contactscount) . ' · ' .
            get_string('stats:members', 'local_oriskill', array_sum($learners)), 'small text-muted'),
        'oriskill-card');
    echo html_writer::div($revenuecard . $customerscard, 'oriskill-grid-2 mb-3', ['style' => 'grid-template-columns: 2fr 1fr']);

    // What happened in the period, each line opening the contact.
    $contactids = $DB->get_records_menu('local_oriskill_contacts', ['creatorid' => $creator->id], '', 'userid, id');
    $rows = '';
    foreach (st::activity($creator, $movements, $from, $to, 12) as $event) {
        $text = s($event->text);
        if (isset($contactids[$event->userid]) && $can('/local/oriskill/contact.php')) {
            $text = html_writer::link(new moodle_url('/local/oriskill/contact.php', ['id' => $contactids[$event->userid]]), $text);
        }
        $rows .= html_writer::tag('li',
            html_writer::div(html_writer::div('<i class="fa ' . $event->icon . ' me-2 ' .
                ($event->tone === 'good' ? 'text-success' : ($event->tone === 'bad' ? 'text-danger' : 'text-muted')) .
                '" aria-hidden="true"></i>' . $text, 'oriskill-row-title') .
                html_writer::div(userdate($event->time, get_string('strftimedatetimeshort', 'langconfig')), 'oriskill-row-sub'),
                'oriskill-row-main'));
    }
    echo html_writer::div(html_writer::tag('h3', get_string('stats:activity', 'local_oriskill')) .
        ($rows ? html_writer::tag('ul', $rows, ['class' => 'oriskill-rows'])
            : html_writer::div('<i class="fa fa-clock" aria-hidden="true"></i>' . get_string('stats:noactivity', 'local_oriskill'),
                'oriskill-empty')), 'oriskill-card mb-4');
}

// Key figures.
$stats = '';
foreach ([
    ['fa-graduation-cap', count($courses), get_string('stat:courses', 'local_oriskill', $onsale)],
    ['fa-user-graduate', array_sum($learners), get_string('stat:learners', 'local_oriskill')],
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
            ($isdone || !$linkallowed($link) ? ''
                : html_writer::div(html_writer::link($link, get_string($key . '_action', 'local_oriskill')), 'small'))),
        ['class' => $isdone ? 'done' : '']);
}
$percent = (int) round(100 * $done / count($steps));
$stepscard = html_writer::tag('h3', get_string('dashboard:steps', 'local_oriskill', (object) ['done' => $done,
        'total' => count($steps)])) .
    html_writer::div(html_writer::span('', '', ['style' => "width: {$percent}%"]), 'oriskill-progress',
        ['role' => 'progressbar', 'aria-valuenow' => $percent, 'aria-valuemin' => 0, 'aria-valuemax' => 100]) .
    html_writer::tag('ul', $items, ['class' => 'oriskill-steps']);

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
$coursescard = html_writer::tag('h3', get_string('mycourses', 'local_oriskill')) .
    ($rows ? html_writer::tag('ul', $rows, ['class' => 'oriskill-rows']) .
        ($can('/local/oriskill/courses.php') ? html_writer::div(html_writer::link(new moodle_url('/local/oriskill/courses.php'),
            get_string('seeall', 'local_oriskill')), 'mt-2 small') : '')
    : html_writer::div('<i class="fa fa-graduation-cap" aria-hidden="true"></i>' .
        get_string('nocoursesyet', 'local_oriskill', $newcourseurl->out(false)), 'oriskill-empty'));
echo html_writer::div(html_writer::div($stepscard, 'oriskill-card') . html_writer::div($coursescard, 'oriskill-card'),
    'oriskill-grid-2');

echo creator_service::space_end();
echo $OUTPUT->footer();
