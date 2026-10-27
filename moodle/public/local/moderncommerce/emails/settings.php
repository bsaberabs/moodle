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
 * Legacy email notification settings editor route.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\external\emails\list_emails;

$type = optional_param('type', '', PARAM_ALPHANUMEXT);

$context = context_system::instance();
require_login();
require_capability('moodle/site:config', $context);

// Only open the editor for a known type; otherwise fall back to the list.
if ($type !== '' && !array_key_exists($type, list_emails::types())) {
    $type = '';
}

$params = [];
if ($type !== '') {
    $params['type'] = $type;
}

redirect(new moodle_url('/local/moderncommerce/admin/email_templates.php', $params));
