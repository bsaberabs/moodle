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
 * External API redeeming an enrollment key for the buyer.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\redeem;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\key_redemption_service;

/**
 * Redeem an enrollment key and enrol the buyer in every granted course.
 */
class redeem_key extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'keycode' => new external_value(PARAM_ALPHANUMEXT, 'Key code.'),
            'orderid' => new external_value(PARAM_INT, 'Originating order ID.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $keycode Key code.
     * @param int $orderid Order ID.
     * @return array
     */
    public static function execute(string $keycode, int $orderid = 0): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'keycode' => $keycode,
            'orderid' => $orderid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:redeemkey', $context);

        // Only honour an order that belongs to this user.
        $orderid = $params['orderid'] > 0
            && $DB->record_exists('local_moderncommerce_orders', ['id' => $params['orderid'], 'userid' => $USER->id])
            ? $params['orderid']
            : null;

        try {
            $result = key_redemption_service::redeem(trim($params['keycode']), (int) $USER->id, $orderid);
        } catch (\moodle_exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'courses' => [],
                'warnings' => [],
            ];
        }

        $count = count($result['courses']);

        return [
            'success' => true,
            'message' => get_string('keyredeemedcourses', 'local_moderncommerce', $count),
            'courses' => $result['courses'],
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the key was redeemed.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'courses' => new external_multiple_structure(validate_key::course_structure()),
            'warnings' => new external_warnings(),
        ]);
    }
}
