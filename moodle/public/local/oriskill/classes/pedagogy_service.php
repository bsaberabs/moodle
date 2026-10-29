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

use moodle_url;

/**
 * Teaching tools of a trainer's course, built from what Moodle already offers: a discussion forum, a satisfaction
 * questionnaire (feedback activity, useful for Qualiopi), a certificate in the trainer's name issued once the
 * course is completed (tool_certificate + mod_coursecertificate), and shortcuts to add quizzes, H5P, assignments
 * and SCORM packages.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pedagogy_service {

    /** Activities shown in the overview, in order: module => Bootstrap icon. */
    const ACTIVITIES = ['quiz' => 'bi-patch-question', 'h5pactivity' => 'bi-puzzle', 'assign' => 'bi-file-earmark-arrow-up',
        'scorm' => 'bi-box-seam', 'forum' => 'bi-chat-dots', 'feedback' => 'bi-emoji-smile', 'coursecertificate' => 'bi-award'];

    /**
     * Activities of a course by module, with what learners did in the forum, questionnaire and certificate.
     *
     * @param \stdClass $course Course.
     * @return array module => {count, cmid (first one), responses (questionnaire answers, certificates issued, posts)}
     */
    public static function overview(\stdClass $course): array {
        global $DB;

        $result = [];
        foreach (array_keys(self::ACTIVITIES) as $module) {
            $cms = $DB->get_records_sql(
                "SELECT cm.id, cm.instance FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module
                  WHERE cm.course = ? AND m.name = ? AND cm.deletioninprogress = 0 ORDER BY cm.id", [$course->id, $module]);
            $instances = array_column($cms, 'instance');
            $responses = 0;
            if ($instances && $module === 'feedback') {
                [$in, $params] = $DB->get_in_or_equal($instances);
                $responses = $DB->count_records_select('feedback_completed', "feedback $in", $params);
            } else if ($instances && $module === 'coursecertificate') {
                $responses = $DB->count_records('tool_certificate_issues', ['courseid' => $course->id]);
            } else if ($instances && $module === 'forum') {
                [$in, $params] = $DB->get_in_or_equal($instances);
                $responses = $DB->count_records_sql("SELECT COUNT(p.id) FROM {forum_posts} p
                    JOIN {forum_discussions} d ON d.id = p.discussion WHERE d.forum $in", $params);
            }
            $result[$module] = (object) ['count' => count($cms), 'cmid' => $cms ? (int) reset($cms)->id : 0,
                'responses' => $responses];
        }
        return $result;
    }

    /**
     * Address to add an activity of a module to the course (Moodle's activity form).
     *
     * @param \stdClass $course Course.
     * @param string $module Module.
     * @return moodle_url
     */
    public static function add_url(\stdClass $course, string $module): moodle_url {
        return new moodle_url('/course/modedit.php', ['add' => $module, 'course' => $course->id, 'section' => 1,
            'return' => 0]);
    }

    /**
     * Add a discussion forum for the learners, at the top of the course.
     *
     * @param \stdClass $course Course.
     * @return int Course module id.
     */
    public static function add_forum(\stdClass $course): int {
        return self::create($course, 'forum', 0, [
            'name' => get_string('tools:forumname', 'local_oriskill'),
            'intro' => get_string('tools:forumintro', 'local_oriskill'),
            'type' => 'general',
            'forcesubscribe' => 0,
        ]);
    }

    /**
     * Add a satisfaction questionnaire at the end of the course: four questions rated 1 to 5 and a comment.
     *
     * @param \stdClass $course Course.
     * @return int Course module id.
     */
    public static function add_feedback(\stdClass $course): int {
        global $DB;

        $cmid = self::create($course, 'feedback', self::last_section($course), [
            'name' => get_string('tools:feedbackname', 'local_oriskill'),
            'intro' => get_string('tools:feedbackintro', 'local_oriskill'),
            'anonymous' => 1,
            'multiple_submit' => 0,
            'autonumbering' => 1,
            'completionsubmit' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'page_after_submit' => get_string('tools:feedbackthanks', 'local_oriskill'),
            'page_after_submitformat' => FORMAT_HTML,
            'page_after_submit_editor' => ['text' => get_string('tools:feedbackthanks', 'local_oriskill'),
                'format' => FORMAT_HTML, 'itemid' => 0],
            'email_notification' => 0,
        ]);
        $feedbackid = (int) $DB->get_field('course_modules', 'instance', ['id' => $cmid]);
        $scale = [];
        foreach ([1, 2, 3, 4, 5] as $value) {
            $scale[] = $value . '####' . get_string('tools:scale' . $value, 'local_oriskill');
        }
        $position = 0;
        foreach (['content', 'trainer', 'platform', 'goals', 'recommend'] as $question) {
            $DB->insert_record('feedback_item', (object) ['feedback' => $feedbackid, 'template' => 0,
                'name' => get_string('tools:q_' . $question, 'local_oriskill'), 'label' => $question,
                'presentation' => 'r>>>>>' . implode('|', $scale) . '<<<<<1', 'typ' => 'multichoicerated', 'hasvalue' => 1,
                'position' => ++$position, 'required' => 1, 'dependitem' => 0, 'dependvalue' => '', 'options' => 'h']);
        }
        $DB->insert_record('feedback_item', (object) ['feedback' => $feedbackid, 'template' => 0,
            'name' => get_string('tools:q_comment', 'local_oriskill'), 'label' => 'comment', 'presentation' => '60|5',
            'typ' => 'textarea', 'hasvalue' => 1, 'position' => ++$position, 'required' => 0, 'dependitem' => 0,
            'dependvalue' => '', 'options' => '']);
        return $cmid;
    }

    /**
     * Add a certificate in the trainer's name, issued automatically once every activity with completion tracking is
     * completed (the certificate opens only then).
     *
     * @param \stdClass $course Course.
     * @param \stdClass $creator Trainer.
     * @return int Course module id.
     */
    public static function add_certificate(\stdClass $course, \stdClass $creator): int {
        global $DB;

        // Open (and so issued) when every tracked activity is completed; without any, it would go to everyone.
        $conditions = [];
        foreach (get_fast_modinfo($course)->get_cms() as $cm) {
            if ($cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress) {
                $conditions[] = ['type' => 'completion', 'cm' => (int) $cm->id, 'e' => COMPLETION_COMPLETE];
            }
        }
        if (!$conditions) {
            throw new \moodle_exception('tools:nocompletion', 'local_oriskill');
        }
        $availability = json_encode(['op' => '&', 'c' => $conditions, 'showc' => array_fill(0, count($conditions), true)]);
        $template = self::certificate_template($course, $creator);
        return self::create($course, 'coursecertificate', self::last_section($course), [
            'name' => get_string('tools:certname', 'local_oriskill'),
            'intro' => get_string('tools:certintro', 'local_oriskill'),
            'template' => $template->get_id(),
            'automaticsend' => 1,
            'expirydatetype' => 0,
            'expirydateoffset' => 0,
            'availability' => $availability,
        ]);
    }

    /**
     * Certificate template of a course: the learner's name, the course, the trainer, the date and a check code.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $creator Trainer.
     * @return \tool_certificate\template
     */
    private static function certificate_template(\stdClass $course, \stdClass $creator): \tool_certificate\template {
        $template = \tool_certificate\template::create((object) [
            'name' => get_string('tools:certtemplate', 'local_oriskill', format_string($course->fullname)),
            'shared' => 0,
            'contextid' => \context_coursecat::instance($course->category)->id,
        ]);
        $page = $template->new_page();
        $page->save((object) []);
        $add = function(string $type, array $data) use ($page) {
            $element = \tool_certificate\element::instance(0, (object) ['element' => $type, 'pageid' => $page->get_id()]);
            $element->save_form_data((object) ($data + ['name' => $type, 'font' => 'freesans', 'fontsize' => 14,
                'colour' => '#111827', 'posx' => 148, 'width' => 0, 'refpoint' => 1]));
        };
        $add('border', ['width' => 2, 'colour' => '#7c3aed', 'posx' => 0, 'posy' => 0, 'refpoint' => 0]);
        $add('text', ['text' => get_string('tools:certtitle', 'local_oriskill'), 'posy' => 35, 'fontsize' => 30,
            'font' => 'freesansb', 'colour' => '#7c3aed']);
        $add('text', ['text' => get_string('tools:certawarded', 'local_oriskill'), 'posy' => 62]);
        $add('userfield', ['userfield' => 'fullname', 'posy' => 75, 'fontsize' => 26, 'font' => 'freesansb']);
        $add('text', ['text' => get_string('tools:certfor', 'local_oriskill'), 'posy' => 100]);
        $add('text', ['text' => format_string($course->fullname), 'posy' => 112, 'fontsize' => 20, 'font' => 'freesansb',
            'width' => 240]);
        $add('text', ['text' => get_string('tools:certby', 'local_oriskill', format_string($creator->displayname)),
            'posy' => 150, 'fontsize' => 13]);
        $add('date', ['dateitem' => -1, 'dateformat' => 'strftimedate', 'posy' => 162, 'fontsize' => 12]);
        $add('code', ['display' => 1, 'posy' => 185, 'fontsize' => 9, 'colour' => '#6b7280']);
        return $template;
    }

    /**
     * Create an activity in the course (as the current user, with Moodle's own checks).
     *
     * @param \stdClass $course Course.
     * @param string $module Module.
     * @param int $section Section number.
     * @param array $fields Module fields (name, intro...).
     * @return int Course module id.
     */
    private static function create(\stdClass $course, string $module, int $section, array $fields): int {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $intro = $fields['intro'] ?? '';
        unset($fields['intro']);
        $info = (object) ($fields + [
            'modulename' => $module,
            'course' => $course->id,
            'section' => $section,
            'visible' => 1,
            'introeditor' => ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => 0],
            'cmidnumber' => '',
        ]);
        return (int) create_module($info)->coursemodule;
    }

    /**
     * Number of the course's last section.
     *
     * @param \stdClass $course Course.
     * @return int
     */
    private static function last_section(\stdClass $course): int {
        global $DB;
        return max(1, (int) $DB->get_field_sql('SELECT MAX(section) FROM {course_sections} WHERE course = ?', [$course->id]));
    }
}
