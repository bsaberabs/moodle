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
 * Learner courses page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\learner_shell;

require_login();

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 10, PARAM_INT);
$search = optional_param('search', '', PARAM_TEXT);
$sort = optional_param('sort', 'recent', PARAM_ALPHANUMEXT);
$categoryid = optional_param('category', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/courses.php', [
    'page' => $page,
    'perpage' => $perpage,
    'search' => $search,
    'sort' => $sort,
    'category' => $categoryid,
]));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('mycourses', 'local_moderncommerce'));
$PAGE->set_heading(get_string('mycourses', 'local_moderncommerce'));

$shell = learner_shell::create('courses');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_courses',
    'id' => 'moderncommerce-learner-courses-app',
    'class' => 'local-moderncommerce-learner-courses',
    'props' => [
        'methodName' => 'local_moderncommerce_list_learner_courses',
        'initialFilters' => [
            'search' => $search,
            'categoryid' => $categoryid,
            'sort' => $sort,
            'page' => $page,
            'perpage' => $perpage,
        ],
        'layout' => $shell->get_react_layout_context(),
        'labels' => [
            'action' => get_string('action', 'local_moderncommerce'),
            'allcategories' => get_string('allcategories', 'local_moderncommerce'),
            'browsecourses' => get_string('browsecourses', 'local_moderncommerce'),
            'browsecoursestoenroll' => get_string('browsecoursestoenroll', 'local_moderncommerce'),
            'category' => get_string('category', 'local_moderncommerce'),
            'clearsearch' => get_string('clearsearch', 'local_moderncommerce'),
            'completedcourses' => get_string('completedcourses', 'local_moderncommerce'),
            'continue' => get_string('continue', 'local_moderncommerce'),
            'course' => get_string('course', 'local_moderncommerce'),
            'enrolled' => get_string('enrolled', 'local_moderncommerce'),
            'inprogress' => get_string('inprogress', 'local_moderncommerce'),
            'includedcourses' => get_string('includedcourses', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'mycourses' => get_string('mycourses', 'local_moderncommerce'),
            'mycoursesdesc' => get_string('mycoursesdesc', 'local_moderncommerce'),
            'nocoursesenrolled' => get_string('nocoursesenrolled', 'local_moderncommerce'),
            'nextpage' => get_string('nextpage', 'local_moderncommerce'),
            'of' => get_string('of', 'local_moderncommerce'),
            'ongoing' => get_string('ongoing', 'local_moderncommerce'),
            'overdue' => get_string('overdue', 'local_moderncommerce'),
            'pastdue' => get_string('pastdue', 'local_moderncommerce'),
            'previouspage' => get_string('previouspage', 'local_moderncommerce'),
            'progress' => get_string('progress', 'local_moderncommerce'),
            'searchcourses' => get_string('searchcourses', 'local_moderncommerce'),
            'sortlastaccess' => get_string('sortlastaccess', 'local_moderncommerce'),
            'sortnameasc' => get_string('sortnameasc', 'local_moderncommerce'),
            'sortnamedesc' => get_string('sortnamedesc', 'local_moderncommerce'),
            'sortprogress' => get_string('sortprogress', 'local_moderncommerce'),
            'sortrecent' => get_string('sortrecent', 'local_moderncommerce'),
            'start' => get_string('start', 'local_moderncommerce'),
            'takecourse' => get_string('takecourse', 'local_moderncommerce'),
            'totalcourses' => get_string('totalcourses', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-courses',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-play-circle',
]);

echo $OUTPUT->header();
echo $shell->set_content($contenthtml)
    ->add_breadcrumb(get_string('mycourses', 'local_moderncommerce'), '', true)
    ->render($OUTPUT);
echo $OUTPUT->footer();
