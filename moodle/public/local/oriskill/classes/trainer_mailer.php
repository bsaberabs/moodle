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
 * A trainer's own sending server: the e-mails sent for them leave from their address (e.g. contact@their-domain.com),
 * through their provider (Brevo, Infomaniak, Mailgun...), which signs them for their domain (SPF, DKIM).
 *
 * Moodle has one mailer for the site: for an e-mail of a trainer with their own server, the SMTP settings and the
 * sending address are switched to theirs just before it leaves, and switched back before the next e-mail.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trainer_mailer {

    /** Settings per trainer. */
    const TABLE = 'local_oriskill_smtp';

    /** Encryption choices. */
    const SECURE = ['', 'tls', 'ssl'];

    /** Site settings switched for a trainer's e-mail. */
    const SITE_SETTINGS = ['smtphosts', 'smtpsecure', 'smtpuser', 'smtppass', 'noreplyaddress'];

    /** @var array|null Site settings saved while a trainer's server is in use. */
    protected static ?array $saved = null;

    /**
     * A trainer's sending settings (the password stays encrypted).
     *
     * @param \stdClass $creator Trainer.
     * @return \stdClass|null
     */
    public static function settings(\stdClass $creator): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['creatorid' => $creator->id]) ?: null;
    }

    /**
     * Save a trainer's sending settings; an empty password keeps the saved one.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data enabled, host, port, secure, username, password, fromemail.
     */
    public static function save(\stdClass $creator, \stdClass $data): void {
        global $DB;

        $host = trim((string) $data->host);
        $fromemail = \core_text::strtolower(trim((string) $data->fromemail));
        if (!empty($data->enabled) && ($host === '' || !preg_match('/^[a-z0-9.-]+$/i', $host) || !validate_email($fromemail))) {
            throw new \moodle_exception('smtp:invalid', 'local_oriskill');
        }
        $record = (object) ['creatorid' => $creator->id, 'enabled' => empty($data->enabled) ? 0 : 1, 'host' => $host,
            'port' => min(65535, max(1, (int) ($data->port ?: 587))),
            'secure' => in_array($data->secure ?? '', self::SECURE, true) ? $data->secure : 'tls',
            'username' => trim((string) $data->username), 'fromemail' => $fromemail, 'timemodified' => time()];
        if (trim((string) $data->password) !== '') {
            $record->password = \core\encryption::encrypt(trim((string) $data->password));
        }
        if ($existing = self::settings($creator)) {
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->password = $record->password ?? '';
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Send the e-mail about to leave through the trainer's server when they have one, else through the site's.
     *
     * @param \stdClass|null $creator Trainer the e-mail is sent for, null for the platform.
     */
    public static function prepare(?\stdClass $creator): void {
        global $CFG;

        // On a test copy of the platform, every e-mail stays in its test mailbox.
        $settings = $creator && empty($CFG->oriskill_staging) ? self::settings($creator) : null;
        if (!$settings || !$settings->enabled || $settings->host === '') {
            self::restore();
            return;
        }
        self::switch_to($settings);
    }

    /**
     * Use a trainer's server for the next e-mails.
     *
     * @param \stdClass $settings settings().
     */
    protected static function switch_to(\stdClass $settings): void {
        global $CFG;

        if (self::$saved === null) {
            self::$saved = [];
            foreach (self::SITE_SETTINGS as $name) {
                self::$saved[$name] = $CFG->$name ?? null;
            }
        }
        $CFG->smtphosts = $settings->host . ':' . (int) $settings->port;
        $CFG->smtpsecure = $settings->secure;
        $CFG->smtpuser = $settings->username;
        $CFG->smtppass = $settings->password !== '' ? \core\encryption::decrypt($settings->password) : '';
        $CFG->noreplyaddress = $settings->fromemail;
        // Moodle keeps its mailer between e-mails: start a new one with these settings.
        get_mailer('close');
    }

    /**
     * Back to the site's server (before an e-mail that is not a trainer's).
     */
    public static function restore(): void {
        global $CFG;

        if (self::$saved === null) {
            return;
        }
        foreach (self::$saved as $name => $value) {
            if ($value === null) {
                unset($CFG->$name);
            } else {
                $CFG->$name = $value;
            }
        }
        self::$saved = null;
        get_mailer('close');
    }

    /**
     * Send a test e-mail to the trainer through their server.
     *
     * @param \stdClass $creator Trainer.
     * @return bool Sent.
     */
    public static function test(\stdClass $creator): bool {
        $trainer = \core_user::get_user($creator->userid, '*', MUST_EXIST);
        $name = format_string($creator->displayname);
        try {
            return (bool) email_log::for_creator((int) $creator->id, 'transactional', fn() => email_to_user($trainer,
                \core_user::get_noreply_user(), get_string('smtp:testsubject', 'local_oriskill', $name),
                get_string('smtp:testbody', 'local_oriskill', $name)));
        } finally {
            self::restore();
        }
    }
}
