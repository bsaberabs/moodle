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
 * External API listing bundles and programs for the admin bundles screen.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\bundles;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\localisation;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * List bundles/programs (canonical bundle products).
 */
class list_bundles extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search bundle name or description.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status filter.', VALUE_DEFAULT, ''),
            'type' => new external_value(PARAM_ALPHA, 'Type filter: bundle or program.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $search Search term.
     * @param string $status Status filter.
     * @param string $type Type filter.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @return array
     */
    public static function execute(
        string $search = '',
        string $status = '',
        string $type = '',
        int $page = 0,
        int $perpage = 10
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'status' => $status,
            'type' => $type,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $page = max(0, $params['page']);
        $perpage = min(100, max(5, $params['perpage']));

        $filters = [];
        $allowedstatuses = ['active', 'inactive', 'draft', 'archived'];
        if (in_array($params['status'], $allowedstatuses, true)) {
            $filters['status'] = $params['status'];
        }
        if ($params['type'] === 'bundle') {
            $filters['isprogram'] = 0;
        } else if ($params['type'] === 'program') {
            $filters['isprogram'] = 1;
        }
        if (trim($params['search']) !== '') {
            $filters['search'] = trim($params['search']);
        }

        $all = bundle_api::get_all($filters);
        $total = count($all);
        $pageitems = array_slice(array_values($all), $page * $perpage, $perpage);

        $items = [];
        foreach ($pageitems as $bundle) {
            $items[] = self::format_bundle($bundle, $context);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'stats' => self::get_stats(),
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
            'items' => new external_multiple_structure(self::bundle_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching bundles.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'stats' => self::stats_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Bundle row structure.
     *
     * @return external_single_structure
     */
    private static function bundle_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Bundle product ID.'),
            'name' => new external_value(PARAM_TEXT, 'Bundle name.'),
            'isprogram' => new external_value(PARAM_BOOL, 'Whether this is a program.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Type label.'),
            'coursecount' => new external_value(PARAM_INT, 'Number of included courses.'),
            'status' => new external_value(PARAM_ALPHA, 'Status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Status badge class.'),
            'onsale' => new external_value(PARAM_BOOL, 'Whether on sale.'),
            'displayprice' => new external_value(PARAM_TEXT, 'Formatted regular price.'),
            'displaysaleprice' => new external_value(PARAM_TEXT, 'Formatted sale price.'),
            'featured' => new external_value(PARAM_BOOL, 'Whether featured.'),
            'visible' => new external_value(PARAM_BOOL, 'Whether visible.'),
            'enrollmentcount' => new external_value(PARAM_INT, 'Paid/completed sales count.'),
            'editurl' => new external_value(PARAM_URL, 'Bundle form URL.'),
            'advancedurl' => new external_value(PARAM_URL, 'Advanced features URL.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total bundles and programs.'),
            'active' => new external_value(PARAM_INT, 'Active bundles and programs.'),
            'bundles' => new external_value(PARAM_INT, 'Bundle products.'),
            'programs' => new external_value(PARAM_INT, 'Program products.'),
        ]);
    }

    /**
     * Format one bundle record.
     *
     * @param object $bundle Normalised bundle record.
     * @param context_system $context System context.
     * @return array
     */
    private static function format_bundle(object $bundle, context_system $context): array {
        $bundleid = (int) $bundle->id;
        $onsale = !empty($bundle->saleprice) && (float) $bundle->saleprice > 0;
        $status = (string) $bundle->status;

        return [
            'id' => $bundleid,
            'name' => format_string($bundle->name, true, ['context' => $context]),
            'isprogram' => !empty($bundle->isprogram),
            'typelabel' => !empty($bundle->isprogram)
                ? get_string('program', 'local_moderncommerce')
                : get_string('bundle', 'local_moderncommerce'),
            'coursecount' => count(bundle_api::get_courses($bundleid)),
            'status' => $status,
            'statuslabel' => self::status_label($status),
            'statusclass' => self::status_class($status),
            'onsale' => $onsale,
            'displayprice' => pricing_service::format_price((float) $bundle->price),
            'displaysaleprice' => $onsale ? pricing_service::format_price((float) $bundle->saleprice) : '',
            'featured' => !empty($bundle->featured),
            'visible' => !empty($bundle->visible),
            'enrollmentcount' => bundle_api::get_enrollment_count($bundleid),
            'editurl' => (new moodle_url('/local/moderncommerce/admin/bundle_form.php', ['bundleid' => $bundleid]))->out(false),
            'advancedurl' => (new moodle_url(
                '/local/moderncommerce/admin/advanced_bundle_features.php',
                ['bundleid' => $bundleid]
            ))->out(false),
        ];
    }

    /**
     * Localised status label.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_label(string $status): string {
        return localisation::status_label($status, ['bundlestatus']);
    }

    /**
     * Status badge class.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_class(string $status): string {
        switch ($status) {
            case 'active':
                return 'success';
            case 'inactive':
            case 'archived':
                return 'danger';
            case 'draft':
                return 'warning';
            default:
                return 'neutral';
        }
    }

    /**
     * Bundle summary stats.
     *
     * @return array
     */
    private static function get_stats(): array {
        global $DB;

        $typesql = "producttype IN ('bundle', 'program')";

        return [
            'total' => (int) $DB->count_records_select('local_moderncommerce_products', $typesql),
            'active' => (int) $DB->count_records_select(
                'local_moderncommerce_products',
                "{$typesql} AND status = :status",
                ['status' => 'active']
            ),
            'bundles' => (int) $DB->count_records('local_moderncommerce_products', ['producttype' => 'bundle']),
            'programs' => (int) $DB->count_records('local_moderncommerce_products', ['producttype' => 'program']),
        ];
    }
}
