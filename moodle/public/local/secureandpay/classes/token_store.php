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
 * Card tokens returned by SecureAndPay, kept per user and per merchant account for one-click payments.
 *
 * A token only works with the SecureAndPay account (IdSite) that saved the card, so a buyer has one
 * token per seller. No card data is stored in Moodle.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_store {

    /** User preference prefix; the full name ends with the IdSite. Value: {"token": ..., "expiry": "YYMM"}. */
    public const PREFERENCE = 'local_secureandpay_card';

    /**
     * Preference name for a merchant account.
     *
     * @param string $siteid SecureAndPay IdSite.
     * @return string
     */
    private static function name(string $siteid): string {
        return self::PREFERENCE . '_' . preg_replace('/[^A-Za-z0-9]/', '', $siteid);
    }

    /**
     * Save the token of the card a user just paid with on a merchant account.
     *
     * @param int $userid User.
     * @param string $siteid SecureAndPay IdSite.
     * @param string $token SecureAndPay token.
     * @param string $expiry Card expiry as YYMM ('' if unknown).
     */
    public static function save(int $userid, string $siteid, string $token, string $expiry): void {
        set_user_preference(self::name($siteid), json_encode(['token' => $token, 'expiry' => $expiry]), $userid);
    }

    /**
     * Token of the user's card saved on a merchant account, if the card has not expired.
     *
     * @param int $userid User.
     * @param string $siteid SecureAndPay IdSite.
     * @return string|null
     */
    public static function get(int $userid, string $siteid): ?string {
        $saved = json_decode((string) get_user_preferences(self::name($siteid), '', $userid), true);
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
     * All saved cards of a user: IdSite => expiry.
     *
     * @param int $userid User.
     * @return array
     */
    public static function list(int $userid): array {
        $cards = [];
        foreach (get_user_preferences(null, null, $userid) as $name => $value) {
            if (str_starts_with($name, self::PREFERENCE . '_')) {
                $saved = json_decode((string) $value, true);
                $cards[substr($name, strlen(self::PREFERENCE) + 1)] = $saved['expiry'] ?? '';
            }
        }
        return $cards;
    }

    /**
     * Forget the user's card saved on a merchant account.
     *
     * @param int $userid User.
     * @param string $siteid SecureAndPay IdSite.
     */
    public static function delete(int $userid, string $siteid): void {
        unset_user_preference(self::name($siteid), $userid);
    }
}
