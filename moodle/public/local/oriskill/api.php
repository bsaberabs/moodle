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
 * REST API of the trainers' spaces: /local/oriskill/api.php/contacts with the header "Authorization: Bearer <key>".
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);
require_once(__DIR__ . '/../../config.php');

$path = (string) ($_SERVER['PATH_INFO'] ?? '');
if ($path === '' && isset($_GET['path'])) {
    $path = (string) $_GET['path'];
}
$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($authorization === '' && function_exists('getallheaders')) {
    // Apache does not always hand the Authorization header to PHP in $_SERVER.
    $authorization = (string) (array_change_key_case(getallheaders())['authorization'] ?? '');
}
// Some servers drop the Authorization header: the key can also come in X-Api-Key.
if ($authorization === '' && !empty($_SERVER['HTTP_X_API_KEY'])) {
    $authorization = 'Bearer ' . $_SERVER['HTTP_X_API_KEY'];
}
$body = json_decode((string) file_get_contents('php://input'), true);
$query = array_map(fn($v) => is_string($v) ? clean_param($v, PARAM_TEXT) : '', $_GET);
[$status, $data] = \local_oriskill\api_service::handle(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), $path,
    $authorization, $query, is_array($body) ? $body : []);
http_response_code($status);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
