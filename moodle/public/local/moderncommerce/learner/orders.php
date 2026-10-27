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
 * Learner orders page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\learner_shell;

require_login();

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 10, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHANUMEXT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/orders.php', [
    'page' => $page,
    'perpage' => $perpage,
    'status' => $status,
]));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('myorders', 'local_moderncommerce'));
$PAGE->set_heading(get_string('myorders', 'local_moderncommerce'));

$shell = learner_shell::create('orders');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_orders',
    'id' => 'moderncommerce-learner-orders-app',
    'class' => 'local-moderncommerce-learner-orders',
    'props' => [
        'methodName' => 'local_moderncommerce_list_learner_orders',
        'initialFilters' => [
            'status' => $status,
            'page' => $page,
            'perpage' => $perpage,
        ],
        'layout' => $shell->get_react_layout_context(),
        'labels' => [
            'actions' => get_string('actions', 'local_moderncommerce'),
            'allorders' => get_string('allorders', 'local_moderncommerce'),
            'browsecatalog' => get_string('browsecatalog', 'local_moderncommerce'),
            'cancelled' => get_string('cancelled', 'local_moderncommerce'),
            'continuepayment' => get_string('continuepayment', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'downloadinvoice' => get_string('downloadinvoice', 'local_moderncommerce'),
            'duedate' => get_string('duedate', 'local_moderncommerce'),
            'invoice' => get_string('invoice', 'local_moderncommerce'),
            'invoices' => get_string('invoices', 'local_moderncommerce'),
            'items' => get_string('items', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'manualinvoices' => get_string('manualinvoices', 'local_moderncommerce'),
            'manualinvoicesdesc' => get_string('manualinvoicesdesc', 'local_moderncommerce'),
            'myorders' => get_string('myorders', 'local_moderncommerce'),
            'nextpage' => get_string('nextpage', 'local_moderncommerce'),
            'noorders' => get_string('noorders', 'local_moderncommerce'),
            'noordersyet' => get_string('noordersyet', 'local_moderncommerce'),
            'of' => get_string('of', 'local_moderncommerce'),
            'order' => get_string('order', 'local_moderncommerce'),
            'outstanding' => get_string('outstanding', 'local_moderncommerce'),
            'paid' => get_string('paid', 'local_moderncommerce'),
            'pending' => get_string('pending', 'local_moderncommerce'),
            'previouspage' => get_string('previouspage', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'status_cancelled' => get_string('status_cancelled', 'local_moderncommerce'),
            'status_paid' => get_string('status_paid', 'local_moderncommerce'),
            'status_pending' => get_string('status_pending', 'local_moderncommerce'),
            'status_refunded' => get_string('status_refunded', 'local_moderncommerce'),
            'total' => get_string('total', 'local_moderncommerce'),
            'totalspent' => get_string('totalspent', 'local_moderncommerce'),
            'view' => get_string('view', 'local_moderncommerce'),
            'viewandmanageorders' => get_string('viewandmanageorders', 'local_moderncommerce'),
            'vieworder' => get_string('vieworder', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-orders',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-receipt',
]);

echo $OUTPUT->header();
echo $shell->set_content($contenthtml)
    ->add_breadcrumb(get_string('myorders', 'local_moderncommerce'), '', true)
    ->render($OUTPUT);
echo $OUTPUT->footer();
