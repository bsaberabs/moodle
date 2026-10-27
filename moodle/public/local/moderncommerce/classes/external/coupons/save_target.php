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
 * External API for saving coupon target rules.
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
 * Save a coupon applicability target.
 */
class save_target extends external_api {
    /** @var string[] Supported target types. */
    private const TARGET_TYPES = ['product', 'course', 'productcategory', 'coursecategory', 'producttype', 'sku'];

    /** @var string[] Text-value target types. */
    private const VALUE_TARGET_TYPES = ['producttype', 'sku'];

    /** @var string[] Supported product types. */
    private const PRODUCT_TYPES = ['course', 'bundle', 'program', 'subscription', 'digital'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Target row ID, or 0 for a new rule.', VALUE_DEFAULT, 0),
            'couponid' => new external_value(PARAM_INT, 'Coupon ID.', VALUE_REQUIRED),
            'targettype' => new external_value(PARAM_ALPHANUMEXT, 'Target type.', VALUE_REQUIRED),
            'targetid' => new external_value(PARAM_INT, 'Numeric target ID, when required.', VALUE_DEFAULT, 0),
            'targetvalue' => new external_value(PARAM_TEXT, 'Text target value, when required.', VALUE_DEFAULT, ''),
            'includemode' => new external_value(PARAM_ALPHANUMEXT, 'Include or exclude.', VALUE_DEFAULT, 'include'),
        ]);
    }

    /**
     * Save target rule.
     *
     * @param int $id Target row ID.
     * @param int $couponid Coupon ID.
     * @param string $targettype Target type.
     * @param int $targetid Numeric target ID.
     * @param string $targetvalue Text target value.
     * @param string $includemode Include or exclude.
     * @return array
     */
    public static function execute(
        int $id = 0,
        int $couponid = 0,
        string $targettype = '',
        int $targetid = 0,
        string $targetvalue = '',
        string $includemode = 'include'
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'couponid' => $couponid,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'targetvalue' => $targetvalue,
            'includemode' => $includemode,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecoupons', $context);

        $params = self::normalise($params);
        if (!$DB->record_exists('local_moderncommerce_coupons', ['id' => $params['couponid']])) {
            return self::failure(0, 0, get_string('couponnotfound', 'local_moderncommerce'));
        }

        $existing = null;
        if ($params['id'] > 0) {
            $existing = $DB->get_record('local_moderncommerce_coupon_targets', ['id' => $params['id']]);
            if (!$existing || (int) $existing->couponid !== $params['couponid']) {
                return self::failure(0, $params['couponid'], get_string('coupontargetnotfound', 'local_moderncommerce'));
            }
        }

        $validation = self::validate_target($params);
        if ($validation !== '') {
            return self::failure($params['id'], $params['couponid'], $validation);
        }

        $duplicate = self::find_duplicate($params);
        if ($duplicate && (!$existing || (int) $duplicate->id !== (int) $existing->id)) {
            return [
                'success' => true,
                'targetid' => (int) $duplicate->id,
                'couponid' => $params['couponid'],
                'message' => get_string('coupontargetexists', 'local_moderncommerce'),
            ];
        }

        $record = (object) [
            'couponid' => $params['couponid'],
            'targettype' => $params['targettype'],
            'targetid' => in_array($params['targettype'], self::VALUE_TARGET_TYPES, true) ? null : $params['targetid'],
            'targetvalue' => in_array($params['targettype'], self::VALUE_TARGET_TYPES, true) ? $params['targetvalue'] : null,
            'includemode' => $params['includemode'],
        ];

        if ($existing) {
            $record->id = (int) $existing->id;
            $record->timecreated = empty($existing->timecreated) ? time() : (int) $existing->timecreated;
            $DB->update_record('local_moderncommerce_coupon_targets', $record);
            $targetid = (int) $existing->id;
        } else {
            $record->timecreated = time();
            $targetid = (int) $DB->insert_record('local_moderncommerce_coupon_targets', $record);
        }

        \local_moderncommerce\audit\audit_service::record(
            $existing ? 'coupon_target_updated' : 'coupon_target_created',
            'coupon_target',
            $targetid,
            [
                'olddata' => $existing ?: null,
                'newdata' => $record,
                'severity' => 'warning',
            ]
        );

        return [
            'success' => true,
            'targetid' => $targetid,
            'couponid' => $params['couponid'],
            'message' => get_string('coupontargetcreated', 'local_moderncommerce'),
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
            'targetid' => new external_value(PARAM_INT, 'Target row ID.'),
            'couponid' => new external_value(PARAM_INT, 'Coupon ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
        ]);
    }

    /**
     * Normalise submitted values.
     *
     * @param array $params Raw params.
     * @return array
     */
    private static function normalise(array $params): array {
        $params['id'] = max(0, (int) $params['id']);
        $params['couponid'] = max(0, (int) $params['couponid']);
        $params['targettype'] = self::normalise_type((string) $params['targettype']);
        $params['targetid'] = max(0, (int) $params['targetid']);
        $params['targetvalue'] = trim(strip_tags((string) $params['targetvalue']));
        $params['includemode'] = (string) $params['includemode'] === 'exclude' ? 'exclude' : 'include';

        if ($params['targettype'] === 'sku') {
            $params['targetvalue'] = substr($params['targetvalue'], 0, 100);
        } else if ($params['targettype'] === 'producttype') {
            $params['targetvalue'] = strtolower($params['targetvalue']);
        }

        return $params;
    }

    /**
     * Validate the requested target exists.
     *
     * @param array $params Normalised params.
     * @return string Empty when valid, otherwise message.
     */
    private static function validate_target(array $params): string {
        global $DB;

        if ($params['couponid'] <= 0 || $params['targettype'] === '') {
            return get_string('coupontargetinvalid', 'local_moderncommerce');
        }

        if ($params['targettype'] === 'producttype') {
            return in_array($params['targetvalue'], self::PRODUCT_TYPES, true)
                ? ''
                : get_string('coupontargetrequiresselection', 'local_moderncommerce');
        }

        if ($params['targettype'] === 'sku') {
            return $params['targetvalue'] !== ''
                ? ''
                : get_string('coupontargetrequiresvalue', 'local_moderncommerce');
        }

        if ($params['targetid'] <= 0) {
            return get_string('coupontargetrequiresselection', 'local_moderncommerce');
        }

        $table = [
            'product' => 'local_moderncommerce_products',
            'course' => 'course',
            'productcategory' => 'local_moderncommerce_product_categories',
            'coursecategory' => 'course_categories',
        ][$params['targettype']] ?? '';

        if ($table === '' || !$DB->record_exists($table, ['id' => $params['targetid']])) {
            return get_string('coupontargetinvalid', 'local_moderncommerce');
        }

        return '';
    }

    /**
     * Find an existing duplicate target row.
     *
     * @param array $params Normalised params.
     * @return \stdClass|false
     */
    private static function find_duplicate(array $params) {
        global $DB;

        $conditions = [
            'couponid' => $params['couponid'],
            'targettype' => $params['targettype'],
            'includemode' => $params['includemode'],
        ];

        if (in_array($params['targettype'], self::VALUE_TARGET_TYPES, true)) {
            $conditions['targetvalue'] = $params['targetvalue'];
        } else {
            $conditions['targetid'] = $params['targetid'];
        }

        return $DB->get_record('local_moderncommerce_coupon_targets', $conditions, '*', IGNORE_MULTIPLE);
    }

    /**
     * Normalise target type.
     *
     * @param string $type Submitted type.
     * @return string
     */
    private static function normalise_type(string $type): string {
        $type = strtolower(trim($type));
        if ($type === 'product_category') {
            $type = 'productcategory';
        } else if ($type === 'course_category') {
            $type = 'coursecategory';
        } else if ($type === 'product_type') {
            $type = 'producttype';
        }

        return in_array($type, self::TARGET_TYPES, true) ? $type : '';
    }

    /**
     * Return failure payload.
     *
     * @param int $targetid Target row ID.
     * @param int $couponid Coupon ID.
     * @param string $message Message.
     * @return array
     */
    private static function failure(int $targetid, int $couponid, string $message): array {
        return [
            'success' => false,
            'targetid' => $targetid,
            'couponid' => $couponid,
            'message' => $message,
        ];
    }
}
