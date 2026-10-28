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

namespace local_moderncommerce\hook;

/**
 * Lets other plugins narrow the payment methods offered for a purchase.
 *
 * Oriskill addition (not in upstream Modern Commerce): dispatched by gateway_manager::get_payment_methods(),
 * which the checkout page, order placement and payment initialisation all go through, so a method removed
 * here can neither be shown nor used. The purchase is the given order, or the user's cart when there is none.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to restrict the payment methods offered for an order or a cart.')]
#[\core\attribute\tags('local_moderncommerce', 'payment')]
final class filter_payment_methods {

    /**
     * Constructor.
     *
     * @param array $methods Available methods, keyed by gateway ID.
     * @param int $userid Buyer.
     * @param object|null $order Order being paid, or null for the buyer's current cart.
     */
    public function __construct(
        /** @var array Available methods, keyed by gateway ID. */
        public array $methods,
        /** @var int Buyer. */
        public readonly int $userid,
        /** @var object|null Order being paid, or null for the buyer's current cart. */
        public readonly ?object $order = null,
    ) {
    }

    /**
     * Withdraw a payment method for this purchase.
     *
     * @param string $gatewayid Gateway ID.
     */
    public function remove(string $gatewayid): void {
        unset($this->methods[$gatewayid]);
    }
}
