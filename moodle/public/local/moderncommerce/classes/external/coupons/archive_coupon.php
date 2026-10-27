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
 * External API for archiving coupons.
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
 * Archive a coupon instead of hard-deleting redemption history.
 */
class archive_coupon extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Coupon ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Archive a coupon.
     *
     * @param int $id Coupon ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB;

        ['id' => $id] = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecoupons', $context);

        $coupon = $id > 0 ? $DB->get_record('local_moderncommerce_coupons', ['id' => $id]) : false;
        if (!$coupon) {
            return [
                'success' => false,
                'couponid' => 0,
                'message' => get_string('couponnotfound', 'local_moderncommerce'),
            ];
        }

        $DB->update_record('local_moderncommerce_coupons', (object) [
            'id' => $id,
            'status' => 'archived',
            'timemodified' => time(),
        ]);

        \local_moderncommerce\audit\audit_service::record('coupon_archived', 'coupon', $id, [
            'olddata' => $coupon,
            'newdata' => ['status' => 'archived'],
            'severity' => 'warning',
        ]);

        return [
            'success' => true,
            'couponid' => $id,
            'message' => get_string('couponarchived', 'local_moderncommerce'),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the coupon was archived.'),
            'couponid' => new external_value(PARAM_INT, 'Coupon ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
        ]);
    }
}
