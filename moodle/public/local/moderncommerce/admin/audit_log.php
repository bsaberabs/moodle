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
 * Audit log ledger (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\ledger_page;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:viewauditlog', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/audit_log.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('auditlog', 'local_moderncommerce'));

$shellhtml = ledger_page::render(
    $OUTPUT,
    'auditlog',
    'local_moderncommerce_admin_list_audit_log',
    get_string('auditlog', 'local_moderncommerce'),
    get_string('auditlogdesc', 'local_moderncommerce'),
    [
        ['value' => 'success', 'label' => get_string('success', 'local_moderncommerce')],
        ['value' => 'failed', 'label' => get_string('failed', 'local_moderncommerce')],
        ['value' => 'warning', 'label' => get_string('warning', 'local_moderncommerce')],
    ]
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
