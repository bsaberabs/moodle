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
 * External API returning the Modern Notify delivery dashboard dataset.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\notifications;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Returns Modern Notify status, channel settings, and recent delivery logs.
 */
class get_notifications extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search term.', VALUE_DEFAULT, ''),
            'channel' => new external_value(PARAM_ALPHANUMEXT, 'Delivery channel filter.', VALUE_DEFAULT, ''),
            'result' => new external_value(PARAM_ALPHANUMEXT, 'Delivery result filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $search Search term.
     * @param string $channel Delivery channel filter.
     * @param string $result Delivery result filter.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @return array
     */
    public static function execute(
        string $search = '',
        string $channel = '',
        string $result = '',
        int $page = 0,
        int $perpage = 10
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'channel' => $channel,
            'result' => $result,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managenotifications', $context);

        [$page, $perpage] = self::paginate((int) $params['page'], (int) $params['perpage']);

        if (!self::notify_tables_exist()) {
            return [
                'installed' => false,
                'stats' => self::empty_stats(),
                'settings' => self::settings(),
                'logs' => [],
                'logtotal' => 0,
                'page' => $page,
                'perpage' => $perpage,
                'channels' => [],
                'results' => [],
                'warnings' => [],
            ];
        }

        $counts = [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'suppressed' => 0,
            'cancelled' => 0,
        ];

        $statusrecords = $DB->get_records_sql(
            "SELECT status, COUNT(1) AS total
               FROM {local_moderncommerce_notify_queue}
           GROUP BY status"
        );
        foreach ($statusrecords as $record) {
            $status = (string) $record->status;
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $record->total;
            }
        }

        $logs = [];
        [$wheresql, $sqlparams] = self::log_filter_sql(
            (string) $params['search'],
            (string) $params['channel'],
            (string) $params['result']
        );
        $logtotal = (int) $DB->count_records_select('local_moderncommerce_notify_log', $wheresql, $sqlparams);
        $logrecords = $DB->get_records_select(
            'local_moderncommerce_notify_log',
            $wheresql,
            $sqlparams,
            'timecreated DESC, id DESC',
            '*',
            $page * $perpage,
            $perpage
        );
        foreach ($logrecords as $record) {
            $logs[] = self::format_log($record);
        }

        return [
            'installed' => true,
            'stats' => [
                'pending' => $counts['pending'] + $counts['processing'],
                'processing' => $counts['processing'],
                'sent' => $counts['sent'],
                'failed' => $counts['failed'],
                'suppressed' => $counts['suppressed'],
                'cancelled' => $counts['cancelled'],
                'logtotal' => (int) $DB->count_records('local_moderncommerce_notify_log'),
            ],
            'settings' => self::settings(),
            'logs' => $logs,
            'logtotal' => $logtotal,
            'page' => $page,
            'perpage' => $perpage,
            'channels' => self::option_values('channel'),
            'results' => self::option_values('result'),
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
            'installed' => new external_value(PARAM_BOOL, 'Whether the notification subsystem tables are ready.'),
            'stats' => self::stats_structure(),
            'settings' => self::settings_structure(),
            'logs' => new external_multiple_structure(self::log_structure()),
            'logtotal' => new external_value(PARAM_INT, 'Filtered delivery log row count.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Current page size.'),
            'channels' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Delivery channel option.')),
            'results' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Delivery result option.')),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Check the notification tables before querying them.
     *
     * @return bool
     */
    private static function notify_tables_exist(): bool {
        global $DB;

        $manager = $DB->get_manager();

        return $manager->table_exists('local_moderncommerce_notify_queue')
            && $manager->table_exists('local_moderncommerce_notify_log');
    }

    /**
     * Current channel settings.
     *
     * @return array
     */
    private static function settings(): array {
        return [
            'batchsize' => max(1, (int) (get_config('local_moderncommerce', 'notify_batchsize') ?: 100)),
            'slack_enabled' => (bool) get_config('local_moderncommerce', 'notify_slack_enabled'),
            'slack_url' => (string) get_config('local_moderncommerce', 'notify_slack_url'),
            'slack_secret_set' => (bool) get_config('local_moderncommerce', 'notify_slack_secret'),
            'teams_enabled' => (bool) get_config('local_moderncommerce', 'notify_teams_enabled'),
            'teams_url' => (string) get_config('local_moderncommerce', 'notify_teams_url'),
            'teams_secret_set' => (bool) get_config('local_moderncommerce', 'notify_teams_secret'),
        ];
    }

    /**
     * Empty stats for unavailable add-on state.
     *
     * @return array
     */
    private static function empty_stats(): array {
        return [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'suppressed' => 0,
            'cancelled' => 0,
            'logtotal' => 0,
        ];
    }

    /**
     * Normalise pagination input.
     *
     * @param int $page Requested page.
     * @param int $perpage Requested page size.
     * @return array{0:int,1:int}
     */
    private static function paginate(int $page, int $perpage): array {
        return [
            max(0, $page),
            min(100, max(1, $perpage)),
        ];
    }

    /**
     * Build filtered log SQL.
     *
     * @param string $search Search term.
     * @param string $channel Delivery channel.
     * @param string $result Delivery result.
     * @return array{0:string,1:array}
     */
    private static function log_filter_sql(string $search, string $channel, string $result): array {
        global $DB;

        $where = ['1 = 1'];
        $sqlparams = [];

        $search = trim($search);
        if ($search !== '') {
            $term = '%' . $DB->sql_like_escape($search) . '%';
            $where[] = '('
                . $DB->sql_like('component', ':component', false) . ' OR '
                . $DB->sql_like('eventkey', ':eventkey', false) . ' OR '
                . $DB->sql_like('channel', ':searchchannel', false) . ' OR '
                . $DB->sql_like('recipientemail', ':recipientemail', false) . ' OR '
                . $DB->sql_like('result', ':searchresult', false) . ' OR '
                . $DB->sql_like('error', ':error', false)
                . ')';
            $sqlparams['component'] = $term;
            $sqlparams['eventkey'] = $term;
            $sqlparams['searchchannel'] = $term;
            $sqlparams['recipientemail'] = $term;
            $sqlparams['searchresult'] = $term;
            $sqlparams['error'] = $term;
        }

        if ($channel !== '') {
            $where[] = 'channel = :channel';
            $sqlparams['channel'] = $channel;
        }

        if ($result !== '') {
            $where[] = 'result = :result';
            $sqlparams['result'] = $result;
        }

        return [implode(' AND ', $where), $sqlparams];
    }

    /**
     * Distinct log field values used by the table filters.
     *
     * @param string $field Field key.
     * @return string[]
     */
    private static function option_values(string $field): array {
        global $DB;

        $allowed = [
            'channel' => 'channel',
            'result' => 'result',
        ];
        if (!isset($allowed[$field])) {
            return [];
        }

        $column = $allowed[$field];
        $values = $DB->get_fieldset_sql(
            "SELECT DISTINCT {$column}
               FROM {local_moderncommerce_notify_log}
              WHERE {$column} IS NOT NULL AND {$column} <> ''
           ORDER BY {$column} ASC"
        );

        return array_values(array_map('strval', $values));
    }

    /**
     * Format one delivery log row for React.
     *
     * @param object $record Database record.
     * @return array
     */
    private static function format_log(object $record): array {
        $recipient = trim((string) ($record->recipientemail ?? ''));
        if ($recipient === '') {
            $recipient = ((int) ($record->recipientuserid ?? 0)) > 0 ? '#' . (int) $record->recipientuserid : '';
        }

        $event = trim((string) ($record->component ?? '') . ' / ' . (string) ($record->eventkey ?? ''));

        return [
            'id' => (int) $record->id,
            'timecreated' => (int) $record->timecreated,
            'displaytime' => userdate((int) $record->timecreated, '%d %b %H:%M'),
            'event' => $event,
            'component' => (string) ($record->component ?? ''),
            'eventkey' => (string) ($record->eventkey ?? ''),
            'channel' => (string) ($record->channel ?? ''),
            'recipient' => $recipient,
            'result' => (string) ($record->result ?? ''),
            'resultclass' => self::result_class((string) ($record->result ?? '')),
            'error' => trim((string) ($record->error ?? '')),
        ];
    }

    /**
     * Badge variant for a log result.
     *
     * @param string $result Result key.
     * @return string
     */
    private static function result_class(string $result): string {
        if ($result === 'sent') {
            return 'success';
        }
        if ($result === 'failed') {
            return 'danger';
        }
        if ($result === 'suppressed' || $result === 'skipped') {
            return 'warning';
        }

        return 'neutral';
    }

    /**
     * Stats return structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'pending' => new external_value(PARAM_INT, 'Pending and processing queue count.'),
            'processing' => new external_value(PARAM_INT, 'Processing queue count.'),
            'sent' => new external_value(PARAM_INT, 'Sent queue count.'),
            'failed' => new external_value(PARAM_INT, 'Failed queue count.'),
            'suppressed' => new external_value(PARAM_INT, 'Suppressed queue count.'),
            'cancelled' => new external_value(PARAM_INT, 'Cancelled queue count.'),
            'logtotal' => new external_value(PARAM_INT, 'Total delivery log rows.'),
        ]);
    }

    /**
     * Settings return structure.
     *
     * @return external_single_structure
     */
    public static function settings_structure(): external_single_structure {
        return new external_single_structure([
            'batchsize' => new external_value(PARAM_INT, 'Send batch size.'),
            'slack_enabled' => new external_value(PARAM_BOOL, 'Whether Slack delivery is enabled.'),
            'slack_url' => new external_value(PARAM_TEXT, 'Slack webhook URL.'),
            'slack_secret_set' => new external_value(PARAM_BOOL, 'Whether a Slack signing secret is configured.'),
            'teams_enabled' => new external_value(PARAM_BOOL, 'Whether Teams delivery is enabled.'),
            'teams_url' => new external_value(PARAM_TEXT, 'Teams webhook URL.'),
            'teams_secret_set' => new external_value(PARAM_BOOL, 'Whether a Teams signing secret is configured.'),
        ]);
    }

    /**
     * Log row return structure.
     *
     * @return external_single_structure
     */
    private static function log_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Log id.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'displaytime' => new external_value(PARAM_TEXT, 'Formatted created time.'),
            'event' => new external_value(PARAM_TEXT, 'Event label.'),
            'component' => new external_value(PARAM_TEXT, 'Source component.'),
            'eventkey' => new external_value(PARAM_TEXT, 'Event key.'),
            'channel' => new external_value(PARAM_TEXT, 'Delivery channel.'),
            'recipient' => new external_value(PARAM_TEXT, 'Recipient display value.'),
            'result' => new external_value(PARAM_TEXT, 'Delivery result.'),
            'resultclass' => new external_value(PARAM_ALPHA, 'Badge variant.'),
            'error' => new external_value(PARAM_TEXT, 'Error summary.'),
        ]);
    }
}
