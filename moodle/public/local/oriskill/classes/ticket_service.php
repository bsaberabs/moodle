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
 * Support tickets: a learner writes to a trainer's team, the team answers (or leaves an internal note), each side
 * is told by e-mail.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ticket_service {

    /** Tickets. */
    const TABLE = 'local_oriskill_tickets';

    /** Messages of the tickets. */
    const MESSAGES = 'local_oriskill_ticketmsgs';

    /** open: waiting for the team; answered: waiting for the learner; closed. */
    const STATUSES = ['open', 'answered', 'closed'];

    /** Tickets per page of the team's list. */
    const PERPAGE = 30;

    /** Roles of the team told of a new ticket or a learner's answer (the trainer always is). */
    const NOTIFIED_ROLES = ['admin', 'support'];

    /**
     * Trainers a learner can write to: those whose courses they are enrolled in.
     *
     * @param int $userid Learner.
     * @return \stdClass[] creatorid => creator
     */
    public static function trainers_of(int $userid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT DISTINCT cr.*
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {local_oriskill_creators} cr ON cr.categoryid = c.category
              WHERE ue.userid = ? AND cr.userid <> ?
           ORDER BY cr.displayname", [$userid, $userid]);
    }

    /**
     * Courses of a trainer a learner is enrolled in.
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid Learner.
     * @return array courseid => name
     */
    public static function courses_of(\stdClass $creator, int $userid): array {
        global $DB;

        $courses = $DB->get_records_sql_menu(
            "SELECT DISTINCT c.id, c.fullname
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
              WHERE ue.userid = ? AND c.category = ?
           ORDER BY c.fullname", [$userid, $creator->categoryid]);
        return array_map('format_string', $courses);
    }

    /**
     * Open a ticket.
     *
     * @param \stdClass $creator Trainer written to.
     * @param int $userid Learner.
     * @param string $subject Subject.
     * @param string $message Message (plain text).
     * @param int $courseid Course concerned, 0 for none.
     * @return \stdClass Ticket.
     */
    public static function create(\stdClass $creator, int $userid, string $subject, string $message, int $courseid = 0): \stdClass {
        global $DB;

        $subject = \core_text::substr(trim($subject), 0, 255);
        $message = trim($message);
        if ($subject === '' || $message === '') {
            throw new \moodle_exception('tickets:empty', 'local_oriskill');
        }
        if ($courseid && !isset(self::courses_of($creator, $userid)[$courseid])) {
            $courseid = 0;
        }
        $now = time();
        $ticket = (object) ['creatorid' => $creator->id, 'userid' => $userid, 'courseid' => $courseid ?: null,
            'subject' => $subject, 'status' => 'open', 'timecreated' => $now, 'timemodified' => $now];
        $ticket->id = $DB->insert_record(self::TABLE, $ticket);
        self::add_message($ticket, $userid, $message, false);
        log_service::add($creator, 'ticket_created', ['subject' => $subject], $courseid ?: null, $userid);
        self::notify_team($creator, $ticket, $message, true);
        return $ticket;
    }

    /**
     * Answer a ticket: the learner (it goes back to the team) or the team (the learner is told). An internal note of
     * the team is seen only by the team.
     *
     * @param \stdClass $ticket Ticket.
     * @param int $userid Author.
     * @param string $message Message (plain text).
     * @param bool $fromteam Written by the trainer's team.
     * @param bool $internal Internal note (team only).
     */
    public static function reply(\stdClass $ticket, int $userid, string $message, bool $fromteam, bool $internal = false): void {
        global $DB;

        $message = trim($message);
        if ($message === '') {
            throw new \moodle_exception('tickets:empty', 'local_oriskill');
        }
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $ticket->creatorid], '*', MUST_EXIST);
        self::add_message($ticket, $userid, $message, $internal && $fromteam);
        if ($internal && $fromteam) {
            return;
        }
        $ticket->status = $fromteam ? 'answered' : 'open';
        $ticket->timemodified = time();
        $DB->update_record(self::TABLE, (object) ['id' => $ticket->id, 'status' => $ticket->status,
            'timemodified' => $ticket->timemodified]);
        if ($fromteam) {
            log_service::add($creator, 'ticket_answered', ['subject' => $ticket->subject], $ticket->courseid ?: null,
                (int) $ticket->userid);
            self::notify_learner($creator, $ticket, $message);
        } else {
            self::notify_team($creator, $ticket, $message, false);
        }
    }

    /**
     * Close or reopen a ticket.
     *
     * @param \stdClass $ticket Ticket.
     * @param string $status open or closed (answered stays answered when reopened by the team).
     */
    public static function set_status(\stdClass $ticket, string $status): void {
        global $DB;

        if (!in_array($status, self::STATUSES, true)) {
            throw new \moodle_exception('invalidparameter', 'debug');
        }
        $ticket->status = $status;
        $ticket->timemodified = time();
        $DB->update_record(self::TABLE, (object) ['id' => $ticket->id, 'status' => $status, 'timemodified' => $ticket->timemodified]);
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $ticket->creatorid], '*', MUST_EXIST);
        log_service::add($creator, $status === 'closed' ? 'ticket_closed' : 'ticket_reopened', ['subject' => $ticket->subject],
            $ticket->courseid ?: null, (int) $ticket->userid);
    }

    /**
     * Messages of a ticket, oldest first.
     *
     * @param \stdClass $ticket Ticket.
     * @param bool $internal Include the team's internal notes.
     * @return array
     */
    public static function messages(\stdClass $ticket, bool $internal): array {
        global $DB;

        return $DB->get_records_select(self::MESSAGES, 'ticketid = ?' . ($internal ? '' : ' AND internal = 0'), [$ticket->id],
            'timecreated ASC, id ASC');
    }

    /**
     * A learner's own ticket.
     *
     * @param int $userid Learner.
     * @param int $id Ticket id.
     * @return \stdClass
     */
    public static function require_own(int $userid, int $id): \stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id, 'userid' => $userid], '*', MUST_EXIST);
    }

    /**
     * A ticket of the trainer the team member may see (a coach: only those of the learners they follow).
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Ticket id.
     * @return \stdClass
     */
    public static function require_for_team(\stdClass $creator, int $id): \stdClass {
        global $DB;

        $ticket = $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
        $visible = team_service::visible_learners($creator);
        if ($visible !== null && !in_array((int) $ticket->userid, $visible, true)) {
            throw new \moodle_exception('error:notallowed', 'local_oriskill');
        }
        return $ticket;
    }

    /**
     * Tickets of a trainer for the team, the ones waiting longest first (closed: latest first).
     *
     * @param \stdClass $creator Trainer.
     * @param string $status A status, or '' for all.
     * @param string $search Part of the subject, or of the learner's name or e-mail.
     * @param int $page Page, from 0.
     * @return array [tickets, total]
     */
    public static function list(\stdClass $creator, string $status, string $search = '', int $page = 0): array {
        global $DB;

        $where = ['t.creatorid = :creatorid'];
        $params = ['creatorid' => $creator->id];
        if (in_array($status, self::STATUSES, true)) {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }
        if (($visible = team_service::visible_learners($creator)) !== null) {
            [$insql, $inparams] = $DB->get_in_or_equal($visible ?: [0], SQL_PARAMS_NAMED);
            $where[] = "t.userid $insql";
            $params += $inparams;
        }
        if (($search = trim($search)) !== '') {
            $where[] = '(' . $DB->sql_like('t.subject', ':s1', false, false) . ' OR ' .
                $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':s2', false, false) . ' OR ' .
                $DB->sql_like('u.email', ':s3', false, false) . ')';
            $params['s1'] = $params['s2'] = $params['s3'] = '%' . $DB->sql_like_escape($search) . '%';
        }
        $from = '{' . self::TABLE . '} t JOIN {user} u ON u.id = t.userid WHERE ' . implode(' AND ', $where);
        $order = $status === 'closed' ? 't.timemodified DESC' : "CASE t.status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END,
            t.timemodified ASC";
        return [
            $DB->get_records_sql("SELECT t.*, u.email" . \core_user\fields::for_name()->get_sql('u')->selects .
                " FROM $from ORDER BY $order, t.id",
                $params, $page * self::PERPAGE, self::PERPAGE),
            $DB->count_records_sql("SELECT COUNT(1) FROM $from", $params),
        ];
    }

    /**
     * Number of tickets waiting for the team.
     *
     * @param \stdClass $creator Trainer.
     * @return int
     */
    public static function open_count(\stdClass $creator): int {
        global $DB;

        $visible = team_service::visible_learners($creator);
        if ($visible === null) {
            return $DB->count_records(self::TABLE, ['creatorid' => $creator->id, 'status' => 'open']);
        }
        if (!$visible) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($visible);
        return $DB->count_records_select(self::TABLE, "creatorid = ? AND status = 'open' AND userid $insql",
            array_merge([$creator->id], $params));
    }

    /**
     * A learner's tickets, latest first.
     *
     * @param int $userid Learner.
     * @return array
     */
    public static function of_user(int $userid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['userid' => $userid], 'timemodified DESC, id DESC');
    }

    /**
     * Add a message.
     *
     * @param \stdClass $ticket Ticket.
     * @param int $userid Author.
     * @param string $message Plain text.
     * @param bool $internal Internal note.
     */
    protected static function add_message(\stdClass $ticket, int $userid, string $message, bool $internal): void {
        global $DB;

        $DB->insert_record(self::MESSAGES, (object) ['ticketid' => $ticket->id, 'userid' => $userid,
            'message' => \core_text::substr($message, 0, 20000), 'internal' => (int) $internal, 'timecreated' => time()]);
    }

    /**
     * People of the team told of a ticket: the trainer, their admins and support, and the coach of the learner.
     *
     * @param \stdClass $creator Trainer.
     * @param int $learnerid Learner.
     * @return \stdClass[] Users.
     */
    public static function team_recipients(\stdClass $creator, int $learnerid): array {
        $ids = [(int) $creator->userid];
        foreach (team_service::members($creator) as $member) {
            if ($member->status === 'active' && $member->userid && in_array($member->role, self::NOTIFIED_ROLES, true)) {
                $ids[] = (int) $member->userid;
            }
        }
        if ($coachid = team_service::coach_map($creator)[$learnerid] ?? 0) {
            $ids[] = $coachid;
        }
        $users = [];
        foreach (array_unique($ids) as $id) {
            $user = \core_user::get_user($id);
            if ($user && !$user->deleted && !$user->suspended && $id !== $learnerid) {
                $users[$id] = $user;
            }
        }
        return $users;
    }

    /**
     * Tell the team of a new ticket or of the learner's answer.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $ticket Ticket.
     * @param string $message The learner's message.
     * @param bool $new New ticket.
     */
    protected static function notify_team(\stdClass $creator, \stdClass $ticket, string $message, bool $new): void {
        $learner = \core_user::get_user($ticket->userid);
        $a = (object) ['id' => $ticket->id, 'subject' => $ticket->subject, 'learner' => $learner ? fullname($learner) : '?',
            'email' => $learner->email ?? '', 'message' => $message,
            'url' => (new \moodle_url('/local/oriskill/tickets.php', ['id' => $ticket->id]))->out(false)];
        foreach (self::team_recipients($creator, (int) $ticket->userid) as $user) {
            self::mail($creator, $user, get_string($new ? 'tickets:mail_new_subject' : 'tickets:mail_reply_subject',
                'local_oriskill', $a), get_string('tickets:mail_team', 'local_oriskill', $a), $learner->email ?? '');
        }
    }

    /**
     * Tell the learner of the team's answer.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $ticket Ticket.
     * @param string $message The answer.
     */
    protected static function notify_learner(\stdClass $creator, \stdClass $ticket, string $message): void {
        $learner = \core_user::get_user($ticket->userid);
        if (!$learner || $learner->deleted) {
            return;
        }
        $a = (object) ['id' => $ticket->id, 'subject' => $ticket->subject, 'firstname' => $learner->firstname,
            'message' => $message, 'trainer' => format_string($creator->displayname),
            'url' => (new \moodle_url('/local/oriskill/support.php', ['id' => $ticket->id]))->out(false)];
        self::mail($creator, $learner, get_string('tickets:mail_answer_subject', 'local_oriskill', $a),
            get_string('tickets:mail_learner', 'local_oriskill', $a));
    }

    /**
     * Send a ticket e-mail, on behalf of the trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $to Recipient.
     * @param string $subject Subject.
     * @param string $text Plain text.
     * @param string $replyto Reply-to address.
     */
    protected static function mail(\stdClass $creator, \stdClass $to, string $subject, string $text, string $replyto = ''): void {
        $from = \core_user::get_noreply_user();
        $from->firstname = format_string($creator->displayname);
        $from->lastname = '';
        $html = text_to_html(s($text), false, false, true);
        $html = preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', $html);
        try {
            email_log::for_creator((int) $creator->id, 'transactional', fn() => email_to_user($to, $from, $subject, $text, $html,
                '', '', true, $replyto, $from->firstname));
        } catch (\Throwable $e) {
            debugging('Ticket e-mail: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
