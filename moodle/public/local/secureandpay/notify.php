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
 * Server-to-server notification URL (urlNotify) called by SecureAndPay.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

$data = optional_param('data', '', PARAM_RAW_TRIMMED);
$signature = optional_param('signature', '', PARAM_ALPHANUM);

try {
    $outcome = \local_secureandpay\notification_handler::handle($data, $signature);
    echo 'OK ' . $outcome;
} catch (\Throwable $e) {
    debugging('SecureAndPay notification rejected: ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(400);
    echo 'ERROR';
}
