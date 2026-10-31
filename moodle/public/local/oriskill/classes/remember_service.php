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
 * "Stay logged in" for 30 days on a device: a box on the login page; ticked, the device gets a cookie holding a token that
 * logs the person back in when their session has ended.
 *
 * The cookie holds a selector and a secret; only a hash of the secret is stored. The token changes each time it is used,
 * keeps its first expiry, and goes when the person logs out; changing the password removes all the person's tokens. A token
 * whose secret does not match removes all the tokens of its owner (it may have been stolen).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remember_service {

    /** Table. */
    const TABLE = 'local_oriskill_remember';

    /** Cookie. */
    const COOKIE = 'oriskill_remember';

    /** Field of the login form. */
    const FIELD = 'oriskill_remember';

    /** How long a device stays logged in. */
    const DAYS = 30;

    /**
     * Give the current device a token for a user.
     *
     * @param int $userid User.
     * @param int $expires Expiry (0: DAYS from now).
     * @return string Cookie value.
     */
    public static function issue(int $userid, int $expires = 0): string {
        global $DB;

        $selector = random_string(24);
        $secret = random_string(40);
        $expires = $expires ?: time() + self::DAYS * DAYSECS;
        $DB->insert_record(self::TABLE, (object) ['userid' => $userid, 'selector' => $selector,
            'hash' => hash('sha256', $secret), 'expires' => $expires, 'timecreated' => time(), 'lastused' => 0,
            'useragent' => \core_text::substr(\core_useragent::get_user_agent_string() ?: '', 0, 255)]);
        $value = $selector . ':' . $secret;
        self::set_cookie($value, $expires);
        return $value;
    }

    /**
     * After the login form: a token when the box was ticked.
     *
     * @param \core\event\user_loggedin $event Event.
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        if (CLI_SCRIPT || AJAX_SCRIPT || \core\session\manager::is_loggedinas() || empty($_POST[self::FIELD])) {
            return;
        }
        self::issue((int) $event->objectid);
    }

    /**
     * Log back in a person whose session has ended, from the token of their device. Called on each request.
     *
     * @param string|null $cookie Cookie value (tests), null for the request's.
     * @return bool Logged in.
     */
    public static function restore(?string $cookie = null): bool {
        global $DB;

        $cookie = $cookie ?? (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($cookie === '' || (isloggedin() && !isguestuser())) {
            return false;
        }
        [$selector, $secret] = array_pad(explode(':', $cookie, 2), 2, '');
        $token = $selector !== '' ? $DB->get_record(self::TABLE, ['selector' => clean_param($selector, PARAM_ALPHANUM)]) : null;
        if (!$token || $token->expires < time()) {
            if ($token) {
                $DB->delete_records(self::TABLE, ['id' => $token->id]);
            }
            self::set_cookie('', 1);
            return false;
        }
        if (!hash_equals($token->hash, hash('sha256', $secret))) {
            // Right selector, wrong secret: the token may have been copied. All the person's devices log in again.
            $DB->delete_records(self::TABLE, ['userid' => $token->userid]);
            self::set_cookie('', 1);
            return false;
        }
        $user = get_complete_user_data('id', $token->userid);
        if (!$user || $user->deleted || $user->suspended || $user->auth === 'nologin'
                || !is_enabled_auth($user->auth)) {
            $DB->delete_records(self::TABLE, ['id' => $token->id]);
            self::set_cookie('', 1);
            return false;
        }
        $DB->delete_records(self::TABLE, ['id' => $token->id]);
        complete_user_login($user);
        self::issue((int) $user->id, (int) $token->expires);
        return true;
    }

    /**
     * Logged out: this device is forgotten.
     *
     * @param \core\event\user_loggedout $event Event.
     */
    public static function user_loggedout(\core\event\user_loggedout $event): void {
        global $DB;

        [$selector] = explode(':', (string) ($_COOKIE[self::COOKIE] ?? ''), 2);
        if ($selector !== '') {
            $DB->delete_records(self::TABLE, ['selector' => clean_param($selector, PARAM_ALPHANUM)]);
        }
        self::set_cookie('', 1);
    }

    /**
     * Password changed: every device of the person logs in again.
     *
     * @param \core\event\base $event user_password_updated or user_password_policy_failed.
     */
    public static function password_changed(\core\event\base $event): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['userid' => $event->relateduserid ?: $event->userid]);
    }

    /**
     * Expired tokens go. Run with the daily clean-up.
     *
     * @return int Tokens removed.
     */
    public static function cleanup(): int {
        global $DB;

        $count = $DB->count_records_select(self::TABLE, 'expires < ?', [time()]);
        $DB->delete_records_select(self::TABLE, 'expires < ?', [time()]);
        return $count;
    }

    /**
     * Script adding the box to the login form.
     *
     * @return string JavaScript
     */
    public static function login_script(): string {
        $label = json_encode(get_string('remember:label', 'local_oriskill', self::DAYS), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        $field = json_encode(self::FIELD);
        return "(function() {
    var button = document.getElementById('loginbtn');
    if (!button || document.getElementById('oriskill-remember')) { return; }
    var box = document.createElement('div');
    box.className = 'form-check mb-3';
    box.innerHTML = '<input class=\"form-check-input\" type=\"checkbox\" value=\"1\" id=\"oriskill-remember\">' +
        '<label class=\"form-check-label\" for=\"oriskill-remember\"></label>';
    box.querySelector('input').name = $field;
    box.querySelector('label').textContent = $label;
    button.parentNode.insertBefore(box, button);
})();";
    }

    /**
     * Set or clear the cookie.
     *
     * @param string $value Value ('' to clear).
     * @param int $expires Expiry.
     */
    protected static function set_cookie(string $value, int $expires): void {
        global $CFG;

        $_COOKIE[self::COOKIE] = $value;
        if (!headers_sent() && !CLI_SCRIPT) {
            setcookie(self::COOKIE, $value, ['expires' => $expires, 'path' => $CFG->sessioncookiepath ?: '/',
                'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        }
    }
}
