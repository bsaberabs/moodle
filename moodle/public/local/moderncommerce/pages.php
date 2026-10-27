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
 * Pages index route utility, rendered inside the admin console shell.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_moderncommerce\output\admin_shell;

$context = context_system::instance();
require_login();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/pages.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_pagetype('local-moderncommerce-pages');
$PAGE->set_title(get_string('pagesindex', 'local_moderncommerce'));

$groups = [
    [
        'title' => 'Catalog & Details',
        'items' => [
            ['name' => 'Catalog', 'url' => new moodle_url('/local/moderncommerce/index.php')],
            [
                'name' => 'Course Details (requires id)',
                'url' => new moodle_url('/local/moderncommerce/course_details.php', ['id' => 123]),
            ],
            [
                'name' => 'Bundle Details (requires id)',
                'url' => new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => 123]),
            ],
            ['name' => 'My Courses', 'url' => new moodle_url('/local/moderncommerce/mycourses.php')],
        ],
    ],
    [
        'title' => 'Commerce',
        'items' => [
            ['name' => 'Cart', 'url' => new moodle_url('/local/moderncommerce/cart.php')],
            ['name' => 'Checkout', 'url' => new moodle_url('/local/moderncommerce/checkout.php')],
            ['name' => 'Order (requires id)', 'url' => new moodle_url('/local/moderncommerce/order.php', ['id' => 1001])],
            ['name' => 'Orders', 'url' => new moodle_url('/local/moderncommerce/orders.php')],
        ],
    ],
    [
        'title' => 'Redemption',
        'items' => [
            ['name' => 'Redeem (single key)', 'url' => new moodle_url('/local/moderncommerce/redeem.php')],
            ['name' => 'Redeem Bundle', 'url' => new moodle_url('/local/moderncommerce/redeem_bundle.php')],
            ['name' => 'Redeem Multiple', 'url' => new moodle_url('/local/moderncommerce/redeem_multiple.php')],
        ],
    ],
    [
        'title' => 'Admin & Setup',
        'items' => [
            ['name' => 'Settings', 'url' => new moodle_url('/local/moderncommerce/admin/settings.php')],
            ['name' => 'Design System (component gallery)', 'url' => new moodle_url('/local/moderncommerce/styleguide.php')],
        ],
    ],
];

// Build the page content (captured, then rendered inside the admin shell).
ob_start();
foreach ($groups as $group) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-header');
    echo html_writer::tag('strong', format_string($group['title']));
    echo html_writer::end_div();

    echo html_writer::start_div('card-body');
    echo html_writer::start_tag('ul', ['class' => 'list-group list-group-flush']);

    foreach ($group['items'] as $item) {
        $link = html_writer::link($item['url'], format_string($item['name']), ['class' => 'stretched-link']);
        echo html_writer::tag(
            'li',
            '<i class="bi bi-link-45deg text-primary me-2" aria-hidden="true"></i>' . $link,
            ['class' => 'list-group-item d-flex align-items-center']
        );
    }

    echo html_writer::end_tag('ul');
    echo html_writer::end_div();
    echo html_writer::end_div();
}
$contenthtml = ob_get_clean();

// Build the shell before the header so its CSS requirements register in time.
$shellhtml = admin_shell::render_page(
    $OUTPUT,
    '',
    get_string('pagesindex', 'local_moderncommerce'),
    $contenthtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
