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
 * Shared payment webhook endpoint.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// The generic webhook must be callable without a session.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
@define('NO_MOODLE_COOKIES', true);
// phpcs:enable moodle.Files.MoodleInternal.MoodleInternalGlobalState

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\payment\gateway_manager;

$gatewayid = optional_param('gateway', '', PARAM_ALPHANUMEXT);
if ($gatewayid === '') {
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('/^([a-z0-9]+)_webhook\.php$/', $script, $matches)) {
        $gatewayid = $matches[1];
    }
}
if ($gatewayid === '') {
    $gatewayid = required_param('gateway', PARAM_ALPHANUMEXT);
}
$gatewayid = gateway_manager::normalize_gateway_id($gatewayid);

$input = file_get_contents('php://input');
$payload = json_decode($input, true);
$headers = function_exists('getallheaders') ? getallheaders() : [];

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => get_string('invalidwebhookpayload', 'local_moderncommerce')]);
    exit;
}

try {
    $success = gateway_manager::process_webhook($gatewayid, $payload, $input, $headers);
    if ($success) {
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => get_string('webhookprocessingerror', 'local_moderncommerce')]);
    }
} catch (Exception $e) {
    debugging('Webhook error for ' . $gatewayid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => get_string('webhookprocessingerror', 'local_moderncommerce')]);
}

exit;
