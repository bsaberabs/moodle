<?php
// This file is part of Moodle and is licensed under the
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
 * Legacy buyer order detail route.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$orderid = required_param('id', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/order.php', ['id' => $orderid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('orderdetails', 'local_moderncommerce'));
$PAGE->set_heading(get_string('orderdetails', 'local_moderncommerce'));

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_order',
    'id' => 'moderncommerce-order-app',
    'class' => 'local-moderncommerce-order',
    'props' => [
        'methodName' => 'local_moderncommerce_get_learner_order',
        'orderId' => $orderid,
        'labels' => [
            'actions' => get_string('actions', 'local_moderncommerce'),
            'backtoorders' => get_string('backtoorders', 'local_moderncommerce'),
            'billingdetails' => get_string('billingdetails', 'local_moderncommerce'),
            'browsecatalog' => get_string('browsecatalog', 'local_moderncommerce'),
            'cancelorder' => get_string('cancelorder', 'local_moderncommerce'),
            'couponcode' => get_string('couponcode', 'local_moderncommerce'),
            'continuepayment' => get_string('continuepayment', 'local_moderncommerce'),
            'customer' => get_string('customer', 'local_moderncommerce'),
            'discount' => get_string('discount', 'local_moderncommerce'),
            'downloadinvoice' => get_string('downloadinvoice', 'local_moderncommerce'),
            'downloadreceipt' => get_string('downloadreceipt', 'local_moderncommerce'),
            'email' => get_string('email', 'local_moderncommerce'),
            'invoice' => get_string('invoice', 'local_moderncommerce'),
            'item' => get_string('item', 'local_moderncommerce'),
            'items' => get_string('items', 'local_moderncommerce'),
            'linetotal' => get_string('linetotal', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'orderdetails' => get_string('orderdetails', 'local_moderncommerce'),
            'orderitems' => get_string('orderitems', 'local_moderncommerce'),
            'ordernumber' => get_string('ordernumber', 'local_moderncommerce'),
            'payment' => get_string('payment', 'local_moderncommerce'),
            'paymentmethod' => get_string('paymentmethod', 'local_moderncommerce'),
            'phone' => get_string('phone', 'local_moderncommerce'),
            'price' => get_string('price', 'local_moderncommerce'),
            'quantity' => get_string('quantity', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'subtotal' => get_string('subtotal', 'local_moderncommerce'),
            'tax' => get_string('tax', 'local_moderncommerce'),
            'total' => get_string('total', 'local_moderncommerce'),
            'transactionid' => get_string('transactionid', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moderncommerce/react_mount', [
    'region' => 'moderncommerce-order',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-receipt',
]);
echo $OUTPUT->footer();
