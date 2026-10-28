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
 * Trainer's sales: paid into their own SecureAndPay account.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\creator_service;
use local_oriskill\seller_service;

$url = new moodle_url('/local/oriskill/sales.php');
$creator = creator_service::require_current_creator($url, 'sales');
$sales = seller_service::sales($creator);

$totals = [];
$table = new html_table();
$table->head = [get_string('date'), get_string('ordercol', 'local_oriskill'), get_string('buyercol', 'local_oriskill'),
    get_string('course'), get_string('amountcol', 'local_oriskill'), get_string('status')];
foreach ($sales as $sale) {
    $refunded = $sale->status === 'refunded';
    if (!$refunded) {
        $totals[$sale->currency] = ($totals[$sale->currency] ?? 0) + (float) $sale->total;
    }
    $table->data[] = [
        userdate($sale->timepaid, get_string('strftimedatetimeshort', 'langconfig')),
        s($sale->ordernumber),
        s($sale->firstname . ' ' . $sale->lastname),
        format_string($sale->itemname),
        pricing_service::format_price($sale->total, $sale->currency),
        get_string($refunded ? 'salerefunded' : 'salepaid', 'local_oriskill'),
    ];
}

echo $OUTPUT->header();
echo creator_service::space_menu($creator, '/local/oriskill/sales.php');
echo html_writer::tag('p', get_string('sales_intro', 'local_oriskill'));
if ($sales) {
    foreach ($totals as $currency => $total) {
        echo html_writer::tag('p', get_string('salestotal', 'local_oriskill', (object) ['count' => count($sales),
            'total' => pricing_service::format_price($total, $currency)]), ['class' => 'lead']);
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nosales', 'local_oriskill'), 'info');
}
echo $OUTPUT->footer();
