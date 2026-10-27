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
 * External API listing enrollment keys for the admin keys screen.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\enrollkeys;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * List enrollment keys with target, status, and usage data.
 */
class list_keys extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search key code.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHA, 'Status filter.', VALUE_DEFAULT, ''),
            'targettype' => new external_value(PARAM_ALPHA, 'Target type filter (course or product).', VALUE_DEFAULT, ''),
            'targetid' => new external_value(PARAM_INT, 'Target ID filter.', VALUE_DEFAULT, 0),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
            'productkind' => new external_value(
                PARAM_ALPHA,
                'Restrict by what the key enrols into: "course" (course + course-product keys) '
                    . 'or "bundle" (bundle/program-product keys). Empty = no restriction.',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $search Search term.
     * @param string $status Status filter.
     * @param string $targettype Target type filter.
     * @param int $targetid Target ID filter.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @param string $productkind Restrict by enrolment kind (course|bundle).
     * @return array
     */
    public static function execute(
        string $search = '',
        string $status = '',
        string $targettype = '',
        int $targetid = 0,
        int $page = 0,
        int $perpage = 10,
        string $productkind = ''
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'status' => $status,
            'targettype' => $targettype,
            'targetid' => $targetid,
            'page' => $page,
            'perpage' => $perpage,
            'productkind' => $productkind,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:generatekeys', $context);

        $page = max(0, $params['page']);
        $perpage = min(100, max(5, $params['perpage']));
        $now = time();

        [$wheresql, $sqlparams] = self::build_filter_sql($params, $now);

        $countsql = "SELECT COUNT(k.id) FROM {local_moderncommerce_enrollkeys} k {$wheresql}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $sql = "SELECT k.*,
                       u.firstname,
                       u.lastname,
                       (SELECT COUNT(1) FROM {local_moderncommerce_key_usage} ku WHERE ku.enrollkeyid = k.id) AS actualused
                  FROM {local_moderncommerce_enrollkeys} k
             LEFT JOIN {user} u ON u.id = k.createdby
                {$wheresql}
              ORDER BY k.timecreated DESC, k.id DESC";
        $records = $DB->get_records_sql($sql, $sqlparams, $page * $perpage, $perpage);

        $targets = self::resolve_targets(array_keys($records), $context);

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_key($record, $targets[$record->id] ?? null, $now);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'stats' => self::get_stats($now, (string) $params['productkind']),
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
            'items' => new external_multiple_structure(self::key_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching keys.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'stats' => self::stats_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Key row structure.
     *
     * @return external_single_structure
     */
    private static function key_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Key ID.'),
            'keycode' => new external_value(PARAM_TEXT, 'Key code.'),
            'keytype' => new external_value(PARAM_ALPHA, 'Key type.'),
            'keytypelabel' => new external_value(PARAM_TEXT, 'Key type label.'),
            'status' => new external_value(PARAM_ALPHA, 'Raw status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Computed status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Status badge class.'),
            'usedcount' => new external_value(PARAM_INT, 'Times used.'),
            'maxuses' => new external_value(PARAM_INT, 'Max uses (0 = unlimited).'),
            'maxusesperuser' => new external_value(PARAM_INT, 'Max uses per user.'),
            'expiryts' => new external_value(PARAM_INT, 'Expiry timestamp.'),
            'expiry' => new external_value(PARAM_TEXT, 'Formatted expiry.'),
            'created' => new external_value(PARAM_TEXT, 'Formatted created date.'),
            'createdbyname' => new external_value(PARAM_TEXT, 'Creator name.'),
            'targettype' => new external_value(PARAM_ALPHA, 'Target type.'),
            'targetlabel' => new external_value(PARAM_TEXT, 'Target type label.'),
            'targetname' => new external_value(PARAM_TEXT, 'Target display name.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total keys.'),
            'active' => new external_value(PARAM_INT, 'Active keys.'),
            'used' => new external_value(PARAM_INT, 'Keys with at least one redemption.'),
            'expired' => new external_value(PARAM_INT, 'Expired keys.'),
        ]);
    }

    /**
     * Build filter SQL.
     *
     * @param array $params Validated parameters.
     * @param int $now Current time.
     * @return array [where, params]
     */
    private static function build_filter_sql(array $params, int $now): array {
        global $DB;

        $where = ['1 = 1'];
        $sqlparams = [];

        if (trim($params['search']) !== '') {
            $where[] = $DB->sql_like('k.keycode', ':search', false);
            $sqlparams['search'] = '%' . $DB->sql_like_escape(trim($params['search'])) . '%';
        }

        switch ($params['status']) {
            case 'active':
                $where[] = "k.status = 'active'";
                break;
            case 'inactive':
                $where[] = "k.status = 'inactive'";
                break;
            case 'expired':
                $where[] = 'k.expirydate > 0 AND k.expirydate < :nowexp';
                $sqlparams['nowexp'] = $now;
                break;
            case 'depleted':
                $where[] = 'k.maxuses > 0 AND k.usedcount >= k.maxuses';
                break;
        }

        $allowedtypes = ['course', 'product'];
        if (in_array($params['targettype'], $allowedtypes, true) || $params['targetid'] > 0) {
            $targetwhere = ['t.enrollkeyid = k.id', "t.includemode = 'include'"];
            if (in_array($params['targettype'], $allowedtypes, true)) {
                $targetwhere[] = 't.targettype = :targettype';
                $sqlparams['targettype'] = $params['targettype'];
            }
            if ($params['targetid'] > 0) {
                $targetwhere[] = 't.targetid = :targetid';
                $sqlparams['targetid'] = $params['targetid'];
            }
            $where[] = 'EXISTS (SELECT 1 FROM {local_moderncommerce_enrollkey_targets} t WHERE '
                . implode(' AND ', $targetwhere) . ')';
        }

        $productkind = (string) ($params['productkind'] ?? '');
        if ($productkind === 'course' || $productkind === 'bundle') {
            $where[] = self::productkind_clause($productkind);
        }

        return ['WHERE ' . implode(' AND ', $where), $sqlparams];
    }

    /**
     * Build the SQL EXISTS clause that scopes keys by what they enrol into.
     *
     * Course keys enrol into a single course, either directly (targettype 'course')
     * or through a course-type product. Bundle keys enrol into a bundle or program
     * product. The clause references the outer enrollkeys alias "k" and uses no bound
     * parameters (only fixed enum literals), so it is safe to embed and reuse.
     *
     * @param string $productkind Either 'course' or 'bundle'.
     * @return string SQL boolean expression (defaults to "1 = 1" for unknown kinds).
     */
    private static function productkind_clause(string $productkind): string {

        if ($productkind === 'course') {
            return "EXISTS (
                        SELECT 1
                          FROM {local_moderncommerce_enrollkey_targets} tk
                         WHERE tk.enrollkeyid = k.id
                           AND tk.includemode = 'include'
                           AND (
                               tk.targettype = 'course'
                               OR (tk.targettype = 'product' AND EXISTS (
                                   SELECT 1
                                     FROM {local_moderncommerce_products} pk
                                    WHERE pk.id = tk.targetid
                                      AND pk.producttype = 'course'))
                           ))";
        }

        if ($productkind === 'bundle') {
            return "EXISTS (
                        SELECT 1
                          FROM {local_moderncommerce_enrollkey_targets} tk
                         WHERE tk.enrollkeyid = k.id
                           AND tk.includemode = 'include'
                           AND tk.targettype = 'product'
                           AND EXISTS (
                               SELECT 1
                                 FROM {local_moderncommerce_products} pk
                                WHERE pk.id = tk.targetid
                                  AND pk.producttype IN ('bundle', 'program')))";
        }

        return '1 = 1';
    }

    /**
     * Resolve the primary include target for each key.
     *
     * @param array $keyids Key IDs.
     * @param context_system $context System context.
     * @return array Target data keyed by key ID.
     */
    private static function resolve_targets(array $keyids, context_system $context): array {
        global $DB;

        if (empty($keyids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($keyids, SQL_PARAMS_NAMED, 'key');
        $rows = $DB->get_records_sql(
            "SELECT id, enrollkeyid, targettype, targetid, targetvalue
               FROM {local_moderncommerce_enrollkey_targets}
              WHERE enrollkeyid {$insql}
                AND includemode = 'include'
           ORDER BY enrollkeyid ASC, id ASC",
            $inparams
        );

        $targetsbykey = [];
        $courseids = [];
        $productids = [];
        foreach ($rows as $row) {
            if (isset($targetsbykey[$row->enrollkeyid])) {
                continue;
            }
            $targetsbykey[$row->enrollkeyid] = $row;
            if ($row->targettype === 'course') {
                $courseids[(int) $row->targetid] = true;
            } else if ($row->targettype === 'product') {
                $productids[(int) $row->targetid] = true;
            }
        }

        $coursenames = self::fetch_names('course', 'fullname', array_keys($courseids));
        $productnames = self::fetch_names('local_moderncommerce_products', 'name', array_keys($productids));

        $resolved = [];
        foreach ($targetsbykey as $keyid => $row) {
            $type = (string) $row->targettype;
            $targetid = (int) $row->targetid;
            if ($type === 'course') {
                $name = $coursenames[$targetid] ?? ($row->targetvalue ?: ('#' . $targetid));
            } else if ($type === 'product') {
                $name = $productnames[$targetid] ?? ($row->targetvalue ?: ('#' . $targetid));
            } else {
                $name = (string) ($row->targetvalue ?? '');
            }
            $resolved[$keyid] = [
                'type' => $type,
                'name' => format_string($name, true, ['context' => $context]),
            ];
        }

        return $resolved;
    }

    /**
     * Batch fetch a name field by ID.
     *
     * @param string $table Table name.
     * @param string $field Field to return.
     * @param array $ids IDs.
     * @return array Names keyed by ID.
     */
    private static function fetch_names(string $table, string $field, array $ids): array {
        global $DB;

        if (empty($ids)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');
        $rows = $DB->get_records_sql("SELECT id, {$field} AS name FROM {{$table}} WHERE id {$insql}", $inparams);

        $names = [];
        foreach ($rows as $row) {
            $names[(int) $row->id] = (string) $row->name;
        }

        return $names;
    }

    /**
     * Format one key record.
     *
     * @param \stdClass $record Key record.
     * @param array|null $target Resolved target.
     * @param int $now Current time.
     * @return array
     */
    private static function format_key(\stdClass $record, ?array $target, int $now): array {
        $isactive = $record->status === 'active';
        $expiryts = (int) ($record->expirydate ?? 0);
        $maxuses = (int) $record->maxuses;
        $isexpired = $isactive && $expiryts > 0 && $expiryts < $now;
        $isdepleted = $isactive && !$isexpired && $maxuses > 0 && (int) $record->usedcount >= $maxuses;

        if ($isactive && !$isexpired && !$isdepleted) {
            $statuslabel = get_string('active', 'local_moderncommerce');
            $statusclass = 'success';
        } else if ($isexpired) {
            $statuslabel = get_string('expired', 'local_moderncommerce');
            $statusclass = 'danger';
        } else if ($isdepleted) {
            $statuslabel = get_string('depleted', 'local_moderncommerce');
            $statusclass = 'warning';
        } else {
            $statuslabel = get_string('inactive', 'local_moderncommerce');
            $statusclass = 'neutral';
        }

        $targettype = $target['type'] ?? '';

        return [
            'id' => (int) $record->id,
            'keycode' => (string) $record->keycode,
            'keytype' => (string) $record->keytype,
            'keytypelabel' => $record->keytype === 'single'
                ? get_string('singleuse', 'local_moderncommerce')
                : get_string('multiuse', 'local_moderncommerce'),
            'status' => (string) $record->status,
            'statuslabel' => $statuslabel,
            'statusclass' => $statusclass,
            'usedcount' => (int) $record->usedcount,
            'maxuses' => $maxuses,
            'maxusesperuser' => (int) $record->maxusesperuser,
            'expiryts' => $expiryts,
            'expiry' => $expiryts > 0
                ? userdate($expiryts, get_string('strftimedate', 'langconfig'))
                : get_string('noexpiry', 'local_moderncommerce'),
            'created' => userdate((int) $record->timecreated, get_string('strftimedate', 'langconfig')),
            'createdbyname' => trim((string) ($record->firstname ?? '') . ' ' . (string) ($record->lastname ?? '')),
            'targettype' => $targettype,
            'targetlabel' => $targettype === 'course'
                ? get_string('course')
                : ($targettype === 'product' ? get_string('bundleorprogram', 'local_moderncommerce') : ''),
            'targetname' => $target['name'] ?? '',
        ];
    }

    /**
     * Key summary stats, scoped to the same enrolment kind as the listing so the
     * metric tiles match the page (course keys vs bundle keys). Search/status
     * filters are intentionally ignored here so the tiles stay stable while the
     * admin filters the table.
     *
     * @param int $now Current time.
     * @param string $productkind Restrict by enrolment kind (course|bundle), or '' for all.
     * @return array
     */
    private static function get_stats(int $now, string $productkind = ''): array {
        global $DB;

        $scope = self::productkind_clause($productkind);

        return [
            'total' => (int) $DB->count_records_sql(
                "SELECT COUNT(k.id) FROM {local_moderncommerce_enrollkeys} k WHERE {$scope}"
            ),
            'active' => (int) $DB->count_records_sql(
                "SELECT COUNT(k.id) FROM {local_moderncommerce_enrollkeys} k WHERE k.status = 'active' AND {$scope}"
            ),
            'used' => (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT ku.enrollkeyid)
                   FROM {local_moderncommerce_key_usage} ku
                   JOIN {local_moderncommerce_enrollkeys} k ON k.id = ku.enrollkeyid
                  WHERE {$scope}"
            ),
            'expired' => (int) $DB->count_records_sql(
                "SELECT COUNT(k.id)
                   FROM {local_moderncommerce_enrollkeys} k
                  WHERE k.expirydate > 0 AND k.expirydate < :now AND {$scope}",
                ['now' => $now]
            ),
        ];
    }
}
