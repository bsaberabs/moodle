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
 * Adhoc task to send contact form emails (Modern Commerce contact core).
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\task;

/**
 * Sends autoreply, admin-notification, reply and client-reply emails for contact submissions.
 */
class send_contact_email extends \core\task\adhoc_task {
    /**
     * Execute the task
     */
    public function execute() {
        global $DB, $CFG;

        $data = $this->get_custom_data();
        $contactid = $data->contactid;
        $type = $data->type; // Email type: autoreply, adminnotify, reply, or clientreply.

        // Get contact record.
        $contact = $DB->get_record('local_moderncommerce_contacts', ['id' => $contactid]);
        if (!$contact) {
            mtrace("Contact record #{$contactid} not found");
            return;
        }

        // Prepare template data.
        $templatedata = [
            'fullname' => $contact->fullname,
            'email' => $contact->email,
            'subject' => $contact->subject,
            'phone' => $contact->phone,
            'message' => $contact->message,
            'submitted_at' => userdate($contact->timecreated),
        ];

        // Get global placeholders.
        $globals = class_exists('\local_moderncommerce\email\placeholder_engine')
            ? \local_moderncommerce\email\placeholder_engine::get_global_placeholder_values()
            : [];

        if ($type === 'autoreply') {
            $this->send_autoreply($contact, $templatedata, $globals);
        } else if ($type === 'adminnotify') {
            $this->send_admin_notification($contact, $templatedata, $globals);
        } else if ($type === 'reply') {
            $this->send_reply_to_client($contact, $data);
        } else if ($type === 'clientreply') {
            $this->send_client_reply_notification($contact, $data);
        }
    }

    /**
     * Send autoreply email to submitter
     */
    private function send_autoreply($contact, $templatedata, $globals) {
        global $CFG;

        $tplid = (int)get_config('local_moderncommerce', 'contact_autoreply_template');
        $subjectoverride = (string)get_config('local_moderncommerce', 'contact_autoreply_subject');
        $bodyoverride = (string)get_config('local_moderncommerce', 'contact_autoreply_body');

        [$subj, $body] = $this->render_email($tplid, $subjectoverride, $bodyoverride, $templatedata, $globals);

        if (empty($body)) {
            mtrace("Autoreply: No email body to send");
            return;
        }

        // Build pseudo-user object.
        $user = new \stdClass();
        $user->id = -99;
        $user->email = $contact->email;
        $user->firstname = $contact->fullname;
        $user->lastname = '';
        $user->firstnamephonetic = '';
        $user->lastnamephonetic = '';
        $user->middlename = '';
        $user->alternatename = '';
        $user->maildisplay = 1;
        $user->mailformat = 1;
        $user->deleted = 0;
        $user->auth = 'manual';
        $user->suspended = 0;
        $user->emailstop = 0;
        $user->username = 'contact_' . time();

        $fromuser = \core_user::get_noreply_user();
        $result = \local_moderncommerce\api\email_api::send_subject_body(
            $user,
            $subj,
            $body,
            $templatedata,
            $fromuser
        );

        if ($result) {
            mtrace("Autoreply sent to {$contact->email}");
        } else {
            mtrace("Failed to send autoreply to {$contact->email}");
        }
    }

    /**
     * Send admin notification email
     */
    private function send_admin_notification($contact, $templatedata, $globals) {
        global $CFG;

        $tplid = (int)get_config('local_moderncommerce', 'contact_adminnotify_template');
        $subjectoverride = (string)get_config('local_moderncommerce', 'contact_adminnotify_subject');
        $bodyoverride = (string)get_config('local_moderncommerce', 'contact_adminnotify_body');
        $recipients = get_config('local_moderncommerce', 'contact_recipient_emails');

        // Fallback to support email if no recipients configured.
        if (empty($recipients)) {
            $recipients = $CFG->supportemail;
        }

        if (empty($recipients)) {
            mtrace("Admin notify: No recipients configured");
            return;
        }

        [$subj, $body] = $this->render_email($tplid, $subjectoverride, $bodyoverride, $templatedata, $globals);

        if (empty($body)) {
            mtrace("Admin notify: No email body to send");
            return;
        }

        $emails = array_filter(array_map('trim', explode(',', $recipients)));
        $fromuser = \core_user::get_noreply_user();

        foreach ($emails as $toemail) {
            if (!validate_email($toemail)) {
                continue;
            }

            // Build pseudo-user object.
            $adminuser = new \stdClass();
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

            $result = \local_moderncommerce\api\email_api::send_subject_body(
                $adminuser,
                $subj,
                $body,
                $templatedata,
                $fromuser
            );

            if ($result) {
                mtrace("Admin notification sent to {$toemail}");
            } else {
                mtrace("Failed to send admin notification to {$toemail}");
            }
        }
    }

