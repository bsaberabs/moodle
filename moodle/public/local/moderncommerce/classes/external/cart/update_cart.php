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
 * External API for buyer cart mutations.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\cart;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_moderncommerce\api\cart_api;
use local_moderncommerce\external\checkout\checkout_helper;

/**
 * Update cart contents or coupon state for the current buyer.
 */
class update_cart extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Cart action.', VALUE_REQUIRED),
            'itemid' => new external_value(PARAM_INT, 'Cart item ID.', VALUE_DEFAULT, 0),
            'courseid' => new external_value(PARAM_INT, 'Course ID to add.', VALUE_DEFAULT, 0),
            'bundleid' => new external_value(PARAM_INT, 'Bundle or program product ID to add.', VALUE_DEFAULT, 0),
            'couponcode' => new external_value(PARAM_TEXT, 'Coupon code.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $action Cart action.
     * @param int $itemid Cart item ID.
     * @param int $courseid Course ID to add.
     * @param int $bundleid Bundle or program product ID to add.
     * @param string $couponcode Coupon code.
     * @return array
     */
    public static function execute(
        string $action,
        int $itemid = 0,
        int $courseid = 0,
        int $bundleid = 0,
        string $couponcode = ''
    ): array {
        global $SESSION, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'itemid' => $itemid,
            'courseid' => $courseid,
            'bundleid' => $bundleid,
            'couponcode' => $couponcode,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:purchase', $context);

        $userid = (int)$USER->id;
        $action = strtolower($params['action']);

        switch ($action) {
            case 'addcourse':
                if ((int)$params['courseid'] <= 0) {
                    throw new invalid_parameter_exception('A valid course ID is required.');
                }
                cart_api::add_to_cart($userid, (int)$params['courseid'], 1);
                $data = checkout_helper::cart_data($userid, true);
                $data['message'] = get_string('addedtocart', 'local_moderncommerce');
                return $data;

            case 'addbundle':
                if ((int)$params['bundleid'] <= 0) {
                    throw new invalid_parameter_exception('A valid bundle ID is required.');
                }
                cart_api::add_bundle_to_cart($userid, (int)$params['bundleid'], 1);
                $data = checkout_helper::cart_data($userid, true);
                $data['message'] = get_string('bundleaddedtocart', 'local_moderncommerce');
                return $data;

            case 'remove':
                if ((int)$params['itemid'] <= 0) {
                    throw new invalid_parameter_exception('A valid cart item ID is required.');
                }
                checkout_helper::remove_cart_item($userid, (int)$params['itemid']);
                $data = checkout_helper::cart_data($userid, true);
                $data['message'] = get_string('removedcart', 'local_moderncommerce');
                return $data;

            case 'clear':
                unset($SESSION->moderncommerce_coupon);
                cart_api::clear_cart($userid);
                $data = checkout_helper::cart_data($userid, false);
                $data['message'] = get_string('cartcleared', 'local_moderncommerce');
                return $data;

            case 'applycoupon':
                if (trim((string)$params['couponcode']) === '') {
                    throw new invalid_parameter_exception(get_string('entercouponcode', 'local_moderncommerce'));
                }
                return checkout_helper::coupon_action($userid, 'apply', (string)$params['couponcode']);

            case 'removecoupon':
                return checkout_helper::coupon_action($userid, 'remove');
        }

        throw new invalid_parameter_exception('Invalid cart action.');
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return checkout_helper::cart_structure();
    }
}
