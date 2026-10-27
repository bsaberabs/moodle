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
 * Modern Commerce subscription plan access route.
 *
 * Defines which courses, categories, and bundles a subscription plan grants.
 * The UI lives in Modern Commerce; the access rules and grant logic live in the
 * local_moderncommerce add-on and are consumed through its webservice
 * endpoints. When the add-on is not installed the page renders an
 * "add-on required" state instead of failing.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

$planid = required_param('id', PARAM_INT);

$context = context_system::instance();
require_login();

require_capability('local/moderncommerce:managesubscriptionplans', $context);
// Subscriptions are core; the React admin always renders.
$available = true;

$pageurl = new moodle_url('/local/moderncommerce/admin/subscription_plan_access.php', ['id' => $planid]);
$subscriptionsurl = new moodle_url('/local/moderncommerce/admin/subscriptions.php');

$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');

$actionshtml = '';
$plan = null;

if ($available) {
    $plan = \local_moderncommerce\subscription\api\plan_api::get($planid, false);
    if (!$plan) {
        redirect(
            $subscriptionsurl,
            get_string('error:plannotfound', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

$pagetitle = $available
    ? format_string($plan->name) . ' — ' . get_string('planaccess', 'local_moderncommerce')
    : get_string('subscriptionsunavailable', 'local_moderncommerce');
$PAGE->set_title($pagetitle);

if ($available) {
    $config = json_encode([
        'component' => '@moodle/lms/local_moderncommerce/subscription_plan_access',
        'id' => 'moderncommerce-subscription-plan-access-app',
        'class' => 'local-moderncommerce-subscription-plan-access',
        'props' => [
            'planid' => $planid,
            'methods' => [
                'get' => 'local_moderncommerce_subscription_get_plan_access',
                'add' => 'local_moderncommerce_subscription_add_plan_access',
                'remove' => 'local_moderncommerce_subscription_remove_plan_access',
                'saveFeatures' => 'local_moderncommerce_subscription_save_plan_features',
            ],
            'searchCoursesMethodName' => 'local_moderncommerce_search_courses',
            'featureMatrixUrl' => (new moodle_url('/local/moderncommerce/admin/subscription_features.php'))->out(false),
            'labels' => [
                'title' => get_string('planaccess', 'local_moderncommerce'),
                'planfeatures' => get_string('planfeatures', 'local_moderncommerce'),
                'planfeatures_desc' => get_string('planfeatures_help', 'local_moderncommerce'),
                'accessrules' => get_string('accessrules', 'local_moderncommerce'),
                'accessrules_desc' => get_string('accessrules_help', 'local_moderncommerce'),
                'featurematrix' => get_string('featurematrix', 'local_moderncommerce'),
                'savechanges' => get_string('savechanges'),
                'nofeaturesmatrix' => get_string('nofeaturesmatrix', 'local_moderncommerce'),
                'accesstype' => get_string('accesstype', 'local_moderncommerce'),
                'accesstype_course' => get_string('accesstype_course', 'local_moderncommerce'),
                'accesstype_category' => get_string('accesstype_category', 'local_moderncommerce'),
                'accesstype_bundle' => get_string('accesstype_bundle', 'local_moderncommerce'),
                'coursesgranted' => get_string('coursesgranted', 'local_moderncommerce'),
                'addaccessrule' => get_string('addaccessrule', 'local_moderncommerce'),
                'selectcourse' => get_string('selectcourse', 'local_moderncommerce'),
                'selectcategory' => get_string('selectcategory', 'local_moderncommerce'),
                'selectbundle' => get_string('selectbundle', 'local_moderncommerce'),
                'searchcoursesplaceholder' => get_string('searchcoursesplaceholder', 'local_moderncommerce'),
                'searchcategories' => get_string('searchcategories', 'local_moderncommerce'),
                'searchbundles' => get_string('searchbundles', 'local_moderncommerce'),
                'selecttargetfirst' => get_string('selecttarget', 'local_moderncommerce'),
                'noaccessrules' => get_string('noaccessrules', 'local_moderncommerce'),
                'noaccessrulesdesc' => get_string('noaccessrulesdesc', 'local_moderncommerce'),
                'confirmremove' => get_string('confirmaccessruledelete', 'local_moderncommerce'),
                'billingcycle_monthly' => get_string('billingcycle_monthly', 'local_moderncommerce'),
                'billingcycle_yearly' => get_string('billingcycle_yearly', 'local_moderncommerce'),
                'remove' => get_string('remove'),
                'loading' => get_string('loading', 'local_moderncommerce'),
                'noresults' => get_string('noresults', 'local_moderncommerce'),
            ],
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/subscription_plan_access', [
        'planaccessreactconfig' => $config,
    ]);

    $actionshtml = admin_shell::action_group([
        [
            'type' => 'link',
            'url' => $subscriptionsurl,
            'label' => get_string('backtosubscriptions', 'local_moderncommerce'),
            'icon' => 'bi-arrow-left',
        ],
    ]);
} else {
    $contenthtml = '<section class="mc-card"><div class="mc-card-body">'
        . '<div class="mc-empty mc-empty--centered">'
        . '<span class="mc-empty__icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>'
        . '<p class="mc-empty__title">' . s(get_string('subscriptionsunavailable', 'local_moderncommerce')) . '</p>'
        . '<p class="mc-empty__text">' . s(get_string('subscriptionsunavailable_desc', 'local_moderncommerce')) . '</p>'
        . '</div></div></section>';
}

$shell = admin_shell::create('subscriptions')
    ->set_title($available ? format_string($plan->name) : $pagetitle)
    ->set_subtitle($available
        ? get_string('planaccess_desc', 'local_moderncommerce')
        : get_string('subscriptionsunavailable_desc', 'local_moderncommerce'))
    ->set_content($contenthtml);
if ($actionshtml !== '') {
    $shell->set_actions($actionshtml);
}

$shellhtml = $shell->render($OUTPUT);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
