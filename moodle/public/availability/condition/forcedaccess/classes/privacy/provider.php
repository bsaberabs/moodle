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

namespace availability_forcedaccess\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: the modules opened to a learner, in the course context.
 *
 * @package    availability_forcedaccess
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('availability_forcedaccess', [
            'courseid' => 'privacy:metadata:availability_forcedaccess:courseid',
            'sectionid' => 'privacy:metadata:availability_forcedaccess:sectionid',
            'userid' => 'privacy:metadata:availability_forcedaccess:userid',
            'usermodified' => 'privacy:metadata:availability_forcedaccess:usermodified',
            'timecreated' => 'privacy:metadata:availability_forcedaccess:timecreated',
        ], 'privacy:metadata:availability_forcedaccess');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {availability_forcedaccess} fa ON fa.courseid = ctx.instanceid AND ctx.contextlevel = :level
              WHERE fa.userid = :userid OR fa.usermodified = :modifier",
            ['level' => CONTEXT_COURSE, 'userid' => $userid, 'modifier' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {availability_forcedaccess} WHERE courseid = ?",
            [$context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $rows = $DB->get_records('availability_forcedaccess', ['courseid' => $context->instanceid, 'userid' => $userid]);
            if (!$rows) {
                continue;
            }
            $course = get_course($context->instanceid);
            $modinfo = get_fast_modinfo($course);
            $modules = [];
            foreach ($rows as $row) {
                $section = $modinfo->get_section_info_by_id($row->sectionid);
                $modules[] = (object) [
                    'module' => $section ? get_section_name($course, $section) : $row->sectionid,
                    'timecreated' => transform::datetime($row->timecreated),
                ];
            }
            writer::with_context($context)->export_data([get_string('pluginname', 'availability_forcedaccess')],
                (object) ['modules' => $modules]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_COURSE) {
            $DB->delete_records('availability_forcedaccess', ['courseid' => $context->instanceid]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                $DB->delete_records('availability_forcedaccess', ['courseid' => $context->instanceid,
                    'userid' => $contextlist->get_user()->id]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids());
        $DB->delete_records_select('availability_forcedaccess', "courseid = ? AND userid $insql",
            array_merge([$context->instanceid], $params));
    }
}
