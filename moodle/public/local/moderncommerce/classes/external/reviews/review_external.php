<?php
// This file is part of Moodle and is licensed under the
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

/**
 * External APIs consumed by Modern Commerce React course review screens.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\reviews;

use context_course;
use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\review_service;

/**
 * Webservice methods for core Modern Commerce course reviews.
 */
class review_external extends external_api {
    /** @var int Maximum rows returned by paged endpoints. */
    private const MAX_PER_PAGE = 100;

    /**
     * Parameters for get_overview.
     *
     * @return external_function_parameters
     */
    public static function get_overview_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get dashboard summary data.
     *
     * @return array
     */
    public static function get_overview(): array {
        self::require_admin_access();

        return [
            'stats' => self::review_stats(),
            'ratingdist' => self::rating_distribution(),
            'courses' => self::courses_with_reviews('', 0, 10),
            'topreviews' => self::featured_reviews('top'),
            'recentreviews' => self::featured_reviews('recent'),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_overview.
     *
     * @return external_single_structure
     */
    public static function get_overview_returns(): external_single_structure {
        return new external_single_structure([
            'stats' => self::stats_structure(),
            'ratingdist' => new external_multiple_structure(self::rating_distribution_structure()),
            'courses' => new external_multiple_structure(self::course_structure()),
            'topreviews' => new external_multiple_structure(self::review_structure()),
            'recentreviews' => new external_multiple_structure(self::review_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for list_courses.
     *
     * @return external_function_parameters
     */
    public static function list_courses_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Course search text.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * List courses with review statistics.
     *
     * @param string $search Search text.
     * @param int $page Page.
     * @param int $perpage Rows per page.
     * @return array
     */
    public static function list_courses(string $search = '', int $page = 0, int $perpage = 10): array {
        $params = self::validate_parameters(self::list_courses_parameters(), [
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        self::require_admin_access();

        $page = max(0, (int) $params['page']);
        $perpage = self::normalise_perpage((int) $params['perpage']);

        return [
            'items' => self::courses_with_reviews($params['search'], $page, $perpage),
            'total' => self::course_count($params['search']),
            'page' => $page,
            'perpage' => $perpage,
            'stats' => self::review_stats(),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for list_courses.
     *
     * @return external_single_structure
     */
    public static function list_courses_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::course_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching courses.'),
            'page' => new external_value(PARAM_INT, 'Current page.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.'),
            'stats' => self::stats_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for list_reviews.
     *
     * @return external_function_parameters
     */
    public static function list_reviews_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID, or 0 for all courses.', VALUE_DEFAULT, 0),
            'filter' => new external_value(PARAM_ALPHA, 'all, visible, or hidden.', VALUE_DEFAULT, 'all'),
            'search' => new external_value(PARAM_TEXT, 'Review, course, or user search text.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * List reviews for admin moderation.
     *
     * @param int $courseid Course ID.
     * @param string $filter Review visibility filter.
     * @param string $search Search text.
     * @param int $page Page.
     * @param int $perpage Rows per page.
     * @return array
     */
    public static function list_reviews(
        int $courseid = 0,
        string $filter = 'all',
        string $search = '',
        int $page = 0,
        int $perpage = 10
    ): array {
        $params = self::validate_parameters(self::list_reviews_parameters(), [
            'courseid' => $courseid,
            'filter' => $filter,
            'search' => $search,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        self::require_admin_access();

        $params['courseid'] = max(0, (int) $params['courseid']);
        $params['filter'] = self::normalise_choice($params['filter'], ['all', 'visible', 'hidden'], 'all');
        $params['page'] = max(0, (int) $params['page']);
        $params['perpage'] = self::normalise_perpage((int) $params['perpage']);

        if ($params['courseid'] > 0) {
            get_course($params['courseid']);
        }

        [$where, $sqlparams] = self::review_filter_sql($params, true);
        $total = self::review_count($where, $sqlparams);
        $items = self::review_records($where, $sqlparams, $params['page'], $params['perpage']);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'course' => self::course_context_data($params['courseid']),
            'stats' => self::review_stats($params['courseid']),
            'ratingdist' => self::rating_distribution($params['courseid']),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for list_reviews.
     *
     * @return external_single_structure
     */
    public static function list_reviews_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::review_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching reviews.'),
            'page' => new external_value(PARAM_INT, 'Current page.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.'),
            'course' => self::course_context_structure(),
            'stats' => self::stats_structure(),
            'ratingdist' => new external_multiple_structure(self::rating_distribution_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for moderate.
     *
     * @return external_function_parameters
     */
    public static function moderate_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Review ID.', VALUE_REQUIRED),
            'action' => new external_value(PARAM_ALPHA, 'hide, show, or delete.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Hide, show, or delete a review.
     *
     * @param int $id Review ID.
     * @param string $action Action.
     * @return array
     */
    public static function moderate(int $id, string $action): array {
        global $DB;

        $params = self::validate_parameters(self::moderate_parameters(), [
            'id' => $id,
            'action' => $action,
        ]);
        self::require_admin_access();

        $review = $DB->get_record('local_moderncommerce_reviews', ['id' => (int) $params['id']], '*', MUST_EXIST);
        $action = self::normalise_choice($params['action'], ['hide', 'show', 'delete'], '');
        if ($action === '') {
            throw new \invalid_parameter_exception('Invalid review action.');
        }

        if ($action === 'delete') {
            $DB->delete_records('local_moderncommerce_review_rxn', ['reviewid' => $review->id]);
            $DB->delete_records('local_moderncommerce_reviews', ['id' => $review->id]);
            return self::simple_result(true, get_string('reviewdeleted', 'local_moderncommerce'));
        }

        $DB->set_field(
            'local_moderncommerce_reviews',
            'hidden',
            $action === 'hide' ? 1 : 0,
            ['id' => $review->id]
        );

        return self::simple_result(
            true,
            get_string($action === 'hide' ? 'reviewhidden' : 'reviewunhidden', 'local_moderncommerce')
        );
    }

    /**
     * Return structure for moderate.
     *
     * @return external_single_structure
     */
    public static function moderate_returns(): external_single_structure {
        return self::simple_result_structure();
    }

    /**
     * Parameters for get_course_reviews.
     *
     * @return external_function_parameters
     */
    public static function get_course_reviews_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID.', VALUE_REQUIRED),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Get visible course reviews for learner/public React surfaces.
     *
     * @param int $courseid Course ID.
     * @param int $page Page.
     * @param int $perpage Rows per page.
     * @return array
     */
    public static function get_course_reviews(int $courseid, int $page = 0, int $perpage = 10): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::get_course_reviews_parameters(), [
            'courseid' => $courseid,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        [$course] = self::require_public_course_review_access((int) $params['courseid']);

        $page = max(0, (int) $params['page']);
        $perpage = self::normalise_perpage((int) $params['perpage']);
        $where = 'r.courseid = :courseid AND r.hidden = 0';
        $sqlparams = ['courseid' => (int) $course->id];
        $enabled = review_service::reviews_enabled();
        $total = $enabled ? self::review_count($where, $sqlparams) : 0;
        $userid = isloggedin() && !isguestuser() ? (int)$USER->id : 0;

        return [
            'enabled' => $enabled,
            'course' => self::course_context_data((int) $course->id),
            'summary' => self::public_course_summary((int) $course->id),
            'ratingdist' => $enabled ? self::rating_distribution((int) $course->id, false) : self::empty_rating_distribution(),
            'reviews' => $enabled ? self::review_records($where, $sqlparams, $page, $perpage) : [],
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'userhasreviewed' => $userid > 0 && $DB->record_exists('local_moderncommerce_reviews', [
                'courseid' => (int) $course->id,
                'userid' => $userid,
            ]),
            'canreview' => $userid > 0 && review_service::user_can_submit_review((int)$course->id, $userid),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_course_reviews.
     *
     * @return external_single_structure
     */
    public static function get_course_reviews_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Whether course reviews are enabled.'),
            'course' => self::course_context_structure(),
            'summary' => self::public_summary_structure(),
            'ratingdist' => new external_multiple_structure(self::rating_distribution_structure()),
            'reviews' => new external_multiple_structure(self::review_structure()),
            'total' => new external_value(PARAM_INT, 'Total visible reviews.'),
            'page' => new external_value(PARAM_INT, 'Current page.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.'),
            'userhasreviewed' => new external_value(PARAM_BOOL, 'Whether the current user already reviewed the course.'),
            'canreview' => new external_value(PARAM_BOOL, 'Whether the current user can review the course.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for submit_review.
     *
     * @return external_function_parameters
     */
    public static function submit_review_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID.', VALUE_REQUIRED),
            'rating' => new external_value(PARAM_INT, 'Rating from 1 to 5.', VALUE_REQUIRED),
            'comment' => new external_value(PARAM_TEXT, 'Review comment.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Submit a learner review.
     *
     * @param int $courseid Course ID.
     * @param int $rating Rating.
     * @param string $comment Comment.
     * @return array
     */
    public static function submit_review(int $courseid, int $rating, string $comment): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::submit_review_parameters(), [
            'courseid' => $courseid,
            'rating' => $rating,
            'comment' => $comment,
        ]);
        [$course] = self::require_course_access((int) $params['courseid'], 'local/moderncommerce:submitreview');
        if (!review_service::user_can_submit_review((int)$course->id, (int)$USER->id)) {
            return self::simple_result(false, get_string('error:cannotreview', 'local_moderncommerce'));
        }

        if ((int) $params['rating'] < 1 || (int) $params['rating'] > 5) {
            return self::simple_result(false, get_string('error:invalidrating', 'local_moderncommerce'));
        }

        if (trim($params['comment']) === '') {
            return self::simple_result(false, get_string('commentrequired', 'local_moderncommerce'));
        }

        if ($DB->record_exists('local_moderncommerce_reviews', ['courseid' => (int) $course->id, 'userid' => (int) $USER->id])) {
            return self::simple_result(false, get_string('error:reviewexists', 'local_moderncommerce'));
        }

        review_service::upsert_review((int) $course->id, (int) $USER->id, (int) $params['rating'], $params['comment']);

        return self::simple_result(true, get_string('reviewsubmitted', 'local_moderncommerce'));
    }

    /**
     * Return structure for submit_review.
     *
     * @return external_single_structure
     */
    public static function submit_review_returns(): external_single_structure {
        return self::simple_result_structure();
    }

    /**
     * Parameters for set_reaction.
     *
     * @return external_function_parameters
     */
    public static function set_reaction_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reviewid' => new external_value(PARAM_INT, 'Review ID.', VALUE_REQUIRED),
            'reaction' => new external_value(PARAM_INT, '1=like, 2=dislike, 3=love.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Set or toggle the current user reaction.
     *
     * @param int $reviewid Review ID.
     * @param int $reaction Reaction.
     * @return array
     */
    public static function set_reaction(int $reviewid, int $reaction): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::set_reaction_parameters(), [
            'reviewid' => $reviewid,
            'reaction' => $reaction,
        ]);
        if (!in_array((int) $params['reaction'], [1, 2, 3], true)) {
            throw new \invalid_parameter_exception('Invalid reaction type.');
        }

        $review = $DB->get_record('local_moderncommerce_reviews', ['id' => (int) $params['reviewid']], '*', MUST_EXIST);
        if (!empty($review->hidden)) {
            throw new \invalid_parameter_exception('Invalid review.');
        }
        self::require_course_access((int) $review->courseid, 'local/moderncommerce:viewreviews');

        review_service::set_reaction((int) $review->id, (int) $USER->id, (int) $params['reaction']);
        $reactions = review_service::get_reaction_counts((int) $review->id);
        $userreaction = (int) $DB->get_field('local_moderncommerce_review_rxn', 'reaction', [
            'reviewid' => (int) $review->id,
            'userid' => (int) $USER->id,
        ]);

        return [
            'success' => true,
            'message' => get_string('reactionupdated', 'local_moderncommerce'),
            'reactions' => self::format_reactions($reactions),
            'userreaction' => $userreaction,
            'warnings' => [],
        ];
    }

    /**
     * Return structure for set_reaction.
     *
     * @return external_single_structure
     */
    public static function set_reaction_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success flag.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'reactions' => self::reaction_structure(),
            'userreaction' => new external_value(PARAM_INT, 'Current user reaction, or 0.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Require site admin access for cross-course moderation endpoints.
     *
     * @return void
     */
    private static function require_admin_access(): void {
        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managereviews', $context);
    }

    /**
     * Require public course review viewing access without forcing login.
     *
     * @param int $courseid Course ID.
     * @return array Course and context.
     */
    private static function require_public_course_review_access(int $courseid): array {
        global $CFG, $PAGE;

        $course = get_course($courseid);
        $context = context_course::instance($courseid);

        if (isloggedin() && !isguestuser()) {
            self::validate_context($context);
            require_capability('local/moderncommerce:viewreviews', $context);
            return [$course, $context];
        }

        $PAGE->set_context($context);
        $guestuserid = !empty($CFG->siteguest) ? (int)$CFG->siteguest : 0;
        require_capability('local/moderncommerce:viewreviews', $context, $guestuserid);

        return [$course, $context];
    }

    /**
     * Require a course-level capability.
     *
     * @param int $courseid Course ID.
     * @param string $capability Capability.
     * @return array Course and context.
     */
    private static function require_course_access(int $courseid, string $capability): array {
        $course = get_course($courseid);
        require_login($course);
        $context = context_course::instance($courseid);
        self::validate_context($context);
        require_capability($capability, $context);

        return [$course, $context];
    }

    /**
     * Build course filter SQL.
     *
     * @param string $search Search text.
     * @return array SQL where and params.
     */
    private static function course_filter_sql(string $search): array {
        global $DB;

        $where = '1 = 1';
        $params = [];
        $search = trim($search);

        if ($search !== '') {
            $where .= ' AND (' . $DB->sql_like('c.fullname', ':searchfullname', false, false)
                . ' OR ' . $DB->sql_like('c.shortname', ':searchshortname', false, false) . ')';
            $params['searchfullname'] = '%' . $search . '%';
            $params['searchshortname'] = '%' . $search . '%';
        }

        return [$where, $params];
    }

    /**
     * Count courses with reviews.
     *
     * @param string $search Search text.
     * @return int
     */
    private static function course_count(string $search = ''): int {
        global $DB;

        [$where, $params] = self::course_filter_sql($search);
        $sql = "SELECT COUNT(DISTINCT c.id)
                  FROM {course} c
                  JOIN {local_moderncommerce_reviews} r ON r.courseid = c.id
                 WHERE $where";

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Get course statistics rows.
     *
     * @param string $search Search text.
     * @param int $page Page.
     * @param int $perpage Rows per page.
     * @return array
     */
    private static function courses_with_reviews(string $search = '', int $page = 0, int $perpage = 10): array {
        global $DB;

        [$where, $params] = self::course_filter_sql($search);
        $sql = "SELECT c.id,
                       c.fullname,
                       c.shortname,
                       COUNT(r.id) AS reviewcount,
                       AVG(r.rating) AS avgrating,
                       SUM(CASE WHEN r.hidden = 0 THEN 1 ELSE 0 END) AS visiblecount,
                       SUM(CASE WHEN r.hidden = 1 THEN 1 ELSE 0 END) AS hiddencount
                  FROM {course} c
                  JOIN {local_moderncommerce_reviews} r ON r.courseid = c.id
                 WHERE $where
              GROUP BY c.id, c.fullname, c.shortname
              ORDER BY reviewcount DESC, c.fullname ASC";
        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        return array_values(array_map([self::class, 'format_course'], $records));
    }

    /**
     * Build review filter SQL.
     *
     * @param array $params Request params.
     * @param bool $includehidden Whether hidden records may be included.
     * @return array SQL where and params.
     */
    private static function review_filter_sql(array $params, bool $includehidden): array {
        global $DB;

        $conditions = [];
        $sqlparams = [];

        if (!empty($params['courseid'])) {
            $conditions[] = 'r.courseid = :courseid';
            $sqlparams['courseid'] = (int) $params['courseid'];
        }

        if (!$includehidden) {
            $conditions[] = 'r.hidden = 0';
        } else if (($params['filter'] ?? 'all') === 'visible') {
            $conditions[] = 'r.hidden = 0';
        } else if (($params['filter'] ?? 'all') === 'hidden') {
            $conditions[] = 'r.hidden = 1';
        }

        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '('
                . $DB->sql_like('r.comment', ':searchcomment', false, false)
                . ' OR ' . $DB->sql_like('c.fullname', ':searchcourse', false, false)
                . ' OR ' . $DB->sql_like('u.firstname', ':searchfirstname', false, false)
                . ' OR ' . $DB->sql_like('u.lastname', ':searchlastname', false, false)
                . ')';
            $sqlparams['searchcomment'] = '%' . $search . '%';
            $sqlparams['searchcourse'] = '%' . $search . '%';
            $sqlparams['searchfirstname'] = '%' . $search . '%';
            $sqlparams['searchlastname'] = '%' . $search . '%';
        }

        return [empty($conditions) ? '1 = 1' : implode(' AND ', $conditions), $sqlparams];
    }

    /**
     * Count reviews from a where clause.
     *
     * @param string $where SQL where.
     * @param array $params SQL params.
     * @return int
     */
    private static function review_count(string $where, array $params): int {
        global $DB;

        $sql = "SELECT COUNT(1)
                  FROM {local_moderncommerce_reviews} r
                  JOIN {course} c ON c.id = r.courseid
                  JOIN {user} u ON u.id = r.userid
                 WHERE $where";

        return (int) $DB->count_records_sql($sql, $params);
    }

    /**
     * Get review rows from a where clause.
     *
     * @param string $where SQL where.
     * @param array $params SQL params.
     * @param int $page Page.
     * @param int $perpage Rows per page.
     * @return array
     */
    private static function review_records(string $where, array $params, int $page, int $perpage): array {
        global $DB;

        $sql = "SELECT r.*,
                       c.fullname AS coursename,
                       c.shortname AS courseshortname
                  FROM {local_moderncommerce_reviews} r
                  JOIN {course} c ON c.id = r.courseid
                  JOIN {user} u ON u.id = r.userid
                 WHERE $where
              ORDER BY r.timecreated DESC, r.id DESC";
        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        return array_values(array_map([self::class, 'format_review'], $records));
    }

    /**
     * Get featured review rows.
     *
     * @param string $mode top or recent.
     * @return array
     */
    private static function featured_reviews(string $mode): array {
        global $DB;

        $order = $mode === 'top' ? 'r.rating DESC, reactioncount DESC, r.timecreated DESC' : 'r.timecreated DESC';
        $sql = "SELECT r.*,
                       c.fullname AS coursename,
                       c.shortname AS courseshortname,
                       (SELECT COUNT(1)
                          FROM {local_moderncommerce_review_rxn} rx
                         WHERE rx.reviewid = r.id) AS reactioncount
                  FROM {local_moderncommerce_reviews} r
                  JOIN {course} c ON c.id = r.courseid
                 WHERE r.hidden = 0
              ORDER BY $order";
        $records = $DB->get_records_sql($sql, [], 0, 5);

        return array_values(array_map([self::class, 'format_review'], $records));
    }

    /**
     * Get aggregate review stats.
     *
     * @param int $courseid Optional course ID.
     * @return array
     */
    private static function review_stats(int $courseid = 0): array {
        global $DB;

        $where = $courseid > 0 ? 'WHERE courseid = :courseid' : '';
        $params = $courseid > 0 ? ['courseid' => $courseid] : [];
        $stats = $DB->get_record_sql(
            "SELECT COUNT(1) AS totalreviews,
                    SUM(CASE WHEN hidden = 0 THEN 1 ELSE 0 END) AS visiblereviews,
                    SUM(CASE WHEN hidden = 1 THEN 1 ELSE 0 END) AS hiddenreviews,
                    AVG(rating) AS avgrating
               FROM {local_moderncommerce_reviews}
              $where",
            $params
        );

        $reactionsql = "SELECT COUNT(1)
                          FROM {local_moderncommerce_review_rxn} rx
                          JOIN {local_moderncommerce_reviews} r ON r.id = rx.reviewid";
        if ($courseid > 0) {
            $reactionsql .= ' WHERE r.courseid = :courseid';
        }

        $totalcourses = $courseid > 0 ? 1 : (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT courseid) FROM {local_moderncommerce_reviews}"
        );
        $avgrating = $stats && $stats->avgrating !== null ? round((float) $stats->avgrating, 2) : 0.0;

        return [
            'totalreviews' => (int) ($stats->totalreviews ?? 0),
            'visiblereviews' => (int) ($stats->visiblereviews ?? 0),
            'hiddenreviews' => (int) ($stats->hiddenreviews ?? 0),
            'avgrating' => $avgrating,
            'displayavgrating' => number_format($avgrating, 1),
            'totalreactions' => (int) $DB->count_records_sql($reactionsql, $params),
            'totalcourses' => $totalcourses,
        ];
    }

    /**
     * Get rating distribution.
     *
     * @param int $courseid Optional course ID.
     * @param bool $includehidden Include hidden reviews.
     * @return array
     */
    private static function rating_distribution(int $courseid = 0, bool $includehidden = true): array {
        global $DB;

        if (!review_service::reviews_enabled() && !$includehidden) {
            return self::empty_rating_distribution();
        }

        $conditions = [];
        $params = [];
        if ($courseid > 0) {
            $conditions[] = 'courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if (!$includehidden) {
            $conditions[] = 'hidden = 0';
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $records = $DB->get_records_sql(
            "SELECT rating, COUNT(1) AS cnt
               FROM {local_moderncommerce_reviews}
              $where
           GROUP BY rating",
            $params
        );

        $total = 0;
        foreach ($records as $record) {
            $total += (int) $record->cnt;
        }

        $distribution = [];
        for ($stars = 5; $stars >= 1; $stars--) {
            $count = isset($records[$stars]) ? (int) $records[$stars]->cnt : 0;
            $distribution[] = [
                'stars' => $stars,
                'count' => $count,
                'percent' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ];
        }

        return $distribution;
    }

    /**
     * Get public visible summary for one course.
     *
     * @param int $courseid Course ID.
     * @return array
     */
    private static function public_course_summary(int $courseid): array {
        $summary = review_service::get_course_summary($courseid);

        return [
            'reviewcount' => (int) $summary['reviewcount'],
            'avgrating' => (float) $summary['avgrating'],
            'displayavgrating' => number_format((float) $summary['avgrating'], 1),
        ];
    }

    /**
     * Format a course stats record.
     *
     * @param object $course Course record.
     * @return array
     */
    private static function format_course(object $course): array {
        $avgrating = $course->avgrating !== null ? round((float) $course->avgrating, 2) : 0.0;

        return [
            'id' => (int) $course->id,
            'fullname' => format_string($course->fullname),
            'shortname' => format_string($course->shortname ?? ''),
            'reviewcount' => (int) ($course->reviewcount ?? 0),
            'visiblecount' => (int) ($course->visiblecount ?? 0),
            'hiddencount' => (int) ($course->hiddencount ?? 0),
            'avgrating' => $avgrating,
            'displayavgrating' => number_format($avgrating, 1),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'viewurl' => (new \moodle_url('/local/moderncommerce/course_details.php', ['id' => $course->id]))->out(false),
            'manageurl' => (new \moodle_url('/local/moderncommerce/admin/course_review_moderation.php', [
                'courseid' => $course->id,
            ]))->out(false),
        ];
    }

    /**
     * Format a review record.
     *
     * @param object $review Review record.
     * @return array
     */
    private static function format_review(object $review): array {
        global $DB, $PAGE, $USER;

        $user = $DB->get_record(
            'user',
            ['id' => (int) $review->userid],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, picture, imagealt, email'
        );
        $username = $user ? fullname($user) : get_string('unknownuser');
        $userimage = '';
        if ($user) {
            $picture = new \user_picture($user);
            $picture->size = 40;
            $userimage = $picture->get_url($PAGE)->out(false);
        }

        $reactions = review_service::get_reaction_counts((int) $review->id);
        $userreaction = (int) $DB->get_field('local_moderncommerce_review_rxn', 'reaction', [
            'reviewid' => (int) $review->id,
            'userid' => (int) $USER->id,
        ]);
        $coursename = isset($review->coursename) ? format_string($review->coursename) : '';

        return [
            'id' => (int) $review->id,
            'courseid' => (int) $review->courseid,
            'coursename' => $coursename,
            'userid' => (int) $review->userid,
            'username' => $username,
            'userimage' => $userimage,
            'rating' => (int) $review->rating,
            'displayrating' => number_format((float) $review->rating, 1),
            'ratingclass' => self::rating_class((int) $review->rating),
            'comment' => (string) $review->comment,
            'timecreated' => (int) $review->timecreated,
            'timemodified' => (int) $review->timemodified,
            'timeformatted' => userdate((int) $review->timecreated, get_string('strftimedatetime', 'langconfig')),
            'hidden' => !empty($review->hidden),
            'likes' => (int) ($reactions[1] ?? 0),
            'dislikes' => (int) ($reactions[2] ?? 0),
            'loves' => (int) ($reactions[3] ?? 0),
            'userreaction' => $userreaction,
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $review->courseid]))->out(false),
            'reviewsurl' => (new \moodle_url('/local/moderncommerce/admin/course_review_moderation.php', [
                'courseid' => $review->courseid,
            ]))->out(false),
        ];
    }

    /**
     * Get course context data.
     *
     * @param int $courseid Course ID, or 0.
     * @return array
     */
    private static function course_context_data(int $courseid): array {
        if ($courseid <= 0) {
            return [
                'id' => 0,
                'fullname' => '',
                'shortname' => '',
                'courseurl' => '',
                'viewurl' => '',
                'manageurl' => '',
            ];
        }

        $course = get_course($courseid);
        return [
            'id' => (int) $course->id,
            'fullname' => format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'viewurl' => (new \moodle_url('/local/moderncommerce/course_details.php', ['id' => $course->id]))->out(false),
            'manageurl' => (new \moodle_url('/local/moderncommerce/admin/course_review_moderation.php', [
                'courseid' => $course->id,
            ]))->out(false),
        ];
    }

    /**
     * Format reaction counts.
     *
     * @param array $reactions Reaction count map.
     * @return array
     */
    private static function format_reactions(array $reactions): array {
        return [
            'likes' => (int) ($reactions[1] ?? 0),
            'dislikes' => (int) ($reactions[2] ?? 0),
            'loves' => (int) ($reactions[3] ?? 0),
        ];
    }

    /**
     * Build a simple result.
     *
     * @param bool $success Success flag.
     * @param string $message Result message.
     * @return array
     */
    private static function simple_result(bool $success, string $message): array {
        return [
            'success' => $success,
            'message' => $message,
            'warnings' => [],
        ];
    }

    /**
     * Normalise page size.
     *
     * @param int $perpage Raw page size.
     * @return int
     */
    private static function normalise_perpage(int $perpage): int {
        return max(1, min(self::MAX_PER_PAGE, $perpage));
    }

    /**
     * Normalise a choice value.
     *
     * @param string $value Value.
     * @param array $allowed Allowed values.
     * @param string $default Default.
     * @return string
     */
    private static function normalise_choice(string $value, array $allowed, string $default): string {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * Rating class for UI badges.
     *
     * @param int $rating Rating.
     * @return string
     */
    private static function rating_class(int $rating): string {
        if ($rating >= 4) {
            return 'high';
        }
        if ($rating >= 3) {
            return 'good';
        }
        if ($rating >= 2) {
            return 'mid';
        }
        return 'poor';
    }

    /**
     * Empty 5-to-1 star distribution.
     *
     * @return array Distribution rows.
     */
    private static function empty_rating_distribution(): array {
        $distribution = [];
        for ($stars = 5; $stars >= 1; $stars--) {
            $distribution[] = [
                'stars' => $stars,
                'count' => 0,
                'percent' => 0,
            ];
        }

        return $distribution;
    }

    /**
     * Generic success result structure.
     *
     * @return external_single_structure
     */
    private static function simple_result_structure(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success flag.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Course structure.
     *
     * @return external_single_structure
     */
    private static function course_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Course full name.'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name.'),
            'reviewcount' => new external_value(PARAM_INT, 'Total reviews.'),
            'visiblecount' => new external_value(PARAM_INT, 'Visible reviews.'),
            'hiddencount' => new external_value(PARAM_INT, 'Hidden reviews.'),
            'avgrating' => new external_value(PARAM_FLOAT, 'Average rating.'),
            'displayavgrating' => new external_value(PARAM_TEXT, 'Formatted average rating.'),
            'courseurl' => new external_value(PARAM_URL, 'Moodle course URL.'),
            'viewurl' => new external_value(PARAM_URL, 'Public course reviews URL.'),
            'manageurl' => new external_value(PARAM_URL, 'Modern Commerce moderation URL.'),
        ]);
    }

    /**
     * Course context structure.
     *
     * @return external_single_structure
     */
    private static function course_context_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Course full name.'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name.'),
            'courseurl' => new external_value(PARAM_URL, 'Moodle course URL.'),
            'viewurl' => new external_value(PARAM_URL, 'Public course reviews URL.'),
            'manageurl' => new external_value(PARAM_URL, 'Modern Commerce moderation URL.'),
        ]);
    }

    /**
     * Review structure.
     *
     * @return external_single_structure
     */
    private static function review_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Review ID.'),
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'coursename' => new external_value(PARAM_TEXT, 'Course name.'),
            'userid' => new external_value(PARAM_INT, 'Reviewer user ID.'),
            'username' => new external_value(PARAM_TEXT, 'Reviewer display name.'),
            'userimage' => new external_value(PARAM_URL, 'Reviewer image URL.'),
            'rating' => new external_value(PARAM_INT, 'Rating.'),
            'displayrating' => new external_value(PARAM_TEXT, 'Formatted rating.'),
            'ratingclass' => new external_value(PARAM_ALPHA, 'Rating display class.'),
            'comment' => new external_value(PARAM_TEXT, 'Review comment.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
            'timeformatted' => new external_value(PARAM_TEXT, 'Formatted created date.'),
            'hidden' => new external_value(PARAM_BOOL, 'Hidden flag.'),
            'likes' => new external_value(PARAM_INT, 'Like count.'),
            'dislikes' => new external_value(PARAM_INT, 'Dislike count.'),
            'loves' => new external_value(PARAM_INT, 'Love count.'),
            'userreaction' => new external_value(PARAM_INT, 'Current user reaction, or 0.'),
            'courseurl' => new external_value(PARAM_URL, 'Moodle course URL.'),
            'reviewsurl' => new external_value(PARAM_URL, 'Course review moderation URL.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'totalreviews' => new external_value(PARAM_INT, 'Total reviews.'),
            'visiblereviews' => new external_value(PARAM_INT, 'Visible reviews.'),
            'hiddenreviews' => new external_value(PARAM_INT, 'Hidden reviews.'),
            'avgrating' => new external_value(PARAM_FLOAT, 'Average rating.'),
            'displayavgrating' => new external_value(PARAM_TEXT, 'Formatted average rating.'),
            'totalreactions' => new external_value(PARAM_INT, 'Total reactions.'),
            'totalcourses' => new external_value(PARAM_INT, 'Reviewed courses.'),
        ]);
    }

    /**
     * Rating distribution structure.
     *
     * @return external_single_structure
     */
    private static function rating_distribution_structure(): external_single_structure {
        return new external_single_structure([
            'stars' => new external_value(PARAM_INT, 'Rating stars.'),
            'count' => new external_value(PARAM_INT, 'Review count.'),
            'percent' => new external_value(PARAM_INT, 'Percentage.'),
        ]);
    }

    /**
     * Public course summary structure.
     *
     * @return external_single_structure
     */
    private static function public_summary_structure(): external_single_structure {
        return new external_single_structure([
            'reviewcount' => new external_value(PARAM_INT, 'Visible review count.'),
            'avgrating' => new external_value(PARAM_FLOAT, 'Average visible rating.'),
            'displayavgrating' => new external_value(PARAM_TEXT, 'Formatted average visible rating.'),
        ]);
    }

    /**
     * Reaction structure.
     *
     * @return external_single_structure
     */
    private static function reaction_structure(): external_single_structure {
        return new external_single_structure([
            'likes' => new external_value(PARAM_INT, 'Like count.'),
            'dislikes' => new external_value(PARAM_INT, 'Dislike count.'),
            'loves' => new external_value(PARAM_INT, 'Love count.'),
        ]);
    }
}
