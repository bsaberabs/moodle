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
 * Frequently asked questions of a course, in the trainer's order: shown on the public course page and at the bottom of the
 * course for its learners.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faq_service {

    /** Table. */
    const TABLE = 'local_oriskill_faq';

    /** Most questions per course. */
    const MAX = 50;

    /**
     * Questions of a course, in order.
     *
     * @param int $courseid Course.
     * @return array of {question, answer}
     */
    public static function items(int $courseid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, ['courseid' => $courseid], 'sortorder, id'));
    }

    /**
     * Replace the questions of a course of the trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course of that trainer.
     * @param array $rows of ['question', 'answer'], in order; empty questions are dropped.
     * @return int Questions kept.
     */
    public static function save(\stdClass $creator, \stdClass $course, array $rows): int {
        global $DB;

        $before = count(self::items((int) $course->id));
        $DB->delete_records(self::TABLE, ['courseid' => $course->id]);
        $order = 0;
        foreach (array_slice($rows, 0, self::MAX) as $row) {
            $question = \core_text::substr(trim(clean_param((string) ($row['question'] ?? ''), PARAM_TEXT)), 0, 255);
            $answer = trim(clean_param((string) ($row['answer'] ?? ''), PARAM_TEXT));
            if ($question === '' || $answer === '') {
                continue;
            }
            $DB->insert_record(self::TABLE, (object) ['creatorid' => $creator->id, 'courseid' => $course->id,
                'question' => $question, 'answer' => \core_text::substr($answer, 0, 5000), 'sortorder' => $order++,
                'timemodified' => time()]);
        }
        log_service::add($creator, 'faq_saved', [], (int) $course->id, null,
            [get_string('faq:title', 'local_oriskill') => [(string) $before, (string) $order]]);
        return $order;
    }

    /**
     * The questions, as a list that opens question by question.
     *
     * @param int $courseid Course.
     * @param string $heading Heading above (empty: none).
     * @return string HTML, empty when the course has none.
     */
    public static function html(int $courseid, string $heading = ''): string {
        $items = self::items($courseid);
        if (!$items) {
            return '';
        }
        $list = '';
        foreach ($items as $item) {
            $list .= \html_writer::tag('details', \html_writer::tag('summary', s($item->question), ['class' => 'fw-semibold py-2']) .
                \html_writer::div(nl2br(s($item->answer)), 'pb-3'), ['class' => 'border-bottom']);
        }
        return \html_writer::tag('section', ($heading !== '' ? \html_writer::tag('h2', $heading, ['class' => 'h4']) : '') . $list,
            ['class' => 'oriskill-faq mt-4', 'id' => 'oriskill-faq']);
    }
}
