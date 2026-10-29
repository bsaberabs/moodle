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

namespace availability_enroldays;

/**
 * Condition: a number of days have passed since the user enrolled in the course (content released over time).
 *
 * The enrolment date is the start of the user's earliest active enrolment in the course.
 *
 * @package    availability_enroldays
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {

    /** @var int Days after the enrolment. */
    protected $days;

    /** @var array Enrolment start per "courseid-userid", for the current request. */
    protected static $starts = [];

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode.
     * @throws \coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        if (!isset($structure->d) || !is_int($structure->d) || $structure->d < 0) {
            throw new \coding_exception('Missing or invalid ->d for enroldays condition');
        }
        $this->days = $structure->d;
    }

    /**
     * Saves the condition.
     *
     * @return \stdClass
     */
    public function save() {
        return self::get_json($this->days);
    }

    /**
     * JSON structure of a condition.
     *
     * @param int $days Days after the enrolment.
     * @return \stdClass
     */
    public static function get_json(int $days): \stdClass {
        return (object) ['type' => 'enroldays', 'd' => $days];
    }

    /**
     * Days after the enrolment.
     *
     * @return int
     */
    public function get_days(): int {
        return $this->days;
    }

    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $start = self::enrolment_start($info->get_course()->id, $userid);
        $allow = $start !== null && \core\di::get(\core\clock::class)->time() >= $start + $this->days * DAYSECS;
        return $not ? !$allow : $allow;
    }

    public function get_description($full, $not, \core_availability\info $info) {
        global $USER;

        $start = self::enrolment_start($info->get_course()->id, $USER->id);
        if ($start !== null && !$not) {
            return get_string('full_after_date', 'availability_enroldays', (object) ['days' => $this->days,
                'date' => userdate($start + $this->days * DAYSECS, get_string('strftimedate', 'langconfig'))]);
        }
        return get_string($not ? 'full_before' : 'full_after', 'availability_enroldays', $this->days);
    }

    protected function get_debug_string() {
        return 'd:' . $this->days;
    }

    /**
     * Start of the user's earliest active enrolment in a course.
     *
     * @param int $courseid Course.
     * @param int $userid User.
     * @return int|null Timestamp, or null when the user is not enrolled.
     */
    public static function enrolment_start(int $courseid, int $userid): ?int {
        global $DB;

        $key = $courseid . '-' . $userid;
        if (!array_key_exists($key, self::$starts)) {
            $start = $DB->get_field_sql(
                "SELECT MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END)
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                  WHERE e.courseid = ? AND ue.userid = ? AND ue.status = ? AND e.status = ?",
                [$courseid, $userid, ENROL_USER_ACTIVE, ENROL_INSTANCE_ENABLED]);
            self::$starts[$key] = $start ? (int) $start : null;
        }
        return self::$starts[$key];
    }

    /**
     * Forget the enrolment dates read during this request (after an enrolment change, and in tests).
     */
    public static function reset_cache(): void {
        self::$starts = [];
    }
}
