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
 * Bundle/program enrollment key management (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\keys_page;

$bundleid = optional_param('bundleid', 0, PARAM_INT);
$bundleid = $bundleid ?: optional_param('filterbundleid', 0, PARAM_INT);

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:generatekeys', $context);

$bundlename = '';
if ($bundleid > 0) {
    $bundlename = (string) $DB->get_field_select(
        'local_moderncommerce_products',
        'name',
        'id = :id AND producttype IN (:bundle, :program)',
        ['id' => $bundleid, 'bundle' => 'bundle', 'program' => 'program']
    );
    if ($bundlename === '') {
        $bundleid = 0;
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/bundle_keys.php', $bundleid ? ['bundleid' => $bundleid] : []));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('managebundlekeys', 'local_moderncommerce'));

$shellhtml = keys_page::render(
    $OUTPUT,
    'product',
    $bundleid,
    $bundlename ? format_string($bundlename) : '',
    'bundlekeys',
    get_string('managebundlekeys', 'local_moderncommerce'),
    get_string('managebundlekeys_desc', 'local_moderncommerce'),
    'bundle',
    true
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
