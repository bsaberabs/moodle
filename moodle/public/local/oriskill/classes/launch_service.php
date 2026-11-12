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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/enrollib.php');

/**
 * Launch date of a training: visible (in the showcase, on sale, in the learners' courses) but locked until that day.
 *
 * Before the launch, learners see a page "Opens on ..." instead of the content; the team is never locked out. On the
 * launch day, the pre-sale enrolments (started since the lock began) start on that day instead, once (the course is then
 * marked as processed), so that modules released D+N and the access duration count from the launch for the learners who
 * bought in advance; learners enrolled before the lock keep their dates. The course stays locked until that is done: at
 * the first visit of a learner after the date, or by the scheduled task.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class launch_service {

    /** Launch date of each course. */
    const TABLE = 'local_oriskill_launch';

    /** Capabilities of the team in a course (trainer, collaborators, coaches, admins): never locked out. */
    const STAFF_CAPS = ['moodle/course:manageactivities', 'moodle/course:viewhiddenactivities'];

    /** Scripts a learner may open in a locked course: buying, enrolling, account, switching back from a preview. */
    const OPEN_PATHS = ['/enrol/', '/payment/', '/local/moderncommerce/', '/local/secureandpay/', '/login/', '/user/',
        '/admin/', '/course/switchrole.php'];

    /** Shortcuts offered next to the date: in N days. */
    const SHORTCUTS = [7, 14, 30];

    /** Setting of the plugin holding the time of the last failed launch of a course (followed by the course id). */
    const FAILURE = 'launchfailed_';

    /** Seconds the pages wait after a failed launch before trying again (the scheduled task tries at each run). */
    const RETRYAFTER = 600;

    /** @var array courseid => record|false, read once per request. */
    private static array $records = [];

    /**
     * Current time.
     *
     * @return int
     */
    private static function now(): int {
        return \core\di::get(\core\clock::class)->time();
    }

    /**
     * Launch record of a course (launchdate 0: no launch date).
     *
     * @param int $courseid Course.
     * @return \stdClass|null
     */
    public static function get(int $courseid): ?\stdClass {
        global $DB;

        // Read again in scheduled tasks: a cron process lives long enough for the trainer to change the date meanwhile.
        if (CLI_SCRIPT || !array_key_exists($courseid, self::$records)) {
            self::$records[$courseid] = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        }
        return self::$records[$courseid] ?: null;
    }

    /**
     * Launch records of several courses.
     *
     * @param int[] $courseids Courses.
     * @return array courseid => record
     */
    public static function for_courses(array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $records = [];
        foreach ($DB->get_records_select(self::TABLE, "courseid $insql", $params) as $record) {
            $records[(int) $record->courseid] = $record;
            self::$records[(int) $record->courseid] = $record;
        }
        return $records;
    }

    /**
     * Forget what was read in this request.
     *
     * @param int|null $courseid Course, or all of them.
     */
    public static function forget(?int $courseid = null): void {
        if ($courseid === null) {
            self::$records = [];
        } else {
            unset(self::$records[$courseid]);
        }
    }

    /**
     * Whether a launch record locks its course: a date is set and it is still to come, or the release dates of the
     * learners enrolled before it are not moved yet.
     *
     * @param \stdClass|null $record Launch record.
     * @return bool
     */
    public static function record_locks(?\stdClass $record): bool {
        return $record && (int) $record->launchdate > 0
            && ((int) $record->launchdate > self::now() || !(int) $record->processed);
    }

    /**
     * Whether the course is locked for its learners.
     *
     * @param int $courseid Course.
     * @return bool
     */
    public static function is_locked(int $courseid): bool {
        return self::record_locks(self::get($courseid));
    }

    /**
     * Launch date of a locked course, or null.
     *
     * @param int $courseid Course.
     * @return int|null
     */
    public static function locked_until(int $courseid): ?int {
        $record = self::get($courseid);
        return self::record_locks($record) ? (int) $record->launchdate : null;
    }

    /**
     * Whether someone works on the course (trainer, collaborator, coach, admin): never locked out.
     *
     * @param \context $context Course context.
     * @param int|null $userid User, the current one by default.
     * @return bool
     */
    public static function is_staff(\context $context, ?int $userid = null): bool {
        return has_any_capability(self::STAFF_CAPS, $context, $userid);
    }

    /**
     * Whether the course is locked for this user (a learner, not the team).
     *
     * @param int $courseid Course.
     * @param int|null $userid User, the current one by default.
     * @return bool
     */
    public static function is_locked_for(int $courseid, ?int $userid = null): bool {
        return self::is_locked($courseid) && !self::is_staff(\context_course::instance($courseid), $userid);
    }

    /**
     * A date as learners read it (day/month/year).
     *
     * @param int $time Timestamp.
     * @param bool $short Day and month only.
     * @return string
     */
    public static function format_date(int $time, bool $short = false): string {
        // Leading zeros kept: 05/12/2026.
        return userdate($time, get_string($short ? 'launch:dateformatshort' : 'launch:dateformat', 'local_oriskill'), 99,
            false, false);
    }

    /**
     * A day as a date field gives it (yyyy-mm-dd), for the date field.
     *
     * @param int $time Timestamp.
     * @return string
     */
    public static function field_date(int $time): string {
        return userdate($time, '%Y-%m-%d', 99, false, false);
    }

    /**
     * Launch date from a date field (yyyy-mm-dd): that day at midnight, in the trainer's time zone.
     *
     * @param string $value Value of the field.
     * @return int Timestamp.
     */
    public static function parse_date(string $value): int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($value), $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new \moodle_exception('launch:err:date', 'local_oriskill');
        }
        return make_timestamp((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * "Opens on dd/mm/yyyy" for a locked course, empty otherwise.
     *
     * @param int $courseid Course.
     * @return string Plain text.
     */
    public static function opens_label(int $courseid): string {
        $until = self::locked_until($courseid);
        return $until ? get_string('launch:opens', 'local_oriskill', self::format_date($until)) : '';
    }

    /**
     * Make the course visible but locked until a launch date. A course already processed is not processed again: the
     * release dates of its learners are not moved a second time.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course of the trainer (checked by the caller).
     * @param int $launchdate Launch date, in the future.
     */
    public static function set(\stdClass $creator, \stdClass $course, int $launchdate): void {
        global $DB;

        $now = self::now();
        if ($launchdate <= $now) {
            throw new \moodle_exception('launch:err:past', 'local_oriskill');
        }
        // Under the lock of the course: the launch may be processing at this very moment (apply()).
        $lock = self::lock((int) $course->id, 30);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'moodle');
        }
        try {
            $record = $DB->get_record(self::TABLE, ['courseid' => $course->id]) ?: null;
            $locked = self::record_locks($record);
            $before = $locked ? self::format_date((int) $record->launchdate) : '–';
            if ($locked) {
                // New date of the current lock: the pre-sale still counts from the start of that lock.
                $DB->update_record(self::TABLE, (object) ['id' => $record->id, 'launchdate' => $launchdate,
                    'timemodified' => $now]);
            } else if ($record) {
                // A new lock (the previous one was removed or processed): its pre-sale starts now, moved at its launch.
                $DB->update_record(self::TABLE, (object) ['id' => $record->id, 'launchdate' => $launchdate,
                    'lockedsince' => $now, 'processed' => 0, 'timemodified' => $now]);
            } else {
                $DB->insert_record(self::TABLE, (object) ['courseid' => $course->id, 'launchdate' => $launchdate,
                    'lockedsince' => $now, 'processed' => 0, 'timecreated' => $now, 'timemodified' => $now]);
            }
        } finally {
            $lock->release();
            self::forget((int) $course->id);
        }
        // Locked first, then shown: learners never see it open in between.
        if (!$course->visible) {
            course_list::set_visible($creator, $course, true);
        }
        log_service::add($creator, 'course_launch', ['date' => self::format_date($launchdate)], (int) $course->id, null,
            [get_string('courses:launchdate', 'local_oriskill') => [$before, self::format_date($launchdate)]]);
    }

    /**
     * Open the course right away ("visible right away"): the launch date is removed. If the course was still locked (date
     * to come or passed, not processed), the learners who enrolled during the pre-sale start now; the learners enrolled
     * before the lock are never touched.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course of the trainer (checked by the caller).
     */
    public static function clear(\stdClass $creator, \stdClass $course): void {
        global $DB;

        $courseid = (int) $course->id;
        $record = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
        if (!$record || !(int) $record->launchdate) {
            return;
        }
        $lock = self::lock($courseid, 30);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'moodle');
        }
        try {
            // Read again under the lock: the scheduled task or a learner may have processed it meanwhile.
            $record = $DB->get_record(self::TABLE, ['courseid' => $courseid], '*', MUST_EXIST);
            $previous = (int) $record->launchdate;
            if ($previous && !(int) $record->processed) {
                self::apply($record, self::now(), ['launchdate' => 0]);
            } else if ($previous) {
                // Already processed: nothing to move again.
                $DB->update_record(self::TABLE, (object) ['id' => $record->id, 'launchdate' => 0, 'timemodified' => self::now()]);
            }
        } finally {
            $lock->release();
            self::forget($courseid);
        }
        if ($previous) {
            self::forget_failure($courseid);
            log_service::add($creator, 'course_launchcleared', [], $courseid, null,
                [get_string('courses:launchdate', 'local_oriskill') => [self::format_date($previous), '–']]);
        }
    }

    /**
     * On the launch day: the pre-sale enrolments (since the lock began) start on that day (the team left out), then the course
     * is marked as processed and opens. Done once, under a lock; running it again changes nothing. A hidden course waits:
     * nobody can open it, and the trainer chooses again when showing it.
     *
     * @param int $courseid Course.
     * @param int $timeout Seconds to wait for another request doing it.
     * @return bool Whether the course is open now.
     */
    public static function process(int $courseid, int $timeout = 5): bool {
        global $DB;

        self::forget($courseid);
        $record = self::get($courseid);
        if (!self::record_locks($record)) {
            return true;
        }
        if ((int) $record->launchdate > self::now() || !$DB->get_field('course', 'visible', ['id' => $courseid])) {
            return false;
        }
        $lock = self::lock($courseid, $timeout);
        if (!$lock) {
            return false;
        }
        try {
            // Read again under the lock: another request may have done it, or the trainer changed the date.
            $record = $DB->get_record(self::TABLE, ['courseid' => $courseid]);
            if ($record && (int) $record->launchdate > 0 && (int) $record->launchdate <= self::now()
                    && !(int) $record->processed) {
                self::apply($record, (int) $record->launchdate);
            }
        } finally {
            $lock->release();
            self::forget($courseid);
        }
        return !self::is_locked($courseid);
    }

    /**
     * process() for a page: never an error for the learner (the course simply stays locked, "opening"), and after a
     * failure the pages leave it to the scheduled task for a while instead of trying again at each request.
     *
     * @param int $courseid Course.
     * @param int $timeout Seconds to wait for another request doing it.
     * @param bool $retrynow Try even after a recent failure (scheduled task).
     * @return bool Whether the course is open now.
     */
    public static function try_process(int $courseid, int $timeout = 5, bool $retrynow = false): bool {
        $failed = (int) get_config('local_oriskill', self::FAILURE . $courseid);
        if (!$retrynow && $failed > self::now() - self::RETRYAFTER) {
            return false;
        }
        try {
            $open = self::process($courseid, $timeout);
        } catch (\Throwable $e) {
            set_config(self::FAILURE . $courseid, self::now(), 'local_oriskill');
            debugging('Launch of course ' . $courseid . ' failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            try {
                if ($creator = seller_service::owner_of_course($courseid)) {
                    log_service::add($creator, 'course_launchfailed', ['error' => $e->getMessage()], $courseid);
                }
            } catch (\Throwable $ignored) {
                // The log is not a reason to fail.
                debugging($ignored->getMessage(), DEBUG_DEVELOPER);
            }
            return false;
        }
        if ($failed) {
            self::forget_failure($courseid);
        }
        return $open;
    }

    /**
     * Forget the last failure of the launch of a course.
     *
     * @param int $courseid Course.
     */
    private static function forget_failure(int $courseid): void {
        if (get_config('local_oriskill', self::FAILURE . $courseid) !== false) {
            unset_config(self::FAILURE . $courseid, 'local_oriskill');
        }
    }

    /**
     * Lock of the launch of a course (only one request moves the release dates).
     *
     * @param int $courseid Course.
     * @param int $timeout Seconds to wait.
     * @return \core\lock\lock|false
     */
    private static function lock(int $courseid, int $timeout) {
        return \core\lock\lock_config::get_lock_factory('local_oriskill_launch')->get_lock('course' . $courseid, $timeout);
    }

    /**
     * Move the enrolments to a start and mark the course as processed, all or nothing: a failure leaves the course
     * locked and unprocessed, to be tried again. Called under the lock of the course.
     *
     * @param \stdClass $record Launch record.
     * @param int $start New start (launch date, or now when opened right away).
     * @param array $fields Other fields of the record to change with it.
     */
    private static function apply(\stdClass $record, int $start, array $fields = []): void {
        global $DB;

        $now = self::now();
        $transaction = $DB->start_delegated_transaction();
        try {
            self::move_starts((int) $record->courseid, $start, (int) $record->lockedsince);
            $DB->update_record(self::TABLE,
                (object) (['id' => $record->id, 'processed' => $now, 'timemodified' => $now] + $fields));
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
        enroldays::reset_cache();
    }

    /**
     * Process the courses whose launch date has come (scheduled task, in case no learner opened them yet).
     * Hidden courses wait: nobody can open them, and the trainer chooses a new date when showing them.
     *
     * @return int Courses opened.
     */
    public static function process_due(): int {
        global $DB;

        $courseids = $DB->get_fieldset_sql(
            "SELECT l.courseid
               FROM {" . self::TABLE . "} l
               JOIN {course} c ON c.id = l.courseid
              WHERE l.launchdate > 0 AND l.launchdate <= :now AND l.processed = 0 AND c.visible = 1
           ORDER BY l.launchdate",
            ['now' => self::now()]);
        $done = 0;
        foreach ($courseids as $courseid) {
            // One course failing does not stop the others (logged); it is tried again at the next run.
            if (self::try_process((int) $courseid, 60, true)) {
                $done++;
            } else if (get_config('local_oriskill', self::FAILURE . $courseid)) {
                mtrace('Launch of course ' . $courseid . ' failed: see the log of its trainer.');
            }
        }
        return $done;
    }

    /**
     * Enrolments of the pre-sale (started since the lock began, before the launch) start on the launch day: modules
     * released D+N count from it. Enrolments from before the lock are never touched. Left out too: the trainer, their
     * team and anyone working on the course (STAFF_CAPS), and enrolments that ended before the launch.
     *
     * The end of a pre-sale access limited in time moves by as much, only when it is certainly the planned duration of
     * that access (see extendable_end()); otherwise it is kept: in doubt, an access is never made longer.
     *
     * @param int $courseid Course.
     * @param int $launchdate Launch date, or now when opened right away (past or now: the enrolments stay active).
     * @param int $lockedsince Start of the lock (0: unknown, nobody is moved).
     */
    private static function move_starts(int $courseid, int $launchdate, int $lockedsince): void {
        global $DB;

        if ($lockedsince <= 0) {
            // Start of the lock unknown: in doubt, nobody is moved.
            return;
        }
        $params = ['courseid' => $courseid, 'launch' => $launchdate, 'launchend' => $launchdate, 'lockedsince' => $lockedsince];
        $staff = [];
        if ($owner = seller_service::owner_of_course($courseid)) {
            $staff = array_merge([(int) $owner->userid], team_service::member_userids($owner));
        }
        $context = \context_course::instance($courseid);
        $staff = array_merge($staff, array_map('intval', array_keys(get_users_by_capability($context, self::STAFF_CAPS, 'u.id'))));
        $staffsql = '';
        if ($staff) {
            [$notin, $staffparams] = $DB->get_in_or_equal(array_unique($staff), SQL_PARAMS_NAMED, 'staff', false);
            $staffsql = "AND ue.userid $notin";
            $params += $staffparams;
        }
        $enrolments = $DB->get_records_sql(
            "SELECT ue.id, ue.userid, ue.enrolid, ue.status, ue.timestart, ue.timecreated, ue.timeend
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                AND (CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END) >= :lockedsince
                AND (CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END) < :launch
                AND (ue.timeend = 0 OR ue.timeend > :launchend) $staffsql",
            $params);
        if (!$enrolments) {
            return;
        }
        $paying = self::paying_userids($courseid);
        $capturedays = self::capture_durations($courseid);
        $instances = [];
        foreach ($enrolments as $ue) {
            $instance = $instances[$ue->enrolid] ??= $DB->get_record('enrol', ['id' => $ue->enrolid]);
            $oldstart = (int) $ue->timestart ?: (int) $ue->timecreated;
            $timeend = null;
            if ($instance && !in_array((int) $ue->userid, $paying, true)
                    && self::extendable_end($ue, $instance, $oldstart, $capturedays)) {
                $timeend = (int) $ue->timeend + ($launchdate - $oldstart);
                if ((int) $instance->enrolenddate > 0) {
                    // Never beyond the end of the enrolment method itself.
                    $timeend = min($timeend, (int) $instance->enrolenddate);
                }
            }
            $plugin = $instance ? enrol_get_plugin($instance->enrol) : null;
            if ($plugin) {
                $plugin->update_user_enrol($instance, (int) $ue->userid, null, $launchdate, $timeend);
            } else {
                // Enrolment method no longer installed: the start is changed directly (the end is kept).
                $DB->update_record('user_enrolments', (object) ['id' => $ue->id, 'timestart' => $launchdate,
                    'timemodified' => self::now()]);
            }
            if ($timeend !== null) {
                self::move_entitlement_end((int) $ue->id, (int) $ue->timeend, $timeend);
            }
        }
    }

    /**
     * Whether the end of an access is certainly its planned duration, counted from its start, so that it can move with
     * the start: the duration of the enrolment method (enrolperiod: Modern Commerce purchase, self enrolment...), or the
     * access duration of one of the trainer's capture pages opening this course (to a few minutes, the time of the
     * sign-up). Any other end (set by the trainer, by a cancelled or unpaid subscription, capped by the end of the method,
     * a suspended access) is kept.
     *
     * @param \stdClass $ue User enrolment (status, timeend).
     * @param \stdClass $instance Enrolment method.
     * @param int $oldstart Start of the access.
     * @param int[] $capturedays Access durations (seconds) of the capture pages of the course.
     * @return bool
     */
    private static function extendable_end(\stdClass $ue, \stdClass $instance, int $oldstart, array $capturedays): bool {
        if ((int) $ue->timeend <= 0 || (int) $ue->status !== ENROL_USER_ACTIVE) {
            return false;
        }
        $duration = (int) $ue->timeend - $oldstart;
        if ((int) $instance->enrolperiod > 0 && $duration === (int) $instance->enrolperiod) {
            return true;
        }
        if ($instance->enrol === 'manual') {
            foreach ($capturedays as $seconds) {
                if (abs($duration - $seconds) <= 5 * MINSECS) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Learners of the course with a payment in instalments, a subscription, an unpaid instalment or a schedule taken over
     * from another platform: the end of their access belongs to their payments (SecureAndPay, Modern Commerce
     * subscriptions) and is never moved.
     *
     * @param int $courseid Course.
     * @return int[] User ids.
     */
    private static function paying_userids(int $courseid): array {
        global $DB;

        $userids = $DB->get_fieldset_sql(
            "SELECT i.userid
               FROM {local_oriskill_instalments} i
              WHERE i.courseid = :c1
                 OR EXISTS (SELECT 1 FROM {local_moderncommerce_order_items} oi WHERE oi.orderid = i.orderid AND oi.courseid = :c2)
              UNION
             SELECT u.userid FROM {local_oriskill_unpaid} u WHERE u.courseid = :c3
              UNION
             SELECT r.userid FROM {local_oriskill_resumed} r WHERE r.courseid = :c4",
            ['c1' => $courseid, 'c2' => $courseid, 'c3' => $courseid, 'c4' => $courseid]);
        if ($DB->get_manager()->table_exists('local_moderncommerce_subscription_access')) {
            $userids = array_merge($userids, $DB->get_fieldset_select('local_moderncommerce_subscription_access', 'userid',
                'courseid = ?', [$courseid]));
        }
        return array_values(array_unique(array_map('intval', $userids)));
    }

    /**
     * Access durations (seconds) of the trainer's capture pages that open this course with a limited access.
     *
     * @param int $courseid Course.
     * @return int[]
     */
    private static function capture_durations(int $courseid): array {
        global $DB;

        $durations = [];
        foreach ($DB->get_records_select('local_oriskill_capturepages', 'accessdays > 0', null, '', 'id, courseids, accessdays')
                as $page) {
            if (in_array($courseid, array_map('intval', explode(',', (string) $page->courseids)), true)) {
                $durations[] = (int) $page->accessdays * DAYSECS;
            }
        }
        return array_values(array_unique($durations));
    }

    /**
     * Keep the Modern Commerce access record of a moved enrolment in step (its end is checked by the store), when its
     * end was the one of the enrolment.
     *
     * @param int $ueid User enrolment.
     * @param int $oldend End before.
     * @param int $newend End after.
     */
    private static function move_entitlement_end(int $ueid, int $oldend, int $newend): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_moderncommerce_entitlements')) {
            return;
        }
        $DB->set_field_select('local_moderncommerce_entitlements', 'timeend', $newend,
            'userenrolmentid = :ueid AND timeend = :oldend', ['ueid' => $ueid, 'oldend' => $oldend]);
    }

    /**
     * In every page of a course (course, modules, sections, files of the modules, web services) reached through
     * require_login(): a learner of a locked course is sent to the page "Opens on ...". Called by the
     * after_require_login callback (lib.php), once the access to the course is granted and before any output.
     *
     * @param mixed $courseorid Course or course id given to require_login().
     * @param \cm_info|\stdClass|null $cm Course module given to require_login().
     * @param bool $preventredirect No redirect possible (web services, AJAX): an exception instead.
     */
    public static function guard($courseorid, $cm, bool $preventredirect): void {
        global $SCRIPT;

        if (during_initial_install() || CLI_SCRIPT) {
            return;
        }
        $courseid = $cm ? (int) $cm->course : (is_object($courseorid) ? (int) ($courseorid->id ?? 0) : (int) $courseorid);
        if (!$courseid || $courseid == SITEID) {
            return;
        }
        try {
            if (!self::is_locked($courseid)) {
                return;
            }
        } catch (\dml_exception $e) {
            // Table not installed yet (upgrade pending): nothing is locked.
            return;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || self::is_staff($context)) {
            return;
        }
        $script = (string) $SCRIPT;
        foreach (self::OPEN_PATHS as $path) {
            if (str_starts_with($script, $path)) {
                return;
            }
        }
        // Own pages (store, order forms, the lock page itself) stay open, but the comments under the lessons.
        if (str_starts_with($script, '/local/oriskill/') && $script !== '/local/oriskill/comment.php') {
            return;
        }
        // Launch day reached: the release dates are moved first, then the course opens. The page is then asked again, to be
        // built with the new starts (not possible for web services and AJAX, nor worth losing a form sent: they go on).
        $until = self::locked_until($courseid);
        if ($until && $until <= self::now() && self::try_process($courseid)) {
            if (!$preventredirect && !AJAX_SCRIPT && !WS_SERVER && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                redirect(qualified_me());
            }
            return;
        }
        if ($preventredirect) {
            throw new \core\exception\require_login_exception('Course not launched yet');
        }
        redirect(new \moodle_url('/local/oriskill/launch.php', ['id' => $courseid]));
    }

    /**
     * A course is being deleted: its launch date goes with it.
     *
     * @param \core_course\hook\before_course_deleted $hook Hook.
     */
    public static function before_course_deleted(\core_course\hook\before_course_deleted $hook): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['courseid' => $hook->course->id]);
        self::forget((int) $hook->course->id);
        self::forget_failure((int) $hook->course->id);
    }
}
