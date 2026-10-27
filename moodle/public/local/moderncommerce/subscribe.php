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
 * Public subscription plans / pricing page (buyer-facing).
 *
 * Lists the subscription plans and lets a buyer start a trial, subscribe (free
 * or via Modern Commerce checkout), or redeem a subscription key. Subscription
 * data and the subscribe actions live in the local_moderncommerce add-on.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\output\learner_shell;

require_login();

$context = context_system::instance();

$plugininfo = core_plugin_manager::instance()->get_plugin_info('local_moderncommerce');
$available = $plugininfo !== null && $plugininfo->is_installed_and_upgraded();
if (!$available) {
    redirect(
        new moodle_url('/local/moderncommerce/learner/index.php'),
        get_string('subscriptionsunavailable_desc', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}
require_capability('local/moderncommerce:subscribetoplan', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/subscribe.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('chooseplan', 'local_moderncommerce'));
$PAGE->set_heading(get_string('chooseplan', 'local_moderncommerce'));

$shell = learner_shell::create('subscriptions');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/subscription_plans',
    'id' => 'moderncommerce-subscription-plans-app',
    'class' => 'local-moderncommerce-subscription-plans',
    'props' => [
        'methods' => [
            'getPlans' => 'local_moderncommerce_subscription_get_plans',
            'subscribe' => 'local_moderncommerce_subscription_subscribe',
            'redeemKey' => 'local_moderncommerce_subscription_redeem_key',
        ],
        'manageUrl' => (new moodle_url('/local/moderncommerce/learner/subscription.php'))->out(false),
        'layout' => $shell->get_react_layout_context(),
        'labels' => [
            'loading' => get_string('loading', 'local_moderncommerce'),
            'chooseplan' => get_string('chooseplan', 'local_moderncommerce'),
            'chooseplan_desc' => get_string('chooseplan_desc', 'local_moderncommerce'),
            'choosebillingcycle' => get_string('choosebillingcycle', 'local_moderncommerce'),
            'monthly_short' => get_string('monthly_short', 'local_moderncommerce'),
            'yearly_short' => get_string('yearly_short', 'local_moderncommerce'),
            'permonth' => get_string('permonth', 'local_moderncommerce'),
            'peryear' => get_string('peryear', 'local_moderncommerce'),
            'freeforever' => get_string('freeforever', 'local_moderncommerce'),
            'mostpopular' => get_string('mostpopular', 'local_moderncommerce'),
            'subscribenow' => get_string('subscribenow', 'local_moderncommerce'),
            'startfreetrial' => get_string('startfreetrial', 'local_moderncommerce'),
            'daytrialbadge' => get_string('daytrialbadge', 'local_moderncommerce', '{$a}'),
            'currentplanlabel' => get_string('currentplanlabel', 'local_moderncommerce'),
            'manageplanbtn' => get_string('manageplanbtn', 'local_moderncommerce'),
            'planincludes' => get_string('planincludes', 'local_moderncommerce'),
            'noplansavailable' => get_string('noplansavailable', 'local_moderncommerce'),
            'haveakey' => get_string('haveakey', 'local_moderncommerce'),
            'haveakey_desc' => get_string('haveakey_desc', 'local_moderncommerce'),
            'enterkey' => get_string('enterkey', 'local_moderncommerce'),
            'redeem' => get_string('redeem', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-subscription-plans',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-credit-card',
]);

echo $OUTPUT->header();
echo $shell->set_content($contenthtml)
    ->add_breadcrumb(get_string('chooseplan', 'local_moderncommerce'), '', true)
    ->render($OUTPUT);
echo $OUTPUT->footer();
