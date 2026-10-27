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
 * Redeem bundle key page renderable.
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
 * Redeem bundle key page output class.
 */
class redeem_bundle_page implements renderable, templatable {
    /** @var object The order */
    protected $order;

    /** @var array Bundle items from order */
    protected $bundleitems;

    /** @var bool Whether all bundles were successfully redeemed */
    protected $hassuccess;

    /** @var array Error messages */
    protected $errors;

    /** @var array Success messages */
    protected $successes;

    /**
     * Constructor.
     *
     * @param object $order The order
     * @param array $bundleitems Bundle items from order
     */
    public function __construct(object $order, array $bundleitems) {
        $this->order = $order;
        $this->bundleitems = $bundleitems;
        $this->hassuccess = false;
        $this->errors = [];
        $this->successes = [];
    }

    /**
     * Set full success state (all bundles redeemed).
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

        $bundles = [];
        $count = 0;
        $total = count($this->bundleitems);

        foreach ($this->bundleitems as $item) {
            $count++;

            // Get courses in bundle.
            $bundlecourses = bundle_api::get_courses($item->bundleid);
            $courses = [];

            foreach ($bundlecourses as $bundlecourse) {
                $course = $DB->get_record('course', ['id' => $bundlecourse->courseid]);
                if ($course) {
                    $courses[] = [
                        'id' => $course->id,
                        'fullname' => format_string($course->fullname),
                        'shortname' => format_string($course->shortname),
                    ];
                }
            }

            $bundles[] = [
                'bundleid' => $item->bundleid,
                'bundlename' => format_string($item->bundlename),
                'coursecount' => count($courses),
                'courses' => $courses,
                'fieldname' => 'bundlekey_' . $item->bundleid,
                'islast' => ($count === $total),
            ];
        }

        return [
            'sesskey' => sesskey(),
            'redeemurl' => (new moodle_url('/local/moderncommerce/redeem_bundle.php', ['orderid' => $this->order->id]))->out(false),
            'ordernumber' => $this->order->ordernumber,
            'ordertotal' => pricing_service::format_order_price($this->order->total, $this->order), 'bundles' => $bundles,
            'hassuccess' => $this->hassuccess,
            'haserrors' => !empty($this->errors),
            'errors' => $this->errors,
            'hassuccesses' => !empty($this->successes),
            'successes' => $this->successes,
            'catalogurl' => (new moodle_url('/local/moderncommerce/index.php'))->out(false),
            'mycoursesurl' => (new moodle_url('/local/moderncommerce/mycourses.php'))->out(false),
        ];
    }
}
