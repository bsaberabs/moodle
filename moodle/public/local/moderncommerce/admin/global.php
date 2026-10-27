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
 * Modern Commerce global (all-pages) widget manager.
 *
 * Mounts the storefront widget editor against the site-wide `global` scope. Widgets
 * placed here render on EVERY storefront page (a band above and a footer band below
 * each page's own content).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\output\public_page;
use local_moderncommerce\storefront\zones;

require_login();
$context = context_system::instance();
require_capability('local/moderncommerce:managestorefront', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/global.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_title(get_string('storeglobal', 'local_moderncommerce'));

// Mount the React storefront editor against the global scope, editing forced on for
// managers (this is an admin tool, not a public page with Moodle's edit-mode toggle).
$mount = public_page::render_widget_mount(zones::PAGE_GLOBAL, $OUTPUT, [
    'editing' => true,
    'canmanage' => true,
    'title' => get_string('storeglobal', 'local_moderncommerce'),
    'icon' => 'bi-globe2',
    'region' => 'moderncommerce-global-widgets',
]);

$intro = html_writer::div(
    html_writer::tag('i', '', ['class' => 'bi bi-info-circle mc-alert__icon', 'aria-hidden' => 'true']) .
        html_writer::div(get_string('storeglobal_intro', 'local_moderncommerce'), 'mc-alert__body'),
    'mc-alert mc-alert--info mb-3'
);
$contenthtml = $intro . $mount;

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'storepages',
    get_string('storeglobal', 'local_moderncommerce'),
    $contenthtml,
    get_string('storeglobal_desc', 'local_moderncommerce')
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
