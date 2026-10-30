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
 * The e-mail a new member of a trainer's course receives, written by the trainer (subject, content, sender name).
 *
 * Once switched on for a course, it is sent at enrolment (purchase, access given by hand, import, as chosen) and
 * replaces the standard e-mails of the platform for that enrolment: Moodle's course welcome and the shop's
 * enrolment confirmation (order receipts stay). Instead of a password, it carries a link to choose one.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_mail {

    /** Settings per course. */
    const TABLE = 'local_oriskill_accessmails';

    /** Access e-mails already sent (one per member and course). */
    const SENT = 'local_oriskill_accesssent';

    /** Links to choose a password. */
    const TOKENS = 'local_oriskill_pwtokens';

    /** Days a link to choose a password stays valid. */
    const TOKEN_DAYS = 7;

    /** Variables of the subject and the content. */
    const PLACEHOLDERS = ['[[PRENOM]]', '[[NOM]]', '[[EMAIL]]', '[[FORMATION]]', '[[URL_FORMATION]]', '[[LIEN_ACTIVATION]]',
        '[[URL_CONNEXION]]', '[[SITE_NAME]]', '[[CLIENT_NAME]]'];

    /** How a member arrived: purchase (or free sign-up), manual (access given by the trainer), import. */
    const TRIGGERS = ['purchase', 'manual', 'import'];

    /** @var string|null Trigger of the enrolments being made (set around manual access and imports). */
    protected static ?string $trigger = null;

    /**
     * Make enrolments with a given trigger.
     *
     * @param string $trigger manual or import.
     * @param callable $enrol Makes the enrolments.
     * @return mixed What $enrol returns.
     */
    public static function with_trigger(string $trigger, callable $enrol) {
        $previous = self::$trigger;
        self::$trigger = $trigger;
        try {
            return $enrol();
        } finally {
            self::$trigger = $previous;
        }
    }

    /**
     * Trigger of the enrolments being made, if set.
     *
     * @return string|null
     */
    public static function current_trigger(): ?string {
        return self::$trigger;
    }

    /**
     * Settings of a course, with the default text when the trainer has not written theirs.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $creator Trainer.
     * @return \stdClass {enabled, onpurchase, onmanual, onimport, sendername, subject, body, bodyformat}
     */
    public static function settings(\stdClass $course, \stdClass $creator): \stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['courseid' => $course->id]);
        return $record ?: (object) ['id' => 0, 'courseid' => $course->id, 'enabled' => 0, 'onpurchase' => 1, 'onmanual' => 1,
            'onimport' => 0, 'sendername' => format_string($creator->displayname),
            'subject' => get_string('accessmail:defaultsubject', 'local_oriskill'),
            'body' => get_string('accessmail:defaultbody', 'local_oriskill'), 'bodyformat' => FORMAT_HTML];
    }

    /**
     * Save the settings of a course.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $data Form data.
     */
    public static function save(\stdClass $course, \stdClass $data): void {
        global $DB;

        $record = (object) ['courseid' => $course->id, 'enabled' => empty($data->enabled) ? 0 : 1,
            'onpurchase' => empty($data->onpurchase) ? 0 : 1, 'onmanual' => empty($data->onmanual) ? 0 : 1,
            'onimport' => empty($data->onimport) ? 0 : 1,
            'sendername' => \core_text::substr(trim((string) $data->sendername), 0, 100),
            'subject' => \core_text::substr(trim((string) $data->subject), 0, 255),
            'body' => (string) $data->body['text'], 'bodyformat' => (int) $data->body['format'], 'timemodified' => time()];
        if ($id = $DB->get_field(self::TABLE, 'id', ['courseid' => $course->id])) {
            $record->id = $id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Whether the access e-mail of a course goes out for a way of arriving.
     *
     * @param int $courseid Course.
     * @param string $trigger One of TRIGGERS.
     * @return bool
     */
    public static function will_send(int $courseid, string $trigger): bool {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['courseid' => $courseid, 'enabled' => 1]);
        return $record && !empty($record->{'on' . $trigger});
    }

    /**
     * Someone was enrolled in a course: send its access e-mail, once, if the trainer wants it for that way of arriving.
     *
     * @param \core\event\user_enrolment_created $event Event.
     */
    public static function user_enrolled(\core\event\user_enrolment_created $event): void {
        global $DB;

        $trigger = self::$trigger ?? 'purchase';
        $courseid = (int) $event->courseid;
        $userid = (int) $event->relateduserid;
        if (!self::will_send($courseid, $trigger) || $DB->record_exists(self::SENT, ['courseid' => $courseid, 'userid' => $userid])) {
            return;
        }
        $course = get_course($courseid);
        $creator = seller_service::owner_of_course($courseid);
        $user = \core_user::get_user($userid);
        if (!$creator || !$user || $user->deleted || $userid == $creator->userid) {
            return;
        }
        $DB->insert_record(self::SENT, (object) ['courseid' => $courseid, 'userid' => $userid, 'timecreated' => time()]);
        try {
            self::send($course, $creator, $user, self::settings($course, $creator));
        } catch (\Throwable $e) {
            debugging('Access e-mail: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Send the access e-mail of a course to someone.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $creator Trainer.
     * @param \stdClass $user Recipient.
     * @param \stdClass $settings settings().
     * @param bool $test A test sent to the trainer (no real link to choose a password).
     * @return bool Sent.
     */
    public static function send(\stdClass $course, \stdClass $creator, \stdClass $user, \stdClass $settings, bool $test = false): bool {
        global $DB;

        $values = [
            '[[PRENOM]]' => $user->firstname, '[[NOM]]' => $user->lastname, '[[EMAIL]]' => $user->email,
            '[[FORMATION]]' => format_string($course->fullname),
            '[[URL_FORMATION]]' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            '[[LIEN_ACTIVATION]]' => $test ? (new \moodle_url('/local/oriskill/setpassword.php', ['t' => 'exemple']))->out(false)
                : self::password_link($user, (int) $course->id)->out(false),
            '[[URL_CONNEXION]]' => (new \moodle_url('/login/index.php'))->out(false),
            '[[SITE_NAME]]' => format_string($creator->displayname),
            '[[CLIENT_NAME]]' => trim((string) $creator->legalname) ?: format_string($creator->displayname),
        ];
        $subject = strtr($settings->subject, $values);
        $html = format_text(strtr($settings->body, array_map('s', $values)), $settings->bodyformat,
            ['context' => \context_course::instance($course->id), 'noclean' => false, 'para' => false]);
        $from = \core_user::get_noreply_user();
        $from->firstname = trim((string) $settings->sendername) ?: format_string($creator->displayname);
        $from->lastname = '';
        $replyto = (string) $DB->get_field('user', 'email', ['id' => $creator->userid]);
        return (bool) email_log::for_creator((int) $creator->id, 'transactional', fn() => email_to_user($user, $from, $subject,
            html_to_text($html), $html, '', '', true, $replyto, $from->firstname));
    }

    /**
     * The standard enrolment e-mails are replaced by the access e-mail just sent for the same course: Moodle's
     * welcome and the shop's enrolment confirmation (recognised by their subject; the order receipts are left alone).
     *
     * @param \core\hook\email\before_email_to_user $hook Hook.
     */
    public static function before_email_to_user(\core\hook\email\before_email_to_user $hook): void {
        global $DB;

        $email = $hook->email;
        if (during_initial_install() || empty($email->user->id) || $email->user->id < 0) {
            return;
        }
        // An import stays silent: no standard course welcome (only the access e-mail or the credentials, if chosen).
        if (self::$trigger === 'import') {
            [$before, $after] = array_pad(explode('%%', get_string('welcometocourse', 'moodle', '%%'), 2), 2, '');
            if (str_starts_with($email->subject, $before) && str_ends_with($email->subject, $after)
                    && \core_text::strlen($email->subject) > \core_text::strlen($before . $after)) {
                $email->add_block_reason('course welcome during an import');
                return;
            }
        }
        // Courses whose access e-mail this person received in the last minutes.
        $sent = $DB->get_fieldset_select(self::SENT, 'courseid', 'userid = ? AND timecreated > ?',
            [$email->user->id, time() - 15 * MINSECS]);
        if (!$sent) {
            return;
        }
        $lang = $email->user->lang ?? null;
        foreach ($sent as $courseid) {
            $name = format_string((string) $DB->get_field('course', 'fullname', ['id' => $courseid]));
            if (in_array($email->subject, [get_string('welcometocourse', 'moodle', $name),
                    get_string_manager()->get_string('welcometocourse', 'moodle', $name, $lang)], true)) {
                $email->add_block_reason('replaced by the access e-mail of course ' . $courseid);
                return;
            }
        }
        $mcsubject = str_replace('{firstname}', (string) $email->user->firstname,
            get_string_manager()->get_string('emailtpl_moderncommerce_enrollment_confirmation_subject', 'local_moderncommerce', null,
                $lang));
        if ($email->subject === $mcsubject && preg_match('/\bMC-\d{8}-[A-Z0-9]{8}\b/', $email->messagetext . $email->messagehtml, $m)) {
            $orderid = (int) $DB->get_field('local_moderncommerce_orders', 'id', ['ordernumber' => $m[0]]);
            $ordered = array_map('intval', $DB->get_fieldset_select('local_moderncommerce_order_items', 'courseid',
                'orderid = ? AND courseid IS NOT NULL', [$orderid]));
            if (array_intersect($ordered, array_map('intval', $sent))) {
                $email->add_block_reason('replaced by the access e-mail of the course ordered');
            }
        }
    }

    /**
     * Link to choose a password, valid TOKEN_DAYS days, usable once; then on to the course.
     *
     * @param \stdClass $user Account.
     * @param int $courseid Course to open afterwards.
     * @return \moodle_url
     */
    public static function password_link(\stdClass $user, int $courseid = 0): \moodle_url {
        global $DB;

        $token = random_string(40);
        $DB->insert_record(self::TOKENS, (object) ['userid' => $user->id, 'token' => hash('sha256', $token),
            'courseid' => $courseid, 'expires' => time() + self::TOKEN_DAYS * DAYSECS, 'used' => 0, 'timecreated' => time()]);
        return new \moodle_url('/local/oriskill/setpassword.php', ['t' => $token]);
    }

    /**
     * The valid link record of a token, or null.
     *
     * @param string $token Token from the link.
     * @return \stdClass|null
     */
    public static function token(string $token): ?\stdClass {
        global $DB;

        if (strlen($token) !== 40) {
            return null;
        }
        $record = $DB->get_record(self::TOKENS, ['token' => hash('sha256', $token)]);
        return $record && !$record->used && $record->expires > time() ? $record : null;
    }

    /**
     * Choose the password of a link: saved, the link can no longer be used, other sessions are closed.
     *
     * @param \stdClass $record token().
     * @param string $password New password (checked against the site's policy).
     * @return \stdClass The account.
     */
    public static function use_token(\stdClass $record, string $password): \stdClass {
        global $DB;

        $user = \core_user::get_user($record->userid, '*', MUST_EXIST);
        $errmsg = '';
        if (!check_password_policy($password, $errmsg, $user)) {
            throw new \moodle_exception('accessmail:weakpassword', 'local_oriskill', '', $errmsg);
        }
        update_internal_user_password($user, $password);
        unset_user_preference('auth_forcepasswordchange', $user);
        $DB->set_field(self::TOKENS, 'used', time(), ['id' => $record->id]);
        \core\session\manager::kill_user_sessions($user->id);
        return \core_user::get_user($user->id);
    }
}
