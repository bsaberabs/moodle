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
 * External API saving a manual invoice and its line items.
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
use local_moderncommerce\api\order_api;
use local_moderncommerce\services\pricing_service;

/**
 * Create or update a manual invoice, recomputing totals from its line items.
 */
class save_invoice extends external_api {
    /** @var string[] Allowed statuses. */
    private const STATUSES = ['draft', 'sent', 'paid', 'overdue', 'cancelled', 'void'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Invoice ID (0 to create).', VALUE_DEFAULT, 0),
            'userid' => new external_value(PARAM_INT, 'Customer user ID.'),
            'invoicenumber' => new external_value(PARAM_TEXT, 'Invoice number (blank auto-generates).', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status.', VALUE_DEFAULT, 'draft'),
            'tax' => new external_value(PARAM_FLOAT, 'Tax amount.', VALUE_DEFAULT, 0),
            'duedate' => new external_value(PARAM_INT, 'Due date timestamp.', VALUE_DEFAULT, 0),
            'notes' => new external_value(PARAM_TEXT, 'Notes.', VALUE_DEFAULT, ''),
            'terms' => new external_value(PARAM_TEXT, 'Terms.', VALUE_DEFAULT, ''),
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'description' => new external_value(PARAM_TEXT, 'Line description.'),
                    'quantity' => new external_value(PARAM_INT, 'Quantity.', VALUE_DEFAULT, 1),
                    'unitprice' => new external_value(PARAM_FLOAT, 'Unit price.', VALUE_DEFAULT, 0),
                ]),
                'Invoice line items.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Invoice ID.
     * @param int $userid Customer user ID.
     * @param string $invoicenumber Invoice number.
     * @param string $status Status.
     * @param float $tax Tax.
     * @param int $duedate Due date.
     * @param string $notes Notes.
     * @param string $terms Terms.
     * @param array $items Line items.
     * @return array
     */
    public static function execute(
        int $id = 0,
        int $userid = 0,
        string $invoicenumber = '',
        string $status = 'draft',
        float $tax = 0,
        int $duedate = 0,
        string $notes = '',
        string $terms = '',
        array $items = []
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'userid' => $userid,
            'invoicenumber' => $invoicenumber,
            'status' => $status,
            'tax' => $tax,
            'duedate' => $duedate,
            'notes' => $notes,
            'terms' => $terms,
            'items' => $items,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:manageorders', $context);

        if (!$DB->record_exists('user', ['id' => $params['userid']])) {
            return self::failure(get_string('invaliduser', 'local_moderncommerce'));
        }

        $status = in_array($params['status'], self::STATUSES, true) ? $params['status'] : 'draft';
        $tax = max(0, (float) $params['tax']);

        // Normalise items and recompute totals server-side.
        $normalitems = [];
        $subtotal = 0.0;
        foreach ($params['items'] as $item) {
            $description = trim((string) $item['description']);
            if ($description === '') {
                continue;
            }
            $quantity = max(1, (int) $item['quantity']);
            $unitprice = max(0, (float) $item['unitprice']);
            $linetotal = $unitprice * $quantity;
            $subtotal += $linetotal;
            $normalitems[] = (object) [
                'description' => $description,
                'quantity' => $quantity,
                'unitprice' => $unitprice,
                'total' => $linetotal,
            ];
        }
        $total = $subtotal + $tax;

        $now = time();
        $currency = pricing_service::get_currency_config()->currency;

        $transaction = $DB->start_delegated_transaction();
        $existing = null;
        try {
            if ($params['id'] <= 0) {
                $number = trim($params['invoicenumber']) !== ''
                    ? trim($params['invoicenumber'])
                    : order_api::generate_invoice_number();

                $invoiceid = (int) $DB->insert_record('local_moderncommerce_invoices', (object) [
                    'orderid' => null,
                    'userid' => $params['userid'],
                    'invoicenumber' => $number,
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'currency' => $currency,
                    'duedate' => $params['duedate'] > 0 ? $params['duedate'] : ($now + (30 * DAYSECS)),
                    'issuedat' => $status === 'draft' ? null : $now,
                    'paidat' => $status === 'paid' ? $now : null,
                    'notes' => $params['notes'],
                    'terms' => $params['terms'],
                    'createdby' => $USER->id,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            } else {
                $invoiceid = (int) $params['id'];
                $existing = $DB->get_record('local_moderncommerce_invoices', ['id' => $invoiceid], '*', MUST_EXIST);

                $update = (object) [
                    'id' => $invoiceid,
                    'userid' => $params['userid'],
                    'invoicenumber' => trim($params['invoicenumber']) !== ''
                        ? trim($params['invoicenumber'])
                        : $existing->invoicenumber,
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'duedate' => $params['duedate'] > 0 ? $params['duedate'] : $existing->duedate,
                    'notes' => $params['notes'],
                    'terms' => $params['terms'],
                    'timemodified' => $now,
                ];
                if ($status === 'paid' && empty($existing->paidat)) {
                    $update->paidat = $now;
                }
                $DB->update_record('local_moderncommerce_invoices', $update);
                $DB->delete_records('local_moderncommerce_invoice_items', ['invoiceid' => $invoiceid]);
            }

            foreach ($normalitems as $item) {
                $item->invoiceid = $invoiceid;
                $item->orderitemid = null;
                $item->timecreated = $now;
                $DB->insert_record('local_moderncommerce_invoice_items', $item);
            }

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return self::failure($e->getMessage());
        }

        \local_moderncommerce\audit\audit_service::record(
            $existing ? 'invoice_updated' : 'invoice_created',
            'invoice',
            $invoiceid,
            [
                'subjectuserid' => (int) $params['userid'],
                'olddata' => $existing,
                'newdata' => [
                    'invoiceid' => $invoiceid,
                    'userid' => (int) $params['userid'],
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'currency' => $currency,
                    'items' => $normalitems,
                ],
                'severity' => 'warning',
            ]
        );

        return [
            'success' => true,
            'invoiceid' => $invoiceid,
            'message' => $params['id'] <= 0
                ? get_string('invoicecreated', 'local_moderncommerce')
                : get_string('invoiceupdated', 'local_moderncommerce'),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the invoice was saved.'),
            'invoiceid' => new external_value(PARAM_INT, 'Saved invoice ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Build a failure response.
     *
     * @param string $message Message.
     * @return array
     */
    private static function failure(string $message): array {
        return ['success' => false, 'invoiceid' => 0, 'message' => $message, 'warnings' => []];
    }
}
