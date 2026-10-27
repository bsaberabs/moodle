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
 * External API for coupon target typeahead options.
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
 * Search target options for coupon applicability rules.
 */
class search_target_options extends external_api {
    /** @var string[] Supported target types. */
    private const TARGET_TYPES = ['product', 'course', 'productcategory', 'coursecategory', 'producttype', 'sku'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'targettype' => new external_value(PARAM_ALPHANUMEXT, 'Target type.', VALUE_REQUIRED),
            'query' => new external_value(PARAM_TEXT, 'Search text.', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Maximum records to return.', VALUE_DEFAULT, 20),
        ]);
    }

    /**
     * Search options.
     *
     * @param string $targettype Target type.
     * @param string $query Search term.
     * @param int $limit Limit.
     * @return array
     */
    public static function execute(string $targettype = '', string $query = '', int $limit = 20): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'targettype' => $targettype,
            'query' => $query,
            'limit' => $limit,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecoupons', $context);

        $targettype = self::normalise_type((string) $params['targettype']);
        $query = trim((string) $params['query']);
        $limit = min(50, max(5, (int) $params['limit']));

        if ($targettype === '') {
            return self::empty_response($targettype, $query, $limit);
        }

        switch ($targettype) {
            case 'product':
                [$items, $total] = self::search_products($query, $limit, $context);
                break;
            case 'course':
                [$items, $total] = self::search_courses($query, $limit, $context);
                break;
            case 'productcategory':
                [$items, $total] = self::search_product_categories($query, $limit, $context);
                break;
            case 'coursecategory':
                [$items, $total] = self::search_course_categories($query, $limit, $context);
                break;
            case 'producttype':
                [$items, $total] = self::search_product_types($query);
                break;
            case 'sku':
                [$items, $total] = self::search_skus($query, $limit, $context);
                break;
            default:
                return self::empty_response($targettype, $query, $limit);
        }

        return [
            'items' => $items,
            'total' => $total,
            'query' => $query,
            'limit' => $limit,
            'targettype' => $targettype,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::option_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching options.'),
            'query' => new external_value(PARAM_TEXT, 'Applied query.'),
            'limit' => new external_value(PARAM_INT, 'Applied limit.'),
            'targettype' => new external_value(PARAM_ALPHANUMEXT, 'Applied target type.'),
        ]);
    }

    /**
     * Option return structure.
     *
     * @return external_single_structure
     */
    private static function option_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Numeric ID, or 0 for text-value targets.'),
            'targetvalue' => new external_value(PARAM_TEXT, 'Text value for text-value targets.'),
            'label' => new external_value(PARAM_TEXT, 'Display label.'),
            'summary' => new external_value(PARAM_TEXT, 'Display summary.'),
        ]);
    }

    /**
     * Search products.
     *
     * @param string $query Search term.
     * @param int $limit Limit.
     * @param context_system $context System context.
     * @return array
     */
    private static function search_products(string $query, int $limit, context_system $context): array {
        global $DB;

        [$where, $params] = self::like_where($query, [
            'p.name' => 'name',
            'p.slug' => 'slug',
            'p.sku' => 'sku',
        ], 'p.id');
        $where = "p.status <> 'archived' AND {$where}";

        $total = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {local_moderncommerce_products} p WHERE {$where}", $params);
        $records = $DB->get_records_sql(
            "SELECT p.id, p.name, p.sku, p.producttype, p.status
               FROM {local_moderncommerce_products} p
              WHERE {$where}
           ORDER BY p.name ASC, p.id ASC",
            $params,
            0,
            $limit
        );

        $items = [];
        foreach ($records as $record) {
            $summaryparts = array_filter([
                (string) $record->sku,
                self::product_type_label((string) $record->producttype),
                (string) $record->status,
            ]);
            $items[] = [
                'id' => (int) $record->id,
                'targetvalue' => '',
                'label' => format_string($record->name, true, ['context' => $context]),
                'summary' => implode(' / ', $summaryparts),
            ];
        }

        return [$items, $total];
    }

    /**
     * Search Moodle courses.
     *
     * @param string $query Search term.
     * @param int $limit Limit.
     * @param context_system $context System context.
     * @return array
     */
    private static function search_courses(string $query, int $limit, context_system $context): array {
        global $DB;

        [$where, $params] = self::like_where($query, [
            'c.fullname' => 'fullname',
            'c.shortname' => 'shortname',
            'c.idnumber' => 'idnumber',
        ], 'c.id');
        $params['siteid'] = SITEID;
        $where = "c.id <> :siteid AND {$where}";

        $total = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {course} c WHERE {$where}", $params);
        $records = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.idnumber, cc.name AS categoryname
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE {$where}
           ORDER BY c.fullname ASC, c.shortname ASC",
            $params,
            0,
            $limit
        );

        $items = [];
        foreach ($records as $record) {
            $summaryparts = array_filter([
                (string) $record->shortname,
                format_string($record->categoryname, true, ['context' => $context]),
            ]);
            $items[] = [
                'id' => (int) $record->id,
                'targetvalue' => '',
                'label' => format_string($record->fullname, true, ['context' => $context]),
                'summary' => implode(' / ', $summaryparts),
            ];
        }

        return [$items, $total];
    }

    /**
     * Search product categories.
     *
     * @param string $query Search term.
     * @param int $limit Limit.
     * @param context_system $context System context.
     * @return array
     */
    private static function search_product_categories(string $query, int $limit, context_system $context): array {
        global $DB;

        [$where, $params] = self::like_where($query, [
            'pc.name' => 'name',
            'pc.slug' => 'slug',
        ], 'pc.id');

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_moderncommerce_product_categories} pc
              WHERE {$where}",
            $params
        );
        $records = $DB->get_records_sql(
            "SELECT pc.id, pc.name, pc.slug, pc.visible
               FROM {local_moderncommerce_product_categories} pc
              WHERE {$where}
           ORDER BY pc.name ASC, pc.id ASC",
            $params,
            0,
            $limit
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = [
                'id' => (int) $record->id,
                'targetvalue' => '',
                'label' => format_string($record->name, true, ['context' => $context]),
                'summary' => (string) $record->slug,
            ];
        }

        return [$items, $total];
    }

    /**
     * Search course categories.
     *
     * @param string $query Search term.
     * @param int $limit Limit.
     * @param context_system $context System context.
     * @return array
     */
    private static function search_course_categories(string $query, int $limit, context_system $context): array {
        global $DB;

        [$where, $params] = self::like_where($query, [
            'cc.name' => 'name',
            'cc.idnumber' => 'idnumber',
        ], 'cc.id');

        $total = (int) $DB->count_records_sql("SELECT COUNT(1) FROM {course_categories} cc WHERE {$where}", $params);
        $records = $DB->get_records_sql(
            "SELECT cc.id, cc.name, cc.idnumber, cc.visible
               FROM {course_categories} cc
              WHERE {$where}
           ORDER BY cc.name ASC, cc.id ASC",
            $params,
            0,
            $limit
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = [
                'id' => (int) $record->id,
                'targetvalue' => '',
                'label' => format_string($record->name, true, ['context' => $context]),
                'summary' => (string) ($record->idnumber ?? ''),
            ];
        }

        return [$items, $total];
    }

    /**
     * Search static product type options.
     *
     * @param string $query Search term.
     * @return array
     */
    private static function search_product_types(string $query): array {
        $query = strtolower($query);
        $options = [
            'course' => get_string('course'),
            'bundle' => get_string('bundle', 'local_moderncommerce'),
            'program' => get_string('program', 'local_moderncommerce'),
            'subscription' => get_string('subscription', 'local_moderncommerce'),
            'digital' => get_string('digitalproduct', 'local_moderncommerce'),
        ];

        $items = [];
        foreach ($options as $value => $label) {
            if ($query !== '' && strpos(strtolower($label . ' ' . $value), $query) === false) {
                continue;
            }

            $items[] = [
                'id' => 0,
                'targetvalue' => $value,
                'label' => $label,
                'summary' => $value,
            ];
        }

        return [$items, count($items)];
    }

    /**
     * Search SKU values.
     *
     * @param string $query Search term.
     * @param int $limit Limit.
     * @param context_system $context System context.
     * @return array
     */
    private static function search_skus(string $query, int $limit, context_system $context): array {
        global $DB;

        [$where, $params] = self::like_where($query, [
            'p.sku' => 'sku',
            'p.name' => 'name',
        ], 'p.id');
        $where = "p.status <> 'archived' AND p.sku IS NOT NULL AND p.sku <> '' AND {$where}";

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT p.sku)
               FROM {local_moderncommerce_products} p
              WHERE {$where}",
            $params
        );
        $records = $DB->get_records_sql(
            "SELECT p.sku,
                    MIN(p.name) AS productname
               FROM {local_moderncommerce_products} p
              WHERE {$where}
           GROUP BY p.sku
           ORDER BY p.sku ASC",
            $params,
            0,
            $limit
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = [
                'id' => 0,
                'targetvalue' => (string) $record->sku,
                'label' => (string) $record->sku,
                'summary' => format_string($record->productname, true, ['context' => $context]),
            ];
        }

        return [$items, $total];
    }

    /**
     * Build a LIKE search fragment.
     *
     * @param string $query Search term.
     * @param array $fields SQL field to parameter-name map.
     * @param string $idfield Numeric ID field.
     * @return array SQL fragment and params.
     */
    private static function like_where(string $query, array $fields, string $idfield): array {
        global $DB;

        if ($query === '') {
            return ['1 = 1', []];
        }

        $clauses = [];
        $params = [];
        $search = '%' . $DB->sql_like_escape($query) . '%';
        foreach ($fields as $field => $name) {
            $param = 'search' . $name;
            $clauses[] = $DB->sql_like($field, ':' . $param, false);
            $params[$param] = $search;
        }

        if (ctype_digit($query)) {
            $clauses[] = $idfield . ' = :exactid';
            $params['exactid'] = (int) $query;
        }

        return ['(' . implode(' OR ', $clauses) . ')', $params];
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
     * Return empty response.
     *
     * @param string $targettype Target type.
     * @param string $query Query.
     * @param int $limit Limit.
     * @return array
     */
    private static function empty_response(string $targettype, string $query, int $limit): array {
        return [
            'items' => [],
            'total' => 0,
            'query' => $query,
            'limit' => $limit,
            'targettype' => $targettype,
        ];
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
