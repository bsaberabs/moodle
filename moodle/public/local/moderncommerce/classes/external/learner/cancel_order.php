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
 * External API to cancel a pending buyer order.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\api\order_api;
use local_moderncommerce\event\order_status_changed;

/**
 * Cancels a pending order owned by the learner (or manageable by staff).
 */
class cancel_order extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Order ID to cancel.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Order ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $USER;

        ['id' => $id] = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);

        $order = order_api::get_order($id);

        // The buyer may cancel their own order; staff need the manage-all capability.
        if ((int)$order->userid === (int)$USER->id) {
            require_capability('local/moderncommerce:viewownorders', $context);
        } else {
            require_capability('local/moderncommerce:viewallorders', $context);
        }

        // Only pending orders can be cancelled.
        if ($order->status !== 'pending') {
            throw new \moodle_exception('cannotcancelorder', 'local_moderncommerce');
        }

        $oldstatus = $order->status;
        order_api::update_order_status($id, 'cancelled');

        $event = order_status_changed::create([
            'context' => $context,
            'objectid' => $order->id,
            'userid' => $USER->id,
            'other' => [
                'ordernumber' => $order->ordernumber,
                'oldstatus' => $oldstatus,
                'newstatus' => 'cancelled',
            ],
        ]);
        $event->trigger();

        return [
            'success' => true,
            'message' => get_string('ordercancelledsuccess', 'local_moderncommerce'),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the order was cancelled.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
        ]);
    }
}
