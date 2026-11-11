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

/**
 * A charge (paid or refused) of a schedule started on another platform and taken over here (gateway::is_resumed()),
 * before it is applied: a callback may keep it aside, for instance when SecureAndPay numbers it as an instalment
 * already paid on the other platform, or as another one than the next one counted here.
 *
 * Dispatched once the signature has been verified with the key of the account that took the order, and only for a
 * charge not recorded yet (a repeated notification goes on to be answered as a duplicate). Left alone, the charge is
 * applied as usual.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Keep aside a SecureAndPay charge of a schedule taken over from another platform')]
#[\core\attribute\tags('payment')]
final class resumed_charge {

    /** @var string|null Outcome given by a callback. */
    private ?string $outcome = null;

    /**
     * Constructor.
     *
     * @param object $order Order of the schedule taken over.
     * @param array $data Notification, decoded and verified.
     * @param string $rawdata Base64 'data' field as posted.
     * @param string $signature 'signature' field as posted.
     * @param int $paid Instalments counted as paid so far (those paid on the other platform included).
     */
    public function __construct(
        /** @var object Order of the schedule taken over. */
        public readonly object $order,
        /** @var array Notification, decoded and verified. */
        public readonly array $data,
        /** @var string Base64 payload as posted. */
        public readonly string $rawdata,
        /** @var string Signature as posted. */
        public readonly string $signature,
        /** @var int Instalments counted as paid so far. */
        public readonly int $paid,
    ) {
    }

    /**
     * The instalment as SecureAndPay numbers it (echeanceNumber, the first payment being number 1).
     *
     * @return int 0 when the notification does not tell.
     */
    public function number(): int {
        $number = $this->data['echeanceNumber'] ?? null;
        return is_numeric($number) ? max(0, (int) $number) : 0;
    }

    /**
     * The charge was kept aside: this is answered to SecureAndPay, nothing else is done.
     *
     * @param string $outcome Outcome, for logging.
     */
    public function set_outcome(string $outcome): void {
        $this->outcome = $outcome;
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
