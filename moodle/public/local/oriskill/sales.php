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
 * Trainer's transactions: every payment (each instalment), refusal and refund, with filters, CSV export and a manual sale.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\affiliate_service;
use local_oriskill\creator_service;
use local_oriskill\stats_service as st;
use local_oriskill\team_service;
use local_oriskill\transaction_service as ts;

$url = new moodle_url('/local/oriskill/sales.php');
$creator = creator_service::require_current_creator($url, 'sales');
$timezone = st::timezone($creator);

if (($markpaid = optional_param('markpaid', 0, PARAM_INT)) && confirm_sesskey()) {
    // A trainer can only settle the commissions owed on their own courses.
    affiliate_service::mark_paid($markpaid, (int) $creator->id);
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Filters: the last 30 days by default.
$day = fn(string $date, bool $end) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    ? (int) (new DateTime($date . ($end ? ' 23:59:59' : ' 00:00:00'), core_date::get_user_timezone_object($timezone)))
        ->getTimestamp() : 0;
[$defaultfrom] = st::period($creator, '30d');
$filters = [
    'fromdate' => optional_param('from', userdate($defaultfrom, '%Y-%m-%d', $timezone, false), PARAM_RAW_TRIMMED),
    'todate' => optional_param('to', '', PARAM_RAW_TRIMMED),
    'type' => optional_param('type', '', PARAM_ALPHA),
    'search' => trim(optional_param('q', '', PARAM_TEXT)),
    'product' => trim(optional_param('product', '', PARAM_TEXT)),
    'method' => trim(optional_param('method', '', PARAM_TEXT)),
];
$filters['type'] = in_array($filters['type'], ts::TYPES, true) ? $filters['type'] : '';
$filters['from'] = $day($filters['fromdate'], false);
$filters['to'] = $day($filters['todate'], true) ?: time();
$perpage = optional_param('perpage', 25, PARAM_INT);
$perpage = in_array($perpage, ts::PERPAGE, true) ? $perpage : 25;
$page = optional_param('page', 0, PARAM_INT);
$params = array_filter(['from' => $filters['fromdate'], 'to' => $filters['todate'], 'type' => $filters['type'],
    'q' => $filters['search'], 'product' => $filters['product'], 'method' => $filters['method']], fn($v) => $v !== '');
$params['from'] = $filters['fromdate'];

// Products and payment methods of the period, for the filters.
$period = ts::rows($creator, $filters['from'], $filters['to']);
$products = [];
$methods = [];
foreach ($period as $row) {
    $products += array_fill_keys(array_keys($row->lines), true);
    $methods[$row->method] = true;
}
ksort($products);
ksort($methods);
$rows = ts::search($creator, $filters, $period);

if (optional_param('download', '', PARAM_ALPHA) === 'csv') {
    team_service::export_allowed($creator, get_string('sales', 'local_oriskill'));
    csv_export_writer::download_array(clean_filename(get_string('sales', 'local_oriskill') . '-' . date('Y-m-d')),
        ts::csv($rows, $timezone));
    exit;
}

$actions = '';
if (team_service::can_export() && $rows) {
    $actions .= html_writer::link(new moodle_url($url, $params + ['download' => 'csv']),
        '<i class="fa fa-download me-1" aria-hidden="true"></i>' . get_string('transactions:export', 'local_oriskill'),
        ['class' => 'btn btn-outline-primary']);
}
if (team_service::can(creator_service::current_role(), '/local/oriskill/document.php')) {
    $actions .= ' ' . html_writer::link(new moodle_url('/local/oriskill/document.php', ['type' => 'invoice']),
        '<i class="fa fa-plus me-1" aria-hidden="true"></i>' . get_string('transactions:manual', 'local_oriskill'),
        ['class' => 'btn btn-outline-primary', 'title' => get_string('transactions:manual_help', 'local_oriskill')]);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/sales.php', get_string('sales', 'local_oriskill'), $actions);
echo html_writer::tag('p', get_string('sales_intro', 'local_oriskill'), ['class' => 'text-muted']);

// Totals of the filtered transactions.
$totals = ts::totals($rows);
$stat = fn(string $value, string $label) => html_writer::div(html_writer::div(html_writer::div($value, 'oriskill-stat-value') .
    html_writer::div($label, 'oriskill-stat-label')), 'oriskill-card oriskill-stat');
echo html_writer::div(
    $stat(st::money(array_map(fn($t) => $t->amount, $totals)), get_string('transactions:total', 'local_oriskill',
        array_sum(array_map(fn($t) => $t->count, $totals)))) .
    $stat(st::money(array_map(fn($t) => $t->refunds, $totals)), get_string('transactions:refunds', 'local_oriskill')) .
    $stat((string) array_sum(array_map(fn($t) => $t->failed, $totals)), get_string('transactions:failedcount', 'local_oriskill')),
    'oriskill-stats mb-3');

// Filters.
$select = fn(string $name, array $options, string $value, string $label) => html_writer::div(
    html_writer::tag('label', $label, ['for' => 'f-' . $name, 'class' => 'form-label small mb-1']) .
    html_writer::select($options, $name, $value, ['' => get_string('all')], ['class' => 'form-select form-select-sm',
        'id' => 'f-' . $name]));
$date = fn(string $name, string $value, string $label) => html_writer::div(
    html_writer::tag('label', $label, ['for' => 'f-' . $name, 'class' => 'form-label small mb-1']) .
    html_writer::empty_tag('input', ['type' => 'date', 'name' => $name, 'id' => 'f-' . $name, 'value' => $value,
        'class' => 'form-control form-control-sm']));
echo html_writer::tag('form',
    html_writer::div(
        html_writer::div(html_writer::tag('label', get_string('search'), ['for' => 'f-q', 'class' => 'form-label small mb-1']) .
            html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'id' => 'f-q', 'value' => $filters['search'],
                'class' => 'form-control form-control-sm',
                'placeholder' => get_string('transactions:search', 'local_oriskill')])) .
        $date('from', $filters['fromdate'], get_string('transactions:from', 'local_oriskill')) .
        $date('to', $filters['todate'], get_string('transactions:to', 'local_oriskill')) .
        $select('type', array_combine(ts::TYPES, array_map(fn($t) => get_string('transactions:type_' . $t, 'local_oriskill'),
            ts::TYPES)), $filters['type'], get_string('status')) .
        $select('product', array_combine(array_keys($products), array_keys($products)), $filters['product'],
            get_string('stats:product', 'local_oriskill')) .
        $select('method', array_combine(array_keys($methods), array_keys($methods)), $filters['method'],
            get_string('transactions:method', 'local_oriskill')) .
        html_writer::div(html_writer::tag('button', get_string('transactions:apply', 'local_oriskill'),
            ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']) . ' ' .
            html_writer::link(new moodle_url($url, ['from' => '']), get_string('transactions:alltime', 'local_oriskill'),
                ['class' => 'btn btn-sm btn-link']), 'align-self-end'),
        'oriskill-filters d-flex flex-wrap gap-2'),
    ['method' => 'get', 'action' => $url->out(false), 'class' => 'oriskill-card mb-3']);

if (!$rows) {
    echo html_writer::div('<i class="fa fa-receipt" aria-hidden="true"></i>' . get_string('nosales', 'local_oriskill'),
        'oriskill-card oriskill-empty');
} else {
    echo html_writer::div(
        html_writer::div(html_writer::span(get_string('transactions:count', 'local_oriskill', count($rows)), 'fw-semibold') .
            html_writer::tag('form', html_writer::input_hidden_params(new moodle_url($url, $params)) .
                html_writer::select(array_combine(ts::PERPAGE, array_map(fn($n) => get_string('transactions:perpage',
                    'local_oriskill', $n), ts::PERPAGE)), 'perpage', $perpage, false, ['class' => 'form-select form-select-sm',
                    'onchange' => 'this.form.submit()', 'aria-label' => get_string('transactions:perpage', 'local_oriskill', '')]),
                ['method' => 'get', 'action' => $url->out(false)]), 'd-flex justify-content-between align-items-center mb-2') .
        ts::table(array_slice($rows, $page * $perpage, $perpage), $creator, $timezone), 'oriskill-card table-responsive');
    echo $OUTPUT->paging_bar(count($rows), $page, $perpage, new moodle_url($url, $params + ['perpage' => $perpage]));
}

// Commissions owed to the affiliates who brought these sales (single rate, without campaigns).
$commissions = affiliate_service::commissions(['sellerid' => $creator->id]);
if ($commissions) {
    echo $OUTPUT->heading(get_string('commissionsowed', 'local_oriskill'), 3, 'mt-5');
    echo html_writer::tag('p', get_string('commissionsowed_intro', 'local_oriskill'));
    $table = new html_table();
    $table->head = [get_string('date'), get_string('ordercol', 'local_oriskill'), get_string('affiliatecol', 'local_oriskill'),
        get_string('payoutdetails', 'local_oriskill'), get_string('course'), get_string('commissioncol', 'local_oriskill'),
        get_string('status'), ''];
    foreach ($commissions as $commission) {
        $due = $commission->status === 'approved';
        $table->data[] = [
            userdate($commission->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            s($commission->ordernumber),
            fullname($commission),
            $due ? format_text($commission->payoutdetails ?? '', FORMAT_PLAIN) : '',
            format_string($commission->itemname),
            pricing_service::format_price($commission->amount, $commission->currency)
                . ' <small class="text-muted">(' . format_float($commission->rate, 2, true, true) . ' %)</small>',
            get_string('commission:' . $commission->status, 'local_oriskill'),
            $due ? $OUTPUT->single_button(new moodle_url($url, ['markpaid' => $commission->id, 'sesskey' => sesskey()]),
                get_string('markpaid', 'local_oriskill'), 'post', ['type' => 'primary']) : '',
        ];
    }
    echo html_writer::table($table);
}
echo creator_service::space_end();
echo $OUTPUT->footer();
