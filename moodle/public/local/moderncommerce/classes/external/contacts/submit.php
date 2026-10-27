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
 * Guest contact-form submission webservice (Modern Commerce contact core).
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\contacts;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use local_moderncommerce\services\captcha_service;

/**
 * Accepts public contact-form submissions and queues the notification emails.
 */
class submit extends external_api {
    /**
     * Parameters for execute.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fullname' => new external_value(PARAM_TEXT, 'Full name', VALUE_REQUIRED),
            'email' => new external_value(PARAM_EMAIL, 'Email', VALUE_REQUIRED),
            'subject' => new external_value(PARAM_TEXT, 'Subject', VALUE_DEFAULT, ''),
            'phone' => new external_value(PARAM_TEXT, 'Phone', VALUE_DEFAULT, ''),
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_REQUIRED),
            // Optional sesskey for CSRF protection when available (logged-in users).
            'sesskey' => new external_value(PARAM_ALPHANUMEXT, 'Session key', VALUE_DEFAULT, null),
            'recaptcharesponse' => new external_value(PARAM_RAW, 'Google reCAPTCHA response token', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Submit a contact form entry.
     *
     * @param string $fullname Full name.
     * @param string $email Email.
     * @param string $subject Subject.
     * @param string $phone Phone.
     * @param string $message Message.
     * @param string|null $sesskey Optional sesskey.
     * @param string $recaptcharesponse Google reCAPTCHA response token.
     * @return array
     */
    public static function execute(
        $fullname,
        $email,
        $subject = '',
        $phone = '',
        $message = '',
        $sesskey = null,
        $recaptcharesponse = ''
    ) {
        global $DB, $CFG;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('fullname', 'email', 'subject', 'phone', 'message', 'sesskey', 'recaptcharesponse')
        );
        $fullname = $params['fullname'];
        $email = $params['email'];
        $subject = $params['subject'];
        $phone = $params['phone'];
        $message = $params['message'];
        $sesskey = $params['sesskey'];
        $recaptcharesponse = $params['recaptcharesponse'];

        self::validate_context(\context_system::instance());

        // Do not enforce login; this endpoint accepts guest submissions.
        // If a sesskey was provided (from logged-in users), validate it for CSRF protection.
        if (!is_null($sesskey) && $sesskey !== '') {
            if (!confirm_sesskey($sesskey)) {
                return [
                    'success' => false,
                    'message' => get_string('invalidsesskey', 'error'),
                ];
            }
        }

        $captcharesult = captcha_service::verify((string)$recaptcharesponse);
        if ($captcharesult !== true) {
            return [
                'success' => false,
                'message' => $captcharesult,
            ];
        }

        // Basic validation (allow multi-line message, but trim for emptiness check).
        $message = trim($message);
        if (trim($fullname) === '' || trim($email) === '' || $message === '') {
            return [
                'success' => false,
                'message' => get_string('contact_error', 'local_moderncommerce'),
            ];
        }

        // Rate limit.
        $fiveminago = time() - 300;
        $recentcount = $DB->count_records_select('local_moderncommerce_contacts', 'email = :email AND timecreated >= :since', [
            'email' => $email,
            'since' => $fiveminago,
        ]);
        if ($recentcount >= 3) {
            return [
                'success' => false,
                'message' => get_string('ratelimit_error', 'local_moderncommerce'),
            ];
        }

        // Insert record.
        $record = (object) [
            'fullname' => trim($fullname),
            'email' => trim($email),
            'subject' => trim($subject),
            'phone' => trim($phone),
            'message' => $message,
            'status' => 'new',
            'source' => 'block_moderncommerce_hero_contact',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $contactid = $DB->insert_record('local_moderncommerce_contacts', $record);

        // Queue tasks.
        $autoreplyenabled = get_config('local_moderncommerce', 'contact_autoreply_enabled');
        $adminnotifyenabled = get_config('local_moderncommerce', 'contact_adminnotify_enabled');

        if ($autoreplyenabled !== '0') {
            $task = new \local_moderncommerce\task\send_contact_email();
            $task->set_custom_data(['contactid' => $contactid, 'type' => 'autoreply']);
            \core\task\manager::queue_adhoc_task($task);
        }
        if ($adminnotifyenabled !== '0') {
            $task = new \local_moderncommerce\task\send_contact_email();
            $task->set_custom_data(['contactid' => $contactid, 'type' => 'adminnotify']);
            \core\task\manager::queue_adhoc_task($task);
        }

        return [
            'success' => true,
            'message' => get_string('contact_submitted', 'local_moderncommerce'),
        ];
    }

    /**
     * Return structure for execute.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success flag'),
            'message' => new external_value(PARAM_TEXT, 'Message'),
        ]);
    }
}
