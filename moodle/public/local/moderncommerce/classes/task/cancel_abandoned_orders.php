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

namespace local_moderncommerce\task;


/**
 * Scheduled task to cancel abandoned orders
 *
 * Orders that remain in 'pending' status for too long are automatically cancelled.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cancel_abandoned_orders extends \core\task\scheduled_task {
    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:cancel_abandoned_orders', 'local_moderncommerce');
    }

    /**
     * Execute task
     */
    public function execute() {
        global $DB;

        // Get configurable timeout (default 48 hours).
        $timeout = get_config('local_moderncommerce', 'abandoned_order_timeout');
        if (!$timeout) {
            $timeout = 48 * 3600; // 48 hours default
        }

        $cutoff = time() - $timeout;

        // Find pending orders older than cutoff.
        $sql = "SELECT * FROM {local_moderncommerce_orders}
                WHERE status = 'pending'
                AND timecreated < :cutoff";

        $orders = $DB->get_records_sql($sql, ['cutoff' => $cutoff]);

        if (empty($orders)) {
            mtrace("No abandoned orders to cancel");
            return;
        }

        $count = 0;
        foreach ($orders as $order) {
            // Update order status to cancelled.
            $order->status = 'cancelled';
            $order->timemodified = time();
            $DB->update_record('local_moderncommerce_orders', $order);

            // Release any cart items back (already handled by cart cleanup).

            // Notify the buyer (no charge was taken) via the notification hub.
            self::notify_cancelled($order);

            // Log the cancellation.
            mtrace("Cancelled abandoned order #{$order->ordernumber} (ID: {$order->id})");
            $count++;
        }

        mtrace("Cancelled $count abandoned orders");
    }

    /**
     * Send the order-cancelled notification through the hub when installed.
     *
     * @param \stdClass $order Cancelled order record.
     * @return void
     */
    private static function notify_cancelled(\stdClass $order): void {
        global $DB;

        $names = [];
        foreach ($DB->get_records('local_moderncommerce_order_items', ['orderid' => $order->id]) as $item) {
            if (!empty($item->coursename)) {
                $names[] = $item->coursename;
            } else if (!empty($item->bundlename)) {
                $names[] = $item->bundlename;
            } else if (!empty($item->itemname)) {
                $names[] = $item->itemname;
            } else if (!empty($item->courseid)) {
                $names[] = (string) $DB->get_field('course', 'fullname', ['id' => $item->courseid]);
            }
        }

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'order_cancelled'))
            ->category('transactional')
            ->template('moderncommerce_order_cancelled')
            ->to_user((int) $order->userid)
            ->placeholders([
                'order_number' => $order->ordernumber,
                'courses_list' => implode('<br>', array_filter($names)),
            ])
            ->related((int) $order->id);

        \local_moderncommerce\notifications\api::notify($notification);
    }
}
