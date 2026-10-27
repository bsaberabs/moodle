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
 * AJAX endpoint for contact form submission (Modern Commerce contact core).
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public endpoint protected by sesskey, captcha, and rate limits.
require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\services\captcha_service;
use local_moderncommerce\services\contact_service;

// Set proper headers for JSON response.
header('Content-Type: application/json');

try {
    // Set page context.
    $PAGE->set_context(context_system::instance());

    require_sesskey();

    // Get validated request parameters.
    $fullname = trim(optional_param('fullname', '', PARAM_TEXT));
    $email    = trim(optional_param('email', '', PARAM_EMAIL));
    $subject  = trim(optional_param('subject', '', PARAM_TEXT));
    $phone    = trim(optional_param('phone', '', PARAM_TEXT));
    $message  = trim(optional_param('message', '', PARAM_TEXT));
    $recaptcharesponse = optional_param('recaptcharesponse', '', PARAM_RAW_TRIMMED);
    if ($recaptcharesponse === '') {
        $recaptcharesponse = optional_param('g-recaptcha-response', '', PARAM_RAW_TRIMMED);
    }

    $captcharesult = captcha_service::verify($recaptcharesponse);
    if ($captcharesult !== true) {
        echo json_encode([
            'success' => false,
            'error' => $captcharesult,
        ]);
        die();
    }

    // Validate through the shared rules so this endpoint and its synchronous
    // twin cannot drift apart. Errors are returned per field, in the same shape
    // the web service layer uses, so one JavaScript handler serves both.
    $errors = contact_service::validate_enquiry([
        'fullname' => $fullname,
        'email' => $email,
        'subject' => $subject,
        'phone' => $phone,
        'message' => $message,
    ]);

    if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'error' => reset($errors),
            'errors' => contact_service::format_errors($errors),
        ]);
        die();
    }

    // Simple rate limiting: limit repeated submissions by same email within 5 minutes.
    $fiveminago = time() - 300;
    $recentcount = $DB->count_records_select('local_moderncommerce_contacts', 'email = :email AND timecreated >= :since', [
        'email' => $email,
        'since' => $fiveminago,
    ]);
    if ($recentcount >= 3) {
        echo json_encode([
            'success' => false,
            'error' => get_string('ratelimit_error', 'local_moderncommerce'),
        ]);
        die();
    }

    // Insert record.
    $record = (object) [
        'fullname' => $fullname,
        'email' => $email,
        'subject' => $subject,
        'phone' => $phone,
        'message' => $message,
        'status' => 'new',
        'source' => 'block_moderncommerce_hero_contact',
        'timecreated' => time(),
        'timemodified' => time(),
    ];

    $contactid = $DB->insert_record('local_moderncommerce_contacts', $record);

    // Queue email tasks.
    $autoreplyenabled = get_config('local_moderncommerce', 'contact_autoreply_enabled');
    $adminnotifyenabled = get_config('local_moderncommerce', 'contact_adminnotify_enabled');

    // Queue autoreply email.
    if ($autoreplyenabled !== '0') {
        $task = new \local_moderncommerce\task\send_contact_email();
        $task->set_custom_data([
            'contactid' => $contactid,
            'type' => 'autoreply',
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    // Queue admin notification email.
    if ($adminnotifyenabled !== '0') {
        $task = new \local_moderncommerce\task\send_contact_email();
        $task->set_custom_data([
            'contactid' => $contactid,
            'type' => 'adminnotify',
        ]);
        \core\task\manager::queue_adhoc_task($task);
    }

    echo json_encode([
        'success' => true,
        'message' => get_string('contact_submitted', 'local_moderncommerce'),
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
    ]);
}
