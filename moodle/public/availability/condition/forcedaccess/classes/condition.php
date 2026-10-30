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

namespace availability_forcedaccess;

use core_availability\info;
use core_availability\result;

/**
 * Condition: the trainer opened this module (section) to the user, whatever its other restrictions.
 *
 * A section the trainer opens to someone gets the restriction "this condition OR (its own rules)"; the users are
 * listed in the availability_forcedaccess table. Learners who were not given access never see this condition among
 * the reasons a module is locked.
 *
 * @package    availability_forcedaccess
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {

    /** @var array Opened section ids per "courseid-userid", for the current request. */
    protected static $sections = [];

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode.
     */
    public function __construct($structure) {
    }

    /**
     * Saves the condition.
     *
     * @return \stdClass
     */
    public function save() {
        return self::get_json();
    }

    /**
     * JSON structure of a condition.
     *
     * @return \stdClass
     */
    public static function get_json(): \stdClass {
        return (object) ['type' => 'forcedaccess'];
    }

    public function check_available($not, info $info, $grabthelot, $userid) {
        // Never listed among the reasons a module is locked: only the module's own rules are.
        return new result($this->is_available($not, $info, $grabthelot, $userid));
    }

    public function is_available($not, info $info, $grabthelot, $userid) {
        $sectionid = self::sectionid($info);
        $allow = $sectionid && in_array($sectionid, self::opened_sections((int) $info->get_course()->id, (int) $userid));
        return $not ? !$allow : $allow;
    }

    public function get_description($full, $not, info $info) {
        return get_string($full ? 'full_staff' : 'full', 'availability_forcedaccess');
    }

    protected function get_debug_string() {
        return '';
    }

    public function is_applied_to_user_lists() {
        return true;
    }

    public function filter_user_list(array $users, $not, info $info, \core_availability\capability_checker $checker) {
        global $DB;

        $opened = $DB->get_records_menu('availability_forcedaccess', ['sectionid' => self::sectionid($info)], '',
            'userid, id');
        return array_filter($users, fn($id) => isset($opened[$id]) !== (bool) $not, ARRAY_FILTER_USE_KEY);
    }

    public function get_user_list_sql($not, info $info, $onlyactive) {
        $params = [];
        [$enrolsql, $enrolparams] = get_enrolled_sql($info->get_context(), '', 0, $onlyactive);
        $section = self::unique_sql_parameter($params, (int) self::sectionid($info));
        $condition = $not ? 'NOT' : '';
        return ["SELECT userids.id
                   FROM ($enrolsql) userids
                  WHERE $condition EXISTS (SELECT 1 FROM {availability_forcedaccess} fa
                                            WHERE fa.userid = userids.id AND fa.sectionid = $section)",
            array_merge($enrolparams, $params)];
    }

    /**
     * Section the availability belongs to (a module's own section for an activity).
     *
     * @param info $info Availability info.
     * @return int Section id, 0 when unknown.
     */
    protected static function sectionid(info $info): int {
        if ($info instanceof \core_availability\info_section) {
            return (int) $info->get_section()->id;
        }
        if ($info instanceof \core_availability\info_module) {
            return (int) $info->get_course_module()->section;
        }
        return 0;
    }

    // ----- Opening modules ----------------------------------------------------------------------------------------

    /**
     * Sections opened to a user in a course.
     *
     * @param int $courseid Course.
     * @param int $userid User.
     * @return int[] Section ids.
     */
    public static function opened_sections(int $courseid, int $userid): array {
        global $DB;

        $key = $courseid . '-' . $userid;
        if (!isset(self::$sections[$key])) {
            self::$sections[$key] = array_map('intval', $DB->get_fieldset_select('availability_forcedaccess', 'sectionid',
                'courseid = ? AND userid = ?', [$courseid, $userid]));
        }
        return self::$sections[$key];
    }

    /**
     * Set the sections opened to a user in a course; their restrictions now let the user in.
     *
     * @param \stdClass $course Course.
     * @param int $userid User.
     * @param int[] $sectionids Sections to open; the others are closed again.
     */
    public static function set_opened_sections(\stdClass $course, int $userid, array $sectionids): void {
        global $DB, $USER;

        $sections = $DB->get_records('course_sections', ['course' => $course->id], '', 'id');
        $sectionids = array_values(array_unique(array_filter(array_map('intval', $sectionids),
            fn($id) => isset($sections[$id]))));
        $current = self::opened_sections((int) $course->id, $userid);
        foreach (array_diff($current, $sectionids) as $sectionid) {
            $DB->delete_records('availability_forcedaccess', ['sectionid' => $sectionid, 'userid' => $userid]);
        }
        foreach (array_diff($sectionids, $current) as $sectionid) {
            $DB->insert_record('availability_forcedaccess', (object) ['courseid' => $course->id, 'sectionid' => $sectionid,
                'userid' => $userid, 'usermodified' => $USER->id, 'timecreated' => time()]);
        }
        self::reset_cache();
        foreach ($sectionids as $sectionid) {
            self::let_in($course, $sectionid);
        }
    }

    /**
     * Make sure a section's restriction includes this condition, as "this condition OR (its own rules)".
     *
     * @param \stdClass $course Course.
     * @param int $sectionid Section.
     */
    public static function let_in(\stdClass $course, int $sectionid): void {
        global $DB;

        $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $course->id]);
        if (!$section) {
            return;
        }
        $wrapped = self::wrap($section->availability);
        if ($wrapped !== $section->availability) {
            course_update_section($course, $section, ['availability' => $wrapped]);
        }
    }

    /**
     * Restriction "this condition OR (rules)" for a section's restriction, unchanged when it has none or already has it.
     *
     * @param string|null $json Restriction.
     * @return string|null
     */
    public static function wrap(?string $json): ?string {
        $tree = $json ? json_decode($json) : null;
        if (!$tree || empty($tree->c) || self::unwrap($tree) !== $tree) {
            return $json;
        }
        // The root display flags move to the new root; hiding any rule entirely hides the module entirely.
        $show = isset($tree->showc) ? !in_array(false, $tree->showc, true) : ($tree->show ?? true);
        unset($tree->show, $tree->showc);
        return json_encode(\core_availability\tree::get_root_json([self::get_json(), $tree], \core_availability\tree::OP_OR,
            $show));
    }

    /**
     * The section's own rules in a restriction made by wrap(), or the restriction itself.
     *
     * @param \stdClass $tree Decoded restriction.
     * @return \stdClass
     */
    public static function unwrap(\stdClass $tree): \stdClass {
        if (($tree->op ?? '') === '|' && count($tree->c ?? []) === 2 && ($tree->c[0]->type ?? '') === 'forcedaccess'
                && !isset($tree->c[1]->type)) {
            return $tree->c[1];
        }
        return $tree;
    }

    /**
     * Forget the sections read during this request (after a change, and in tests).
     */
    public static function reset_cache(): void {
        self::$sections = [];
    }

    // ----- Clean-up -------------------------------------------------------------------------------------------------

    /**
     * A user who is no longer enrolled in the course loses the modules opened to them.
     *
     * @param \core\event\user_enrolment_deleted $event Event.
     */
    public static function user_unenrolled(\core\event\user_enrolment_deleted $event): void {
        global $DB;

        if (!is_enrolled(\context_course::instance($event->courseid), $event->relateduserid)) {
            $DB->delete_records('availability_forcedaccess', ['courseid' => $event->courseid,
                'userid' => $event->relateduserid]);
            self::reset_cache();
        }
    }

    /**
     * Forget the opened modules of a deleted course.
     *
     * @param \core\event\course_deleted $event Event.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;

        $DB->delete_records('availability_forcedaccess', ['courseid' => $event->courseid]);
        self::reset_cache();
    }
}
