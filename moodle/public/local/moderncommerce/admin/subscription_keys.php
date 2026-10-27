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
 * Modern Commerce subscription key management route.
 *
 * The UI lives in Modern Commerce; the data and business logic live in the
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

$context = context_system::instance();
require_login();

require_capability('local/moderncommerce:managesubscriptionplans', $context);
// Subscriptions are core; the React admin always renders.
$available = true;

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/subscription_keys.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$pagetitle = $available
    ? get_string('subscriptionkeys', 'local_moderncommerce')
    : get_string('subscriptionsunavailable', 'local_moderncommerce');
$PAGE->set_title($pagetitle);

$actionshtml = '';

if ($available) {
    $config = json_encode([
        'component' => '@moodle/lms/local_moderncommerce/subscription_keys_admin',
        'id' => 'moderncommerce-subscription-keys-admin-app',
        'class' => 'local-moderncommerce-subscription-keys-admin',
        'props' => [
            'listMethodName' => 'local_moderncommerce_subscription_list_keys',
            'generateMethodName' => 'local_moderncommerce_subscription_generate_keys',
            'actionMethodName' => 'local_moderncommerce_subscription_key_action',
            'statusOptions' => [
                ['value' => 'active', 'label' => get_string('active', 'local_moderncommerce')],
                ['value' => 'disabled', 'label' => get_string('disabled', 'local_moderncommerce')],
            ],
            'perPageOptions' => [10, 20, 50, 100],
            'labels' => [
                'title' => get_string('subscriptionkeys', 'local_moderncommerce'),
                'generatekeys' => get_string('generatekeys', 'local_moderncommerce'),
                'cancel' => get_string('cancel'),
                'selectplan' => get_string('selectplan', 'local_moderncommerce'),
                'numberofkeys' => get_string('numberofkeys', 'local_moderncommerce'),
                'maxusesperkey' => get_string('maxusesperkey', 'local_moderncommerce'),
                'zerounlimited' => get_string('zerounlimited', 'local_moderncommerce'),
                'maxusesperuser' => get_string('maxusesperuser', 'local_moderncommerce'),
                'subscriptiondurationdays' => get_string('subscriptiondurationdays', 'local_moderncommerce'),
                'zerouseplandefault' => get_string('zerouseplandefault', 'local_moderncommerce'),
                'validfrom' => get_string('validfrom', 'local_moderncommerce'),
                'validuntil' => get_string('validuntil', 'local_moderncommerce'),
                'batchname' => get_string('batchname', 'local_moderncommerce'),
                'optionalbatchname' => get_string('optionalbatchname', 'local_moderncommerce'),
                'notes' => get_string('notes', 'local_moderncommerce'),
                'optionalnotes' => get_string('optionalnotes', 'local_moderncommerce'),
                'generatedkeys' => get_string('generatedkeys', 'local_moderncommerce'),
                'copied' => get_string('copied', 'local_moderncommerce'),
                'exportkeys' => get_string('exportkeys', 'local_moderncommerce'),
                'totalkeys' => get_string('totalkeys', 'local_moderncommerce'),
                'activekeys' => get_string('activekeys', 'local_moderncommerce'),
                'usedkeys' => get_string('usedkeys', 'local_moderncommerce'),
                'disabledkeys' => get_string('disabledkeys', 'local_moderncommerce'),
                'search' => get_string('search'),
                'searchkeys' => get_string('searchkeys', 'local_moderncommerce'),
                'plan' => get_string('plan', 'local_moderncommerce'),
                'allplans' => get_string('allplans', 'local_moderncommerce'),
                'status' => get_string('status', 'local_moderncommerce'),
                'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
                'perpage' => get_string('perpage', 'local_moderncommerce'),
                'showing' => get_string('showing', 'local_moderncommerce'),
                'keycode' => get_string('keycode', 'local_moderncommerce'),
                'usage' => get_string('usage', 'local_moderncommerce'),
                'expires' => get_string('expires', 'local_moderncommerce'),
                'actions' => get_string('actions', 'local_moderncommerce'),
                'nokeys' => get_string('nokeysyet', 'local_moderncommerce'),
                'nokeysdesc' => get_string('clicktogeneratekeys', 'local_moderncommerce'),
                'noresults' => get_string('noresults', 'local_moderncommerce'),
                'copy' => get_string('copykey', 'local_moderncommerce'),
                'batch' => get_string('batch', 'local_moderncommerce'),
                'neverexpires' => get_string('neverexpires', 'local_moderncommerce'),
                'deactivate' => get_string('deactivate', 'local_moderncommerce'),
                'activate' => get_string('activate', 'local_moderncommerce'),
                'delete' => get_string('delete'),
                'cannotdeletekeyinuse' => get_string('cannotdeletekeyinuse', 'local_moderncommerce'),
                'loading' => get_string('loading', 'local_moderncommerce'),
                'previous' => get_string('previous'),
                'page' => get_string('page', 'local_moderncommerce'),
                'next' => get_string('next'),
                'deleteconfirm' => get_string('confirmdeletekey', 'local_moderncommerce'),
                'selectplanfirst' => get_string('selectplantogeneratekeys', 'local_moderncommerce'),
            ],
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/subscription_keys', [
        'subscriptionkeysreactconfig' => $config,
    ]);

    $actionshtml = admin_shell::action_group([
        [
            'type' => 'button',
            'label' => get_string('refresh'),
            'icon' => 'bi-arrow-clockwise',
            'attributes' => ['id' => 'moderncommerce-subscription-keys-refresh'],
        ],
    ]);
} else {
    $contenthtml = local_moderncommerce_subscription_unavailable_card('bi-key');
}

$shell = admin_shell::create('subscriptionkeys')
    ->set_title($pagetitle)
    ->set_subtitle($available
        ? get_string('managekeysforplans', 'local_moderncommerce')
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
