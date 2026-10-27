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
 * Create or edit a bundle/program (React builder).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\bundles_page;

$bundleid = optional_param('bundleid', 0, PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:managecourses', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/bundle_form.php', $bundleid ? ['bundleid' => $bundleid] : []));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title($bundleid
    ? get_string('editbundleprogram', 'local_moderncommerce')
    : get_string('createbundleprogram', 'local_moderncommerce'));

// Open the builder directly: a positive ID edits that bundle, -1 opens a fresh create form.
$shellhtml = bundles_page::render($OUTPUT, $bundleid > 0 ? $bundleid : -1);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
