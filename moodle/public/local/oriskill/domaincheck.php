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
 * Asked by the proxy before it requests a certificate for a domain: 200 for an active trainer's domain, 404 otherwise.
 *
 * Without this check, anyone pointing a domain at the server would make the proxy request certificates for it.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

$domain = optional_param('domain', '', PARAM_HOST);
$found = $domain !== '' && \local_oriskill\creator_service::creator_by_domain($domain);
http_response_code($found ? 200 : 404);
header('Content-Type: text/plain; charset=utf-8');
echo $found ? 'ok' : 'unknown';
