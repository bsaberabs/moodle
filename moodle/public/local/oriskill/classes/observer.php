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

namespace local_oriskill;

/**
 * Modern Commerce order events: referral at order time, commissions, payment groups and the trainer's invoice once paid.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * The buyer placed an order: attribute it to the affiliate whose link they followed.
     *
     * @param \local_moderncommerce\event\order_created $event Event.
     */
    public static function order_created(\local_moderncommerce\event\order_created $event): void {
        if (!empty($_COOKIE[affiliate_service::COOKIE])) {
            affiliate_service::attribute_order((int) $event->objectid, (string) $_COOKIE[affiliate_service::COOKIE]);
        }
    }

    /**
     * The order is paid: create the affiliate's pending commissions, and put the buyer of a trainer course
     * in the group of how they paid.
     *
     * @param \local_moderncommerce\event\order_paid $event Event.
     */
    public static function order_paid(\local_moderncommerce\event\order_paid $event): void {
        affiliate_service::record_commissions((int) $event->objectid);
        instalment_service::group_buyers((int) $event->objectid);
        billing_service::issue_invoice((int) $event->objectid);
        activecampaign_service::order_paid((int) $event->objectid);
        coupon_service::order_paid((int) $event->objectid);
        webhook_service::order_event((int) $event->objectid, 'order.paid');
    }
}
