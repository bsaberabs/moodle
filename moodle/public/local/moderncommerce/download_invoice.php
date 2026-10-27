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
 * Download invoice or receipt PDF.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();

use local_moderncommerce\services\invoice_service;
use local_moderncommerce\services\learner_invoice_service;

$invoiceid = optional_param('id', 0, PARAM_INT);
$orderid = optional_param('orderid', 0, PARAM_INT);
$type = optional_param('type', 'receipt', PARAM_ALPHA);

// Validate type.
if (!in_array($type, ['invoice', 'receipt'])) {
    $type = 'receipt';
}

$context = context_system::instance();

if ($invoiceid > 0) {
    $invoice = $DB->get_record('local_moderncommerce_invoices', ['id' => $invoiceid], '*', MUST_EXIST);
    $canviewall = has_capability('local/moderncommerce:viewallorders', $context);

    if (!$canviewall) {
        if ((int) $invoice->userid !== (int) $USER->id) {
            throw new moodle_exception('nopermission', 'error');
        }

        require_capability('local/moderncommerce:viewownorders', $context);
        if (!in_array((string) $invoice->status, learner_invoice_service::VISIBLE_STATUSES, true)) {
            throw new moodle_exception('nopermission', 'error');
        }
    }

    invoice_service::download_manual_invoice($invoiceid);
}

$orderid = $orderid > 0 ? $orderid : required_param('orderid', PARAM_INT);

// Get order and verify ownership or admin rights.
$order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid], '*', MUST_EXIST);

// Check permissions.
if ((int) $order->userid === (int) $USER->id) {
    require_capability('local/moderncommerce:viewownorders', $context);
} else if (!has_capability('local/moderncommerce:viewallorders', $context)) {
    throw new moodle_exception('nopermission', 'error');
}

// For receipts, order must be paid.
if ($type === 'receipt' && $order->status !== 'paid') {
    throw new moodle_exception('orderpending', 'local_moderncommerce');
}

// Generate and download PDF.
invoice_service::download($orderid, $type);
