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
 * External API for saving coupons.
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
use core_text;

/**
 * Create or update a coupon.
 */
class save_coupon extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Coupon ID, or 0 for a new coupon.', VALUE_DEFAULT, 0),
            'code' => new external_value(PARAM_TEXT, 'Coupon code.', VALUE_REQUIRED),
            'name' => new external_value(PARAM_TEXT, 'Coupon name.', VALUE_DEFAULT, ''),
            'discounttype' => new external_value(PARAM_ALPHANUMEXT, 'Discount type.', VALUE_DEFAULT, 'percentage'),
            'value' => new external_value(PARAM_FLOAT, 'Discount value.', VALUE_REQUIRED),
            'maxdiscount' => new external_value(PARAM_FLOAT, 'Maximum discount amount.', VALUE_DEFAULT, 0),
            'minpurchase' => new external_value(PARAM_FLOAT, 'Minimum purchase amount.', VALUE_DEFAULT, 0),
            'minitems' => new external_value(PARAM_INT, 'Minimum item count.', VALUE_DEFAULT, 0),
            'maxuses' => new external_value(PARAM_INT, 'Maximum uses, or 0 for unlimited.', VALUE_DEFAULT, 0),
            'maxusesperuser' => new external_value(PARAM_INT, 'Maximum uses per user, or 0 for unlimited.', VALUE_DEFAULT, 1),
            'stackable' => new external_value(PARAM_BOOL, 'Whether coupon can stack with other promotions.', VALUE_DEFAULT, false),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Coupon status.', VALUE_DEFAULT, 'active'),
            'startdate' => new external_value(PARAM_INT, 'Start timestamp, or 0.', VALUE_DEFAULT, 0),
            'enddate' => new external_value(PARAM_INT, 'End timestamp, or 0.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save a coupon.
     *
     * @param int $id Coupon ID.
     * @param string $code Coupon code.
     * @param string $name Coupon name.
     * @param string $discounttype Discount type.
     * @param float $value Discount value.
     * @param float $maxdiscount Maximum discount amount.
     * @param float $minpurchase Minimum purchase amount.
     * @param int $minitems Minimum item count.
     * @param int $maxuses Maximum uses.
     * @param int $maxusesperuser Maximum uses per user.
     * @param bool $stackable Stackable flag.
     * @param string $status Status.
     * @param int $startdate Start timestamp.
     * @param int $enddate End timestamp.
     * @return array
     */
    public static function execute(
        int $id = 0,
        string $code = '',
        string $name = '',
        string $discounttype = 'percentage',
        float $value = 0,
        float $maxdiscount = 0,
        float $minpurchase = 0,
        int $minitems = 0,
        int $maxuses = 0,
        int $maxusesperuser = 1,
        bool $stackable = false,
        string $status = 'active',
        int $startdate = 0,
        int $enddate = 0
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'code' => $code,
            'name' => $name,
            'discounttype' => $discounttype,
            'value' => $value,
            'maxdiscount' => $maxdiscount,
            'minpurchase' => $minpurchase,
            'minitems' => $minitems,
            'maxuses' => $maxuses,
            'maxusesperuser' => $maxusesperuser,
            'stackable' => $stackable,
            'status' => $status,
            'startdate' => $startdate,
            'enddate' => $enddate,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecoupons', $context);

        $params = self::normalise($params);
        $existing = null;
        if ($params['id'] > 0) {
            $existing = $DB->get_record('local_moderncommerce_coupons', ['id' => $params['id']]);
            if (!$existing) {
                return self::failure(0, get_string('couponnotfound', 'local_moderncommerce'));
            }
        }

        $validation = self::validate_business_rules($params);
        if ($validation !== '') {
            return self::failure($params['id'], $validation);
        }

        $now = time();
        $record = (object) [
            'code' => $params['code'],
            'name' => $params['name'],
            'discounttype' => $params['discounttype'],
            'value' => $params['value'],
            'maxdiscount' => $params['maxdiscount'] > 0 ? $params['maxdiscount'] : null,
            'minpurchase' => $params['minpurchase'] > 0 ? $params['minpurchase'] : null,
            'minitems' => $params['minitems'] > 0 ? $params['minitems'] : null,
            'maxuses' => $params['maxuses'] > 0 ? $params['maxuses'] : null,
            'maxusesperuser' => $params['maxusesperuser'] > 0 ? $params['maxusesperuser'] : null,
            'stackable' => $params['stackable'] ? 1 : 0,
            'status' => $params['status'],
            'startdate' => $params['startdate'] > 0 ? $params['startdate'] : null,
            'enddate' => $params['enddate'] > 0 ? $params['enddate'] : null,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_moderncommerce_coupons', $record);
            $couponid = (int) $existing->id;
            $message = get_string('couponupdated', 'local_moderncommerce');
        } else {
            $record->usedcount = 0;
            $record->createdby = $USER->id;
            $record->timecreated = $now;
            $couponid = (int) $DB->insert_record('local_moderncommerce_coupons', $record);
            $message = get_string('couponcreated', 'local_moderncommerce');
        }

        \local_moderncommerce\audit\audit_service::record(
            $existing ? 'coupon_updated' : 'coupon_created',
            'coupon',
            $couponid,
            [
                'olddata' => $existing ?: null,
                'newdata' => $record,
                'severity' => 'warning',
            ]
        );

        return [
            'success' => true,
            'couponid' => $couponid,
            'message' => $message,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether save succeeded.'),
            'couponid' => new external_value(PARAM_INT, 'Coupon ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
        ]);
    }

    /**
     * Normalise submitted values.
     *
     * @param array $params Raw params.
     * @return array Normalised params.
     */
    private static function normalise(array $params): array {
        $params['id'] = max(0, (int) $params['id']);
        $params['code'] = self::normalise_code((string) $params['code']);
        $params['name'] = trim(strip_tags((string) $params['name']));
        $params['discounttype'] = self::normalise_choice((string) $params['discounttype'], ['percentage', 'fixed'], 'percentage');
        $params['value'] = max(0, (float) $params['value']);
        $params['maxdiscount'] = max(0, (float) $params['maxdiscount']);
        $params['minpurchase'] = max(0, (float) $params['minpurchase']);
        $params['minitems'] = max(0, (int) $params['minitems']);
        $params['maxuses'] = max(0, (int) $params['maxuses']);
        $params['maxusesperuser'] = max(0, (int) $params['maxusesperuser']);
        $params['status'] = self::normalise_choice((string) $params['status'], ['active', 'inactive', 'archived'], 'active');
        $params['startdate'] = max(0, (int) $params['startdate']);
        $params['enddate'] = max(0, (int) $params['enddate']);

        if ($params['name'] === '') {
            $params['name'] = $params['code'];
        }

        return $params;
    }

    /**
     * Validate business rules that require database state.
     *
     * @param array $params Normalised params.
     * @return string Empty when valid, otherwise message.
     */
    private static function validate_business_rules(array $params): string {
        global $DB;

        if ($params['code'] === '') {
            return get_string('couponcoderequired', 'local_moderncommerce');
        }

        if ($params['value'] <= 0) {
            return get_string('discountvaluerequired', 'local_moderncommerce');
        }

        if ($params['discounttype'] === 'percentage' && $params['value'] > 100) {
            return get_string('percentagecouponmax', 'local_moderncommerce');
        }

        if ($params['startdate'] > 0 && $params['enddate'] > 0 && $params['enddate'] < $params['startdate']) {
            return get_string('invaliddatewindow', 'local_moderncommerce');
        }

        if (
            $DB->record_exists_select(
                'local_moderncommerce_coupons',
                'code = :code AND id <> :id',
                ['code' => $params['code'], 'id' => $params['id']]
            )
        ) {
            return get_string('couponcodeexists', 'local_moderncommerce');
        }

        return '';
    }

    /**
     * Return a failure payload.
     *
     * @param int $couponid Coupon ID.
     * @param string $message Message.
     * @return array
     */
    private static function failure(int $couponid, string $message): array {
        return [
            'success' => false,
            'couponid' => $couponid,
            'message' => $message,
        ];
    }

    /**
     * Normalise coupon code.
     *
     * @param string $code Submitted code.
     * @return string Normalised code.
     */
    private static function normalise_code(string $code): string {
        $code = core_text::strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9_-]/', '', $code);

        return substr($code ?? '', 0, 50);
    }

    /**
     * Normalise an option value.
     *
     * @param string $value Submitted value.
     * @param array $allowed Allowed values.
     * @param string $fallback Fallback value.
     * @return string Normalised value.
     */
    private static function normalise_choice(string $value, array $allowed, string $fallback): string {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
