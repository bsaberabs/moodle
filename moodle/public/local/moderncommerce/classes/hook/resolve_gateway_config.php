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
 * Lets other plugins collect a purchase on another account of a gateway (a marketplace seller's own Stripe or
 * PayPal account) instead of the store's.
 *
 * Oriskill addition (not in upstream Modern Commerce): dispatched by gateway_manager::get_gateway_config()
 * while a payment context is set (checkout methods for an order or a cart, payment start, browser return,
 * webhook). A plugin that takes the purchase calls use_account(): the gateway then runs entirely on that
 * account's settings, and gateway_manager only records a payment for an order of that same account.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to collect a purchase on another account of a payment gateway.')]
#[\core\attribute\tags('local_moderncommerce', 'payment')]
final class resolve_gateway_config {

    /** @var string Account the purchase is collected on: '' for the store's own account. */
    private string $account = '';

    /**
     * Constructor.
     *
     * @param string $gatewayid Gateway ID.
     * @param array $config Store settings of the gateway.
     * @param object|null $order Order being paid, if known.
     * @param int $userid Buyer, for a purchase still in the cart.
     * @param string|null $webhookaccount Account named in the webhook address, for a webhook call.
     */
    public function __construct(
        /** @var string Gateway ID. */
        public readonly string $gatewayid,
        /** @var array Gateway settings: the store's, or the account's after use_account(). */
        public array $config,
        /** @var object|null Order being paid, if known. */
        public readonly ?object $order = null,
        /** @var int Buyer, for a purchase still in the cart. */
        public readonly int $userid = 0,
        /** @var string|null Account named in the webhook address, for a webhook call. */
        public readonly ?string $webhookaccount = null,
    ) {
    }

    /**
     * Collect this purchase on another account.
     *
     * @param string $account Account identifier (letters, digits, '-' and '_'), also used in its webhook address.
     * @param array $config Complete gateway settings of that account; 'enabled' false when it does not accept
     *                      this gateway (the method is then not offered).
     */
    public function use_account(string $account, array $config): void {
        $this->account = $account;
        $this->config = $config;
    }

    /**
     * Account the purchase is collected on.
     *
     * @return string '' for the store's own account.
     */
    public function get_account(): string {
        return $this->account;
    }
}
