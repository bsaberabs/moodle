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
 * External API for deleting coupon target rules.
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
 * Delete a coupon applicability target.
 */
class delete_target extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Target row ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Delete target rule.
     *
     * @param int $id Target row ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecoupons', $context);

        $id = max(0, (int) $params['id']);
        $target = $id > 0 ? $DB->get_record('local_moderncommerce_coupon_targets', ['id' => $id]) : false;
        if (!$target) {
            return [
                'success' => false,
                'targetid' => 0,
                'couponid' => 0,
                'message' => get_string('coupontargetnotfound', 'local_moderncommerce'),
            ];
        }

        $couponid = (int) $target->couponid;
        $DB->delete_records('local_moderncommerce_coupon_targets', ['id' => $id]);

        \local_moderncommerce\audit\audit_service::record('coupon_target_deleted', 'coupon_target', $id, [
            'olddata' => $target,
            'newdata' => null,
            'severity' => 'warning',
        ]);

        return [
            'success' => true,
            'targetid' => $id,
            'couponid' => $couponid,
            'message' => get_string('coupontargetdeleted', 'local_moderncommerce'),
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether deletion succeeded.'),
            'targetid' => new external_value(PARAM_INT, 'Target row ID.'),
            'couponid' => new external_value(PARAM_INT, 'Coupon ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
        ]);
    }
}
