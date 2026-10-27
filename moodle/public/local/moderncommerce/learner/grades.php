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
 * Learner grades page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\learner_shell;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/grades.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('mygrades', 'local_moderncommerce'));
$PAGE->set_heading(get_string('mygrades', 'local_moderncommerce'));

$shell = learner_shell::create('grades');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_grades',
    'id' => 'moderncommerce-learner-grades-app',
    'class' => 'local-moderncommerce-learner-grades',
    'props' => [
        'methodName' => 'local_moderncommerce_list_learner_grades',
        'layout' => $shell->get_react_layout_context(),
        'labels' => [
            'actions' => get_string('actions', 'local_moderncommerce'),
            'completed' => get_string('completed', 'local_moderncommerce'),
            'completedcourses' => get_string('completedcourses', 'local_moderncommerce'),
            'completionnotenabled' => get_string('completionnotenabled', 'local_moderncommerce'),
            'course' => get_string('course', 'local_moderncommerce'),
            'enrolledcourses' => get_string('enrolledcourses', 'local_moderncommerce'),
            'gradedcourses' => get_string('gradedcourses', 'local_moderncommerce'),
            'gradeaverage' => get_string('gradeaverage', 'local_moderncommerce'),
            'gradeoverview' => get_string('gradeoverview', 'local_moderncommerce'),
            'gradeoverviewdesc' => get_string('gradeoverviewdesc', 'local_moderncommerce'),
            'inprogress' => get_string('inprogress', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'mycourses' => get_string('mycourses', 'local_moderncommerce'),
            'mygrades' => get_string('mygrades', 'local_moderncommerce'),
            'mygradesdesc' => get_string('mygradesdesc', 'local_moderncommerce'),
            'nogrades' => get_string('nogrades', 'local_moderncommerce'),
            'nogradesdesc' => get_string('nogradesdesc', 'local_moderncommerce'),
            'notavailable' => get_string('notavailable', 'local_moderncommerce'),
            'openfullgradereport' => get_string('openfullgradereport', 'local_moderncommerce'),
            'progress' => get_string('progress', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'view' => get_string('view', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-grades',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-clipboard-check',
]);

echo $OUTPUT->header();
echo $contenthtml;
echo $OUTPUT->footer();
