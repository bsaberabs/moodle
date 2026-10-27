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
 * Design-system component gallery — living reference for the mc-* design system.
 *
 * Static showcase (no data layer). Rendered inside the admin console shell.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\output\admin_shell;

$context = context_system::instance();
require_login();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/styleguide.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title('Design system');

$templatecontext = [
    'colors' => [
        ['name' => 'Primary', 'var' => '--mc-primary'],
        ['name' => 'Primary hover', 'var' => '--mc-primary-hover'],
        ['name' => 'Primary light', 'var' => '--mc-primary-light'],
        ['name' => 'Page background', 'var' => '--mc-page-bg'],
        ['name' => 'Surface', 'var' => '--mc-surface'],
        ['name' => 'Border', 'var' => '--mc-border'],
        ['name' => 'Text', 'var' => '--mc-text'],
        ['name' => 'Text muted', 'var' => '--mc-text-muted'],
        ['name' => 'Success', 'var' => '--mc-success'],
        ['name' => 'Warning', 'var' => '--mc-warning'],
        ['name' => 'Danger', 'var' => '--mc-danger'],
        ['name' => 'Info', 'var' => '--mc-info'],
        ['name' => 'Neutral bg', 'var' => '--mc-neutral-bg'],
        ['name' => 'Sidebar', 'var' => '--mc-sidebar-bg'],
    ],
    'statuses' => [
        ['key' => 'success', 'label' => 'Active'],
        ['key' => 'warning', 'label' => 'Pending'],
        ['key' => 'danger', 'label' => 'Failed'],
        ['key' => 'info', 'label' => 'Processing'],
        ['key' => 'primary', 'label' => 'Featured'],
        ['key' => 'neutral', 'label' => 'Inactive'],
    ],
    'orders' => [
        [
            'number' => '#1042',
            'customer' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'statuskey' => 'success',
            'status' => 'Paid',
            'total' => '$129.00',
        ],
        [
            'number' => '#1041',
            'customer' => 'Alan Turing',
            'email' => 'alan@example.com',
            'statuskey' => 'warning',
            'status' => 'Pending',
            'total' => '$49.00',
        ],
        [
            'number' => '#1040',
            'customer' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'statuskey' => 'danger',
            'status' => 'Failed',
            'total' => '$0.00',
        ],
    ],
];

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/styleguide', $templatecontext);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'link',
        'url' => new moodle_url('/local/moderncommerce/icons_bootstrap.php'),
        'label' => 'Icon library',
        'icon' => 'bi-emoji-smile',
    ],
]);

// Build the shell before the header so its CSS requirements register in time.
$shellhtml = admin_shell::render_page(
    $OUTPUT,
    '',
    'Design system',
    $contenthtml,
    'Component gallery for the mc-* design system',
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
