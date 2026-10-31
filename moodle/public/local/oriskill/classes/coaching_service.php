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
 * Coaching of a trainer's learners: credits (bought with an offer of an order form, or given by the team), spent by the
 * coaching sessions the team records; and the assignments waiting for a correction (a coach: their own learners).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coaching_service {

    /** Movements of credits. */
    const TABLE = 'local_oriskill_credits';

    /**
     * Credits left of the learners of a trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid One learner (0: all).
     * @return array userid => credits
     */
    public static function balances(\stdClass $creator, int $userid = 0): array {
        global $DB;

        return array_map('intval', $DB->get_records_sql_menu("SELECT userid, SUM(delta) FROM {" . self::TABLE . "}
                                                               WHERE creatorid = ?" . ($userid ? ' AND userid = ?' : '') . "
                                                            GROUP BY userid", array_filter([$creator->id, $userid])));
    }

    /**
     * History of the credits of a learner, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid Learner.
     * @return array
     */
    public static function history(\stdClass $creator, int $userid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['creatorid' => $creator->id, 'userid' => $userid], 'timecreated DESC, id DESC');
    }

    /**
     * Give credits (positive) or record a session that spends some (negative).
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid Learner.
     * @param int $delta Credits added or spent.
     * @param string $note What it is for (a session: date and subject).
     * @param int $byuserid Who records it (0: an order).
     * @param int $orderid Order that gave them (0: none).
     */
    public static function add(\stdClass $creator, int $userid, int $delta, string $note, int $byuserid, int $orderid = 0): void {
        global $DB;

        if (!$delta) {
            return;
        }
        if ($delta < 0 && (self::balances($creator, $userid)[$userid] ?? 0) + $delta < 0) {
            throw new \moodle_exception('coaching:notenough', 'local_oriskill');
        }
        $DB->insert_record(self::TABLE, (object) ['creatorid' => $creator->id, 'userid' => $userid, 'delta' => $delta,
            'note' => \core_text::substr(trim(clean_param($note, PARAM_TEXT)), 0, 255), 'byuserid' => $byuserid,
            'orderid' => $orderid, 'timecreated' => time()]);
        log_service::add($creator, $delta > 0 ? 'credits_given' : 'credits_spent', ['count' => abs($delta)], null, $userid);
    }

    /**
     * A paid order of an order form offer that comes with coaching credits: the buyer gets them, once.
     *
     * @param int $orderid Order.
     */
    public static function order_paid(int $orderid): void {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        $notes = $order ? (json_decode((string) $order->notes, true) ?: []) : [];
        if (empty($notes['offer']) || $DB->record_exists(self::TABLE, ['orderid' => $orderid])) {
            return;
        }
        $offer = $DB->get_record('local_oriskill_formoffers', ['id' => $notes['offer']]);
        $form = $offer ? $DB->get_record('local_oriskill_orderforms', ['id' => $offer->formid]) : null;
        $creator = $form ? $DB->get_record('local_oriskill_creators', ['id' => $form->creatorid]) : null;
        if ($creator && (int) ($offer->coachingcredits ?? 0) > 0) {
            self::add($creator, (int) $order->userid, (int) $offer->coachingcredits,
                get_string('coaching:fromorder', 'local_oriskill', $order->ordernumber), 0, $orderid);
        }
    }

    /**
     * Assignments handed in and waiting for a grade, in the trainer's courses (a coach: their learners only).
     *
     * @param \stdClass $creator Trainer.
     * @return array of {cmid, course, assignment, userid, learner, submitted}
     */
    public static function to_grade(\stdClass $creator): array {
        global $DB;

        $courses = $DB->get_records('course', ['category' => $creator->categoryid], '', 'id, fullname');
        if (!$courses) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
        $records = $DB->get_records_sql("SELECT s.id, s.userid, s.timemodified, a.id AS assignid, a.name, a.course
                                           FROM {assign_submission} s
                                           JOIN {assign} a ON a.id = s.assignment
                                      LEFT JOIN {assign_grades} g ON g.assignment = s.assignment AND g.userid = s.userid
                                                AND g.attemptnumber = s.attemptnumber
                                          WHERE a.course $insql AND s.status = 'submitted' AND s.latest = 1 AND s.userid > 0
                                                AND (g.id IS NULL OR g.grade IS NULL OR g.grade < 0 OR g.timemodified < s.timemodified)
                                       ORDER BY s.timemodified ASC", $params);
        $visible = team_service::visible_learners($creator);
        $rows = [];
        foreach ($records as $r) {
            if ($visible !== null && !in_array((int) $r->userid, $visible, true)) {
                continue;
            }
            $cm = get_coursemodule_from_instance('assign', $r->assignid, $r->course);
            $user = \core_user::get_user($r->userid);
            if (!$cm || !$user) {
                continue;
            }
            $rows[] = (object) ['cmid' => (int) $cm->id, 'course' => format_string($courses[$r->course]->fullname),
                'assignment' => format_string($r->name), 'userid' => (int) $r->userid, 'learner' => fullname($user),
                'submitted' => (int) $r->timemodified];
        }
        return $rows;
    }
}
