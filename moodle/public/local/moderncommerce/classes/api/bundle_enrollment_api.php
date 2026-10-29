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
 * Bundle Enrollment API.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/enrol/moderncommerce/lib.php');

/**
 * Bundle enrollment helper backed by canonical product bundle tables.
 */
class bundle_enrollment_api {
    /**
     * Enroll user in all courses included in a bundle/program product.
     *
     * @param int $userid User ID.
     * @param int $bundleid Bundle product ID.
     * @param int|null $orderid Order ID.
     * @return bool Success.
     */
    public static function enroll_user($userid, $bundleid, $orderid = null) {
        global $DB;

        $bundle = bundle_api::get((int) $bundleid);
        if (!$bundle) {
            throw new \moodle_exception('invalidbundle', 'local_moderncommerce');
        }

        if (!empty($bundle->isprogram)) {
            \local_moderncommerce\services\program_progress_service::enrol(
                (int)$userid,
                (int)$bundleid,
                $orderid === null ? null : (int)$orderid
            );
        } else {
            foreach (bundle_api::get_courses((int) $bundleid) as $course) {
                self::enroll_in_course((int) $userid, (int) $course->courseid);
            }
        }

        $DB->execute(
            "UPDATE {local_moderncommerce_products}
                SET currentenrollment = COALESCE(currentenrollment, 0) + 1,
                    timemodified = :now
              WHERE id = :id",
            ['id' => (int) $bundleid, 'now' => time()]
        );

        return true;
    }

    /**
     * Enroll user in a single course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool Success.
     */
    private static function enroll_in_course($userid, $courseid) {
        global $DB;

        $instance = $DB->get_record('enrol', [
            'courseid' => (int) $courseid,
            'enrol' => 'moderncommerce',
        ], '*', IGNORE_MISSING);

        if (!$instance) {
            $plugin = enrol_get_plugin('moderncommerce');
            $course = $DB->get_record('course', ['id' => (int) $courseid]);
            if ($plugin && $course) {
                $instanceid = $plugin->add_instance($course);
                $instance = $DB->get_record('enrol', ['id' => $instanceid]);
            }
        }

        if (!$instance) {
            return false;
        }

        $plugin = enrol_get_plugin('moderncommerce');
        if (!$plugin) {
            return false;
        }

        $plugin->enrol_user($instance, (int) $userid, $instance->roleid, time(), 0);
        return true;
    }

    /**
     * Get user's computed program progress.
     *
     * @param int $userid User ID.
     * @param int $bundleid Bundle product ID.
     * @return object|false Progress object.
     */
    public static function get_user_progress($userid, $bundleid) {
        $bundle = bundle_api::get((int)$bundleid);
        if ($bundle && !empty($bundle->isprogram) && self::is_enrolled((int)$userid, (int)$bundleid)) {
            return (object)\local_moderncommerce\services\program_progress_service::sync(
                (int)$userid,
                (int)$bundleid
            );
        }
        if (!self::is_enrolled((int) $userid, (int) $bundleid)) {
            return false;
        }

        $courses = bundle_api::get_courses((int) $bundleid);
        $coursedetails = [];
        $coursescompleted = 0;

        foreach ($courses as $bc) {
            $course = get_course((int) $bc->courseid);
            $completion = new \completion_info($course);
            $iscomplete = $completion->is_course_complete((int) $userid);
            if ($iscomplete) {
                $coursescompleted++;
            }

            $coursedetails[] = [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'completed' => $iscomplete,
                'sortorder' => (int) $bc->sortorder,
            ];
        }

        $totalcourses = count($courses);
        $progress = new \stdClass();
        $progress->bundleid = (int) $bundleid;
        $progress->userid = (int) $userid;
        $progress->status = ($totalcourses > 0 && $coursescompleted === $totalcourses) ? 'completed' : 'active';
        $progress->totalcourses = $totalcourses;
        $progress->coursescompleted = $coursescompleted;
        $progress->progresspercentage = $totalcourses > 0 ? round(($coursescompleted / $totalcourses) * 100, 2) : 0;
        $progress->certificateissued = 0;
        $progress->courses = $coursedetails;

        return $progress;
    }

    /**
     * Program progress is computed live until a dedicated progress table is introduced.
     *
     * @param int $userid User ID.
     * @param int $bundleid Bundle product ID.
     * @return bool Success.
     */
    public static function update_progress($userid, $bundleid) {
        return self::get_user_progress((int) $userid, (int) $bundleid) !== false;
    }

    /**
     * Program certificate issuing requires a future canonical program progress table.
     *
     * @param int $enrollmentid Enrollment ID.
     * @return bool Success.
     */
    public static function issue_certificate($enrollmentid) {
        global $DB;
        $progress = $DB->get_record('local_moderncommerce_program_progress', ['id' => (int)$enrollmentid]);
        if (!$progress) {
            return false;
        }
        $result = \local_moderncommerce\services\program_progress_service::sync(
            (int)$progress->userid,
            (int)$progress->programid
        );
        return !empty($result['certificateissued']);
    }

