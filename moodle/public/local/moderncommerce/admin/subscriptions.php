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
 * Modern Commerce subscription management route.
 *
 * The UI lives in Modern Commerce; subscription plans, the feature matrix, and
 * subscriber lifecycle live in the local_moderncommerce add-on and are
 * consumed through its webservice endpoints. When the add-on is not installed
 * the page renders an "add-on required" state instead of failing.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

$context = context_system::instance();
require_login();

$subscriptionview = $subscriptionviewoverride ?? 'plans';
$subscriptionnavkey = $subscriptionnavkeyoverride ?? 'subscriptionplans';
$subscriptionpageurl = $subscriptionpageurloverride ?? '/local/moderncommerce/admin/subscriptions.php';
$subscriptiontitlekey = $subscriptiontitlekeyoverride ?? 'subscriptions';
$subscriptionsubtitlekey = $subscriptionsubtitlekeyoverride ?? 'manageplansdesc';
$subscriptioncapability = $subscriptioncapabilityoverride ?? 'local/moderncommerce:managesubscriptionplans';

require_capability($subscriptioncapability, $context);
// Subscriptions are core; the React admin always renders.
$available = true;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url($subscriptionpageurl));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$pagetitle = $available
    ? get_string($subscriptiontitlekey, 'local_moderncommerce')
    : get_string('subscriptionsunavailable', 'local_moderncommerce');
$PAGE->set_title($pagetitle);

$actionshtml = '';

