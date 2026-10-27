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
 * Learner profile page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\learner_shell;
use local_moderncommerce\output\validation_labels;

require_login();

$context = context_system::instance();
require_capability('local/moderncommerce:viewcatalog', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/profile.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('myprofile', 'local_moderncommerce'));
$PAGE->set_heading(get_string('myprofile', 'local_moderncommerce'));

$shell = learner_shell::create('profile');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_profile',
    'id' => 'moderncommerce-learner-profile-app',
    'class' => 'local-moderncommerce-learner-profile',
    'props' => [
        'getMethodName' => 'local_moderncommerce_get_learner_profile',
        'saveMethodName' => 'local_moderncommerce_save_learner_profile',
        'savePictureMethodName' => 'local_moderncommerce_save_learner_profile_picture',
        'layout' => $shell->get_react_layout_context(),
        // Same limit learner_profile_service enforces, so the browser rejects an
        // oversized file before encoding and uploading it.
        'maxImageBytes' => get_max_upload_file_size($CFG->maxbytes),
        'labels' => validation_labels::all() + [
            'error:invalidphone' => get_string('error:invalidphone', 'local_moderncommerce'),
            'aboutyou' => get_string('aboutyou', 'local_moderncommerce'),
            'address' => get_string('address', 'local_moderncommerce'),
            'basicdetails' => get_string('basicdetails', 'local_moderncommerce'),
            'cancel' => get_string('cancel', 'local_moderncommerce'),
            'city' => get_string('city', 'local_moderncommerce'),
            'contactdetails' => get_string('contactdetails', 'local_moderncommerce'),
            'description' => get_string('description', 'local_moderncommerce'),
            'emaildisplay' => get_string('emaildisplay', 'local_moderncommerce'),
            'hideprofileedit' => get_string('hideprofileedit', 'local_moderncommerce'),
            'idnumber' => get_string('idnumber', 'local_moderncommerce'),
            'interests' => get_string('interests', 'local_moderncommerce'),
            'interestsplaceholder' => get_string('interestsplaceholder', 'local_moderncommerce'),
            'language' => get_string('language', 'local_moderncommerce'),
            'phone1' => get_string('phone1', 'local_moderncommerce'),
            'phone2' => get_string('phone2', 'local_moderncommerce'),
            'preferences' => get_string('preferences', 'local_moderncommerce'),
            'remove' => get_string('remove', 'local_moderncommerce'),
            'timezone' => get_string('timezone', 'local_moderncommerce'),
            'country' => get_string('country', 'local_moderncommerce'),
            'customprofilefields' => get_string('customprofilefields', 'local_moderncommerce'),
            'department' => get_string('department', 'local_moderncommerce'),
            'editprofile' => get_string('editprofile', 'local_moderncommerce'),
            'email' => get_string('email'),
            'firstname' => get_string('firstname'),
            'institution' => get_string('institution', 'local_moderncommerce'),
            'lastname' => get_string('lastname'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'myprofile' => get_string('myprofile', 'local_moderncommerce'),
            'notprovided' => get_string('notprovided', 'local_moderncommerce'),
            'openfullprofileeditor' => get_string('openfullprofileeditor', 'local_moderncommerce'),
            'profiledetails' => get_string('profiledetails', 'local_moderncommerce'),
            'profiledetailsdesc' => get_string('profiledetailsdesc', 'local_moderncommerce'),
            'profileimagefilemissing' => get_string('profileimagefilemissing', 'local_moderncommerce'),
            'removeprofileimage' => get_string('removeprofileimage', 'local_moderncommerce'),
            'savechanges' => get_string('savechanges', 'local_moderncommerce'),
            'saving' => get_string('saving', 'local_moderncommerce'),
            'selectprofileimage' => get_string('selectprofileimage', 'local_moderncommerce'),
            'showprofileedit' => get_string('showprofileedit', 'local_moderncommerce'),
            'uploadprofileimage' => get_string('uploadprofileimage', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-profile',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-person-fill',
]);

echo $OUTPUT->header();
echo $contenthtml;
echo $OUTPUT->footer();
