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
 * Modern Notify delivery dashboard route (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

require_login();

$context = context_system::instance();
require_capability('local/moderncommerce:managenotifications', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/notifications.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('addon_modernnotify', 'local_moderncommerce'));

$settingsurl = has_capability('local/moderncommerce:managesettings', $context)
    ? (new moodle_url('/local/moderncommerce/admin/settings.php', ['tab' => 'notifications']))->out(false)
    : '';

$notificationsreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/notifications_admin',
    'id' => 'moderncommerce-notifications-admin-app',
    'class' => 'local-moderncommerce-notifications-admin',
    'props' => [
        'methods' => [
            'get' => 'local_moderncommerce_admin_get_notifications',
            'save' => 'local_moderncommerce_admin_save_notification_settings',
        ],
        'settingsUrl' => $settingsurl,
        'labels' => [
            'title' => get_string('addon_modernnotify', 'local_moderncommerce'),
            'addonmissing' => get_string('addonmissing', 'local_moderncommerce'),
            'addonmissingdesc' => get_string('addon_modernnotify_desc', 'local_moderncommerce'),
            'pending' => get_string('pending', 'local_moderncommerce'),
            'sent' => get_string('sent', 'local_moderncommerce'),
            'failed' => get_string('failed', 'local_moderncommerce'),
            'suppressed' => get_string('suppressed', 'local_moderncommerce'),
            'channelsettings' => get_string('channelsettings', 'local_moderncommerce'),
            'channelsettingsdesc' => get_string('notifydeliveryheading_desc', 'local_moderncommerce'),
            'deliveryheading' => get_string('notifydeliveryheading', 'local_moderncommerce'),
            'batchsize' => get_string('notificationbatchsize', 'local_moderncommerce'),
            'batchsizedesc' => get_string('notify_batchsize_desc', 'local_moderncommerce'),
            'slackheading' => get_string('notify_slackheading', 'local_moderncommerce'),
            'slackheadingdesc' => get_string('notify_slackheading_desc', 'local_moderncommerce'),
            'slackenabled' => get_string('slackenabled', 'local_moderncommerce'),
            'slackurldesc' => get_string('notify_slackurl_desc', 'local_moderncommerce'),
            'teamsheading' => get_string('notify_teamsheading', 'local_moderncommerce'),
            'teamsheadingdesc' => get_string('notify_teamsheading_desc', 'local_moderncommerce'),
            'teamsenabled' => get_string('teamsenabled', 'local_moderncommerce'),
            'teamsurldesc' => get_string('notify_teamsurl_desc', 'local_moderncommerce'),
            'webhookurl' => get_string('webhookurl', 'local_moderncommerce'),
            'signingsecret' => get_string('signingsecret', 'local_moderncommerce'),
            'signingsecretdesc' => get_string('notify_slacksecret_desc', 'local_moderncommerce'),
            'secretconfigured' => get_string('secretconfigured_short', 'local_moderncommerce'),
            'settings' => get_string('settings', 'local_moderncommerce'),
            'saving' => get_string('saving', 'local_moderncommerce'),
            'savechanges' => get_string('savechanges', 'local_moderncommerce'),
            'recentactivity' => get_string('recentactivity', 'local_moderncommerce'),
            'search' => get_string('search', 'local_moderncommerce'),
            'allchannels' => get_string('allchannels', 'local_moderncommerce'),
            'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
            'time' => get_string('time', 'local_moderncommerce'),
            'event' => get_string('event', 'local_moderncommerce'),
            'channel' => get_string('channel', 'local_moderncommerce'),
            'recipient' => get_string('recipient', 'local_moderncommerce'),
            'result' => get_string('result', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'nodeliveries' => get_string('nodeliveries', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'previous' => get_string('previous', 'local_moderncommerce'),
            'next' => get_string('next', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/notifications', [
    'notificationsreactconfig' => $notificationsreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'button',
        'label' => get_string('refresh'),
        'icon' => 'bi-arrow-clockwise',
        'attributes' => ['id' => 'moderncommerce-notifications-refresh'],
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'notifications',
    get_string('addon_modernnotify', 'local_moderncommerce'),
    $contenthtml,
    get_string('addon_modernnotify_desc', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
