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
 * Statistics of one order form: visitors, order steps, paid orders, revenue, per offer and per day.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\creator_service;
use local_oriskill\formstats_service;

$id = required_param('id', PARAM_INT);
$days = optional_param('days', 30, PARAM_INT);
$days = in_array($days, [7, 30, 90], true) ? $days : 30;
$url = new moodle_url('/local/oriskill/orderformstats.php', ['id' => $id, 'days' => $days]);
$creator = creator_service::require_current_creator($url, 'menu:orderforms');
$form = $DB->get_record('local_oriskill_orderforms', ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
$figures = formstats_service::figures($form, $days);
$currency = pricing_service::get_currency_config()->currency;
$money = fn(float $value) => pricing_service::format_price($value, $currency);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/orderforms.php', format_string($form->title));
echo html_writer::div(html_writer::link(new moodle_url('/local/oriskill/orderforms.php'),
    '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' . get_string('menu:orderforms', 'local_oriskill')), 'mb-3');

// Period.
$periods = '';
foreach ([7, 30, 90] as $option) {
    $periods .= html_writer::link(new moodle_url($url, ['days' => $option]),
        get_string('stats:days', 'local_oriskill', $option),
        ['class' => 'btn btn-sm ' . ($option === $days ? 'btn-primary' : 'btn-outline-secondary')]) . ' ';
}
echo html_writer::div($periods, 'mb-3');

// Key figures.
$stats = '';
foreach ([
    ['fa-eye', $figures->views, get_string('stats:views', 'local_oriskill')],
    ['fa-cart-arrow-down', $figures->checkouts, get_string('stats:checkouts', 'local_oriskill')],
    ['fa-check', $figures->orders, get_string('stats:orders', 'local_oriskill')],
    ['fa-percent', format_float($figures->conversion, 1, true, true) . ' %', get_string('stats:conversion', 'local_oriskill')],
    ['fa-euro-sign', $money($figures->revenue), get_string('stats:revenue', 'local_oriskill')],
] as [$icon, $value, $label]) {
    $stats .= html_writer::div(
        html_writer::span('<i class="fa ' . $icon . '" aria-hidden="true"></i>', 'oriskill-stat-icon') .
        html_writer::div(html_writer::div(s($value), 'oriskill-stat-value') . html_writer::div($label, 'oriskill-stat-label')),
        'oriskill-card oriskill-stat');
}
echo html_writer::div($stats, 'oriskill-stats');

// Per offer.
$table = new html_table();
$table->attributes['class'] = 'table align-middle';
$table->head = [get_string('orderform:offer', 'local_oriskill'), get_string('stats:checkouts', 'local_oriskill'),
    get_string('stats:orders', 'local_oriskill'), get_string('stats:instalments', 'local_oriskill'),
    get_string('stats:revenue', 'local_oriskill')];
foreach ($figures->offers as $offer) {
    $table->data[] = [s($offer->label), $offer->checkouts, $offer->orders, $offer->instalments, $money($offer->revenue)];
}
$extras = html_writer::div(get_string('stats:extras', 'local_oriskill', (object) ['bumps' => $figures->bumps,
    'orders' => $figures->orders, 'upsells' => $figures->upsells, 'upsellrevenue' => $money($figures->upsellrevenue)]),
    'small text-muted');
echo html_writer::div(html_writer::tag('h3', get_string('stats:peroffer', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::table($table) . $extras, 'oriskill-card mb-4');

// Per day, as bars.
$max = max(1, max(array_map(fn($d) => $d->views, $figures->days)));
$rows = '';
foreach (array_reverse($figures->days, true) as $day => $d) {
    $date = DateTime::createFromFormat('Ymd', (string) $day)->getTimestamp();
    $rows .= html_writer::tag('tr',
        html_writer::tag('td', userdate($date, get_string('strftimedaydate', 'langconfig')), ['class' => 'small text-nowrap']) .
        html_writer::tag('td', html_writer::div(html_writer::span('', '', ['style' => 'width: ' .
            round(100 * $d->views / $max) . '%']), 'oriskill-progress'), ['style' => 'width: 50%']) .
        html_writer::tag('td', $d->views, ['class' => 'text-end']) .
        html_writer::tag('td', $d->checkouts, ['class' => 'text-end']) .
        html_writer::tag('td', $d->orders, ['class' => 'text-end fw-semibold']));
}
echo html_writer::div(html_writer::tag('h3', get_string('stats:perday', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('table', html_writer::tag('thead', html_writer::tag('tr',
        html_writer::tag('th', get_string('date')) . html_writer::tag('th', '') .
        html_writer::tag('th', get_string('stats:views', 'local_oriskill'), ['class' => 'text-end']) .
        html_writer::tag('th', get_string('stats:checkouts', 'local_oriskill'), ['class' => 'text-end']) .
        html_writer::tag('th', get_string('stats:orders', 'local_oriskill'), ['class' => 'text-end']))) .
        html_writer::tag('tbody', $rows), ['class' => 'table table-sm align-middle']) .
    html_writer::div(get_string('stats:note', 'local_oriskill'), 'small text-muted'), 'oriskill-card');

echo creator_service::space_end();
echo $OUTPUT->footer();