    /**
     * Send reply email from admin to client
     *
     * @param \stdClass $contact The contact record
     * @param \stdClass $data The task custom data
     */
    private function send_reply_to_client($contact, $data) {
        $site = get_site();
        $fromuser = \core_user::get_noreply_user();

        // Build reply URL for client.
        $replyurl = new \moodle_url('/local/moderncommerce/contact/reply.php', ['token' => $contact->replytoken]);

        // Email subject.
        $subject = get_string('replyemailsubject', 'local_moderncommerce', [
            'sitename' => format_string($site->fullname),
            'subject' => $contact->subject ?: get_string('pluginname', 'local_moderncommerce'),
        ]);

        // Email body.
        $messagehtml = get_string('replyemailbody', 'local_moderncommerce', [
            'fullname' => format_string($contact->fullname),
            'message' => nl2br(s($data->replymessage)),
            'originalsubject' => s($contact->subject),
            'replyurl' => $replyurl->out(false),
            'sitename' => format_string($site->fullname),
        ]);

        // Build recipient user object.
        $touser = new \stdClass();
        $touser->id = -99;
        $touser->email = $contact->email;
        $touser->firstname = $contact->fullname;
        $touser->lastname = '';
        $touser->firstnamephonetic = '';
        $touser->lastnamephonetic = '';
        $touser->middlename = '';
        $touser->alternatename = '';
        $touser->maildisplay = 1;
        $touser->mailformat = 1;
        $touser->deleted = 0;
        $touser->auth = 'manual';
        $touser->suspended = 0;
        $touser->emailstop = 0;
        $touser->username = 'contact_reply_' . time();

        $result = \local_moderncommerce\api\email_api::send_subject_body(
            $touser,
            $subject,
            $messagehtml,
            [],
            $fromuser
        );

        if ($result) {
            mtrace("Reply sent to client {$contact->email}");
        } else {
            mtrace("Failed to send reply to {$contact->email}");
        }
    }

    /**
     * Send notification to admin when client replies
     *
     * @param \stdClass $contact The contact record
     * @param \stdClass $data The task custom data
     */
    private function send_client_reply_notification($contact, $data) {
        $fromuser = \core_user::get_noreply_user();

        // Get admin recipients from config.
        $adminemails = get_config('local_moderncommerce', 'contact_adminnotify_recipients');
        if (empty($adminemails)) {
            $adminemails = get_config('local_moderncommerce', 'contact_recipient_emails');
        }
        if (empty($adminemails)) {
            mtrace("Client reply notification: No admin recipients configured");
            return;
        }

        $emails = array_filter(array_map('trim', explode(',', $adminemails)));

        foreach ($emails as $email) {
            if (!validate_email($email)) {
                continue;
            }

            $touser = new \stdClass();
            $touser->id = -99;
            $touser->email = $email;
            $touser->firstname = 'Admin';
            $touser->lastname = '';
            $touser->firstnamephonetic = '';
            $touser->lastnamephonetic = '';
            $touser->middlename = '';
            $touser->alternatename = '';
            $touser->maildisplay = 1;
            $touser->mailformat = 1;
            $touser->deleted = 0;
            $touser->auth = 'manual';
            $touser->suspended = 0;
            $touser->emailstop = 0;
            $touser->username = 'admin_clientreply_' . time();

            $subject = get_string('clientreplynotifysubject', 'local_moderncommerce', [
                'fullname' => format_string($contact->fullname),
            ]);
            $messagehtml = get_string('clientreplynotifybody', 'local_moderncommerce', [
                'fullname' => format_string($contact->fullname),
                'email' => s($contact->email),
                'message' => nl2br(s($data->replymessage)),
                'viewurl' => (new \moodle_url('/local/moderncommerce/admin/contacts.php'))->out(false),
            ]);
            $result = \local_moderncommerce\api\email_api::send_subject_body(
                $touser,
                $subject,
                $messagehtml,
                [],
                $fromuser
            );

            if ($result) {
                mtrace("Client reply notification sent to {$email}");
            } else {
                mtrace("Failed to send client reply notification to {$email}");
            }
        }
    }

    /**
     * Render email subject and body
     */
    private function render_email($tplid, $subjectoverride, $bodyoverride, $templatedata, $globals) {
        $subject = trim($subjectoverride);
        $body = trim($bodyoverride);

        // If overrides exist, render them directly.
        if ($body !== '' || $subject !== '') {
            if (class_exists('\local_moderncommerce\email\placeholder_engine')) {
                $engine = new \local_moderncommerce\email\placeholder_engine();
                if ($subject !== '') {
                    $subject = $engine->substitute_placeholders($subject, $templatedata, $globals);
                }
                if ($body !== '') {
                    $body = $engine->substitute_placeholders($body, $templatedata, $globals);
                }
            }
            return [$subject, $body];
        }

        // Otherwise, if a template id is selected, load and render it.
        if (!empty($tplid) && class_exists('\local_moderncommerce\email\template_manager')) {
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
    }
}
