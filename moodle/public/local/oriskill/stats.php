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
 * Sales statistics of the trainer: totals (since the start, year, month, still due this month), the last 30 days,
 * month by month, and a breakdown by product, payment method and country.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\creator_service;
use local_oriskill\stats_service as st;
use local_oriskill\transaction_service;

$url = new moodle_url('/local/oriskill/stats.php');
$creator = creator_service::require_current_creator($url, 'menu:stats');
$timezone = st::timezone($creator);
$now = time();
$thisyear = (int) userdate($now, '%Y', $timezone);
$year = optional_param('year', $thisyear, PARAM_INT);
$month = optional_param('month', 0, PARAM_INT);
$monthstart = fn(int $y, int $m) => make_timestamp($y, $m, 1, 0, 0, 0, $timezone);

// Sums of a period from the transactions: currency => {amount, count, refunds}.
$all = array_filter(transaction_service::rows($creator, 0, $now, false), fn($t) => $t->type !== 'failed');
$sums = fn(int $from, int $to): array => transaction_service::totals(array_filter($all,
    fn($t) => $t->time >= $from && $t->time < $to));
$amounts = fn(array $sums, string $field = 'amount') => array_map(fn($s) => $s->$field, $sums);
$count = fn(array $sums) => array_sum(array_map(fn($s) => $s->count, $sums));

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/stats.php', get_string('menu:stats', 'local_oriskill'));
echo html_writer::tag('p', get_string('stats:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Totals.
$m = (int) userdate($now, '%m', $timezone);
$cards = [
    [get_string('stats:total', 'local_oriskill'), $sums(0, $now + 1)],
    [get_string('stats:year', 'local_oriskill', $thisyear), $sums($monthstart($thisyear, 1), $now + 1)],
    [get_string('stats:year', 'local_oriskill', $thisyear - 1), $sums($monthstart($thisyear - 1, 1), $monthstart($thisyear, 1))],
    [get_string('stats:month', 'local_oriskill', userdate($now, '%B %Y', $timezone)), $sums($monthstart($thisyear, $m), $now + 1)],
    [get_string('stats:month', 'local_oriskill', userdate(strtotime('-1 month', $monthstart($thisyear, $m)), '%B %Y', $timezone)),
        $sums(strtotime('-1 month', $monthstart($thisyear, $m)), $monthstart($thisyear, $m))],
];
$tiles = '';
foreach ($cards as [$label, $s]) {
    $tiles .= html_writer::div(html_writer::div(
        html_writer::div(st::money($amounts($s)), 'oriskill-stat-value') .
        html_writer::div($label . ' · ' . get_string('stats:sales', 'local_oriskill', $count($s)), 'oriskill-stat-label')),
        'oriskill-card oriskill-stat');
}
$tiles .= html_writer::div(html_writer::div(
    html_writer::div(st::money(st::due_this_month($creator, $now)), 'oriskill-stat-value') .
    html_writer::div(get_string('stats:due', 'local_oriskill'), 'oriskill-stat-label')), 'oriskill-card oriskill-stat');
echo html_writer::div($tiles, 'oriskill-stats mb-4');

// The last 30 days.
[$from, $to] = st::period($creator, '30d', $now);
$movements = st::movements($creator, $from, $to);
echo html_writer::div(html_writer::tag('h3', get_string('stats:last30', 'local_oriskill'), ['class' => 'h5']) .
    st::chart(st::series($creator, $movements, $from, $to), get_string('stats:last30', 'local_oriskill')), 'oriskill-card mb-4');

// Month by month.
$years = [];
$first = $all ? min(array_map(fn($t) => $t->time, $all)) : 0;
for ($y = $thisyear; $y >= ($first ? (int) userdate($first, '%Y', $timezone) : $thisyear); $y--) {
    $years[$y] = $y;
}
$year = isset($years[$year]) ? $year : $thisyear;
$rows = [];
$yeartotal = [];
for ($mm = 1; $mm <= 12; $mm++) {
    $start = $monthstart($year, $mm);
    if ($start > $now) {
        break;
    }
    $s = $sums($start, strtotime('+1 month', $start));
    $gross = array_map(fn($x) => $x->amount + $x->refunds, $s);
    $rows[] = [html_writer::link(new moodle_url($url, ['year' => $year, 'month' => $mm], 'breakdown'),
            userdate($start, '%B', $timezone)), $count($s), st::money($gross), st::money($amounts($s, 'refunds')),
        html_writer::tag('strong', st::money($amounts($s)))];
}
$table = new html_table();
$table->attributes['class'] = 'table align-middle';
$table->head = [get_string('stats:monthcol', 'local_oriskill'), get_string('stats:salescount', 'local_oriskill'),
    get_string('stats:gross', 'local_oriskill'), get_string('stats:refunds', 'local_oriskill'),
    get_string('stats:collected', 'local_oriskill')];
$table->data = array_reverse($rows);
echo html_writer::div(
    html_writer::div(html_writer::tag('h3', get_string('stats:bymonth', 'local_oriskill'), ['class' => 'h5 mb-0']) .
        html_writer::tag('form', html_writer::select($years, 'year', $year, false, ['class' => 'form-select form-select-sm',
            'onchange' => 'this.form.submit()', 'aria-label' => get_string('year')]), ['method' => 'get',
            'action' => $url->out(false)]), 'd-flex justify-content-between align-items-center mb-2') .
    html_writer::table($table), 'oriskill-card table-responsive mb-4');

// Breakdown of the year or of a month.
$bfrom = $month ? $monthstart($year, $month) : $monthstart($year, 1);
$bto = $month ? strtotime('+1 month', $bfrom) - 1 : $monthstart($year + 1, 1) - 1;
$bmovements = st::movements($creator, $bfrom, min($bto, $now));
$periods = [0 => get_string('stats:wholeyear', 'local_oriskill', $year)];
for ($mm = 1; $mm <= 12 && $monthstart($year, $mm) <= $now; $mm++) {
    $periods[$mm] = userdate($monthstart($year, $mm), '%B %Y', $timezone);
}
$blocks = '';
foreach (['product', 'method', 'country'] as $by) {
    $rows = st::breakdown($bmovements, $by);
    $max = max(1, ...array_values(array_map(fn($r) => $r->amount, $rows)));
    $items = '';
    foreach (array_slice($rows, 0, 12, true) as $label => $row) {
        $items .= html_writer::tag('li',
            html_writer::div(html_writer::span(s($label), 'small') . html_writer::span(
                pricing_service::format_price(round($row->amount, 2), st::currency()) . ' · ' . $row->count, 'small fw-semibold'),
                'd-flex justify-content-between gap-2') .
            html_writer::div('', '', ['style' => 'height: 8px; border-radius: 0 4px 4px 0; background: #7c3aed; width: ' .
                max(1, round(100 * $row->amount / $max)) . '%']), ['class' => 'mb-2']);
    }
    $blocks .= html_writer::div(html_writer::tag('h4', get_string('stats:by_' . $by, 'local_oriskill'), ['class' => 'h6']) .
        ($items ? html_writer::tag('ul', $items, ['class' => 'list-unstyled mb-0'])
            : html_writer::div(get_string('stats:nothing', 'local_oriskill'), 'small text-muted')), 'oriskill-card');
}
echo html_writer::div(
    html_writer::div(html_writer::tag('h3', get_string('stats:breakdown', 'local_oriskill'), ['class' => 'h5 mb-0']) .
        html_writer::tag('form', html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'year', 'value' => $year]) .
            html_writer::select($periods, 'month', $month, false, ['class' => 'form-select form-select-sm',
                'onchange' => 'this.form.submit()', 'aria-label' => get_string('stats:breakdown', 'local_oriskill')]),
            ['method' => 'get', 'action' => $url->out(false) . '#breakdown']),
        'd-flex justify-content-between align-items-center mb-2', ['id' => 'breakdown']) .
    html_writer::div(get_string('stats:breakdown_help', 'local_oriskill'), 'small text-muted mb-3') .
    html_writer::div($blocks, 'oriskill-grid-3'), 'mb-4');

// The last transactions.
$last = array_slice(transaction_service::search($creator, ['from' => strtotime('-1 year', $now), 'to' => $now]), 0, 10);
echo html_writer::div(html_writer::tag('h3', get_string('stats:lasttransactions', 'local_oriskill'), ['class' => 'h5']) .
    ($last ? transaction_service::table($last, $creator, $timezone) . html_writer::div(html_writer::link(
        new moodle_url('/local/oriskill/sales.php'), get_string('stats:alltransactions', 'local_oriskill')), 'small')
        : html_writer::div(get_string('nosales', 'local_oriskill'), 'text-muted')), 'oriskill-card table-responsive');

echo creator_service::space_end();
echo $OUTPUT->footer();
