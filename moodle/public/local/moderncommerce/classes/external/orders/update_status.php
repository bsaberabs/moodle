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
 * External API to change an order status.
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
use local_moderncommerce\localisation;

/**
 * Update the status of an order.
 */
class update_status extends external_api {
    /** @var string[] Allowed target statuses. */
    private const STATUSES = ['pending', 'processing', 'paid', 'completed', 'failed', 'cancelled', 'refunded'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'orderid' => new external_value(PARAM_INT, 'Order ID.'),
            'status' => new external_value(PARAM_ALPHA, 'New order status.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $orderid Order ID.
     * @param string $status New status.
     * @return array
     */
    public static function execute(int $orderid, string $status): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'orderid' => $orderid,
            'status' => $status,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:manageorders', $context);

        if (!in_array($params['status'], self::STATUSES, true)) {
            return self::failure($params['orderid'], get_string('invalidstatus', 'local_moderncommerce'));
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $params['orderid']], '*', MUST_EXIST);

        $applied = order_api::update_order_status($params['orderid'], $params['status']);
        if (!$applied) {
            return self::failure(
                $params['orderid'],
                get_string('orderstatustransitionnotallowed', 'local_moderncommerce')
            );
        }

        $updated = $DB->get_record('local_moderncommerce_orders', ['id' => $params['orderid']], 'id, status', MUST_EXIST);

        return [
            'success' => true,
            'orderid' => (int) $updated->id,
            'status' => (string) $updated->status,
            'statuslabel' => self::status_label((string) $updated->status),
            'statusclass' => self::status_class((string) $updated->status),
            'message' => get_string('orderstatusupdated', 'local_moderncommerce'),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the status was changed.'),
            'orderid' => new external_value(PARAM_INT, 'Order ID.'),
            'status' => new external_value(PARAM_ALPHA, 'Resulting order status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Resulting status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Resulting status badge class.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Build a failure response.
     *
     * @param int $orderid Order ID.
     * @param string $message Message.
     * @return array
     */
    private static function failure(int $orderid, string $message): array {
        global $DB;

        $status = (string) $DB->get_field('local_moderncommerce_orders', 'status', ['id' => $orderid]);

        return [
            'success' => false,
            'orderid' => $orderid,
            'status' => $status,
            'statuslabel' => self::status_label($status),
            'statusclass' => self::status_class($status),
            'message' => $message,
            'warnings' => [],
        ];
    }

    /**
     * Localised status label.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_label(string $status): string {
        return localisation::status_label($status, ['orderstatus']);
    }

    /**
     * Status badge class.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_class(string $status): string {
        switch ($status) {
            case 'paid':
            case 'completed':
                return 'success';
            case 'pending':
            case 'processing':
                return 'warning';
            case 'failed':
            case 'cancelled':
                return 'danger';
            case 'refunded':
                return 'info';
            default:
                return 'neutral';
        }
    }
}