    /**
     * Get user's purchased bundles/programs.
     *
     * @param int $userid User ID.
     * @param string $type Type filter: all, programs, bundles.
     * @return array Array of bundle products.
     */
    public static function get_user_bundles($userid, $type = 'all') {
        global $DB;

        $params = [
            'userid' => (int) $userid,
            'paid' => 'paid',
            'completed' => 'completed',
            'bundle' => 'bundle',
            'program' => 'program',
        ];
        $conditions = [
            'o.userid = :userid',
            'o.status IN (:paid, :completed)',
            'p.producttype IN (:bundle, :program)',
        ];

        if ($type === 'programs') {
            $conditions[] = 'p.producttype = :programonly';
            $params['programonly'] = 'program';
        } else if ($type === 'bundles') {
            $conditions[] = 'p.producttype = :bundleonly';
            $params['bundleonly'] = 'bundle';
        }

        $sql = "SELECT p.*, MIN(o.timecreated) AS purchasedate
                  FROM {local_moderncommerce_products} p
                  JOIN {local_moderncommerce_order_items} i ON i.productid = p.id
                  JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
                 WHERE " . implode(' AND ', $conditions) . "
              GROUP BY p.id, p.producttype, p.name, p.slug, p.sku, p.status, p.visible, p.featured,
                       p.shortdescription, p.description, p.imageurl, p.taxable, p.taxcategory,
                       p.enrolduration, p.maxenrollment, p.currentenrollment, p.displayorder,
                       p.createdby, p.modifiedby, p.timecreated, p.timemodified
              ORDER BY purchasedate DESC";

        $bundles = $DB->get_records_sql($sql, $params);

        foreach ($bundles as $bundle) {
            $bundle->isprogram = $bundle->producttype === 'program' ? 1 : 0;
            if ($bundle->isprogram) {
                $bundle->progress = self::get_user_progress((int) $userid, (int) $bundle->id);
            }
        }

        return $bundles;
    }

    /**
     * Check if user has purchased a bundle/program.
     *
     * @param int $userid User ID.
     * @param int $bundleid Bundle product ID.
     * @return bool Enrolled.
     */
    public static function is_enrolled($userid, $bundleid) {
        global $DB;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {local_moderncommerce_orders} o
               JOIN {local_moderncommerce_order_items} i ON i.orderid = o.id
              WHERE o.userid = :userid
                AND i.productid = :bundleid
                AND o.status IN ('paid', 'completed')",
            [
                'userid' => (int) $userid,
                'bundleid' => (int) $bundleid,
            ]
        );
    }

    /**
     * Get computed completion stats for bundle purchasers.
     *
     * @param int $bundleid Bundle product ID.
     * @return array Stats.
     */
    public static function get_completion_stats($bundleid) {
        global $DB;

        $sql = "SELECT DISTINCT o.userid
                  FROM {local_moderncommerce_orders} o
                  JOIN {local_moderncommerce_order_items} i ON i.orderid = o.id
                 WHERE i.productid = :bundleid
                   AND o.status IN ('paid', 'completed')";
        $users = $DB->get_records_sql($sql, ['bundleid' => (int) $bundleid]);

        $stats = [
            'total' => count($users),
            'active' => 0,
            'completed' => 0,
            'average_progress' => 0,
        ];

        $totalprogress = 0;
        foreach ($users as $user) {
            $progress = self::get_user_progress((int) $user->userid, (int) $bundleid);
            if (!$progress) {
                continue;
            }
            if ($progress->status === 'completed') {
                $stats['completed']++;
            } else {
                $stats['active']++;
            }
            $totalprogress += (float) $progress->progresspercentage;
        }

        if ($stats['total'] > 0) {
            $stats['average_progress'] = round($totalprogress / $stats['total'], 2);
        }

        return $stats;
    }

    /**
     * Unenroll user from all courses in a bundle/program.
     *
     * @param int $userid User ID.
     * @param int $bundleid Bundle product ID.
     * @return bool Success.
     */
    public static function unenroll_user($userid, $bundleid) {
        global $DB;

        foreach (bundle_api::get_courses((int) $bundleid) as $course) {
            self::unenroll_from_course((int) $userid, (int) $course->courseid);
        }

        $DB->execute(
            "UPDATE {local_moderncommerce_products}
                SET currentenrollment = CASE
                        WHEN COALESCE(currentenrollment, 0) > 0 THEN currentenrollment - 1
                        ELSE 0
                    END,
                    timemodified = :now
              WHERE id = :id",
            ['id' => (int) $bundleid, 'now' => time()]
        );

        return true;
    }

    /**
     * Unenroll user from a single course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool Success.
     */
    private static function unenroll_from_course($userid, $courseid) {
        global $DB;

        $instance = $DB->get_record('enrol', [
            'courseid' => (int) $courseid,
            'enrol' => 'moderncommerce',
        ]);

        if (!$instance) {
            return false;
        }

        $plugin = enrol_get_plugin('moderncommerce');
        if (!$plugin) {
            return false;
        }

        $plugin->unenrol_user($instance, (int) $userid);
        return true;
    }
}
