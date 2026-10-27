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
 * Admin webservices for Modern Commerce newsletter subscribers.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\newsletter;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Newsletter subscriber admin webservice methods.
 */
class admin extends external_api {
    /** @var int Maximum rows per page. */
    private const MAX_PER_PAGE = 100;

    /** @var int Maximum rows in one CSV export. */
    private const MAX_EXPORT_ROWS = 10000;

    /**
     * Parameters for list_subscribers.
     *
     * @return external_function_parameters
     */
    public static function list_subscribers_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search email or source.', VALUE_DEFAULT, ''),
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Source filter, or empty for all.', VALUE_DEFAULT, ''),
            'sort' => new external_value(
                PARAM_ALPHAEXT,
                'newest, oldest, email_asc, email_desc, source_asc.',
                VALUE_DEFAULT,
                'newest'
            ),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * List newsletter subscribers.
     *
     * @param string $search Search term.
     * @param string $source Source filter.
     * @param string $sort Sort key.
     * @param int $page Page.
     * @param int $perpage Rows per page.
     * @return array
     */
    public static function list_subscribers(
        string $search = '',
        string $source = '',
        string $sort = 'newest',
        int $page = 0,
        int $perpage = 10
    ): array {
        global $DB;

        $params = self::validate_parameters(self::list_subscribers_parameters(), [
            'search' => $search,
            'source' => $source,
            'sort' => $sort,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        self::require_cap('local/moderncommerce:viewnewsletter');

        [$wheresql, $sqlparams] = self::subscriber_filter_sql($params);
        $page = max(0, (int) $params['page']);
        $perpage = max(1, min(self::MAX_PER_PAGE, (int) $params['perpage']));
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {local_moderncommerce_subscriber} s
              WHERE {$wheresql}",
            $sqlparams
        );

        $records = $DB->get_records_sql(
            "SELECT s.id, s.email, s.source, s.userid, s.timecreated,
                    u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
               FROM {local_moderncommerce_subscriber} s
          LEFT JOIN {user} u ON u.id = s.userid
              WHERE {$wheresql}
           ORDER BY " . self::sort_sql((string) $params['sort']),
            $sqlparams,
            $page * $perpage,
            $perpage
        );

        return [
            'items' => array_values(array_map([self::class, 'format_subscriber'], $records)),
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'stats' => self::stats(),
            'sources' => self::sources(),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for list_subscribers.
     *
     * @return external_single_structure
     */
    public static function list_subscribers_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::subscriber_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching subscribers.'),
            'page' => new external_value(PARAM_INT, 'Current page.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.'),
            'stats' => self::stats_structure(),
            'sources' => new external_multiple_structure(self::option_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for delete_subscriber.
     *
     * @return external_function_parameters
     */
    public static function delete_subscriber_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Subscriber ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Delete a newsletter subscriber.
     *
     * @param int $id Subscriber ID.
     * @return array
     */
    public static function delete_subscriber(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::delete_subscriber_parameters(), ['id' => $id]);
        self::require_cap('local/moderncommerce:managenewsletter');

        $DB->get_record('local_moderncommerce_subscriber', ['id' => (int) $params['id']], '*', MUST_EXIST);
        $DB->delete_records('local_moderncommerce_subscriber', ['id' => (int) $params['id']]);

        return [
            'success' => true,
            'message' => get_string('newslettersubscriberdeleted', 'local_moderncommerce'),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for delete_subscriber.
     *
     * @return external_single_structure
     */
    public static function delete_subscriber_returns(): external_single_structure {
        return self::simple_result_structure();
    }

    /**
     * Parameters for export_subscribers.
     *
     * @return external_function_parameters
     */
    public static function export_subscribers_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search email or source.', VALUE_DEFAULT, ''),
            'source' => new external_value(PARAM_ALPHANUMEXT, 'Source filter, or empty for all.', VALUE_DEFAULT, ''),
            'sort' => new external_value(
                PARAM_ALPHAEXT,
                'newest, oldest, email_asc, email_desc, source_asc.',
                VALUE_DEFAULT,
                'newest'
            ),
        ]);
    }

    /**
     * Export matching newsletter subscribers as CSV content.
     *
     * @param string $search Search term.
     * @param string $source Source filter.
     * @param string $sort Sort key.
     * @return array
     */
    public static function export_subscribers(string $search = '', string $source = '', string $sort = 'newest'): array {
        global $DB;

        $params = self::validate_parameters(self::export_subscribers_parameters(), [
            'search' => $search,
            'source' => $source,
            'sort' => $sort,
        ]);
        self::require_cap('local/moderncommerce:viewnewsletter');

        [$wheresql, $sqlparams] = self::subscriber_filter_sql($params);
        $records = $DB->get_records_sql(
            "SELECT s.id, s.email, s.source, s.userid, s.timecreated,
                    u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
               FROM {local_moderncommerce_subscriber} s
          LEFT JOIN {user} u ON u.id = s.userid
              WHERE {$wheresql}
           ORDER BY " . self::sort_sql((string) $params['sort']),
            $sqlparams,
            0,
            self::MAX_EXPORT_ROWS
        );

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Email', 'Source', 'Moodle user', 'Subscribed at']);
        foreach ($records as $record) {
            $subscriber = self::format_subscriber($record);
            fputcsv($handle, [
                $subscriber['email'],
                $subscriber['source'],
                $subscriber['userlabel'],
                $subscriber['displaydate'],
            ]);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return [
            'filename' => 'moderncommerce-newsletter-subscribers-' . date('Ymd-His') . '.csv',
            'mimetype' => 'text/csv',
            'content' => (string) $content,
            'warnings' => [],
        ];
    }

    /**
     * Return structure for export_subscribers.
     *
     * @return external_single_structure
     */
    public static function export_subscribers_returns(): external_single_structure {
        return new external_single_structure([
            'filename' => new external_value(PARAM_TEXT, 'CSV filename.'),
            'mimetype' => new external_value(PARAM_TEXT, 'MIME type.'),
            'content' => new external_value(PARAM_RAW, 'CSV content.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Build subscriber filter SQL.
     *
     * @param array $params Validated parameters.
     * @return array{0: string, 1: array}
     */
    private static function subscriber_filter_sql(array $params): array {
        global $DB;

        $where = ['1 = 1'];
        $sqlparams = [];

        if ((string) $params['search'] !== '') {
            $needle = '%' . $DB->sql_like_escape((string) $params['search']) . '%';
            $where[] = '(' . $DB->sql_like('s.email', ':searchemail', false)
                . ' OR ' . $DB->sql_like('s.source', ':searchsource', false) . ')';
            $sqlparams['searchemail'] = $needle;
            $sqlparams['searchsource'] = $needle;
        }

        if ((string) $params['source'] !== '') {
            $where[] = 's.source = :source';
            $sqlparams['source'] = (string) $params['source'];
        }

        return [implode(' AND ', $where), $sqlparams];
    }

    /**
     * Resolve a safe ORDER BY fragment.
     *
     * @param string $sort Sort key.
     * @return string
     */
    private static function sort_sql(string $sort): string {
        switch ($sort) {
            case 'oldest':
                return 's.timecreated ASC, s.id ASC';
            case 'email_asc':
                return 's.email ASC, s.id ASC';
            case 'email_desc':
                return 's.email DESC, s.id DESC';
            case 'source_asc':
                return 's.source ASC, s.email ASC';
            default:
                return 's.timecreated DESC, s.id DESC';
        }
    }

    /**
     * KPI stats.
     *
     * @return array
     */
    private static function stats(): array {
        global $DB;

        return [
            'total' => (int) $DB->count_records('local_moderncommerce_subscriber'),
            'thisweek' => (int) $DB->count_records_select(
                'local_moderncommerce_subscriber',
                'timecreated >= :weekago',
                ['weekago' => time() - (7 * DAYSECS)]
            ),
            'knownusers' => (int) $DB->count_records_select('local_moderncommerce_subscriber', 'userid > 0'),
            'guests' => (int) $DB->count_records('local_moderncommerce_subscriber', ['userid' => 0]),
        ];
    }

    /**
     * Source filter options.
     *
     * @return array
     */
    private static function sources(): array {
        global $DB;

        $sources = $DB->get_fieldset_sql(
            "SELECT DISTINCT source
               FROM {local_moderncommerce_subscriber}
              WHERE source IS NOT NULL AND source <> ''
           ORDER BY source ASC"
        );
        $out = [];
        foreach ($sources as $source) {
            $out[] = ['value' => (string) $source, 'label' => (string) $source];
        }
        return $out;
    }

    /**
     * Format a subscriber row.
     *
     * @param \stdClass $record Subscriber row with optional user fields.
     * @return array
     */
    private static function format_subscriber(\stdClass $record): array {
        $userlabel = '';
        if ((int) $record->userid > 0 && trim((string) ($record->firstname ?? '') . (string) ($record->lastname ?? '')) !== '') {
            $userlabel = fullname((object) [
                'firstname' => (string) ($record->firstname ?? ''),
                'lastname' => (string) ($record->lastname ?? ''),
                'firstnamephonetic' => (string) ($record->firstnamephonetic ?? ''),
                'lastnamephonetic' => (string) ($record->lastnamephonetic ?? ''),
                'middlename' => (string) ($record->middlename ?? ''),
                'alternatename' => (string) ($record->alternatename ?? ''),
            ]);
        }

        return [
            'id' => (int) $record->id,
            'email' => (string) $record->email,
            'source' => (string) ($record->source ?? ''),
            'userid' => (int) $record->userid,
            'userlabel' => $userlabel,
            'timecreated' => (int) $record->timecreated,
            'displaydate' => userdate((int) $record->timecreated),
        ];
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
     * Subscriber structure.
     *
     * @return external_single_structure
     */
    private static function subscriber_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Subscriber ID.'),
            'email' => new external_value(PARAM_EMAIL, 'Email address.'),
            'source' => new external_value(PARAM_TEXT, 'Source.'),
            'userid' => new external_value(PARAM_INT, 'Moodle user ID, or 0.'),
            'userlabel' => new external_value(PARAM_TEXT, 'Moodle user display name.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'displaydate' => new external_value(PARAM_TEXT, 'Formatted date.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total subscribers.'),
            'thisweek' => new external_value(PARAM_INT, 'Subscribers this week.'),
            'knownusers' => new external_value(PARAM_INT, 'Subscribers linked to a Moodle user.'),
            'guests' => new external_value(PARAM_INT, 'Guest subscribers.'),
        ]);
    }

    /**
     * Option structure.
     *
     * @return external_single_structure
     */
    private static function option_structure(): external_single_structure {
        return new external_single_structure([
            'value' => new external_value(PARAM_RAW, 'Option value.'),
            'label' => new external_value(PARAM_TEXT, 'Option label.'),
        ]);
    }
}
