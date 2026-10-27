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

namespace local_moderncommerce\output;

/**
 * Shared renderer data for core Modern Commerce course review admin routes.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_reviews_page {
    /**
     * Require access to review administration.
     *
     * @param \context_system $context System context.
     */
    public static function require_access(\context_system $context): void {
        require_capability('local/moderncommerce:managereviews', $context);
    }

    /**
     * Get the page title.
     *
     * @param string $mode Page mode.
     * @param int $courseid Optional course ID.
     * @return string
     */
    public static function title(string $mode, int $courseid): string {
        if ($mode === 'courses') {
            return get_string('allcourseswithreviews', 'local_moderncommerce');
        }

        if ($mode === 'reviews') {
            if ($courseid > 0) {
                $course = get_course($courseid);
                return get_string('coursereviews', 'local_moderncommerce') . ': ' . format_string($course->fullname);
            }
            return get_string('allreviews', 'local_moderncommerce');
        }

        return get_string('reviewsdashboard', 'local_moderncommerce');
    }

    /**
     * Get the page subtitle.
     *
     * @param string $mode Page mode.
     * @return string
     */
    public static function subtitle(string $mode): string {
        if ($mode === 'courses') {
            return get_string('courseswithreviews', 'local_moderncommerce');
        }

        if ($mode === 'reviews') {
            return get_string('reviewsmoderationdesc', 'local_moderncommerce');
        }

        return get_string('reviewsdashboarddesc', 'local_moderncommerce');
    }

    /**
     * Render the React mount.
     *
     * @param object $output Moodle renderer.
     * @param string $mode Page mode.
     * @param int $courseid Optional course ID.
     * @return string
     */
    public static function content(object $output, string $mode, int $courseid): string {
        $config = json_encode(self::react_config($mode, $courseid), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return $output->render_from_template('local_moderncommerce/admin/course_reviews', [
            'coursereviewsreactconfig' => $config,
        ]);
    }

    /**
     * Build topbar actions.
     *
     * @param string $mode Page mode.
     * @return string
     */
    public static function actions(string $mode): string {
        $actions = [
            [
                'type' => 'button',
                'label' => get_string('refresh'),
                'icon' => 'bi-arrow-clockwise',
                'attributes' => ['id' => 'moderncommerce-course-reviews-refresh'],
            ],
        ];

        return admin_shell::action_group($actions);
    }

    /**
     * Build React config.
     *
     * @param string $mode Page mode.
     * @param int $courseid Optional course ID.
     * @return array
     */
    private static function react_config(string $mode, int $courseid): array {
        return [
            'component' => '@moodle/lms/local_moderncommerce/course_reviews_admin',
            'id' => 'moderncommerce-course-reviews-admin-app',
            'class' => 'local-moderncommerce-course-reviews-admin',
            'props' => [
                'mode' => $mode,
                'courseId' => $courseid,
                'methods' => [
                    'overview' => 'local_moderncommerce_reviews_get_overview',
                    'listCourses' => 'local_moderncommerce_reviews_list_courses',
                    'listReviews' => 'local_moderncommerce_reviews_list_reviews',
                    'reviewAction' => 'local_moderncommerce_reviews_moderate',
                ],
                'urls' => [
                    'overview' => (new \moodle_url('/local/moderncommerce/admin/course_reviews.php'))->out(false),
                    'courses' => (new \moodle_url('/local/moderncommerce/admin/course_review_courses.php'))->out(false),
                    'reviews' => (new \moodle_url('/local/moderncommerce/admin/course_review_moderation.php'))->out(false),
                ],
                'statusOptions' => [
                    ['value' => 'visible', 'label' => get_string('visible', 'local_moderncommerce')],
                    ['value' => 'hidden', 'label' => get_string('hidden', 'local_moderncommerce')],
                ],
                'perPageOptions' => [10, 20, 50, 100],
                'labels' => self::labels(),
            ],
        ];
    }

    /**
     * Labels for React UI.
     *
     * @return array
     */
    private static function labels(): array {
        return [
            'title' => get_string('reviewsdashboard', 'local_moderncommerce'),
            'dashboard' => get_string('reviewsdashboard', 'local_moderncommerce'),
            'courses' => get_string('allcourseswithreviews', 'local_moderncommerce'),
            'moderation' => get_string('reviewsmoderation', 'local_moderncommerce'),
            'stats' => get_string('reviewsummary', 'local_moderncommerce'),
            'totalreviews' => get_string('totalreviews', 'local_moderncommerce'),
            'visiblereviews' => get_string('visiblereviews', 'local_moderncommerce'),
            'hiddenreviews' => get_string('hiddenreviews', 'local_moderncommerce'),
            'avgrating' => get_string('avgrating', 'local_moderncommerce'),
            'totalreactions' => get_string('reactions', 'local_moderncommerce'),
            'courseswithreviews' => get_string('courseswithreviews', 'local_moderncommerce'),
            'ratingdistribution' => get_string('ratingdistribution', 'local_moderncommerce'),
            'topreviews' => get_string('topreviews', 'local_moderncommerce'),
            'recentreviews' => get_string('recentreviews', 'local_moderncommerce'),
            'reviewer' => get_string('reviewer', 'local_moderncommerce'),
            'course' => get_string('course', 'local_moderncommerce'),
            'rating' => get_string('rating', 'local_moderncommerce'),
            'comment' => get_string('comment', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'visible' => get_string('visible', 'local_moderncommerce'),
            'hidden' => get_string('hidden', 'local_moderncommerce'),
            'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchreviews' => get_string('searchreviews', 'local_moderncommerce'),
            'searchcourses' => get_string('searchreviewedcourses', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'page' => get_string('page', 'local_moderncommerce'),
            'next' => get_string('next'),
            'view' => get_string('view'),
            'viewcourse' => get_string('viewcourse', 'local_moderncommerce'),
            'viewreviews' => get_string('viewreviews', 'local_moderncommerce'),
            'managereviews' => get_string('managereviews', 'local_moderncommerce'),
            'hide' => get_string('hidereview', 'local_moderncommerce'),
            'show' => get_string('showreview', 'local_moderncommerce'),
            'delete' => get_string('delete'),
            'confirmdelete' => get_string('confirmdeletereview', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'noreviews' => get_string('noreviews', 'local_moderncommerce'),
            'nocoursesreviewed' => get_string('nocoursesreviewed', 'local_moderncommerce'),
            'likes' => get_string('likes', 'local_moderncommerce'),
            'dislikes' => get_string('dislikes', 'local_moderncommerce'),
            'loves' => get_string('loves', 'local_moderncommerce'),
        ];
    }
}
