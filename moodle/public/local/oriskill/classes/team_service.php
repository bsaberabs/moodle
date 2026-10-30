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
 * The trainer (owner) invites a collaborator by e-mail with a role: admin (everything but the payment accounts),
 * teaching (courses, videos, learners, module release), support (learners, sales, invoices), accounting (sales,
 * invoices, quotes, schedules, unpaid, learners read-only), partner (learners read-only, no export unless an admin
 * allows it) or coach (only the learners assigned to them, read-only). The collaborator accepts with their own
 * Oriskill account. Admin and teaching collaborators get Moodle's manager role on the trainer's category, so they
 * edit the trainer's courses; coaches get the non-editing teacher role there, to comment and grade.
 * Admin and accounting collaborators must use two-factor authentication.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class team_service {

    /** Collaborator roles. */
    const ROLES = ['admin', 'teaching', 'support', 'accounting', 'partner', 'coach'];

    /** Roles that edit the trainer's courses (Moodle manager role on the trainer's category). */
    const COURSE_EDITORS = ['admin', 'teaching'];

    /** Roles that see payments and customer data: two-factor authentication required. */
    const MFA_ROLES = ['admin', 'accounting'];

    /** Pages some roles may only look at (no change, no action). */
    const VIEW_ONLY = [
        '/local/oriskill/learners.php' => ['accounting', 'partner', 'coach'],
        '/local/oriskill/contacts.php' => ['accounting'],
    ];

    /** Pages of the trainer space and the collaborator roles allowed on each (the owner is allowed everywhere). */
    const PAGE_ROLES = [
        '/local/oriskill/dashboard.php' => ['admin', 'teaching', 'support', 'accounting'],
        '/local/oriskill/sales.php' => ['admin', 'support', 'accounting'],
        '/local/oriskill/stats.php' => ['admin', 'support', 'accounting'],
        '/local/oriskill/invoices.php' => ['admin', 'support', 'accounting'],
        '/local/oriskill/affiliation.php' => ['admin', 'accounting'],
        '/local/oriskill/document.php' => ['admin', 'accounting'],
        '/local/oriskill/schedules.php' => ['admin', 'support', 'accounting'],
        '/local/oriskill/unpaid.php' => ['admin', 'support', 'accounting'],
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
        '/local/oriskill/learners.php' => ['admin', 'teaching', 'support', 'accounting', 'partner', 'coach'],
        '/local/oriskill/learner.php' => ['admin', 'teaching', 'support', 'accounting', 'partner', 'coach'],
        '/local/oriskill/access.php' => ['admin', 'teaching'],
        '/local/oriskill/contacts.php' => ['admin', 'support', 'accounting', 'partner'],
        '/local/oriskill/contact.php' => ['admin', 'support', 'accounting', 'partner'],
        '/local/oriskill/contactimport.php' => ['admin', 'support'],
        '/local/oriskill/tickets.php' => ['admin', 'teaching', 'support', 'coach'],
        '/local/oriskill/payment.php' => [],
        '/local/oriskill/apply.php' => ['admin'],
        '/local/oriskill/team.php' => ['admin'],
        '/local/oriskill/integrations.php' => ['admin'],
        '/local/oriskill/domains.php' => ['admin'],
        '/local/oriskill/privacy.php' => ['admin'],
        '/local/oriskill/webhooks.php' => ['admin'],
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
     * Whether a role may act on a page it can open (not only look at it).
     *
     * @param string $role owner or a collaborator role.
     * @param string $path Page path.
     * @return bool
     */
    public static function can_change(string $role, string $path): bool {
        return self::can($role, $path) && !in_array($role, self::VIEW_ONLY[$path] ?? [], true);
    }

    /**
     * First page of the trainer space a role may open.
     *
     * @param string $role owner or a collaborator role.
     * @return string Page path.
     */
    public static function home(string $role): string {
        foreach (self::PAGE_ROLES as $path => $roles) {
            if (self::can($role, $path)) {
                return $path;
            }
        }
        return '/local/oriskill/dashboard.php';
    }

    /**
     * Whether a user must set up two-factor authentication before working with this role: admin and accounting
     * collaborators, when the site offers it.
     *
     * @param string $role Role in the trainer space.
     * @param int $userid User.
     * @return bool
     */
    public static function needs_mfa(string $role, int $userid): bool {
        global $DB;

        if (!in_array($role, self::MFA_ROLES, true) || !get_config('tool_mfa', 'enabled')) {
            return false;
        }
        return !$DB->record_exists_select('tool_mfa', "userid = ? AND factor IN ('totp', 'webauthn') AND revoked = 0",
            [$userid]);
    }

    /**
     * Whether the current user may export lists of people from the trainer space. Partners may not, unless an
     * admin allowed it.
     *
     * @return bool
     */
    public static function can_export(): bool {
        global $DB, $USER;

        $space = self::current_space();
        if (!$space) {
            return false;
        }
        if ($space['role'] !== 'partner') {
            return true;
        }
        return (bool) $DB->get_field('local_oriskill_members', 'canexport', ['creatorid' => $space['creator']->id,
            'userid' => $USER->id, 'status' => 'active']);
    }

    /**
     * Refuse an export the current user may not make, and record the others in the trainer's log.
     *
     * @param \stdClass $creator Trainer.
     * @param string $what What is exported (shown in the log).
     */
    public static function export_allowed(\stdClass $creator, string $what): void {
        if (!self::can_export()) {
            throw new \moodle_exception('error:noexport', 'local_oriskill');
        }
        log_service::add($creator, 'export', ['what' => $what]);
    }

    /**
     * Allow or forbid a partner to export.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $member Member of that trainer.
     * @param bool $allowed Allowed.
     */
    public static function set_export(\stdClass $creator, \stdClass $member, bool $allowed): void {
        global $DB;

        if ((int) $member->creatorid !== (int) $creator->id) {
            throw new \moodle_exception('invaliddata', 'error');
        }
        $DB->set_field('local_oriskill_members', 'canexport', $allowed ? 1 : 0, ['id' => $member->id]);
        log_service::add($creator, $allowed ? 'member_exporton' : 'member_exportoff', ['email' => $member->email], null,
            $member->userid ? (int) $member->userid : null);
    }

    // ----- Coaches ------------------------------------------------------------------------------------------

    /**
     * Active coaches of a trainer.
     *
     * @param \stdClass $creator Trainer.
     * @return array userid => user
     */
    public static function coaches(\stdClass $creator): array {
        global $DB;

        $userids = $DB->get_fieldset_select('local_oriskill_members', 'userid',
            "creatorid = ? AND role = 'coach' AND status = 'active' AND userid IS NOT NULL", [$creator->id]);
        $result = [];
        foreach ($userids as $userid) {
            if ($user = \core_user::get_user((int) $userid)) {
                $result[(int) $userid] = $user;
            }
        }
        return $result;
    }

    /**
     * Coach of each of a trainer's learners.
     *
     * @param \stdClass $creator Trainer.
     * @return array learner userid => coach userid
     */
    public static function coach_map(\stdClass $creator): array {
        global $DB;

        return array_map('intval', $DB->get_records_menu('local_oriskill_coachees', ['creatorid' => $creator->id], '',
            'userid, coachid'));
    }

    /**
     * Give a learner a coach (0 for none).
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid Learner.
     * @param int $coachid Coach (an active coach of the trainer), 0 for none.
     */
    public static function set_coach(\stdClass $creator, int $userid, int $coachid): void {
        global $DB;

        $coaches = self::coaches($creator);
        if ($coachid && !isset($coaches[$coachid])) {
            throw new \moodle_exception('invaliddata', 'error');
        }
        $old = self::coach_map($creator)[$userid] ?? 0;
        $DB->delete_records('local_oriskill_coachees', ['creatorid' => $creator->id, 'userid' => $userid]);
        if ($coachid) {
            $DB->insert_record('local_oriskill_coachees', (object) ['creatorid' => $creator->id, 'userid' => $userid,
                'coachid' => $coachid, 'timecreated' => time()]);
        }
        $name = fn(int $id) => $id ? (isset($coaches[$id]) ? fullname($coaches[$id])
            : fullname(\core_user::get_user($id) ?: (object) ['firstname' => '?', 'lastname' => ''])) : '–';
        log_service::add($creator, 'learner_setcoach', ['coach' => $name($coachid)], null, $userid,
            [get_string('change:coach', 'local_oriskill') => [$name($old), $name($coachid)]]);
    }

    /**
     * The learners a coach follows, when the current user works as a coach; null for every other role (all).
     *
     * @param \stdClass $creator Trainer.
     * @return int[]|null
     */
    public static function visible_learners(\stdClass $creator): ?array {
        global $DB, $USER;

        $space = self::current_space();
        if (!$space || $space['role'] !== 'coach') {
            return null;
        }
        return array_map('intval', $DB->get_fieldset_select('local_oriskill_coachees', 'userid',
            'creatorid = ? AND coachid = ?', [$creator->id, $USER->id]));
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
            'canexport' => $role === 'partner' ? 0 : 1,
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
        $DB->update_record('local_oriskill_members', (object) ['id' => $member->id, 'role' => $role,
            'canexport' => $role === 'partner' ? 0 : 1]);
        if ($old === 'coach' && $member->userid) {
            $DB->delete_records('local_oriskill_coachees', ['creatorid' => $creator->id, 'coachid' => $member->userid]);
        }
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
        if ($member->userid) {
            $DB->delete_records('local_oriskill_coachees', ['creatorid' => $creator->id, 'coachid' => $member->userid]);
        }
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
        if ($member->status !== 'active' || !$member->userid || !$creator->categoryid) {
            return;
        }
        $shortname = in_array($member->role, self::COURSE_EDITORS, true) ? 'manager'
            : ($member->role === 'coach' ? 'teacher' : '');
        if ($shortname !== '') {
            role_assign(self::role_id($shortname), (int) $member->userid, context_coursecat::instance($creator->categoryid)->id,
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
            role_unassign_all(['userid' => (int) $member->userid,
                'contextid' => context_coursecat::instance($creator->categoryid)->id, 'component' => 'local_oriskill',
                'itemid' => (int) $member->id]);
        }
    }

    /**
     * Id of a Moodle role.
     *
     * @param string $shortname manager or teacher.
     * @return int
     */
    private static function role_id(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
