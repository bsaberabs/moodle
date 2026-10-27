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
 * External API for Modern Commerce newsletter / lead-capture subscriptions.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\newsletter;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\services\captcha_service;

/**
 * Stores a storefront newsletter subscription in Modern Commerce core.
 */
class subscribe extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'email' => new external_value(PARAM_EMAIL, 'The email address to subscribe.'),
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Optional source key.', VALUE_DEFAULT, 'storefront'),
            'recaptcharesponse' => new external_value(PARAM_RAW, 'Google reCAPTCHA response token.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Store or accept a newsletter subscription.
     *
     * @param string $email The email address.
     * @param string $source Optional source key.
     * @param string $recaptcharesponse Google reCAPTCHA response token.
     * @return array
     */
    public static function execute(string $email = '', string $source = 'storefront', string $recaptcharesponse = ''): array {
        global $DB, $USER, $CFG, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'email' => $email,
            'source' => $source,
            'recaptcharesponse' => $recaptcharesponse,
        ]);

        $context = context_system::instance();
        if (isloggedin() && !isguestuser()) {
            self::validate_context($context);
            require_capability('local/moderncommerce:viewcatalog', $context);
        } else {
            $PAGE->set_context($context);
            $guestuserid = !empty($CFG->siteguest) ? (int) $CFG->siteguest : 0;
            require_capability('local/moderncommerce:viewcatalog', $context, $guestuserid);
        }

        $captcharesult = captcha_service::verify((string)$params['recaptcharesponse']);
        if ($captcharesult !== true) {
            return [
                'success' => false,
                'message' => $captcharesult,
            ];
        }

        $clean = \core_text::strtolower(trim($params['email']));
        if ($clean === '' || !validate_email($clean)) {
            return [
                'success' => false,
                'message' => get_string('invalidemail', 'local_moderncommerce'),
            ];
        }

        if ($DB->record_exists('local_moderncommerce_subscriber', ['email' => $clean])) {
            return [
                'success' => true,
                'message' => get_string('alreadysubscribed', 'local_moderncommerce'),
            ];
        }

        $DB->insert_record('local_moderncommerce_subscriber', (object) [
            'email' => $clean,
            'source' => $params['source'],
            'userid' => (isloggedin() && !isguestuser()) ? (int) $USER->id : 0,
            'timecreated' => time(),
        ]);

        return [
            'success' => true,
            'message' => get_string('subscribed', 'local_moderncommerce'),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the subscription was accepted.'),
            'message' => new external_value(PARAM_TEXT, 'A message to show the visitor.'),
        ]);
    }
}
