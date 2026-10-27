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
 * External API for public course details.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\courses;

use context_course;
use context_system;
use core_course_category;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\services\access_service;
use local_moderncommerce\services\meta_service;
use local_moderncommerce\services\pricing_service;
use moodle_url;
use xmldb_table;

/**
 * Return one public course details dataset for React storefront rendering.
 */
class get_course_details extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Moodle course ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Moodle course ID.
     * @return array Details response.
     */
    public static function execute(int $id): array {
        global $CFG, $DB, $USER;

        ['id' => $id] = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

        if ($id <= 0) {
            return self::error_response(get_string('invalidrecord', 'error', 'course'));
        }

        $course = $DB->get_record('course', ['id' => $id], '*', IGNORE_MISSING);
        if (!$course || empty($course->visible)) {
            return self::error_response(get_string('coursenotavailable', 'local_moderncommerce'));
        }

        self::validate_public_details_context(context_system::instance());
        $context = context_course::instance((int)$course->id);

        $pricing = pricing_service::get_course_pricing((int)$course->id);
        $haspurchaseoption = $pricing && !empty($pricing->enabled);
        $isinsubscriptionplan = self::is_in_subscription_plan((int)$course->id, (int)$course->category);
        $isavailable = $haspurchaseoption || $isinsubscriptionplan;
        $userid = isloggedin() && !isguestuser() ? (int)$USER->id : 0;
        $accessurl = $userid > 0 ? access_service::resolve_course_access_url($userid, (int)$course->id) : null;
        $productid = self::resolve_product_id((int)$course->id);

        if (!$isavailable) {
            return self::error_response(get_string('coursenotavailable', 'local_moderncommerce'));
        }

        return [
            'success' => true,
            'message' => '',
            'course' => self::course_data($course, $context),
            'price' => self::price_data($pricing),
            'meta' => self::meta_data((int)$course->id),
            'state' => [
                'isloggedin' => $userid > 0,
                'hasaccess' => $accessurl !== null,
                'isavailable' => true,
                'canpurchase' => $haspurchaseoption,
                'isinsubscriptionplan' => $isinsubscriptionplan,
                'productid' => $productid,
                'inwishlist' => self::is_in_wishlist($productid, $userid),
            ],
            'overview' => self::overview_data($course, $context),
            'objectives' => self::objectives_data((int)$course->id),
            'outline' => self::outline_data($course),
            'urls' => self::urls_data((int)$course->id, $accessurl),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether details loaded successfully.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'course' => self::course_structure(),
            'price' => self::price_structure(),
            'meta' => self::meta_structure(),
            'state' => self::state_structure(),
            'overview' => self::overview_structure(),
            'objectives' => new external_multiple_structure(self::objective_structure()),
            'outline' => new external_multiple_structure(self::outline_structure()),
            'urls' => self::urls_structure(),
        ]);
    }

    /**
     * Validate details access without forcing anonymous visitors to log in.
     *
     * @param context_system $context System context.
     */
    private static function validate_public_details_context(context_system $context): void {
        global $CFG, $PAGE;

        if (isloggedin() && !isguestuser()) {
            self::validate_context($context);
            require_capability('local/moderncommerce:viewcatalog', $context);
            return;
        }

        $PAGE->set_context($context);
        $guestuserid = !empty($CFG->siteguest) ? (int)$CFG->siteguest : 0;
        require_capability('local/moderncommerce:viewcatalog', $context, $guestuserid);
    }

    /**
     * Course structure.
     *
     * @return external_single_structure
     */
    private static function course_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Course full name.'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name.'),
            'summary' => new external_value(PARAM_RAW, 'Formatted course summary.'),
            'imageurl' => new external_value(PARAM_RAW, 'Course image URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether an image URL exists.'),
            'categoryid' => new external_value(PARAM_INT, 'Course category ID.'),
            'categoryname' => new external_value(PARAM_TEXT, 'Course category name.'),
            'courseurl' => new external_value(PARAM_RAW, 'Moodle course view URL.'),
        ]);
    }

    /**
     * Price structure.
     *
     * @return external_single_structure
     */
    private static function price_structure(): external_single_structure {
        return new external_single_structure([
            'hasprice' => new external_value(PARAM_BOOL, 'Whether pricing exists.'),
            'isfree' => new external_value(PARAM_BOOL, 'Whether current price is free.'),
            'hassale' => new external_value(PARAM_BOOL, 'Whether sale pricing applies.'),
            'current' => new external_value(PARAM_TEXT, 'Current display price.'),
            'original' => new external_value(PARAM_TEXT, 'Original display price.'),
            'discountpercentage' => new external_value(PARAM_INT, 'Sale discount percentage.'),
            'rawcurrent' => new external_value(PARAM_FLOAT, 'Current raw price.'),
            'raworiginal' => new external_value(PARAM_FLOAT, 'Original raw price.'),
        ]);
    }

    /**
     * Meta structure.
     *
     * @return external_single_structure
     */
    private static function meta_structure(): external_single_structure {
        return new external_single_structure([
            'duration' => new external_value(PARAM_TEXT, 'Duration label.'),
            'hasduration' => new external_value(PARAM_BOOL, 'Whether duration exists.'),
            'skilllevel' => new external_value(PARAM_TEXT, 'Skill level label.'),
            'hasskilllevel' => new external_value(PARAM_BOOL, 'Whether skill level exists.'),
            'language' => new external_value(PARAM_TEXT, 'Language label.'),
            'haslanguage' => new external_value(PARAM_BOOL, 'Whether language exists.'),
            'quizzescount' => new external_value(PARAM_INT, 'Quiz count.'),
            'certificateenabled' => new external_value(PARAM_BOOL, 'Whether certificate is enabled.'),
            'featured' => new external_value(PARAM_BOOL, 'Whether course is featured.'),
            'bestseller' => new external_value(PARAM_BOOL, 'Whether course is bestseller.'),
            'trending' => new external_value(PARAM_BOOL, 'Whether course is trending.'),
        ]);
    }

    /**
     * State structure.
     *
     * @return external_single_structure
     */
    private static function state_structure(): external_single_structure {
        return new external_single_structure([
            'isloggedin' => new external_value(PARAM_BOOL, 'Whether the visitor is logged in.'),
            'hasaccess' => new external_value(PARAM_BOOL, 'Whether the visitor has course access.'),
            'isavailable' => new external_value(PARAM_BOOL, 'Whether the course is available.'),
            'canpurchase' => new external_value(PARAM_BOOL, 'Whether the course can be purchased directly.'),
            'isinsubscriptionplan' => new external_value(PARAM_BOOL, 'Whether a plan can grant this course.'),
            'productid' => new external_value(PARAM_INT, 'Product backing this course (0 when none).'),
            'inwishlist' => new external_value(PARAM_BOOL, 'Whether the current learner has saved this course.'),
        ]);
    }

    /**
     * Resolve the standalone product that sells this course.
     *
     * A course row can be linked to several products - its own course product AND every
     * bundle that includes it - so the join MUST filter on producttype. Without that
     * filter this returns whichever row comes first and the learner ends up saving a
     * bundle to their wishlist from a course page.
     *
     * @param int $courseid Course ID.
     * @return int Product ID, or 0 when the course is not sold on its own.
     */
    private static function resolve_product_id(int $courseid): int {
        global $DB;

        if ($courseid <= 0 || !self::table_exists('local_moderncommerce_product_courses')) {
            return 0;
        }

        $sql = "SELECT pc.productid
                  FROM {local_moderncommerce_product_courses} pc
                  JOIN {local_moderncommerce_products} p ON p.id = pc.productid
                 WHERE pc.courseid = :courseid
                   AND pc.relationtype = :relationtype
                   AND p.producttype = :producttype
                   AND p.status = :status
              ORDER BY pc.productid ASC";

        $productid = $DB->get_field_sql($sql, [
            'courseid' => $courseid,
            'relationtype' => 'included',
            'producttype' => 'course',
            'status' => 'active',
        ], IGNORE_MULTIPLE);

        return $productid ? (int)$productid : 0;
    }

    /**
     * Whether the given learner has saved this product.
     *
     * @param int $productid Product ID.
     * @param int $userid User ID (0 for anonymous visitors).
     * @return bool
     */
    private static function is_in_wishlist(int $productid, int $userid): bool {
        global $DB;

        if ($productid <= 0 || $userid <= 0 || !self::table_exists('local_moderncommerce_wishlist')) {
            return false;
        }

        return $DB->record_exists('local_moderncommerce_wishlist', [
            'userid' => $userid,
            'productid' => $productid,
        ]);
    }

    /**
     * Overview structure.
     *
     * @return external_single_structure
     */
    private static function overview_structure(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Formatted overview HTML.'),
            'hasoverview' => new external_value(PARAM_BOOL, 'Whether overview exists.'),
        ]);
    }

    /**
     * Objective structure.
     *
     * @return external_single_structure
     */
    private static function objective_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Objective row ID.'),
            'text' => new external_value(PARAM_TEXT, 'Objective text.'),
        ]);
    }

    /**
     * Outline structure.
     *
     * @return external_single_structure
     */
    private static function outline_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Outline row or section ID.'),
            'title' => new external_value(PARAM_TEXT, 'Outline title.'),
            'estimatedtime' => new external_value(PARAM_TEXT, 'Estimated time label.'),
            'hasestimatedtime' => new external_value(PARAM_BOOL, 'Whether estimated time exists.'),
            'activitycount' => new external_value(PARAM_INT, 'Activity count.'),
            'hasactivitycount' => new external_value(PARAM_BOOL, 'Whether activity count exists.'),
            'imageurl' => new external_value(PARAM_RAW, 'Outline thumbnail URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether outline thumbnail exists.'),
            'icon' => new external_value(PARAM_ALPHANUMEXT, 'Fallback Bootstrap icon class.'),
        ]);
    }

    /**
     * URLs structure.
     *
     * @return external_single_structure
     */
    private static function urls_structure(): external_single_structure {
        return new external_single_structure([
            'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
            'cart' => new external_value(PARAM_RAW, 'Cart URL.'),
            'checkout' => new external_value(PARAM_RAW, 'Checkout URL.'),
            'login' => new external_value(PARAM_RAW, 'Login URL.'),
            'register' => new external_value(PARAM_RAW, 'Registration URL.'),
            'launch' => new external_value(PARAM_RAW, 'Course launch URL when already entitled.'),
        ]);
    }

    /**
     * Build a complete error response.
     *
     * @param string $message Error message.
     * @return array
     */
    private static function error_response(string $message): array {
        return [
            'success' => false,
            'message' => $message,
            'course' => [
                'id' => 0,
                'fullname' => '',
                'shortname' => '',
                'summary' => '',
                'imageurl' => '',
                'hasimage' => false,
                'categoryid' => 0,
                'categoryname' => '',
                'courseurl' => '',
            ],
            'price' => self::price_data(null),
            'meta' => self::meta_data(0),
            'state' => [
                'isloggedin' => isloggedin() && !isguestuser(),
                'hasaccess' => false,
                'isavailable' => false,
                'canpurchase' => false,
                'isinsubscriptionplan' => false,
            ],
            'overview' => [
                'html' => '',
                'hasoverview' => false,
            ],
            'objectives' => [],
            'outline' => [],
            'urls' => self::urls_data(0, null),
        ];
    }

    /**
     * Build course data.
     *
     * @param \stdClass $course Course record.
     * @param context_course $context Course context.
     * @return array
     */
    private static function course_data(\stdClass $course, context_course $context): array {
        $imageurl = function_exists('local_moderncommerce_get_course_image_url')
            ? local_moderncommerce_get_course_image_url((int)$course->id)
            : '';

        return [
            'id' => (int)$course->id,
            'fullname' => format_string($course->fullname, true, ['context' => $context]),
            'shortname' => format_string($course->shortname, true, ['context' => $context]),
            'summary' => !empty($course->summary)
                ? format_text($course->summary, $course->summaryformat, ['context' => $context])
                : '',
            'imageurl' => $imageurl,
            'hasimage' => $imageurl !== '',
            'categoryid' => (int)$course->category,
            'categoryname' => self::category_name((int)$course->category),
            'courseurl' => access_service::course_view_url((int)$course->id)->out(false),
        ];
    }

    /**
     * Build price data.
     *
     * @param \stdClass|null $pricing Pricing data.
     * @return array
     */
    private static function price_data(?\stdClass $pricing): array {
        if (!$pricing || empty($pricing->enabled)) {
            return [
                'hasprice' => false,
                'isfree' => false,
                'hassale' => false,
                'current' => '',
                'original' => '',
                'discountpercentage' => 0,
                'rawcurrent' => 0.0,
                'raworiginal' => 0.0,
            ];
        }

        $current = (float)$pricing->final_price;
        $original = (float)$pricing->price;

        return [
            'hasprice' => true,
            'isfree' => !empty($pricing->is_free),
            'hassale' => !empty($pricing->has_sale),
            'current' => !empty($pricing->is_free)
                ? get_string('free', 'local_moderncommerce')
                : pricing_service::format_price($current),
            'original' => !empty($pricing->has_sale) ? pricing_service::format_price($original) : '',
            'discountpercentage' => (int)($pricing->discount_percentage ?? 0),
            'rawcurrent' => $current,
            'raworiginal' => $original,
        ];
    }

    /**
     * Build meta data.
     *
     * @param int $courseid Course ID.
     * @return array
     */
    private static function meta_data(int $courseid): array {
        $meta = $courseid > 0 ? meta_service::get_course_meta($courseid) : null;
        $duration = '';
        if ($meta && ((int)$meta->duration_hours > 0 || (int)$meta->duration_minutes > 0)) {
            $duration = meta_service::format_duration((int)$meta->duration_hours, (int)$meta->duration_minutes);
        }

        $skilllevel = $meta && !empty($meta->skill_level) ? (string)$meta->skill_level : '';
        $language = $meta && !empty($meta->language) ? (string)$meta->language : '';

        return [
            'duration' => $duration,
            'hasduration' => $duration !== '',
            'skilllevel' => $skilllevel,
            'hasskilllevel' => $skilllevel !== '',
            'language' => $language,
            'haslanguage' => $language !== '',
            'quizzescount' => $courseid > 0 ? meta_service::get_quizzes_count($courseid) : 0,
            'certificateenabled' => $meta ? !empty($meta->cert_enabled) : false,
            'featured' => $meta ? !empty($meta->meta_featured_course) : false,
            'bestseller' => $meta ? !empty($meta->meta_bestseller_badge) : false,
            'trending' => $meta ? !empty($meta->meta_trending_badge) : false,
        ];
    }

    /**
     * Build overview data.
     *
     * @param \stdClass $course Course record.
     * @param context_course $context Course context.
     * @return array
     */
    private static function overview_data(\stdClass $course, context_course $context): array {
        $meta = meta_service::get_course_meta((int)$course->id);
        $overviewtext = '';
        $format = (int)$course->summaryformat;

        if ($meta && empty($meta->overview_autogen) && !empty($meta->overview_text)) {
            $overviewtext = (string)$meta->overview_text;
            $format = FORMAT_HTML;
        } else if (!empty($course->summary)) {
            $overviewtext = (string)$course->summary;
        }

        $html = $overviewtext !== '' ? format_text($overviewtext, $format, ['context' => $context]) : '';

        return [
            'html' => $html,
            'hasoverview' => trim(strip_tags($html)) !== '',
        ];
    }

    /**
     * Build objectives data.
     *
     * @param int $courseid Course ID.
     * @return array
     */
    private static function objectives_data(int $courseid): array {
        global $DB;

        if ($courseid <= 0 || !self::table_exists('local_moderncommerce_course_objectives')) {
            return [];
        }

        $records = $DB->get_records(
            'local_moderncommerce_course_objectives',
            ['courseid' => $courseid],
            'sortorder ASC, id ASC',
            'id, objective'
        );

        $objectives = [];
        foreach ($records as $record) {
            $text = trim(format_string($record->objective));
            if ($text === '') {
                continue;
            }

            $objectives[] = [
                'id' => (int)$record->id,
                'text' => $text,
            ];
        }

        return $objectives;
    }

    /**
     * Build outline data.
     *
     * @param \stdClass $course Course record.
     * @return array
     */
    private static function outline_data(\stdClass $course): array {
        global $DB;

        $courseid = (int)$course->id;
        $meta = meta_service::get_course_meta($courseid);
        $usecustomoutline = $meta && empty($meta->outline_autogen);

        if ($usecustomoutline && self::table_exists('local_moderncommerce_course_outline')) {
            $records = $DB->get_records(
                'local_moderncommerce_course_outline',
                ['courseid' => $courseid],
                'sortorder ASC, id ASC',
                'id, sectiontitle, estimatedtime'
            );
            $outline = [];
            foreach ($records as $record) {
                $title = trim(format_string($record->sectiontitle));
                if ($title === '') {
                    continue;
                }

                $estimatedtime = trim(format_string((string)$record->estimatedtime));
                $outline[] = [
                    'id' => (int)$record->id,
                    'title' => $title,
                    'estimatedtime' => $estimatedtime,
                    'hasestimatedtime' => $estimatedtime !== '',
                    'activitycount' => 0,
                    'hasactivitycount' => false,
                ] + self::outline_visual_data((int)$record->id, 'bi-journal-text');
            }

            if ($outline) {
                return $outline;
            }
        }

        return self::generated_outline_data($course);
    }

    /**
     * Generate outline rows from Moodle course sections.
     *
     * @param \stdClass $course Course record.
     * @return array
     */
    private static function generated_outline_data(\stdClass $course): array {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
        $dbsections = $DB->get_records(
            'course_sections',
            ['course' => (int)$course->id],
            'section',
            'id, section, component, name'
        );
        $outline = [];
        $sectioncount = 0;

        foreach ($sections as $section) {
            if ((int)$section->section === 0 || empty($section->uservisible)) {
                continue;
            }

            $dbsection = $dbsections[$section->id] ?? null;
            if ($dbsection && $dbsection->component === 'mod_subsection') {
                continue;
            }

            $sectioncount++;
            $sectionname = !empty($section->name)
                ? format_string($section->name)
                : get_string('section') . ' ' . $sectioncount;
            $activitycount = !empty($section->sequence)
                ? count(array_filter(explode(',', $section->sequence)))
                : 0;

            $outline[] = [
                'id' => (int)$section->id,
                'title' => $sectionname,
                'estimatedtime' => '',
                'hasestimatedtime' => false,
                'activitycount' => $activitycount,
                'hasactivitycount' => $activitycount > 0,
            ] + self::outline_visual_data((int)$section->id, 'bi-collection-play');
        }

        return $outline;
    }

    /**
     * Build visual metadata for a public outline row.
     *
     * @param int $seed Stable thumbnail seed.
     * @param string $icon Fallback Bootstrap icon class.
     * @return array
     */
    private static function outline_visual_data(int $seed, string $icon): array {
        $imageurl = function_exists('local_moderncommerce_get_placeholder_image_url')
            ? local_moderncommerce_get_placeholder_image_url($seed)
            : '';

        return [
            'imageurl' => $imageurl,
            'hasimage' => $imageurl !== '',
            'icon' => $icon,
        ];
    }

    /**
     * Build URL data.
     *
     * @param int $courseid Course ID.
     * @param moodle_url|null $launchurl Course launch URL.
     * @return array
     */
    private static function urls_data(int $courseid, ?moodle_url $launchurl): array {
        $detailsurl = $courseid > 0
            ? new moodle_url('/local/moderncommerce/course_details.php', ['id' => $courseid])
            : new moodle_url('/local/moderncommerce/index.php');

        return [
            'catalog' => (new moodle_url('/local/moderncommerce/index.php'))->out(false),
            'cart' => (new moodle_url('/local/moderncommerce/cart.php'))->out(false),
            'checkout' => $courseid > 0
                ? (new moodle_url('/local/moderncommerce/checkout.php', ['courseid' => $courseid]))->out(false)
                : '',
            'login' => (new moodle_url('/login/index.php', ['wantsurl' => $detailsurl->out(false)]))->out(false),
            'register' => (is_enabled_auth('ccp')
                ? new moodle_url('/auth/ccp/signup.php')
                : new moodle_url('/login/signup.php'))->out(false),
            'launch' => $launchurl ? $launchurl->out(false) : '',
        ];
    }

    /**
     * Determine if any subscription plan can include the course.
     *
     * @param int $courseid Course ID.
     * @param int $categoryid Category ID.
     * @return bool
     */
    private static function is_in_subscription_plan(int $courseid, int $categoryid): bool {
        global $DB;

        if ($courseid <= 0 || !self::table_exists('local_moderncommerce_subscription_access_rules')) {
            return false;
        }

        $hasaccessrule = $DB->record_exists('local_moderncommerce_subscription_access_rules', [
            'access_type' => 'course',
            'target_id' => $courseid,
        ]);
        if ($hasaccessrule) {
            return true;
        }

        if (
            $categoryid > 0 && $DB->record_exists('local_moderncommerce_subscription_access_rules', [
            'access_type' => 'category',
            'target_id' => $categoryid,
            ])
        ) {
            return true;
        }

        if (!self::table_exists('local_moderncommerce_product_courses')) {
            return false;
        }

        $sql = "SELECT 1
                  FROM {local_moderncommerce_subscription_access_rules} par
                  JOIN {local_moderncommerce_product_courses} pc ON pc.productid = par.target_id
                 WHERE par.access_type IN ('bundle', 'program', 'product')
                   AND pc.courseid = :courseid
                   AND pc.relationtype = :relationtype";

        return $DB->record_exists_sql($sql, [
            'courseid' => $courseid,
            'relationtype' => 'included',
        ]);
    }

    /**
     * Get category name.
     *
     * @param int $categoryid Category ID.
     * @return string
     */
    private static function category_name(int $categoryid): string {
        if ($categoryid <= 0) {
            return '';
        }

        try {
            $category = core_course_category::get($categoryid, IGNORE_MISSING);
        } catch (\moodle_exception $e) {
            return '';
        }

        return $category ? format_string($category->name) : '';
    }

    /**
     * Check whether a table exists.
     *
     * @param string $table Table name without prefix.
     * @return bool
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }
}
