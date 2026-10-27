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
 * Modern Commerce admin documentation center.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use local_moderncommerce\docs\admin_help_catalog;
use local_moderncommerce\services\admin_access_service;

require_login();

$context = context_system::instance();
if (!admin_access_service::can_access_admin($context)) {
    require_capability('local/moderncommerce:viewreports', $context);
}
$exiturl = admin_access_service::resolve_landing_url($context) ?? new moodle_url('/local/moderncommerce/learner/index.php');

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/help/index.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_secondary_navigation(false);
$PAGE->add_body_class('mc-admin-help-page');

$localstring = static function (string $identifier): string {
    return get_string($identifier, 'local_moderncommerce');
};

$title = $localstring('adminhelp_title');

$PAGE->set_title($title);
$PAGE->set_heading($title);

$labels = [
    'title' => $title,
    'eyebrow' => $localstring('adminhelp_eyebrow'),
    'intro' => $localstring('adminhelp_intro'),
    'sidebarnav' => $localstring('adminhelp_sidebarnav'),
    'searchlabel' => $localstring('adminhelp_searchlabel'),
    'searchplaceholder' => $localstring('adminhelp_searchplaceholder'),
    'alltopics' => $localstring('adminhelp_alltopics'),
    'results' => $localstring('adminhelp_results'),
    'noresults' => $localstring('adminhelp_noresults'),
    'clearsearch' => $localstring('adminhelp_clearsearch'),
    'copylink' => $localstring('adminhelp_copylink'),
    'copied' => $localstring('adminhelp_copied'),
    'opensource' => $localstring('adminhelp_opensource'),
    'exit' => $localstring('adminhelp_exit'),
    'readtime' => $localstring('adminhelp_readtime'),
];

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/admin_help',
    'id' => 'mc-admin-help-app',
    'class' => 'mch-root',
    'props' => [
        'groups' => admin_help_catalog::groups(),
        'documents' => admin_help_catalog::rendered_documents(),
        'exitUrl' => $exiturl->out(false),
        'labels' => $labels,
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_moderncommerce/react_mount', [
    'region' => 'mc-admin-help',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-journal-text',
]);

echo $OUTPUT->footer();
