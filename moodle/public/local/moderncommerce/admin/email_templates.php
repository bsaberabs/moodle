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
 * Modern Commerce email templates management route.
 *
 * The UI and template data live in Modern Commerce core.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\email_templates_page;

$type = optional_param('type', '', PARAM_ALPHANUMEXT);

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:manageemailtemplates', $context);

$urlparams = [];
if ($type !== '') {
    $urlparams['type'] = $type;
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/email_templates.php', $urlparams));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');

$PAGE->set_title(get_string('emailtemplates', 'local_moderncommerce'));

// Build the page HTML (queues React JS + bootstrap-icons CSS) BEFORE the header.
$shellhtml = email_templates_page::render($OUTPUT, $type);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
