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
 * External API saving a bundle/program and its included courses.
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
use local_moderncommerce\services\category_service;

/**
 * Create or update a bundle/program, reconciling its included courses.
 */
class save_bundle extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Bundle product ID (0 to create).', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Bundle name.'),
            'isprogram' => new external_value(PARAM_BOOL, 'Whether this is a program.', VALUE_DEFAULT, false),
            'shortdescription' => new external_value(PARAM_TEXT, 'Short description.', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_RAW, 'Full description.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status.', VALUE_DEFAULT, 'active'),
            'visible' => new external_value(PARAM_BOOL, 'Whether visible.', VALUE_DEFAULT, true),
            'featured' => new external_value(PARAM_BOOL, 'Whether featured.', VALUE_DEFAULT, false),
            'displayorder' => new external_value(PARAM_INT, 'Display order.', VALUE_DEFAULT, 0),
            'maxenrollment' => new external_value(PARAM_INT, 'Max enrollment (0 = unlimited).', VALUE_DEFAULT, 0),
            'categoryid' => new external_value(
                PARAM_INT,
                'Primary product category ID, or 0 for none.',
                VALUE_DEFAULT,
                0
            ),
            'price' => new external_value(PARAM_FLOAT, 'Regular price.', VALUE_DEFAULT, 0),
            'saleprice' => new external_value(PARAM_FLOAT, 'Sale price (0 = none).', VALUE_DEFAULT, 0),
            'salestartdate' => new external_value(PARAM_INT, 'Sale start timestamp.', VALUE_DEFAULT, 0),
            'saleenddate' => new external_value(PARAM_INT, 'Sale end timestamp.', VALUE_DEFAULT, 0),
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Included course ID.'),
                'Included course IDs in order.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Bundle ID.
     * @param string $name Name.
     * @param bool $isprogram Program flag.
     * @param string $shortdescription Short description.
     * @param string $description Description.
     * @param string $status Status.
     * @param bool $visible Visible flag.
     * @param bool $featured Featured flag.
     * @param int $displayorder Display order.
     * @param int $maxenrollment Max enrollment.
     * @param int $categoryid Primary product category ID.
     * @param float $price Regular price.
     * @param float $saleprice Sale price.
     * @param int $salestartdate Sale start.
     * @param int $saleenddate Sale end.
     * @param array $courseids Included course IDs.
     * @return array
     */
    public static function execute(
        int $id = 0,
        string $name = '',
        bool $isprogram = false,
        string $shortdescription = '',
        string $description = '',
        string $status = 'active',
        bool $visible = true,
        bool $featured = false,
        int $displayorder = 0,
        int $maxenrollment = 0,
        int $categoryid = 0,
        float $price = 0,
        float $saleprice = 0,
        int $salestartdate = 0,
        int $saleenddate = 0,
        array $courseids = []
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'name' => $name,
            'isprogram' => $isprogram,
            'shortdescription' => $shortdescription,
            'description' => $description,
            'status' => $status,
            'visible' => $visible,
            'featured' => $featured,
            'displayorder' => $displayorder,
            'maxenrollment' => $maxenrollment,
            'categoryid' => $categoryid,
            'price' => $price,
            'saleprice' => $saleprice,
            'salestartdate' => $salestartdate,
            'saleenddate' => $saleenddate,
            'courseids' => $courseids,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        if (trim($params['name']) === '') {
            return ['success' => false, 'bundleid' => 0, 'message' => get_string('required'), 'warnings' => []];
        }

        $categoryid = max(0, (int) $params['categoryid']);
        if ($categoryid > 0 && !category_service::category_exists($categoryid)) {
            return [
                'success' => false,
                'bundleid' => 0,
                'message' => get_string('invalidcategory', 'local_moderncommerce'),
                'warnings' => [],
            ];
        }

        $allowedstatuses = ['active', 'inactive', 'draft', 'archived'];
        $status = in_array($params['status'], $allowedstatuses, true) ? $params['status'] : 'active';
        $courseids = array_values(array_unique(array_filter(array_map('intval', $params['courseids']))));

        $data = (object) [
            'name' => trim($params['name']),
            'isprogram' => $params['isprogram'] ? 1 : 0,
            'shortdescription' => $params['shortdescription'],
            'description' => $params['description'],
            'status' => $status,
            'visible' => $params['visible'] ? 1 : 0,
            'featured' => $params['featured'] ? 1 : 0,
            'displayorder' => $params['displayorder'],
            'maxenrollment' => $params['maxenrollment'] > 0 ? $params['maxenrollment'] : null,
            'price' => max(0, $params['price']),
            'saleprice' => max(0, $params['saleprice']),
            'salestartdate' => $params['salestartdate'] > 0 ? $params['salestartdate'] : null,
            'saleenddate' => $params['saleenddate'] > 0 ? $params['saleenddate'] : null,
        ];

        $oldbundle = null;
        if ($params['id'] > 0) {
            $oldbundle = bundle_api::get((int) $params['id']);
            if ($oldbundle) {
                $oldbundle->courseids = array_map(static function ($course): int {
                    return (int) $course->courseid;
                }, bundle_api::get_courses((int) $params['id']));
            }
        }

        if ($params['id'] <= 0) {
            $data->courseids = $courseids;
            $bundleid = bundle_api::create($data);
        } else {
            $bundleid = (int) $params['id'];
            bundle_api::update($bundleid, $data);
            self::reconcile_courses($bundleid, $courseids);
        }

        // Bundles and programs are products, so they categorise exactly like any other product.
        category_service::set_primary_category($bundleid, $categoryid);

        \local_moderncommerce\audit\audit_service::record(
            $oldbundle ? 'bundle_updated' : 'bundle_created',
            'bundle',
            $bundleid,
            [
                'olddata' => $oldbundle,
                'newdata' => [
                    'bundle' => $data,
                    'courseids' => $courseids,
                ],
                'severity' => 'warning',
            ]
        );

        return [
            'success' => true,
            'bundleid' => $bundleid,
            'message' => get_string('bundlesaved', 'local_moderncommerce'),
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
            'success' => new external_value(PARAM_BOOL, 'Whether the bundle was saved.'),
            'bundleid' => new external_value(PARAM_INT, 'Saved bundle product ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Reconcile included courses to match the submitted set and order.
     *
     * @param int $bundleid Bundle product ID.
     * @param int[] $courseids Desired course IDs in order.
     * @return void
     */
    private static function reconcile_courses(int $bundleid, array $courseids): void {
        $current = [];
        foreach (bundle_api::get_courses($bundleid) as $course) {
            $current[] = (int) $course->courseid;
        }

        foreach (array_diff($current, $courseids) as $courseid) {
            bundle_api::remove_course($bundleid, $courseid);
        }

        foreach (array_diff($courseids, $current) as $courseid) {
            try {
                bundle_api::add_course($bundleid, $courseid);
            } catch (\moodle_exception $e) {
                debugging('Failed to add course to bundle: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        if (!empty($courseids)) {
            bundle_api::reorder_courses($bundleid, $courseids);
        }
    }
}
