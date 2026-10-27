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
 * External API for learner certificates.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use moodle_url;
use xmldb_table;

/**
 * Returns the logged-in learner's Course Certificate records.
 */
class list_certificates extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        self::validate_parameters(self::execute_parameters(), []);
        require_login();

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        $available = self::is_coursecertificate_available();
        $certificates = $available ? self::get_user_certificates((int)$USER->id) : [];

        return [
            'success' => true,
            'available' => $available,
            'message' => $available ? '' : get_string('certificatefeaturesunavailabledesc', 'local_moderncommerce'),
            'certificates' => $certificates,
            'stats' => self::build_stats($certificates),
            'urls' => [
                'catalog' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
                'dashboard' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false),
                'certificates' => (new moodle_url('/local/moderncommerce/learner/certificates.php'))->out(false),
            ],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether certificates loaded.'),
            'available' => new external_value(PARAM_BOOL, 'Whether Course Certificate is available.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'certificates' => new external_multiple_structure(self::certificate_structure()),
            'stats' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total certificates.'),
                'courses' => new external_value(PARAM_INT, 'Unique certificate courses.'),
                'active' => new external_value(PARAM_INT, 'Unexpired certificates.'),
                'latestissued' => new external_value(PARAM_TEXT, 'Latest issued date.'),
            ]),
            'urls' => new external_single_structure([
                'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
                'dashboard' => new external_value(PARAM_RAW, 'Dashboard URL.'),
                'certificates' => new external_value(PARAM_RAW, 'Certificates URL.'),
            ]),
        ]);
    }

    /**
     * Certificate structure.
     *
     * @return external_single_structure
     */
    private static function certificate_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Certificate issue ID.'),
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'coursename' => new external_value(PARAM_TEXT, 'Course name.'),
            'templatename' => new external_value(PARAM_TEXT, 'Template name.'),
            'code' => new external_value(PARAM_TEXT, 'Certificate code.'),
            'issueddate' => new external_value(PARAM_TEXT, 'Issued date.'),
            'expiresdate' => new external_value(PARAM_TEXT, 'Expiry date.'),
            'expired' => new external_value(PARAM_BOOL, 'Whether certificate is expired.'),
            'viewurl' => new external_value(PARAM_RAW, 'View URL.'),
        ]);
    }

    /**
     * Check whether Course Certificate data can be queried.
     *
     * @return bool
     */
    private static function is_coursecertificate_available(): bool {
        global $DB;

        try {
            return $DB->record_exists('modules', ['name' => 'coursecertificate'])
                && self::table_exists('tool_certificate_issues')
                && self::table_exists('tool_certificate_templates');
        } catch (\Exception $exception) {
            return false;
        }
    }

    /**
     * Return certificate records for one learner.
     *
     * @param int $userid User ID.
     * @return array
     */
    private static function get_user_certificates(int $userid): array {
        global $DB;

        try {
            $sql = "SELECT tci.id,
                           tci.courseid,
                           tci.code,
                           tci.timecreated,
                           tci.expires,
                           tct.name AS template_name,
                           c.fullname AS course_name
                      FROM {tool_certificate_issues} tci
                 LEFT JOIN {tool_certificate_templates} tct ON tct.id = tci.templateid
                 LEFT JOIN {course} c ON c.id = tci.courseid
                     WHERE tci.userid = :userid
                       AND tci.archived = 0
                  ORDER BY tci.timecreated DESC, tci.id DESC";
            $records = $DB->get_records_sql($sql, ['userid' => $userid]);
        } catch (\Exception $exception) {
            return [];
        }

        $certificates = [];
        foreach ($records as $record) {
            $expires = !empty($record->expires) ? (int)$record->expires : 0;
            $certificates[] = [
                'id' => (int)$record->id,
                'courseid' => !empty($record->courseid) ? (int)$record->courseid : 0,
                'coursename' => format_string($record->course_name ?? get_string('sitewide', 'local_moderncommerce')),
                'templatename' => format_string($record->template_name ?? get_string('certificate', 'local_moderncommerce')),
                'code' => clean_param((string)$record->code, PARAM_TEXT),
                'issueddate' => self::format_date((int)$record->timecreated),
                'expiresdate' => $expires > 0 ? self::format_date($expires) : '',
                'expired' => $expires > 0 && $expires < time(),
                'viewurl' => (new moodle_url('/admin/tool/certificate/view.php', ['code' => $record->code]))->out(false),
            ];
        }

        return $certificates;
    }

    /**
     * Build certificate summary stats.
     *
     * @param array $certificates Certificate rows.
     * @return array
     */
    private static function build_stats(array $certificates): array {
        $courseids = [];
        $active = 0;
        $latestissued = '';

        foreach ($certificates as $index => $certificate) {
            if ($index === 0) {
                $latestissued = $certificate['issueddate'];
            }
            if (!empty($certificate['courseid'])) {
                $courseids[(int)$certificate['courseid']] = true;
            }
            if (empty($certificate['expired'])) {
                $active++;
            }
        }

        return [
            'total' => count($certificates),
            'courses' => count($courseids),
            'active' => $active,
            'latestissued' => $latestissued,
        ];
    }

    /**
     * Format a Moodle timestamp.
     *
     * @param int $timestamp Unix timestamp.
     * @return string
     */
    private static function format_date(int $timestamp): string {
        return $timestamp > 0 ? userdate($timestamp, get_string('strftimedatefullshort', 'core_langconfig')) : '';
    }

    /**
     * Check whether a database table exists.
     *
     * @param string $table Table name without braces.
     * @return bool
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }
}
