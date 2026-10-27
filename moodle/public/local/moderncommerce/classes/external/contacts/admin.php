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
 * Admin webservices for contact submissions, consumed by the Modern Commerce React screens.
 *
 * Contact capture is core to Modern Commerce: it owns the data, email engine, and
 * public submit/reply endpoints, and renders the admin UI against these endpoints.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\contacts;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\localisation;
use core_external\external_warnings;

/**
 * Contact admin webservice methods.
 */
class admin extends external_api {
    /** @var int Maximum rows per page. */
    private const MAX_PER_PAGE = 100;

    /** @var string[] Valid contact statuses. */
    private const STATUSES = ['new', 'inprogress', 'closed', 'archived'];

    /** @var string[] Email placeholders available in templates/overrides. */
    private const PLACEHOLDERS = ['{fullname}', '{email}', '{subject}', '{phone}', '{message}', '{submitted_at}', '{sitename}'];

    /**
     * Parameters for get_contacts.
     *
     * @return external_function_parameters
     */
    public static function get_contacts_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search text.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status filter, or empty for all.', VALUE_DEFAULT, ''),
            'datefrom' => new external_value(PARAM_INT, 'From timestamp, or 0.', VALUE_DEFAULT, 0),
            'dateto' => new external_value(PARAM_INT, 'To timestamp, or 0.', VALUE_DEFAULT, 0),
            'sort' => new external_value(PARAM_ALPHAEXT, 'newest, oldest, name_asc, name_desc.', VALUE_DEFAULT, 'newest'),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * List contact submissions.
     *
     * @param string $search Search.
     * @param string $status Status.
     * @param int $datefrom From timestamp.
     * @param int $dateto To timestamp.
     * @param string $sort Sort.
     * @param int $page Page.
     * @param int $perpage Per page.
     * @return array
     */
    public static function get_contacts(
        string $search = '',
        string $status = '',
        int $datefrom = 0,
        int $dateto = 0,
        string $sort = 'newest',
        int $page = 0,
        int $perpage = 10
    ): array {
        global $DB;

        $params = self::validate_parameters(self::get_contacts_parameters(), [
            'search' => $search,
            'status' => $status,
            'datefrom' => $datefrom,
            'dateto' => $dateto,
            'sort' => $sort,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        self::require_cap('local/moderncommerce:viewcontacts');

        $page = max(0, (int) $params['page']);
        $perpage = max(1, min(self::MAX_PER_PAGE, (int) $params['perpage']));

        $where = ['1 = 1'];
        $sqlparams = [];
        if ($params['search'] !== '') {
            $likes = [];
            foreach (['fullname', 'email', 'subject', 'phone', 'message'] as $col) {
                $likes[] = $DB->sql_like($col, ':s' . $col, false);
                $sqlparams['s' . $col] = '%' . $DB->sql_like_escape($params['search']) . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }
        if ($params['status'] !== '' && in_array($params['status'], self::STATUSES, true)) {
            $where[] = 'status = :status';
            $sqlparams['status'] = $params['status'];
        }
        if ((int) $params['datefrom'] > 0) {
            $where[] = 'timecreated >= :datefrom';
            $sqlparams['datefrom'] = (int) $params['datefrom'];
        }
        if ((int) $params['dateto'] > 0) {
            $where[] = 'timecreated <= :dateto';
            $sqlparams['dateto'] = (int) $params['dateto'];
        }
        $wheresql = implode(' AND ', $where);

        $order = 'timecreated DESC';
        switch ($params['sort']) {
            case 'oldest':
                $order = 'timecreated ASC';
                break;
            case 'name_asc':
                $order = 'fullname ASC';
                break;
            case 'name_desc':
                $order = 'fullname DESC';
                break;
        }

        $total = (int) $DB->count_records_select('local_moderncommerce_contacts', $wheresql, $sqlparams);
        $records = $DB->get_records_select(
            'local_moderncommerce_contacts',
            $wheresql,
            $sqlparams,
            $order,
            '*',
            $page * $perpage,
            $perpage
        );

        return [
            'items' => array_values(array_map([self::class, 'format_contact'], $records)),
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'stats' => self::stats(),
            'statuses' => self::status_options(),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_contacts.
     *
     * @return external_single_structure
     */
    public static function get_contacts_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::contact_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching contacts.'),
            'page' => new external_value(PARAM_INT, 'Current page.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.'),
            'stats' => self::stats_structure(),
            'statuses' => new external_multiple_structure(self::option_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for get_contact.
     *
     * @return external_function_parameters
     */
    public static function get_contact_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Contact ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get one contact submission with its conversation thread.
     *
     * @param int $id Contact ID.
     * @return array
     */
    public static function get_contact(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::get_contact_parameters(), ['id' => $id]);
        self::require_cap('local/moderncommerce:viewcontacts');

        $contact = $DB->get_record('local_moderncommerce_contacts', ['id' => (int) $params['id']], '*', MUST_EXIST);

        return [
            'contact' => self::format_contact($contact),
            'thread' => self::thread($contact),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_contact.
     *
     * @return external_single_structure
     */
    public static function get_contact_returns(): external_single_structure {
        return new external_single_structure([
            'contact' => self::contact_structure(),
            'thread' => new external_multiple_structure(self::thread_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for reply_to_contact.
     *
     * @return external_function_parameters
     */
    public static function reply_to_contact_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Contact ID.', VALUE_REQUIRED),
            'message' => new external_value(PARAM_RAW, 'Reply message.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Reply to a contact (stores the reply and emails the sender).
     *
     * @param int $id Contact ID.
     * @param string $message Reply message.
     * @return array
     */
    public static function reply_to_contact(int $id, string $message): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::reply_to_contact_parameters(), [
            'id' => $id,
            'message' => $message,
        ]);
        self::require_cap('local/moderncommerce:managecontacts');

        $contact = $DB->get_record('local_moderncommerce_contacts', ['id' => (int) $params['id']], '*', MUST_EXIST);
        $replymessage = trim($params['message']);
        if ($replymessage === '') {
            throw new \moodle_exception('erroremptyreply', 'local_moderncommerce');
        }

        $now = time();
        if (empty($contact->replytoken)) {
            $contact->replytoken = bin2hex(random_bytes(32));
            $DB->set_field('local_moderncommerce_contacts', 'replytoken', $contact->replytoken, ['id' => $contact->id]);
        }

        $DB->insert_record('local_moderncommerce_contact_replies', (object) [
            'contactid' => (int) $contact->id,
            'userid' => (int) $USER->id,
            'fromclient' => 0,
            'message' => $replymessage,
            'timecreated' => $now,
        ]);

        if ((string) $contact->status === 'new') {
            $DB->set_field('local_moderncommerce_contacts', 'status', 'inprogress', ['id' => $contact->id]);
            $contact->status = 'inprogress';
        }
        $DB->set_field('local_moderncommerce_contacts', 'timemodified', $now, ['id' => $contact->id]);

        $task = new \local_moderncommerce\task\send_contact_email();
        $task->set_custom_data([
            'contactid' => (int) $contact->id,
            'type' => 'reply',
            'replymessage' => $replymessage,
        ]);
        \core\task\manager::queue_adhoc_task($task);

        $contact = $DB->get_record('local_moderncommerce_contacts', ['id' => (int) $contact->id], '*', MUST_EXIST);

        return [
            'success' => true,
            'message' => get_string('replysent', 'local_moderncommerce'),
            'contact' => self::format_contact($contact),
            'thread' => self::thread($contact),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for reply_to_contact.
     *
     * @return external_single_structure
     */
    public static function reply_to_contact_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the reply was sent.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'contact' => self::contact_structure(),
            'thread' => new external_multiple_structure(self::thread_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for set_contact_status.
     *
     * @return external_function_parameters
     */
    public static function set_contact_status_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Contact ID.', VALUE_REQUIRED),
            'status' => new external_value(PARAM_ALPHA, 'new, inprogress, closed, or archived.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Update a contact status.
     *
     * @param int $id Contact ID.
     * @param string $status New status.
     * @return array
     */
    public static function set_contact_status(int $id, string $status): array {
        global $DB;

        $params = self::validate_parameters(self::set_contact_status_parameters(), [
            'id' => $id,
            'status' => $status,
        ]);
        self::require_cap('local/moderncommerce:managecontacts');

        if (!in_array($params['status'], self::STATUSES, true)) {
            throw new \moodle_exception('invalidrequest', 'error');
        }
        $contact = $DB->get_record('local_moderncommerce_contacts', ['id' => (int) $params['id']], '*', MUST_EXIST);
        $DB->set_field('local_moderncommerce_contacts', 'status', $params['status'], ['id' => $contact->id]);
        $DB->set_field('local_moderncommerce_contacts', 'timemodified', time(), ['id' => $contact->id]);
        $contact->status = $params['status'];

        return [
            'success' => true,
            'message' => get_string('statusupdated', 'local_moderncommerce'),
            'contact' => self::format_contact($contact),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for set_contact_status.
     *
     * @return external_single_structure
     */
    public static function set_contact_status_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the status changed.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'contact' => self::contact_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for get_email_settings.
     *
     * @return external_function_parameters
     */
    public static function get_email_settings_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get contact email configuration.
     *
     * @return array
     */
    public static function get_email_settings(): array {
        self::require_cap('local/moderncommerce:managesettings');

        return [
            'recipientemails' => (string) (get_config('local_moderncommerce', 'contact_recipient_emails') ?: ''),
            'templates' => self::template_options(),
            'placeholders' => array_values(self::PLACEHOLDERS),
            'autoreply' => self::email_block('autoreply'),
            'adminnotify' => self::email_block('adminnotify'),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_email_settings.
     *
     * @return external_single_structure
     */
    public static function get_email_settings_returns(): external_single_structure {
        return new external_single_structure([
            'recipientemails' => new external_value(PARAM_RAW, 'Comma-separated admin recipient emails.'),
            'templates' => new external_multiple_structure(self::option_structure()),
            'placeholders' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Placeholder token.')),
            'autoreply' => self::email_block_structure(),
            'adminnotify' => self::email_block_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for save_email_settings.
     *
     * @return external_function_parameters
     */
    public static function save_email_settings_parameters(): external_function_parameters {
        return new external_function_parameters([
            'recipientemails' => new external_value(PARAM_RAW, 'Comma-separated admin recipient emails.', VALUE_DEFAULT, ''),
            'autoreply' => self::email_block_input(),
            'adminnotify' => self::email_block_input(),
        ]);
    }

    /**
     * Save contact email configuration.
     *
     * @param string $recipientemails Recipient emails.
     * @param array $autoreply Autoreply config.
     * @param array $adminnotify Admin notification config.
     * @return array
     */
    public static function save_email_settings(string $recipientemails, array $autoreply, array $adminnotify): array {
        $params = self::validate_parameters(self::save_email_settings_parameters(), [
            'recipientemails' => $recipientemails,
            'autoreply' => $autoreply,
            'adminnotify' => $adminnotify,
        ]);
        self::require_cap('local/moderncommerce:managesettings');

        set_config('contact_recipient_emails', trim($params['recipientemails']), 'local_moderncommerce');
        self::save_email_block('autoreply', $params['autoreply']);
        self::save_email_block('adminnotify', $params['adminnotify']);

        return self::simple_result(true, get_string('emailsettingssaved', 'local_moderncommerce'));
    }

    /**
     * Return structure for save_email_settings.
     *
     * @return external_single_structure
     */
    public static function save_email_settings_returns(): external_single_structure {
        return self::simple_result_structure();
    }

    /**
     * Read an email block config (autoreply or adminnotify).
     *
     * @param string $prefix Config prefix.
     * @return array
     */
    private static function email_block(string $prefix): array {
        return [
            'enabled' => (bool) get_config('local_moderncommerce', 'contact_' . $prefix . '_enabled'),
            'templateid' => (int) (get_config('local_moderncommerce', 'contact_' . $prefix . '_template') ?: 0),
            'subject' => (string) (get_config('local_moderncommerce', 'contact_' . $prefix . '_subject') ?: ''),
            'body' => (string) (get_config('local_moderncommerce', 'contact_' . $prefix . '_body') ?: ''),
        ];
    }

    /**
     * Persist an email block config.
     *
     * @param string $prefix Config prefix.
     * @param array $block Block data.
     */
    private static function save_email_block(string $prefix, array $block): void {
        set_config('contact_' . $prefix . '_enabled', !empty($block['enabled']) ? 1 : 0, 'local_moderncommerce');
        set_config('contact_' . $prefix . '_template', (int) ($block['templateid'] ?? 0) ?: '', 'local_moderncommerce');
        set_config('contact_' . $prefix . '_subject', trim((string) ($block['subject'] ?? '')), 'local_moderncommerce');
        set_config('contact_' . $prefix . '_body', (string) ($block['body'] ?? ''), 'local_moderncommerce');
    }

    /**
     * Available email-template options from Modern Commerce.
     *
     * @return array
     */
    private static function template_options(): array {
        global $CFG, $DB;

        $options = [];
        if (file_exists($CFG->dirroot . '/local/moderncommerce/lib.php')) {
            require_once($CFG->dirroot . '/local/moderncommerce/lib.php');
            if (function_exists('local_moderncommerce_get_available_templates')) {
                foreach (local_moderncommerce_get_available_templates() as $id => $name) {
                    if ((int) $id > 0) {
                        $options[] = ['id' => (int) $id, 'name' => (string) $name];
                    }
                }
                return $options;
            }
        }
        if ($DB->get_manager()->table_exists(new \xmldb_table('local_moderncommerce_emailtpl'))) {
            $records = $DB->get_records('local_moderncommerce_emailtpl', ['status' => 'active'], 'name ASC', 'id, name');
            foreach ($records as $record) {
                $options[] = ['id' => (int) $record->id, 'name' => (string) $record->name];
            }
        }
        return $options;
    }

    /**
     * Build a contact's conversation thread.
     *
     * @param \stdClass $contact Contact record.
     * @return array
     */
    private static function thread(\stdClass $contact): array {
        global $DB;

        $thread = [[
            'fromclient' => true,
            'sendername' => format_string($contact->fullname),
            'message' => (string) $contact->message,
            'displaydate' => userdate((int) $contact->timecreated),
            'isoriginal' => true,
        ]];

        $replies = $DB->get_records('local_moderncommerce_contact_replies', ['contactid' => (int) $contact->id], 'timecreated ASC');
        foreach ($replies as $reply) {
            $sendername = get_string('you', 'local_moderncommerce');
            if (!empty($reply->fromclient)) {
                $sendername = format_string($contact->fullname);
            } else if (!empty($reply->userid)) {
                $user = $DB->get_record('user', ['id' => (int) $reply->userid]);
                if ($user) {
                    $sendername = fullname($user);
                }
            }
            $thread[] = [
                'fromclient' => (bool) $reply->fromclient,
                'sendername' => $sendername,
                'message' => (string) $reply->message,
                'displaydate' => userdate((int) $reply->timecreated),
                'isoriginal' => false,
            ];
        }

        return $thread;
    }

    /**
     * KPI stats.
     *
     * @return array
     */
    private static function stats(): array {
        global $DB;

        return [
            'total' => (int) $DB->count_records('local_moderncommerce_contacts'),
            'unread' => (int) $DB->count_records('local_moderncommerce_contacts', ['status' => 'new']),
            'replied' => (int) $DB->count_records('local_moderncommerce_contacts', ['status' => 'closed']),
            'thisweek' => (int) $DB->count_records_select(
                'local_moderncommerce_contacts',
                'timecreated >= :weekago',
                ['weekago' => time() - (7 * DAYSECS)]
            ),
        ];
    }

    /**
     * Format a contact for return values.
     *
     * @param \stdClass $contact Contact record.
     * @return array
     */
    private static function format_contact(\stdClass $contact): array {
        return [
            'id' => (int) $contact->id,
            'fullname' => format_string($contact->fullname),
            'email' => (string) $contact->email,
            'subject' => (string) ($contact->subject ?? ''),
            'phone' => (string) ($contact->phone ?? ''),
            'message' => (string) $contact->message,
            'status' => (string) $contact->status,
            'statuslabel' => self::status_label((string) $contact->status),
            'statusclass' => self::status_class((string) $contact->status),
            'source' => (string) ($contact->source ?? ''),
            'timecreated' => (int) $contact->timecreated,
            'displaydate' => userdate((int) $contact->timecreated),
            'isunread' => ((string) $contact->status === 'new'),
        ];
    }

    /**
     * Status options for filters.
     *
     * @return array
     */
    private static function status_options(): array {
        $options = [];
        foreach (self::STATUSES as $status) {
            $options[] = ['id' => 0, 'name' => self::status_label($status), 'value' => $status];
        }
        return $options;
    }

    /**
     * Human label for a status.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_label(string $status): string {
        return localisation::status_label($status);
    }

    /**
     * Badge class for a status.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_class(string $status): string {
        switch ($status) {
            case 'new':
                return 'warning';
            case 'inprogress':
                return 'info';
            case 'closed':
                return 'success';
            default:
                return 'neutral';
        }
    }

    /**
     * Require login and a system capability.
     *
     * @param string $capability Capability.
     * @return context_system
     */
    private static function require_cap(string $capability): context_system {
        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability($capability, $context);
        return $context;
    }

    /**
     * Generic success result.
     *
     * @param bool $success Success.
     * @param string $message Message.
     * @return array
     */
    private static function simple_result(bool $success, string $message): array {
        return ['success' => $success, 'message' => $message, 'warnings' => []];
    }

    /**
     * Generic success result structure.
     *
     * @return external_single_structure
     */
    private static function simple_result_structure(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success flag.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Contact structure.
     *
     * @return external_single_structure
     */
    private static function contact_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Contact ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Full name.'),
            'email' => new external_value(PARAM_TEXT, 'Email.'),
            'subject' => new external_value(PARAM_TEXT, 'Subject.'),
            'phone' => new external_value(PARAM_TEXT, 'Phone.'),
            'message' => new external_value(PARAM_RAW, 'Message.'),
            'status' => new external_value(PARAM_ALPHA, 'Status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Status badge class.'),
            'source' => new external_value(PARAM_RAW, 'Submission source.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'displaydate' => new external_value(PARAM_TEXT, 'Formatted date.'),
            'isunread' => new external_value(PARAM_BOOL, 'Whether unread (new).'),
        ]);
    }

    /**
     * Thread item structure.
     *
     * @return external_single_structure
     */
    private static function thread_structure(): external_single_structure {
        return new external_single_structure([
            'fromclient' => new external_value(PARAM_BOOL, 'Whether from the client.'),
            'sendername' => new external_value(PARAM_TEXT, 'Sender name.'),
            'message' => new external_value(PARAM_RAW, 'Message.'),
            'displaydate' => new external_value(PARAM_TEXT, 'Formatted date.'),
            'isoriginal' => new external_value(PARAM_BOOL, 'Whether the original submission.'),
        ]);
    }

    /**
     * Option structure (id/name/value).
     *
     * @return external_single_structure
     */
    private static function option_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Option ID.'),
            'name' => new external_value(PARAM_TEXT, 'Option label.'),
            'value' => new external_value(PARAM_RAW, 'Option value.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Email block input structure.
     *
     * @return external_single_structure
     */
    private static function email_block_input(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Enabled.'),
            'templateid' => new external_value(PARAM_INT, 'Template ID, or 0.', VALUE_DEFAULT, 0),
            'subject' => new external_value(PARAM_TEXT, 'Subject override.', VALUE_DEFAULT, ''),
            'body' => new external_value(PARAM_RAW, 'Body override.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Email block output structure.
     *
     * @return external_single_structure
     */
    private static function email_block_structure(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Enabled.'),
            'templateid' => new external_value(PARAM_INT, 'Template ID, or 0.'),
            'subject' => new external_value(PARAM_TEXT, 'Subject override.'),
            'body' => new external_value(PARAM_RAW, 'Body override.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total contacts.'),
            'unread' => new external_value(PARAM_INT, 'Unread (new).'),
            'replied' => new external_value(PARAM_INT, 'Replied (closed).'),
            'thisweek' => new external_value(PARAM_INT, 'This week.'),
        ]);
    }
}
