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
 * Redeem key page renderable.
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

/**
 * Redeem key page output class.
 */
class redeem_page implements renderable, templatable {
    /** @var int|null The order ID if applicable */
    protected $orderid;

    /** @var bool Whether the redemption was successful */
    protected $success;

    /** @var string|null The success message */
    protected $successmessage;

    /** @var object|null The redeemed course */
    protected $redeemedcourse;

    /** @var bool Whether there was an error */
    protected $haserror;

    /** @var string|null The error message */
    protected $errormessage;

    /**
     * Constructor.
     *
     * @param int|null $orderid The order ID if applicable
     */
    public function __construct(?int $orderid = null) {
        $this->orderid = $orderid;
        $this->success = false;
        $this->successmessage = null;
        $this->redeemedcourse = null;
        $this->haserror = false;
        $this->errormessage = null;
    }

    /**
     * Set success state.
     *
     * @param string $message The success message
     * @param object|null $course The redeemed course
     */
    public function set_success(string $message, ?object $course = null): void {
        $this->success = true;
        $this->successmessage = $message;
        $this->redeemedcourse = $course;
    }

    /**
     * Set error state.
     *
     * @param string $message The error message
     */
    public function set_error(string $message): void {
        $this->haserror = true;
        $this->errormessage = $message;
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output The renderer
     * @return array Template data
     */
    public function export_for_template(renderer_base $output): array {
        global $CFG;

        $data = [
            'sesskey' => sesskey(),
            'redeemurl' => (new moodle_url('/local/moderncommerce/redeem.php'))->out(false),
            'hasorder' => !empty($this->orderid),
            'orderid' => $this->orderid,
            'hassuccess' => $this->success,
            'successmessage' => $this->successmessage,
            'haserror' => $this->haserror,
            'errormessage' => $this->errormessage,
            'catalogurl' => (new moodle_url('/local/moderncommerce/index.php'))->out(false),
        ];

        // Add redeemed course info if available.
        if ($this->redeemedcourse) {
            $imageurl = local_moderncommerce_get_course_image_url($this->redeemedcourse->id);
            if (empty($imageurl)) {
                $imageurl = $output->image_url('course-placeholder', 'local_moderncommerce')->out(false);
            }

            $data['redeemedcourse'] = [
                'id' => $this->redeemedcourse->id,
                'fullname' => format_string($this->redeemedcourse->fullname),
                'shortname' => format_string($this->redeemedcourse->shortname),
                'imageurl' => $imageurl,
                'courseurl' => (new moodle_url('/course/view.php', ['id' => $this->redeemedcourse->id]))->out(false),
            ];
        }

        return $data;
    }
}
