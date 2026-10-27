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
 * External API for listing coupons.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\coupons;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_text;
use local_moderncommerce\localisation;
use local_moderncommerce\services\pricing_service;

/**
 * List coupons for the React admin coupon screen.
 */
class list_coupons extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search coupon name or code.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Runtime coupon status filter.', VALUE_DEFAULT, ''),
            'discounttype' => new external_value(PARAM_ALPHANUMEXT, 'Discount type filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort key.', VALUE_DEFAULT, 'timecreated'),
            'direction' => new external_value(PARAM_ALPHA, 'Sort direction.', VALUE_DEFAULT, 'DESC'),
        ]);
    }

    /**
     * Execute the coupon listing.
     *
     * @param string $search Search term.
     * @param string $status Runtime coupon status filter.
     * @param string $discounttype Discount type filter.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @param string $sort Sort key.
     * @param string $direction Sort direction.
     * @return array
     */
    public static function execute(
        string $search = '',
        string $status = '',
        string $discounttype = '',
        int $page = 0,
        int $perpage = 10,
        string $sort = 'timecreated',
        string $direction = 'DESC'
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'status' => $status,
            'discounttype' => $discounttype,
            'page' => $page,
            'perpage' => $perpage,
            'sort' => $sort,
            'direction' => $direction,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecoupons', $context);

        $params = self::normalise_parameters($params);
        [$wheresql, $sqlparams] = self::build_filter_sql($params);
        [$sortkey, $sortsql, $sortdirection] = self::get_sort_sql($params['sort'], $params['direction']);

        $countsql = "SELECT COUNT(1)
                       FROM {local_moderncommerce_coupons} c
                      {$wheresql}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $selectsql = "SELECT c.id,
                             c.code,
                             c.name,
                             c.discounttype,
                             c.value,
                             c.maxdiscount,
                             c.minpurchase,
                             c.minitems,
                             c.maxuses,
                             c.usedcount,
                             c.maxusesperuser,
                             c.stackable,
                             c.status,
                             c.startdate,
                             c.enddate,
                             c.createdby,
                             c.timecreated,
                             c.timemodified,
                             COALESCE(usagecount.actualuses, 0) AS actualuses,
                             COALESCE(usagecount.discounttotal, 0) AS discounttotal
                        FROM {local_moderncommerce_coupons} c
                   LEFT JOIN (
                             SELECT couponid,
                                    COUNT(1) AS actualuses,
                                    SUM(discountamount) AS discounttotal
                               FROM {local_moderncommerce_coupon_usage}
                           GROUP BY couponid
                             ) usagecount ON usagecount.couponid = c.id
                      {$wheresql}
                    ORDER BY {$sortsql} {$sortdirection}, c.id ASC";

        $records = $DB->get_records_sql(
            $selectsql,
            $sqlparams,
            $params['page'] * $params['perpage'],
            $params['perpage']
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_coupon($record, $context);
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'sort' => $sortkey,
            'direction' => $sortdirection,
            'currency' => self::get_currency_data(),
            'stats' => self::get_stats(),
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
            'items' => new external_multiple_structure(self::coupon_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching coupons.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Applied sort key.'),
            'direction' => new external_value(PARAM_ALPHA, 'Applied sort direction.'),
            'currency' => self::currency_structure(),
            'stats' => self::stats_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Coupon return structure.
     *
     * @return external_single_structure
     */
    private static function coupon_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Coupon ID.'),
            'code' => new external_value(PARAM_TEXT, 'Coupon code.'),
            'name' => new external_value(PARAM_TEXT, 'Coupon name.'),
            'discounttype' => new external_value(PARAM_ALPHANUMEXT, 'Discount type.'),
            'discounttypelabel' => new external_value(PARAM_TEXT, 'Display discount type.'),
            'value' => new external_value(PARAM_FLOAT, 'Discount value.'),
            'displayvalue' => new external_value(PARAM_TEXT, 'Formatted discount value.'),
            'maxdiscount' => new external_value(PARAM_FLOAT, 'Maximum discount amount.'),
            'displaymaxdiscount' => new external_value(PARAM_TEXT, 'Formatted maximum discount.'),
            'minpurchase' => new external_value(PARAM_FLOAT, 'Minimum purchase amount.'),
            'displayminpurchase' => new external_value(PARAM_TEXT, 'Formatted minimum purchase.'),
            'minitems' => new external_value(PARAM_INT, 'Minimum item count.'),
            'maxuses' => new external_value(PARAM_INT, 'Maximum uses, or 0 for unlimited.'),
            'usedcount' => new external_value(PARAM_INT, 'Stored usage counter.'),
            'actualuses' => new external_value(PARAM_INT, 'Actual usage records.'),
            'maxusesperuser' => new external_value(PARAM_INT, 'Maximum uses per user, or 0 for unlimited.'),
            'stackable' => new external_value(PARAM_BOOL, 'Whether coupon can be stacked.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Stored status.'),
            'runtimestatus' => new external_value(PARAM_ALPHANUMEXT, 'Computed runtime status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Display status.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Display status class.'),
            'startdate' => new external_value(PARAM_INT, 'Start timestamp, or 0.'),
            'enddate' => new external_value(PARAM_INT, 'End timestamp, or 0.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
            'discounttotal' => new external_value(PARAM_FLOAT, 'Total redeemed discount amount.'),
            'displaydiscounttotal' => new external_value(PARAM_TEXT, 'Formatted total redeemed discount.'),
        ]);
    }

    /**
     * Currency return structure.
     *
     * @return external_single_structure
     */
    private static function currency_structure(): external_single_structure {
        return new external_single_structure([
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.'),
            'symbol' => new external_value(PARAM_TEXT, 'Currency symbol.'),
            'position' => new external_value(PARAM_ALPHA, 'Symbol position.'),
            'decimals' => new external_value(PARAM_INT, 'Decimal places.'),
        ]);
    }

    /**
     * Stats return structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'totalcoupons' => new external_value(PARAM_INT, 'Total coupon count.'),
            'activecoupons' => new external_value(PARAM_INT, 'Currently usable coupons.'),
            'scheduledcoupons' => new external_value(PARAM_INT, 'Scheduled coupons.'),
            'expiredcoupons' => new external_value(PARAM_INT, 'Expired coupons.'),
            'depletedcoupons' => new external_value(PARAM_INT, 'Depleted coupons.'),
            'inactivecoupons' => new external_value(PARAM_INT, 'Inactive or archived coupons.'),
            'totalredemptions' => new external_value(PARAM_INT, 'Coupon redemption count.'),
            'totaldiscount' => new external_value(PARAM_FLOAT, 'Total coupon discount redeemed.'),
            'displaytotaldiscount' => new external_value(PARAM_TEXT, 'Formatted coupon discount redeemed.'),
        ]);
    }

    /**
     * Normalise request parameters.
     *
     * @param array $params Raw parameters.
     * @return array Normalised parameters.
     */
    private static function normalise_parameters(array $params): array {
        $params['search'] = trim((string) $params['search']);
        $params['status'] = self::normalise_choice(
            (string) $params['status'],
            ['', 'active', 'scheduled', 'expired', 'depleted', 'inactive', 'archived'],
            ''
        );
        $params['discounttype'] = self::normalise_choice((string) $params['discounttype'], ['', 'percentage', 'fixed'], '');
        $params['page'] = max(0, (int) $params['page']);
        $params['perpage'] = min(100, max(10, (int) $params['perpage']));
        $params['direction'] = core_text::strtoupper((string) $params['direction']) === 'ASC' ? 'ASC' : 'DESC';

        return $params;
    }

    /**
     * Build filter SQL.
     *
     * @param array $params Normalised parameters.
     * @return array{0:string,1:array}
     */
    private static function build_filter_sql(array $params): array {
        global $DB;

        $where = [];
        $sqlparams = [];
        $now = time();

        if ($params['search'] !== '') {
            $search = '%' . $DB->sql_like_escape(core_text::strtolower($params['search'])) . '%';
            $where[] = '(' . $DB->sql_like('LOWER(c.code)', ':searchcode', false, false)
                . ' OR ' . $DB->sql_like('LOWER(c.name)', ':searchname', false, false) . ')';
            $sqlparams['searchcode'] = $search;
            $sqlparams['searchname'] = $search;
        }

        if ($params['discounttype'] !== '') {
            $where[] = 'c.discounttype = :discounttype';
            $sqlparams['discounttype'] = $params['discounttype'];
        }

        if ($params['status'] !== '') {
            self::append_status_filter($where, $sqlparams, $params['status'], $now);
        }

        return [
            empty($where) ? '' : 'WHERE ' . implode(' AND ', $where),
            $sqlparams,
        ];
    }

    /**
     * Append runtime status filter SQL.
     *
     * @param array $where Where clauses.
     * @param array $sqlparams SQL parameters.
     * @param string $status Runtime status.
     * @param int $now Current timestamp.
     */
    private static function append_status_filter(array &$where, array &$sqlparams, string $status, int $now): void {
        if ($status === 'inactive' || $status === 'archived') {
            $where[] = 'c.status = :statusfilter';
            $sqlparams['statusfilter'] = $status;
            return;
        }

        if ($status === 'scheduled') {
            $where[] = 'c.status = :statusfilter AND COALESCE(c.startdate, 0) > :statusnow';
            $sqlparams['statusfilter'] = 'active';
            $sqlparams['statusnow'] = $now;
            return;
        }

        if ($status === 'expired') {
            $where[] = 'c.status = :statusfilter AND COALESCE(c.enddate, 0) > 0 AND c.enddate < :statusnow';
            $sqlparams['statusfilter'] = 'active';
            $sqlparams['statusnow'] = $now;
            return;
        }

        if ($status === 'depleted') {
            $where[] = 'c.status = :statusfilter AND COALESCE(c.maxuses, 0) > 0 AND c.usedcount >= c.maxuses';
            $sqlparams['statusfilter'] = 'active';
            return;
        }

        $where[] = "c.status = :statusfilter
                    AND COALESCE(c.startdate, 0) <= :activefrom
                    AND (COALESCE(c.enddate, 0) = 0 OR c.enddate >= :activeto)
                    AND (COALESCE(c.maxuses, 0) = 0 OR c.usedcount < c.maxuses)";
        $sqlparams['statusfilter'] = 'active';
        $sqlparams['activefrom'] = $now;
        $sqlparams['activeto'] = $now;
    }

    /**
     * Build safe sort SQL.
     *
     * @param string $sort Sort key.
     * @param string $direction Sort direction.
     * @return array{0:string,1:string,2:string}
     */
    private static function get_sort_sql(string $sort, string $direction): array {
        $allowed = [
            'code' => 'c.code',
            'name' => 'c.name',
            'discounttype' => 'c.discounttype',
            'value' => 'c.value',
            'usedcount' => 'c.usedcount',
            'maxuses' => 'c.maxuses',
            'startdate' => 'c.startdate',
            'enddate' => 'c.enddate',
            'status' => 'c.status',
            'timecreated' => 'c.timecreated',
            'timemodified' => 'c.timemodified',
        ];

        $sortkey = array_key_exists($sort, $allowed) ? $sort : 'timecreated';
        $sortdirection = core_text::strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return [$sortkey, $allowed[$sortkey], $sortdirection];
    }

    /**
     * Format one coupon record.
     *
     * @param \stdClass $record Coupon record.
     * @param context_system $context System context.
     * @return array
     */
    private static function format_coupon(\stdClass $record, context_system $context): array {
        $discounttype = self::normalise_choice((string) $record->discounttype, ['percentage', 'fixed'], 'percentage');
        $value = (float) $record->value;
        $maxdiscount = empty($record->maxdiscount) ? 0.0 : (float) $record->maxdiscount;
        $minpurchase = empty($record->minpurchase) ? 0.0 : (float) $record->minpurchase;
        $minitems = empty($record->minitems) ? 0 : (int) $record->minitems;
        $maxuses = empty($record->maxuses) ? 0 : (int) $record->maxuses;
        $maxusesperuser = empty($record->maxusesperuser) ? 0 : (int) $record->maxusesperuser;
        $startdate = empty($record->startdate) ? 0 : (int) $record->startdate;
        $enddate = empty($record->enddate) ? 0 : (int) $record->enddate;
        $runtime = self::get_runtime_status($record);
        $discounttotal = empty($record->discounttotal) ? 0.0 : (float) $record->discounttotal;

        return [
            'id' => (int) $record->id,
            'code' => (string) $record->code,
            'name' => format_string($record->name ?: $record->code, true, ['context' => $context]),
            'discounttype' => $discounttype,
            'discounttypelabel' => self::get_discount_type_label($discounttype),
            'value' => $value,
            'displayvalue' => self::format_discount_value($discounttype, $value),
            'maxdiscount' => $maxdiscount,
            'displaymaxdiscount' => $maxdiscount > 0 ? pricing_service::format_price($maxdiscount) : '',
            'minpurchase' => $minpurchase,
            'displayminpurchase' => $minpurchase > 0 ? pricing_service::format_price($minpurchase) : '',
            'minitems' => $minitems,
            'maxuses' => $maxuses,
            'usedcount' => (int) $record->usedcount,
            'actualuses' => (int) $record->actualuses,
            'maxusesperuser' => $maxusesperuser,
            'stackable' => !empty($record->stackable),
            'status' => (string) $record->status,
            'runtimestatus' => $runtime,
            'statuslabel' => self::get_status_label($runtime),
            'statusclass' => self::get_status_class($runtime),
            'startdate' => $startdate,
            'enddate' => $enddate,
            'timecreated' => (int) $record->timecreated,
            'timemodified' => (int) $record->timemodified,
            'discounttotal' => $discounttotal,
            'displaydiscounttotal' => pricing_service::format_price($discounttotal),
        ];
    }

    /**
     * Get aggregate coupon stats.
     *
     * @return array
     */
    private static function get_stats(): array {
        global $DB;

        $stats = [
            'totalcoupons' => 0,
            'activecoupons' => 0,
            'scheduledcoupons' => 0,
            'expiredcoupons' => 0,
            'depletedcoupons' => 0,
            'inactivecoupons' => 0,
        ];

        $records = $DB->get_records(
            'local_moderncommerce_coupons',
            null,
            '',
            'id, status, startdate, enddate, maxuses, usedcount'
        );

        foreach ($records as $record) {
            $stats['totalcoupons']++;
            $runtime = self::get_runtime_status($record);
            if ($runtime === 'active') {
                $stats['activecoupons']++;
            } else if ($runtime === 'scheduled') {
                $stats['scheduledcoupons']++;
            } else if ($runtime === 'expired') {
                $stats['expiredcoupons']++;
            } else if ($runtime === 'depleted') {
                $stats['depletedcoupons']++;
            } else {
                $stats['inactivecoupons']++;
            }
        }

        $discounttotal = (float) $DB->get_field_sql(
            'SELECT COALESCE(SUM(discountamount), 0) FROM {local_moderncommerce_coupon_usage}'
        );

        return $stats + [
            'totalredemptions' => (int) $DB->count_records('local_moderncommerce_coupon_usage'),
            'totaldiscount' => $discounttotal,
            'displaytotaldiscount' => pricing_service::format_price($discounttotal),
        ];
    }

    /**
     * Get configured currency data.
     *
     * @return array
     */
    private static function get_currency_data(): array {
        $config = pricing_service::get_currency_config();

        return [
            'code' => (string) $config->currency,
            'symbol' => (string) $config->symbol,
            'position' => (string) $config->position,
            'decimals' => (int) $config->decimals,
        ];
    }

    /**
     * Compute the runtime coupon status.
     *
     * @param \stdClass $record Coupon record.
     * @return string Runtime status.
     */
    private static function get_runtime_status(\stdClass $record): string {
        $status = (string) ($record->status ?? 'active');
        if ($status !== 'active') {
            return $status === 'archived' ? 'archived' : 'inactive';
        }

        $now = time();
        $startdate = empty($record->startdate) ? 0 : (int) $record->startdate;
        $enddate = empty($record->enddate) ? 0 : (int) $record->enddate;
        $maxuses = empty($record->maxuses) ? 0 : (int) $record->maxuses;
        $usedcount = empty($record->usedcount) ? 0 : (int) $record->usedcount;

        if ($startdate > 0 && $startdate > $now) {
            return 'scheduled';
        }

        if ($enddate > 0 && $enddate < $now) {
            return 'expired';
        }

        if ($maxuses > 0 && $usedcount >= $maxuses) {
            return 'depleted';
        }

        return 'active';
    }

    /**
     * Get display label for coupon status.
     *
     * @param string $status Runtime status.
     * @return string Label.
     */
    private static function get_status_label(string $status): string {
        return localisation::status_label($status);
    }

    /**
     * Get visual class for coupon status.
     *
     * @param string $status Runtime status.
     * @return string Badge class suffix.
     */
    private static function get_status_class(string $status): string {
        if ($status === 'active') {
            return 'success';
        }

        if ($status === 'scheduled') {
            return 'info';
        }

        if ($status === 'expired') {
            return 'danger';
        }

        if ($status === 'depleted') {
            return 'warning';
        }

        return 'neutral';
    }

    /**
     * Format discount value.
     *
     * @param string $discounttype Discount type.
     * @param float $value Raw value.
     * @return string Formatted value.
     */
    private static function format_discount_value(string $discounttype, float $value): string {
        if ($discounttype === 'fixed') {
            return pricing_service::format_price($value);
        }

        return rtrim(rtrim(number_format($value, 2), '0'), '.') . '%';
    }

    /**
     * Get label for discount type.
     *
     * @param string $discounttype Discount type.
     * @return string Label.
     */
    private static function get_discount_type_label(string $discounttype): string {
        $stringid = 'coupontype_' . $discounttype;
        if (get_string_manager()->string_exists($stringid, 'local_moderncommerce')) {
            return get_string($stringid, 'local_moderncommerce');
        }

        return ucfirst($discounttype);
    }

    /**
     * Normalise an option value.
     *
     * @param string $value Submitted value.
     * @param array $allowed Allowed values.
     * @param string $fallback Fallback value.
     * @return string Normalised value.
     */
    private static function normalise_choice(string $value, array $allowed, string $fallback): string {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
