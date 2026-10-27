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
 * Course advanced/merchandising features editor (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

$courseid = optional_param('courseid', 0, PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:managecourses', $context);

$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course || (int) $course->id === (int) SITEID) {
    redirect(
        new moodle_url('/local/moderncommerce/admin/pricing.php'),
        get_string('error:invalidcourse', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/course_advanced_features.php', ['courseid' => $courseid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('courseinformation', 'local_moderncommerce'));

$coursefeaturesreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/course_features_admin',
    'id' => 'moderncommerce-course-features-app',
    'class' => 'local-moderncommerce-course-features',
    'props' => [
        'courseId' => (int) $courseid,
        'getMethodName' => 'local_moderncommerce_admin_get_course_features',
        'saveMethodName' => 'local_moderncommerce_admin_save_course_features',
        'labels' => [
            'coursebasics' => get_string('coursebasics', 'local_moderncommerce'),
            'skilllevel' => get_string('skilllevel', 'local_moderncommerce'),
            'language' => get_string('language', 'local_moderncommerce'),
            'duration' => get_string('duration', 'local_moderncommerce'),
            'hours' => get_string('hours', 'local_moderncommerce'),
            'minutes' => get_string('minutes', 'local_moderncommerce'),
            'passgrade' => get_string('passgrade', 'local_moderncommerce'),
            'enablecertificate' => get_string('enablecertificate', 'local_moderncommerce'),
            'overview' => get_string('overviewtext', 'local_moderncommerce'),
            'autogenerate' => get_string('autogenerate', 'local_moderncommerce'),
            'learningobjectives' => get_string('learningobjectives', 'local_moderncommerce'),
            'addobjective' => get_string('addobjective', 'local_moderncommerce'),
            'courseoutline' => get_string('courseoutline', 'local_moderncommerce'),
            'sectiontitle' => get_string('sectiontitle', 'local_moderncommerce'),
            'estimatedtime' => get_string('estimatedtime', 'local_moderncommerce'),
            'addsection' => get_string('addsection', 'local_moderncommerce'),
            'merchandising' => get_string('merchandising', 'local_moderncommerce'),
            'regularprice' => get_string('regularprice', 'local_moderncommerce'),
            'saleprice' => get_string('saleprice', 'local_moderncommerce'),
            'badges' => get_string('badges', 'local_moderncommerce'),
            'featured' => get_string('featured', 'local_moderncommerce'),
            'bestseller' => get_string('bestseller', 'local_moderncommerce'),
            'trending' => get_string('trending', 'local_moderncommerce'),
            'none' => get_string('none'),
            'save' => get_string('savechanges'),
            'saved' => get_string('changessaved'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'remove' => get_string('delete'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/course_features', [
    'coursefeaturesreactconfig' => $coursefeaturesreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'link',
        'url' => new moodle_url('/local/moderncommerce/admin/pricing.php'),
        'label' => get_string('coursesandpricing', 'local_moderncommerce'),
        'icon' => 'bi-arrow-left',
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'courses',
    get_string('courseinformation', 'local_moderncommerce'),
    $contenthtml,
    format_string($course->fullname),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
