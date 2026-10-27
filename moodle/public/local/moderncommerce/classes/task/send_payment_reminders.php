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
 * Scheduled task to send payment reminder emails
 *
 * Sends reminder emails to users with pending orders that haven't been paid.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_payment_reminders extends \core\task\scheduled_task {
    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:send_payment_reminders', 'local_moderncommerce');
    }

    /**
     * Execute task
     */
    public function execute() {
        global $DB;

        // Only send reminders if enabled.
        $enabled = get_config('local_moderncommerce', 'enable_payment_reminders');
        if (!$enabled) {
            mtrace("Payment reminders are disabled");
            return;
        }

        // Time windows for reminders (in hours after order creation).
        $firstreminder = 24 * 3600;  // 24 hours
        $secondreminder = 48 * 3600; // 48 hours

        // Don't send reminders for orders older than 72 hours.
        $maxage = 72 * 3600;

        $now = time();

        $sql = "SELECT o.*, u.email, u.firstname, u.lastname
                FROM {local_moderncommerce_orders} o
                JOIN {user} u ON u.id = o.userid
                WHERE o.status = 'pending'
                AND o.timecreated < :firstwindow
                AND o.timecreated > :maxage";
        $orders = $DB->get_records_sql($sql, [
            'firstwindow' => $now - $firstreminder, 'maxage' => $now - $maxage,
        ]);
        $sent = 0;
        foreach ($orders as $order) {
            $reminderssent = $this->get_reminders_sent((int) $order->id);
            $targetreminder = ($order->timecreated < ($now - $secondreminder)) ? 2 : 1;
            if ($reminderssent >= $targetreminder) {
                continue;
            }
            $remindernum = $reminderssent + 1;
            try {
                $this->send_reminder($order, $remindernum);
                \local_moderncommerce\api\order_api::log_audit(
                    (int) $order->userid,
                    'payment_reminder_sent',
                    'order',
                    (int) $order->id,
                    null,
                    ['remindernum' => $remindernum]
                );
                $sent++;
                mtrace("Sent payment reminder {$remindernum} for order #{$order->ordernumber}");
            } catch (\Throwable $e) {
                mtrace("Failed to send reminder for order #{$order->ordernumber}: " . $e->getMessage());
            }
        }

        mtrace("Sent $sent payment reminder(s)");
    }
    /**
     * Send payment reminder email
     *
     * @param object $order Order with user info
     * @param int $remindernum Which reminder (1 or 2)
     */
    protected function send_reminder($order, $remindernum) {
        global $DB;

        $user = $DB->get_record('user', ['id' => $order->userid]);
        if (!$user) {
            return;
        }

        $items = \local_moderncommerce\api\order_api::get_order_items((int) $order->id);
        \local_moderncommerce\email_notifications::send_payment_reminder($user, $order, $items, $remindernum);
    }

    /**
     * Count reminders already sent for an order.
     *
     * @param int $orderid Order ID.
     * @return int
     */
    protected function get_reminders_sent(int $orderid): int {

        global $DB;
        if (!$DB->get_manager()->table_exists('local_moderncommerce_audit_log')) {
            return 0;
        }

        return (int) $DB->count_records('local_moderncommerce_audit_log', [
            'action' => 'payment_reminder_sent', 'entitytype' => 'order', 'entityid' => $orderid,
        ]);
    }
}
