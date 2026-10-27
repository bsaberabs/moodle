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
 * External API for checkout bootstrap.
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
use local_moderncommerce\api\cart_api;
use local_moderncommerce\api\order_api;
use local_moderncommerce\services\order_validation_service;
use moodle_exception;

/**
 * Prepare the checkout dataset for a cart, direct purchase, or pending order.
 */
class start_checkout extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'orderid' => new external_value(PARAM_INT, 'Existing pending order ID.', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Direct course purchase ID.', VALUE_DEFAULT, 0),
            'bundleid' => new external_value(PARAM_INT, 'Direct bundle or program product ID.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $orderid Existing order ID.
     * @param int $courseid Direct course ID.
     * @param int $bundleid Direct bundle or program product ID.
     * @return array
     */
    public static function execute(int $orderid = 0, int $courseid = 0, int $bundleid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'orderid' => $orderid,
            'courseid' => $courseid,
            'bundleid' => $bundleid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:purchase', $context);

        $userid = (int)$USER->id;
        $existingorder = null;

        if ((int)$params['courseid'] > 0) {
            self::add_course_for_direct_checkout($userid, (int)$params['courseid']);
        }

        if ((int)$params['bundleid'] > 0) {
            self::add_bundle_for_direct_checkout($userid, (int)$params['bundleid']);
        }

        if ((int)$params['orderid'] > 0) {
            $existingorder = order_api::get_order((int)$params['orderid']);
            if ((int)$existingorder->userid !== $userid) {
                throw new moodle_exception('accessdenied', 'admin');
            }

            if ((string)$existingorder->status !== 'pending') {
                $data = checkout_helper::checkout_data($userid, $existingorder);
                $data['success'] = false;
                $data['cancheckout'] = false;
                $data['message'] = get_string('orderalreadyprocessed', 'local_moderncommerce');
                return $data;
            }

            $validation = order_validation_service::validate_order_for_payment((int)$existingorder->id, $userid);
            if (!$validation['valid'] && $validation['cancel_order']) {
                order_validation_service::cancel_duplicate_order((int)$existingorder->id, $validation['errors']);
                $data = checkout_helper::checkout_data($userid);
                $data['success'] = false;
                $data['cancheckout'] = false;
                $data['message'] = get_string('ordercancelled_duplicate', 'local_moderncommerce');
                return $data;
            }
        } else {
            cart_api::refresh_cart_prices($userid, true);
        }

        $data = checkout_helper::checkout_data($userid, $existingorder);
        if (!$existingorder && !$data['cancheckout']) {
            $data['message'] = get_string('emptycart', 'local_moderncommerce');
        }

        return $data;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return checkout_helper::checkout_structure();
    }

    /**
     * Add a direct course checkout item, ignoring only duplicate cart state.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     */
    private static function add_course_for_direct_checkout(int $userid, int $courseid): void {
        try {
            cart_api::add_to_cart($userid, $courseid, 1);
        } catch (moodle_exception $e) {
            if (($e->errorcode ?? '') !== 'coursealreadyincart') {
                throw $e;
            }
        }
    }

    /**
     * Add a direct bundle/program checkout item, ignoring only duplicate cart state.
     *
     * @param int $userid User ID.
     * @param int $bundleid Bundle or program product ID.
     */
    private static function add_bundle_for_direct_checkout(int $userid, int $bundleid): void {
        try {
            cart_api::add_bundle_to_cart($userid, $bundleid, 1);
        } catch (moodle_exception $e) {
            if (($e->errorcode ?? '') !== 'bundlealreadyincart') {
                throw $e;
            }
        }
    }
}
