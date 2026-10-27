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
 * Admin orders management page (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:viewallorders', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/orders.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('manageorders', 'local_moderncommerce'));

$initialfilters = [
    'search' => optional_param('search', '', PARAM_TEXT),
    'status' => optional_param('status', '', PARAM_ALPHA),
    'page' => optional_param('page', 0, PARAM_INT),
    'perpage' => optional_param('perpage', 10, PARAM_INT),
    'sort' => optional_param('sort', 'timecreated', PARAM_ALPHANUMEXT),
    'direction' => optional_param('direction', 'DESC', PARAM_ALPHA),
];

$statusoptions = [['value' => '', 'label' => get_string('allstatuses', 'local_moderncommerce')]];
foreach (['pending', 'processing', 'paid', 'completed', 'failed', 'cancelled', 'refunded'] as $status) {
    $statusoptions[] = ['value' => $status, 'label' => get_string('status_' . $status, 'local_moderncommerce')];
}

$ordersreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/orders_admin',
    'id' => 'moderncommerce-orders-admin-app',
    'class' => 'local-moderncommerce-orders-admin',
    'props' => [
        'methodName' => 'local_moderncommerce_admin_list_orders',
        'updateStatusMethodName' => 'local_moderncommerce_admin_update_order_status',
        'initialFilters' => $initialfilters,
        'statusOptions' => $statusoptions,
        'perPageOptions' => [10, 25, 50, 100],
        'labels' => [
            'title' => get_string('manageorders', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchplaceholder' => get_string('orderssearchplaceholder', 'local_moderncommerce'),
            'status' => get_string('status'),
            'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'next' => get_string('next'),
            'ordernumber' => get_string('ordernumber', 'local_moderncommerce'),
            'customer' => get_string('customer', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'items' => get_string('items', 'local_moderncommerce'),
            'total' => get_string('total', 'local_moderncommerce'),
            'paymentmethod' => get_string('paymentmethod', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'view' => get_string('viewdetails', 'local_moderncommerce'),
            'updatestatus' => get_string('updatestatus', 'local_moderncommerce'),
            'changestatus' => get_string('changestatus', 'local_moderncommerce'),
            'statusupdated' => get_string('orderstatusupdated', 'local_moderncommerce'),
            'noorders' => get_string('noordersfound', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'totalorders' => get_string('totalorders', 'local_moderncommerce'),
            'paidorders' => get_string('paidorders', 'local_moderncommerce'),
            'pendingorders' => get_string('pendingorders', 'local_moderncommerce'),
            'refundedorders' => get_string('refundedorders', 'local_moderncommerce'),
            'revenue' => get_string('revenue', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/orders', [
    'ordersreactconfig' => $ordersreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'button',
        'label' => get_string('refresh'),
        'icon' => 'bi-arrow-clockwise',
        'attributes' => ['id' => 'moderncommerce-orders-refresh'],
    ],
    [
        'type' => 'link',
        'url' => new moodle_url('/local/moderncommerce/admin/reports.php', [
            'type' => 'sales',
            'export' => 'csv',
            'sesskey' => sesskey(),
        ]),
        'label' => get_string('exportcsv', 'local_moderncommerce'),
        'icon' => 'bi-download',
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'orders',
    get_string('manageorders', 'local_moderncommerce'),
    $contenthtml,
    get_string('manageorderssubtitle', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
