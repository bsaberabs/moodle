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
 * Invisible image of the e-mails: loading it means the e-mail was opened.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Read by mail programs: no session, no login.
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

$id = optional_param('id', 0, PARAM_INT);
$key = optional_param('k', '', PARAM_ALPHANUM);
if ($id && $key !== '') {
    try {
        \local_oriskill\email_log::opened($id, $key);
    } catch (\Throwable $e) {
        // The image is always returned.
        debugging('E-mail opening: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
