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
 * Card token returned by SecureAndPay, kept per user for one-click payments.
 *
 * The token only works for this merchant at SecureAndPay; no card data is stored in Moodle.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_store {

    /** User preference holding {"token": ..., "expiry": "YYMM"}. */
    public const PREFERENCE = 'local_secureandpay_card';

    /**
     * Save the token of the card a user just paid with.
     *
     * @param int $userid User.
     * @param string $token SecureAndPay token.
     * @param string $expiry Card expiry as YYMM ('' if unknown).
     */
    public static function save(int $userid, string $token, string $expiry): void {
        set_user_preference(self::PREFERENCE, json_encode(['token' => $token, 'expiry' => $expiry]), $userid);
    }

    /**
     * Token of the user's saved card, if the card has not expired.
     *
     * @param int $userid User.
     * @return string|null
     */
    public static function get(int $userid): ?string {
        $saved = json_decode((string) get_user_preferences(self::PREFERENCE, '', $userid), true);
        if (empty($saved['token'])) {
            return null;
        }
        // A card is valid until the end of its expiry month.
        if (preg_match('/^\d{4}$/', (string) ($saved['expiry'] ?? '')) && $saved['expiry'] < date('ym')) {
            return null;
        }
        return (string) $saved['token'];
    }

    /**
     * Forget the user's saved card.
     *
     * @param int $userid User.
     */
    public static function delete(int $userid): void {
        unset_user_preference(self::PREFERENCE, $userid);
    }
}
