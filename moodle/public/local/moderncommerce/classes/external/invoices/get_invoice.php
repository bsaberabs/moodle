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
 * External API returning one invoice with items for the admin editor.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\invoices;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Get one invoice with its line items.
 */
class get_invoice extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Invoice ID.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Invoice ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:manageorders', $context);

        $invoice = $DB->get_record('local_moderncommerce_invoices', ['id' => $params['id']], '*', MUST_EXIST);
        $customer = $DB->get_record('user', ['id' => $invoice->userid], '*', IGNORE_MISSING);

        $items = [];
        $itemrecords = $DB->get_records('local_moderncommerce_invoice_items', ['invoiceid' => $invoice->id], 'id ASC');
        foreach ($itemrecords as $item) {
            $items[] = [
                'id' => (int) $item->id,
                'description' => (string) $item->description,
                'quantity' => (int) round((float) $item->quantity),
                'unitprice' => (float) $item->unitprice,
                'total' => (float) $item->total,
            ];
        }

        return [
            'id' => (int) $invoice->id,
            'invoicenumber' => (string) $invoice->invoicenumber,
            'customerid' => $customer ? (int) $customer->id : 0,
            'customername' => $customer ? fullname($customer) : '',
            'customeremail' => $customer ? (string) $customer->email : '',
            'status' => (string) $invoice->status,
            'currency' => (string) $invoice->currency,
            'duedate' => (int) ($invoice->duedate ?? 0),
            'notes' => (string) ($invoice->notes ?? ''),
            'terms' => (string) ($invoice->terms ?? ''),
            'subtotal' => (float) $invoice->subtotal,
            'tax' => (float) $invoice->tax,
            'total' => (float) $invoice->total,
            'items' => $items,
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Invoice ID.'),
            'invoicenumber' => new external_value(PARAM_TEXT, 'Invoice number.'),
            'customerid' => new external_value(PARAM_INT, 'Customer user ID.'),
            'customername' => new external_value(PARAM_TEXT, 'Customer name.'),
            'customeremail' => new external_value(PARAM_TEXT, 'Customer email.'),
            'status' => new external_value(PARAM_ALPHA, 'Status.'),
            'currency' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.'),
            'duedate' => new external_value(PARAM_INT, 'Due date timestamp.'),
            'notes' => new external_value(PARAM_RAW, 'Notes.'),
            'terms' => new external_value(PARAM_RAW, 'Terms.'),
            'subtotal' => new external_value(PARAM_FLOAT, 'Subtotal.'),
            'tax' => new external_value(PARAM_FLOAT, 'Tax amount.'),
            'total' => new external_value(PARAM_FLOAT, 'Total.'),
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Item ID.'),
                'description' => new external_value(PARAM_TEXT, 'Line description.'),
                'quantity' => new external_value(PARAM_INT, 'Quantity.'),
                'unitprice' => new external_value(PARAM_FLOAT, 'Unit price.'),
                'total' => new external_value(PARAM_FLOAT, 'Line total.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }
}
