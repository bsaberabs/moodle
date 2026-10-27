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
 * Public contact-form POST endpoint (Modern Commerce contact core).
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public contact endpoint protected by sesskey, captcha, and rate limits.
require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\services\captcha_service;
use local_moderncommerce\services\contact_service;

// Set page context (required for email functions).
$PAGE->set_context(context_system::instance());

// No login required; protect via sesskey to avoid CSRF.
require_sesskey();

$fullname = trim(optional_param('fullname', '', PARAM_TEXT));
$email    = trim(optional_param('email', '', PARAM_EMAIL));
$subject  = trim(optional_param('subject', '', PARAM_TEXT));
$phone    = trim(optional_param('phone', '', PARAM_TEXT));
$message  = trim(optional_param('message', '', PARAM_TEXT));
$returnurl = new moodle_url(optional_param('returnurl', '/', PARAM_URL));

$errors = contact_service::validate_enquiry([
    'fullname' => $fullname,
    'email' => $email,
    'subject' => $subject,
    'phone' => $phone,
    'message' => $message,
]);

if (!empty($errors)) {
    // Stash the per-field messages plus what was typed, so the page the user
    // lands back on can highlight the offending fields instead of showing one
    // generic sentence and discarding their message.
    global $SESSION;
    $SESSION->local_moderncommerce_contact_errors = $errors;
    $SESSION->local_moderncommerce_contact_values = [
        'fullname' => $fullname,
        'email' => $email,
        'subject' => $subject,
        'phone' => $phone,
        'message' => $message,
    ];
    redirect($returnurl, reset($errors), 5, \core\output\notification::NOTIFY_ERROR);
}

$captcharesult = captcha_service::verify(captcha_service::request_response());
if ($captcharesult !== true) {
    redirect($returnurl, $captcharesult, 5);
}

global $DB, $CFG;

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

// Simple rate limiting: limit repeated submissions by same email within 5 minutes.
$fiveminago = time() - 300;
$recentcount = $DB->count_records_select('local_moderncommerce_contacts', 'email = :email AND timecreated >= :since', [
    'email' => $email,
    'since' => $fiveminago,
]);
if ($recentcount >= 3) {
    redirect($returnurl, get_string('contact_error', 'local_moderncommerce'), 5);
}

$DB->insert_record('local_moderncommerce_contacts', $record);

// Send emails via Modern Commerce Email Templates if available.
$autoreplytplid = (int)get_config('local_moderncommerce', 'contact_autoreply_template');
$admintplid     = (int)get_config('local_moderncommerce', 'contact_adminnotify_template');
$autoreplysubject = (string)get_config('local_moderncommerce', 'contact_autoreply_subject');
$autoreplybody    = (string)get_config('local_moderncommerce', 'contact_autoreply_body');
$adminsubject     = (string)get_config('local_moderncommerce', 'contact_adminnotify_subject');
$adminbody        = (string)get_config('local_moderncommerce', 'contact_adminnotify_body');
$recipients   = get_config('local_moderncommerce', 'contact_recipient_emails');

$templatedata = [
    'fullname' => $fullname,
    'email' => $email,
    'subject' => $subject,
    'phone' => $phone,
    'message' => $message,
    'submitted_at' => userdate(time()),
];

$globals = class_exists('\local_moderncommerce\email\placeholder_engine')
    ? \local_moderncommerce\email\placeholder_engine::get_global_placeholder_values()
    : [];

