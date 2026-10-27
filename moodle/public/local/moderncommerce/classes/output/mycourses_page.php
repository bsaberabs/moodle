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
 * My Courses page renderable.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use renderable;
use renderer_base;
use templatable;
use moodle_url;
use context_course;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\api\order_api;
/**
 * My Courses page output class.
 */
class mycourses_page implements renderable, templatable {
    /** @var int The user ID */
    protected $userid;

    /** @var string The current filter */
    protected $filter;

    /**
     * Constructor.
     *
     * @param int $userid The user ID
     * @param string $filter The filter to apply (all, inprogress, completed, notstarted)
     */
    public function __construct(int $userid, string $filter = 'all') {
        $this->userid = $userid;
        $this->filter = $filter;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output The renderer
     * @return array Template data
     */
    public function export_for_template(renderer_base $output): array {
        global $DB, $CFG;

        require_once($CFG->libdir . '/completionlib.php');

        // Get all paid/completed orders for the user.
        [$statussql, $statusparams] = $DB->get_in_or_equal(['paid', 'completed'], SQL_PARAMS_NAMED, 'status');
        $orders = $DB->get_records_select(
            'local_moderncommerce_orders',
            "userid = :userid AND status $statussql",
            ['userid' => $this->userid] + $statusparams,
            'timecreated DESC'
        );
        $purchasedcourses = [];
        $courseids = [];

        // Collect all purchased courses.
        foreach ($orders as $order) {
            $items = order_api::get_order_items((int) $order->id);
            foreach ($items as $item) {
                $itemcourseids = [];
                if (!empty($item->courseid)) {
                    $itemcourseids[] = (int) $item->courseid;
                } else if (!empty($item->bundleid)) {
                    foreach (bundle_api::get_courses((int) $item->bundleid) as $bundlecourse) {
                        $itemcourseids[] = (int) $bundlecourse->courseid;
                    }
                }

                foreach (array_unique($itemcourseids) as $courseid) {
                    if (isset($courseids[$courseid])) {
                        continue;
                    }
                    $course = $DB->get_record('course', ['id' => $courseid]);
                    if (!$course) {
                        continue;
                    }
                    $courseids[$courseid] = true;
                    $purchasedcourses[] = [
                    'course' => $course, 'item' => $item, 'order' => $order,
                    ];
                }
            }
        }
        // Process courses with progress.
        $courses = [];
        $inprogresscount = 0;
        $completedcount = 0;
        $notstartedcount = 0;

        foreach ($purchasedcourses as $data) {
            $course = $data['course'];
            $order = $data['order'];

            // Get course completion info.
            $completion = new \completion_info($course);
            $progress = 0;
            $hasprogress = false;
            $iscompleted = false;
            $isinprogress = false;
            $isnotstarted = true;

            if ($completion->is_enabled()) {
                $hasprogress = true;
                $progress = (int) \core_completion\progress::get_course_progress_percentage($course, $this->userid);

                if ($progress >= 100) {
                    $iscompleted = true;
                    $isnotstarted = false;
                    $completedcount++;
                } else if ($progress > 0) {
                    $isinprogress = true;
                    $isnotstarted = false;
                    $inprogresscount++;
                } else {
                    $notstartedcount++;
                }
            } else {
                // Check if user has accessed the course.
                $lastaccess = $DB->get_field('user_lastaccess', 'timeaccess', [
                    'userid' => $this->userid,
                    'courseid' => $course->id,
                ]);

                if ($lastaccess) {
                    $isinprogress = true;
                    $isnotstarted = false;
                    $inprogresscount++;
                } else {
                    $notstartedcount++;
                }
            }

            // Apply filter.
            if ($this->filter === 'inprogress' && !$isinprogress) {
                continue;
            }
            if ($this->filter === 'completed' && !$iscompleted) {
                continue;
            }
            if ($this->filter === 'notstarted' && !$isnotstarted) {
                continue;
            }

            // Get course image.
            $imageurl = local_moderncommerce_get_course_image_url($course->id);
            if (empty($imageurl)) {
                $imageurl = $output->image_url('course-placeholder', 'local_moderncommerce')->out(false);
            }

            $courses[] = [
                'id' => $course->id,
                'fullname' => format_string($course->fullname),
                'shortname' => format_string($course->shortname),
                'summary' => format_text($course->summary, FORMAT_HTML, ['noclean' => true]),
                'imageurl' => $imageurl,
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'progress' => $progress,
                'hasprogress' => $hasprogress,
                'purchasedate' => userdate($order->timecreated, get_string('strftimedatefullshort', 'core_langconfig')),
                'ordernumber' => $order->ordernumber,
                'iscompleted' => $iscompleted,
                'isinprogress' => $isinprogress,
                'isnotstarted' => $isnotstarted,
            ];
        }

        $totalcount = count($purchasedcourses);

        return [
            'hascourses' => !empty($courses),
            'courses' => $courses,
            'catalogurl' => (new moodle_url('/local/moderncommerce/index.php'))->out(false),
            'totalcount' => $totalcount,
            'inprogresscount' => $inprogresscount,
            'completedcount' => $completedcount,
            'notstartedcount' => $notstartedcount,
            'allactive' => $this->filter === 'all',
            'inprogressactive' => $this->filter === 'inprogress',
            'completedactive' => $this->filter === 'completed',
            'notstartedactive' => $this->filter === 'notstarted',
        ];
    }
}
