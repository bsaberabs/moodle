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

namespace local_oriskill\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider: trainer space records and logos, in the system context.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_oriskill_creators', [
            'userid' => 'privacy:metadata:creators:userid',
            'displayname' => 'privacy:metadata:creators:displayname',
            'tagline' => 'privacy:metadata:creators:tagline',
            'bio' => 'privacy:metadata:creators:bio',
            'motivation' => 'privacy:metadata:creators:motivation',
            'status' => 'privacy:metadata:creators:status',
        ], 'privacy:metadata:creators');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:logo');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql('SELECT ctx.id FROM {context} ctx
                                      JOIN {local_oriskill_creators} c ON c.userid = :userid
                                     WHERE ctx.contextlevel = :level',
            ['userid' => $userid, 'level' => CONTEXT_SYSTEM]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context() instanceof \context_system) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_oriskill_creators}', []);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $creator = $DB->get_record('local_oriskill_creators', ['userid' => $userid]);
        if (!$creator) {
            return;
        }
        $context = context_system::instance();
        $subcontext = [get_string('pluginname', 'local_oriskill')];
        writer::with_context($context)->export_data($subcontext, (object) [
            'displayname' => $creator->displayname,
            'slug' => $creator->slug,
            'tagline' => $creator->tagline,
            'bio' => $creator->bio,
            'motivation' => $creator->motivation,
            'status' => $creator->status,
        ])->export_area_files($subcontext, 'local_oriskill', 'logo', $creator->id);
    }

    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if ($context instanceof \context_system) {
            foreach ($DB->get_records('local_oriskill_creators') as $creator) {
                self::delete_creator($creator);
            }
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if ($creator = $DB->get_record('local_oriskill_creators', ['userid' => $contextlist->get_user()->id])) {
            self::delete_creator($creator);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            if ($creator = $DB->get_record('local_oriskill_creators', ['userid' => $userid])) {
                self::delete_creator($creator);
            }
        }
    }

    /**
     * Delete a trainer space record and its logo. The trainer's courses stay with the platform.
     *
     * @param \stdClass $creator Creator.
     */
    private static function delete_creator(\stdClass $creator): void {
        global $DB;
        get_file_storage()->delete_area_files(context_system::instance()->id, 'local_oriskill', 'logo', $creator->id);
        $DB->delete_records('local_oriskill_creators', ['id' => $creator->id]);
    }
}
