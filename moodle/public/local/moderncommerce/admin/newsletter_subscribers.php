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
 * Modern Commerce newsletter subscribers route.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:viewnewsletter', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/newsletter_subscribers.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$pagetitle = get_string('newslettersubscribers', 'local_moderncommerce');
$PAGE->set_title($pagetitle);

$config = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/newsletter_subscribers_admin',
    'id' => 'moderncommerce-newsletter-subscribers-admin-app',
    'class' => 'local-moderncommerce-newsletter-subscribers-admin',
    'props' => [
        'methods' => [
            'list' => 'local_moderncommerce_newsletter_list_subscribers',
            'delete' => 'local_moderncommerce_newsletter_delete_subscriber',
            'export' => 'local_moderncommerce_newsletter_export_subscribers',
        ],
        'sortOptions' => [
            ['value' => 'newest', 'label' => get_string('contactsort_newest', 'local_moderncommerce')],
            ['value' => 'oldest', 'label' => get_string('contactsort_oldest', 'local_moderncommerce')],
            ['value' => 'email_asc', 'label' => get_string('newsletter_sort_email_asc', 'local_moderncommerce')],
            ['value' => 'email_desc', 'label' => get_string('newsletter_sort_email_desc', 'local_moderncommerce')],
            ['value' => 'source_asc', 'label' => get_string('newsletter_sort_source_asc', 'local_moderncommerce')],
        ],
        'perPageOptions' => [10, 25, 50, 100],
        'canManage' => has_capability('local/moderncommerce:managenewsletter', $context),
        'labels' => [
            'title' => get_string('newslettersubscribers', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchplaceholder' => get_string('newslettersearchplaceholder', 'local_moderncommerce'),
            'source' => get_string('newslettersource', 'local_moderncommerce'),
            'allsources' => get_string('newsletterallsources', 'local_moderncommerce'),
            'sortby' => get_string('contactsortby', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'page' => get_string('page', 'local_moderncommerce'),
            'next' => get_string('next'),
            'email' => get_string('email'),
            'subscriber' => get_string('newslettersubscriber', 'local_moderncommerce'),
            'user' => get_string('user'),
            'subscribedat' => get_string('newslettersubscribedat', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'delete' => get_string('delete'),
            'exportcsv' => get_string('newsletterexportcsv', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'nosubscribers' => get_string('newsletternosubscribers', 'local_moderncommerce'),
            'total' => get_string('newslettertotal', 'local_moderncommerce'),
            'thisweek' => get_string('newsletterthisweek', 'local_moderncommerce'),
            'knownusers' => get_string('newsletterknownusers', 'local_moderncommerce'),
            'guests' => get_string('newsletterguests', 'local_moderncommerce'),
            'deletesubscriberconfirm' => get_string('newsletterdeletesubscriberconfirm', 'local_moderncommerce'),
            'exported' => get_string('newsletterexported', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/newsletter_subscribers', [
    'newslettersubscribersreactconfig' => $config,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'button',
        'label' => get_string('refresh'),
        'icon' => 'bi-arrow-clockwise',
        'attributes' => ['id' => 'moderncommerce-newsletter-subscribers-refresh'],
    ],
]);

$shell = admin_shell::create('newsletter')
    ->set_title($pagetitle)
    ->set_subtitle(get_string('newslettersubscribers_desc', 'local_moderncommerce'))
    ->set_content($contenthtml)
    ->set_actions($actionshtml);

$shellhtml = $shell->render($OUTPUT);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
