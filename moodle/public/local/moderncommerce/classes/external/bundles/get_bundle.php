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
 * External API returning one bundle/program for the admin builder.
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
use local_moderncommerce\services\pricing_service;

/**
 * Get one bundle/program with included courses and savings for the admin builder.
 */
class get_bundle extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Bundle product ID.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Bundle product ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $CFG;

        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $bundle = bundle_api::get($params['id']);
        if (!$bundle) {
            throw new \moodle_exception('invalidbundle', 'local_moderncommerce');
        }

        $fs = get_file_storage();
        $hasimage = (bool) $fs->get_area_files(
            $context->id,
            'local_moderncommerce',
            'bundleimage',
            $params['id'],
            'id',
            false
        );
        $imageurl = $hasimage ? local_moderncommerce_get_bundle_image_url($params['id']) : '';

        $onsale = !empty($bundle->saleprice) && (float) $bundle->saleprice > 0;
        $regularprice = (float) $bundle->price;
        $saleprice = $onsale ? (float) $bundle->saleprice : 0.0;

        $courses = [];
        foreach (bundle_api::get_courses($params['id']) as $course) {
            $courses[] = [
                'courseid' => (int) $course->courseid,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'shortname' => format_string($course->shortname, true, ['context' => $context]),
                'visible' => !empty($course->visible),
                'sortorder' => (int) $course->sortorder,
            ];
        }

        $savings = bundle_api::calculate_savings($params['id']);

        return [
            'id' => (int) $bundle->id,
            'name' => $bundle->name,
            'shortdescription' => (string) ($bundle->shortdescription ?? ''),
            'description' => (string) ($bundle->description ?? ''),
            'isprogram' => !empty($bundle->isprogram),
            'status' => (string) $bundle->status,
            'visible' => !empty($bundle->visible),
            'featured' => !empty($bundle->featured),
            'displayorder' => (int) ($bundle->displayorder ?? 0),
            'maxenrollment' => (int) ($bundle->maxenrollment ?? 0),
            'primarycategoryid' => (int) (
                category_service::get_primary_category_map([(int) $bundle->id])[(int) $bundle->id]['id'] ?? 0
            ),
            'price' => $regularprice,
            'saleprice' => $saleprice,
            'salestartdate' => (int) ($bundle->salestartdate ?? 0),
            'saleenddate' => (int) ($bundle->saleenddate ?? 0),
            'imageurl' => $imageurl,
            'hasimage' => $hasimage,
            'courses' => $courses,
            'savings' => [
                'total' => (float) $savings['total'],
                'bundle' => (float) $savings['bundle'],
                'savings' => (float) $savings['savings'],
                'percentage' => (int) $savings['percentage'],
                'displaytotal' => pricing_service::format_price((float) $savings['total']),
                'displaysavings' => pricing_service::format_price((float) $savings['savings']),
            ],
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
            'id' => new external_value(PARAM_INT, 'Bundle product ID.'),
            'name' => new external_value(PARAM_TEXT, 'Bundle name.'),
            'shortdescription' => new external_value(PARAM_RAW, 'Short description.'),
            'description' => new external_value(PARAM_RAW, 'Full description.'),
            'isprogram' => new external_value(PARAM_BOOL, 'Whether this is a program.'),
            'status' => new external_value(PARAM_ALPHA, 'Status.'),
            'visible' => new external_value(PARAM_BOOL, 'Whether visible.'),
            'featured' => new external_value(PARAM_BOOL, 'Whether featured.'),
            'displayorder' => new external_value(PARAM_INT, 'Display order.'),
            'maxenrollment' => new external_value(PARAM_INT, 'Max enrollment (0 = unlimited).'),
            'primarycategoryid' => new external_value(PARAM_INT, 'Primary product category ID (0 = none).'),
            'price' => new external_value(PARAM_FLOAT, 'Regular price.'),
            'saleprice' => new external_value(PARAM_FLOAT, 'Sale price (0 = none).'),
            'salestartdate' => new external_value(PARAM_INT, 'Sale start timestamp.'),
            'saleenddate' => new external_value(PARAM_INT, 'Sale end timestamp.'),
            'imageurl' => new external_value(PARAM_RAW, 'Resolved bundle image URL (empty when none).'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether a custom image is stored.'),
            'courses' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Course ID.'),
                'fullname' => new external_value(PARAM_TEXT, 'Course full name.'),
                'shortname' => new external_value(PARAM_TEXT, 'Course short name.'),
                'visible' => new external_value(PARAM_BOOL, 'Whether the course is visible.'),
                'sortorder' => new external_value(PARAM_INT, 'Sort order.'),
            ])),
            'savings' => new external_single_structure([
                'total' => new external_value(PARAM_FLOAT, 'Sum of included course prices.'),
                'bundle' => new external_value(PARAM_FLOAT, 'Bundle price.'),
                'savings' => new external_value(PARAM_FLOAT, 'Savings amount.'),
                'percentage' => new external_value(PARAM_INT, 'Savings percentage.'),
                'displaytotal' => new external_value(PARAM_TEXT, 'Formatted total.'),
                'displaysavings' => new external_value(PARAM_TEXT, 'Formatted savings.'),
            ]),
            'warnings' => new external_warnings(),
        ]);
    }
}
