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
 * Time learners spend in a trainer's course, day by day (to justify a training time, CPF).
 *
 * The course pages send the seconds spent every BEAT seconds and when they are left. The server never credits more
 * than the time since the previous credit, so several tabs open at once do not count twice.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class studytime_service {

    /** Time per learner, course and day. */
    const TABLE = 'local_oriskill_studytime';

    /** Tracking mode per course. */
    const MODES_TABLE = 'local_oriskill_studymodes';

    /**
     * Modes: off; active = while the course tab is shown; continuous = while a course page is open, even in a
     * background tab.
     */
    const MODES = ['off', 'active', 'continuous'];

    /** Mode of a course nothing was chosen for. */
    const DEFAULT_MODE = 'active';

    /** Seconds between two sendings of a page. */
    const BEAT = 60;

    /** Tolerance on a sending (browsers run the timers of background tabs about once a minute only). */
    const GRACE = 60;

    /**
     * Tracking mode of a course.
     *
     * @param int $courseid Course.
     * @return string One of MODES.
     */
    public static function mode(int $courseid): string {
        global $DB;
        $mode = $DB->get_field(self::MODES_TABLE, 'mode', ['courseid' => $courseid]);
        return in_array($mode, self::MODES, true) ? $mode : self::DEFAULT_MODE;
    }

    /**
     * Choose the tracking mode of a course.
     *
     * @param int $courseid Course.
     * @param string $mode One of MODES.
     */
    public static function set_mode(int $courseid, string $mode): void {
        global $DB;

        if (!in_array($mode, self::MODES, true)) {
            throw new \moodle_exception('invalidparameter', 'debug');
        }
        if ($id = $DB->get_field(self::MODES_TABLE, 'id', ['courseid' => $courseid])) {
            $DB->update_record(self::MODES_TABLE, (object) ['id' => $id, 'mode' => $mode, 'timemodified' => time()]);
        } else {
            $DB->insert_record(self::MODES_TABLE, (object) ['courseid' => $courseid, 'mode' => $mode, 'timemodified' => time()]);
        }
    }

    /**
     * Whether the time of the current user is tracked on the current page, and how.
     *
     * Only for learners actually enrolled in a trainer's course, not for its staff nor someone "logged in as".
     *
     * @param \stdClass $course Course of the page.
     * @return string|null Mode, or null when not tracked.
     */
    public static function page_mode(\stdClass $course): ?string {
        global $DB, $USER;

        if ($course->id == SITEID || !isloggedin() || isguestuser() || \core\session\manager::is_loggedinas()) {
            return null;
        }
        $mode = self::mode((int) $course->id);
        if ($mode === 'off' || !$DB->record_exists('local_oriskill_creators', ['categoryid' => $course->category])) {
            return null;
        }
        $context = \context_course::instance($course->id);
        if (!is_enrolled($context, $USER, '', true) || has_capability('moodle/course:update', $context)) {
            return null;
        }
        return $mode;
    }

    /**
     * Credit the time a page reports.
     *
     * @param int $courseid Course.
     * @param int $userid Learner.
     * @param int $seconds Seconds the page says were spent since its previous sending.
     * @param int|null $now Time (tests).
     * @return int Seconds credited.
     */
    public static function beat(int $courseid, int $userid, int $seconds, ?int $now = null): int {
        global $DB;

        $now = $now ?? time();
        $last = (int) $DB->get_field_sql("SELECT MAX(timemodified) FROM {" . self::TABLE . "}
                                           WHERE courseid = ? AND userid = ?", [$courseid, $userid]);
        $credit = min(max(0, $seconds), self::BEAT + self::GRACE, $last ? max(0, $now - $last) : PHP_INT_MAX);
        if ($credit <= 0) {
            return 0;
        }
        $day = (int) date('Ymd', $now);
        $row = $DB->get_record(self::TABLE, ['courseid' => $courseid, 'userid' => $userid, 'day' => $day]);
        if ($row) {
            $DB->update_record(self::TABLE, (object) ['id' => $row->id, 'seconds' => $row->seconds + $credit,
                'timemodified' => $now]);
        } else {
            $DB->insert_record(self::TABLE, (object) ['courseid' => $courseid, 'userid' => $userid, 'day' => $day,
                'seconds' => $credit, 'timecreated' => $now, 'timemodified' => $now]);
        }
        return $credit;
    }

    /**
     * Total time of learners in a course.
     *
     * @param int $courseid Course.
     * @param int[] $userids Learners.
     * @return int[] userid => seconds (learners with no time are missing).
     */
    public static function totals(int $courseid, array $userids): array {
        global $DB;

        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        return array_map('intval', $DB->get_records_sql_menu(
            "SELECT userid, SUM(seconds) FROM {" . self::TABLE . "}
              WHERE courseid = :courseid AND userid $insql
           GROUP BY userid", ['courseid' => $courseid] + $params));
    }

    /**
     * Time of a learner in a course, day by day, oldest first.
     *
     * @param int $courseid Course.
     * @param int $userid Learner.
     * @return array of objects {day (timestamp), seconds, first, last}
     */
    public static function days(int $courseid, int $userid): array {
        global $DB;

        $rows = $DB->get_records(self::TABLE, ['courseid' => $courseid, 'userid' => $userid], 'day ASC');
        return array_values(array_map(fn($row) => (object) [
            'day' => make_timestamp((int) substr($row->day, 0, 4), (int) substr($row->day, 4, 2), (int) substr($row->day, 6, 2)),
            'seconds' => (int) $row->seconds,
            'first' => (int) $row->timecreated,
            'last' => (int) $row->timemodified,
        ], $rows));
    }

    /**
     * A duration as "2 h 05", "12 min" or "0 min", to the nearest minute (like the minutes of the statement).
     *
     * @param int $seconds Seconds.
     * @return string
     */
    public static function format(int $seconds): string {
        $minutes = (int) round($seconds / 60);
        if ($minutes < 60) {
            return get_string('studytime:minutes', 'local_oriskill', $minutes);
        }
        return get_string('studytime:hours', 'local_oriskill', (object) ['h' => intdiv($minutes, 60),
            'm' => sprintf('%02d', $minutes % 60)]);
    }

    /**
     * Script of a course page that sends the time spent.
     *
     * @param int $courseid Course.
     * @param string $mode active or continuous.
     * @return string JavaScript.
     */
    public static function page_script(int $courseid, string $mode): string {
        $config = json_encode(['url' => (new \moodle_url('/local/oriskill/studytime.php'))->out(false),
            'sesskey' => sesskey(), 'courseid' => $courseid, 'continuous' => $mode === 'continuous', 'beat' => self::BEAT]);
        return <<<JS
(function(c) {
    var counted = 0, last = Date.now(), shown = !document.hidden;
    function tick() {
        var now = Date.now();
        if (c.continuous || shown) {
            counted += (now - last) / 1000;
        }
        last = now;
        shown = !document.hidden;
    }
    function send(leaving) {
        tick();
        var seconds = Math.floor(counted);
        if (seconds < 1) {
            return;
        }
        counted -= seconds;
        var data = new FormData();
        data.append('sesskey', c.sesskey);
        data.append('courseid', c.courseid);
        data.append('seconds', seconds);
        if (leaving && navigator.sendBeacon) {
            navigator.sendBeacon(c.url, data);
        } else {
            fetch(c.url, {method: 'POST', body: data, credentials: 'same-origin', keepalive: true}).catch(function() {});
        }
    }
    document.addEventListener('visibilitychange', tick);
    setInterval(function() { send(false); }, c.beat * 1000);
    window.addEventListener('pagehide', function() { send(true); });
})($config);
JS;
    }
}
