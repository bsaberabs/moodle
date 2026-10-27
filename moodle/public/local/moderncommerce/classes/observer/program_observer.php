<?php
// This file is part of Moodle and is licensed under the GNU GPL v3 or later.
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
 * Observer for Moodle course completion events affecting programs.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\observer;

use local_moderncommerce\services\program_progress_service;

/**
 * Refresh programs when Moodle marks a course complete.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_observer {
    /**
     * Synchronize programs affected by a course completion.
     *
     * @param \core\event\course_completed $event Course completion event.
     */
    public static function course_completed(\core\event\course_completed $event): void {
        program_progress_service::sync_for_course((int)$event->relateduserid, (int)$event->courseid);
    }
}
