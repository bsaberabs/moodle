<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_oriskill;

/**
 * Log of a trainer space: who changed what (prices, payment accounts, learners' access, team...).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_service {

    /** Entries per page of the log. */
    const PERPAGE = 50;

    /** Categories of the log, by action prefix (anything else is a setting). */
    const CATEGORIES = [
        'access' => ['learner_', 'access_', 'accessmail_', 'notifs_', 'unpaid_', 'subscription_'],
        'sales' => ['price_', 'instalments_', 'affiliaterate_', 'coupon_', 'payment_', 'orderform_', 'abandon_', 'invoice_',
            'quote_', 'credit_', 'refund_', 'billing_', 'accounting_'],
        'crm' => ['contact_', 'contacts_', 'segment_', 'capturepage_'],
        'team' => ['member_', 'export'],
    ];

    /**
     * Record an action of the current user (or of Oriskill itself when run by a task or a payment notification).
     *
     * @param \stdClass $creator Trainer space.
     * @param string $action Action key (log:<action> language string).
     * @param array $details Values for the description.
     * @param int|null $courseid Course concerned.
     * @param int|null $relateduserid Learner or collaborator concerned.
     * @param array $changes What changed: label => [before, after] (unchanged values are left out).
     */
    public static function add(\stdClass $creator, string $action, array $details = [], ?int $courseid = null,
            ?int $relateduserid = null, array $changes = []): void {
        global $DB, $USER;

        foreach ($changes as $label => [$before, $after]) {
            if ((string) $before !== (string) $after) {
                $details['_changes'][] = [(string) $label, (string) $before, (string) $after];
            }
        }
        $DB->insert_record('local_oriskill_log', (object) [
            'creatorid' => $creator->id,
            'userid' => isloggedin() && !isguestuser() ? (int) $USER->id : 0,
            'action' => $action,
            'courseid' => $courseid,
            'relateduserid' => $relateduserid,
            'details' => $details ? json_encode($details) : null,
            'timecreated' => time(),
        ]);
    }

    /**
     * Before / after of two states: label => [before, after], for the labels of either state (missing: –).
     *
     * @param array $before label => value before.
     * @param array $after label => value after.
     * @return array label => [before, after]
     */
    public static function diff(array $before, array $after): array {
        $changes = [];
        foreach (array_keys($before + $after) as $label) {
            $changes[$label] = [$before[$label] ?? '–', $after[$label] ?? '–'];
        }
        return $changes;
    }

    /**
     * What an entry changed.
     *
     * @param \stdClass $entry Log entry.
     * @return array of [label, before, after]
     */
    public static function changes(\stdClass $entry): array {
        return json_decode((string) $entry->details, true)['_changes'] ?? [];
    }

    /**
     * Category of an action.
     *
     * @param string $action Action key.
     * @return string access, sales, crm, team or settings.
     */
    public static function category(string $action): string {
        foreach (self::CATEGORIES as $category => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($action, $prefix)) {
                    return $category;
                }
            }
        }
        return 'settings';
    }

    /**
     * Entries of a trainer space, newest first.
     *
     * @param \stdClass $creator Trainer space.
     * @param int $page Page, from 0.
     * @param array $filters category, userid (author, 0 for Oriskill itself, -1 for all), from and to (timestamps),
     *                       learner (part of the name or e-mail of the person concerned).
     * @return array [entries, total]
     */
    public static function entries(\stdClass $creator, int $page = 0, array $filters = []): array {
        global $DB;

        $where = ['creatorid = :creatorid'];
        $params = ['creatorid' => $creator->id];
        $category = $filters['category'] ?? '';
        if ($category === 'settings') {
            $n = 0;
            foreach (array_merge(...array_values(self::CATEGORIES)) as $prefix) {
                $where[] = $DB->sql_like('action', ':p' . $n, true, true, true);
                $params['p' . $n++] = $DB->sql_like_escape($prefix) . '%';
            }
        } else if (isset(self::CATEGORIES[$category])) {
            $likes = [];
            foreach (self::CATEGORIES[$category] as $n => $prefix) {
                $likes[] = $DB->sql_like('action', ':p' . $n);
                $params['p' . $n] = $DB->sql_like_escape($prefix) . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }
        if (($filters['userid'] ?? -1) >= 0) {
            $where[] = 'userid = :userid';
            $params['userid'] = (int) $filters['userid'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'timecreated >= :from';
            $params['from'] = (int) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'timecreated <= :to';
            $params['to'] = (int) $filters['to'];
        }
        if (($learner = trim($filters['learner'] ?? '')) !== '') {
            $where[] = 'relateduserid IN (SELECT id FROM {user} WHERE ' .
                $DB->sql_like($DB->sql_fullname('firstname', 'lastname'), ':name', false, false) . ' OR ' .
                $DB->sql_like('email', ':email', false, false) . ')';
            $params['name'] = $params['email'] = '%' . $DB->sql_like_escape($learner) . '%';
        }
        $select = implode(' AND ', $where);
        return [
            $DB->get_records_select('local_oriskill_log', $select, $params, 'timecreated DESC, id DESC', '*',
                $page * self::PERPAGE, self::PERPAGE),
            $DB->count_records_select('local_oriskill_log', $select, $params),
        ];
    }

    /**
     * The people who acted in a trainer space.
     *
     * @param \stdClass $creator Trainer space.
     * @return array userid => name (0: Oriskill itself)
     */
    public static function authors(\stdClass $creator): array {
        global $DB;

        $authors = [];
        foreach ($DB->get_fieldset_sql('SELECT DISTINCT userid FROM {local_oriskill_log} WHERE creatorid = ?', [$creator->id])
                as $userid) {
            $user = $userid ? \core_user::get_user($userid) : null;
            $authors[(int) $userid] = $userid ? ($user ? fullname($user) : '?')
                : get_string('log:automatic', 'local_oriskill');
        }
        \core_collator::asort($authors);
        return $authors;
    }

    /**
     * Readable description of an entry.
     *
     * @param \stdClass $entry Log entry.
     * @return string Plain text.
     */
    public static function describe(\stdClass $entry): string {
        global $DB;

        $a = (object) (json_decode((string) $entry->details, true) ?: []);
        unset($a->_changes);
        if (!empty($a->role)) {
            $a->role = get_string('role:' . $a->role, 'local_oriskill');
        }
        if (!empty($a->from)) {
            $a->from = get_string('role:' . $a->from, 'local_oriskill');
        }
        $a->course = $entry->courseid
            ? format_string((string) $DB->get_field('course', 'fullname', ['id' => $entry->courseid])) : '';
        $user = $entry->relateduserid ? \core_user::get_user($entry->relateduserid) : null;
        $a->user = $user ? fullname($user) : '';
        $key = 'log:' . $entry->action;
        return get_string_manager()->string_exists($key, 'local_oriskill')
            ? get_string($key, 'local_oriskill', $a) : $entry->action;
    }
}
