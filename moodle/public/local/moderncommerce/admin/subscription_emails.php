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
 * Modern Commerce subscription email management route.
 *
 * The UI lives in Modern Commerce; the email template data lives in the
 * local_moderncommerce add-on and is consumed through its webservice
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
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/subscription_emails.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$pagetitle = $available
    ? get_string('subscriptionemails', 'local_moderncommerce')
    : get_string('subscriptionsunavailable', 'local_moderncommerce');
$pagesubtitle = $available
    ? get_string('subscriptionemails_desc', 'local_moderncommerce')
    : get_string('subscriptionsunavailable_desc', 'local_moderncommerce');
$PAGE->set_title($pagetitle);

$actionshtml = '';

if ($available) {
    $config = json_encode([
        'component' => '@moodle/lms/local_moderncommerce/subscription_emails_admin',
        'id' => 'moderncommerce-subscription-emails-admin-app',
        'class' => 'local-moderncommerce-subscription-emails-admin',
        'props' => [
            'listMethodName' => 'local_moderncommerce_subscription_list_email_templates',
            'saveMethodName' => 'local_moderncommerce_subscription_save_email_template',
            'labels' => [
                'title' => get_string('subscriptionemails', 'local_moderncommerce'),
                'description' => get_string('subscriptionemails_desc', 'local_moderncommerce'),
                'email' => get_string('email', 'local_moderncommerce'),
                'purpose' => get_string('description', 'local_moderncommerce'),
                'status' => get_string('status', 'local_moderncommerce'),
                'actions' => get_string('actions', 'local_moderncommerce'),
                'search' => get_string('search'),
                'searchplaceholder' => 'Search subscription emails',
                'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
                'defaulttemplate' => 'Default template',
                'template' => get_string('emailtemplate', 'local_moderncommerce'),
                'templatehint' => 'Choose a reusable template as the starting point, then adjust the subject and '
                    . 'body for this subscription email.',
                'modified' => get_string('modified', 'local_moderncommerce'),
                'showing' => get_string('showing', 'local_moderncommerce'),
                'perpage' => get_string('perpage', 'local_moderncommerce'),
                'previous' => get_string('previous'),
                'next' => get_string('next'),
                'page' => get_string('page', 'local_moderncommerce'),
                'emailsettings' => get_string('emailsettings', 'local_moderncommerce'),
                'backtolist' => get_string('backtolist', 'local_moderncommerce'),
                'enabled' => get_string('enabled', 'local_moderncommerce'),
                'disabled' => get_string('disabled', 'local_moderncommerce'),
                'customsubject' => get_string('subject', 'local_moderncommerce'),
                'custommessage' => get_string('body', 'local_moderncommerce'),
                'bodyvisual' => 'Visual editor',
                'bodyhtml' => 'HTML',
                'bodyeditormode' => 'Body editor mode',
                'formattoolbar' => 'Formatting toolbar',
                'formatbold' => 'Bold',
                'formatitalic' => 'Italic',
                'formatbulletlist' => 'Bulleted list',
                'formatnumberedlist' => 'Numbered list',
                'formatlink' => 'Link',
                'formatunlink' => 'Remove link',
                'formatclear' => 'Clear formatting',
                'linkurl' => 'Link URL',
                'placeholders' => get_string('placeholders', 'local_moderncommerce'),
                'insertplaceholder' => get_string('insertplaceholder', 'local_moderncommerce'),
                'copied' => get_string('copied', 'local_moderncommerce'),
                'loading' => get_string('loading', 'local_moderncommerce'),
                'edit' => get_string('edit'),
                'save' => get_string('save'),
                'cancel' => get_string('cancel'),
            ],
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/subscription_emails', [
        'subscriptionemailsreactconfig' => $config,
    ]);

    $actionshtml = admin_shell::action_group([
        [
            'type' => 'button',
            'label' => get_string('refresh'),
            'icon' => 'bi-arrow-clockwise',
            'attributes' => ['id' => 'moderncommerce-subscription-emails-refresh'],
        ],
    ]);
} else {
    $contenthtml = local_moderncommerce_subscription_unavailable_card('bi-envelope-open');
}

$shell = admin_shell::create('subscriptionemails')
    ->set_title($pagetitle)
    ->set_subtitle($pagesubtitle)
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
