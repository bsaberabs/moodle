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
 * External API listing recent webhook events.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\payments;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\localisation;

/**
 * List recent webhook events for the admin webhooks screen.
 */
class list_webhook_events extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'gateway' => new external_value(PARAM_ALPHANUMEXT, 'Gateway filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $gateway Gateway filter.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @return array
     */
    public static function execute(string $gateway = '', int $page = 0, int $perpage = 10): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'gateway' => $gateway,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:configuregateways', $context);

        $page = max(0, $params['page']);
        $perpage = min(100, max(5, $params['perpage']));

        if (!$DB->get_manager()->table_exists('local_moderncommerce_webhook_events')) {
            return self::empty_response($page, $perpage);
        }

        $conditions = [];
        $sqlparams = [];
        if ($params['gateway'] !== '') {
            $conditions['gateway'] = $params['gateway'];
            $sqlparams['gateway'] = $params['gateway'];
        }

        $total = (int) $DB->count_records('local_moderncommerce_webhook_events', $conditions);
        $records = $DB->get_records(
            'local_moderncommerce_webhook_events',
            $conditions,
            'timecreated DESC, id DESC',
            '*',
            $page * $perpage,
            $perpage
        );

        $events = [];
        foreach ($records as $record) {
            $status = (string) ($record->status ?? 'unknown');
            $events[] = [
                'id' => (int) $record->id,
                'gateway' => ucfirst((string) $record->gateway),
                'eventtype' => (string) ($record->eventtype ?? ''),
                'reference' => (string) ($record->reference ?? ''),
                'status' => localisation::status_label($status),
                'statusclass' => self::status_class($status),
                'signatureverified' => !empty($record->signatureverified),
                'attemptcount' => (int) ($record->attemptcount ?? 0),
                'lasterror' => (string) ($record->lasterror ?? ''),
                'date' => userdate((int) $record->timecreated, get_string('strftimedatetime', 'langconfig')),
            ];
        }

        return [
            'events' => $events,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
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
            'events' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Event ID.'),
                'gateway' => new external_value(PARAM_TEXT, 'Gateway label.'),
                'eventtype' => new external_value(PARAM_TEXT, 'Event type.'),
                'reference' => new external_value(PARAM_TEXT, 'Reference.'),
                'status' => new external_value(PARAM_TEXT, 'Status label.'),
                'statusclass' => new external_value(PARAM_ALPHA, 'Status badge class.'),
                'signatureverified' => new external_value(PARAM_BOOL, 'Whether the signature verified.'),
                'attemptcount' => new external_value(PARAM_INT, 'Processing attempt count.'),
                'lasterror' => new external_value(PARAM_TEXT, 'Last processing error.'),
                'date' => new external_value(PARAM_TEXT, 'Formatted date.'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total matching events.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Empty response.
     *
     * @param int $page Page.
     * @param int $perpage Page size.
     * @return array
     */
    private static function empty_response(int $page, int $perpage): array {
        return [
            'events' => [],
            'total' => 0,
            'page' => $page,
            'perpage' => $perpage,
            'warnings' => [],
        ];
    }

    /**
     * Badge class for an event status.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_class(string $status): string {
        $status = strtolower($status);
        if (in_array($status, ['success', 'processed', 'completed', 'verified'], true)) {
            return 'success';
        }
        if (in_array($status, ['failed', 'error', 'invalid', 'declined'], true)) {
            return 'danger';
        }
        if (in_array($status, ['pending', 'received', 'processing', 'retrying'], true)) {
            return 'warning';
        }

        return 'neutral';
    }
}
