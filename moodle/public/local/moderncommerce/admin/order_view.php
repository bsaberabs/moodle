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
 * Admin order detail view (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

$orderid = required_param('id', PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:viewallorders', $context);

$order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid], 'id, ordernumber', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/order_view.php', ['id' => $orderid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('order', 'local_moderncommerce') . ' #' . $order->ordernumber);

$statusoptions = [];
foreach (['pending', 'processing', 'paid', 'completed', 'failed', 'cancelled', 'refunded'] as $status) {
    $statusoptions[] = ['value' => $status, 'label' => get_string('status_' . $status, 'local_moderncommerce')];
}

$orderdetailreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/order_detail',
    'id' => 'moderncommerce-order-detail-app',
    'class' => 'local-moderncommerce-order-detail',
    'props' => [
        'orderId' => $orderid,
        'getMethodName' => 'local_moderncommerce_admin_get_order',
        'updateStatusMethodName' => 'local_moderncommerce_admin_update_order_status',
        'createRefundMethodName' => 'local_moderncommerce_admin_create_refund',
        'ordersUrl' => (new moodle_url('/local/moderncommerce/admin/orders.php'))->out(false),
        'statusOptions' => $statusoptions,
        'labels' => [
            'orderdetails' => get_string('orderdetails', 'local_moderncommerce'),
            'ordersummary' => get_string('ordersummary', 'local_moderncommerce'),
            'customer' => get_string('customer', 'local_moderncommerce'),
            'billingdetails' => get_string('billingdetails', 'local_moderncommerce'),
            'items' => get_string('items', 'local_moderncommerce'),
            'transactions' => get_string('transactions', 'local_moderncommerce'),
            'refunds' => get_string('refunds', 'local_moderncommerce'),
            'notes' => get_string('notes', 'local_moderncommerce'),
            'customernotes' => get_string('customernotes', 'local_moderncommerce'),
            'adminnotes' => get_string('adminnotes', 'local_moderncommerce'),
            'name' => get_string('name'),
            'type' => get_string('type', 'local_moderncommerce'),
            'sku' => get_string('sku', 'local_moderncommerce'),
            'quantity' => get_string('quantity', 'local_moderncommerce'),
            'unitprice' => get_string('unitprice', 'local_moderncommerce'),
            'subtotal' => get_string('subtotal', 'local_moderncommerce'),
            'discount' => get_string('discount', 'local_moderncommerce'),
            'tax' => get_string('tax', 'local_moderncommerce'),
            'fees' => get_string('fees', 'local_moderncommerce'),
            'total' => get_string('total', 'local_moderncommerce'),
            'refundedtotal' => get_string('refundedtotal', 'local_moderncommerce'),
            'coupon' => get_string('coupon', 'local_moderncommerce'),
            'paymentmethod' => get_string('paymentmethod', 'local_moderncommerce'),
            'orderdate' => get_string('orderdate', 'local_moderncommerce'),
            'paidon' => get_string('paidon', 'local_moderncommerce'),
            'completedon' => get_string('completedon', 'local_moderncommerce'),
            'refundedon' => get_string('refundedon', 'local_moderncommerce'),
            'email' => get_string('email'),
            'phone' => get_string('phone', 'local_moderncommerce'),
            'address' => get_string('address', 'local_moderncommerce'),
            'city' => get_string('city', 'local_moderncommerce'),
            'state' => get_string('state', 'local_moderncommerce'),
            'country' => get_string('country'),
            'zipcode' => get_string('zipcode', 'local_moderncommerce'),
            'reference' => get_string('reference', 'local_moderncommerce'),
            'gateway' => get_string('gateway', 'local_moderncommerce'),
            'amount' => get_string('amount', 'local_moderncommerce'),
            'status' => get_string('status'),
            'date' => get_string('date', 'local_moderncommerce'),
            'changestatus' => get_string('changestatus', 'local_moderncommerce'),
            'updatestatus' => get_string('updatestatus', 'local_moderncommerce'),
            'statusupdated' => get_string('orderstatusupdated', 'local_moderncommerce'),
            'refund' => get_string('refund', 'local_moderncommerce'),
            'processrefund' => get_string('processrefund', 'local_moderncommerce'),
            'refundamount' => get_string('refundamount', 'local_moderncommerce'),
            'refundreason' => get_string('refundreason', 'local_moderncommerce'),
            'refundtype' => get_string('refundtype', 'local_moderncommerce'),
            'fullrefund' => get_string('fullrefund', 'local_moderncommerce'),
            'partialrefund' => get_string('partialrefund', 'local_moderncommerce'),
            'unenrolonrefund' => get_string('unenrolonrefund', 'local_moderncommerce'),
            'confirmrefund' => get_string('confirmrefund', 'local_moderncommerce'),
            'refundcreated' => get_string('refundcreated', 'local_moderncommerce'),
            'norefunds' => get_string('norefunds', 'local_moderncommerce'),
            'notransactions' => get_string('notransactions', 'local_moderncommerce'),
            'noitems' => get_string('noitems', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'cancel' => get_string('cancel'),
            'save' => get_string('save'),
            'backtoorders' => get_string('backtoorders', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/order_view', [
    'orderdetailreactconfig' => $orderdetailreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'link',
        'url' => new moodle_url('/local/moderncommerce/admin/orders.php'),
        'label' => get_string('backtoorders', 'local_moderncommerce'),
        'icon' => 'bi-arrow-left',
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'orders',
    get_string('order', 'local_moderncommerce') . ' #' . $order->ordernumber,
    $contenthtml,
    get_string('orderdetails', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
