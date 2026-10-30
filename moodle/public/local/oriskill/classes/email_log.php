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
 * Every e-mail the platform sends to a person, for support ("I received nothing"): subject, content, date, opening.
 *
 * E-mails are noted just before they leave (hook before_email_to_user) and get an invisible image that tells when
 * they are opened. An e-mail belongs to a trainer when it is sent for them (set by the code that sends it) or when it
 * is about one of their orders; the others are the platform's own (password, account...).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_log {

    /** E-mails sent. */
    const TABLE = 'local_oriskill_emails';

    /** Kinds of e-mails. */
    const KINDS = ['transactional', 'marketing', 'direct'];

    /** Days the e-mails are kept. */
    const KEEP_DAYS = 365;

    /** Content kept at most (bytes). */
    const MAX_BODY = 200000;

    /** @var array|null Trainer and kind of the e-mails being sent: [creatorid, kind]. */
    protected static ?array $context = null;

    /**
     * Send e-mails for a trainer: those sent by $send are theirs, of this kind.
     *
     * @param int $creatorid Trainer.
     * @param string $kind One of KINDS.
     * @param callable $send Sends the e-mails.
     * @return mixed What $send returns.
     */
    public static function for_creator(int $creatorid, string $kind, callable $send) {
        $previous = self::$context;
        self::$context = [$creatorid, in_array($kind, self::KINDS, true) ? $kind : 'transactional'];
        try {
            return $send();
        } finally {
            self::$context = $previous;
        }
    }

    /**
     * Note an e-mail about to leave, and add the image that tells when it is opened.
     *
     * @param \core\hook\email\before_email_to_user $hook Hook.
     */
    public static function before_email_to_user(\core\hook\email\before_email_to_user $hook): void {
        global $DB;

        $email = $hook->email;
        $userid = (int) ($email->user->id ?? 0);
        // People without an account are noted only when written to from the CRM.
        $direct = self::$context && self::$context[1] === 'direct';
        if (($userid <= 0 && !$direct) || during_initial_install() || !empty($email->user->deleted)) {
            return;
        }
        $userid = max(0, $userid);
        [$creatorid, $kind] = self::$context ?? [self::creator_of_order($email->subject . ' ' . $email->messagetext)
            ?: self::creator_of_course($email->subject), 'transactional'];
        $record = (object) ['userid' => $userid, 'creatorid' => $creatorid, 'kind' => $kind,
            'email' => \core_text::substr((string) $email->user->email, 0, 100),
            'subject' => \core_text::substr($email->subject, 0, 255),
            'bodyhtml' => self::cut($email->messagehtml), 'bodytext' => self::cut($email->messagetext),
            'attachment' => \core_text::substr((string) $email->attachname, 0, 255), 'status' => 'sent',
            'timeopened' => 0, 'opens' => 0, 'timecreated' => time()];
        try {
            $record->id = $DB->insert_record(self::TABLE, $record);
        } catch (\Throwable $e) {
            // Never a reason for an e-mail not to leave.
            debugging('E-mail log: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return;
        }
        if ($email->messagehtml !== '') {
            $pixel = \html_writer::empty_tag('img', ['src' => self::pixel_url((int) $record->id)->out(false), 'width' => 1,
                'height' => 1, 'alt' => '', 'style' => 'display:block;width:1px;height:1px;border:0']);
            $email->messagehtml = stripos($email->messagehtml, '</body>') !== false
                ? preg_replace('~</body>~i', $pixel . '</body>', $email->messagehtml, 1) : $email->messagehtml . $pixel;
        }
        // Now and then, forget the oldest e-mails.
        if (random_int(1, 200) === 1) {
            $DB->delete_records_select(self::TABLE, 'timecreated < ?', [time() - self::KEEP_DAYS * DAYSECS]);
        }
    }

    /**
     * An e-mail could not be sent (Moodle reports it): mark the latest one with that subject.
     *
     * @param \core\event\email_failed $event Event.
     */
    public static function email_failed(\core\event\email_failed $event): void {
        global $DB;

        $latest = $DB->get_records(self::TABLE, ['userid' => $event->relateduserid], 'id DESC', 'id, subject', 0, 5);
        foreach ($latest as $row) {
            if ($row->subject === \core_text::substr((string) ($event->other['subject'] ?? ''), 0, 255)) {
                $DB->set_field(self::TABLE, 'status', 'failed', ['id' => $row->id]);
                return;
            }
        }
    }

    /**
     * Trainer of the order an e-mail mentions (order numbers "MC-20261030-AB12CD34").
     *
     * @param string $text Subject and text.
     * @return int Trainer, 0 when none.
     */
    public static function creator_of_order(string $text): int {
        global $DB;

        if (!preg_match('/\bMC-\d{8}-[A-Z0-9]{8}\b/', $text, $m)) {
            return 0;
        }
        $order = $DB->get_record('local_moderncommerce_orders', ['ordernumber' => $m[0]]);
        if (!$order) {
            return 0;
        }
        $sellers = seller_service::purchase_sellers($order, (int) $order->userid);
        return count($sellers) === 1 ? (int) reset($sellers)->id : 0;
    }

    /**
     * Trainer of the course a subject names ("Welcome to <course>" sent on enrolment).
     *
     * @param string $subject Subject.
     * @return int Trainer, 0 when none or several.
     */
    public static function creator_of_course(string $subject): int {
        global $DB;

        if (trim($subject) === '') {
            return 0;
        }
        $creators = $DB->get_fieldset_sql(
            "SELECT DISTINCT c.id FROM {local_oriskill_creators} c JOIN {course} co ON co.category = c.categoryid
              WHERE " . $DB->sql_like('?', $DB->sql_concat("'%'", 'co.fullname', "'%'"), false, false),
            [$subject]);
        return count($creators) === 1 ? (int) reset($creators) : 0;
    }

    /**
     * Address of the image telling an e-mail was opened.
     *
     * @param int $id E-mail.
     * @return \moodle_url
     */
    public static function pixel_url(int $id): \moodle_url {
        return new \moodle_url('/local/oriskill/emailopen.php', ['id' => $id, 'k' => self::key($id)]);
    }

    /**
     * Key of the image of an e-mail (nobody can mark others' e-mails as opened).
     *
     * @param int $id E-mail.
     * @return string
     */
    public static function key(int $id): string {
        return substr(hash_hmac('sha256', 'email-open-' . $id, get_site_identifier()), 0, 16);
    }

    /**
     * The image was loaded: the e-mail was opened.
     *
     * @param int $id E-mail.
     * @param string $key Key.
     */
    public static function opened(int $id, string $key): void {
        global $DB;

        if (!hash_equals(self::key($id), $key)) {
            return;
        }
        $DB->execute("UPDATE {" . self::TABLE . "}
                         SET opens = opens + 1, timeopened = CASE WHEN timeopened = 0 THEN ? ELSE timeopened END
                       WHERE id = ?", [time(), $id]);
    }

    /**
     * E-mails a trainer may see for a person: theirs with their content, the platform's own without it.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $contact Contact.
     * @return array
     */
    public static function for_contact(\stdClass $creator, \stdClass $contact): array {
        global $DB;

        $where = $contact->userid ? '(userid = :userid AND creatorid IN (0, :creatorid))'
            : '(userid = 0 AND email = :email AND creatorid = :creatorid)';
        return $DB->get_records_select(self::TABLE, $where, ['userid' => $contact->userid, 'creatorid' => $creator->id,
            'email' => $contact->email],
            'timecreated DESC, id DESC', 'id, creatorid, kind, subject, attachment, status, timeopened, opens, timecreated', 0, 200);
    }

    /**
     * One of the trainer's e-mails, with its content.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id E-mail.
     * @return \stdClass
     */
    public static function require_own(\stdClass $creator, int $id): \stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
    }

    /**
     * Send one of the trainer's e-mails again, as it was (without its attachment).
     *
     * @param \stdClass $creator Trainer.
     * @param int $id E-mail.
     * @return bool Sent.
     */
    public static function resend(\stdClass $creator, int $id): bool {
        $sent = self::require_own($creator, $id);
        $user = \core_user::get_user($sent->userid);
        if (!$user || $user->deleted) {
            return false;
        }
        // Without the image of the first sending: the new one gets its own.
        $html = preg_replace('~<img[^>]+emailopen\.php[^>]*>~i', '', (string) $sent->bodyhtml);
        return self::for_creator((int) $creator->id, $sent->kind, fn() => email_to_user($user, \core_user::get_noreply_user(),
            $sent->subject, (string) $sent->bodytext, $html));
    }

    /**
     * Write to a contact from their page in the CRM.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $contact Contact.
     * @param string $subject Subject.
     * @param string $message Message (plain text).
     * @return bool Sent.
     */
    public static function send_direct(\stdClass $creator, \stdClass $contact, string $subject, string $message): bool {
        global $DB;

        $subject = trim($subject);
        $message = trim($message);
        if ($subject === '' || $message === '') {
            throw new \moodle_exception('email:empty', 'local_oriskill');
        }
        $user = $contact->userid ? \core_user::get_user($contact->userid) : null;
        if (!$user || $user->deleted) {
            // No account: a recipient made of the contact's address, as for the other e-mails without one.
            $user = \core_user::get_noreply_user();
            $user->id = -1;
            $user->email = $contact->email;
            $user->firstname = $contact->firstname;
            $user->lastname = $contact->lastname;
            $user->mailformat = 1;
        }
        $from = \core_user::get_noreply_user();
        $from->firstname = format_string($creator->displayname);
        $from->lastname = '';
        $replyto = (string) $DB->get_field('user', 'email', ['id' => $creator->userid]);
        $html = text_to_html(s($message), false, false, true);
        return self::for_creator((int) $creator->id, 'direct', fn() => email_to_user($user, $from, $subject, $message, $html,
            '', '', true, $replyto, format_string($creator->displayname)));
    }

    /**
     * Content cut to what is kept.
     *
     * @param string $text Content.
     * @return string
     */
    protected static function cut(string $text): string {
        return strlen($text) > self::MAX_BODY ? \core_text::substr($text, 0, self::MAX_BODY) : $text;
    }
}
