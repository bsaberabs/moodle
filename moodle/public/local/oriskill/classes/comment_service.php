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
 * Comments and questions of the learners under the lessons of a trainer's course (turned on course by course), the
 * answers of the team, and what is left to handle.
 *
 * A learner's comment is published at once and waits "to handle" until the team answers it or marks it done; the team
 * can hide a comment from the learners. The team is told of each new comment, the learner of each answer.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comment_service {

    /** Table. */
    const TABLE = 'local_oriskill_comments';

    /** Page of the team. */
    const PAGE = '/local/oriskill/comments.php';

    /** Views of the team page. */
    const VIEWS = ['new', 'done', 'hidden', 'all'];

    /** Comments per page of the team page. */
    const PERPAGE = 20;

    /** Longest comment. */
    const MAXLENGTH = 5000;

    /**
     * Whether the comments are turned on under the lessons of a course.
     *
     * @param int $courseid Course.
     * @return bool
     */
    public static function enabled(int $courseid): bool {
        return (bool) get_config('local_oriskill', 'comments_' . $courseid);
    }

    /**
     * Turn the comments on or off for a course.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course of that trainer.
     * @param bool $on On.
     */
    public static function set_enabled(\stdClass $creator, \stdClass $course, bool $on): void {
        $before = self::enabled((int) $course->id);
        set_config('comments_' . $course->id, $on ? 1 : 0, 'local_oriskill');
        $state = fn($v) => get_string($v ? 'comments:on' : 'comments:off', 'local_oriskill');
        log_service::add($creator, 'comments_switched', [], (int) $course->id, null,
            [get_string('comments:title', 'local_oriskill') => [$state($before), $state($on)]]);
    }

    /**
     * Trainer of a course.
     *
     * @param \stdClass $course Course.
     * @return \stdClass|null
     */
    public static function creator_of(\stdClass $course): ?\stdClass {
        global $DB;

        return $DB->get_record('local_oriskill_creators', ['categoryid' => $course->category, 'status' => 'active']) ?: null;
    }

    /**
     * Role of a user in the team of a trainer allowed to handle the comments, or null.
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid User.
     * @return string|null owner or a collaborator role.
     */
    public static function team_role(\stdClass $creator, int $userid): ?string {
        $space = team_service::spaces_of($userid)[$creator->id] ?? null;
        return $space && team_service::can($space['role'], self::PAGE) ? $space['role'] : null;
    }

    /**
     * Who may see the comments of a lesson and write one: a learner enrolled in the course, or the team.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course.
     * @param int $userid User.
     * @return string|null learner, team, or null.
     */
    public static function access(\stdClass $creator, \stdClass $course, int $userid): ?string {
        if (self::team_role($creator, $userid) !== null) {
            return 'team';
        }
        return is_enrolled(\context_course::instance($course->id), $userid, '', true) ? 'learner' : null;
    }

    /**
     * Comments of a lesson with their answers, oldest first; hidden ones for the team only.
     *
     * @param int $cmid Lesson.
     * @param bool $team The reader is of the team.
     * @return array of comments, each with ->answers
     */
    public static function of_lesson(int $cmid, bool $team): array {
        global $DB;

        $records = $DB->get_records(self::TABLE, ['cmid' => $cmid] + ($team ? [] : ['hidden' => 0]), 'timecreated ASC, id ASC');
        $threads = [];
        foreach ($records as $record) {
            if (!$record->parentid) {
                $record->answers = [];
                $threads[$record->id] = $record;
            }
        }
        foreach ($records as $record) {
            if ($record->parentid && isset($threads[$record->parentid])) {
                $threads[$record->parentid]->answers[] = $record;
            }
        }
        return array_values($threads);
    }

    /**
     * Write a comment under a lesson, or answer one.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course.
     * @param \cm_info $cm Lesson.
     * @param int $userid Author.
     * @param string $message Text.
     * @param int $parentid Comment answered (0: a comment of its own).
     * @return \stdClass Comment.
     */
    public static function add(\stdClass $creator, \stdClass $course, \cm_info $cm, int $userid, string $message,
            int $parentid = 0): \stdClass {
        global $DB;

        $message = trim(\core_text::substr($message, 0, self::MAXLENGTH));
        $access = self::access($creator, $course, $userid);
        if ($message === '' || !$access || !self::enabled((int) $course->id) || (int) $cm->course !== (int) $course->id) {
            throw new \moodle_exception('error:notallowed', 'local_oriskill');
        }
        $parent = null;
        if ($parentid) {
            $parent = $DB->get_record(self::TABLE, ['id' => $parentid, 'cmid' => $cm->id, 'parentid' => 0], '*', MUST_EXIST);
        }
        $team = $access === 'team';
        $now = time();
        $comment = (object) ['creatorid' => $creator->id, 'courseid' => $course->id, 'cmid' => $cm->id, 'userid' => $userid,
            'parentid' => $parentid, 'fromteam' => (int) $team, 'message' => $message, 'status' => $team ? 'done' : 'new',
            'hidden' => 0, 'timecreated' => $now, 'timemodified' => $now];
        $comment->id = $DB->insert_record(self::TABLE, $comment);
        if ($parent) {
            // Answered by the team: handled. A learner adding to the thread brings it back to handle.
            $DB->update_record(self::TABLE, (object) ['id' => $parent->id, 'status' => $team ? 'done' : 'new',
                'timemodified' => $now]);
        }
        if ($team) {
            if ($parent && !$parent->fromteam && (int) $parent->userid !== $userid) {
                self::notify_learner($creator, $course, $cm, $parent, $comment);
            }
        } else {
            self::notify_team($creator, $course, $cm, $comment);
        }
        return $comment;
    }

    /**
     * A comment of the trainer's courses, for the team, or an exception (a coach: only their learners').
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Comment.
     * @return \stdClass
     */
    public static function require_for_team(\stdClass $creator, int $id): \stdClass {
        global $DB, $USER;

        $comment = $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
        $thread = $comment->parentid ? $DB->get_record(self::TABLE, ['id' => $comment->parentid], '*', MUST_EXIST) : $comment;
        if (creator_service::current_role() === 'coach'
                && (team_service::coach_map($creator)[(int) $thread->userid] ?? 0) !== (int) $USER->id) {
            throw new \moodle_exception('error:notallowed', 'local_oriskill');
        }
        return $comment;
    }

    /**
     * Mark a comment handled or to handle.
     *
     * @param \stdClass $comment Comment.
     * @param string $status new or done.
     */
    public static function set_status(\stdClass $comment, string $status): void {
        global $DB;

        $DB->update_record(self::TABLE, (object) ['id' => $comment->id, 'status' => $status === 'done' ? 'done' : 'new',
            'timemodified' => time()]);
    }

    /**
     * Hide a comment from the learners, or show it again. A hidden comment is handled.
     *
     * @param \stdClass $comment Comment.
     * @param bool $hidden Hidden.
     */
    public static function set_hidden(\stdClass $comment, bool $hidden): void {
        global $DB;

        $DB->update_record(self::TABLE, (object) ['id' => $comment->id, 'hidden' => (int) $hidden,
            'status' => $hidden ? 'done' : $comment->status, 'timemodified' => time()]);
    }

    /**
     * Delete a comment and its answers.
     *
     * @param \stdClass $comment Comment.
     */
    public static function delete(\stdClass $comment): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['parentid' => $comment->id]);
        $DB->delete_records(self::TABLE, ['id' => $comment->id]);
    }

    /**
     * SQL restricting comments to those a coach follows (nothing for the other roles).
     *
     * @param \stdClass $creator Trainer.
     * @return array [sql, params]
     */
    private static function coach_sql(\stdClass $creator): array {
        global $DB, $USER;

        if (creator_service::current_role() !== 'coach') {
            return ['', []];
        }
        $learners = array_keys(array_filter(team_service::coach_map($creator), fn($coach) => (int) $coach === (int) $USER->id));
        if (!$learners) {
            return [' AND 1 = 0', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($learners, SQL_PARAMS_NAMED, 'coachee');
        return [" AND c.userid $insql", $params];
    }

    /**
     * Comments of the learners (not the answers) for the team page, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @param string $view new, done, hidden or all.
     * @param int $courseid Only this course (0: all).
     * @param int $page Page.
     * @return array [comments with ->answers, total]
     */
    public static function list(\stdClass $creator, string $view, int $courseid = 0, int $page = 0): array {
        global $DB;

        $where = 'c.creatorid = :creatorid AND c.parentid = 0';
        $params = ['creatorid' => $creator->id];
        if ($view === 'new') {
            $where .= " AND c.status = 'new' AND c.hidden = 0";
        } else if ($view === 'done') {
            $where .= " AND c.status = 'done' AND c.hidden = 0";
        } else if ($view === 'hidden') {
            $where .= ' AND c.hidden = 1';
        }
        if ($courseid) {
            $where .= ' AND c.courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        [$coachsql, $coachparams] = self::coach_sql($creator);
        $where .= $coachsql;
        $params += $coachparams;
        $total = $DB->count_records_sql("SELECT COUNT(1) FROM {" . self::TABLE . "} c WHERE $where", $params);
        $comments = $DB->get_records_sql("SELECT c.* FROM {" . self::TABLE . "} c WHERE $where
                                        ORDER BY c.timecreated DESC, c.id DESC", $params, $page * self::PERPAGE, self::PERPAGE);
        foreach ($comments as $comment) {
            $comment->answers = [];
        }
        if ($comments) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_keys($comments));
            foreach ($DB->get_records_select(self::TABLE, "parentid $insql", $inparams, 'timecreated ASC, id ASC') as $answer) {
                $comments[$answer->parentid]->answers[] = $answer;
            }
        }
        return [array_values($comments), $total];
    }

    /**
     * Comments waiting for the team, in all the courses or per course.
     *
     * @param \stdClass $creator Trainer.
     * @param bool $percourse Per course (courseid => count) rather than the total.
     * @return int|array
     */
    public static function pending(\stdClass $creator, bool $percourse = false): int|array {
        global $DB;

        [$coachsql, $coachparams] = self::coach_sql($creator);
        $sql = "FROM {" . self::TABLE . "} c
               WHERE c.creatorid = :creatorid AND c.parentid = 0 AND c.status = 'new' AND c.hidden = 0 $coachsql";
        $params = ['creatorid' => $creator->id] + $coachparams;
        return $percourse ? array_map('intval', $DB->get_records_sql_menu("SELECT c.courseid, COUNT(1) $sql GROUP BY c.courseid",
            $params)) : $DB->count_records_sql("SELECT COUNT(1) $sql", $params);
    }

    /**
     * Address of a lesson, at its comments.
     *
     * @param int $cmid Lesson.
     * @return moodle_url
     */
    public static function lesson_url(int $cmid): moodle_url {
        $cm = get_fast_modinfo(get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST)->course)->get_cm($cmid);
        return new moodle_url($cm->url ?? new moodle_url('/course/view.php', ['id' => $cm->course]), [], 'oriskill-comments');
    }

    /**
     * The comments under a lesson, for the footer of its page: the threads and a box to write.
     *
     * @param \cm_info $cm Lesson.
     * @return string HTML, empty when the comments are off or the reader may not see them.
     */
    public static function lesson_html(\cm_info $cm): string {
        global $USER;

        $course = $cm->get_course();
        if (!self::enabled((int) $course->id) || !isloggedin() || isguestuser() || !$creator = self::creator_of($course)) {
            return '';
        }
        $access = self::access($creator, $course, (int) $USER->id);
        if (!$access) {
            return '';
        }
        $team = $access === 'team';
        $threads = self::of_lesson((int) $cm->id, $team);
        $post = new moodle_url('/local/oriskill/comment.php');
        $hidden = fn(array $fields) => implode('', array_map(fn($k, $v) => \html_writer::empty_tag('input',
            ['type' => 'hidden', 'name' => $k, 'value' => $v]), array_keys($fields), $fields));
        $authors = [];
        $item = function(\stdClass $c) use (&$authors, $creator, $team, $hidden, $post) {
            $authors[$c->userid] = $authors[$c->userid] ?? \core_user::get_user($c->userid);
            $author = $authors[$c->userid];
            $name = $c->fromteam ? self::team_name($creator, $author) : ($author ? fullname($author) : '?');
            $tools = '';
            if ($team) {
                $tools = \html_writer::tag('form', $hidden(['sesskey' => sesskey(), 'id' => $c->id,
                    'action' => $c->hidden ? 'show' : 'hide']) . \html_writer::tag('button', get_string($c->hidden
                    ? 'comments:show' : 'comments:hide', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-link btn-sm p-0']),
                    ['method' => 'post', 'action' => $post->out(false), 'class' => 'd-inline ms-2']);
            }
            return \html_writer::div(
                \html_writer::div(\html_writer::span(s($name), 'fw-semibold') .
                    ($c->fromteam ? ' ' . \html_writer::span(get_string('comments:team', 'local_oriskill'),
                        'badge bg-primary') : '') .
                    ($c->hidden ? ' ' . \html_writer::span(get_string('comments:hiddenbadge', 'local_oriskill'),
                        'badge bg-secondary') : '') .
                    \html_writer::span(' · ' . userdate($c->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                        'text-muted small') . $tools, 'mb-1') .
                \html_writer::div(nl2br(s($c->message))),
                'oriskill-comment p-3 mb-2 rounded border' . ($c->fromteam ? ' bg-light' : '') . ($c->hidden ? ' opacity-50' : ''));
        };
        $textarea = fn(string $id, string $label, int $rows) => \html_writer::tag('label', $label,
                ['for' => $id, 'class' => 'visually-hidden']) .
            \html_writer::tag('textarea', '', ['name' => 'message', 'id' => $id, 'rows' => $rows, 'required' => 'required',
                'maxlength' => self::MAXLENGTH, 'class' => 'form-control mb-2', 'placeholder' => $label]);

        $html = '';
        foreach ($threads as $thread) {
            $answers = implode('', array_map($item, $thread->answers));
            $reply = \html_writer::tag('details', \html_writer::tag('summary', get_string('comments:reply', 'local_oriskill'),
                    ['class' => 'small text-primary']) .
                \html_writer::tag('form', $hidden(['sesskey' => sesskey(), 'cmid' => $cm->id, 'parentid' => $thread->id,
                    'action' => 'add']) . $textarea('oriskill-reply-' . $thread->id, get_string('comments:reply', 'local_oriskill'), 2) .
                    \html_writer::tag('button', get_string('comments:send', 'local_oriskill'), ['type' => 'submit',
                        'class' => 'btn btn-sm btn-primary']), ['method' => 'post', 'action' => $post->out(false),
                    'class' => 'mt-2']), ['class' => 'ms-4 mb-3']);
            $html .= $item($thread) . \html_writer::div($answers, 'ms-4') . $reply;
        }
        $form = \html_writer::tag('form', $hidden(['sesskey' => sesskey(), 'cmid' => $cm->id, 'action' => 'add']) .
            $textarea('oriskill-comment-new', get_string('comments:placeholder', 'local_oriskill'), 3) .
            \html_writer::tag('button', get_string('comments:send', 'local_oriskill'), ['type' => 'submit',
                'class' => 'btn btn-primary']), ['method' => 'post', 'action' => $post->out(false)]);
        return \html_writer::tag('section',
            \html_writer::tag('h3', get_string('comments:lessontitle', 'local_oriskill', count($threads)), ['class' => 'h5']) .
            ($html ?: \html_writer::tag('p', get_string('comments:first', 'local_oriskill'), ['class' => 'text-muted'])) . $form,
            ['id' => 'oriskill-comments', 'class' => 'oriskill-lesson-comments mt-5 pt-4 border-top']);
    }

    /**
     * Name shown on an answer of the team: the space, and who wrote it when that is someone else.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass|false|null $author Author.
     * @return string
     */
    public static function team_name(\stdClass $creator, $author): string {
        $space = format_string($creator->displayname);
        return $author && fullname($author) !== $space ? $space . ' · ' . fullname($author) : $space;
    }

    /**
     * Tell the team of a new comment.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course.
     * @param \cm_info $cm Lesson.
     * @param \stdClass $comment Comment.
     */
    protected static function notify_team(\stdClass $creator, \stdClass $course, \cm_info $cm, \stdClass $comment): void {
        $learner = \core_user::get_user($comment->userid);
        $a = (object) ['learner' => $learner ? fullname($learner) : '?', 'lesson' => format_string($cm->name),
            'course' => format_string($course->fullname), 'message' => $comment->message,
            'url' => (new moodle_url(self::PAGE, ['courseid' => $course->id]))->out(false)];
        foreach (ticket_service::team_recipients($creator, (int) $comment->userid) as $user) {
            self::mail($creator, $user, get_string('comments:mail_team_subject', 'local_oriskill', $a),
                get_string('comments:mail_team_body', 'local_oriskill', $a));
        }
    }

    /**
     * Tell a learner the team answered their comment.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $course Course.
     * @param \cm_info $cm Lesson.
     * @param \stdClass $parent The learner's comment.
     * @param \stdClass $answer Answer.
     */
    protected static function notify_learner(\stdClass $creator, \stdClass $course, \cm_info $cm, \stdClass $parent,
            \stdClass $answer): void {
        $learner = \core_user::get_user($parent->userid);
        if (!$learner || $learner->deleted || $learner->suspended) {
            return;
        }
        $a = (object) ['firstname' => $learner->firstname, 'lesson' => format_string($cm->name),
            'trainer' => format_string($creator->displayname), 'message' => $answer->message,
            'url' => self::lesson_url((int) $cm->id)->out(false)];
        self::mail($creator, $learner, get_string('comments:mail_learner_subject', 'local_oriskill', $a),
            get_string('comments:mail_learner_body', 'local_oriskill', $a));
    }

    /**
     * Send an e-mail in the trainer's name.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $to Recipient.
     * @param string $subject Subject.
     * @param string $text Text.
     */
    protected static function mail(\stdClass $creator, \stdClass $to, string $subject, string $text): void {
        $from = \core_user::get_noreply_user();
        $from->firstname = format_string($creator->displayname);
        $from->lastname = '';
        $html = preg_replace('~(https?://[^\s<]+)~', '<a href="$1">$1</a>', text_to_html(s($text), false, false, true));
        try {
            email_log::for_creator((int) $creator->id, 'transactional', fn() => email_to_user($to, $from, $subject, $text, $html));
        } catch (\Throwable $e) {
            debugging('Comment e-mail: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
