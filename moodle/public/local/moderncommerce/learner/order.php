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
 * Learner order detail page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\learner_shell;

require_login();

$orderid = required_param('id', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/order.php', ['id' => $orderid]));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('orderdetails', 'local_moderncommerce'));
$PAGE->set_heading(get_string('orderdetails', 'local_moderncommerce'));

$shell = learner_shell::create('orders');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_order',
    'id' => 'moderncommerce-learner-order-app',
    'class' => 'local-moderncommerce-learner-order',
    'props' => [
        'methodName' => 'local_moderncommerce_get_learner_order',
        'orderId' => $orderid,
        'layout' => $shell->get_react_layout_context(),
        'labels' => [
            'actions' => get_string('actions', 'local_moderncommerce'),
            'address' => get_string('address', 'local_moderncommerce'),
            'backtoorders' => get_string('backtoorders', 'local_moderncommerce'),
            'billingdetails' => get_string('billingdetails', 'local_moderncommerce'),
            'browsecatalog' => get_string('browsecatalog', 'local_moderncommerce'),
            'cancel' => get_string('cancel', 'local_moderncommerce'),
            'cancelorder' => get_string('cancelorder', 'local_moderncommerce'),
            'cancelorderconfirmmessage' => get_string('cancelorderconfirmmessage', 'local_moderncommerce'),
            'cancelordererror' => get_string('cancelordererror', 'local_moderncommerce'),
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
            'noorderitems' => get_string('noorderitems', 'local_moderncommerce'),
            'notavailable' => get_string('notavailable', 'local_moderncommerce'),
            'ordercancelledsuccess' => get_string('ordercancelledsuccess', 'local_moderncommerce'),
            'orderdate' => get_string('orderdate', 'local_moderncommerce'),
            'orderdetails' => get_string('orderdetails', 'local_moderncommerce'),
            'orderitems' => get_string('orderitems', 'local_moderncommerce'),
            'ordernumber' => get_string('ordernumber', 'local_moderncommerce'),
            'orderpending' => get_string('orderpending', 'local_moderncommerce'),
            'payment' => get_string('payment', 'local_moderncommerce'),
            'paymentdetails' => get_string('paymentdetails', 'local_moderncommerce'),
            'paymentmethod' => get_string('paymentmethod', 'local_moderncommerce'),
            'phone' => get_string('phone', 'local_moderncommerce'),
            'price' => get_string('price', 'local_moderncommerce'),
            'processing' => get_string('processing', 'local_moderncommerce'),
            'quantity' => get_string('quantity', 'local_moderncommerce'),
            'receipt' => get_string('receipt', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'subtotal' => get_string('subtotal', 'local_moderncommerce'),
            'tax' => get_string('tax', 'local_moderncommerce'),
            'total' => get_string('total', 'local_moderncommerce'),
            'transactionid' => get_string('transactionid', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-order',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-receipt',
]);

echo $OUTPUT->header();
echo $shell->set_content($contenthtml)
    ->add_breadcrumb(
        get_string('myorders', 'local_moderncommerce'),
        (new moodle_url('/local/moderncommerce/learner/orders.php'))->out(false)
    )
    ->add_breadcrumb(get_string('orderdetails', 'local_moderncommerce'), '', true)
    ->render($OUTPUT);
echo $OUTPUT->footer();
