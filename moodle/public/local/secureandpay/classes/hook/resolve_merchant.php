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

namespace local_secureandpay\hook;

use local_secureandpay\merchant;

/**
 * Lets another plugin route an order to another SecureAndPay account than the platform's,
 * for instance the account of the trainer selling the course, or refuse the payment.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Choose the SecureAndPay merchant account that takes an order')]
#[\core\attribute\tags('payment')]
final class resolve_merchant {

    /** @var merchant|null Account chosen by a callback. */
    private ?merchant $merchant = null;

    /** @var string|null Why the order cannot be paid, if a callback refused it. */
    private ?string $refusal = null;

    /**
     * Constructor.
     *
     * @param object $order Modern Commerce order.
     * @param bool $forpayment True when a payment is about to start; false when checking a notification
     *                         (a notification must always be verified, so refusals are ignored then).
     */
    public function __construct(
        /** @var object Order. */
        public readonly object $order,
        /** @var bool Payment about to start. */
        public readonly bool $forpayment,
    ) {
    }

    /**
     * Route the order to this account.
     *
     * @param merchant $merchant Account.
     */
    public function set_merchant(merchant $merchant): void {
        $this->merchant = $merchant;
    }

    /**
     * Refuse the payment, with a message shown to the buyer.
     *
     * @param string $message Message.
     */
    public function refuse(string $message): void {
        $this->refusal = $message;
    }

    /**
     * Account chosen by a callback, if any.
     *
     * @return merchant|null
     */
    public function get_merchant(): ?merchant {
        return $this->merchant;
    }

    /**
     * Refusal message, if any.
     *
     * @return string|null
     */
    public function get_refusal(): ?string {
        return $this->refusal;
    }
}
