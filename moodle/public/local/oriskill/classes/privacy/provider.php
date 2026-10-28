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
            'sap_siteid' => 'privacy:metadata:creators:sapsiteid',
            'sap_key' => 'privacy:metadata:creators:sapkey',
        ], 'privacy:metadata:creators');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:logo');
        $collection->add_database_table('local_oriskill_affiliates', [
            'userid' => 'privacy:metadata:affiliates:userid',
            'code' => 'privacy:metadata:affiliates:code',
            'clicks' => 'privacy:metadata:affiliates:clicks',
            'payoutdetails' => 'privacy:metadata:affiliates:payoutdetails',
        ], 'privacy:metadata:affiliates');
        $collection->add_database_table('local_oriskill_commissions', [
            'affiliateid' => 'privacy:metadata:commissions:affiliateid',
            'orderid' => 'privacy:metadata:commissions:orderid',
            'amount' => 'privacy:metadata:commissions:amount',
            'status' => 'privacy:metadata:commissions:status',
        ], 'privacy:metadata:commissions');
        $collection->add_cookie(\local_oriskill\affiliate_service::COOKIE, 'privacy:metadata:cookie');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql('SELECT ctx.id FROM {context} ctx
                                     WHERE ctx.contextlevel = :level
                                       AND (EXISTS (SELECT 1 FROM {local_oriskill_creators} c WHERE c.userid = :userid1)
                                            OR EXISTS (SELECT 1 FROM {local_oriskill_affiliates} a WHERE a.userid = :userid2))',
            ['userid1' => $userid, 'userid2' => $userid, 'level' => CONTEXT_SYSTEM]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context() instanceof \context_system) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_oriskill_creators}', []);
            $userlist->add_from_sql('userid', 'SELECT userid FROM {local_oriskill_affiliates}', []);
        }
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $context = context_system::instance();
        if ($affiliate = $DB->get_record('local_oriskill_affiliates', ['userid' => $userid])) {
            writer::with_context($context)->export_data([get_string('affiliateprogram', 'local_oriskill')], (object) [
                'code' => $affiliate->code,
                'status' => $affiliate->status,
                'clicks' => $affiliate->clicks,
                'payoutdetails' => $affiliate->payoutdetails,
                'commissions' => array_values(array_map(fn($c) => [
                    'item' => $c->itemname, 'amount' => $c->amount, 'currency' => $c->currency, 'rate' => $c->rate,
                    'status' => $c->status, 'timecreated' => \core_privacy\local\request\transform::datetime($c->timecreated),
                ], $DB->get_records('local_oriskill_commissions', ['affiliateid' => $affiliate->id]))),
            ]);
        }
        $creator = $DB->get_record('local_oriskill_creators', ['userid' => $userid]);
        if (!$creator) {
            return;
        }
        $subcontext = [get_string('pluginname', 'local_oriskill')];
        writer::with_context($context)->export_data($subcontext, (object) [
            'displayname' => $creator->displayname,
            'slug' => $creator->slug,
            'tagline' => $creator->tagline,
            'bio' => $creator->bio,
            'motivation' => $creator->motivation,
            'status' => $creator->status,
            // The signing key is a secret: only whether one is saved is exported.
            'sap_siteid' => $creator->sap_siteid,
            'sap_key_saved' => !empty($creator->sap_key),
        ])->export_area_files($subcontext, 'local_oriskill', 'logo', $creator->id);
    }

    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if ($context instanceof \context_system) {
            foreach ($DB->get_records('local_oriskill_creators') as $creator) {
                self::delete_creator($creator);
            }
            foreach ($DB->get_fieldset('local_oriskill_affiliates', 'userid') as $userid) {
                self::delete_affiliate((int) $userid);
            }
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        if ($creator = $DB->get_record('local_oriskill_creators', ['userid' => $contextlist->get_user()->id])) {
            self::delete_creator($creator);
        }
        self::delete_affiliate((int) $contextlist->get_user()->id);
    }

    /**
     * Delete a user's affiliate account, with their referrals and commissions.
     *
     * @param int $userid User.
     */
    private static function delete_affiliate(int $userid): void {
        global $DB;
        if ($affiliate = $DB->get_record('local_oriskill_affiliates', ['userid' => $userid])) {
            $DB->delete_records('local_oriskill_commissions', ['affiliateid' => $affiliate->id]);
            $DB->delete_records('local_oriskill_referrals', ['affiliateid' => $affiliate->id]);
            $DB->delete_records('local_oriskill_affiliates', ['id' => $affiliate->id]);
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
            self::delete_affiliate((int) $userid);
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
