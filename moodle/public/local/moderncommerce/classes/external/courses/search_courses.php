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
 * External API for searching Moodle courses from commerce admin screens.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\courses;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Search courses for the pricing React typeahead picker.
 */
class search_courses extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(
                PARAM_TEXT,
                'Search by course name, shortname, idnumber, or numeric ID.',
                VALUE_DEFAULT,
                ''
            ),
            'limit' => new external_value(PARAM_INT, 'Maximum records to return.', VALUE_DEFAULT, 20),
        ]);
    }

    /**
     * Execute course search.
     *
     * @param string $query Search term.
     * @param int $limit Result limit.
     * @return array
     */
    public static function execute(string $query = '', int $limit = 20): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'limit' => $limit,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $query = trim((string) $params['query']);
        $limit = min(50, max(5, (int) $params['limit']));

        [$where, $sqlparams] = self::get_search_sql($query);
        $sqlparams['siteid'] = SITEID;

        $countsql = "SELECT COUNT(1)
                       FROM {course} c
                      WHERE c.id <> :siteid
                        AND {$where}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $sqlparams['relationtype'] = 'included';
        $sqlparams['producttype'] = 'course';

        $sql = "SELECT c.id,
                       c.fullname,
                       c.shortname,
                       c.idnumber,
                       c.summary,
                       c.visible,
                       cc.name AS categoryname,
                       existing.productid AS existingproductid,
                       existing.productname AS existingproductname,
                       existing.productstatus AS existingproductstatus
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
             LEFT JOIN (
                       SELECT pc.courseid,
                              MIN(p.id) AS productid,
                              MIN(p.name) AS productname,
                              MIN(p.status) AS productstatus
                         FROM {local_moderncommerce_product_courses} pc
                         JOIN {local_moderncommerce_products} p ON p.id = pc.productid
                        WHERE pc.relationtype = :relationtype
                          AND p.producttype = :producttype
                          AND p.status <> 'archived'
                     GROUP BY pc.courseid
                       ) existing ON existing.courseid = c.id
                 WHERE c.id <> :siteid
                   AND {$where}
              ORDER BY c.fullname ASC, c.shortname ASC";

        $records = $DB->get_records_sql($sql, $sqlparams, 0, $limit);
        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_course($record, $context);
        }

        return [
            'items' => $items,
            'total' => $total,
            'query' => $query,
            'limit' => $limit,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::course_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching courses.'),
            'query' => new external_value(PARAM_TEXT, 'Applied query.'),
            'limit' => new external_value(PARAM_INT, 'Applied result limit.'),
        ]);
    }

    /**
     * Course return structure.
     *
     * @return external_single_structure
     */
    private static function course_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Course fullname.'),
            'shortname' => new external_value(PARAM_TEXT, 'Course shortname.'),
            'idnumber' => new external_value(PARAM_TEXT, 'Course ID number.'),
            'categoryname' => new external_value(PARAM_TEXT, 'Course category name.'),
            'summary' => new external_value(PARAM_TEXT, 'Plain text course summary.'),
            'visible' => new external_value(PARAM_BOOL, 'Whether course is visible.'),
            'existingproductid' => new external_value(PARAM_INT, 'Existing course product ID, or 0.'),
            'existingproductname' => new external_value(PARAM_TEXT, 'Existing course product name.'),
            'existingproductstatus' => new external_value(PARAM_TEXT, 'Existing course product status.'),
            'suggestedsku' => new external_value(PARAM_TEXT, 'Suggested product SKU.'),
            'suggestedslug' => new external_value(PARAM_TEXT, 'Suggested product slug.'),
        ]);
    }

    /**
     * Build search SQL for a query.
     *
     * @param string $query Search term.
     * @return array SQL fragment and params.
     */
    private static function get_search_sql(string $query): array {
        global $DB;

        if ($query === '') {
            return ['1 = 1', []];
        }

        $where = [
            $DB->sql_like('c.fullname', ':fullname', false),
            $DB->sql_like('c.shortname', ':shortname', false),
            $DB->sql_like('c.idnumber', ':idnumber', false),
        ];
        $search = '%' . $DB->sql_like_escape($query) . '%';
        $params = [
            'fullname' => $search,
            'shortname' => $search,
            'idnumber' => $search,
        ];

        if (ctype_digit($query)) {
            $where[] = 'c.id = :exactid';
            $params['exactid'] = (int) $query;
        }

        return ['(' . implode(' OR ', $where) . ')', $params];
    }

    /**
     * Format a course record for the typeahead.
     *
     * @param \stdClass $record Course row.
     * @param context_system $context System context.
     * @return array
     */
    private static function format_course(\stdClass $record, context_system $context): array {
        $shortname = (string) $record->shortname;

        return [
            'id' => (int) $record->id,
            'fullname' => format_string($record->fullname, true, ['context' => $context]),
            'shortname' => format_string($shortname, true, ['context' => $context]),
            'idnumber' => (string) $record->idnumber,
            'categoryname' => format_string($record->categoryname, true, ['context' => $context]),
            'summary' => trim(strip_tags((string) $record->summary)),
            'visible' => !empty($record->visible),
            'existingproductid' => $record->existingproductid === null ? 0 : (int) $record->existingproductid,
            'existingproductname' => $record->existingproductname === null
                ? ''
                : format_string($record->existingproductname, true, ['context' => $context]),
            'existingproductstatus' => $record->existingproductstatus === null ? '' : (string) $record->existingproductstatus,
            'suggestedsku' => self::suggest_sku((int) $record->id, $shortname),
            'suggestedslug' => self::slugify($shortname !== '' ? $shortname : (string) $record->fullname),
        ];
    }

    /**
     * Build a stable suggested SKU.
     *
     * @param int $courseid Course ID.
     * @param string $shortname Course shortname.
     * @return string
     */
    private static function suggest_sku(int $courseid, string $shortname): string {
        $sku = strtoupper(preg_replace('/[^A-Z0-9_-]+/i', '-', trim($shortname)));
        $sku = trim((string) $sku, '-_');

        return $sku !== '' ? $sku : 'COURSE-' . $courseid;
    }

    /**
     * Build a URL-safe slug.
     *
     * @param string $value Source value.
     * @return string
     */
    private static function slugify(string $value): string {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug);
        $slug = trim((string) $slug, '-_');

        return $slug !== '' ? $slug : 'course';
    }
}
