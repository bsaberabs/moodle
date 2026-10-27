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
 * Webhook configuration admin page (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\output\admin_shell;

require_login();

$context = context_system::instance();
require_capability('local/moderncommerce:configuregateways', $context);
$canconfiguresettings = has_capability('local/moderncommerce:managesettings', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/webhooks.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('pluginname', 'local_moderncommerce') . ': ' . get_string('webhooks', 'local_moderncommerce'));

$webhooksreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/webhooks_admin',
    'id' => 'moderncommerce-webhooks-admin-app',
    'class' => 'local-moderncommerce-webhooks-admin',
    'props' => local_moderncommerce_webhooks_react_props($canconfiguresettings),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/webhooks', [
    'webhooksreactconfig' => $webhooksreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'button',
        'label' => get_string('refresh'),
        'icon' => 'bi-arrow-clockwise',
        'attributes' => ['id' => 'moderncommerce-webhooks-refresh'],
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'webhooks',
    get_string('webhooks', 'local_moderncommerce'),
    $contenthtml,
    get_string('webhooks_desc', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
