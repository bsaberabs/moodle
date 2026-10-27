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
 * External API for coupon code uniqueness checks.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\coupons;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Check whether a coupon code already exists.
 */
class check_code extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'code' => new external_value(PARAM_TEXT, 'Coupon code to check.'),
            'couponid' => new external_value(PARAM_INT, 'Coupon ID to exclude while editing.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $code Coupon code.
     * @param int $couponid Coupon ID to exclude.
     * @return array
     */
    public static function execute(string $code, int $couponid = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'code' => $code,
            'couponid' => $couponid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecoupons', $context);

        $code = strtoupper(trim($params['code']));
        if ($code === '') {
            return ['success' => true, 'exists' => false, 'code' => '', 'message' => ''];
        }

        $sql = "SELECT id
                  FROM {local_moderncommerce_coupons}
                 WHERE code = :code";
        $sqlparams = ['code' => $code];
        if ((int)$params['couponid'] > 0) {
            $sql .= " AND id <> :couponid";
            $sqlparams['couponid'] = (int)$params['couponid'];
        }

        $exists = $DB->record_exists_sql($sql, $sqlparams);

        return [
            'success' => true,
            'exists' => $exists,
            'code' => $code,
            'message' => $exists ? get_string('couponcodeinuse', 'local_moderncommerce') : '',
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded.'),
            'exists' => new external_value(PARAM_BOOL, 'Whether the coupon code already exists.'),
            'code' => new external_value(PARAM_TEXT, 'Normalized coupon code.'),
            'message' => new external_value(PARAM_TEXT, 'Optional status message.'),
        ]);
    }
}
