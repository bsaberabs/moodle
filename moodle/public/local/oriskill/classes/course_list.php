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

namespace local_oriskill;

/**
 * The list of a trainer's courses: what each holds, its members, its sales, its tickets; favourites, turning a course
 * on or off, duplicating and deleting it.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_list {

    /** User preference holding the favourite courses (comma-separated ids). */
    const FAVOURITES = 'local_oriskill_favcourses';

    /** Columns the list can be sorted by. */
    const SORTS = ['name', 'members', 'sales', 'revenue'];

    /**
     * The courses of a trainer with their figures, favourites first.
     *
     * @param \stdClass $creator Trainer.
     * @param array $filters q (name), state (on, off), favourites (bool), sort (SORTS), dir (asc, desc).
     * @return array courseid => row {course, product..., modules, activities, members, latest (users), groups, sales,
     *               revenue (currency => amount), tickets, favourite}
     */
    public static function rows(\stdClass $creator, array $filters = []): array {
        global $DB;

        $rows = seller_service::priced_courses($creator);
        if (!$rows) {
            return [];
        }
        $favourites = self::favourites();
        $members = seller_service::learner_counts($creator, array_keys($rows));
        $sales = self::sales($creator, array_keys($rows));
        [$insql, $params] = $DB->get_in_or_equal(array_keys($rows));
        $groups = $DB->get_records_sql_menu("SELECT courseid, COUNT(1) FROM {groups} WHERE courseid $insql GROUP BY courseid",
            $params);
        $tickets = $DB->get_records_sql_menu("SELECT courseid, COUNT(1) FROM {" . ticket_service::TABLE . "}
                                               WHERE status = 'open' AND courseid $insql GROUP BY courseid", $params);
        $comments = comment_service::pending($creator, true);
        $q = \core_text::strtolower(trim($filters['q'] ?? ''));
        foreach ($rows as $courseid => $row) {
            $on = (bool) $row->course->visible;
            if (($q !== '' && !str_contains(\core_text::strtolower($row->course->fullname . ' ' . $row->course->shortname), $q))
                    || (($filters['state'] ?? '') === 'on' && !$on) || (($filters['state'] ?? '') === 'off' && $on)
                    || (!empty($filters['favourites']) && !in_array($courseid, $favourites))) {
                unset($rows[$courseid]);
                continue;
            }
            $modinfo = get_fast_modinfo($row->course);
            $row->modules = count(array_filter($modinfo->get_section_info_all(), fn($s) => $s->section > 0 && !$s->component));
            $row->activities = count(array_filter($modinfo->get_cms(), fn($cm) => $cm->visible && !$cm->deletioninprogress));
            $row->members = (int) ($members[$courseid] ?? 0);
            $row->latest = self::latest_members($creator, $courseid);
            $row->groups = (int) ($groups[$courseid] ?? 0);
            $row->sales = $sales[$courseid]->count ?? 0;
            $row->revenue = $sales[$courseid]->revenue ?? [];
            $row->tickets = (int) ($tickets[$courseid] ?? 0);
            $row->comments = (int) ($comments[$courseid] ?? 0);
            $row->favourite = in_array($courseid, $favourites);
        }
        $sort = in_array($filters['sort'] ?? '', self::SORTS, true) ? $filters['sort'] : 'name';
        $dir = ($filters['dir'] ?? '') === 'desc' ? -1 : 1;
        $currency = stats_service::currency();
        $key = fn($r) => match ($sort) {
            'members' => $r->members,
            'sales' => $r->sales,
            'revenue' => $r->revenue[$currency] ?? 0,
            default => \core_text::strtolower(format_string($r->course->fullname)),
        };
        uasort($rows, fn($a, $b) => ($b->favourite <=> $a->favourite) ?: $dir * ($key($a) <=> $key($b)));
        return $rows;
    }

    /**
     * Sales of each course from the money collected (invoices less credit notes); an order of several courses is
     * shared among them in proportion to their price.
     *
     * @param \stdClass $creator Trainer.
     * @param int[] $courseids Courses.
     * @return array courseid => {count (orders), revenue (currency => amount)}
     */
    public static function sales(\stdClass $creator, array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $records = $DB->get_recordset_sql(
            "SELECT d.id, d.type, d.total, d.currency, d.orderid, oi.courseid, oi.total AS itemtotal, o.total AS ordertotal
               FROM {local_oriskill_invoices} d
               JOIN {local_moderncommerce_orders} o ON o.id = d.orderid
               JOIN {local_moderncommerce_order_items} oi ON oi.orderid = o.id
              WHERE d.creatorid = :creatorid AND d.type IN ('invoice', 'credit') AND oi.courseid $insql",
            ['creatorid' => $creator->id] + $params);
        $sales = [];
        $orders = [];
        foreach ($records as $r) {
            $s = $sales[$r->courseid] ?? (object) ['count' => 0, 'revenue' => []];
            $share = (float) $r->ordertotal > 0 ? (float) $r->itemtotal / (float) $r->ordertotal : 1;
            $s->revenue[$r->currency] = ($s->revenue[$r->currency] ?? 0) + ($r->type === 'credit' ? -1 : 1) * (float) $r->total * $share;
            if ($r->type === 'invoice' && !isset($orders[$r->courseid][$r->orderid])) {
                $orders[$r->courseid][$r->orderid] = true;
                $s->count++;
            }
            $sales[$r->courseid] = $s;
        }
        $records->close();
        return $sales;
    }

    /**
     * The latest learners enrolled in a course (not the team).
     *
     * @param \stdClass $creator Trainer.
     * @param int $courseid Course.
     * @param int $limit How many.
     * @return \stdClass[] Users.
     */
    public static function latest_members(\stdClass $creator, int $courseid, int $limit = 4): array {
        global $DB;

        [$staffsql, $params] = $DB->get_in_or_equal(array_merge([(int) $creator->userid], team_service::member_userids($creator)),
            SQL_PARAMS_NAMED, 'staff', false);
        $fields = \core_user\fields::for_userpic()->get_sql('u', false, '', '', false)->selects;
        return array_values($DB->get_records_sql(
            "SELECT $fields, MAX(ue.timecreated) AS enrolled
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid
              WHERE e.courseid = :courseid AND ue.userid $staffsql AND u.deleted = 0
           GROUP BY $fields
           ORDER BY MAX(ue.timecreated) DESC", ['courseid' => $courseid] + $params, 0, $limit));
    }

    /**
     * Favourite courses of the current user.
     *
     * @return int[]
     */
    public static function favourites(): array {
        return array_values(array_filter(array_map('intval', explode(',', (string) get_user_preferences(self::FAVOURITES, '')))));
    }

    /**
     * Pin a course at the top of the list, or unpin it.
     *
     * @param int $courseid Course.
     */
    public static function toggle_favourite(int $courseid): void {
        $favourites = self::favourites();
        $favourites = in_array($courseid, $favourites) ? array_diff($favourites, [$courseid]) : array_merge($favourites, [$courseid]);
        set_user_preference(self::FAVOURITES, implode(',', $favourites));
    }

    /**
     * A course of the trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param int $courseid Course.
     * @return \stdClass Course.
     */
    public static function require_course(\stdClass $creator, int $courseid): \stdClass {
        $rows = seller_service::priced_courses($creator);
        if (!isset($rows[$courseid])) {
            throw new \moodle_exception('invalidcourseid');
        }
        return $rows[$courseid]->course;
    }

    /**
     * Turn a course on (learners see it) or off.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course.
     * @param bool $on On.
     */
    public static function set_visible(\stdClass $creator, \stdClass $course, bool $on): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        course_change_visibility($course->id, $on);
        log_service::add($creator, 'course_visibility', [], (int) $course->id, null,
            [get_string('courses:state', 'local_oriskill') => [
                get_string($course->visible ? 'courses:on' : 'courses:off', 'local_oriskill'),
                get_string($on ? 'courses:on' : 'courses:off', 'local_oriskill')]]);
    }

    /**
     * Duplicate a course in the trainer's category (turned off), with or without its members.
     *
     * The copy goes through Moodle's backup and restore, run by the site (the trainer's role may not back up and
     * restore courses; that the course is theirs is checked by the caller). The trainer is then a teacher of the copy,
     * as of any course they create.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course.
     * @param bool $withmembers Copy the members, their roles and their progress too.
     * @return int New course id.
     */
    public static function duplicate(\stdClass $creator, \stdClass $course, bool $withmembers): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/enrol/locallib.php');

        $name = get_string('courses:copyof', 'local_oriskill', $course->fullname);
        $shortname = $course->shortname . '-' . get_string('courses:copysuffix', 'local_oriskill');
        for ($i = 2; $DB->record_exists('course', ['shortname' => $shortname]); $i++) {
            $shortname = $course->shortname . '-' . get_string('courses:copysuffix', 'local_oriskill') . $i;
        }
        $settings = ['activities' => 1, 'blocks' => 1, 'filters' => 1, 'users' => (int) $withmembers,
            'enrolments' => \backup::ENROL_WITHUSERS, 'role_assignments' => (int) $withmembers,
            'userscompletion' => (int) $withmembers, 'comments' => 0, 'logs' => 0, 'grade_histories' => 0];
        $adminid = (int) get_admin()->id;

        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE, \backup::INTERACTIVE_NO,
            \backup::MODE_SAMESITE, $adminid);
        foreach ($settings as $setting => $value) {
            if ($bc->get_plan()->setting_exists($setting)) {
                $bc->get_plan()->get_setting($setting)->set_value($value);
            }
        }
        $backupid = $bc->get_backupid();
        $basepath = $bc->get_plan()->get_basepath();
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();
        if (!file_exists($basepath . '/moodle_backup.xml')) {
            $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $basepath);
        }

        $newid = \restore_dbops::create_new_course($name, $shortname, (int) $course->category);
        $rc = new \restore_controller($backupid, $newid, \backup::INTERACTIVE_NO, \backup::MODE_SAMESITE, $adminid,
            \backup::TARGET_NEW_COURSE);
        foreach ($settings as $setting => $value) {
            if ($rc->get_plan()->setting_exists($setting)
                    && $rc->get_plan()->get_setting($setting)->get_status() == \backup_setting::NOT_LOCKED) {
                $rc->get_plan()->get_setting($setting)->set_value($value);
            }
        }
        if (!$rc->execute_precheck() && !empty($rc->get_precheck_results()['errors'])) {
            $errors = implode(' ', $rc->get_precheck_results()['errors']);
            $rc->destroy();
            fulldelete($basepath);
            $file->delete();
            delete_course($newid, false);
            throw new \moodle_exception('backupprecheckerrors', 'webservice', '', $errors);
        }
        $rc->execute_plan();
        $rc->destroy();
        $DB->update_record('course', (object) ['id' => $newid, 'fullname' => $name, 'shortname' => $shortname, 'visible' => 0,
            'visibleold' => 0]);
        fulldelete($basepath);
        $file->delete();
        enrol_try_internal_enrol($newid, (int) $creator->userid, (int) $CFG->creatornewroleid);
        rebuild_course_cache($newid, true);

        log_service::add($creator, 'course_duplicated', ['name' => format_string($course->fullname),
            'members' => get_string($withmembers ? 'yes' : 'no')], $newid);
        return $newid;
    }

    /**
     * Delete a course (its members lose their access).
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course.
     */
    public static function delete(\stdClass $creator, \stdClass $course): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $members = (int) (seller_service::learner_counts($creator, [$course->id])[$course->id] ?? 0);
        if (!delete_course($course, false)) {
            throw new \moodle_exception('cannotdeletecourse');
        }
        fix_course_sortorder();
        log_service::add($creator, 'course_deleted', ['name' => format_string($course->fullname), 'members' => $members]);
    }
}
