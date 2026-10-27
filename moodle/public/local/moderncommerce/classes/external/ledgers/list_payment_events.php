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
 * External API listing payment events for the admin ledger.
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
 * List Modern Commerce payment events.
 */
class list_payment_events extends external_api {
    /** @var string Source table. */
    private const TABLE = 'local_moderncommerce_payment_events';

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
     * @param string $gateway Gateway filter.
     * @param string $status Status filter.
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
        require_capability('local/moderncommerce:configuregateways', $context);

        [$page, $perpage] = ledger_support::paginate($params['page'], $params['perpage']);

        $columns = [
            ledger_support::column(get_string('gateway', 'local_moderncommerce')),
            ledger_support::column(get_string('eventtype', 'local_moderncommerce')),
            ledger_support::column(get_string('reference', 'local_moderncommerce')),
            ledger_support::column(get_string('amount', 'local_moderncommerce'), 'end'),
            ledger_support::column(get_string('status', 'local_moderncommerce')),
            ledger_support::column(get_string('when', 'local_moderncommerce')),
        ];

        if (!$DB->get_manager()->table_exists(self::TABLE)) {
            return self::empty($columns, $page, $perpage);
        }

        $where = ['1 = 1'];
        $sqlparams = [];
        if (trim($params['search']) !== '') {
            $where[] = '(' . $DB->sql_like('reference', ':s1', false) . ' OR '
                . $DB->sql_like('transactionid', ':s2', false) . ' OR '
                . $DB->sql_like('eventtype', ':s3', false) . ')';
            $term = '%' . $DB->sql_like_escape(trim($params['search'])) . '%';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
            $sqlparams['s3'] = $term;
        }
        if ($params['gateway'] !== '') {
            $where[] = 'gateway = :gateway';
            $sqlparams['gateway'] = $params['gateway'];
        }
        if ($params['status'] !== '') {
            $where[] = 'status = :status';
            $sqlparams['status'] = $params['status'];
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

        $items = [];
        foreach ($records as $r) {
            $status = (string) ($r->status ?? '');
            $amount = number_format((float) $r->amount, 2) . ' ' . strtoupper((string) ($r->currency ?? ''));
            $items[] = [
                'id' => (int) $r->id,
                'cells' => [
                    ledger_support::cell(ucfirst((string) $r->gateway)),
                    ledger_support::cell($r->eventtype),
                    ledger_support::cell($r->reference ?: ($r->transactionid ?: '-')),
                    ledger_support::cell(trim($amount)),
                    ledger_support::cell(localisation::status_label($status), ledger_support::status_class($status)),
                    ledger_support::cell(ledger_support::date((int) $r->timecreated)),
                ],
            ];
        }

        return [
            'columns' => $columns,
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perpage' => $perpage,
            'gateways' => ledger_support::gateway_options(self::TABLE),
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
