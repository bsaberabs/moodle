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
 * Bundle advanced/merchandising features editor (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\services\certificate_template_service;

$bundleid = optional_param('bundleid', 0, PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:managecourses', $context);

$bundle = $DB->get_record_select(
    'local_moderncommerce_products',
    "id = :id AND producttype IN (:bundle, :program)",
    ['id' => $bundleid, 'bundle' => 'bundle', 'program' => 'program']
);
if (!$bundle) {
    redirect(
        new moodle_url('/local/moderncommerce/admin/bundles.php'),
        get_string('invalidbundle', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/advanced_bundle_features.php', ['bundleid' => $bundleid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('acf_bundleadmin', 'local_moderncommerce'));

$bundlefeaturesreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/bundle_features_admin',
    'id' => 'moderncommerce-bundle-features-app',
    'class' => 'local-moderncommerce-bundle-features',
    'props' => [
        'bundleId' => (int) $bundleid,
        'getMethodName' => 'local_moderncommerce_admin_get_bundle_features',
        'searchPrerequisitesMethodName' => 'local_moderncommerce_admin_search_program_prerequisites',
        'saveMethodName' => 'local_moderncommerce_admin_save_bundle_features',
        'certificateTemplatesUrl' => certificate_template_service::manage_url(),
        'labels' => [
            'bundleinfo' => get_string('catalogvisibility', 'local_moderncommerce'),
            'durationassessments' => get_string('durationassessments', 'local_moderncommerce'),
            'completionsettings' => get_string('completionsettings', 'local_moderncommerce'),
            'reorder' => get_string('reorder', 'local_moderncommerce'),
            'overview' => get_string('bundleoverview', 'local_moderncommerce'),
            'overviewdesc' => get_string('bundleoverviewdesc', 'local_moderncommerce'),
            'overviewmanaged' => get_string('overviewmanaged', 'local_moderncommerce'),
            'price' => get_string('price', 'local_moderncommerce'),
            'saleprice' => get_string('saleprice', 'local_moderncommerce'),
            'type' => get_string('type', 'local_moderncommerce'),
            'coursesinbundle' => get_string('coursesinbundle', 'local_moderncommerce'),
            'savings' => get_string('savings', 'local_moderncommerce'),
            'totalduration' => get_string('totalduration', 'local_moderncommerce'),
            'certificate' => get_string('certificate', 'local_moderncommerce'),
            'enabled' => get_string('enabled', 'local_moderncommerce'),
            'disabled' => get_string('disabled', 'local_moderncommerce'),
            'merchandisingready' => get_string('merchandisingready', 'local_moderncommerce'),
            'merchandisingtip' => get_string('merchandisingtip', 'local_moderncommerce'),
            'pathordered' => get_string('pathordered', 'local_moderncommerce'),
            'pathflexible' => get_string('pathflexible', 'local_moderncommerce'),
            'noimage' => get_string('noimage', 'local_moderncommerce'),
            'skilllevel' => get_string('skilllevel', 'local_moderncommerce'),
            'language' => get_string('language', 'local_moderncommerce'),
            'visibility' => get_string('visibility', 'local_moderncommerce'),
            'availability' => get_string('availability', 'local_moderncommerce'),
            'startdate' => get_string('startdatelabel', 'local_moderncommerce'),
            'enddate' => get_string('enddatelabel', 'local_moderncommerce'),
            'duration' => get_string('duration', 'local_moderncommerce'),
            'hours' => get_string('hours', 'local_moderncommerce'),
            'minutes' => get_string('minutes', 'local_moderncommerce'),
            'autodetect' => get_string('autodetect', 'local_moderncommerce'),
            'manualentry' => get_string('manualentry', 'local_moderncommerce'),
            'nolearninghoursfield' => get_string('nolearninghoursfield', 'local_moderncommerce'),
            'nolearninghourscourselevel' => get_string('nolearninghourscourselevel', 'local_moderncommerce'),
            'partiallearninghours' => get_string('partiallearninghours', 'local_moderncommerce'),
            'alllearninghoursset' => get_string('alllearninghoursset', 'local_moderncommerce'),
            'nobundlecourseslearninghours' => get_string('nobundlecourseslearninghours', 'local_moderncommerce'),
            'quizassessmentcount' => get_string('quizassessmentcount', 'local_moderncommerce'),
            'assessments' => get_string('assessments', 'local_moderncommerce'),
            'passgrade' => get_string('passgrade', 'local_moderncommerce'),
            'passpolicy' => get_string('passpolicy', 'local_moderncommerce'),
            'enablecertificate' => get_string('enablecertificate', 'local_moderncommerce'),
            'certificatetemplate' => get_string('certificatetemplate', 'local_moderncommerce'),
            'certificatetemplatehelp' => get_string('certificatetemplate_help', 'local_moderncommerce'),
            'selectcertificatetemplate' => get_string('selectcertificatetemplate', 'local_moderncommerce'),
            'useglobalcertificatetemplate' => get_string('useglobalcertificatetemplate', 'local_moderncommerce'),
            'certificatetemplaterequired' => get_string('certificatetemplaterequired', 'local_moderncommerce'),
            'coursecertificateunavailableprogram' => get_string(
                'coursecertificateunavailableprogram',
                'local_moderncommerce'
            ),
            'nocertificatetemplates' => get_string('nocertificatetemplates', 'local_moderncommerce'),
            'managecertificatetemplates' => get_string('managecertificatetemplates', 'local_moderncommerce'),
            'merchandising' => get_string('merchandising', 'local_moderncommerce'),
            'regularprice' => get_string('regularprice', 'local_moderncommerce'),
            'compareprice' => get_string('compareprice', 'local_moderncommerce'),
            'badges' => get_string('badges', 'local_moderncommerce'),
            'featured' => get_string('featured', 'local_moderncommerce'),
            'bestseller' => get_string('bestseller', 'local_moderncommerce'),
            'trending' => get_string('trending', 'local_moderncommerce'),
            'courseoutline' => get_string('courseoutline', 'local_moderncommerce'),
            'sectiontitle' => get_string('sectiontitle', 'local_moderncommerce'),
            'addsection' => get_string('addsection', 'local_moderncommerce'),
            'mustpasscourses' => get_string('mustpasscourses', 'local_moderncommerce'),
            'mustpasscourseshelp' => get_string('mustpasscourses_help', 'local_moderncommerce'),
            'programprerequisites' => get_string('programprerequisites', 'local_moderncommerce'),
            'programprerequisiteshelp' => get_string('programprerequisites_help', 'local_moderncommerce'),
            'searchprogramprerequisites' => get_string('searchprogramprerequisites', 'local_moderncommerce'),
            'noprerequisitematches' => get_string('noprerequisitematches', 'local_moderncommerce'),
            'selectedprerequisites' => get_string('selectedprerequisites', 'local_moderncommerce'),
            'tags' => get_string('tags', 'local_moderncommerce'),
            'addtag' => get_string('addtag', 'local_moderncommerce'),
            'remove' => get_string('delete'),
            'none' => get_string('none'),
            'save' => get_string('savechanges'),
            'saved' => get_string('changessaved'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'error:invalidnumber' => get_string('error:invalidnumber', 'local_moderncommerce'),
            'error:invalidinteger' => get_string('error:invalidinteger', 'local_moderncommerce'),
            'error:minvalue' => get_string('error:minvalue', 'local_moderncommerce'),
            'error:maxvalue' => get_string('error:maxvalue', 'local_moderncommerce'),
            'error:enddatebeforestart' => get_string('error:enddatebeforestart', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/bundle_features', [
    'bundlefeaturesreactconfig' => $bundlefeaturesreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'link',
        'url' => new moodle_url('/local/moderncommerce/admin/bundles.php'),
        'label' => get_string('managebundles', 'local_moderncommerce'),
        'icon' => 'bi-arrow-left',
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'bundles',
    get_string('acf_bundleadmin', 'local_moderncommerce'),
    $contenthtml,
    format_string($bundle->name),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
