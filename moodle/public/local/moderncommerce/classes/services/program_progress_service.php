<?php
// This file is part of Moodle and is licensed under the GNU GPL v3 or later.
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

/**
 * Program enrollment, completion, and certificate service.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

use local_moderncommerce\api\bundle_api;

/**
 * Canonical runtime for ModernCommerce programs.
 *
 * Program access and program completion are deliberately separate. Receiving a
 * program grants every included course immediately. Prerequisites, course
 * completion, grades, and must-pass rules determine completion and certificate
 * eligibility; they never lock a course.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_progress_service {
    /** Program progress table name. */
    private const TABLE = 'local_moderncommerce_program_progress';

    /**
     * Start or refresh a learner's program.
     *
     * @param int $userid Learner ID.
     * @param int $programid Program product ID.
     * @param int|null $orderid Related order ID.
     * @return array
     */
    public static function enrol(int $userid, int $programid, ?int $orderid = null): array {
        if (!self::is_program($programid)) {
            throw new \moodle_exception('invalidprogram', 'local_moderncommerce');
        }
        return self::sync($userid, $programid, $orderid, true);
    }

    /**
     * Recalculate progress, ensure course access, and issue the completion certificate.
     *
     * @param int $userid Learner ID.
     * @param int $programid Program product ID.
     * @param int|null $orderid Related order ID.
     * @param bool $issuecertificate Whether certificate issuance is allowed.
     * @return array
     */
    public static function sync(
        int $userid,
        int $programid,
        ?int $orderid = null,
        bool $issuecertificate = true
    ): array {
        global $DB;

        $program = $DB->get_record('local_moderncommerce_products', [
            'id' => $programid,
            'producttype' => 'program',
        ], 'id, name', MUST_EXIST);
        $meta = bundle_meta_service::get_meta($programid);
        $existingprogress = $DB->get_record(self::TABLE, ['programid' => $programid, 'userid' => $userid]);
        $effectiveorderid = $orderid ?: (
            $existingprogress && !empty($existingprogress->orderid) ? (int)$existingprogress->orderid : null
        );
        $courses = array_values(bundle_api::get_courses($programid));
        usort($courses, static fn($a, $b) => [(int)$a->sortorder, (int)$a->id] <=> [(int)$b->sortorder, (int)$b->id]);

        $prerequisites = [];
        foreach (bundle_meta_service::get_prereq_courses($programid) as $course) {
            $courseid = (int)$course['id'];
            $prerequisites[] = [
                'id' => $courseid,
                'name' => (string)$course['fullname'],
                'completed' => self::course_completed($userid, $courseid),
            ];
        }
        $prerequisitesmet = !array_filter($prerequisites, static fn(array $row): bool => !$row['completed']);
        $mustpass = array_flip(bundle_meta_service::get_mustpass($programid));
        $threshold = (float)($meta['pass_grade'] ?? 70.0);
        $coursestates = [];
        $completed = 0;
        $passed = 0;
        $gradepoints = 0.0;
        $graded = 0;
        $currentcourseid = 0;

        foreach ($courses as $mapping) {
            $courseid = (int)$mapping->courseid;
            $iscomplete = self::course_completed($userid, $courseid);
            $grade = self::course_grade_percentage($userid, $courseid);
            $ispassed = $iscomplete && ($grade === null || $grade >= $threshold);
            self::grant_course_access($userid, $courseid, $effectiveorderid, $programid);
            if (!$iscomplete && $currentcourseid === 0) {
                $currentcourseid = $courseid;
            }
            if ($iscomplete) {
                $completed++;
            }
            if ($ispassed) {
                $passed++;
            }
            if ($grade !== null) {
                $gradepoints += $grade;
                $graded++;
            }
            $coursestates[] = [
                'id' => $courseid,
                'sortorder' => (int)$mapping->sortorder,
                'required' => !isset($mapping->required) || !empty($mapping->required),
                'completed' => $iscomplete,
                'passed' => $ispassed,
                'grade' => $grade,
                'mustpass' => isset($mustpass[$courseid]),
                'unlocked' => true,
            ];
        }

        $total = count($coursestates);
        $average = $graded > 0 ? round($gradepoints / $graded, 2) : 0.0;
        $policy = (string)($meta['pass_policy'] ?? 'all_must_pass');
        if (!in_array($policy, ['all_must_pass', 'weighted_avg', 'any_pass'], true)) {
            $policy = 'all_must_pass';
        }
        $allmustpasspassed = $policy === 'all_must_pass' || !array_filter(
            $coursestates,
            static fn(array $row): bool => $row['mustpass'] && !$row['passed']
        );
        if ($policy === 'weighted_avg') {
            $policycomplete = $total > 0 && $completed === $total && $average >= $threshold;
        } else if ($policy === 'any_pass') {
            $policycomplete = $passed > 0;
        } else {
            $policycomplete = $total > 0 && $passed === $total;
        }
        // Pass policies may add grade requirements, but never permit an award
        // before every included course has been completed.
        $allcoursescomplete = $total > 0 && $completed === $total;
        $iscomplete = $prerequisitesmet && $allcoursescomplete && $allmustpasspassed && $policycomplete;
        $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0.0;
        $now = time();

        $record = $existingprogress;
        $wascomplete = $record && $record->status === 'completed';
        $persisted = (object)[
            'programid' => $programid,
            'userid' => $userid,
            'orderid' => $effectiveorderid,
            'status' => $iscomplete ? 'completed' : 'active',
            'totalcourses' => $total,
            'completedcourses' => $completed,
            'passedcourses' => $passed,
            'averagegrade' => $average,
            'progresspercentage' => $percentage,
            'currentcourseid' => $currentcourseid ?: null,
            'timecompleted' => $iscomplete ? ((int)($record->timecompleted ?? 0) ?: $now) : null,
            'timemodified' => $now,
        ];
        if ($record) {
            $persisted->id = $record->id;
            $DB->update_record(self::TABLE, $persisted);
        } else {
            $persisted->certificateissueid = null;
            $persisted->timecreated = $now;
            $persisted->id = $DB->insert_record(self::TABLE, $persisted);
            $record = $persisted;
        }

        $certificateissueid = (int)($record->certificateissueid ?? 0);
        if ($iscomplete && $issuecertificate && !empty($meta['bundle_cert_enabled'])) {
            $certificateissueid = self::issue_certificate($userid, $programid, (int)$persisted->id, $meta);
        }

        if ($iscomplete && !$wascomplete) {
            \local_moderncommerce\event\program_completed::create([
                'context' => \context_system::instance(),
                'objectid' => (int)$persisted->id,
                'relateduserid' => $userid,
                'other' => [
                    'programid' => $programid,
                    'certificateissueid' => $certificateissueid,
                ],
            ])->trigger();
        }

        return [
            'programid' => $programid,
            'userid' => $userid,
            'status' => $persisted->status,
            'completed' => $iscomplete,
            'totalcourses' => $total,
            'coursescompleted' => $completed,
            'coursespassed' => $passed,
            'averagegrade' => $average,
            'progresspercentage' => $percentage,
            'currentcourseid' => $currentcourseid,
            'certificateissued' => $certificateissueid > 0,
            'certificateissueid' => $certificateissueid,
            'prerequisitesmet' => $prerequisitesmet,
            'prerequisites' => $prerequisites,
            'courses' => $coursestates,
            'passpolicy' => $policy,
            'passgrade' => $threshold,
        ];
    }

    /**
     * Refresh every purchased program containing a completed course.
     *
     * @param int $userid Learner ID.
     * @param int $courseid Completed course ID.
     */
    public static function sync_for_course(int $userid, int $courseid): void {
        global $DB;
        $programids = $DB->get_fieldset_sql(
            "SELECT DISTINCT p.id
               FROM {local_moderncommerce_products} p
          LEFT JOIN {local_moderncommerce_product_courses} pc ON pc.productid = p.id
               JOIN {local_moderncommerce_order_items} oi ON oi.productid = p.id
               JOIN {local_moderncommerce_orders} o ON o.id = oi.orderid
              WHERE p.producttype = :type
                AND (pc.courseid = :courseid OR EXISTS (
                    SELECT 1 FROM {local_moderncommerce_bundle_prereq} prereq
                     WHERE prereq.bundleid = p.id AND prereq.courseid = :prereqcourseid
                ))
                AND o.userid = :userid AND o.status IN ('paid', 'completed')",
            [
                'type' => 'program', 'courseid' => $courseid,
                'prereqcourseid' => $courseid, 'userid' => $userid,
            ]
        );
        foreach ($programids as $programid) {
            self::sync($userid, (int)$programid);
        }
    }

    /**
     * Initialize progress for existing purchasers during bundle conversion.
     *
     * @param int $programid Program product ID.
     * @return array
     */
    public static function initialize_existing(int $programid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT o.userid, MIN(o.id) AS orderid
               FROM {local_moderncommerce_orders} o
               JOIN {local_moderncommerce_order_items} oi ON oi.orderid = o.id
              WHERE oi.productid = :programid AND o.status IN ('paid', 'completed')
           GROUP BY o.userid",
            ['programid' => $programid]
        );
        $migrated = 0;
        $completed = 0;
        foreach ($rows as $row) {
            $progress = self::sync((int)$row->userid, $programid, (int)$row->orderid);
            $migrated++;
            $completed += $progress['completed'] ? 1 : 0;
        }
        return ['migrated' => $migrated, 'completed' => $completed];
    }

    /**
     * Check whether a product is a program.
     *
     * @param int $programid Product ID.
     * @return bool
     */
    private static function is_program(int $programid): bool {
        global $DB;
        return $DB->record_exists('local_moderncommerce_products', ['id' => $programid, 'producttype' => 'program']);
    }

    /**
     * Check Moodle's authoritative course completion state.
     *
     * @param int $userid Learner ID.
     * @param int $courseid Course ID.
     * @return bool
     */
    private static function course_completed(int $userid, int $courseid): bool {
        global $DB;

        // Moodle writes the authoritative completion timestamp before emitting
        // core\event\course_completed. Reading it directly also avoids stale
        // completion_info caches during the observer request.
        if (
            $DB->record_exists_select(
                'course_completions',
                'userid = :userid AND course = :courseid AND timecompleted IS NOT NULL',
                ['userid' => $userid, 'courseid' => $courseid]
            )
        ) {
            return true;
        }

        try {
            $course = get_course($courseid);
            return (new \completion_info($course))->is_course_complete($userid);
        } catch (\Throwable $exception) {
            debugging('Program completion check failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Return the normalized final course grade percentage.
     *
     * @param int $userid Learner ID.
     * @param int $courseid Course ID.
     * @return float|null
     */
    private static function course_grade_percentage(int $userid, int $courseid): ?float {
        $grades = grade_get_course_grades($courseid, $userid);
        if (empty($grades->grades[$userid]) || $grades->grades[$userid]->grade === null) {
            return null;
        }
        $grade = $grades->grades[$userid];
        $min = (float)($grade->grademin ?? 0);
        $max = (float)($grade->grademax ?? 100);
        return $max > $min ? round((((float)$grade->grade - $min) / ($max - $min)) * 100, 2) : null;
    }

    /**
     * Ensure the learner can access an included program course.
     *
     * @param int $userid Learner ID.
     * @param int $courseid Course ID.
     * @param int|null $orderid Related order ID.
     * @param int $programid Program product ID.
     */
    private static function grant_course_access(int $userid, int $courseid, ?int $orderid, int $programid): void {
        enrolment_service::enrol_user_in_course($userid, $courseid, [
            'orderid' => $orderid ?: 0,
            'method' => 'program',
            'productid' => $programid,
        ]);
    }

    /**
     * Issue the program certificate once and persist its native issue ID.
     *
     * @param int $userid Learner ID.
     * @param int $programid Program product ID.
     * @param int $progressid Program progress record ID.
     * @param array $meta Program metadata.
     * @return int Certificate issue ID, or zero when no certificate was issued.
     */
    private static function issue_certificate(int $userid, int $programid, int $progressid, array $meta): int {
        global $DB;
        $templateid = certificate_template_service::resolve((int)($meta['certificate_template_id'] ?? 0));
        if ($templateid <= 0 || !class_exists(\tool_certificate\template::class)) {
            return 0;
        }
        $factory = \core\lock\lock_config::get_lock_factory('local_moderncommerce_program_certificate');
        $lock = $factory->get_lock('program_' . $programid . '_user_' . $userid, 10);
        if (!$lock) {
            return 0;
        }
        try {
            $progress = $DB->get_record(self::TABLE, ['id' => $progressid], '*', MUST_EXIST);
            if (!empty($progress->certificateissueid)) {
                return (int)$progress->certificateissueid;
            }
            $template = \tool_certificate\template::instance($templateid);
            $program = $DB->get_record('local_moderncommerce_products', ['id' => $programid], 'id, name', MUST_EXIST);
            $issueid = $template->issue_certificate($userid, null, [
                'programid' => $programid,
                'programname' => format_string($program->name),
            ], 'local_moderncommerce', null);
            $DB->set_field(self::TABLE, 'certificateissueid', $issueid, ['id' => $progressid, 'certificateissueid' => null]);
            return (int)$issueid;
        } catch (\Throwable $exception) {
            debugging('Program certificate issuance failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return 0;
        } finally {
            $lock->release();
        }
    }
}
