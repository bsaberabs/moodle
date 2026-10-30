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

use context_coursecat;
use moodle_url;

/**
 * Collaborators of a trainer and what each role may do in the trainer space.
 *
 * The trainer (owner) invites a collaborator by e-mail with one of three roles: admin (everything but the payment
 * accounts), teaching (courses, videos, learners, module release) and support (learners, sales, invoices). The
 * collaborator accepts with their own Oriskill account. Admin and teaching collaborators get Moodle's manager role
 * on the trainer's category, so they edit the trainer's courses.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class team_service {

    /** Collaborator roles. */
    const ROLES = ['admin', 'teaching', 'support'];

    /** Roles that edit the trainer's courses (Moodle manager role on the trainer's category). */
    const COURSE_EDITORS = ['admin', 'teaching'];

    /** Pages of the trainer space and the collaborator roles allowed on each (the owner is allowed everywhere). */
    const PAGE_ROLES = [
        '/local/oriskill/dashboard.php' => ['admin', 'teaching', 'support'],
        '/local/oriskill/sales.php' => ['admin', 'support'],
        '/local/oriskill/invoices.php' => ['admin', 'support'],
        '/local/oriskill/document.php' => ['admin'],
        '/local/oriskill/schedules.php' => ['admin', 'support'],
        '/local/oriskill/unpaid.php' => ['admin', 'support'],
        '/local/oriskill/courses.php' => ['admin', 'teaching'],
        '/local/oriskill/videos.php' => ['admin', 'teaching'],
        '/local/oriskill/tools.php' => ['admin', 'teaching'],
        '/local/oriskill/pricing.php' => ['admin'],
        '/local/oriskill/orderforms.php' => ['admin'],
        '/local/oriskill/orderform.php' => ['admin'],
        '/local/oriskill/orderformstats.php' => ['admin'],
        '/local/oriskill/abandons.php' => ['admin'],
        '/local/oriskill/coupons.php' => ['admin'],
        '/local/oriskill/capturepages.php' => ['admin'],
        '/local/oriskill/capturepage.php' => ['admin'],
        '/local/oriskill/learners.php' => ['admin', 'teaching', 'support'],
        '/local/oriskill/learner.php' => ['admin', 'teaching', 'support'],
        '/local/oriskill/access.php' => ['admin', 'teaching'],
        '/local/oriskill/payment.php' => [],
        '/local/oriskill/apply.php' => ['admin'],
        '/local/oriskill/team.php' => ['admin'],
        '/local/oriskill/integrations.php' => ['admin'],
        '/local/oriskill/log.php' => ['admin'],
    ];

    /** Days an invitation link stays valid. */
    const INVITATION_DAYS = 14;

    /** @var array|null Trainer space the current user works in: ['creator' => record, 'role' => owner|admin|...]. */
    private static ?array $current = null;

    /**
     * Trainer spaces a user works in: their own (role owner) and those they collaborate on.
     *
     * @param int $userid User.
     * @return array creatorid => ['creator' => record, 'role' => string]
     */
    public static function spaces_of(int $userid): array {
        global $DB;

        $spaces = [];
        if ($own = $DB->get_record('local_oriskill_creators', ['userid' => $userid, 'status' => 'active'])) {
            $spaces[$own->id] = ['creator' => $own, 'role' => 'owner'];
        }
        $records = $DB->get_records_sql(
            "SELECT m.role, c.*
               FROM {local_oriskill_members} m
               JOIN {local_oriskill_creators} c ON c.id = m.creatorid
              WHERE m.userid = ? AND m.status = 'active' AND c.status = 'active'
           ORDER BY c.displayname", [$userid]);
        foreach ($records as $record) {
            $role = $record->role;
            unset($record->role);
            $spaces[$record->id] = $spaces[$record->id] ?? ['creator' => $record, 'role' => $role];
        }
        return $spaces;
    }

    /**
     * Trainer space the current user works in: the one they chose last, else their own, else the first they
     * collaborate on.
     *
     * @return array|null ['creator' => record, 'role' => owner|admin|teaching|support]
     */
    public static function current_space(): ?array {
        global $SESSION, $USER;

        if (self::$current !== null && (int) self::$current['userid'] === (int) $USER->id) {
            return self::$current['space'];
        }
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        $spaces = self::spaces_of((int) $USER->id);
        $chosen = (int) ($SESSION->local_oriskill_space ?? 0);
        $space = $spaces[$chosen] ?? (reset($spaces) ?: null);
        self::$current = ['userid' => $USER->id, 'space' => $space];
        return $space;
    }

    /**
     * Work in another of the current user's trainer spaces.
     *
     * @param int $creatorid Trainer space.
     */
    public static function switch_space(int $creatorid): void {
        global $SESSION, $USER;

        if (isset(self::spaces_of((int) $USER->id)[$creatorid])) {
            $SESSION->local_oriskill_space = $creatorid;
            self::$current = null;
        }
    }

    /**
     * Whether a role may open a page of the trainer space.
     *
     * @param string $role owner, admin, teaching or support.
     * @param string $path Page path, such as /local/oriskill/pricing.php.
     * @return bool
     */
    public static function can(string $role, string $path): bool {
        return $role === 'owner' || in_array($role, self::PAGE_ROLES[$path] ?? [], true);
    }

    /**
     * Collaborators of a trainer (invitations pending included), oldest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function members(\stdClass $creator): array {
        global $DB;

        return $DB->get_records('local_oriskill_members', ['creatorid' => $creator->id], 'timecreated ASC, id ASC');
    }

    /**
     * Users who are collaborators of a trainer (not learners, even when enrolled in the trainer's courses).
     *
     * @param \stdClass $creator Trainer.
     * @return int[]
     */
    public static function member_userids(\stdClass $creator): array {
        global $DB;

        return array_map('intval', $DB->get_fieldset_select('local_oriskill_members', 'userid',
            "creatorid = ? AND status = 'active' AND userid IS NOT NULL", [$creator->id]));
    }

    /**
     * Invite a collaborator by e-mail.
     *
     * @param \stdClass $creator Trainer.
     * @param string $email Address to send the invitation to.
     * @param string $role admin, teaching or support.
     * @return \stdClass Member record.
     */
    public static function invite(\stdClass $creator, string $email, string $role): \stdClass {
        global $DB, $USER;

        $email = \core_text::strtolower(trim($email));
        if (!validate_email($email)) {
            throw new \moodle_exception('invalidemail');
        }
        if (!in_array($role, self::ROLES, true)) {
            throw new \moodle_exception('invaliddata', 'error');
        }
        $owneremail = \core_text::strtolower((string) $DB->get_field('user', 'email', ['id' => $creator->userid]));
        if ($email === $owneremail || $DB->record_exists_select('local_oriskill_members',
                'creatorid = ? AND LOWER(email) = ?', [$creator->id, $email])) {
            throw new \moodle_exception('error:alreadymember', 'local_oriskill');
        }
        $member = (object) [
            'creatorid' => $creator->id,
            'email' => $email,
            'role' => $role,
            'status' => 'invited',
            'token' => random_string(48),
            'invitedby' => $USER->id,
            'timecreated' => time(),
        ];
        $member->id = $DB->insert_record('local_oriskill_members', $member);
        self::send_invitation($creator, $member);
        log_service::add($creator, 'member_invited', ['email' => $email, 'role' => $role]);
        return $member;
    }

    /**
     * Send (again) the invitation e-mail, with a new link.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $member Pending member.
     */
    public static function send_invitation(\stdClass $creator, \stdClass $member): void {
        global $DB, $USER;

        if ($member->status !== 'invited') {
            return;
        }
        $member->token = random_string(48);
        $member->timecreated = time();
        $DB->update_record('local_oriskill_members', (object) ['id' => $member->id, 'token' => $member->token,
            'timecreated' => $member->timecreated]);
        $recipient = \core_user::get_noreply_user();
        $recipient->email = $member->email;
        $recipient->firstname = '';
        $recipient->lastname = '';
        $recipient->mailformat = 1;
        $a = (object) [
            'trainer' => format_string($creator->displayname),
            'inviter' => fullname($USER),
            'role' => get_string('role:' . $member->role, 'local_oriskill'),
            'url' => (new moodle_url('/local/oriskill/join.php', ['token' => $member->token]))->out(false),
            'days' => self::INVITATION_DAYS,
        ];
        email_to_user($recipient, \core_user::get_noreply_user(), get_string('team:mail_subject', 'local_oriskill', $a),
            get_string('team:mail_body', 'local_oriskill', $a));
    }

    /**
     * Pending invitation of a link, if still valid.
     *
     * @param string $token Link secret.
     * @return \stdClass|null
     */
    public static function invitation(string $token): ?\stdClass {
        global $DB;

        if (strlen($token) < 32) {
            return null;
        }
        $member = $DB->get_record('local_oriskill_members', ['token' => $token, 'status' => 'invited']);
        if (!$member || $member->timecreated < time() - self::INVITATION_DAYS * DAYSECS) {
            return null;
        }
        return $member;
    }

    /**
     * The current user accepts an invitation and joins the trainer's team.
     *
     * @param \stdClass $member Pending member.
     * @return \stdClass Trainer.
     */
    public static function accept(\stdClass $member): \stdClass {
        global $DB, $USER;

        $creator = $DB->get_record('local_oriskill_creators', ['id' => $member->creatorid, 'status' => 'active'], '*',
            MUST_EXIST);
        if ((int) $creator->userid === (int) $USER->id || $DB->record_exists('local_oriskill_members',
                ['creatorid' => $creator->id, 'userid' => $USER->id, 'status' => 'active'])) {
            throw new \moodle_exception('error:alreadymember', 'local_oriskill');
        }
        $member->userid = (int) $USER->id;
        $member->status = 'active';
        $member->token = null;
        $member->timeaccepted = time();
        $DB->update_record('local_oriskill_members', $member);
        self::grant($creator, $member);
        log_service::add($creator, 'member_joined', ['role' => $member->role], null, (int) $USER->id);
        self::switch_space((int) $creator->id);
        return $creator;
    }

    /**
     * Change a collaborator's role.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $member Member of that trainer.
     * @param string $role New role.
     */
    public static function set_role(\stdClass $creator, \stdClass $member, string $role): void {
        global $DB;

        if (!in_array($role, self::ROLES, true) || (int) $member->creatorid !== (int) $creator->id) {
            throw new \moodle_exception('invaliddata', 'error');
        }
        if ($member->role === $role) {
            return;
        }
        self::revoke($creator, $member);
        $old = $member->role;
        $member->role = $role;
        $DB->set_field('local_oriskill_members', 'role', $role, ['id' => $member->id]);
        self::grant($creator, $member);
        log_service::add($creator, 'member_role', ['email' => $member->email, 'from' => $old, 'role' => $role], null,
            $member->userid ? (int) $member->userid : null);
    }

    /**
     * Remove a collaborator (or cancel an invitation).
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $member Member of that trainer.
     */
    public static function remove(\stdClass $creator, \stdClass $member): void {
        global $DB;

        if ((int) $member->creatorid !== (int) $creator->id) {
            throw new \moodle_exception('invaliddata', 'error');
        }
        self::revoke($creator, $member);
        $DB->delete_records('local_oriskill_members', ['id' => $member->id]);
        log_service::add($creator, 'member_removed', ['email' => $member->email, 'role' => $member->role], null,
            $member->userid ? (int) $member->userid : null);
    }

    /**
     * Moodle rights of a collaborator on the trainer's courses.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $member Active member.
     */
    private static function grant(\stdClass $creator, \stdClass $member): void {
        if ($member->status === 'active' && $member->userid && $creator->categoryid
                && in_array($member->role, self::COURSE_EDITORS, true)) {
            role_assign(self::manager_role(), (int) $member->userid, context_coursecat::instance($creator->categoryid)->id,
                'local_oriskill', (int) $member->id);
        }
    }

    /**
     * Withdraw the Moodle rights given by grant().
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $member Member.
     */
    private static function revoke(\stdClass $creator, \stdClass $member): void {
        if ($member->userid && $creator->categoryid) {
            role_unassign(self::manager_role(), (int) $member->userid,
                context_coursecat::instance($creator->categoryid)->id, 'local_oriskill', (int) $member->id);
        }
    }

    /**
     * Moodle's manager role id.
     *
     * @return int
     */
    private static function manager_role(): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
    }
}
