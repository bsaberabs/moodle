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
 * Log of a trainer space: who changed what (prices, payment accounts, learners' access, team...).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_service {

    /** Entries per page of the log. */
    const PERPAGE = 50;

    /**
     * Record an action of the current user (or of Oriskill itself when run by a task or a payment notification).
     *
     * @param \stdClass $creator Trainer space.
     * @param string $action Action key (log:<action> language string).
     * @param array $details Values for the description.
     * @param int|null $courseid Course concerned.
     * @param int|null $relateduserid Learner or collaborator concerned.
     */
    public static function add(\stdClass $creator, string $action, array $details = [], ?int $courseid = null,
            ?int $relateduserid = null): void {
        global $DB, $USER;

        $DB->insert_record('local_oriskill_log', (object) [
            'creatorid' => $creator->id,
            'userid' => isloggedin() && !isguestuser() ? (int) $USER->id : 0,
            'action' => $action,
            'courseid' => $courseid,
            'relateduserid' => $relateduserid,
            'details' => $details ? json_encode($details) : null,
            'timecreated' => time(),
        ]);
    }

    /**
     * Entries of a trainer space, newest first.
     *
     * @param \stdClass $creator Trainer space.
     * @param int $page Page, from 0.
     * @return array [entries, total]
     */
    public static function entries(\stdClass $creator, int $page = 0): array {
        global $DB;

        return [
            $DB->get_records('local_oriskill_log', ['creatorid' => $creator->id], 'timecreated DESC, id DESC', '*',
                $page * self::PERPAGE, self::PERPAGE),
            $DB->count_records('local_oriskill_log', ['creatorid' => $creator->id]),
        ];
    }

    /**
     * Readable description of an entry.
     *
     * @param \stdClass $entry Log entry.
     * @return string Plain text.
     */
    public static function describe(\stdClass $entry): string {
        global $DB;

        $a = (object) (json_decode((string) $entry->details, true) ?: []);
        if (!empty($a->role)) {
            $a->role = get_string('role:' . $a->role, 'local_oriskill');
        }
        if (!empty($a->from)) {
            $a->from = get_string('role:' . $a->from, 'local_oriskill');
        }
        $a->course = $entry->courseid
            ? format_string((string) $DB->get_field('course', 'fullname', ['id' => $entry->courseid])) : '';
        $user = $entry->relateduserid ? \core_user::get_user($entry->relateduserid) : null;
        $a->user = $user ? fullname($user) : '';
        $key = 'log:' . $entry->action;
        return get_string_manager()->string_exists($key, 'local_oriskill')
            ? get_string($key, 'local_oriskill', $a) : $entry->action;
    }
}
