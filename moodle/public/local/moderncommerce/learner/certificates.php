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
 * Learner certificates page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\learner_shell;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/certificates.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('mycertificates', 'local_moderncommerce'));
$PAGE->set_heading(get_string('mycertificates', 'local_moderncommerce'));

$shell = learner_shell::create('certificates');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_certificates',
    'id' => 'moderncommerce-learner-certificates-app',
    'class' => 'local-moderncommerce-learner-certificates',
    'props' => [
        'methodName' => 'local_moderncommerce_list_learner_certificates',
        'layout' => $shell->get_react_layout_context(),
        'labels' => [
            'actions' => get_string('actions', 'local_moderncommerce'),
            'activeaccess' => get_string('activeaccess', 'local_moderncommerce'),
            'activecertificates' => get_string('activecertificates', 'local_moderncommerce'),
            'browsecatalog' => get_string('browsecatalog', 'local_moderncommerce'),
            'certificatecode' => get_string('certificatecode', 'local_moderncommerce'),
            'certificatecourses' => get_string('certificatecourses', 'local_moderncommerce'),
            'certificatedesc' => get_string('certificatedesc', 'local_moderncommerce'),
            'certificateemptydesc' => get_string('certificateemptydesc', 'local_moderncommerce'),
            'certificatefeaturesunavailable' => get_string('certificatefeaturesunavailable', 'local_moderncommerce'),
            'certificatefeaturesunavailabledesc' => get_string('certificatefeaturesunavailabledesc', 'local_moderncommerce'),
            'certificatepath' => get_string('certificatepath', 'local_moderncommerce'),
            'certificatesearned' => get_string('certificatesearned', 'local_moderncommerce'),
            'certificatetemplate' => get_string('certificatetemplate', 'local_moderncommerce'),
            'course' => get_string('course', 'local_moderncommerce'),
            'expiresdate' => get_string('expiresdate', 'local_moderncommerce'),
            'includedcourses' => get_string('includedcourses', 'local_moderncommerce'),
            'issueddate' => get_string('issueddate', 'local_moderncommerce'),
            'latestcertificate' => get_string('latestcertificate', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'mycertificates' => get_string('mycertificates', 'local_moderncommerce'),
            'nocertificates' => get_string('nocertificates', 'local_moderncommerce'),
            'noexpiry' => get_string('noexpiry', 'local_moderncommerce'),
            'viewcertificate' => get_string('viewcertificate', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-certificates',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-patch-check-fill',
]);

echo $OUTPUT->header();
echo $shell->set_content($contenthtml)
    ->add_breadcrumb(get_string('mycertificates', 'local_moderncommerce'), '', true)
    ->render($OUTPUT);
echo $OUTPUT->footer();
