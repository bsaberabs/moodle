<?php
// This file is part of Moodle - https://moodle.org/
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
 * Download an invoice or credit note in a trainer's name (its buyer, its trainer or an administrator).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\billing_service;

$id = required_param('id', PARAM_INT);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oriskill/invoice.php', ['id' => $id]));
require_login(null, false);

$document = $DB->get_record('local_oriskill_invoices', ['id' => $id], '*', MUST_EXIST);
if (!billing_service::can_view($document)) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('invoice:title', 'local_oriskill'));
}
[$filename, $content] = billing_service::pdf($document);
send_file($content, $filename, 0, 0, true, true, 'application/pdf');
