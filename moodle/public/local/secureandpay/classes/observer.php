<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_secureandpay;

use core_user;
use local_moderncommerce\subscription\services\subscription_service;

/**
 * Event observers.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /** History actions after which SecureAndPay no longer bills the subscription. */
    private const STOPPED_ACTIONS = ['gateway_cancelled', 'gateway_stopped', 'payments_completed'];

    /**
     * The subscriber cancelled at the end of the paid period: stop SecureAndPay billing now, so that nothing is
     * charged after it (a free trial cancelled before its end is never charged). Access lasts until the end date.
     *
     * @param \core\event\base $event subscription_cancel_scheduled event.
     */
    public static function subscription_cancel_scheduled(\core\event\base $event): void {
        self::stop_billing((int) $event->objectid);
    }

    /**
     * A subscription cancelled in Moodle (by an administrator, or when a scheduled cancellation takes effect):
     * stop SecureAndPay billing unless it already stopped.
     *
     * @param \core\event\base $event subscription_cancelled event.
     */
    public static function subscription_cancelled(\core\event\base $event): void {
        self::stop_billing((int) $event->objectid);
    }

    /**
     * Stop the ClicktoPay schedule of a subscription at SecureAndPay (desabonnerWs.php). If SecureAndPay does not
     * confirm, the site administrators are asked to stop it in the SecureAndPay back office.
     *
     * @param int $subscriptionid Subscription.
     */
    private static function stop_billing(int $subscriptionid): void {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
        if (!$subscription || !$subscription->orderid) {
            return;
        }
        // Already stopped (by us or by SecureAndPay), or ended after its last payment: nothing left to stop.
        // Modern Commerce overwrites the cancellation reason when the subscription ends, so the history tells.
        [$insql, $params] = $DB->get_in_or_equal(self::STOPPED_ACTIONS, SQL_PARAMS_NAMED);
        if ($DB->record_exists_select('local_moderncommerce_subscription_history',
                "subscriptionid = :subscriptionid AND action $insql", ['subscriptionid' => $subscription->id] + $params)) {
            return;
        }
        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $subscription->orderid]);
        if (!$order || !gateway::order_is_subscription($order)) {
            return;
        }
        $reference = self::schedule_reference($order);
        if (!$reference) {
            return;
        }

        [$stopped, $answer] = gateway::stop_schedule($order, $reference);
        subscription_service::log_history((int) $subscription->id, (int) $subscription->userid,
            $stopped ? 'gateway_cancelled' : 'gateway_cancel_failed', null, null, null, null, null, null,
            get_string($stopped ? 'schedulestopped' : 'schedulenotstopped', 'local_secureandpay',
                (object) ['reference' => $reference, 'answer' => shorten_text($answer, 200)]));
        if ($stopped) {
            return;
        }

        $user = core_user::get_user($subscription->userid);
        $a = (object) ['reference' => $reference, 'user' => fullname($user), 'email' => $user->email,
            'answer' => $answer];
        foreach (get_admins() as $admin) {
            email_to_user($admin, core_user::get_noreply_user(),
                get_string('cancelinsecureandpay_subject', 'local_secureandpay', $a),
                get_string('cancelinsecureandpay_body', 'local_secureandpay', $a));
        }
    }

    /**
     * SecureAndPay orderID of the payment that started an order's schedule.
     *
     * @param object $order Order.
     * @return string|null
     */
    private static function schedule_reference(object $order): ?string {
        global $DB;

        $reference = $DB->get_field_select('local_moderncommerce_payment_attempts', 'reference',
            'orderid = :orderid AND gateway = :gateway AND status = :status',
            ['orderid' => $order->id, 'gateway' => gateway::GATEWAY, 'status' => 'success'], IGNORE_MULTIPLE);
        if ($reference) {
            return $reference;
        }
        // A free trial is not a Modern Commerce payment attempt: its orderID is kept in the order notes.
        $notes = json_decode((string) ($order->notes ?? ''), true);
        return $notes['subscription']['secureandpay_reference'] ?? null;
    }
}
