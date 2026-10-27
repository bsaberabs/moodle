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
 * External API for learner subscription access details.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\localisation;
use moodle_url;
use xmldb_table;

/**
 * Returns the courses and products granted by a learner subscription.
 */
class get_subscription_access extends external_api {
    /** @var array Active subscription statuses. */
    private const ACTIVE_STATUSES = ['active', 'trial', 'grace'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Subscription ID.', VALUE_DEFAULT, 0),
            'planid' => new external_value(PARAM_INT, 'Plan ID.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Subscription ID.
     * @param int $planid Plan ID.
     * @return array
     */
    public static function execute(int $id = 0, int $planid = 0): array {
        global $CFG, $DB, $USER;

        ['id' => $id, 'planid' => $planid] = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'planid' => $planid,
        ]);

        require_login();
        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        if (!self::subscription_available()) {
            return self::empty_response(false, get_string('subscriptionsunavailable_desc', 'local_moderncommerce'));
        }

        $subscription = self::find_subscription((int)$USER->id, $id, $planid, $context);
        if (!$subscription) {
            return self::empty_response(true, get_string('nosubscriptiondesc', 'local_moderncommerce'));
        }

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid]);
        if (!$plan) {
            return self::empty_response(true, get_string('unknownplan', 'local_moderncommerce'));
        }

        $access = self::access_data((int)$plan->id);

        return [
            'success' => true,
            'available' => true,
            'hassubscription' => true,
            'message' => '',
            'subscription' => self::subscription_data($subscription),
            'plan' => [
                'id' => (int)$plan->id,
                'name' => format_string((string)$plan->name),
            ],
            'courses' => $access['courses'],
            'categories' => $access['categories'],
            'bundles' => $access['bundles'],
            'counts' => [
                'courses' => count($access['courses']),
                'categories' => count($access['categories']),
                'bundles' => count($access['bundles']),
                'totalcourses' => count($access['allcourseids']),
            ],
            'urls' => self::urls($subscription),
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
            'available' => new external_value(PARAM_BOOL, 'Whether subscription add-on is available.'),
            'hassubscription' => new external_value(PARAM_BOOL, 'Whether subscription exists.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'subscription' => self::subscription_structure(),
            'plan' => self::plan_structure(),
            'courses' => new external_multiple_structure(self::course_structure()),
            'categories' => new external_multiple_structure(self::group_structure()),
            'bundles' => new external_multiple_structure(self::group_structure()),
            'counts' => self::counts_structure(),
            'urls' => self::url_structure(),
        ]);
    }

    /**
     * Empty response.
     *
     * @param bool $available Whether the subscription add-on is available.
     * @param string $message Message.
     * @return array
     */
    private static function empty_response(bool $available, string $message): array {
        return [
            'success' => true,
            'available' => $available,
            'hassubscription' => false,
            'message' => $message,
            'subscription' => [
                'id' => 0,
                'planid' => 0,
                'status' => '',
                'statuslabel' => '',
                'statusclass' => 'neutral',
            ],
            'plan' => [
                'id' => 0,
                'name' => '',
            ],
            'courses' => [],
            'categories' => [],
            'bundles' => [],
            'counts' => [
                'courses' => 0,
                'categories' => 0,
                'bundles' => 0,
                'totalcourses' => 0,
            ],
            'urls' => self::urls(null),
        ];
    }

    /**
     * Find a subscription with ownership checks.
     *
     * @param int $userid User ID.
     * @param int $subscriptionid Subscription ID.
     * @param int $planid Plan ID.
     * @param context_system $context System context.
     * @return \stdClass|false
     */
    private static function find_subscription(int $userid, int $subscriptionid, int $planid, context_system $context) {
        global $DB;

        if ($subscriptionid > 0) {
            $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
            if ($subscription && (int)$subscription->userid !== $userid) {
                require_capability('local/moderncommerce:viewsubscribers', $context);
            }
            return $subscription;
        }

        if ($planid > 0) {
            return $DB->get_record('local_moderncommerce_user_subscriptions', [
                'userid' => $userid,
                'planid' => $planid,
            ]);
        }

        [$statussql, $params] = $DB->get_in_or_equal(self::ACTIVE_STATUSES, SQL_PARAMS_NAMED, 'substatus');
        $params['userid'] = $userid;

        return $DB->get_record_sql(
            "SELECT *
               FROM {local_moderncommerce_user_subscriptions}
              WHERE userid = :userid
                AND status {$statussql}
           ORDER BY timecreated DESC",
            $params,
            IGNORE_MULTIPLE
        );
    }

    /**
     * Resolve plan access rules into renderable groups.
     *
     * @param int $planid Plan ID.
     * @return array
     */
    private static function access_data(int $planid): array {
        global $DB;

        $access = [
            'courses' => [],
            'categories' => [],
            'bundles' => [],
            'allcourseids' => [],
        ];
        if (!self::table_exists('local_moderncommerce_subscription_access_rules')) {
            return $access;
        }

        $directcourseids = [];
        foreach ($DB->get_records('local_moderncommerce_subscription_access_rules', ['planid' => $planid]) as $rule) {
            $type = strtolower((string)$rule->access_type);
            $targetid = (int)$rule->target_id;

            if ($targetid <= 0) {
                continue;
            }

            if ($type === 'course') {
                $directcourseids[$targetid] = $targetid;
                $access['allcourseids'][$targetid] = $targetid;
                continue;
            }

            if ($type === 'category') {
                $group = self::category_group($targetid);
                if ($group['id'] > 0) {
                    foreach ($group['courses'] as $course) {
                        $access['allcourseids'][(int)$course['id']] = (int)$course['id'];
                    }
                    $access['categories'][$targetid] = $group;
                }
                continue;
            }

            if (in_array($type, ['bundle', 'program', 'product'], true)) {
                $group = self::product_group($targetid);
                if ($group['id'] > 0) {
                    if ($group['producttype'] === 'course') {
                        foreach ($group['courses'] as $course) {
                            $directcourseids[(int)$course['id']] = (int)$course['id'];
                            $access['allcourseids'][(int)$course['id']] = (int)$course['id'];
                        }
                    } else {
                        foreach ($group['courses'] as $course) {
                            $access['allcourseids'][(int)$course['id']] = (int)$course['id'];
                        }
                        $access['bundles'][$targetid] = $group;
                    }
                }
            }
        }

        $access['courses'] = self::courses_from_ids($directcourseids);
        $access['categories'] = array_values($access['categories']);
        $access['bundles'] = array_values($access['bundles']);

        return $access;
    }

    /**
     * Build a course category access group.
     *
     * @param int $categoryid Category ID.
     * @return array
     */
    private static function category_group(int $categoryid): array {
        global $DB;

        $category = $DB->get_record('course_categories', ['id' => $categoryid], 'id, name');
        if (!$category) {
            return self::empty_group();
        }

        $courses = [];
        $records = $DB->get_records(
            'course',
            ['category' => $categoryid, 'visible' => 1],
            'fullname ASC',
            'id, fullname, shortname, summary, category'
        );
        foreach ($records as $course) {
            $courses[] = self::course_data($course);
        }

        return [
            'id' => (int)$category->id,
            'name' => format_string((string)$category->name),
            'producttype' => 'category',
            'typelabel' => get_string('category', 'local_moderncommerce'),
            'coursecount' => count($courses),
            'courses' => $courses,
        ];
    }

    /**
     * Build a product/bundle access group.
     *
     * @param int $productid Product ID.
     * @return array
     */
    private static function product_group(int $productid): array {
        global $DB;

        if (!self::table_exists('local_moderncommerce_products')) {
            return self::empty_group();
        }

        $product = $DB->get_record(
            'local_moderncommerce_products',
            ['id' => $productid],
            'id, name, producttype'
        );
        if (!$product) {
            return self::empty_group();
        }

        $courses = self::product_courses($productid);
        $producttype = strtolower((string)$product->producttype);

        return [
            'id' => (int)$product->id,
            'name' => format_string((string)$product->name),
            'producttype' => $producttype,
            'typelabel' => self::product_type_label($producttype),
            'coursecount' => count($courses),
            'courses' => $courses,
        ];
    }

    /**
     * Load product courses.
     *
     * @param int $productid Product ID.
     * @return array
     */
    private static function product_courses(int $productid): array {
        global $DB;

        if (!self::table_exists('local_moderncommerce_product_courses')) {
            return [];
        }

        $sql = "SELECT c.id, c.fullname, c.shortname, c.summary, c.category
                  FROM {local_moderncommerce_product_courses} pc
                  JOIN {course} c ON c.id = pc.courseid
                 WHERE pc.productid = :productid
                   AND pc.relationtype = :relationtype
                   AND c.visible = 1
              ORDER BY pc.sortorder ASC, c.fullname ASC";
        $records = $DB->get_records_sql($sql, [
            'productid' => $productid,
            'relationtype' => 'included',
        ]);

        $courses = [];
        foreach ($records as $course) {
            $courses[] = self::course_data($course);
        }

        return $courses;
    }

    /**
     * Load direct courses.
     *
     * @param array $courseids Course IDs.
     * @return array
     */
    private static function courses_from_ids(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if (empty($courseids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $records = $DB->get_records_select(
            'course',
            "id {$insql} AND visible = 1",
            $params,
            'fullname ASC',
            'id, fullname, shortname, summary, category'
        );

        $courses = [];
        foreach ($records as $course) {
            $courses[] = self::course_data($course);
        }

        return $courses;
    }

    /**
     * Course data.
     *
     * @param \stdClass $course Course record.
     * @return array
     */
    private static function course_data(\stdClass $course): array {
        global $DB, $USER;

        $category = $DB->get_record('course_categories', ['id' => $course->category], 'id, name');
        $context = \context_course::instance((int)$course->id, IGNORE_MISSING);
        $summary = shorten_text(trim(strip_tags((string)$course->summary)), 180);
        $imageurl = function_exists('local_moderncommerce_get_course_image_url')
            ? local_moderncommerce_get_course_image_url((int)$course->id)
            : '';

        return [
            'id' => (int)$course->id,
            'name' => format_string((string)$course->fullname),
            'shortname' => format_string((string)$course->shortname),
            'summary' => $summary,
            'categoryid' => (int)$course->category,
            'categoryname' => $category ? format_string((string)$category->name) : '',
            'imageurl' => $imageurl,
            'hasimage' => $imageurl !== '',
            'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'enrollurl' => (new moodle_url('/enrol/index.php', ['id' => $course->id]))->out(false),
            'isenrolled' => $context ? is_enrolled($context, $USER->id, '', true) : false,
        ];
    }

    /**
     * Subscription data.
     *
     * @param \stdClass $subscription Subscription record.
     * @return array
     */
    private static function subscription_data(\stdClass $subscription): array {
        $status = (string)$subscription->status;

        return [
            'id' => (int)$subscription->id,
            'planid' => (int)$subscription->planid,
            'status' => $status,
            'statuslabel' => localisation::status_label($status),
            'statusclass' => self::status_class($status),
        ];
    }

    /**
     * URLs.
     *
     * @param \stdClass|null $subscription Subscription record.
     * @return array
     */
    private static function urls(?\stdClass $subscription): array {
        $id = $subscription ? (int)$subscription->id : 0;

        return [
            'plan' => (new moodle_url('/local/moderncommerce/learner/subscription.php', ['id' => $id]))->out(false),
            'plans' => (new moodle_url('/local/moderncommerce/subscribe.php'))->out(false),
            'catalog' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
            'courses' => (new moodle_url('/local/moderncommerce/learner/courses.php'))->out(false),
        ];
    }

    /**
     * Status class.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_class(string $status): string {
        if ($status === 'active') {
            return 'success';
        }
        if (in_array($status, ['trial', 'grace', 'pending'], true)) {
            return 'warning';
        }
        if (in_array($status, ['cancelled', 'expired', 'suspended'], true)) {
            return 'danger';
        }

        return 'neutral';
    }

    /**
     * Product type label.
     *
     * @param string $producttype Product type.
     * @return string
     */
    private static function product_type_label(string $producttype): string {
        return get_string_manager()->string_exists($producttype, 'local_moderncommerce')
            ? get_string($producttype, 'local_moderncommerce')
            : ucfirst($producttype);
    }

    /**
     * Empty group.
     *
     * @return array
     */
    private static function empty_group(): array {
        return [
            'id' => 0,
            'name' => '',
            'producttype' => '',
            'typelabel' => '',
            'coursecount' => 0,
            'courses' => [],
        ];
    }

    /**
     * Optional subscription string with fallback.
     *
     * @param string $key String key.
     * @param string $fallback Fallback text.
     * @return string
     */
    private static function subscription_string(string $key, string $fallback): string {
        return get_string_manager()->string_exists($key, 'local_moderncommerce')
            ? get_string($key, 'local_moderncommerce')
            : $fallback;
    }

    /**
     * Check whether subscription integration is available.
     *
     * @return bool
     */
    private static function subscription_available(): bool {
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('local_moderncommerce');

        return $plugininfo !== null
            && $plugininfo->is_installed_and_upgraded()
            && self::table_exists('local_moderncommerce_user_subscriptions')
            && self::table_exists('local_moderncommerce_subscription_plans');
    }

    /**
     * Check whether table exists.
     *
     * @param string $table Table name without prefix.
     * @return bool
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }

    /**
     * Subscription structure.
     *
     * @return external_single_structure
     */
    private static function subscription_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Subscription ID.'),
            'planid' => new external_value(PARAM_INT, 'Plan ID.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Status class.'),
        ]);
    }

    /**
     * Plan structure.
     *
     * @return external_single_structure
     */
    private static function plan_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Plan ID.'),
            'name' => new external_value(PARAM_TEXT, 'Plan name.'),
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
            'summary' => new external_value(PARAM_RAW, 'Course summary.'),
            'categoryid' => new external_value(PARAM_INT, 'Category ID.'),
            'categoryname' => new external_value(PARAM_TEXT, 'Category name.'),
            'imageurl' => new external_value(PARAM_RAW, 'Image URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether image exists.'),
            'courseurl' => new external_value(PARAM_RAW, 'Course URL.'),
            'enrollurl' => new external_value(PARAM_RAW, 'Enrolment URL.'),
            'isenrolled' => new external_value(PARAM_BOOL, 'Whether user is enrolled.'),
        ]);
    }

    /**
     * Group structure.
     *
     * @return external_single_structure
     */
    private static function group_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Group ID.'),
            'name' => new external_value(PARAM_TEXT, 'Group name.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Type label.'),
            'coursecount' => new external_value(PARAM_INT, 'Course count.'),
            'courses' => new external_multiple_structure(self::course_structure()),
        ]);
    }

    /**
     * Counts structure.
     *
     * @return external_single_structure
     */
    private static function counts_structure(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_value(PARAM_INT, 'Direct course count.'),
            'categories' => new external_value(PARAM_INT, 'Category count.'),
            'bundles' => new external_value(PARAM_INT, 'Bundle count.'),
            'totalcourses' => new external_value(PARAM_INT, 'Total unique course count.'),
        ]);
    }

    /**
     * URL structure.
     *
     * @return external_single_structure
     */
    private static function url_structure(): external_single_structure {
        return new external_single_structure([
            'plan' => new external_value(PARAM_RAW, 'Subscription plan URL.'),
            'plans' => new external_value(PARAM_RAW, 'Plans URL.'),
            'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
            'courses' => new external_value(PARAM_RAW, 'Courses URL.'),
        ]);
    }
}
