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
 * Shared payment return endpoint.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\api\order_api;
use local_moderncommerce\payment\gateway_manager;
use local_moderncommerce\services\order_validation_service;

require_login();

$gatewayid = optional_param('gateway', '', PARAM_ALPHANUMEXT);
if ($gatewayid === '') {
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('/^([a-z0-9]+)_callback\.php$/', $script, $matches)) {
        $gatewayid = $matches[1];
    }
}
if ($gatewayid === '') {
    $gatewayid = required_param('gateway', PARAM_ALPHANUMEXT);
}
$gatewayid = gateway_manager::normalize_gateway_id($gatewayid);
$params = array_merge($_GET, $_POST);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(gateway_manager::callback_url($gatewayid));
$PAGE->set_pagelayout('base');

$learnerappurl = static function (string $route = 'dashboard'): string {
    return (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/' . ltrim($route, '/');
};

ob_start();

try {
    if (($params['status'] ?? '') === 'cancelled') {
        ob_end_clean();
        redirect(
            $learnerappurl('checkout'),
            get_string('paymentcancelled', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $result = gateway_manager::process_return($gatewayid, $params);
    $order = null;

    if (!empty($result->orderid)) {
        $order = order_api::get_order((int) $result->orderid);
    } else if (!empty($result->orderreference)) {
        $resolvedorderid = $DB->get_field(
            'local_moderncommerce_orders',
            'id',
            ['ordernumber' => $result->orderreference],
            MUST_EXIST
        );
        $order = order_api::get_order((int) $resolvedorderid);
    }

    if (!$order) {
        throw new moodle_exception('invalidorder', 'local_moderncommerce');
    }

    if ((int)$order->userid !== (int)$USER->id) {
        throw new moodle_exception('accessdenied', 'admin');
    }

    $ledger = gateway_manager::record_payment_result($order, $gatewayid, $result);
    $currentstatus = strtolower((string)($order->status ?? ''));
    $paidstatuses = ['paid', 'completed', 'refunded'];
    $incomingstatus = strtolower((string)($ledger->incomingstatus ?? ($result->status ?? '')));

    if (in_array($currentstatus, $paidstatuses, true) && in_array($incomingstatus, ['failed', 'cancelled'], true)) {
        ob_end_clean();
        redirect(
            $learnerappurl('orders/' . (int)$order->id),
            get_string('orderalreadyprocessed', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }

    if ($result->status === 'success') {
        if (!empty($ledger->duplicate) && in_array($currentstatus, $paidstatuses, true)) {
            ob_end_clean();
            redirect(
                new moodle_url('/local/moderncommerce/success.php', ['orderid' => (int)$order->id]),
                get_string('orderalreadyprocessed', 'local_moderncommerce'),
                null,
                \core\output\notification::NOTIFY_INFO
            );
        }

        $validation = order_validation_service::validate_order_for_payment($order->id, $order->userid);
        if (!$validation['valid'] && $validation['cancel_order']) {
            order_validation_service::cancel_duplicate_order($order->id, $validation['errors']);

            ob_end_clean();
            redirect(
                $learnerappurl('dashboard'),
                get_string('ordercancelled_duplicate', 'local_moderncommerce') . ' ' .
                    get_string('contactsupportforrefund', 'local_moderncommerce'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }

        order_api::update_order_status($order->id, 'paid');

        ob_end_clean();
        redirect(
            new moodle_url('/local/moderncommerce/success.php', ['orderid' => (int)$order->id]),
            get_string('paymentsuccess', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    if ($incomingstatus === 'cancelled') {
        if (!in_array($currentstatus, $paidstatuses, true)) {
            order_api::update_order_status($order->id, 'cancelled');
        }

        ob_end_clean();
        redirect(
            $learnerappurl('checkout'),
            get_string('paymentcancelled', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    if (!in_array($currentstatus, $paidstatuses, true)) {
        order_api::update_order_status($order->id, 'failed');
    }

    ob_end_clean();
    redirect(
        in_array($currentstatus, $paidstatuses, true)
            ? $learnerappurl('orders/' . (int)$order->id)
            : $learnerappurl('checkout'),
        get_string('paymentfailed', 'local_moderncommerce') . ': ' . ($result->message ?? ''),
        null,
        in_array($currentstatus, $paidstatuses, true)
            ? \core\output\notification::NOTIFY_INFO
            : \core\output\notification::NOTIFY_ERROR
    );
} catch (Exception $e) {
    ob_end_clean();
    $message = $e->getMessage();
    $notice = get_string('paymentverificationfailed', 'local_moderncommerce', $message);
    if ($e instanceof moodle_exception && ($e->errorcode ?? '') === 'paymentverifyfailed') {
        $notice = $message;
    }

    redirect(
        $learnerappurl('checkout'),
        $notice,
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
