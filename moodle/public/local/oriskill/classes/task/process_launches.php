<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_oriskill\task;

use local_oriskill\launch_service;

/**
 * Every 5 minutes: open the trainings whose launch date has come (release dates of the learners enrolled before it
 * moved to that day), in case no learner opened them yet.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_launches extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task:processlaunches', 'local_oriskill');
    }

    public function execute() {
        mtrace('Trainings opened on their launch date: ' . launch_service::process_due());
    }
}
