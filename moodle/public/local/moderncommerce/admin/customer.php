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
 * Admin customer detail view (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

$customerid = required_param('id', PARAM_INT);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = min(100, max(10, optional_param('perpage', 10, PARAM_INT)));

require_login();
$context = context_system::instance();
require_capability('local/moderncommerce:viewallorders', $context);

$customer = $DB->get_record(
    'user',
    ['id' => $customerid, 'deleted' => 0],
    'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename',
    MUST_EXIST
);
$customername = fullname($customer);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/customer.php', ['id' => $customerid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('customerdetails', 'local_moderncommerce') . ': ' . $customername);

$customerdetailreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/customer_detail',
    'id' => 'moderncommerce-customer-detail-app',
    'class' => 'local-moderncommerce-customer-detail',
    'props' => [
        'customerId' => $customerid,
        'methodName' => 'local_moderncommerce_admin_get_customer',
        'initialPage' => $page,
        'initialPerPage' => $perpage,
        'customersUrl' => (new moodle_url('/local/moderncommerce/admin/customers.php'))->out(false),
        'labels' => [
            'customerdetails' => get_string('customerdetails', 'local_moderncommerce'),
            'customer' => get_string('customer', 'local_moderncommerce'),
            'customerdetails_desc' => get_string('customerdetails_desc', 'local_moderncommerce'),
            'latestbillingdetails' => get_string('latestbillingdetails', 'local_moderncommerce'),
            'orderhistory' => get_string('orderhistory', 'local_moderncommerce'),
            'totalorders' => get_string('totalorders', 'local_moderncommerce'),
            'totalspent' => get_string('totalspent', 'local_moderncommerce'),
            'pendingorders' => get_string('pendingorders', 'local_moderncommerce'),
            'saveditems' => get_string('saveditems', 'local_moderncommerce'),
            'refundedorders' => get_string('refundedorders', 'local_moderncommerce'),
            'email' => get_string('email'),
            'phone' => get_string('phone', 'local_moderncommerce'),
            'city' => get_string('city', 'local_moderncommerce'),
            'state' => get_string('state', 'local_moderncommerce'),
            'country' => get_string('country'),
            'zipcode' => get_string('zipcode', 'local_moderncommerce'),
            'address' => get_string('address', 'local_moderncommerce'),
            'accountcreated' => get_string('accountcreated', 'local_moderncommerce'),
            'firstorder' => get_string('firstorder', 'local_moderncommerce'),
            'lastorder' => get_string('lastorder', 'local_moderncommerce'),
            'status' => get_string('status'),
            'ordernumber' => get_string('ordernumber', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'items' => get_string('items', 'local_moderncommerce'),
            'paymentmethod' => get_string('paymentmethod', 'local_moderncommerce'),
            'total' => get_string('total', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'viewdetails' => get_string('viewdetails', 'local_moderncommerce'),
            'noordersfound' => get_string('noordersfound', 'local_moderncommerce'),
            'nothingtodisplay' => get_string('nothingtodisplay'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'next' => get_string('next'),
            'page' => get_string('page', 'local_moderncommerce'),
            'of' => get_string('of', 'local_moderncommerce'),
            'backtocustomers' => get_string('backtocustomers', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/customer', [
    'customerdetailreactconfig' => $customerdetailreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'link',
        'url' => new moodle_url('/local/moderncommerce/admin/customers.php'),
        'label' => get_string('backtocustomers', 'local_moderncommerce'),
        'icon' => 'bi-arrow-left',
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'customers',
    get_string('customerdetails', 'local_moderncommerce') . ': ' . $customername,
    $contenthtml,
    get_string('customerdetails_desc', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
