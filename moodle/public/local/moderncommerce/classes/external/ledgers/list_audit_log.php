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
 * External API listing audit log entries for the admin ledger.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\ledgers;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use local_moderncommerce\localisation;

/**
 * List Modern Commerce audit log entries.
 */
class list_audit_log extends external_api {
    /** @var string Source table. */
    private const TABLE = 'local_moderncommerce_audit_log';

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return ledger_support::params();
    }

    /**
     * Execute.
     *
     * @param string $search Search term.
     * @param string $gateway Unused gateway filter.
     * @param string $status Result filter.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @return array
     */
    public static function execute(
        string $search = '',
        string $gateway = '',
        string $status = '',
        int $page = 0,
        int $perpage = 10
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'gateway' => $gateway,
            'status' => $status,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewauditlog', $context);

        [$page, $perpage] = ledger_support::paginate($params['page'], $params['perpage']);

        $columns = [
            ledger_support::column(get_string('action', 'local_moderncommerce')),
            ledger_support::column(get_string('entity', 'local_moderncommerce')),
            ledger_support::column(get_string('actor', 'local_moderncommerce')),
            ledger_support::column(get_string('result', 'local_moderncommerce')),
            ledger_support::column(get_string('ipaddress', 'local_moderncommerce')),
            ledger_support::column(get_string('when', 'local_moderncommerce')),
        ];

        if (!$DB->get_manager()->table_exists(self::TABLE)) {
            return self::empty($columns, $page, $perpage);
        }

        $where = ['1 = 1'];
        $sqlparams = [];
        if (trim($params['search']) !== '') {
            $where[] = '(' . $DB->sql_like('action', ':s1', false) . ' OR '
                . $DB->sql_like('entitytype', ':s2', false) . ' OR '
                . $DB->sql_like('correlationid', ':s3', false) . ')';
            $term = '%' . $DB->sql_like_escape(trim($params['search'])) . '%';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
            $sqlparams['s3'] = $term;
        }
        if ($params['status'] !== '') {
            $where[] = 'result = :result';
            $sqlparams['result'] = $params['status'];
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_select(self::TABLE, $wheresql, $sqlparams);
        $records = $DB->get_records_select(
            self::TABLE,
            $wheresql,
            $sqlparams,
            'timecreated DESC, id DESC',
            '*',
            $page * $perpage,
            $perpage
        );

        $users = self::get_actors($records);
        $subjects = self::get_subjects($records);

        $items = [];
        foreach ($records as $r) {
            $result = (string) ($r->result ?? '');
            $actorid = (int) ($r->actoruserid ?? 0);
            $subjectid = (int) ($r->subjectuserid ?? 0);
            $actor = $actorid > 0 && isset($users[$actorid])
                ? fullname($users[$actorid])
                : get_string('system', 'local_moderncommerce');
            $subject = $subjectid > 0 && isset($subjects[$subjectid])
                ? fullname($subjects[$subjectid])
                : '-';
            $entity = trim((string) $r->entitytype . ' #' . (string) $r->entityid);
            $items[] = [
                'id' => (int) $r->id,
                'cells' => [
                    ledger_support::cell($r->action),
                    ledger_support::cell($entity),
                    ledger_support::cell($actor),
                    ledger_support::cell(localisation::status_label($result), ledger_support::status_class($result)),
                    ledger_support::cell($r->ipaddress ?: '-'),
                    ledger_support::cell(ledger_support::date((int) $r->timecreated)),
                ],
                'details' => [
                    self::detail(get_string('subject', 'local_moderncommerce'), $subject),
                    self::detail(get_string('source', 'local_moderncommerce'), $r->source ?? '-'),
                    self::detail(
                        get_string('severity', 'local_moderncommerce'),
                        $r->severity ?? '-',
                        ledger_support::status_class($r->severity ?? '')
                    ),
                    self::detail(get_string('correlationid', 'local_moderncommerce'), $r->correlationid ?: '-'),
                    self::detail(get_string('useragent', 'local_moderncommerce'), $r->useragent ?: '-'),
                    self::detail(
                        get_string('redacted', 'local_moderncommerce'),
                        empty($r->redacted) ? get_string('no') : get_string('yes')
                    ),
                    self::detail(get_string('eventhash', 'local_moderncommerce'), $r->eventhash ?: '-'),
                    self::detail(get_string('previoushash', 'local_moderncommerce'), $r->previoushash ?: '-'),
                    self::detail(get_string('olddata', 'local_moderncommerce'), self::format_json($r->olddata ?? null)),
                    self::detail(get_string('newdata', 'local_moderncommerce'), self::format_json($r->newdata ?? null)),
                ],
            ];
        }

        return [
            'columns' => $columns,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'gateways' => [],
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return ledger_support::returns();
    }

    /**
     * Fetch actor user records.
     *
     * @param array $records Audit records.
     * @return array Users keyed by ID.
     */
    private static function get_actors(array $records): array {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map(static function ($r): int {
            return (int) ($r->actoruserid ?? 0);
        }, $records))));

        return empty($ids) ? [] : $DB->get_records_list('user', 'id', $ids);
    }

    /**
     * Fetch subject user records.
     *
     * @param array $records Audit records.
     * @return array Users keyed by ID.
     */
    private static function get_subjects(array $records): array {
        global $DB;

        $ids = array_values(array_unique(array_filter(array_map(static function ($r): int {
            return (int) ($r->subjectuserid ?? 0);
        }, $records))));

        return empty($ids) ? [] : $DB->get_records_list('user', 'id', $ids);
    }

    /**
     * Build a detail cell.
     *
     * @param string $label Detail label.
     * @param mixed $value Detail value.
     * @param string $badge Badge class.
     * @return array
     */
    private static function detail(string $label, $value, string $badge = ''): array {
        return [
            'label' => $label,
            'value' => (string) $value,
            'badge' => $badge,
        ];
    }

    /**
     * Pretty-format JSON payloads for the detail drawer.
     *
     * @param mixed $json Raw JSON.
     * @return string
     */
    private static function format_json($json): string {
        if ($json === null || $json === '') {
            return '-';
        }

        $decoded = json_decode((string) $json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return (string) $json;
        }

        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $pretty === false ? (string) $json : $pretty;
    }

    /**
     * Empty response.
     *
     * @param array $columns Columns.
     * @param int $page Page.
     * @param int $perpage Per page.
     * @return array
     */
    private static function empty(array $columns, int $page, int $perpage): array {
        return [
            'columns' => $columns,
            'items' => [],
            'total' => 0,
            'page' => $page,
            'perpage' => $perpage,
            'gateways' => [],
            'warnings' => [],
        ];
    }
}
