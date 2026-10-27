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
 * External API for learner course access list.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns the logged-in learner's accessible courses.
 */
class list_courses extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search text.', VALUE_DEFAULT, ''),
            'categoryid' => new external_value(PARAM_INT, 'Course category ID.', VALUE_DEFAULT, 0),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort key.', VALUE_DEFAULT, 'recent'),
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Courses per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $search Search text.
     * @param int $categoryid Course category ID.
     * @param string $sort Sort key.
     * @param int $page Zero-based page number.
     * @param int $perpage Courses per page.
     * @return array
     */
    public static function execute(
        string $search = '',
        int $categoryid = 0,
        string $sort = 'recent',
        int $page = 0,
        int $perpage = 10
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'categoryid' => $categoryid,
            'sort' => $sort,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        $params = self::normalise_params($params);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        $dashboard = get_dashboard::execute();
        $allcourses = $dashboard['access']['courses'];
        $categories = self::build_categories($allcourses, $params['categoryid']);
        $filtered = self::filter_courses($allcourses, $params);
        self::sort_courses($filtered, $params['sort']);

        $stats = self::build_stats($filtered);
        $total = count($filtered);
        $offset = $params['page'] * $params['perpage'];
        $courses = array_slice($filtered, $offset, $params['perpage']);

        return [
            'success' => true,
            'message' => '',
            'courses' => array_values($courses),
            'stats' => $stats,
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'totalpages' => max(1, (int)ceil($total / $params['perpage'])),
            'hasprevious' => $params['page'] > 0,
            'hasnext' => ($offset + $params['perpage']) < $total,
            'filters' => [
                'search' => $params['search'],
                'categoryid' => $params['categoryid'],
                'sort' => $params['sort'],
            ],
            'categories' => $categories,
            'urls' => [
                'catalog' => $dashboard['urls']['catalog'],
                'courses' => $dashboard['urls']['courses'],
            ],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether courses loaded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'courses' => new external_multiple_structure(self::course_structure()),
            'stats' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total filtered course count.'),
                'completed' => new external_value(PARAM_INT, 'Completed course count.'),
                'ongoing' => new external_value(PARAM_INT, 'Ongoing course count.'),
                'pastdue' => new external_value(PARAM_INT, 'Past due course count.'),
            ]),
            'total' => new external_value(PARAM_INT, 'Total filtered courses.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Courses per page.'),
            'totalpages' => new external_value(PARAM_INT, 'Total pages.'),
            'hasprevious' => new external_value(PARAM_BOOL, 'Whether a previous page exists.'),
            'hasnext' => new external_value(PARAM_BOOL, 'Whether a next page exists.'),
            'filters' => new external_single_structure([
                'search' => new external_value(PARAM_TEXT, 'Search text.'),
                'categoryid' => new external_value(PARAM_INT, 'Category ID.'),
                'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort key.'),
            ]),
            'categories' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Category ID.'),
                'name' => new external_value(PARAM_TEXT, 'Category name.'),
                'selected' => new external_value(PARAM_BOOL, 'Whether selected.'),
            ])),
            'urls' => new external_single_structure([
                'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
                'courses' => new external_value(PARAM_RAW, 'Courses URL.'),
            ]),
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
            'name' => new external_value(PARAM_TEXT, 'Course name.'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name.'),
            'summary' => new external_value(PARAM_RAW, 'Plain course summary.'),
            'categoryid' => new external_value(PARAM_INT, 'Category ID.'),
            'categoryname' => new external_value(PARAM_TEXT, 'Category name.'),
            'imageurl' => new external_value(PARAM_RAW, 'Course image URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether course has an image.'),
            'progress' => new external_value(PARAM_INT, 'Progress percentage.'),
            'progresslabel' => new external_value(PARAM_TEXT, 'Progress label.'),
            'completed' => new external_value(PARAM_BOOL, 'Whether complete.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status key.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'courseurl' => new external_value(PARAM_RAW, 'Course URL.'),
            'modulecount' => new external_value(PARAM_INT, 'Module count.'),
            'enrolleddate' => new external_value(PARAM_INT, 'Enrolment timestamp.'),
            'enrolleddatelabel' => new external_value(PARAM_TEXT, 'Enrolment date label.'),
            'lastaccess' => new external_value(PARAM_INT, 'Last access timestamp.'),
            'lastaccesslabel' => new external_value(PARAM_TEXT, 'Last access label.'),
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Access source.'),
            'sourcelabel' => new external_value(PARAM_TEXT, 'Access source label.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
            'productname' => new external_value(PARAM_TEXT, 'Product name.'),
        ]);
    }

    /**
     * Normalise request parameters.
     *
     * @param array $params Raw parameters.
     * @return array Normalised parameters.
     */
    private static function normalise_params(array $params): array {
        $params['search'] = trim((string)$params['search']);
        $params['categoryid'] = max(0, (int)$params['categoryid']);
        $params['page'] = max(0, (int)$params['page']);
        $params['perpage'] = min(50, max(1, (int)$params['perpage']));

        $allowedsorts = ['recent', 'name', 'name_desc', 'progress', 'progress_asc', 'lastaccess'];
        if (!in_array($params['sort'], $allowedsorts, true)) {
            $params['sort'] = 'recent';
        }

        return $params;
    }

    /**
     * Build category filters from accessible courses.
     *
     * @param array $courses Courses.
     * @param int $selectedid Selected category ID.
     * @return array Category options.
     */
    private static function build_categories(array $courses, int $selectedid): array {
        $categories = [];
        foreach ($courses as $course) {
            $categoryid = (int)$course['categoryid'];
            if ($categoryid <= 0 || isset($categories[$categoryid])) {
                continue;
            }

            $categories[$categoryid] = [
                'id' => $categoryid,
                'name' => (string)$course['categoryname'],
                'selected' => $categoryid === $selectedid,
            ];
        }

        uasort($categories, static function (array $left, array $right): int {
            return strcasecmp($left['name'], $right['name']);
        });

        return array_values($categories);
    }

    /**
     * Filter courses.
     *
     * @param array $courses Courses.
     * @param array $params Normalised params.
     * @return array Filtered courses.
     */
    private static function filter_courses(array $courses, array $params): array {
        $search = \core_text::strtolower($params['search']);

        return array_values(array_filter($courses, static function (array $course) use ($params, $search): bool {
            if ($params['categoryid'] > 0 && (int)$course['categoryid'] !== $params['categoryid']) {
                return false;
            }

            if ($search !== '') {
                $haystack = \core_text::strtolower(
                    $course['name'] . ' ' . $course['shortname'] . ' ' . $course['summary']
                );
                if (strpos($haystack, $search) === false) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Build course metrics for the filtered learner course set.
     *
     * @param array $courses Courses.
     * @return array Course stats.
     */
    private static function build_stats(array $courses): array {
        $stats = [
            'total' => count($courses),
            'completed' => 0,
            'ongoing' => 0,
            'pastdue' => 0,
        ];
        $now = time();

        foreach ($courses as $course) {
            $completed = self::course_is_completed($course);
            $pastdue = !$completed && self::course_is_past_due($course, $now);

            if ($completed) {
                $stats['completed']++;
                continue;
            }

            if ($pastdue) {
                $stats['pastdue']++;
                continue;
            }

            if ((int)($course['progress'] ?? 0) > 0 || (string)($course['status'] ?? '') === 'inprogress') {
                $stats['ongoing']++;
            }
        }

        return $stats;
    }

    /**
     * Whether a learner course is completed.
     *
     * @param array $course Course.
     * @return bool
     */
    private static function course_is_completed(array $course): bool {
        return !empty($course['completed']) || (int)($course['progress'] ?? 0) >= 100;
    }

    /**
     * Whether a learner course has passed its Moodle course end date.
     *
     * @param array $course Course.
     * @param int $now Current timestamp.
     * @return bool
     */
    private static function course_is_past_due(array $course, int $now): bool {
        global $DB;

        $courseid = (int)($course['id'] ?? 0);
        if ($courseid <= 0) {
            return false;
        }

        $duedate = (int)$DB->get_field('course', 'enddate', ['id' => $courseid]);
        return $duedate > 0 && $duedate < $now;
    }

    /**
     * Sort courses in place.
     *
     * @param array $courses Courses.
     * @param string $sort Sort key.
     */
    private static function sort_courses(array &$courses, string $sort): void {
        usort($courses, static function (array $left, array $right) use ($sort): int {
            switch ($sort) {
                case 'name':
                    return strcasecmp($left['name'], $right['name']);
                case 'name_desc':
                    return strcasecmp($right['name'], $left['name']);
                case 'progress':
                    return ((int)$right['progress'] <=> (int)$left['progress']) ?: strcasecmp($left['name'], $right['name']);
                case 'progress_asc':
                    return ((int)$left['progress'] <=> (int)$right['progress']) ?: strcasecmp($left['name'], $right['name']);
                case 'lastaccess':
                    return ((int)$right['lastaccess'] <=> (int)$left['lastaccess']) ?: strcasecmp($left['name'], $right['name']);
                case 'recent':
                default:
                    return ((int)$right['enrolleddate'] <=> (int)$left['enrolleddate'])
                        ?: ((int)$right['lastaccess'] <=> (int)$left['lastaccess'])
                        ?: strcasecmp($left['name'], $right['name']);
            }
        });
    }
}
