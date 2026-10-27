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
 * Shared structures and helpers for read-only ledger web services.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\ledgers;

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Shared building blocks for the generic admin ledger apps.
 */
class ledger_support {
    /**
     * Standard ledger request parameters.
     *
     * @return external_function_parameters
     */
    public static function params(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search term.', VALUE_DEFAULT, ''),
            'gateway' => new external_value(PARAM_ALPHANUMEXT, 'Gateway filter.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status/result filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Standard ledger return structure (generic cell table).
     *
     * @return external_single_structure
     */
    public static function returns(): external_single_structure {
        return new external_single_structure([
            'columns' => new external_multiple_structure(new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Column header.'),
                'align' => new external_value(PARAM_ALPHA, 'Column alignment (start or end).'),
            ])),
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Row ID.'),
                'cells' => new external_multiple_structure(new external_single_structure([
                    'value' => new external_value(PARAM_TEXT, 'Cell value.'),
                    'badge' => new external_value(PARAM_ALPHA, 'Badge class (empty for plain text).'),
                ])),
                'details' => new external_multiple_structure(new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Detail label.'),
                    'value' => new external_value(PARAM_RAW, 'Detail value.'),
                    'badge' => new external_value(PARAM_ALPHA, 'Badge class (empty for plain text).'),
                ]), 'Optional row details.', VALUE_OPTIONAL),
            ])),
            'total' => new external_value(PARAM_INT, 'Total matching rows.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Records per page.'),
            'gateways' => new external_multiple_structure(new external_single_structure([
                'value' => new external_value(PARAM_ALPHANUMEXT, 'Gateway value.'),
                'label' => new external_value(PARAM_TEXT, 'Gateway label.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Build a column descriptor.
     *
     * @param string $label Header label.
     * @param string $align start or end.
     * @return array
     */
    public static function column(string $label, string $align = 'start'): array {
        return ['label' => $label, 'align' => $align === 'end' ? 'end' : 'start'];
    }

    /**
     * Build a cell.
     *
     * @param mixed $value Cell value.
     * @param string $badge Badge class (empty for plain text).
     * @return array
     */
    public static function cell($value, string $badge = ''): array {
        return ['value' => (string) $value, 'badge' => $badge];
    }

    /**
     * Normalise page/perpage.
     *
     * @param int $page Page.
     * @param int $perpage Per page.
     * @return array [page, perpage]
     */
    public static function paginate(int $page, int $perpage): array {
        return [max(0, $page), min(100, max(5, $perpage))];
    }

    /**
     * Badge class for an event/result status.
     *
     * @param string $status Status.
     * @return string
     */
    public static function status_class(string $status): string {
        $status = strtolower($status);
        if (in_array($status, ['success', 'processed', 'completed', 'paid', 'verified'], true)) {
            return 'success';
        }
        if (in_array($status, ['failed', 'failure', 'error', 'invalid', 'declined', 'denied'], true)) {
            return 'danger';
        }
        if (in_array($status, ['pending', 'received', 'processing', 'retrying', 'warning'], true)) {
            return 'warning';
        }

        return 'neutral';
    }

    /**
     * Distinct gateway filter options from a table.
     *
     * @param string $table Table name.
     * @return array
     */
    public static function gateway_options(string $table): array {
        global $DB;

        if (!$DB->get_manager()->table_exists($table)) {
            return [];
        }

        $rows = $DB->get_records_sql("SELECT DISTINCT gateway FROM {{$table}} WHERE gateway IS NOT NULL ORDER BY gateway ASC");
        $options = [];
        foreach ($rows as $row) {
            if ((string) $row->gateway === '') {
                continue;
            }
            $options[] = ['value' => (string) $row->gateway, 'label' => ucfirst((string) $row->gateway)];
        }

        return $options;
    }

    /**
     * Format a timestamp for ledger display.
     *
     * @param int $timestamp Timestamp.
     * @return string
     */
    public static function date(int $timestamp): string {
        return $timestamp > 0 ? userdate($timestamp, get_string('strftimedatetimeshort', 'langconfig')) : '-';
    }
}
