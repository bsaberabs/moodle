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
 * Client reply page - allows clients to respond via email link (Modern Commerce contact core).
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public reply link is authenticated by a single-use contact token.
require_once(__DIR__ . '/../../../config.php');

$token = required_param('token', PARAM_ALPHANUM);
$action = optional_param('action', '', PARAM_ALPHA);
$replymessage = optional_param('replymessage', '', PARAM_TEXT);

// Find the contact by token.
$record = $DB->get_record('local_moderncommerce_contacts', ['replytoken' => $token]);

if (!$record) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/local/moderncommerce/contact/reply.php', ['token' => $token]));
    $PAGE->set_title(get_string('invalidtoken', 'local_moderncommerce'));
    $PAGE->set_heading(get_string('invalidtoken', 'local_moderncommerce'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('invalidtoken', 'local_moderncommerce'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// Handle reply submission.
$replyerror = '';
$replyvalue = '';

if ($action === 'reply') {
    // Previously the whole block was skipped when the message was empty, so
    // pressing send did nothing and said nothing.
    $trimmed = trim($replymessage);
    if ($trimmed === '') {
        $replyerror = get_string('error:required', 'local_moderncommerce');
    } else if (core_text::strlen($trimmed) > 5000) {
        $replyerror = get_string('error:maxlength', 'local_moderncommerce', 5000);
        $replyvalue = $trimmed;
    }
}

if ($action === 'reply' && $replyerror === '' && $replymessage !== '') {
    require_sesskey();
    $replymessage = trim($replymessage);

    if (!empty($replymessage)) {
        // Store reply in database.
        $reply = new stdClass();
        $reply->contactid = $record->id;
        $reply->userid = null; // Client reply, no user ID.
        $reply->fromclient = 1;
        $reply->message = $replymessage;
        $reply->timecreated = time();
        $DB->insert_record('local_moderncommerce_contact_replies', $reply);

        // Update contact status to new (so admin sees there's a new reply).
        $DB->set_field('local_moderncommerce_contacts', 'status', 'new', ['id' => $record->id]);
        $DB->set_field('local_moderncommerce_contacts', 'timemodified', time(), ['id' => $record->id]);

        // Queue notification to admin via adhoc task.
        $task = new \local_moderncommerce\task\send_contact_email();
        $task->set_custom_data([
            'contactid' => $record->id,
            'type' => 'clientreply',
            'replymessage' => $replymessage,
        ]);
        \core\task\manager::queue_adhoc_task($task);

        redirect(
            new moodle_url('/local/moderncommerce/contact/reply.php', ['token' => $token]),
            get_string('clientreplysent', 'local_moderncommerce'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

// Page setup.
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/contact/reply.php', ['token' => $token]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('clientreply', 'local_moderncommerce'));
$PAGE->set_heading(get_string('clientreply', 'local_moderncommerce'));

// Get conversation thread.
$replies = $DB->get_records('local_moderncommerce_contact_replies', ['contactid' => $record->id], 'timecreated ASC');
$thread = [];

// Add original message.
$thread[] = [
    'fromclient' => true,
    'fromadmin' => false,
    'sendername' => format_string($record->fullname),
    'message' => format_text($record->message, FORMAT_PLAIN),
    'timecreated' => userdate($record->timecreated),
    'isoriginal' => true,
];

// Add replies.
foreach ($replies as $reply) {
    $sendername = get_string('you', 'local_moderncommerce');
    if (!$reply->fromclient) {
        $sendername = format_string(get_site()->fullname);
    }
    $thread[] = [
        'fromclient' => (bool)$reply->fromclient,
        'fromadmin' => !$reply->fromclient,
        'sendername' => $sendername,
        'message' => format_text($reply->message, FORMAT_PLAIN),
        'timecreated' => userdate($reply->timecreated),
        'isoriginal' => false,
    ];
}

// Build context data.
$contextdata = [
    'token' => $token,
    'sesskey' => sesskey(),
    'fullname' => format_string($record->fullname),
    'subject' => s((string)$record->subject),
    'thread' => $thread,
    'replyurl' => (new moodle_url('/local/moderncommerce/contact/reply.php'))->out(false),
    'sitename' => format_string(get_site()->fullname),
];

$contextdata['replyerror'] = $replyerror;
$contextdata['replyvalue'] = $replyvalue;

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moderncommerce/contact/clientreply', $contextdata);
echo $OUTPUT->footer();
