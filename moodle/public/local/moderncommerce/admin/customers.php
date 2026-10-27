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
 * Modern Commerce customers admin page (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

require_login();
$context = context_system::instance();
require_capability('local/moderncommerce:viewallorders', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/customers.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('customers', 'local_moderncommerce'));

$initialfilters = [
    'search' => optional_param('search', '', PARAM_TEXT),
    'status' => optional_param('status', '', PARAM_ALPHA),
    'page' => optional_param('page', 0, PARAM_INT),
    'perpage' => optional_param('perpage', 10, PARAM_INT),
    'sort' => optional_param('sort', 'lastorder', PARAM_ALPHANUMEXT),
    'direction' => optional_param('direction', 'DESC', PARAM_ALPHA),
];

$customersreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/customers_admin',
    'id' => 'moderncommerce-customers-admin-app',
    'class' => 'local-moderncommerce-customers-admin',
    'props' => [
        'methodName' => 'local_moderncommerce_admin_list_customers',
        'initialFilters' => $initialfilters,
        'statusOptions' => [
            ['value' => '', 'label' => get_string('allcustomers', 'local_moderncommerce')],
            ['value' => 'active', 'label' => get_string('active', 'local_moderncommerce')],
            ['value' => 'suspended', 'label' => get_string('suspended', 'local_moderncommerce')],
        ],
        'perPageOptions' => [10, 25, 50, 100],
        'labels' => [
            'title' => get_string('customers', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchplaceholder' => get_string('customerssearchplaceholder', 'local_moderncommerce'),
            'status' => get_string('status'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'next' => get_string('next'),
            'customer' => get_string('customer', 'local_moderncommerce'),
            'email' => get_string('email'),
            'orders' => get_string('orders', 'local_moderncommerce'),
            'paidorders' => get_string('paidorders', 'local_moderncommerce'),
            'pendingorders' => get_string('pendingorders', 'local_moderncommerce'),
            'totalspent' => get_string('totalspent', 'local_moderncommerce'),
            'refundedorders' => get_string('refundedorders', 'local_moderncommerce'),
            'lastorder' => get_string('lastorder', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'viewdetails' => get_string('viewdetails', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'nocustomers' => get_string('nocustomersfound', 'local_moderncommerce'),
            'totalcustomers' => get_string('totalcustomers', 'local_moderncommerce'),
            'totalorders' => get_string('totalorders', 'local_moderncommerce'),
            'revenue' => get_string('revenue', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/customers', [
    'customersreactconfig' => $customersreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'button',
        'label' => get_string('refresh'),
        'icon' => 'bi-arrow-clockwise',
        'attributes' => ['id' => 'moderncommerce-customers-refresh'],
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'customers',
    get_string('customers', 'local_moderncommerce'),
    $contenthtml,
    get_string('customers_desc', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
