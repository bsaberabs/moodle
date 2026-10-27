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
 * External API previewing an enrollment key for the buyer redeem screen.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\redeem;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\key_redemption_service;

/**
 * Validate a key code and report what it grants without redeeming it.
 */
class validate_key extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'keycode' => new external_value(PARAM_ALPHANUMEXT, 'Key code.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $keycode Key code.
     * @return array
     */
    public static function execute(string $keycode): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['keycode' => $keycode]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:redeemkey', $context);

        $preview = key_redemption_service::preview(trim($params['keycode']), (int) $USER->id);

        return [
            'valid' => (bool) $preview['valid'],
            'message' => $preview['errorkey'] !== null
                ? get_string($preview['errorkey'], 'local_moderncommerce')
                : '',
            'courses' => array_map(static function (array $course): array {
                return [
                    'id' => $course['id'],
                    'fullname' => $course['fullname'],
                    'alreadyenrolled' => $course['alreadyenrolled'],
                    'url' => $course['url'],
                ];
            }, $preview['courses']),
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
            'valid' => new external_value(PARAM_BOOL, 'Whether the key can be redeemed.'),
            'message' => new external_value(PARAM_TEXT, 'Validation message when not valid.'),
            'courses' => new external_multiple_structure(self::course_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Course structure.
     *
     * @return external_single_structure
     */
    public static function course_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Course full name.'),
            'alreadyenrolled' => new external_value(PARAM_BOOL, 'Whether the user is already enrolled.'),
            'url' => new external_value(PARAM_URL, 'Course URL.'),
        ]);
    }
}
