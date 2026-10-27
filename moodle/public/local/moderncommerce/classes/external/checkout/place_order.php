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
 * External API for checkout order placement.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\checkout;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\api\order_api;
use local_moderncommerce\payment\gateway_manager;
use local_moderncommerce\services\order_validation_service;
use moodle_exception;

/**
 * Create an order from the active cart, or continue payment for a pending order.
 */
class place_order extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'orderid' => new external_value(PARAM_INT, 'Existing pending order ID.', VALUE_DEFAULT, 0),
            'paymentmethod' => new external_value(PARAM_ALPHANUMEXT, 'Payment method.', VALUE_REQUIRED),
            'firstname' => new external_value(PARAM_TEXT, 'First name.', VALUE_DEFAULT, ''),
            'lastname' => new external_value(PARAM_TEXT, 'Last name.', VALUE_DEFAULT, ''),
            'email' => new external_value(PARAM_EMAIL, 'Email.', VALUE_DEFAULT, ''),
            'phone' => new external_value(PARAM_TEXT, 'Phone.', VALUE_DEFAULT, ''),
            'address' => new external_value(PARAM_TEXT, 'Address.', VALUE_DEFAULT, ''),
            'city' => new external_value(PARAM_TEXT, 'City.', VALUE_DEFAULT, ''),
            'state' => new external_value(PARAM_TEXT, 'State.', VALUE_DEFAULT, ''),
            'country' => new external_value(PARAM_TEXT, 'Country.', VALUE_DEFAULT, ''),
            'zipcode' => new external_value(PARAM_TEXT, 'ZIP/postal code.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $orderid Existing order ID.
     * @param string $paymentmethod Payment method.
     * @param string $firstname First name.
     * @param string $lastname Last name.
     * @param string $email Email.
     * @param string $phone Phone.
     * @param string $address Address.
     * @param string $city City.
     * @param string $state State.
     * @param string $country Country.
     * @param string $zipcode ZIP/postal code.
     * @return array
     */
    public static function execute(
        int $orderid = 0,
        string $paymentmethod = '',
        string $firstname = '',
        string $lastname = '',
        string $email = '',
        string $phone = '',
        string $address = '',
        string $city = '',
        string $state = '',
        string $country = '',
        string $zipcode = ''
    ): array {
        global $SESSION, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'orderid' => $orderid,
            'paymentmethod' => $paymentmethod,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'zipcode' => $zipcode,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:purchase', $context);

        $paymentmethod = gateway_manager::normalize_gateway_id((string)$params['paymentmethod']);
        if ($paymentmethod === '' || !gateway_manager::is_payment_method_available($paymentmethod)) {
            throw new moodle_exception('invalidpaymentmethod', 'local_moderncommerce');
        }

        if ((int)$params['orderid'] > 0) {
            $order = self::continue_pending_order((int)$params['orderid'], (int)$USER->id, $paymentmethod);
        } else {
            $billing = self::billing_info($params, $USER);
            $billing['couponcode'] = !empty($SESSION->moderncommerce_coupon)
                ? (string)$SESSION->moderncommerce_coupon
                : null;
            $order = order_api::create_order((int)$USER->id, $paymentmethod, $billing);
            // Remember the buyer's contact info so they do not re-enter it next time.
            checkout_helper::save_contact_preference((int)$USER->id, $billing);
        }

        unset($SESSION->moderncommerce_coupon);

        return [
            'success' => true,
            'message' => '',
            'orderid' => (int)$order->id,
            'ordernumber' => (string)$order->ordernumber,
            'redirecturl' => gateway_manager::init_url($paymentmethod, (int)$order->id)->out(false),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether order placement succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'orderid' => new external_value(PARAM_INT, 'Order ID.'),
            'ordernumber' => new external_value(PARAM_TEXT, 'Order number.'),
            'redirecturl' => new external_value(PARAM_RAW, 'Payment initialization URL.'),
        ]);
    }

    /**
     * Validate and return an existing pending order.
     *
     * @param int $orderid Order ID.
     * @param int $userid User ID.
     * @param string $paymentmethod Payment method.
     * @return object
     */
    private static function continue_pending_order(int $orderid, int $userid, string $paymentmethod): object {
        $order = order_api::get_order($orderid);
        if ((int)$order->userid !== $userid) {
            throw new moodle_exception('accessdenied', 'admin');
        }

        if ((string)$order->status !== 'pending') {
            throw new moodle_exception('orderalreadyprocessed', 'local_moderncommerce');
        }

        $validation = order_validation_service::validate_order_for_payment($orderid, $userid);
        if (!$validation['valid'] && $validation['cancel_order']) {
            order_validation_service::cancel_duplicate_order($orderid, $validation['errors']);
            throw new moodle_exception('ordercancelled_duplicate', 'local_moderncommerce');
        }

        order_api::record_selected_payment_method($order, $paymentmethod);
        return $order;
    }

    /**
     * Build billing data, falling back to Moodle user fields for hidden/empty fields.
     *
     * @param array $params Request params.
     * @param object $user Current user.
     * @return array
     */
    private static function billing_info(array $params, object $user): array {
        return [
            'firstname' => (string)($params['firstname'] ?: ($user->firstname ?? '')),
            'lastname' => (string)($params['lastname'] ?: ($user->lastname ?? '')),
            'email' => (string)($params['email'] ?: ($user->email ?? '')),
            'phone' => (string)$params['phone'],
            'address' => (string)$params['address'],
            'city' => (string)$params['city'],
            'state' => (string)$params['state'],
            'country' => (string)($params['country'] ?: ($user->country ?? '')),
            'zipcode' => (string)$params['zipcode'],
        ];
    }
}
