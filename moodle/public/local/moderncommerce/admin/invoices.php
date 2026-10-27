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
 * Manual invoice management (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\output\validation_labels;
use local_moderncommerce\services\pricing_service;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:manageorders', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/invoices.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('manageinvoices', 'local_moderncommerce'));

$currencyconfig = pricing_service::get_currency_config();

$statusoptions = [];
foreach (['draft', 'sent', 'paid', 'overdue', 'cancelled', 'void'] as $status) {
    $statusoptions[] = ['value' => $status, 'label' => get_string($status, 'local_moderncommerce')];
}

$invoicesreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/invoices_admin',
    'id' => 'moderncommerce-invoices-admin-app',
    'class' => 'local-moderncommerce-invoices-admin',
    'props' => [
        'listMethodName' => 'local_moderncommerce_admin_list_invoices',
        'getMethodName' => 'local_moderncommerce_admin_get_invoice',
        'saveMethodName' => 'local_moderncommerce_admin_save_invoice',
        'setStatusMethodName' => 'local_moderncommerce_admin_set_invoice_status',
        'searchCustomersMethodName' => 'local_moderncommerce_admin_search_customers',
        'currency' => [
            'code' => $currencyconfig->currency,
            'symbol' => $currencyconfig->symbol,
            'position' => $currencyconfig->position,
            'decimals' => (int) $currencyconfig->decimals,
        ],
        'statusOptions' => $statusoptions,
        'perPageOptions' => [10, 25, 50, 100],
        'labels' => validation_labels::all() + [
            'error:invoiceneedsitems' => get_string('error:invoiceneedsitems', 'local_moderncommerce'),
            'title' => get_string('manageinvoices', 'local_moderncommerce'),
            'createinvoice' => get_string('createinvoice', 'local_moderncommerce'),
            'editinvoice' => get_string('editinvoice', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchplaceholder' => get_string('searchinvoices', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'next' => get_string('next'),
            'invoicenumber' => get_string('invoicenumber', 'local_moderncommerce'),
            'customer' => get_string('customer', 'local_moderncommerce'),
            'total' => get_string('total', 'local_moderncommerce'),
            'duedate' => get_string('duedate', 'local_moderncommerce'),
            'created' => get_string('created', 'local_moderncommerce'),
            'items' => get_string('lineitem', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'edit' => get_string('edit'),
            'delete' => get_string('delete'),
            'markpaid' => get_string('markpaid', 'local_moderncommerce'),
            'markassent' => get_string('markassent', 'local_moderncommerce'),
            'deleteconfirm' => get_string('deleteinvoiceconfirm', 'local_moderncommerce'),
            'noinvoices' => get_string('noinvoices', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'totalinvoices' => get_string('totalinvoices', 'local_moderncommerce'),
            'paidinvoices' => get_string('paidinvoices', 'local_moderncommerce'),
            'pendinginvoices' => get_string('pendinginvoices', 'local_moderncommerce'),
            'overdue' => get_string('overdueinvoices', 'local_moderncommerce'),
            'outstanding' => get_string('outstanding', 'local_moderncommerce'),
            'customerlabel' => get_string('customer', 'local_moderncommerce'),
            'searchcustomer' => get_string('searchcustomer', 'local_moderncommerce'),
            'selectcustomer' => get_string('selectcustomer', 'local_moderncommerce'),
            'duedatefield' => get_string('duedate', 'local_moderncommerce'),
            'notes' => get_string('notes', 'local_moderncommerce'),
            'terms' => get_string('terms', 'local_moderncommerce'),
            'tax' => get_string('tax', 'local_moderncommerce'),
            'subtotal' => get_string('subtotal', 'local_moderncommerce'),
            'description' => get_string('description', 'local_moderncommerce'),
            'quantity' => get_string('quantity', 'local_moderncommerce'),
            'unitprice' => get_string('unitprice', 'local_moderncommerce'),
            'amount' => get_string('amount', 'local_moderncommerce'),
            'addlineitem' => get_string('addlineitem', 'local_moderncommerce'),
            'save' => get_string('savechanges'),
            'cancel' => get_string('cancel'),
            'saved' => get_string('invoiceupdated', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/invoices', [
    'invoicesreactconfig' => $invoicesreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'button',
        'label' => get_string('refresh'),
        'icon' => 'bi-arrow-clockwise',
        'attributes' => ['id' => 'moderncommerce-invoices-refresh'],
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'invoices',
    get_string('manageinvoices', 'local_moderncommerce'),
    $contenthtml,
    get_string('invoicesdesc', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
