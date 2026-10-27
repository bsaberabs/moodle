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
 * Shared payment initialization endpoint.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\payment\gateway_manager;
use local_moderncommerce\api\order_api;
use local_moderncommerce\services\order_validation_service;

require_login();

$gatewayid = required_param('gateway', PARAM_ALPHANUMEXT);
$orderid = required_param('orderid', PARAM_INT);
$gatewayid = gateway_manager::normalize_gateway_id($gatewayid);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(gateway_manager::init_url($gatewayid, $orderid));
$PAGE->set_pagelayout('base');

$learnerappurl = static function (string $route = 'dashboard'): string {
    return (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/' . ltrim($route, '/');
};

$order = order_api::get_order((int) $orderid);

if ((int)$order->userid !== (int)$USER->id) {
    throw new moodle_exception('accessdenied', 'admin');
}

if ($order->status !== 'pending') {
    redirect(
        $learnerappurl('orders/' . (int)$orderid),
        get_string('orderalreadyprocessed', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$validation = order_validation_service::validate_order_for_payment((int)$order->id, (int)$order->userid);
if (!$validation['valid'] && $validation['cancel_order']) {
    order_validation_service::cancel_duplicate_order((int)$order->id, $validation['errors']);
    redirect(
        $learnerappurl('dashboard'),
        get_string('ordercancelled_duplicate', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

if ($gatewayid === 'manual') {
    redirect(
        new moodle_url('/local/moderncommerce/success.php', ['orderid' => (int)$order->id]),
        get_string('ordercreated', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($gatewayid === 'enrollkey') {
    redirect($learnerappurl('redeem?orderid=' . (int)$order->id));
}

try {
    $payment = gateway_manager::initialize_payment($gatewayid, $order);
    $redirecturl = $payment['authorization_url'] ?? ($payment['redirect_url'] ?? null);

    if (empty($redirecturl)) {
        throw new moodle_exception('paymentinitfailed', 'local_moderncommerce');
    }

    redirect($redirecturl);
} catch (Throwable $e) {
    debugging('Payment init error for ' . $gatewayid . ' order ' . $orderid . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
    redirect(
        $learnerappurl('orders/' . (int)$orderid),
        get_string('paymentfailed', 'local_moderncommerce') . ': ' . $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
