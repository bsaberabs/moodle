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

/**
 * Installation of local_oriskill.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Let trainers, teachers of their own courses, log in as one of their learners ("Log in as" from the learners
 * page): Moodle limits that session to the course, shows a banner and logs it.
 */
function local_oriskill_allow_trainer_loginas(): void {
    global $DB;

    if ($roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'])) {
        assign_capability('moodle/user:loginas', CAP_ALLOW, $roleid, context_system::instance()->id, true);
    }
}

/**
 * Set up the plugin after its tables are created.
 *
 * @return bool
 */
function xmldb_local_oriskill_install() {
    local_oriskill_allow_trainer_loginas();
    return true;
}
