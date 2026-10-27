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
 * Modern Commerce contact submissions route.
 *
 * Contact capture is core to Modern Commerce: the data, email engine, and public
 * submit/reply endpoints all live in this plugin, and this page renders the React
 * admin UI against the contact webservice endpoints.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:viewcontacts', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/contacts.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$pagetitle = get_string('contactsubmissions', 'local_moderncommerce');
$PAGE->set_title($pagetitle);

$config = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/contacts_admin',
    'id' => 'moderncommerce-contacts-admin-app',
    'class' => 'local-moderncommerce-contacts-admin',
    'props' => [
        'methods' => [
            'list' => 'local_moderncommerce_get_contacts',
            'get' => 'local_moderncommerce_get_contact',
            'reply' => 'local_moderncommerce_reply_to_contact',
            'setStatus' => 'local_moderncommerce_set_contact_status',
        ],
        'sortOptions' => [
            ['value' => 'newest', 'label' => get_string('contactsort_newest', 'local_moderncommerce')],
            ['value' => 'oldest', 'label' => get_string('contactsort_oldest', 'local_moderncommerce')],
            ['value' => 'name_asc', 'label' => get_string('contactsort_name_asc', 'local_moderncommerce')],
            ['value' => 'name_desc', 'label' => get_string('contactsort_name_desc', 'local_moderncommerce')],
        ],
        'perPageOptions' => [10, 25, 50, 100],
        'labels' => [
            'title' => get_string('contactsubmissions', 'local_moderncommerce'),
            'conversation' => get_string('contactconversation', 'local_moderncommerce'),
            'backtolist' => get_string('back'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchplaceholder' => get_string('contactsearchplaceholder', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
            'sortby' => get_string('contactsortby', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'from' => get_string('contactfrom', 'local_moderncommerce'),
            'subject' => get_string('contactsubject', 'local_moderncommerce'),
            'received' => get_string('contactreceived', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'nocontacts' => get_string('contactnocontacts', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'view' => get_string('view'),
            'previous' => get_string('previous'),
            'page' => get_string('page', 'local_moderncommerce'),
            'next' => get_string('next'),
            'total' => get_string('contacttotal', 'local_moderncommerce'),
            'unread' => get_string('contactunread', 'local_moderncommerce'),
            'replied' => get_string('contactreplied', 'local_moderncommerce'),
            'thisweek' => get_string('contactthisweek', 'local_moderncommerce'),
            'reply' => get_string('contactreply', 'local_moderncommerce'),
            'replyplaceholder' => get_string('contactreplyplaceholder', 'local_moderncommerce'),
            'sendreply' => get_string('contactsendreply', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/contacts', [
    'contactsreactconfig' => $config,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'button',
        'label' => get_string('refresh'),
        'icon' => 'bi-arrow-clockwise',
        'attributes' => ['id' => 'moderncommerce-contacts-refresh'],
    ],
]);

$shell = admin_shell::create('contact')
    ->set_title($pagetitle)
    ->set_subtitle(get_string('contactsubmissions_desc', 'local_moderncommerce'))
    ->set_content($contenthtml)
    ->set_actions($actionshtml);

$shellhtml = $shell->render($OUTPUT);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
