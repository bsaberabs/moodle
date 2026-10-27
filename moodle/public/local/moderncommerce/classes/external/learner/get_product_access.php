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
 * External API for learner product access details.
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
use local_moderncommerce\services\access_service;
use local_moderncommerce\services\program_progress_service;
use moodle_url;
use xmldb_table;

/**
 * Returns included courses for an owned bundle/program inside the learner app.
 */
class get_product_access extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Product ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Product ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $CFG, $DB, $USER;

        ['id' => $id] = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        require_login();
        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        if ($id <= 0 || !self::table_exists('local_moderncommerce_products')) {
            return self::empty_response(get_string('productaccessunavailable', 'local_moderncommerce'));
        }

        $product = $DB->get_record(
            'local_moderncommerce_products',
            ['id' => $id],
            'id, name, producttype, shortdescription, description, imageurl, status, visible'
        );
        if (!$product || !access_service::user_has_product_purchase_access((int)$USER->id, $id)) {
            return self::empty_response(get_string('productaccessunavailable', 'local_moderncommerce'));
        }

        $producttype = self::normalise_product_type((string)$product->producttype);
        $programprogress = self::empty_program_progress();
        if ($producttype === 'program') {
            $programprogress = program_progress_service::sync((int)$USER->id, $id);
        }
        $courses = self::product_courses((int)$USER->id, $id, $product, $producttype, $programprogress);
        $counts = self::counts($courses);

        return [
            'success' => true,
            'message' => '',
            'product' => self::product_data($product, $producttype, count($courses)),
            'courses' => $courses,
            'counts' => $counts,
            'urls' => self::urls($id, $producttype),
            'programprogress' => $programprogress,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether request succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'product' => self::product_structure(),
            'courses' => new external_multiple_structure(self::course_structure()),
            'counts' => self::counts_structure(),
            'urls' => self::url_structure(),
            'programprogress' => self::program_progress_structure(),
        ]);
    }

    /**
     * Empty response.
     *
     * @param string $message Message.
     * @return array
     */
    private static function empty_response(string $message): array {
        return [
            'success' => false,
            'message' => $message,
            'product' => [
                'id' => 0,
                'name' => '',
                'description' => '',
                'producttype' => '',
                'typelabel' => '',
                'coursecount' => 0,
                'imageurl' => '',
                'hasimage' => false,
            ],
            'courses' => [],
            'counts' => [
                'courses' => 0,
                'completed' => 0,
                'inprogress' => 0,
                'notstarted' => 0,
            ],
            'urls' => [
                'library' => self::learner_url('library'),
                'dashboard' => self::learner_url('dashboard'),
                'details' => '',
            ],
            'programprogress' => self::empty_program_progress(),
        ];
    }

    /**
     * Product data.
     *
     * @param \stdClass $product Product.
     * @param string $producttype Normalised product type.
     * @param int $coursecount Course count.
     * @return array
     */
    private static function product_data(\stdClass $product, string $producttype, int $coursecount): array {
        $description = trim(strip_tags((string)($product->shortdescription ?: $product->description)));
        $imageurl = (string)($product->imageurl ?? '');
        if ($imageurl === '' && in_array($producttype, ['bundle', 'program'], true)) {
            $imageurl = (string)\local_moderncommerce_get_bundle_image_url((int)$product->id);
        }

        return [
            'id' => (int)$product->id,
            'name' => format_string((string)$product->name),
            'description' => $description,
            'producttype' => $producttype,
            'typelabel' => self::product_type_label($producttype),
            'coursecount' => $coursecount,
            'imageurl' => $imageurl,
            'hasimage' => $imageurl !== '',
        ];
    }

    /**
     * Load product courses.
     *
     * @param int $userid User ID.
     * @param int $productid Product ID.
     * @param \stdClass $product Product.
     * @param string $producttype Product type.
     * @return array
     */
    private static function product_courses(
        int $userid,
        int $productid,
        \stdClass $product,
        string $producttype,
        array $programprogress
    ): array {
        global $DB;

        if (!self::table_exists('local_moderncommerce_product_courses')) {
            return [];
        }

        $sql = "SELECT pc.id AS mapid,
                       pc.courseid,
                       pc.sortorder,
                       c.fullname,
                       c.shortname,
                       c.summary,
                       c.category,
                       c.visible,
                       cc.name AS categoryname
                  FROM {local_moderncommerce_product_courses} pc
                  JOIN {course} c ON c.id = pc.courseid
             LEFT JOIN {course_categories} cc ON cc.id = c.category
                 WHERE pc.productid = :productid
                   AND pc.relationtype = :relationtype
              ORDER BY pc.sortorder ASC, c.fullname ASC";
        $records = $DB->get_records_sql($sql, [
            'productid' => $productid,
            'relationtype' => 'included',
        ]);

        $courses = [];
        $states = [];
        foreach ($programprogress['courses'] ?? [] as $state) {
            $states[(int)$state['id']] = $state;
        }
        foreach ($records as $record) {
            $course = self::course_data($userid, $record, $product, $producttype, $states[(int)$record->courseid] ?? null);
            if ($course) {
                $courses[] = $course;
            }
        }

        return $courses;
    }

    /**
     * Course data.
     *
     * @param int $userid User ID.
     * @param \stdClass $record Course row.
     * @param \stdClass $product Product.
     * @param string $producttype Product type.
     * @return array|null
     */
    private static function course_data(
        int $userid,
        \stdClass $record,
        \stdClass $product,
        string $producttype,
        ?array $programstate = null
    ): ?array {
        $courseid = (int)$record->courseid;
        // Prerequisites are completion/certificate requirements, not locks.
        $locked = false;
        if ($courseid <= 0 || !access_service::user_has_course_access($userid, $courseid)) {
            return null;
        }

        try {
            $course = get_course($courseid);
        } catch (\moodle_exception $exception) {
            debugging('Modern Commerce product access course lookup failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        $coursecontext = context_course::instance($courseid, IGNORE_MISSING);
        $completion = new \completion_info($course);
        $progress = 0;
        $completed = false;

        if ($completion->is_enabled()) {
            $percentage = \core_completion\progress::get_course_progress_percentage($course, $userid);
            $progress = $percentage !== null ? (int)round($percentage) : 0;
            $completed = $completion->is_course_complete($userid);
        }

        if ($completed || $progress >= 100) {
            $status = 'completed';
            $statuslabel = get_string('completed', 'local_moderncommerce');
        } else if ($progress > 0) {
            $status = 'inprogress';
            $statuslabel = get_string('inprogress', 'local_moderncommerce');
        } else {
            $status = 'notstarted';
            $statuslabel = get_string('notstarted', 'local_moderncommerce');
        }

        $imageurl = (string)\local_moderncommerce_get_course_image_url($courseid);
        $lastaccess = self::last_access($userid, $courseid);

        return [
            'id' => $courseid,
            'name' => $coursecontext
                ? format_string($course->fullname, true, ['context' => $coursecontext])
                : format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'summary' => trim(strip_tags((string)$course->summary)),
            'categoryid' => (int)$course->category,
            'categoryname' => format_string((string)($record->categoryname ?? '')),
            'imageurl' => $imageurl,
            'hasimage' => $imageurl !== '',
            'progress' => max(0, min(100, $progress)),
            'progresslabel' => max(0, min(100, $progress)) . '%',
            'completed' => $completed,
            'status' => $status,
            'statuslabel' => $statuslabel,
            'courseurl' => $locked ? '' : access_service::course_view_url($courseid)->out(false),
            'locked' => $locked,
            'unlockreason' => $locked ? get_string('programlocked', 'local_moderncommerce') : '',
            'modulecount' => self::module_count($course),
            'enrolleddate' => self::enrolled_date($userid, $courseid),
            'enrolleddatelabel' => self::enrolled_date_label($userid, $courseid),
            'lastaccess' => $lastaccess,
            // Date only, matching enrolleddatelabel: a clock time in a course card is
            // noise the learner cannot act on.
            'lastaccesslabel' => $lastaccess > 0
                ? userdate($lastaccess, get_string('strftimedateshort'))
                : get_string('never'),
            'source' => 'product',
            'sourcelabel' => self::product_type_label($producttype),
            'producttype' => $producttype,
            'productname' => format_string((string)$product->name),
        ];
    }

    /**
     * Build empty program progress for bundle and error responses.
     *
     * @return array
     */
    private static function empty_program_progress(): array {
        return [
            'programid' => 0, 'userid' => 0, 'status' => '', 'completed' => false,
            'totalcourses' => 0, 'coursescompleted' => 0, 'coursespassed' => 0,
            'averagegrade' => 0.0, 'progresspercentage' => 0.0, 'currentcourseid' => 0,
            'certificateissued' => false, 'certificateissueid' => 0, 'prerequisitesmet' => true,
            'prerequisites' => [], 'courses' => [], 'passpolicy' => '', 'passgrade' => 0.0,
        ];
    }

    /**
     * Build counts from course rows.
     *
     * @param array $courses Courses.
     * @return array
     */
    private static function counts(array $courses): array {
        $counts = [
            'courses' => count($courses),
            'completed' => 0,
            'inprogress' => 0,
            'notstarted' => 0,
        ];

        foreach ($courses as $course) {
            if (!empty($course['completed'])) {
                $counts['completed']++;
            } else if ((int)$course['progress'] > 0) {
                $counts['inprogress']++;
            } else {
                $counts['notstarted']++;
            }
        }

        return $counts;
    }

    /**
     * Count modules.
     *
     * @param \stdClass $course Course.
     * @return int
     */
    private static function module_count(\stdClass $course): int {
        try {
            return count(get_fast_modinfo($course)->get_cms());
        } catch (\moodle_exception $exception) {
            debugging('Modern Commerce product access module count failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    /**
     * Last access timestamp.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return int
     */
    private static function last_access(int $userid, int $courseid): int {
        global $DB;

        return (int)$DB->get_field('user_lastaccess', 'timeaccess', [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
    }

    /**
     * Enrolment timestamp.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return int
     */
    private static function enrolled_date(int $userid, int $courseid): int {
        global $DB;

        $timecreated = $DB->get_field_sql(
            "SELECT MIN(ue.timecreated)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                AND ue.userid = :userid",
            [
                'courseid' => $courseid,
                'userid' => $userid,
            ]
        );

        return $timecreated ? (int)$timecreated : 0;
    }

    /**
     * Enrolment date label.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return string
     */
    private static function enrolled_date_label(int $userid, int $courseid): string {
        $timecreated = self::enrolled_date($userid, $courseid);

        return $timecreated > 0 ? userdate($timecreated, get_string('strftimedateshort')) : '';
    }

    /**
     * URLs.
     *
     * @param int $productid Product ID.
     * @param string $producttype Product type.
     * @return array
     */
    private static function urls(int $productid, string $producttype): array {
        return [
            'library' => self::learner_url('library'),
            'dashboard' => self::learner_url('dashboard'),
            'details' => self::public_details_url($productid, $producttype),
        ];
    }

    /**
     * Learner app URL.
     *
     * @param string $route Route.
     * @return string
     */
    private static function learner_url(string $route): string {
        return (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/' . ltrim($route, '/');
    }

    /**
     * Public details URL.
     *
     * @param int $productid Product ID.
     * @param string $producttype Product type.
     * @return string
     */
    private static function public_details_url(int $productid, string $producttype): string {
        if (in_array($producttype, ['bundle', 'program'], true)) {
            return (new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $productid]))->out(false);
        }

        return '';
    }

    /**
     * Product type label.
     *
     * @param string $producttype Product type.
     * @return string
     */
    private static function product_type_label(string $producttype): string {
        $producttype = self::normalise_product_type($producttype);

        return get_string_manager()->string_exists($producttype, 'local_moderncommerce')
            ? get_string($producttype, 'local_moderncommerce')
            : ucfirst($producttype);
    }

    /**
     * Normalise product type.
     *
     * @param string $producttype Product type.
     * @return string
     */
    private static function normalise_product_type(string $producttype): string {
        $producttype = strtolower(trim($producttype));
        return $producttype === 'membership' ? 'subscription' : $producttype;
    }

    /**
     * Table existence helper.
     *
     * @param string $table Table name.
     * @return bool
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }

    /**
     * Product structure.
     *
     * @return external_single_structure
     */
    private static function product_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Product ID.'),
            'name' => new external_value(PARAM_TEXT, 'Product name.'),
            'description' => new external_value(PARAM_RAW, 'Plain product description.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Product type label.'),
            'coursecount' => new external_value(PARAM_INT, 'Included course count.'),
            'imageurl' => new external_value(PARAM_RAW, 'Product image URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether product has an image.'),
        ]);
    }

    /**
     * Course structure.
     *
     * @return external_single_structure
     */
    private static function course_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course ID.'),
            'name' => new external_value(PARAM_TEXT, 'Course name.'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name.'),
            'summary' => new external_value(PARAM_RAW, 'Plain course summary.'),
            'categoryid' => new external_value(PARAM_INT, 'Category ID.'),
            'categoryname' => new external_value(PARAM_TEXT, 'Category name.'),
            'imageurl' => new external_value(PARAM_RAW, 'Course image URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether course has an image.'),
            'progress' => new external_value(PARAM_INT, 'Progress percentage.'),
            'progresslabel' => new external_value(PARAM_TEXT, 'Progress label.'),
            'completed' => new external_value(PARAM_BOOL, 'Whether complete.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status key.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'courseurl' => new external_value(PARAM_RAW, 'Course URL.'),
            'locked' => new external_value(PARAM_BOOL, 'Deprecated compatibility flag; program courses are not locked.'),
            'unlockreason' => new external_value(PARAM_TEXT, 'Deprecated compatibility message; normally empty.'),
            'modulecount' => new external_value(PARAM_INT, 'Module count.'),
            'enrolleddate' => new external_value(PARAM_INT, 'Enrolment timestamp.'),
            'enrolleddatelabel' => new external_value(PARAM_TEXT, 'Enrolment date label.'),
            'lastaccess' => new external_value(PARAM_INT, 'Last access timestamp.'),
            'lastaccesslabel' => new external_value(PARAM_TEXT, 'Last access label.'),
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Access source.'),
            'sourcelabel' => new external_value(PARAM_TEXT, 'Access source label.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
            'productname' => new external_value(PARAM_TEXT, 'Product name.'),
        ]);
    }

    /**
     * Counts structure.
     *
     * @return external_single_structure
     */
    private static function counts_structure(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_value(PARAM_INT, 'Course count.'),
            'completed' => new external_value(PARAM_INT, 'Completed course count.'),
            'inprogress' => new external_value(PARAM_INT, 'In-progress course count.'),
            'notstarted' => new external_value(PARAM_INT, 'Not-started course count.'),
        ]);
    }

    /**
     * URL structure.
     *
     * @return external_single_structure
     */
    private static function url_structure(): external_single_structure {
        return new external_single_structure([
            'library' => new external_value(PARAM_RAW, 'Learner library URL.'),
            'dashboard' => new external_value(PARAM_RAW, 'Learner dashboard URL.'),
            'details' => new external_value(PARAM_RAW, 'Public details URL.'),
        ]);
    }

    /**
     * Build the program progress response structure.
     *
     * @return external_single_structure
     */
    private static function program_progress_structure(): external_single_structure {
        return new external_single_structure([
            'programid' => new external_value(PARAM_INT, 'Program ID.'),
            'userid' => new external_value(PARAM_INT, 'Learner ID.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Program status.'),
            'completed' => new external_value(PARAM_BOOL, 'Whether the completion policy is met.'),
            'totalcourses' => new external_value(PARAM_INT, 'Total program courses.'),
            'coursescompleted' => new external_value(PARAM_INT, 'Completed courses.'),
            'coursespassed' => new external_value(PARAM_INT, 'Passed courses.'),
            'averagegrade' => new external_value(PARAM_FLOAT, 'Average final course grade.'),
            'progresspercentage' => new external_value(PARAM_FLOAT, 'Completion percentage.'),
            'currentcourseid' => new external_value(PARAM_INT, 'First incomplete program course ID.'),
            'certificateissued' => new external_value(PARAM_BOOL, 'Whether the program certificate was issued.'),
            'certificateissueid' => new external_value(PARAM_INT, 'Tool certificate issue ID.'),
            'prerequisitesmet' => new external_value(PARAM_BOOL, 'Whether external prerequisites are complete.'),
            'prerequisites' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Prerequisite course ID.'),
                'name' => new external_value(PARAM_TEXT, 'Prerequisite course name.'),
                'completed' => new external_value(PARAM_BOOL, 'Whether prerequisite is complete.'),
            ])),
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID.'),
                'sortorder' => new external_value(PARAM_INT, 'Program order.'),
                'required' => new external_value(PARAM_BOOL, 'Whether required.'),
                'completed' => new external_value(PARAM_BOOL, 'Whether complete.'),
                'passed' => new external_value(PARAM_BOOL, 'Whether passed.'),
                'grade' => new external_value(PARAM_RAW, 'Normalised grade or null.'),
                'mustpass' => new external_value(PARAM_BOOL, 'Whether explicitly required to pass.'),
                'unlocked' => new external_value(
                    PARAM_BOOL,
                    'Whether learner access is available; always true for included courses.'
                ),
            ])),
            'passpolicy' => new external_value(PARAM_ALPHANUMEXT, 'Completion policy.'),
            'passgrade' => new external_value(PARAM_FLOAT, 'Required pass grade.'),
        ]);
    }
}
