<?php
// This file is part of Moodle and is licensed under the
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

namespace local_moderncommerce\notifications\local;

/**
 * Marketing-notification opt-out (unsubscribe) list.
 *
 * Only marketing-category sends consult this; transactional/reminder/dunning always
 * deliver. The unsubscribe landing page writes records here.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suppression {
    /** @var string Table. */
    private const TABLE = 'local_moderncommerce_notify_suppression';

    /**
     * Whether a recipient has opted out of a marketing notification.
     *
     * @param \stdClass $user Recipient.
     * @param string $category Category being sent.
     * @param string $eventkey Event key being sent.
     * @return bool
     */
    public static function is_suppressed(\stdClass $user, string $category, string $eventkey): bool {
        global $DB;

        $userid = (int) ($user->id ?? 0);
        $email = (string) ($user->email ?? '');

        $where = [];
        $params = [];
        if ($userid > 0) {
            $where[] = 'userid = :uid';
            $params['uid'] = $userid;
        }
        if ($email !== '') {
            $where[] = 'email = :email';
            $params['email'] = $email;
        }
        if (empty($where)) {
            return false;
        }

        // Scope: 'all', or matching this category/eventkey.
        $sql = '(' . implode(' OR ', $where) . ')'
            . " AND scope IN ('all', :cat, :evt)";
        $params['cat'] = $category;
        $params['evt'] = $eventkey;

        return $DB->record_exists_select(self::TABLE, $sql, $params);
    }

    /**
     * Add a suppression record (one-click unsubscribe / bounce / complaint).
     *
     * @param int $userid User id (0 if unknown).
     * @param string|null $email Email address.
     * @param string $scope all|category|eventkey value.
     * @param string $reason unsubscribe|bounce|complaint.
     * @param string|null $token Signed token used.
     * @return int New record id.
     */
    public static function add(
        int $userid,
        ?string $email,
        string $scope = 'all',
        string $reason = 'unsubscribe',
        ?string $token = null
    ): int {
        global $DB;

        return (int) $DB->insert_record(self::TABLE, (object) [
            'userid' => $userid,
            'email' => $email,
            'scope' => $scope,
            'reason' => $reason,
            'token' => $token,
            'timecreated' => time(),
        ]);
    }

    /**
     * Idempotently suppress all marketing for a recipient (used by unsubscribe.php).
     *
     * @param int $userid User id (0 if unknown).
     * @param string|null $email Email address.
     * @param string|null $token Signed token used.
     * @return void
     */
    public static function suppress_all(int $userid, ?string $email, ?string $token = null): void {
        global $DB;

        $params = [];
        $where = [];
        if ($userid > 0) {
            $where[] = 'userid = :uid';
            $params['uid'] = $userid;
        }
        if (!empty($email)) {
            $where[] = 'email = :email';
            $params['email'] = $email;
        }
        if (empty($where)) {
            return;
        }
        $exists = $DB->record_exists_select(
            self::TABLE,
            '(' . implode(' OR ', $where) . ") AND scope = 'all'",
            $params
        );
        if (!$exists) {
            self::add($userid, $email, 'all', 'unsubscribe', $token);
        }
    }

    /**
     * The per-site secret used to sign unsubscribe tokens (generated on first use).
     *
     * @return string
     */
    public static function secret(): string {
        $secret = get_config('local_moderncommerce', 'notify_unsub_secret');
        if (empty($secret)) {
            $secret = bin2hex(random_bytes(32));
            set_config('notify_unsub_secret', $secret, 'local_moderncommerce');
        }
        return $secret;
    }

    /**
     * Compute the signed unsubscribe token for a recipient.
     *
     * @param int $userid User id.
     * @param string $email Email address.
     * @return string
     */
    public static function make_token(int $userid, string $email): string {
        $normalised = \core_text::strtolower(trim($email));
        return hash_hmac('sha256', $userid . '|' . $normalised, self::secret());
    }

    /**
     * Verify an unsubscribe token.
     *
     * @param int $userid User id.
     * @param string $email Email address.
     * @param string $token Token to check.
     * @return bool
     */
    public static function verify_token(int $userid, string $email, string $token): bool {
        return $token !== '' && hash_equals(self::make_token($userid, $email), $token);
    }

    /**
     * Build the one-click unsubscribe URL for a recipient.
     *
     * @param int $userid User id.
     * @param string $email Email address.
     * @return string
     */
    public static function unsubscribe_url(int $userid, string $email): string {
        return (new \moodle_url('/local/moderncommerce/unsubscribe.php', [
            'u' => $userid,
            'e' => $email,
            't' => self::make_token($userid, $email),
        ]))->out(false);
    }
}
