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
 * External API for listing coupon target rules.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\coupons;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * List coupon applicability targets for the React coupon editor.
 */
class list_targets extends external_api {
    /** @var string[] Supported target types. */
    private const TARGET_TYPES = ['product', 'course', 'productcategory', 'coursecategory', 'producttype', 'sku'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'couponid' => new external_value(PARAM_INT, 'Coupon ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute listing.
     *
     * @param int $couponid Coupon ID.
     * @return array
     */
    public static function execute(int $couponid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'couponid' => $couponid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecoupons', $context);

        $couponid = max(0, (int) $params['couponid']);
        if (!$DB->record_exists('local_moderncommerce_coupons', ['id' => $couponid])) {
            return [
                'success' => false,
                'message' => get_string('couponnotfound', 'local_moderncommerce'),
                'couponid' => 0,
                'items' => [],
            ];
        }

        $records = $DB->get_records(
            'local_moderncommerce_coupon_targets',
            ['couponid' => $couponid],
            'includemode ASC, targettype ASC, id ASC'
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_target($record, $context);
        }

        return [
            'success' => true,
            'message' => '',
            'couponid' => $couponid,
            'items' => $items,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether listing succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'couponid' => new external_value(PARAM_INT, 'Coupon ID.'),
            'items' => new external_multiple_structure(self::target_structure()),
        ]);
    }

    /**
     * Target return structure.
     *
     * @return external_single_structure
     */
    private static function target_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Target row ID.'),
            'couponid' => new external_value(PARAM_INT, 'Coupon ID.'),
            'targettype' => new external_value(PARAM_ALPHANUMEXT, 'Target type.'),
            'targettypelabel' => new external_value(PARAM_TEXT, 'Target type label.'),
            'targetid' => new external_value(PARAM_INT, 'Target numeric ID, or 0.'),
            'targetvalue' => new external_value(PARAM_TEXT, 'Target text value, or empty.'),
            'includemode' => new external_value(PARAM_ALPHANUMEXT, 'Include or exclude mode.'),
            'includemodelabel' => new external_value(PARAM_TEXT, 'Include mode label.'),
            'displayname' => new external_value(PARAM_TEXT, 'Display name.'),
            'summary' => new external_value(PARAM_TEXT, 'Display summary.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
        ]);
    }

    /**
     * Format one target.
     *
     * @param \stdClass $target Target record.
     * @param context_system $context System context.
     * @return array
     */
    private static function format_target(\stdClass $target, context_system $context): array {
        $type = self::normalise_type((string) $target->targettype);
        $mode = (string) ($target->includemode ?? 'include');
        if ($mode !== 'exclude') {
            $mode = 'include';
        }

        [$displayname, $summary] = self::resolve_display(
            (int) ($target->targetid ?? 0),
            (string) ($target->targetvalue ?? ''),
            $type,
            $context
        );

        return [
            'id' => (int) $target->id,
            'couponid' => (int) $target->couponid,
            'targettype' => $type,
            'targettypelabel' => self::target_type_label($type),
            'targetid' => empty($target->targetid) ? 0 : (int) $target->targetid,
            'targetvalue' => (string) ($target->targetvalue ?? ''),
            'includemode' => $mode,
            'includemodelabel' => get_string($mode, 'local_moderncommerce'),
            'displayname' => $displayname,
            'summary' => $summary,
            'timecreated' => empty($target->timecreated) ? 0 : (int) $target->timecreated,
        ];
    }

    /**
     * Resolve the display name and summary for a target row.
     *
     * @param int $targetid Numeric target ID.
     * @param string $targetvalue Text target value.
     * @param string $type Target type.
     * @param context_system $context System context.
     * @return array
     */
    private static function resolve_display(int $targetid, string $targetvalue, string $type, context_system $context): array {
        global $DB;

        if ($type === 'product') {
            $product = $targetid > 0 ? $DB->get_record('local_moderncommerce_products', ['id' => $targetid]) : false;
            if ($product) {
                $summaryparts = array_filter([(string) $product->sku, self::product_type_label((string) $product->producttype)]);
                return [
                    format_string($product->name, true, ['context' => $context]),
                    implode(' / ', $summaryparts),
                ];
            }

            return [get_string('productnotfound', 'local_moderncommerce'), 'ID ' . $targetid];
        }

        if ($type === 'course') {
            $course = $targetid > 0 ? $DB->get_record('course', ['id' => $targetid]) : false;
            if ($course) {
                $categoryname = '';
                if (!empty($course->category)) {
                    $category = $DB->get_record('course_categories', ['id' => $course->category], 'id,name', IGNORE_MISSING);
                    $categoryname = $category ? format_string($category->name, true, ['context' => $context]) : '';
                }

                return [
                    format_string($course->fullname, true, ['context' => $context]),
                    trim((string) $course->shortname . ($categoryname !== '' ? ' / ' . $categoryname : '')),
                ];
            }

            return [get_string('invalidcourseid', 'local_moderncommerce'), 'ID ' . $targetid];
        }

        if ($type === 'productcategory') {
            $category = $targetid > 0 ? $DB->get_record('local_moderncommerce_product_categories', ['id' => $targetid]) : false;
            if ($category) {
                return [
                    format_string($category->name, true, ['context' => $context]),
                    (string) $category->slug,
                ];
            }

            return [get_string('invalidcategory', 'local_moderncommerce'), 'ID ' . $targetid];
        }

        if ($type === 'coursecategory') {
            $category = $targetid > 0 ? $DB->get_record('course_categories', ['id' => $targetid]) : false;
            if ($category) {
                return [
                    format_string($category->name, true, ['context' => $context]),
                    (string) ($category->idnumber ?? ''),
                ];
            }

            return [get_string('invalidcategory', 'local_moderncommerce'), 'ID ' . $targetid];
        }

        if ($type === 'producttype') {
            return [self::product_type_label($targetvalue), $targetvalue];
        }

        if ($type === 'sku') {
            return [$targetvalue, get_string('sku', 'local_moderncommerce')];
        }

        return [$targetvalue, ''];
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

        return in_array($type, self::TARGET_TYPES, true) ? $type : 'product';
    }

    /**
     * Get a translated target type label.
     *
     * @param string $type Target type.
     * @return string
     */
    private static function target_type_label(string $type): string {
        return get_string('targettype_' . self::normalise_type($type), 'local_moderncommerce');
    }

    /**
     * Get a product type label.
     *
     * @param string $type Product type.
     * @return string
     */
    private static function product_type_label(string $type): string {
        $labels = [
            'course' => get_string('course'),
            'bundle' => get_string('bundle', 'local_moderncommerce'),
            'program' => get_string('program', 'local_moderncommerce'),
            'subscription' => get_string('subscription', 'local_moderncommerce'),
            'digital' => get_string('digitalproduct', 'local_moderncommerce'),
        ];

        return $labels[$type] ?? $type;
    }
}
