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

use availability_enroldays\condition as enroldays;
use availability_forcedaccess\condition as forcedaccess;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->libdir . '/enrollib.php');

/**
 * What a trainer manages in their own courses: learners' access, groups, module release rules, and progress.
 *
 * Every method expects a course that belongs to the trainer: pages check it with require_own_course().
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learning_service {

    /** Module release rules a trainer can pick (besides a custom rule set in Moodle). */
    const TIMINGS = ['open', 'previous', 'date', 'days'];

    /**
     * A course of the trainer, or an exception.
     *
     * @param \stdClass $creator Trainer.
     * @param int $courseid Course.
     * @return \stdClass Course record.
     */
    public static function require_own_course(\stdClass $creator, int $courseid): \stdClass {
        global $DB;

        $owner = seller_service::owner_of_course($courseid);
        if (!$owner || (int) $owner->id !== (int) $creator->id) {
            throw new \moodle_exception('nopermissions', 'error', '', 'course');
        }
        return $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    }

    // ----- Learners -----------------------------------------------------------------------------------------

    /**
     * Learners of a course (everyone enrolled except the trainer), with their access and progress.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course.
     * @return array userid => object (user fields, timestart, timeend, suspended, source, groups, progress, grade,
     *               lastaccess, completed)
     */
    public static function learners(\stdClass $creator, \stdClass $course): array {
        global $DB;

        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $records = $DB->get_records_sql(
            "SELECT ue.id AS ueid, u.id, u.email, u.picture, u.imagealt, $fields,
                    ue.timestart, ue.timeend, ue.timecreated, ue.status, e.status AS instancestatus, e.enrol
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid
              WHERE e.courseid = :courseid AND u.deleted = 0 AND u.id <> :trainer
           ORDER BY u.lastname, u.firstname, ue.id",
            ['courseid' => $course->id, 'trainer' => $creator->userid]);
        // Collaborators enrolled to work on the course are not learners.
        $staff = team_service::member_userids($creator);
        $records = array_filter($records, fn($record) => !in_array((int) $record->id, $staff, true));
        if (!$records) {
            return [];
        }

        $learners = [];
        foreach ($records as $record) {
            $start = $record->timestart ?: $record->timecreated;
            $active = (int) $record->status === ENROL_USER_ACTIVE && (int) $record->instancestatus === ENROL_INSTANCE_ENABLED;
            if (isset($learners[$record->id])) {
                // Several enrolments (purchase and offered access): keep the earliest start and the latest end.
                $learner = $learners[$record->id];
                $learner->timestart = min($learner->timestart, $start);
                $learner->timeend = ($learner->timeend && $record->timeend) ? max($learner->timeend, $record->timeend) : 0;
                $learner->suspended = $learner->suspended && !$active;
                continue;
            }
            $record->timestart = $start;
            $record->suspended = !$active;
            $learners[$record->id] = $record;
        }

        $userids = array_keys($learners);
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $purchased = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {local_moderncommerce_entitlements}
              WHERE courseid = :courseid AND status = 'active' AND userid $insql",
            ['courseid' => $course->id] + $params);
        $lastaccess = $DB->get_records_sql_menu(
            "SELECT userid, timeaccess FROM {user_lastaccess} WHERE courseid = :courseid AND userid $insql",
            ['courseid' => $course->id] + $params);
        $completed = $DB->get_records_sql_menu(
            "SELECT userid, timecompleted FROM {course_completions}
              WHERE course = :courseid AND timecompleted > 0 AND userid $insql",
            ['courseid' => $course->id] + $params);
        $groups = groups_get_all_groups($course->id);
        $members = [];
        foreach (self::group_members($course) as $mapping) {
            $members[$mapping->userid][] = $mapping->groupid;
        }
        $grades = grade_get_course_grades($course->id, $userids)->grades ?? [];
        $studytime = studytime_service::totals((int) $course->id, $userids);

        $now = \core\di::get(\core\clock::class)->time();
        foreach ($learners as $userid => $learner) {
            $learner->source = in_array($userid, $purchased) ? 'purchase' : 'offered';
            $learner->expired = $learner->timeend && $learner->timeend < $now;
            $learner->lastaccess = (int) ($lastaccess[$userid] ?? 0);
            $learner->completed = (int) ($completed[$userid] ?? 0);
            $learner->groups = array_values(array_intersect_key($groups, array_flip($members[$userid] ?? [])));
            $learner->progress = \core_completion\progress::get_course_progress_percentage($course, $userid);
            $learner->grade = isset($grades[$userid]) && $grades[$userid]->grade !== null ? $grades[$userid]->str_grade : null;
            $learner->studytime = $studytime[$userid] ?? 0;
        }
        return $learners;
    }

    /**
     * Give a learner access to the course (offered by the trainer), creating their account if needed.
     *
     * A new account receives its username and a temporary password by e-mail.
     *
     * @param \stdClass $course Course.
     * @param string $email E-mail address.
     * @param string $firstname First name, for a new account.
     * @param string $lastname Last name, for a new account.
     * @param int $timeend End of access, 0 for unlimited.
     * @param int $groupid Group to put the learner in, 0 for none.
     * @param bool $sendcredentials Send a new account its username and password (otherwise the learner uses
     *                              "forgotten password" to get in).
     * @return array [user, bool account created]
     */
    public static function offer_access(\stdClass $course, string $email, string $firstname, string $lastname,
            int $timeend, int $groupid, bool $sendcredentials = true): array {
        global $CFG, $DB;

        $email = trim(\core_text::strtolower($email));
        if (!validate_email($email)) {
            throw new \moodle_exception('invalidemail');
        }
        $user = $DB->get_record_select('user', 'LOWER(email) = ? AND deleted = 0 AND mnethostid = ?',
            [$email, $CFG->mnet_localhost_id], '*', IGNORE_MULTIPLE);
        $created = false;
        // The trainer's access e-mail, when it goes out, carries a link to choose a password: no credentials e-mail.
        $trigger = access_mail::current_trigger() ?? 'manual';
        $sendcredentials = $sendcredentials && !access_mail::will_send((int) $course->id, $trigger);
        if (!$user) {
            if (trim($firstname) === '' || trim($lastname) === '') {
                throw new \moodle_exception('error:newlearnername', 'local_oriskill');
            }
            $username = \core_user::clean_field($email, 'username');
            if ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
                throw new \moodle_exception('error:usernametaken', 'local_oriskill');
            }
            $userid = user_create_user((object) ['username' => $username, 'email' => $email, 'auth' => 'manual',
                'firstname' => trim($firstname), 'lastname' => trim($lastname), 'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id, 'lang' => current_language()], false, false);
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            if ($sendcredentials) {
                // The account e-mail belongs to the trainer of the course.
                $creatorid = (int) $DB->get_field('local_oriskill_creators', 'id', ['categoryid' => $course->category],
                    IGNORE_MULTIPLE);
                email_log::for_creator($creatorid, 'transactional', fn() => setnew_password_and_mail($user));
                set_user_preference('auth_forcepasswordchange', 1, $user);
            } else {
                update_internal_user_password($user, generate_password(20));
            }
            $created = true;
        }

        $plugin = enrol_get_plugin('manual');
        $instance = self::manual_instance($course);
        $now = \core\di::get(\core\clock::class)->time();
        $existing = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id]);
        if ($existing) {
            $plugin->update_user_enrol($instance, $user->id, ENROL_USER_ACTIVE, null, $timeend);
        } else {
            access_mail::with_trigger($trigger, fn() => $plugin->enrol_user($instance, $user->id, $instance->roleid, $now, $timeend));
        }
        if ($groupid) {
            self::set_learner_group($course, $user->id, $groupid);
        }
        enroldays::reset_cache();
        return [$user, $created];
    }

    /**
     * Suspend or reactivate all of a learner's enrolments in the course.
     *
     * @param \stdClass $course Course.
     * @param int $userid Learner.
     * @param bool $suspend Suspend (true) or reactivate (false).
     */
    public static function set_suspended(\stdClass $course, int $userid, bool $suspend): void {
        foreach (self::user_enrolments($course, $userid) as [$plugin, $instance]) {
            $plugin->update_user_enrol($instance, $userid, $suspend ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE);
        }
    }

    /**
     * Change the end of a learner's access (all their enrolments in the course).
     *
     * @param \stdClass $course Course.
     * @param int $userid Learner.
     * @param int $timeend End of access, 0 for unlimited.
     */
    public static function set_timeend(\stdClass $course, int $userid, int $timeend): void {
        foreach (self::user_enrolments($course, $userid) as [$plugin, $instance]) {
            $plugin->update_user_enrol($instance, $userid, null, null, $timeend);
        }
    }

    /**
     * Change the start of a learner's access (all their enrolments in the course); modules released J+X follow it.
     *
     * @param \stdClass $course Course.
     * @param int $userid Learner.
     * @param int $timestart Start of access.
     */
    public static function set_timestart(\stdClass $course, int $userid, int $timestart): void {
        if ($timestart <= 0) {
            throw new \moodle_exception('invalidparameter', 'debug');
        }
        foreach (self::user_enrolments($course, $userid) as [$plugin, $instance]) {
            $plugin->update_user_enrol($instance, $userid, null, $timestart);
        }
        enroldays::reset_cache();
    }

    /**
     * Start again from scratch: forget which activities the learner completed, and the course completion.
     * Grades and attempts stay; modules opened by "previous module completed" lock again.
     *
     * @param \stdClass $course Course.
     * @param int $userid Learner.
     */
    public static function reset_progress(\stdClass $course, int $userid): void {
        global $DB;

        $incourse = 'coursemoduleid IN (SELECT id FROM {course_modules} WHERE course = ?) AND userid = ?';
        $DB->delete_records_select('course_modules_completion', $incourse, [$course->id, $userid]);
        $DB->delete_records_select('course_modules_viewed', $incourse, [$course->id, $userid]);
        $DB->delete_records('course_completion_crit_compl', ['course' => $course->id, 'userid' => $userid]);
        $DB->delete_records('course_completions', ['course' => $course->id, 'userid' => $userid]);
        \cache::make('core', 'completion')->delete($userid . '_' . $course->id);
        \cache::make('core', 'coursecompletion')->delete($userid . '_' . $course->id);
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'core', 'course_progress')->purge();
    }

    /**
     * Open modules to a learner whatever their release rules and groups; the other modules follow their rules again.
     *
     * @param \stdClass $course Course.
     * @param int $userid Learner.
     * @param int[] $sectionids Modules (sections) to open.
     */
    public static function set_forced_modules(\stdClass $course, int $userid, array $sectionids): void {
        $valid = array_map(fn($rule) => (int) $rule->section->id, self::section_rules($course));
        forcedaccess::set_opened_sections($course, $userid, array_intersect(array_map('intval', $sectionids), $valid));
    }

    /**
     * Remove an access offered by the trainer. A purchased access can only be suspended.
     *
     * @param \stdClass $course Course.
     * @param int $userid Learner.
     */
    public static function remove_offered(\stdClass $course, int $userid): void {
        global $DB;

        if ($DB->record_exists('local_moderncommerce_entitlements',
                ['courseid' => $course->id, 'userid' => $userid, 'status' => 'active'])) {
            throw new \moodle_exception('error:purchasedaccess', 'local_oriskill');
        }
        foreach (self::user_enrolments($course, $userid) as [$plugin, $instance]) {
            $plugin->unenrol_user($instance, $userid);
        }
    }

    /**
     * The course's manual enrolment instance, created and enabled if needed.
     *
     * @param \stdClass $course Course.
     * @return \stdClass Enrol instance.
     */
    protected static function manual_instance(\stdClass $course): \stdClass {
        global $DB;

        $plugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $id = $plugin->add_default_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
        } else if ((int) $instance->status !== ENROL_INSTANCE_ENABLED) {
            $plugin->update_status($instance, ENROL_INSTANCE_ENABLED);
            $instance->status = ENROL_INSTANCE_ENABLED;
        }
        if (empty($instance->roleid)) {
            $instance->roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        }
        return $instance;
    }

    /**
     * A learner's enrolments in the course, with the plugin handling each.
     *
     * @param \stdClass $course Course.
     * @param int $userid Learner.
     * @return array of [enrol_plugin, instance]
     */
    protected static function user_enrolments(\stdClass $course, int $userid): array {
        global $DB;

        $result = [];
        $instances = $DB->get_records_sql(
            "SELECT e.* FROM {enrol} e JOIN {user_enrolments} ue ON ue.enrolid = e.id
              WHERE e.courseid = ? AND ue.userid = ?", [$course->id, $userid]);
        foreach ($instances as $instance) {
            if ($plugin = enrol_get_plugin($instance->enrol)) {
                $result[] = [$plugin, $instance];
            }
        }
        return $result;
    }

    // ----- Groups -------------------------------------------------------------------------------------------

    /**
     * Put a learner in one group of the course (or none), leaving the other groups.
     *
     * @param \stdClass $course Course.
     * @param int $userid Learner.
     * @param int $groupid Group, 0 for none.
     */
    public static function set_learner_group(\stdClass $course, int $userid, int $groupid): void {
        $groups = groups_get_all_groups($course->id);
        if ($groupid && !isset($groups[$groupid])) {
            throw new \moodle_exception('invalidgroupid');
        }
        foreach ($groups as $group) {
            if ((int) $group->id !== $groupid && groups_is_member($group->id, $userid)) {
                groups_remove_member($group, $userid);
            }
        }
        if ($groupid && !groups_is_member($groupid, $userid)) {
            groups_add_member($groupid, $userid);
        }
    }

    /**
     * Group memberships in the course, read from the database (the group cache can lag behind a change).
     *
     * @param \stdClass $course Course.
     * @return array of {groupid, userid}
     */
    public static function group_members(\stdClass $course): array {
        global $DB;

        return array_values($DB->get_records_sql(
            "SELECT gm.id, gm.groupid, gm.userid FROM {groups_members} gm JOIN {groups} g ON g.id = gm.groupid
              WHERE g.courseid = ?", [$course->id]));
    }

    /**
     * Create a group in the course.
     *
     * @param \stdClass $course Course.
     * @param string $name Group name.
     * @return int Group id.
     */
    public static function create_group(\stdClass $course, string $name): int {
        $name = trim($name);
        if ($name === '') {
            throw new \moodle_exception('error:groupname', 'local_oriskill');
        }
        if (groups_get_group_by_name($course->id, $name)) {
            throw new \moodle_exception('groupnameexists', 'group', '', $name);
        }
        return groups_create_group((object) ['courseid' => $course->id, 'name' => $name]);
    }

    /**
     * Delete a group of the course.
     *
     * @param \stdClass $course Course.
     * @param int $groupid Group.
     */
    public static function delete_group(\stdClass $course, int $groupid): void {
        if (!isset(groups_get_all_groups($course->id)[$groupid])) {
            throw new \moodle_exception('invalidgroupid');
        }
        groups_delete_group($groupid);
    }

    // ----- Module release rules -----------------------------------------------------------------------------

    /**
     * Release rule of each module (section) of the course, except the first, general section.
     *
     * @param \stdClass $course Course.
     * @return array sectionnum => object {section, name, timing, date, days, groups, custom, stale}
     */
    public static function section_rules(\stdClass $course): array {
        $modinfo = get_fast_modinfo($course);
        $rules = [];
        foreach ($modinfo->get_section_info_all() as $num => $section) {
            if ($num == 0 || $section->component) {
                continue;
            }
            $rule = self::parse_rule($section->availability,
                $num > 1 ? self::completion_cmids($modinfo, $num - 1) : []);
            $rule->section = $section;
            $rule->name = get_section_name($course, $section);
            $rule->hascompletion = $num > 1 && self::completion_cmids($modinfo, $num - 1);
            $rules[$num] = $rule;
        }
        return $rules;
    }

    /**
     * Set the release rule of a module (section).
     *
     * @param \stdClass $course Course.
     * @param int $sectionnum Section number (1 or more).
     * @param string $timing One of TIMINGS.
     * @param int $date Opening date, for "date".
     * @param int $days Days after enrolment, for "days".
     * @param int[] $groupids Groups the module is reserved to (empty: everyone).
     */
    public static function set_section_rule(\stdClass $course, int $sectionnum, string $timing, int $date, int $days,
            array $groupids): void {
        global $DB;

        if ($sectionnum < 1 || !in_array($timing, self::TIMINGS)) {
            throw new \moodle_exception('invalidparameter', 'debug');
        }
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info($sectionnum, MUST_EXIST);
        $conditions = [];
        if ($timing === 'previous') {
            $cmids = $sectionnum > 1 ? self::completion_cmids($modinfo, $sectionnum - 1) : [];
            if (!$cmids) {
                throw new \moodle_exception('error:nopreviouscompletion', 'local_oriskill');
            }
            foreach ($cmids as $cmid) {
                $conditions[] = \availability_completion\condition::get_json($cmid, COMPLETION_COMPLETE);
            }
        } else if ($timing === 'date') {
            $conditions[] = \availability_date\condition::get_json(\availability_date\condition::DIRECTION_FROM, $date);
        } else if ($timing === 'days') {
            $conditions[] = enroldays::get_json(max(0, $days));
        }
        $valid = groups_get_all_groups($course->id);
        $groupids = array_values(array_filter(array_map('intval', $groupids), fn($id) => isset($valid[$id])));
        if (count($groupids) === 1) {
            $conditions[] = \availability_group\condition::get_json($groupids[0]);
        } else if ($groupids) {
            $conditions[] = \core_availability\tree::get_nested_json(
                array_map(fn($id) => \availability_group\condition::get_json($id), $groupids),
                \core_availability\tree::OP_OR);
        }
        $json = $conditions ? json_encode(\core_availability\tree::get_root_json($conditions,
            \core_availability\tree::OP_AND, true)) : null;
        // Learners the module was opened to keep it.
        if ($DB->record_exists('availability_forcedaccess', ['sectionid' => $section->id])) {
            $json = forcedaccess::wrap($json);
        }
        course_update_section($course, $section, ['availability' => $json]);
    }

    /**
     * Read an availability structure as one of the trainer's simple rules.
     *
     * @param string|null $json Section availability.
     * @param int[] $previouscmids Activities with completion in the previous module.
     * @return \stdClass {timing, date, days, groups, custom, stale}
     */
    protected static function parse_rule(?string $json, array $previouscmids): \stdClass {
        $rule = (object) ['timing' => 'open', 'date' => 0, 'days' => 0, 'groups' => [], 'custom' => false, 'stale' => false];
        $tree = $json ? json_decode($json) : null;
        if (!$tree || empty($tree->c)) {
            return $rule;
        }
        // Modules opened to some learners: the rule is what applies to the others.
        $tree = forcedaccess::unwrap($tree);
        if (($tree->op ?? '&') !== '&') {
            $rule->custom = true;
            return $rule;
        }
        $cmids = [];
        foreach ($tree->c as $child) {
            $type = $child->type ?? null;
            if ($type === 'completion' && ($child->e ?? null) == COMPLETION_COMPLETE && $rule->timing === 'open'
                    || $type === 'completion' && $rule->timing === 'previous') {
                $rule->timing = 'previous';
                $cmids[] = (int) $child->cm;
            } else if ($type === 'date' && ($child->d ?? '') === '>=' && $rule->timing === 'open') {
                $rule->timing = 'date';
                $rule->date = (int) $child->t;
            } else if ($type === 'enroldays' && $rule->timing === 'open') {
                $rule->timing = 'days';
                $rule->days = (int) $child->d;
            } else if ($type === 'group' && !empty($child->id) && !$rule->groups) {
                $rule->groups = [(int) $child->id];
            } else if ($type === null && ($child->op ?? '') === '|' && !$rule->groups && !empty($child->c)
                    && count(array_filter($child->c, fn($g) => ($g->type ?? '') === 'group' && !empty($g->id)))
                        === count($child->c)) {
                $rule->groups = array_map(fn($g) => (int) $g->id, $child->c);
            } else {
                $rule->custom = true;
                return $rule;
            }
        }
        if ($rule->timing === 'previous') {
            // Completion conditions on other activities than the previous module's are a custom rule.
            if (array_diff($cmids, $previouscmids)) {
                $rule->custom = true;
            } else {
                // Activities added to the previous module since the rule was saved are not part of it yet.
                $rule->stale = (bool) array_diff($previouscmids, $cmids);
            }
        }
        return $rule;
    }

    /**
     * Activities with completion tracking in a section.
     *
     * @param \course_modinfo $modinfo Course info.
     * @param int $sectionnum Section number.
     * @return int[] Course module ids.
     */
    protected static function completion_cmids(\course_modinfo $modinfo, int $sectionnum): array {
        $cmids = [];
        foreach ($modinfo->sections[$sectionnum] ?? [] as $cmid) {
            $cm = $modinfo->get_cm($cmid);
            if ($cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress) {
                $cmids[] = (int) $cmid;
            }
        }
        return $cmids;
    }

    // ----- Progress of one learner --------------------------------------------------------------------------

    /**
     * Progress of a learner, module by module: whether each module is open to them, and each activity's state.
     *
     * @param \stdClass $course Course.
     * @param int $userid Learner.
     * @return array of objects {sectionid, name, available, availableinfo, restricted, forced,
     *     activities: [{name, url, icon, state, grade}]}
     */
    public static function learner_progress(\stdClass $course, int $userid): array {
        $modinfo = get_fast_modinfo($course, $userid);
        $forced = forcedaccess::opened_sections((int) $course->id, $userid);
        $completion = new \completion_info($course);
        $result = [];
        foreach ($modinfo->get_section_info_all() as $num => $section) {
            if ($section->component || empty($modinfo->sections[$num])) {
                continue;
            }
            $activities = [];
            foreach ($modinfo->sections[$num] as $cmid) {
                $cm = $modinfo->get_cm($cmid);
                if (!$cm->has_view() && $cm->modname !== 'label' || $cm->deletioninprogress) {
                    continue;
                }
                $state = null;
                if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                    $data = $completion->get_data($cm, false, $userid);
                    $state = in_array($data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS]) ? 'done'
                        : ($data->completionstate == COMPLETION_COMPLETE_FAIL ? 'failed' : 'todo');
                }
                $grade = null;
                if (plugin_supports('mod', $cm->modname, FEATURE_GRADE_HAS_GRADE, false)) {
                    $grades = grade_get_grades($course->id, 'mod', $cm->modname, $cm->instance, $userid);
                    $item = $grades->items[0] ?? null;
                    if ($item && isset($item->grades[$userid]) && $item->grades[$userid]->grade !== null) {
                        $grade = $item->grades[$userid]->str_grade;
                    }
                }
                $activities[] = (object) ['name' => $cm->get_formatted_name(), 'url' => $cm->url, 'modname' => $cm->modname,
                    'state' => $state, 'grade' => $grade];
            }
            $result[] = (object) [
                'sectionid' => (int) $section->id,
                'restricted' => $num > 0 && !empty($section->availability),
                'forced' => in_array((int) $section->id, $forced),
                'name' => get_section_name($course, $section),
                'available' => (bool) $section->available,
                'availableinfo' => $section->available ? '' :
                    \core_availability\info::format_info($section->availableinfo, $course),
                'activities' => $activities,
            ];
        }
        return $result;
    }
}
