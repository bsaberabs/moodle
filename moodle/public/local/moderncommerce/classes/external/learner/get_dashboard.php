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
 * External API for the learner dashboard.
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
use local_moderncommerce\localisation;
use local_moderncommerce\services\access_service;
use local_moderncommerce\services\learner_invoice_service;
use local_moderncommerce\services\pricing_service;
use moodle_url;
use xmldb_table;

/**
 * Returns the logged-in learner account dashboard dataset for React.
 */
class get_dashboard extends external_api {
    /** @var array Product types that can appear in learner access. */
    private const PRODUCT_TYPES = ['course', 'bundle', 'program', 'subscription', 'plan', 'membership'];

    /** @var array Product types that should remain account-level cards. */
    private const ACCOUNT_PRODUCT_TYPES = ['bundle', 'program', 'subscription', 'plan'];

    /** @var array Order statuses that grant access. */
    private const PAID_ORDER_STATUSES = ['paid', 'completed'];

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
     * @return array Dashboard response.
     */
    public static function execute(): array {
        global $CFG, $USER;

        self::validate_parameters(self::execute_parameters(), []);
        require_login();

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $userid = (int)$USER->id;
        $products = self::get_access_products($userid);
        $productcourses = self::get_product_courses(array_keys($products));
        $courses = self::build_course_access($userid, $products, $productcourses);
        $productsdata = self::build_product_access($products, $productcourses);
        $recentorders = self::get_recent_orders($userid);
        $recentinvoices = learner_invoice_service::recent_for_user($userid);
        $stats = self::build_stats($userid, $courses, $productsdata);

        return [
            'success' => true,
            'message' => '',
            'user' => self::user_data(),
            'stats' => $stats,
            'access' => [
                'courses' => array_values($courses),
                'products' => array_values($productsdata),
                'bundles' => array_values(array_filter($productsdata, static function (array $product): bool {
                    return $product['producttype'] === 'bundle';
                })),
                'programs' => array_values(array_filter($productsdata, static function (array $product): bool {
                    return $product['producttype'] === 'program';
                })),
                'subscriptions' => array_values(array_filter($productsdata, static function (array $product): bool {
                    return $product['producttype'] === 'subscription';
                })),
                'plans' => array_values(array_filter($productsdata, static function (array $product): bool {
                    return $product['producttype'] === 'plan';
                })),
            ],
            'recentorders' => $recentorders,
            'recentinvoices' => $recentinvoices,
            'urls' => self::url_data(),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the dashboard loaded successfully.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'user' => self::user_structure(),
            'stats' => self::stats_structure(),
            'access' => self::access_structure(),
            'recentorders' => new external_multiple_structure(self::order_structure()),
            'recentinvoices' => new external_multiple_structure(self::invoice_structure()),
            'urls' => self::url_structure(),
        ]);
    }

    /**
     * Get all products this learner currently owns or can access.
     *
     * @param int $userid User ID.
     * @return array Product records keyed by product ID.
     */
    private static function get_access_products(int $userid): array {
        $products = [];

        foreach (self::get_entitlement_products($userid) as $product) {
            $product->accesssource = 'entitlement';
            $products[(int)$product->id] = $product;
        }

        foreach (self::get_paid_order_products($userid) as $product) {
            $productid = (int)$product->id;
            if (!isset($products[$productid])) {
                $product->accesssource = 'purchase';
                $products[$productid] = $product;
            }
        }

        uasort($products, static function (\stdClass $left, \stdClass $right): int {
            $typecompare = strcmp((string)$left->producttype, (string)$right->producttype);
            return $typecompare ?: strcasecmp((string)$left->name, (string)$right->name);
        });

        return $products;
    }

    /**
     * Get product records from active entitlement rows.
     *
     * @param int $userid User ID.
     * @return array Product records.
     */
    private static function get_entitlement_products(int $userid): array {
        global $DB;

        if (
            !self::table_exists('local_moderncommerce_entitlements')
                || !self::table_exists('local_moderncommerce_products')
        ) {
            return [];
        }

        [$typesql, $typeparams] = $DB->get_in_or_equal(self::PRODUCT_TYPES, SQL_PARAMS_NAMED, 'etype');
        $params = array_merge($typeparams, [
            'userid' => $userid,
            'status' => 'active',
            'nowstart' => time(),
            'nowend' => time(),
        ]);

        $sql = "SELECT DISTINCT p.id,
                       p.producttype,
                       p.name,
                       p.shortdescription,
                       p.description,
                       p.imageurl,
                       p.status,
                       p.visible,
                       p.timecreated
                  FROM {local_moderncommerce_entitlements} e
                  JOIN {local_moderncommerce_products} p ON p.id = e.productid
                 WHERE e.userid = :userid
                   AND e.productid IS NOT NULL
                   AND e.status = :status
                   AND LOWER(p.producttype) {$typesql}
                   AND (e.timestart IS NULL OR e.timestart = 0 OR e.timestart <= :nowstart)
                   AND (e.timeend IS NULL OR e.timeend = 0 OR e.timeend >= :nowend)";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get product records from paid/completed order items.
     *
     * @param int $userid User ID.
     * @return array Product records.
     */
    private static function get_paid_order_products(int $userid): array {
        global $DB;

        if (
            !self::table_exists('local_moderncommerce_orders')
                || !self::table_exists('local_moderncommerce_order_items')
                || !self::table_exists('local_moderncommerce_products')
        ) {
            return [];
        }

        [$typesql, $typeparams] = $DB->get_in_or_equal(self::PRODUCT_TYPES, SQL_PARAMS_NAMED, 'otype');
        [$statussql, $statusparams] = $DB->get_in_or_equal(self::PAID_ORDER_STATUSES, SQL_PARAMS_NAMED, 'ostatus');
        $params = array_merge($typeparams, $statusparams, [
            'userid' => $userid,
        ]);

        $sql = "SELECT DISTINCT p.id,
                       p.producttype,
                       p.name,
                       p.shortdescription,
                       p.description,
                       p.imageurl,
                       p.status,
                       p.visible,
                       p.timecreated
                  FROM {local_moderncommerce_orders} o
                  JOIN {local_moderncommerce_order_items} oi ON oi.orderid = o.id
                  JOIN {local_moderncommerce_products} p ON p.id = oi.productid
                 WHERE o.userid = :userid
                   AND o.status {$statussql}
                   AND oi.productid IS NOT NULL
                   AND LOWER(p.producttype) {$typesql}";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get included Moodle courses for product IDs.
     *
     * @param array $productids Product IDs.
     * @return array Course records grouped by product ID.
     */
    private static function get_product_courses(array $productids): array {
        global $DB;

        $productids = array_values(array_filter(array_map('intval', $productids)));
        if (empty($productids) || !self::table_exists('local_moderncommerce_product_courses')) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($productids, SQL_PARAMS_NAMED, 'productid');
        $params['relationtype'] = 'included';

        $sql = "SELECT pc.id AS mapid,
                       pc.productid,
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
                 WHERE pc.productid {$insql}
                   AND pc.relationtype = :relationtype
              ORDER BY pc.productid ASC, pc.sortorder ASC, c.fullname ASC";

        $rows = $DB->get_records_sql($sql, $params);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int)$row->productid][] = $row;
        }

        return $grouped;
    }

    /**
     * Build course access cards from enrolments, entitlements, and owned products.
     *
     * @param int $userid User ID.
     * @param array $products Product records keyed by product ID.
     * @param array $productcourses Product course records grouped by product ID.
     * @return array Course cards.
     */
    private static function build_course_access(int $userid, array $products, array $productcourses): array {
        $courseids = [];
        $sources = [];

        foreach (enrol_get_my_courses('id, fullname, shortname, summary, category, visible', 'fullname ASC') as $course) {
            $courseid = (int)$course->id;
            $courseids[$courseid] = $courseid;
            $sources[$courseid] = [
                'source' => 'enrolment',
                'sourcelabel' => get_string('active', 'local_moderncommerce'),
                'producttype' => '',
                'productname' => '',
            ];
        }

        foreach ($products as $product) {
            $productid = (int)$product->id;
            $producttype = self::normalise_product_type((string)$product->producttype);
            foreach ($productcourses[$productid] ?? [] as $course) {
                $courseid = (int)$course->courseid;
                $courseids[$courseid] = $courseid;

                if (empty($sources[$courseid]) || $producttype === 'course') {
                    $sources[$courseid] = [
                        'source' => $product->accesssource ?? 'purchase',
                        'sourcelabel' => self::product_type_label($producttype),
                        'producttype' => $producttype,
                        'productname' => format_string((string)$product->name),
                    ];
                }
            }
        }

        foreach (self::get_direct_course_access_ids($userid) as $courseid) {
            $courseids[$courseid] = $courseid;
            if (empty($sources[$courseid])) {
                $sources[$courseid] = [
                    'source' => 'purchase',
                    'sourcelabel' => get_string('course', 'local_moderncommerce'),
                    'producttype' => 'course',
                    'productname' => '',
                ];
            }
        }

        $courses = [];
        foreach ($courseids as $courseid) {
            if (!access_service::user_has_course_access($userid, $courseid)) {
                continue;
            }

            $course = self::normalise_course($userid, $courseid, $sources[$courseid] ?? []);
            if ($course) {
                $courses[$courseid] = $course;
            }
        }

        uasort($courses, static function (array $left, array $right): int {
            $accesscompare = $right['lastaccess'] <=> $left['lastaccess'];
            return $accesscompare ?: strcasecmp($left['name'], $right['name']);
        });

        return array_values($courses);
    }

    /**
     * Get direct course IDs from entitlements and legacy order item rows.
     *
     * @param int $userid User ID.
     * @return array Course IDs.
     */
    private static function get_direct_course_access_ids(int $userid): array {
        global $DB;

        $courseids = [];

        if (self::table_exists('local_moderncommerce_entitlements')) {
            $now = time();
            $rows = $DB->get_fieldset_select(
                'local_moderncommerce_entitlements',
                'courseid',
                "userid = :userid
                    AND courseid IS NOT NULL
                    AND courseid > 0
                    AND status = :status
                    AND (timestart IS NULL OR timestart = 0 OR timestart <= :nowstart)
                    AND (timeend IS NULL OR timeend = 0 OR timeend >= :nowend)",
                [
                    'userid' => $userid,
                    'status' => 'active',
                    'nowstart' => $now,
                    'nowend' => $now,
                ]
            );

            foreach ($rows as $courseid) {
                $courseids[(int)$courseid] = (int)$courseid;
            }
        }

        if (
            self::table_exists('local_moderncommerce_orders')
                && self::table_exists('local_moderncommerce_order_items')
        ) {
            [$statussql, $statusparams] = $DB->get_in_or_equal(
                self::PAID_ORDER_STATUSES,
                SQL_PARAMS_NAMED,
                'dstatus'
            );
            $params = array_merge($statusparams, ['userid' => $userid]);
            $rows = $DB->get_fieldset_sql(
                "SELECT DISTINCT oi.courseid
                   FROM {local_moderncommerce_orders} o
                   JOIN {local_moderncommerce_order_items} oi ON oi.orderid = o.id
                  WHERE o.userid = :userid
                    AND o.status {$statussql}
                    AND oi.courseid IS NOT NULL
                    AND oi.courseid > 0",
                $params
            );

            foreach ($rows as $courseid) {
                $courseids[(int)$courseid] = (int)$courseid;
            }
        }

        return array_values($courseids);
    }

    /**
     * Normalise a Moodle course into a dashboard card.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param array $source Access source metadata.
     * @return array|null Course card, or null if the course no longer exists.
     */
    private static function normalise_course(int $userid, int $courseid, array $source): ?array {
        global $DB;

        try {
            $course = get_course($courseid);
        } catch (\moodle_exception $exception) {
            debugging('Modern Commerce learner dashboard course lookup failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
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

        $lastaccess = (int)$DB->get_field('user_lastaccess', 'timeaccess', [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
        $categoryname = (string)$DB->get_field('course_categories', 'name', ['id' => $course->category]);
        $imageurl = \local_moderncommerce_get_course_image_url($courseid);

        return [
            'id' => $courseid,
            'name' => $coursecontext
                ? format_string($course->fullname, true, ['context' => $coursecontext])
                : format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'summary' => trim(strip_tags((string)$course->summary)),
            'categoryid' => (int)$course->category,
            // Escape => false: React escapes on render, so letting format_string() also
            // escape turns "A & B" into a literal "A &amp; B" on screen.
            'categoryname' => format_string($categoryname, true, ['escape' => false]),
            'imageurl' => (string)$imageurl,
            'hasimage' => $imageurl !== '',
            'progress' => max(0, min(100, $progress)),
            'progresslabel' => max(0, min(100, $progress)) . '%',
            'completed' => $completed,
            'status' => $status,
            'statuslabel' => $statuslabel,
            'courseurl' => access_service::course_view_url($courseid)->out(false),
            'modulecount' => self::get_course_module_count($course),
            'enrolleddate' => self::get_course_enrolled_date($userid, $courseid),
            'enrolleddatelabel' => self::get_course_enrolled_date_label($userid, $courseid),
            'lastaccess' => $lastaccess,
            // Date only, matching enrolleddatelabel: the two sit side by side in the course
            // card, and a clock time there is noise the learner cannot act on.
            'lastaccesslabel' => $lastaccess > 0
                ? userdate($lastaccess, get_string('strftimedateshort'))
                : get_string('never'),
            'source' => (string)($source['source'] ?? ''),
            'sourcelabel' => (string)($source['sourcelabel'] ?? ''),
            'producttype' => (string)($source['producttype'] ?? ''),
            'productname' => (string)($source['productname'] ?? ''),
        ];
    }

    /**
     * Count visible course modules for a course.
     *
     * @param \stdClass $course Course record.
     * @return int Module count.
     */
    private static function get_course_module_count(\stdClass $course): int {
        try {
            return count(get_fast_modinfo($course)->get_cms());
        } catch (\moodle_exception $exception) {
            debugging('Modern Commerce module count failed: ' . $exception->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    /**
     * Get the user's earliest native enrolment date for a course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return int Enrolment timestamp, or 0.
     */
    private static function get_course_enrolled_date(int $userid, int $courseid): int {
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
     * Get the user's enrolment date label for a course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return string Enrolment date label.
     */
    private static function get_course_enrolled_date_label(int $userid, int $courseid): string {
        $timecreated = self::get_course_enrolled_date($userid, $courseid);

        return $timecreated > 0 ? userdate($timecreated, get_string('strftimedateshort')) : '';
    }

    /**
     * Build account-level product access cards.
     *
     * @param array $products Product records keyed by product ID.
     * @param array $productcourses Product course records grouped by product ID.
     * @return array Product cards.
     */
    private static function build_product_access(array $products, array $productcourses): array {
        $cards = [];

        foreach ($products as $product) {
            $producttype = self::normalise_product_type((string)$product->producttype);
            if (!in_array($producttype, self::ACCOUNT_PRODUCT_TYPES, true)) {
                continue;
            }

            $productid = (int)$product->id;
            $imageurl = self::get_product_image_url($product, $producttype);
            $description = trim(strip_tags((string)($product->shortdescription ?: $product->description)));

            $cards[] = [
                'id' => $productid,
                'name' => format_string((string)$product->name),
                'description' => $description,
                'producttype' => $producttype,
                'typelabel' => self::product_type_label($producttype),
                'coursecount' => count($productcourses[$productid] ?? []),
                'imageurl' => $imageurl,
                'hasimage' => $imageurl !== '',
                'detailsurl' => self::product_details_url($productid, $producttype),
                'dashboardurl' => access_service::learner_dashboard_url()->out(false),
                'status' => (string)$product->status,
                'statuslabel' => self::product_status_label((string)$product->status),
                'statusclass' => self::status_class((string)$product->status),
                'source' => (string)($product->accesssource ?? ''),
            ];
        }

        return $cards;
    }

    /**
     * Get recent learner orders.
     *
     * @param int $userid User ID.
     * @return array Order cards.
     */
    private static function get_recent_orders(int $userid): array {
        global $DB;

        if (!self::table_exists('local_moderncommerce_orders')) {
            return [];
        }

        $orders = $DB->get_records(
            'local_moderncommerce_orders',
            ['userid' => $userid],
            'timecreated DESC',
            '*',
            0,
            5
        );
        $data = [];

        foreach ($orders as $order) {
            $items = self::get_order_item_summary((int)$order->id);
            $data[] = [
                'id' => (int)$order->id,
                'ordernumber' => (string)$order->ordernumber,
                'date' => userdate((int)$order->timecreated, get_string('strftimedateshort')),
                'datetime' => userdate((int)$order->timecreated, get_string('strftimedatetime')),
                'itemcount' => $items['itemcount'],
                'itemstext' => $items['itemcount'] === 1
                    ? get_string('item', 'local_moderncommerce')
                    : get_string('items', 'local_moderncommerce'),
                'firstitemname' => $items['firstitemname'],
                'hasmoreitems' => $items['itemcount'] > 1,
                'moreitemscount' => max(0, $items['itemcount'] - 1),
                'total' => pricing_service::format_order_price((float)$order->total, $order),
                'status' => (string)$order->status,
                'statuslabel' => self::order_status_label((string)$order->status),
                'statusclass' => self::status_class((string)$order->status),
                'viewurl' => self::learner_app_url('orders/' . (int)$order->id),
            ];
        }

        return $data;
    }

    /**
     * Summarise an order's line items.
     *
     * @param int $orderid Order ID.
     * @return array Item count and first item label.
     */
    private static function get_order_item_summary(int $orderid): array {
        global $DB;

        if (!self::table_exists('local_moderncommerce_order_items')) {
            return [
                'itemcount' => 0,
                'firstitemname' => '',
            ];
        }

        $items = $DB->get_records('local_moderncommerce_order_items', ['orderid' => $orderid], 'id ASC');
        $firstitem = $items ? reset($items) : null;

        return [
            'itemcount' => count($items),
            'firstitemname' => $firstitem ? format_string((string)$firstitem->itemname) : '',
        ];
    }

    /**
     * Build dashboard stats.
     *
     * @param int $userid User ID.
     * @param array $courses Course cards.
     * @param array $products Product cards.
     * @return array Stats.
     */
    private static function build_stats(int $userid, array $courses, array $products): array {
        global $DB;

        $invoicestats = learner_invoice_service::stats_for_user($userid);
        $counts = [
            'courses' => count($courses),
            'completedcourses' => count(array_filter($courses, static function (array $course): bool {
                return !empty($course['completed']);
            })),
            'bundles' => 0,
            'programs' => 0,
            'subscriptions' => 0,
            'plans' => 0,
            'orders' => self::table_exists('local_moderncommerce_orders')
                ? $DB->count_records('local_moderncommerce_orders', ['userid' => $userid])
                : 0,
            'invoices' => $invoicestats['total'],
            'outstandinginvoices' => $invoicestats['outstanding'],
            'displayinvoiceoutstanding' => $invoicestats['displayoutstanding'],
            'certificates' => 0,
        ];

        foreach ($products as $product) {
            if (isset($counts[$product['producttype'] . 's'])) {
                $counts[$product['producttype'] . 's']++;
            }
        }

        if ($DB->get_manager()->table_exists(new xmldb_table('customcert_issues'))) {
            $counts['certificates'] = $DB->count_records('customcert_issues', ['userid' => $userid]);
        }

        return $counts;
    }

    /**
     * Build user data for display.
     *
     * @return array User data.
     */
    private static function user_data(): array {
        global $PAGE, $USER;

        $userpicture = new \user_picture($USER);
        $userpicture->size = 100;

        return [
            'fullname' => fullname($USER),
            'initials' => self::initials(fullname($USER)),
            'avatarurl' => $userpicture->get_url($PAGE)->out(false),
            'membersince' => get_string('membersince', 'local_moderncommerce', userdate($USER->timecreated, '%B %Y')),
        ];
    }

    /**
     * Build shared learner URLs.
     *
     * @return array URL data.
     */
    private static function url_data(): array {
        return [
            'catalog' => self::learner_app_url('library'),
            'dashboard' => self::learner_app_url('dashboard'),
            'courses' => self::learner_app_url('courses'),
            'orders' => self::learner_app_url('orders'),
            'subscriptions' => self::learner_app_url('subscriptions'),
        ];
    }

    /**
     * Build a learner app hash route URL.
     *
     * @param string $route Route without leading hash.
     * @return string
     */
    private static function learner_app_url(string $route): string {
        return (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/' . ltrim($route, '/');
    }

    /**
     * Product image URL.
     *
     * @param \stdClass $product Product record.
     * @param string $producttype Normalised product type.
     * @return string Image URL.
     */
    private static function get_product_image_url(\stdClass $product, string $producttype): string {
        if (!empty($product->imageurl)) {
            return (string)$product->imageurl;
        }

        if ($producttype === 'bundle' || $producttype === 'program') {
            return \local_moderncommerce_get_bundle_image_url((int)$product->id);
        }

        return '';
    }

    /**
     * Product details URL.
     *
     * @param int $productid Product ID.
     * @param string $producttype Product type.
     * @return string URL.
     */
    private static function product_details_url(int $productid, string $producttype): string {
        if ($producttype === 'bundle' || $producttype === 'program') {
            return (new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $productid]))->out(false);
        }

        return (new moodle_url('/local/moderncommerce/learner/subscription.php'))->out(false);
    }

    /**
     * Product type display label.
     *
     * @param string $producttype Product type.
     * @return string Label.
     */
    private static function product_type_label(string $producttype): string {
        $producttype = self::normalise_product_type($producttype);
        if (get_string_manager()->string_exists($producttype, 'local_moderncommerce')) {
            return get_string($producttype, 'local_moderncommerce');
        }

        return ucfirst($producttype);
    }

    /**
     * Product status display label.
     *
     * @param string $status Stored status.
     * @return string Label.
     */
    private static function product_status_label(string $status): string {
        return localisation::status_label($status);
    }

    /**
     * Order status display label.
     *
     * @param string $status Stored status.
     * @return string Label.
     */
    private static function order_status_label(string $status): string {
        return localisation::status_label($status, ['orderstatus']);
    }

    /**
     * Map stored status to UI class.
     *
     * @param string $status Stored status.
     * @return string Class suffix.
     */
    private static function status_class(string $status): string {
        if (in_array($status, ['active', 'paid', 'completed'], true)) {
            return 'success';
        }
        if (in_array($status, ['pending', 'processing', 'draft'], true)) {
            return 'warning';
        }
        if (in_array($status, ['failed', 'cancelled', 'inactive', 'archived'], true)) {
            return 'danger';
        }
        if ($status === 'refunded') {
            return 'info';
        }

        return 'neutral';
    }

    /**
     * Normalise equivalent product type names.
     *
     * @param string $producttype Product type.
     * @return string Normalised type.
     */
    private static function normalise_product_type(string $producttype): string {
        $producttype = strtolower(trim($producttype));
        return $producttype === 'membership' ? 'subscription' : $producttype;
    }

    /**
     * Generate initials from a full name.
     *
     * @param string $fullname User full name.
     * @return string Initials.
     */
    private static function initials(string $fullname): string {
        $parts = preg_split('/\s+/', trim($fullname));
        $initials = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= \core_text::strtoupper(\core_text::substr($part, 0, 1));
            }
            if (\core_text::strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : '?';
    }

    /**
     * Check whether an optional table exists.
     *
     * @param string $table Table name without prefix.
     * @return bool True when the table exists.
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }

    /**
     * User structure.
     *
     * @return external_single_structure
     */
    private static function user_structure(): external_single_structure {
        return new external_single_structure([
            'fullname' => new external_value(PARAM_TEXT, 'Full name.'),
            'initials' => new external_value(PARAM_TEXT, 'Initials.'),
            'avatarurl' => new external_value(PARAM_RAW, 'Avatar URL.'),
            'membersince' => new external_value(PARAM_TEXT, 'Member since label.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_value(PARAM_INT, 'Accessible course count.'),
            'completedcourses' => new external_value(PARAM_INT, 'Completed course count.'),
            'bundles' => new external_value(PARAM_INT, 'Bundle access count.'),
            'programs' => new external_value(PARAM_INT, 'Program access count.'),
            'subscriptions' => new external_value(PARAM_INT, 'Subscription access count.'),
            'plans' => new external_value(PARAM_INT, 'Plan access count.'),
            'orders' => new external_value(PARAM_INT, 'Order count.'),
            'invoices' => new external_value(PARAM_INT, 'Visible manual invoice count.'),
            'outstandinginvoices' => new external_value(PARAM_INT, 'Outstanding manual invoice count.'),
            'displayinvoiceoutstanding' => new external_value(PARAM_TEXT, 'Formatted outstanding manual invoice amount.'),
            'certificates' => new external_value(PARAM_INT, 'Certificate count.'),
        ]);
    }

    /**
     * Access structure.
     *
     * @return external_single_structure
     */
    private static function access_structure(): external_single_structure {
        $productstructure = self::product_structure();

        return new external_single_structure([
            'courses' => new external_multiple_structure(self::course_structure()),
            'products' => new external_multiple_structure($productstructure),
            'bundles' => new external_multiple_structure($productstructure),
            'programs' => new external_multiple_structure($productstructure),
            'subscriptions' => new external_multiple_structure($productstructure),
            'plans' => new external_multiple_structure($productstructure),
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
            'hasimage' => new external_value(PARAM_BOOL, 'Whether an image URL exists.'),
            'progress' => new external_value(PARAM_INT, 'Completion percentage.'),
            'progresslabel' => new external_value(PARAM_TEXT, 'Completion percentage label.'),
            'completed' => new external_value(PARAM_BOOL, 'Whether course is complete.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Course status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Course status label.'),
            'courseurl' => new external_value(PARAM_RAW, 'Course URL.'),
            'modulecount' => new external_value(PARAM_INT, 'Course module count.'),
            'enrolleddate' => new external_value(PARAM_INT, 'Native enrolment timestamp.'),
            'enrolleddatelabel' => new external_value(PARAM_TEXT, 'Native enrolment date label.'),
            'lastaccess' => new external_value(PARAM_INT, 'Last access timestamp.'),
            'lastaccesslabel' => new external_value(PARAM_TEXT, 'Last access label.'),
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Access source.'),
            'sourcelabel' => new external_value(PARAM_TEXT, 'Access source label.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Source product type.'),
            'productname' => new external_value(PARAM_TEXT, 'Source product name.'),
        ]);
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
            'hasimage' => new external_value(PARAM_BOOL, 'Whether an image URL exists.'),
            'detailsurl' => new external_value(PARAM_RAW, 'Product details URL.'),
            'dashboardurl' => new external_value(PARAM_RAW, 'Learner dashboard URL.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Product status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Product status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Product status class.'),
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Access source.'),
        ]);
    }

    /**
     * Order structure.
     *
     * @return external_single_structure
     */
    private static function order_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Order ID.'),
            'ordernumber' => new external_value(PARAM_TEXT, 'Order number.'),
            'date' => new external_value(PARAM_TEXT, 'Short date.'),
            'datetime' => new external_value(PARAM_TEXT, 'Full date/time.'),
            'itemcount' => new external_value(PARAM_INT, 'Line item count.'),
            'itemstext' => new external_value(PARAM_TEXT, 'Item count label.'),
            'firstitemname' => new external_value(PARAM_TEXT, 'First item name.'),
            'hasmoreitems' => new external_value(PARAM_BOOL, 'Whether more items exist.'),
            'moreitemscount' => new external_value(PARAM_INT, 'Extra item count.'),
            'total' => new external_value(PARAM_TEXT, 'Formatted order total.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Order status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Order status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Order status class.'),
            'viewurl' => new external_value(PARAM_RAW, 'Order details URL.'),
        ]);
    }

    /**
     * Invoice structure.
     *
     * @return external_single_structure
     */
    private static function invoice_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Invoice ID.'),
            'invoicenumber' => new external_value(PARAM_TEXT, 'Invoice number.'),
            'date' => new external_value(PARAM_TEXT, 'Short invoice date.'),
            'datetime' => new external_value(PARAM_TEXT, 'Full invoice date/time.'),
            'duedate' => new external_value(PARAM_TEXT, 'Due date.'),
            'total' => new external_value(PARAM_TEXT, 'Formatted invoice total.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Invoice status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Invoice status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Invoice status class.'),
            'downloadurl' => new external_value(PARAM_RAW, 'Invoice download URL.'),
        ]);
    }

    /**
     * URL structure.
     *
     * @return external_single_structure
     */
    private static function url_structure(): external_single_structure {
        return new external_single_structure([
            'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
            'dashboard' => new external_value(PARAM_RAW, 'Learner dashboard URL.'),
            'courses' => new external_value(PARAM_RAW, 'Learner courses URL.'),
            'orders' => new external_value(PARAM_RAW, 'Learner orders URL.'),
            'subscriptions' => new external_value(PARAM_RAW, 'Learner subscriptions URL.'),
        ]);
    }
}
