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
 * External API changing or deleting an invoice.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\invoices;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Change an invoice status or delete it.
 */
class set_invoice_status extends external_api {
    /** @var string[] Allowed status actions. */
    private const STATUSES = ['draft', 'sent', 'paid', 'overdue', 'cancelled', 'void'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'invoiceid' => new external_value(PARAM_INT, 'Invoice ID.'),
            'action' => new external_value(PARAM_ALPHA, 'A status value or "delete".'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $invoiceid Invoice ID.
     * @param string $action Action.
     * @return array
     */
    public static function execute(int $invoiceid, string $action): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'invoiceid' => $invoiceid,
            'action' => $action,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:manageorders', $context);

        $invoice = $DB->get_record('local_moderncommerce_invoices', ['id' => $params['invoiceid']], '*', MUST_EXIST);

        if ($params['action'] === 'delete') {
            $DB->delete_records('local_moderncommerce_invoice_items', ['invoiceid' => $invoice->id]);
            $DB->delete_records('local_moderncommerce_invoices', ['id' => $invoice->id]);
            \local_moderncommerce\audit\audit_service::record('invoice_deleted', 'invoice', (int) $invoice->id, [
                'subjectuserid' => (int) ($invoice->userid ?? 0),
                'olddata' => $invoice,
                'newdata' => null,
                'severity' => 'warning',
            ]);
            return self::result(true, get_string('invoicedeleted', 'local_moderncommerce'), 'deleted');
        }

        if (!in_array($params['action'], self::STATUSES, true)) {
            return self::result(false, get_string('invalidaction', 'local_moderncommerce'), (string) $invoice->status);
        }

        $update = (object) [
            'id' => $invoice->id,
            'status' => $params['action'],
            'timemodified' => time(),
        ];
        if ($params['action'] === 'paid' && empty($invoice->paidat)) {
            $update->paidat = time();
        }
        $DB->update_record('local_moderncommerce_invoices', $update);

        \local_moderncommerce\audit\audit_service::record('invoice_status_changed', 'invoice', (int) $invoice->id, [
            'subjectuserid' => (int) ($invoice->userid ?? 0),
            'olddata' => ['status' => $invoice->status, 'paidat' => $invoice->paidat ?? null],
            'newdata' => $update,
            'severity' => 'warning',
        ]);

        self::notify_invoice($invoice, $params['action']);

        return self::result(
            true,
            get_string('invoiceupdated', 'local_moderncommerce'),
            $params['action']
        );
    }

    /**
     * Notify the customer of an invoice status change through the hub.
     *
     * @param \stdClass $invoice Invoice record (pre-update).
     * @param string $action New status (sent|paid|cancelled|void|...).
     * @return void
     */
    private static function notify_invoice(\stdClass $invoice, string $action): void {
        global $CFG;

        $map = [
            'sent' => ['invoice_sent', 'moderncommerce_invoice_sent'],
            'paid' => ['invoice_paid', 'moderncommerce_invoice_paid'],
            'cancelled' => ['invoice_cancelled', 'moderncommerce_invoice_cancelled'],
            'void' => ['invoice_cancelled', 'moderncommerce_invoice_cancelled'],
        ];
        if (!isset($map[$action])) {
            return;
        }
        [$eventkey, $templatekey] = $map[$action];

        $userid = !empty($invoice->customerid) ? (int) $invoice->customerid
            : (!empty($invoice->userid) ? (int) $invoice->userid : 0);
        if ($userid <= 0) {
            return;
        }

        $total = (float) ($invoice->total ?? ($invoice->amount ?? 0));
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            $totaldisplay = \local_moderncommerce\services\pricing_service::format_price($total);
        } else {
            $totaldisplay = number_format($total, 2);
        }
        $invoiceurl = $CFG->wwwroot . '/local/moderncommerce/download_invoice.php?id=' . $invoice->id;

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', $eventkey))
            ->category('transactional')
            ->template($templatekey)
            ->to_user($userid)
            ->placeholders([
                'invoice_number' => $invoice->invoicenumber ?? ('#' . $invoice->id),
                'invoice_total' => $totaldisplay,
                'invoice_duedate' => !empty($invoice->duedate) ? userdate($invoice->duedate) : '',
                'pay_invoice_url' => $invoiceurl,
                'invoice_url' => $invoiceurl,
                'invoice_pdf_url' => $invoiceurl,
                'organisation_name' => '',
            ])
            ->context_url($invoiceurl)
            ->related((int) $invoice->id);

        \local_moderncommerce\notifications\api::notify($notification);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the action succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'status' => new external_value(PARAM_ALPHA, 'Resulting status (or "deleted").'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Resulting status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Resulting status badge class.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Build a result array.
     *
     * @param bool $success Success flag.
     * @param string $message Message.
     * @param string $status Resulting status.
     * @return array
     */
    private static function result(bool $success, string $message, string $status): array {
        $islabel = $status !== 'deleted';

        return [
            'success' => $success,
            'message' => $message,
            'status' => $status,
            'statuslabel' => $islabel ? list_invoices::status_label($status) : '',
            'statusclass' => $islabel ? list_invoices::status_class($status) : 'neutral',
            'warnings' => [],
        ];
    }
}
