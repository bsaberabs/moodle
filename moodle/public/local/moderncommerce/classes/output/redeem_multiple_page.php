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
 * Redeem multiple keys page renderable.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;


use renderable;
use renderer_base;
use templatable;
use moodle_url;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\services\pricing_service;

/**
 * Redeem multiple keys page output class.
 */
class redeem_multiple_page implements renderable, templatable {
    /** @var object The order */
    protected $order;

    /** @var array Course items from order */
    protected $courseitems;

    /** @var array Bundle items from order */
    protected $bundleitems;

    /** @var bool Whether all items were successfully redeemed */
    protected $hassuccess;

    /** @var array Error messages */
    protected $errors;

    /** @var array Success messages */
    protected $successes;

    /**
     * Constructor.
     *
     * @param object $order The order
     * @param array $courseitems Individual course items from order
     * @param array $bundleitems Bundle items from order
     */
    public function __construct(object $order, array $courseitems, array $bundleitems) {
        $this->order = $order;
        $this->courseitems = $courseitems;
        $this->bundleitems = $bundleitems;
        $this->hassuccess = false;
        $this->errors = [];
        $this->successes = [];
    }

    /**
     * Set full success state (all items redeemed).
     */
    public function set_full_success(): void {
        $this->hassuccess = true;
    }

    /**
     * Add an error message.
     *
     * @param string $error The error message
     */
    public function add_error(string $error): void {
        $this->errors[] = $error;
    }

    /**
     * Add a success message.
     *
     * @param string $success The success message
     */
    public function add_success(string $success): void {
        $this->successes[] = $success;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output The renderer
     * @return array Template data
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        global $USER;

        // Process bundles.
        $bundles = [];
        foreach ($this->bundleitems as $item) {
            // Get courses in bundle.
            $bundlecourses = bundle_api::get_courses($item->bundleid);
            $courses = [];
            $allenrolled = true;
            $enrolledcount = 0;

            foreach ($bundlecourses as $bundlecourse) {
                $course = $DB->get_record('course', ['id' => $bundlecourse->courseid]);
                if ($course) {
                    $context = \context_course::instance($course->id);
                    $isenrolled = is_enrolled($context, $USER->id);
                    if ($isenrolled) {
                        $enrolledcount++;
                    } else {
                        $allenrolled = false;
                    }
                    $courses[] = [
                        'id' => $course->id,
                        'fullname' => format_string($course->fullname),
                        'isenrolled' => $isenrolled,
                        'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    ];
                }
            }

            $price = null;
            if (isset($item->unitprice)) {
                $price = pricing_service::format_order_price($item->unitprice, $this->order);
            }

            $bundles[] = [
                'bundleid' => $item->bundleid,
                'name' => format_string($item->bundlename ?: $item->coursename ?: get_string('bundle', 'local_moderncommerce')),
                'coursecount' => count($courses),
                'courses' => $courses,
                'price' => $price,
                'fieldname' => 'bundlekey_' . $item->bundleid,
                'allenrolled' => $allenrolled,
                'enrolledcount' => $enrolledcount,
                'bundleurl' => (new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $item->bundleid]))->out(false),
            ];
        }

        // Process individual courses.
        $courses = [];
        foreach ($this->courseitems as $item) {
            $course = $DB->get_record('course', ['id' => $item->courseid]);
            if (!$course) {
                continue;
            }

            $price = null;
            if (isset($item->unitprice)) {
                $price = pricing_service::format_order_price($item->unitprice, $this->order);
            } else if (isset($item->price)) {
                $price = pricing_service::format_order_price($item->price, $this->order);
            }

                // Check if user is enrolled.
                $context = \context_course::instance($course->id);
                $isenrolled = is_enrolled($context, $USER->id);

                $courses[] = [
                'id' => $course->id,
                'courseid' => $course->id,
                'fullname' => format_string($course->fullname),
                'shortname' => format_string($course->shortname),
                'price' => $price,
                'fieldname' => 'keycode_' . $course->id,
                'isenrolled' => $isenrolled,
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                ];
        }

        // Check if all items are already enrolled.
        $allitemsenrolled = true;
        foreach ($bundles as $bundle) {
            if (!$bundle['allenrolled']) {
                $allitemsenrolled = false;
                break;
            }
        }
        if ($allitemsenrolled) {
            foreach ($courses as $course) {
                if (!$course['isenrolled']) {
                    $allitemsenrolled = false;
                    break;
                }
            }
        }

        return [
            'sesskey' => sesskey(),
            'redeemurl' => (new moodle_url(
                '/local/moderncommerce/redeem_multiple.php',
                ['orderid' => $this->order->id]
            ))->out(false),
            'ordernumber' => $this->order->ordernumber,
            'ordertotal' => pricing_service::format_order_price($this->order->total, $this->order),
            'hasbundles' => !empty($bundles),
            'bundles' => $bundles,
            'hascourses' => !empty($courses),
            'courses' => $courses,
            'hassuccess' => $this->hassuccess,
            'haserrors' => !empty($this->errors),
            'errors' => $this->errors,
            'hassuccesses' => !empty($this->successes),
            'successes' => $this->successes,
            'allitemsenrolled' => $allitemsenrolled,
            'catalogurl' => (new moodle_url('/local/moderncommerce/index.php'))->out(false),
            'mycoursesurl' => (new moodle_url('/local/moderncommerce/mycourses.php'))->out(false),
            'orderurl' => (new moodle_url('/local/moderncommerce/order.php', ['id' => $this->order->id]))->out(false),
        ];
    }
}
