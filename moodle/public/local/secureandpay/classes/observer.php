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

/**
 * Event observers.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * SecureAndPay has no API to stop a ClicktoPay subscription, so when one is cancelled
     * in Moodle the site administrators are asked to stop it in the SecureAndPay back office.
     *
     * @param \core\event\base $event subscription_cancelled event.
     */
    public static function subscription_cancelled(\core\event\base $event): void {
        global $DB;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $event->objectid]);
        if (!$subscription || !$subscription->orderid || $subscription->cancellation_reason === 'secureandpay') {
            return;
        }
        $reference = $DB->get_field_select('local_moderncommerce_payment_attempts', 'reference',
            'orderid = :orderid AND gateway = :gateway AND status = :status',
            ['orderid' => $subscription->orderid, 'gateway' => gateway::GATEWAY, 'status' => 'success'], IGNORE_MULTIPLE);
        if (!$reference) {
            return;
        }

        $user = core_user::get_user($subscription->userid);
        $a = (object) ['reference' => $reference, 'user' => fullname($user), 'email' => $user->email];
        foreach (get_admins() as $admin) {
            email_to_user($admin, core_user::get_noreply_user(),
                get_string('cancelinsecureandpay_subject', 'local_secureandpay', $a),
                get_string('cancelinsecureandpay_body', 'local_secureandpay', $a));
        }
    }
}