if ($available) {
    $config = json_encode([
        'component' => '@moodle/lms/local_moderncommerce/subscriptions_admin',
        'id' => 'moderncommerce-subscriptions-admin-app',
        'class' => 'local-moderncommerce-subscriptions-admin',
        'props' => [
            'view' => $subscriptionview,
            'methods' => [
                'overview' => 'local_moderncommerce_subscription_get_overview',
                'savePlan' => 'local_moderncommerce_subscription_save_plan',
                'deletePlan' => 'local_moderncommerce_subscription_delete_plan',
                'featureMatrix' => 'local_moderncommerce_subscription_get_feature_matrix',
                'saveFeature' => 'local_moderncommerce_subscription_save_feature',
                'deleteFeature' => 'local_moderncommerce_subscription_delete_feature',
                'saveFeatureMatrix' => 'local_moderncommerce_subscription_save_feature_matrix',
                'listSubscriptions' => 'local_moderncommerce_subscription_list_subscriptions',
                'getSubscription' => 'local_moderncommerce_subscription_get_subscription',
                'subscriptionAction' => 'local_moderncommerce_subscription_subscription_action',
            ],
            'statusOptions' => [
                ['value' => 'active', 'label' => get_string('planstatus_active', 'local_moderncommerce')],
                ['value' => 'inactive', 'label' => get_string('planstatus_inactive', 'local_moderncommerce')],
            ],
            'billingOptions' => [
                ['value' => 'monthly', 'label' => get_string('billingcycle_monthly', 'local_moderncommerce')],
                ['value' => 'yearly', 'label' => get_string('billingcycle_yearly', 'local_moderncommerce')],
            ],
            'subscriptionStatusOptions' => [
                ['value' => 'active', 'label' => get_string('status_active', 'local_moderncommerce')],
                ['value' => 'trial', 'label' => get_string('status_trial', 'local_moderncommerce')],
                ['value' => 'grace', 'label' => get_string('status_grace', 'local_moderncommerce')],
                ['value' => 'suspended', 'label' => get_string('status_suspended', 'local_moderncommerce')],
                ['value' => 'cancelled', 'label' => get_string('status_cancelled', 'local_moderncommerce')],
                ['value' => 'expired', 'label' => get_string('status_expired', 'local_moderncommerce')],
            ],
            'iconOptions' => local_moderncommerce_subscription_icon_options(),
            'perPageOptions' => [10, 25, 50, 100],
            'labels' => [
                'title' => get_string('subscriptions', 'local_moderncommerce'),
                'plans' => get_string('plans', 'local_moderncommerce'),
                'featurematrix' => get_string('featurematrix', 'local_moderncommerce'),
                'subscribers' => get_string('subscribers', 'local_moderncommerce'),
                'totalplans' => get_string('totalplans', 'local_moderncommerce'),
                'activeplans' => get_string('activeplans', 'local_moderncommerce'),
                'totalsubscribers' => get_string('totalsubscribers', 'local_moderncommerce'),
                'mrr' => get_string('mrr', 'local_moderncommerce'),
                'search' => get_string('search'),
                'searchplans' => get_string('searchplans', 'local_moderncommerce'),
                'status' => get_string('status', 'local_moderncommerce'),
                'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
                'billingcycle' => get_string('billingcycle', 'local_moderncommerce'),
                'allcycles' => get_string('allcycles', 'local_moderncommerce'),
                'showcolumns' => get_string('showcolumns', 'local_moderncommerce'),
                'reportcolumns' => get_string('reportcolumns', 'local_moderncommerce'),
                'reportcolumnsdesc' => get_string('reportcolumnsdesc', 'local_moderncommerce'),
                'applycolumns' => get_string('applycolumns', 'local_moderncommerce'),
                'resetcolumns' => get_string('resetcolumns', 'local_moderncommerce'),
                'exportsubscribers' => get_string('exportsubscribers', 'local_moderncommerce'),
                'createplan' => get_string('createplan', 'local_moderncommerce'),
                'planname' => get_string('planname', 'local_moderncommerce'),
                'actions' => get_string('actions', 'local_moderncommerce'),
                'noplansfound' => get_string('noplansfound', 'local_moderncommerce'),
                'noresults' => get_string('noresults', 'local_moderncommerce'),
                'loading' => get_string('loading', 'local_moderncommerce'),
                'close' => get_string('close', 'local_moderncommerce'),
                'showing' => get_string('showing', 'local_moderncommerce'),
                'previous' => get_string('previous'),
                'page' => get_string('page', 'local_moderncommerce'),
                'next' => get_string('next'),
                'edit' => get_string('edit'),
                'delete' => get_string('delete'),
                'access' => get_string('accessaction', 'local_moderncommerce'),
                'view' => get_string('view'),
                'save' => get_string('save'),
                'cancel' => get_string('cancel'),
                'savechanges' => get_string('savechanges'),
                'billingcycle_yearly' => get_string('billingcycle_yearly', 'local_moderncommerce'),
                'billingcycle_monthly' => get_string('billingcycle_monthly', 'local_moderncommerce'),
                'planprice' => get_string('planprice', 'local_moderncommerce'),
                'planstatus_active' => get_string('planstatus_active', 'local_moderncommerce'),
                'planstatus_inactive' => get_string('planstatus_inactive', 'local_moderncommerce'),
                'editplan' => get_string('editplan', 'local_moderncommerce'),
                'backtolist' => get_string('backtolist', 'local_moderncommerce'),
                'planinfo' => get_string('planinfo', 'local_moderncommerce'),
                'plancode' => get_string('plancode', 'local_moderncommerce'),
                'planstatus' => get_string('status', 'local_moderncommerce'),
                'plandescription' => get_string('plandescription', 'local_moderncommerce'),
                'pricingsettings' => get_string('pricingsettings', 'local_moderncommerce'),
                'promoprice' => get_string('promoprice', 'local_moderncommerce'),
                'promoenddate' => get_string('promoenddate', 'local_moderncommerce'),
                'subscriptionsettings' => get_string('subscriptionsettings', 'local_moderncommerce'),
                'trialdays' => get_string('trialdayslabel', 'local_moderncommerce'),
                'graceperioddays' => get_string('graceperioddays', 'local_moderncommerce'),
                'maxseats' => get_string('maxseats', 'local_moderncommerce'),
                'sortorder' => get_string('plansortorder', 'local_moderncommerce'),
                'featuredplan' => get_string('featuredplan', 'local_moderncommerce'),
                'error_namerequired' => get_string('error:namerequired', 'local_moderncommerce'),
                'deleteplanwarning' => get_string('deleteplanwarning', 'local_moderncommerce', '{$a}'),
                'confirmdeleteplan' => get_string('confirmdeleteplan', 'local_moderncommerce'),
                'featurematrix_desc' => get_string('featurematrix_desc', 'local_moderncommerce'),
                'addnewfeature' => get_string('addnewfeature', 'local_moderncommerce'),
                'editfeature' => get_string('editfeature', 'local_moderncommerce'),
                'featurename' => get_string('featurename', 'local_moderncommerce'),
                'featurename_placeholder' => get_string('featurename_placeholder', 'local_moderncommerce'),
                'featureicon' => get_string('featureicon', 'local_moderncommerce'),
                'featureicon_help' => get_string('featureicon_help', 'local_moderncommerce'),
                'selecticon' => get_string('selecticon', 'local_moderncommerce'),
                'noplans' => get_string('noplans', 'local_moderncommerce'),
                'nofeaturesmatrix' => get_string('nofeaturesmatrix', 'local_moderncommerce'),
                'feature' => get_string('feature', 'local_moderncommerce'),
                'confirmdeletefeaturematrix' => get_string('confirmdelete', 'local_moderncommerce'),
                'status_active' => get_string('status_active', 'local_moderncommerce'),
                'status_trial' => get_string('status_trial', 'local_moderncommerce'),
                'status_cancelled' => get_string('status_cancelled', 'local_moderncommerce'),
                'plan' => get_string('plan', 'local_moderncommerce'),
                'allplans' => get_string('allplans', 'local_moderncommerce'),
                'searchsubscribers' => get_string('searchsubscribers', 'local_moderncommerce'),
                'perpage' => get_string('perpage', 'local_moderncommerce'),
                'subscriber' => get_string('subscriber', 'local_moderncommerce'),
                'email' => get_string('email', 'local_moderncommerce'),
                'enddate' => get_string('enddate', 'local_moderncommerce'),
                'startdate' => get_string('startdate', 'local_moderncommerce'),
                'autorenew' => get_string('autorenew', 'local_moderncommerce'),
                'autorenew_enabled' => get_string('autorenew_enabled', 'local_moderncommerce'),
                'autorenew_disabled' => get_string('autorenew_disabled', 'local_moderncommerce'),
                'nosubscribers' => get_string('nosubscribers', 'local_moderncommerce'),
                'action_cancel' => get_string('action_cancel', 'local_moderncommerce'),
                'action_reactivate' => get_string('action_reactivate', 'local_moderncommerce'),
                'action_suspend' => get_string('action_suspend', 'local_moderncommerce'),
                'action_renew' => get_string('action_renew', 'local_moderncommerce'),
                'confirmcancel' => get_string('cancelconfirm_admin', 'local_moderncommerce'),
                'confirmreactivate' => get_string('confirmreactivate', 'local_moderncommerce'),
                'confirmrenew' => get_string('confirmrenew', 'local_moderncommerce'),
                'enableautorenew' => get_string('enableautorenew', 'local_moderncommerce'),
                'disableautorenew' => get_string('disableautorenew', 'local_moderncommerce'),
                'subscriptiondetails' => get_string('subscriptiondetails', 'local_moderncommerce'),
                'currentplan' => get_string('currentplan', 'local_moderncommerce'),
                'renewalcount' => get_string('renewalcount', 'local_moderncommerce'),
                'trialenddate' => get_string('trialenddate', 'local_moderncommerce'),
                'graceenddate' => get_string('graceenddate', 'local_moderncommerce'),
                'accountcredit' => get_string('accountcredit', 'local_moderncommerce'),
                'adminactions' => get_string('adminactions', 'local_moderncommerce'),
                'coursesincluded' => get_string('coursesincluded', 'local_moderncommerce'),
                'nocourseaccess' => get_string('nocourseaccess', 'local_moderncommerce'),
                'expires' => get_string('expires', 'local_moderncommerce'),
                'fullaccess' => get_string('fullaccess', 'local_moderncommerce'),
                'subscriptionhistory' => get_string('subscriptionhistory', 'local_moderncommerce'),
                'nosubscriptionhistory' => get_string('nosubscriptionhistory', 'local_moderncommerce'),
                'date' => get_string('date', 'local_moderncommerce'),
                'created' => get_string('created', 'local_moderncommerce'),
                'updated' => get_string('updated', 'local_moderncommerce'),
                'action' => get_string('action', 'local_moderncommerce'),
                'amount' => get_string('amount', 'local_moderncommerce'),
                'notes' => get_string('notes', 'local_moderncommerce'),
            ],
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/subscriptions', [
        'subscriptionsreactconfig' => $config,
    ]);

    $actionshtml = admin_shell::action_group([
        [
            'type' => 'button',
            'label' => get_string('refresh'),
            'icon' => 'bi-arrow-clockwise',
            'attributes' => ['id' => 'moderncommerce-subscriptions-refresh'],
        ],
    ]);
} else {
    $contenthtml = local_moderncommerce_subscription_unavailable_card('bi-credit-card');
}

$shell = admin_shell::create($subscriptionnavkey)
    ->set_title($pagetitle)
    ->set_subtitle($available
        ? get_string($subscriptionsubtitlekey, 'local_moderncommerce')
        : get_string('subscriptionsunavailable_desc', 'local_moderncommerce'))
    ->set_content($contenthtml);
if ($actionshtml !== '') {
    $shell->set_actions($actionshtml);
}

$shellhtml = $shell->render($OUTPUT);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();

/**
 * Render the "add-on required" empty state used when local_moderncommerce is absent.
 *
 * @param string $icon Bootstrap icon class.
 * @return string
 */
function local_moderncommerce_subscription_unavailable_card(string $icon): string {
    return '<section class="mc-card"><div class="mc-card-body">'
        . '<div class="mc-empty mc-empty--centered">'
        . '<span class="mc-empty__icon"><i class="bi ' . s($icon) . '" aria-hidden="true"></i></span>'
        . '<p class="mc-empty__title">' . s(get_string('subscriptionsunavailable', 'local_moderncommerce')) . '</p>'
        . '<p class="mc-empty__text">' . s(get_string('subscriptionsunavailable_desc', 'local_moderncommerce')) . '</p>'
        . '</div></div></section>';
}

/**
 * Build the curated Bootstrap icon options used by the subscription feature editor.
 *
 * Stored feature icons omit the Bootstrap "bi-" prefix, so the option values are
 * normalized to names such as "check-circle".
 *
 * @return array<int, array{value: string, label: string, domain: string, keywords: string}>
 */
function local_moderncommerce_subscription_icon_options(): array {
    $options = [];

    $addoption = static function (string $icon, string $label, string $domain = 'General') use (&$options): void {
        $icon = trim($icon);
        $icon = preg_replace('/^bi\s+/', '', $icon);
        $icon = preg_replace('/^bi-/', '', $icon);
        $icon = clean_param($icon, PARAM_ALPHANUMEXT);

        if ($icon === '') {
            return;
        }

        $key = strtolower($icon);
        if (isset($options[$key])) {
            return;
        }

        $label = clean_param($label !== '' ? $label : ucwords(str_replace('-', ' ', $icon)), PARAM_TEXT);
        $domain = clean_param($domain !== '' ? $domain : 'General', PARAM_TEXT);

        $options[$key] = [
            'value' => $icon,
            'label' => $label,
            'domain' => $domain,
            'keywords' => trim($label . ' ' . $domain . ' ' . $icon),
        ];
    };

    $defaults = [
        ['check-circle', 'Check circle'],
        ['star', 'Star'],
        ['award', 'Award'],
        ['book', 'Book'],
        ['collection', 'Collection'],
        ['infinity', 'Infinity'],
        ['clock-history', 'Clock history'],
        ['laptop', 'Laptop'],
        ['headset', 'Headset'],
        ['graph-up-arrow', 'Progress chart'],
        ['patch-check', 'Verified badge'],
        ['lightning-charge', 'Lightning charge'],
    ];

    foreach ($defaults as $default) {
        $addoption($default[0], $default[1]);
    }

    $jsonpath = __DIR__ . '/../category-icons.json';
    if (is_readable($jsonpath)) {
        $data = json_decode((string) file_get_contents($jsonpath), true);
        if (is_array($data)) {
            foreach ($data as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $icon = (string) ($item['icon'] ?? '');
                $label = (string) ($item['label'] ?? '');
                $domain = (string) ($item['domain'] ?? 'General');
                $addoption($icon, $label, $domain);
            }
        }
    }

    uasort($options, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

    return array_values($options);
}
