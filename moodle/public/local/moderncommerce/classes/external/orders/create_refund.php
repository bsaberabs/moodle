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
 * External API to record an order refund.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\orders;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\api\order_api;
use local_moderncommerce\services\enrolment_service;

/**
 * Record a refund for an order and optionally revoke access.
 */
class create_refund extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'orderid' => new external_value(PARAM_INT, 'Order ID.'),
            'refundtype' => new external_value(PARAM_ALPHA, 'Refund type (full or partial).', VALUE_DEFAULT, 'full'),
            'amount' => new external_value(PARAM_FLOAT, 'Refund amount.', VALUE_DEFAULT, 0),
            'reason' => new external_value(PARAM_TEXT, 'Refund reason.', VALUE_DEFAULT, ''),
            'unenrol' => new external_value(PARAM_BOOL, 'Whether to unenrol the buyer.', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $orderid Order ID.
     * @param string $refundtype Refund type.
     * @param float $amount Refund amount.
     * @param string $reason Reason.
     * @param bool $unenrol Whether to unenrol.
     * @return array
     */
    public static function execute(
        int $orderid,
        string $refundtype = 'full',
        float $amount = 0,
        string $reason = '',
        bool $unenrol = false
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'orderid' => $orderid,
            'refundtype' => $refundtype,
            'amount' => $amount,
            'reason' => $reason,
            'unenrol' => $unenrol,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:processrefunds', $context);

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $params['orderid']], '*', MUST_EXIST);

        if (!in_array($order->status, ['paid', 'completed'], true)) {
            return self::failure(get_string('refundonlypaidorders', 'local_moderncommerce'));
        }

        $refundtype = $params['refundtype'] === 'partial' ? 'partial' : 'full';
        $alreadyrefunded = (float) ($order->refundedtotal ?? 0);
        $maxrefundable = max(0, (float) $order->total - $alreadyrefunded);

        $amount = $refundtype === 'full' ? $maxrefundable : round((float) $params['amount'], 2);
        if ($amount <= 0 || $amount > $maxrefundable + 0.001) {
            return self::failure(get_string('invalidrefundamount', 'local_moderncommerce'));
        }

        $now = time();
        $refund = (object) [
            'orderid' => (int) $order->id,
            'attemptid' => null,
            'amount' => $amount,
            'currency' => $order->currency,
            'reason' => $params['reason'] !== '' ? $params['reason'] : get_string('refund', 'local_moderncommerce'),
            'status' => 'pending',
            'refundreference' => 'RF-' . strtoupper(substr(md5(uniqid((string) $order->id, true)), 0, 10)),
            'requestedby' => $USER->id,
            'processedby' => $USER->id,
            'adminnotes' => get_string('refundtype', 'local_moderncommerce') . ': ' . $refundtype
                . '; ' . get_string('paymentmethod', 'local_moderncommerce') . ': '
                . (order_api::get_payment_method((int) $order->id) ?: get_string('manualpayment', 'local_moderncommerce')),
            'timerequested' => $now,
            'timeprocessed' => null,
        ];
        $refundid = (int) $DB->insert_record('local_moderncommerce_refunds', $refund);
        $refund->id = $refundid;

        // Update refunded total and move the order to refunded.
        $order->refundedtotal = $alreadyrefunded + $amount;
        $order->timemodified = $now;
        $DB->update_record('local_moderncommerce_orders', (object) [
            'id' => $order->id,
            'refundedtotal' => $order->refundedtotal,
            'timemodified' => $now,
        ]);
        order_api::update_order_status((int) $order->id, 'refunded');

        if (!empty($params['unenrol'])) {
            self::revoke_access($order);
        }

        try {
            \local_moderncommerce\email_notifications::send_refund_confirmation($order, $refund);
        } catch (\Throwable $e) {
            debugging('Failed to send refund email: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        self::notify_admins_refund($order, $refund);

        order_api::log_audit((int) $order->userid, 'refund_created', 'order', (int) $order->id, null, [
            'refundid' => $refundid,
            'amount' => $amount,
            'refundtype' => $refundtype,
        ]);

        return [
            'success' => true,
            'refundid' => $refundid,
            'message' => get_string('refundcreated', 'local_moderncommerce'),
            'warnings' => [],
        ];
    }

    /**
     * Send the "refund requested" operational alert to store admins via the hub.
     *
     * @param \stdClass $order Order record.
     * @param \stdClass $refund Refund record.
     * @return void
     */
    private static function notify_admins_refund(\stdClass $order, \stdClass $refund): void {
        global $DB, $CFG;

        $buyer = $DB->get_record('user', ['id' => $order->userid]);
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            $amount = \local_moderncommerce\services\pricing_service::format_order_price((float) $refund->amount, $order);
        } else {
            $amount = number_format((float) $refund->amount, 2);
        }
        $url = $CFG->wwwroot . '/local/moderncommerce/order.php?id=' . $order->id;

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'refund_requested'))
            ->category('operational')
            ->template('ops_refund_requested')
            ->placeholders([
                'refund_amount' => $amount,
                'order_number' => $order->ordernumber,
                'customer_name' => $buyer ? fullname($buyer) : '',
                'refund_reason' => $refund->reason ?? '',
                'admin_order_url' => $url,
            ])
            ->context_url($url)
            ->related((int) $order->id);

        \local_moderncommerce\notifications\api::notify_admins($notification);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the refund was recorded.'),
            'refundid' => new external_value(PARAM_INT, 'Created refund ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Revoke buyer access for every course in the order.
     *
     * @param \stdClass $order Order record.
     * @return void
     */
    private static function revoke_access(\stdClass $order): void {
        global $DB;

        foreach (order_api::get_order_items((int) $order->id) as $item) {
            $courseids = [];

            if (!empty($item->courseid)) {
                $courseids[] = (int) $item->courseid;
            }

            // Expand bundle/program products to their included courses via canonical relations.
            if (in_array((string) $item->itemtype, ['bundle', 'program'], true) && !empty($item->productid)) {
                $included = $DB->get_fieldset_select(
                    'local_moderncommerce_product_courses',
                    'courseid',
                    'productid = :productid AND relationtype = :relationtype',
                    ['productid' => (int) $item->productid, 'relationtype' => 'included']
                );
                foreach ($included as $courseid) {
                    $courseids[] = (int) $courseid;
                }
            }

            foreach (array_unique(array_filter($courseids)) as $courseid) {
                try {
                    enrolment_service::unenrol_user_from_course((int) $order->userid, $courseid, (int) $order->id);
                } catch (\Throwable $e) {
                    debugging('Failed to unenrol on refund: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }
    }

    /**
     * Build a failure response.
     *
     * @param string $message Message.
     * @return array
     */
    private static function failure(string $message): array {
        return [
            'success' => false,
            'refundid' => 0,
            'message' => $message,
            'warnings' => [],
        ];
    }
}
