<?php
// This file is part of Moodle and is licensed under the GNU GPL v3 or later.
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
 * External service for searching program prerequisite courses.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\bundles;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\services\bundle_meta_service;

/**
 * Search visible Moodle courses that can be program certificate prerequisites.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_prerequisite_courses extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'bundleid' => new external_value(PARAM_INT, 'Program product ID.'),
            'query' => new external_value(PARAM_TEXT, 'Course name or short name search.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Search prerequisite candidates.
     *
     * @param int $bundleid Program product ID.
     * @param string $query Course search query.
     * @return array
     */
    public static function execute(int $bundleid, string $query = ''): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'bundleid' => $bundleid,
            'query' => $query,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $DB->get_record('local_moderncommerce_products', [
            'id' => $params['bundleid'],
            'producttype' => 'program',
        ], 'id', MUST_EXIST);

        return [
            'courses' => bundle_meta_service::search_prereq_candidates(
                (int)$params['bundleid'],
                (string)$params['query'],
                20
            ),
        ];
    }

    /**
     * Define the service return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID.'),
                'fullname' => new external_value(PARAM_TEXT, 'Course full name.'),
            ])),
        ]);
    }
}