// Helper to render subject/body either from overrides or selected template id.
$renderemail = function ($tplid, $subjectoverride, $bodyoverride) use ($templatedata, $globals) {
    $subject = trim((string)$subjectoverride);
    $body = trim((string)$bodyoverride);

    // If overrides exist, render them directly.
    if ($body !== '' || $subject !== '') {
        $engine = new \local_moderncommerce\email\placeholder_engine();
        if ($subject !== '') {
            $subject = $engine->substitute_placeholders($subject, $templatedata, $globals);
        }
        if ($body !== '') {
            $body = $engine->substitute_placeholders($body, $templatedata, $globals);
        }
        return [$subject, $body];
    }

    // Otherwise, if a template id is selected, load and render it.
    if (!empty($tplid)) {
        $manager = new \local_moderncommerce\email\template_manager();
        $template = $manager->get_template_by_id($tplid);
        if ($template && (!isset($template->status) || $template->status === 'active')) {
            $engine = new \local_moderncommerce\email\placeholder_engine();
            $subject = $engine->substitute_placeholders((string)$template->subject, $templatedata, $globals);
            $body = $engine->substitute_placeholders((string)$template->body, $templatedata, $globals);
            return [$subject, $body];
        }
    }

    return [null, null];
};

// Autoreply to submitter.
$autoreplyenabled = get_config('local_moderncommerce', 'contact_autoreply_enabled');
if ($autoreplyenabled !== '0' && class_exists('\local_moderncommerce\api\email_api')) {
    // Build a pseudo-user object with all required fields for email_to_user.
    $user = new stdClass();
    $user->id = -99; // Fake ID for non-existent user.
    $user->email = $email;
    $user->firstname = $fullname;
    $user->lastname = '';
    $user->firstnamephonetic = '';
    $user->lastnamephonetic = '';
    $user->middlename = '';
    $user->alternatename = '';
    $user->maildisplay = 1;
    $user->mailformat = 1; // HTML.
    $user->deleted = 0;
    $user->auth = 'manual';
    $user->suspended = 0;
    $user->emailstop = 0;
    $user->username = 'contact_' . time();

    try {
        [$subj, $body] = $renderemail($autoreplytplid, $autoreplysubject, $autoreplybody);
        if (!empty($body)) {
            $fromuser = \core_user::get_noreply_user();
            \local_moderncommerce\api\email_api::send_subject_body(
                $user,
                $subj ?: get_string('emailsubject_autoreply_default', 'local_moderncommerce'),
                $body,
                $templatedata,
                $fromuser
            );
        }
    } catch (Exception $e) {
        debugging('Contact autoreply failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

// Admin/staff notification.
$adminnotifyenabled = get_config('local_moderncommerce', 'contact_adminnotify_enabled');
// If contact_recipient_emails is empty, fallback to support email.
if (empty($recipients)) {
    $recipients = $CFG->supportemail;
}
if ($adminnotifyenabled !== '0' && !empty($recipients) && class_exists('\local_moderncommerce\api\email_api')) {
    $emails = array_filter(array_map('trim', explode(',', $recipients)));
    foreach ($emails as $toemail) {
        if (!validate_email($toemail)) {
            continue;
        }
        // Build a pseudo-user object with all required fields for email_to_user.
        $adminuser = new stdClass();
        $adminuser->id = -99;
        $adminuser->email = $toemail;
        $adminuser->firstname = 'Contact';
        $adminuser->lastname = 'Notification';
        $adminuser->firstnamephonetic = '';
        $adminuser->lastnamephonetic = '';
        $adminuser->middlename = '';
        $adminuser->alternatename = '';
        $adminuser->maildisplay = 1;
        $adminuser->mailformat = 1;
        $adminuser->deleted = 0;
        $adminuser->auth = 'manual';
        $adminuser->suspended = 0;
        $adminuser->emailstop = 0;
        $adminuser->username = 'admin_notify_' . time();

        try {
            [$subj, $body] = $renderemail($admintplid, $adminsubject, $adminbody);
            if (!empty($body)) {
                $fromuser = \core_user::get_noreply_user();
                \local_moderncommerce\api\email_api::send_subject_body(
                    $adminuser,
                    $subj ?: get_string('emailsubject_admin_default', 'local_moderncommerce'),
                    $body,
                    $templatedata,
                    $fromuser
                );
            }
        } catch (Exception $e) {
            debugging('Contact admin notify failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}

redirect($returnurl, get_string('contact_submitted', 'local_moderncommerce'));
