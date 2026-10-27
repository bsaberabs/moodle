<?php
// This file is part of Moodle and is licensed under the
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
 * External API for learner grade summary.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_course;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_url;

/**
 * Returns the logged-in learner's course grade overview data.
 */
class list_grades extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute.
     *
     * @return array Grade response.
     */
    public static function execute(): array {
        global $CFG, $USER;

        self::validate_parameters(self::execute_parameters(), []);
        require_login();

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $courses = [];
        foreach (enrol_get_users_courses((int)$USER->id, true, 'id, shortname, fullname, showgrades, visible') as $course) {
            if ((int)$course->id === SITEID || empty($course->visible) || empty($course->showgrades)) {
                continue;
            }

            $coursecontext = context_course::instance((int)$course->id, IGNORE_MISSING);
            if (!$coursecontext || !has_capability('moodle/grade:view', $coursecontext)) {
                continue;
            }

            $courses[] = self::course_row($course, (int)$USER->id);
        }

        usort($courses, static function (array $left, array $right): int {
            return strcasecmp($left['fullname'], $right['fullname']);
        });

        $stats = self::stats($courses);

        return [
            'success' => true,
            'message' => '',
            'courses' => $courses,
            'stats' => $stats,
            'urls' => [
                'fullreport' => (new moodle_url('/grade/report/overview/index.php'))->out(false),
                'courses' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/courses',
            ],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether grades loaded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'courses' => new external_multiple_structure(self::course_structure()),
            'stats' => self::stats_structure(),
            'urls' => new external_single_structure([
                'fullreport' => new external_value(PARAM_RAW, 'Full Moodle grade overview URL.'),
                'courses' => new external_value(PARAM_RAW, 'Learner courses URL.'),
            ]),
        ]);
    }

    /**
     * Build one grade course row.
     *
     * @param \stdClass $course Course record.
     * @param int $userid User ID.
     * @return array
     */
    private static function course_row(\stdClass $course, int $userid): array {
        $completion = self::completion_data($course, $userid);
        $grade = self::course_grade((int)$course->id, $userid);
        $iscomplete = $completion['progress'] >= 100;

        return [
            'courseid' => (int)$course->id,
            'fullname' => format_string((string)$course->fullname),
            'shortname' => format_string((string)$course->shortname),
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'progress' => $completion['progress'],
            'completedactivities' => $completion['completed'],
            'totalactivities' => $completion['total'],
            'completionenabled' => $completion['total'] > 0,
            'iscomplete' => $iscomplete,
            'statuslabel' => $completion['total'] > 0
                ? get_string($iscomplete ? 'completed' : 'inprogress', 'local_moderncommerce')
                : get_string('completionnotenabled', 'local_moderncommerce'),
            'statusclass' => $iscomplete ? 'success' : ($completion['total'] > 0 ? 'warning' : 'neutral'),
            'hasgrade' => $grade['hasgrade'],
            'gradepercentage' => $grade['gradepercentage'],
            'gradelabel' => $grade['gradelabel'],
        ];
    }

    /**
     * Get completion progress for a course.
     *
     * @param \stdClass $course Course record.
     * @param int $userid User ID.
     * @return array
     */
    private static function completion_data(\stdClass $course, int $userid): array {
        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return [
                'total' => 0,
                'completed' => 0,
                'progress' => 0,
            ];
        }

        $total = 0;
        $completed = 0;
        $modinfo = get_fast_modinfo($course, $userid);

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || (int)$cm->completion === COMPLETION_TRACKING_NONE) {
                continue;
            }

            $total++;
            $data = $completion->get_data($cm, false, $userid);
            if (in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                $completed++;
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'progress' => $total > 0 ? (int)round(($completed / $total) * 100) : 0,
        ];
    }

    /**
     * Get final course grade.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return array
     */
    private static function course_grade(int $courseid, int $userid): array {
        global $DB;

        $sql = "SELECT gi.id,
                       gi.grademax,
                       gg.finalgrade
                  FROM {grade_items} gi
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                 WHERE gi.courseid = :courseid
                   AND gi.itemtype = :itemtype
                   AND (gi.hidden = 0 OR gi.hidden IS NULL)
                   AND (gg.hidden = 0 OR gg.hidden IS NULL)";
        $grade = $DB->get_record_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'itemtype' => 'course',
        ]);

        // No usable course grade item: the course simply is not graded. A grademax of 0
        // makes the percentage undefined, so it counts as ungraded too.
        if (!$grade || (float)$grade->grademax <= 0) {
            return [
                'hasgrade' => false,
                'gradepercentage' => 0,
                'gradelabel' => get_string('gradeungraded', 'local_moderncommerce'),
            ];
        }

        // The course is graded, but this user has no result recorded yet.
        if ($grade->finalgrade === null) {
            return [
                'hasgrade' => false,
                'gradepercentage' => 0,
                'gradelabel' => get_string('gradenotyet', 'local_moderncommerce'),
            ];
        }

        $percentage = (int)round(((float)$grade->finalgrade / (float)$grade->grademax) * 100);

        return [
            'hasgrade' => true,
            'gradepercentage' => $percentage,
            'gradelabel' => get_string('gradepercent', 'local_moderncommerce', $percentage),
        ];
    }

    /**
     * Build grade stats.
     *
     * @param array $courses Course rows.
     * @return array
     */
    private static function stats(array $courses): array {
        $graded = array_values(array_filter($courses, static function (array $course): bool {
            return !empty($course['hasgrade']);
        }));
        $completed = array_values(array_filter($courses, static function (array $course): bool {
            return !empty($course['iscomplete']);
        }));
        $average = 0;

        if (!empty($graded)) {
            $average = (int)round(array_sum(array_map(static function (array $course): int {
                return (int)$course['gradepercentage'];
            }, $graded)) / count($graded));
        }

        return [
            'courses' => count($courses),
            'gradedcourses' => count($graded),
            'completedcourses' => count($completed),
            'gradeaverage' => $average,
            'hasgradeaverage' => !empty($graded),
        ];
    }

    /**
     * Course structure.
     *
     * @return external_single_structure
     */
    private static function course_structure(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Course name.'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name.'),
            'courseurl' => new external_value(PARAM_RAW, 'Course URL.'),
            'progress' => new external_value(PARAM_INT, 'Completion progress percentage.'),
            'completedactivities' => new external_value(PARAM_INT, 'Completed tracked activities.'),
            'totalactivities' => new external_value(PARAM_INT, 'Total tracked activities.'),
            'completionenabled' => new external_value(PARAM_BOOL, 'Whether completion tracking is available.'),
            'iscomplete' => new external_value(PARAM_BOOL, 'Whether complete.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Completion status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Completion status class.'),
            'hasgrade' => new external_value(PARAM_BOOL, 'Whether a course grade is available.'),
            'gradepercentage' => new external_value(PARAM_INT, 'Grade percentage.'),
            'gradelabel' => new external_value(PARAM_TEXT, 'Grade label.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_value(PARAM_INT, 'Visible grade course count.'),
            'gradedcourses' => new external_value(PARAM_INT, 'Courses with grades.'),
            'completedcourses' => new external_value(PARAM_INT, 'Completed courses.'),
            'gradeaverage' => new external_value(PARAM_INT, 'Average grade percentage.'),
            'hasgradeaverage' => new external_value(PARAM_BOOL, 'Whether an average grade exists.'),
        ]);
    }
}
