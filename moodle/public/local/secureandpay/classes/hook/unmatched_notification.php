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
 * A notification whose orderID no order here was paid with: a schedule started on another platform, for instance.
 *
 * A callback that recognises it either gives the order it belongs to (the notification is then applied as usual) or
 * an outcome of its own (kept aside, for instance). Left alone, the notification is rejected as before. A callback
 * must check who signed the notification (signed_by()) before changing anything.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Recognise a SecureAndPay notification that matches no order paid here')]
#[\core\attribute\tags('payment')]
final class unmatched_notification {

    /** @var object|null Order given by a callback. */
    private ?object $order = null;

    /** @var string|null Outcome given by a callback. */
    private ?string $outcome = null;

    /**
     * Constructor.
     *
     * @param array $data Notification, decoded.
     * @param string $rawdata Base64 'data' field as posted.
     * @param string $signature 'signature' field as posted.
     */
    public function __construct(
        /** @var array Notification, decoded. */
        public readonly array $data,
        /** @var string Base64 payload as posted. */
        public readonly string $rawdata,
        /** @var string Signature as posted. */
        public readonly string $signature,
    ) {
    }

    /**
     * Whether the notification was signed with the key of this account.
     *
     * @param merchant $merchant Account.
     * @return bool
     */
    public function signed_by(merchant $merchant): bool {
        return $merchant->is_configured()
            && hash_equals($merchant->sign($this->rawdata), strtolower(trim($this->signature)));
    }

    /**
     * The notification belongs to this order: it is applied to it as usual.
     *
     * @param object $order Modern Commerce order.
     */
    public function set_order(object $order): void {
        $this->order = $order;
    }

    /**
     * The notification was dealt with otherwise: this is answered to SecureAndPay, nothing else is done.
     *
     * @param string $outcome Outcome, for logging.
     */
    public function set_outcome(string $outcome): void {
        $this->outcome = $outcome;
    }

    /**
     * Order given by a callback, if any.
     *
     * @return object|null
     */
    public function get_order(): ?object {
        return $this->order;
    }

    /**
     * Outcome given by a callback, if any.
     *
     * @return string|null
     */
    public function get_outcome(): ?string {
        return $this->outcome;
    }
}
