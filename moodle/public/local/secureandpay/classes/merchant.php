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

/**
 * A SecureAndPay merchant account: where an order's money goes and which key signs its messages.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class merchant {

    /**
     * Constructor.
     *
     * @param string $siteid SecureAndPay IdSite.
     * @param string $key Signing key.
     * @param bool $testmode Use the SecureAndPay test environment.
     * @param string $owner Who the account belongs to, for logs: 'platform' or e.g. 'creator:12'.
     */
    public function __construct(
        /** @var string SecureAndPay IdSite. */
        public readonly string $siteid,
        /** @var string Signing key. */
        public readonly string $key,
        /** @var bool Test environment. */
        public readonly bool $testmode,
        /** @var string Account owner. */
        public readonly string $owner = 'platform',
    ) {
    }

    /**
     * Whether the account can take payments.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return $this->siteid !== '' && $this->key !== '';
    }

    /**
     * HMAC-SHA256 of a base64 payload with this account's key.
     *
     * @param string $data Base64 payload.
     * @return string
     */
    public function sign(string $data): string {
        return hash_hmac('sha256', $data, $this->key);
    }
}
