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
 * External API searching customers for the invoice editor.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\invoices;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Typeahead search for customers (Moodle users).
 */
class search_customers extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search by name or email.', VALUE_DEFAULT, ''),
            'limit' => new external_value(PARAM_INT, 'Maximum results.', VALUE_DEFAULT, 20),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $query Search term.
     * @param int $limit Limit.
     * @return array
     */
    public static function execute(string $query = '', int $limit = 20): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'limit' => $limit,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:manageorders', $context);

        $query = trim($params['query']);
        $limit = min(50, max(1, $params['limit']));

        $where = ['u.deleted = 0', 'u.suspended = 0', 'u.id <> :guestid'];
        $sqlparams = ['guestid' => (int) $CFG->siteguest];

        if ($query !== '') {
            $namesql = $DB->sql_fullname('u.firstname', 'u.lastname');
            $where[] = '(' . $DB->sql_like($namesql, ':q1', false) . ' OR '
                . $DB->sql_like('u.email', ':q2', false) . ' OR '
                . $DB->sql_like('u.firstname', ':q3', false) . ' OR '
                . $DB->sql_like('u.lastname', ':q4', false) . ')';
            $term = '%' . $DB->sql_like_escape($query) . '%';
            $sqlparams['q1'] = $term;
            $sqlparams['q2'] = $term;
            $sqlparams['q3'] = $term;
            $sqlparams['q4'] = $term;
        }

        $records = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                    u.middlename, u.alternatename, u.email
               FROM {user} u
              WHERE " . implode(' AND ', $where) . "
           ORDER BY u.lastname ASC, u.firstname ASC",
            $sqlparams,
            0,
            $limit
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = [
                'id' => (int) $record->id,
                'fullname' => fullname($record),
                'email' => (string) $record->email,
            ];
        }

        return [
            'items' => $items,
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
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'User ID.'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name.'),
                'email' => new external_value(PARAM_TEXT, 'Email.'),
            ])),
            'warnings' => new \core_external\external_warnings(),
        ]);
    }
}
