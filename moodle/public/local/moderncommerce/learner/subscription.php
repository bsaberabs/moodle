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
 * Learner subscription plan page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\output\learner_shell;

require_login();

$subscriptionid = optional_param('id', 0, PARAM_INT);
$planid = optional_param('planid', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/subscription.php', [
    'id' => $subscriptionid,
    'planid' => $planid,
]));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');

$shell = learner_shell::create('subscriptions');
$subscriptionsavailable = learner_shell::subscriptions_available();
$pagetitle = $subscriptionsavailable
    ? get_string('mysubscriptionplan', 'local_moderncommerce')
    : get_string('subscriptionsunavailable', 'local_moderncommerce');

$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);

if ($subscriptionsavailable) {
    $reactconfig = json_encode([
        'component' => '@moodle/lms/local_moderncommerce/learner_subscription',
        'id' => 'moderncommerce-learner-subscription-app',
        'class' => 'local-moderncommerce-learner-subscription',
        'props' => [
            'methodName' => 'local_moderncommerce_get_learner_subscription',
            'accessMethodName' => 'local_moderncommerce_get_learner_subscription_access',
            'subscriptionId' => $subscriptionid,
            'planId' => $planid,
            'layout' => $shell->get_react_layout_context(),
            'labels' => [
                'accessdetails' => get_string('accessdetails', 'local_moderncommerce'),
                'accessfilter_all' => get_string('accessfilter_all', 'local_moderncommerce'),
                'accessfilter_enrolled' => get_string('accessfilter_enrolled', 'local_moderncommerce'),
                'accessfilter_open' => get_string('accessfilter_open', 'local_moderncommerce'),
                'accessfilterlabel' => get_string('accessfilterlabel', 'local_moderncommerce'),
                'accessnoresults' => get_string('accessnoresults', 'local_moderncommerce'),
                'accessresultscount' => get_string('accessresultscount', 'local_moderncommerce'),
                'accesssearchlabel' => get_string('accesssearchlabel', 'local_moderncommerce'),
                'accesssearchplaceholder' => get_string('accesssearchplaceholder', 'local_moderncommerce'),
                'subscriptionnocourses' => get_string('subscriptionnocourses', 'local_moderncommerce'),
                'directcourses' => get_string('directcourses', 'local_moderncommerce'),
                'bundleaccess' => get_string('bundleaccess', 'local_moderncommerce'),
                'categoryaccess' => get_string('categoryaccess', 'local_moderncommerce'),
                'clearfilter' => get_string('clearfilter', 'local_moderncommerce'),
                'enrolled' => get_string('enrolled', 'local_moderncommerce'),
                'openenrollment' => get_string('openenrollment', 'local_moderncommerce'),
                'takecourse' => get_string('takecourse', 'local_moderncommerce'),
                'accountcredit' => get_string('accountcredit', 'local_moderncommerce'),
                'actions' => get_string('actions', 'local_moderncommerce'),
                'autorenew' => get_string('autorenew', 'local_moderncommerce'),
                'billinghistory' => get_string('billinghistory', 'local_moderncommerce'),
                'browseplans' => get_string('browseplans', 'local_moderncommerce'),
                'browsecatalog' => get_string('browsecatalog', 'local_moderncommerce'),
                'bundles' => get_string('bundles', 'local_moderncommerce'),
                'cancel' => get_string('cancel', 'local_moderncommerce'),
                'cancelatperiodend' => get_string('cancelatperiodend', 'local_moderncommerce'),
                'category' => get_string('category', 'local_moderncommerce'),
                'changeplan' => get_string('changeplan', 'local_moderncommerce'),
                'courses' => get_string('courses', 'local_moderncommerce'),
                'enddate' => get_string('enddate', 'local_moderncommerce'),
                'features' => get_string('features', 'local_moderncommerce'),
                'lifetime' => get_string('lifetime', 'local_moderncommerce'),
                'loading' => get_string('loading', 'local_moderncommerce'),
                'mysubscriptionplan' => get_string('mysubscriptionplan', 'local_moderncommerce'),
                'mycourses' => get_string('mycourses', 'local_moderncommerce'),
                'noactivesubscription' => get_string('noactivesubscription', 'local_moderncommerce'),
                'orders' => get_string('orders', 'local_moderncommerce'),
                'plan' => get_string('plan', 'local_moderncommerce'),
                'price' => get_string('price', 'local_moderncommerce'),
                'renewsubscription' => get_string('renewsubscription', 'local_moderncommerce'),
                'startdate' => get_string('startdate', 'local_moderncommerce'),
                'status' => get_string('status', 'local_moderncommerce'),
                'subscriptionnotice_active' => get_string('subscriptionnotice_active', 'local_moderncommerce'),
                'subscriptionnotice_autorenew' => get_string('subscriptionnotice_autorenew', 'local_moderncommerce'),
                'subscriptionnotice_cancelscheduled' => get_string('subscriptionnotice_cancelscheduled', 'local_moderncommerce'),
                'subscriptionnotice_expired' => get_string('subscriptionnotice_expired', 'local_moderncommerce'),
                'subscriptionnotice_expiring' => get_string('subscriptionnotice_expiring', 'local_moderncommerce'),
                'subscriptionnotice_lifetime' => get_string('subscriptionnotice_lifetime', 'local_moderncommerce'),
                'subscriptionnotice_trial' => get_string('subscriptionnotice_trial', 'local_moderncommerce'),
                'subscriptionperiod' => get_string('subscriptionperiod', 'local_moderncommerce'),
                'subscriptionstatussummary' => get_string('subscriptionstatussummary', 'local_moderncommerce'),
                'subscriptionsunavailable' => get_string('subscriptionsunavailable', 'local_moderncommerce'),
                'totalcourses' => get_string('totalcourses', 'local_moderncommerce'),
                'viewaccesslibrary' => get_string('viewaccesslibrary', 'local_moderncommerce'),
                'viewsubscriptionaccess' => get_string('viewsubscriptionaccess', 'local_moderncommerce'),
            ],
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
        'region' => 'moderncommerce-learner-subscription',
        'reactconfig' => $reactconfig,
        'icon' => 'bi-credit-card',
    ]);
} else {
    $contenthtml = local_moderncommerce_subscription_unavailable_html();
}

echo $OUTPUT->header();
echo $shell->set_content($contenthtml)
    ->add_breadcrumb($pagetitle, '', true)
    ->render($OUTPUT);
echo $OUTPUT->footer();
