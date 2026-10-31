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

use moodle_url;

/**
 * Search of the whole trainer space: people, orders, invoices, courses, order forms, tickets. Each kind is searched only
 * when the role may open its page.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_service {

    /** Kinds of result, in the order shown, with the page that must be open to the role. */
    const KINDS = [
        'people' => '/local/oriskill/contacts.php',
        'orders' => '/local/oriskill/sales.php',
        'invoices' => '/local/oriskill/invoices.php',
        'courses' => '/local/oriskill/learners.php',
        'orderforms' => '/local/oriskill/orderforms.php',
        'tickets' => '/local/oriskill/tickets.php',
    ];

    /** Results per kind. */
    const LIMIT = 8;

    /** Shortest search. */
    const MINLENGTH = 2;

    /**
     * Search.
     *
     * @param \stdClass $creator Trainer.
     * @param string $role Role of the reader.
     * @param string $q Words searched.
     * @param string $only One kind only ('' for all).
     * @return array kind => list of {title, subtitle, url}
     */
    public static function search(\stdClass $creator, string $role, string $q, string $only = ''): array {
        global $DB;

        $q = trim($q);
        if (\core_text::strlen($q) < self::MINLENGTH) {
            return [];
        }
        $like = '%' . $DB->sql_like_escape($q) . '%';
        $results = [];
        foreach (self::KINDS as $kind => $path) {
            if (($only !== '' && $only !== $kind) || !team_service::can($role, $path)) {
                continue;
            }
            $results[$kind] = self::$kind($creator, $q, $like);
        }
        return array_filter($results);
    }

    /**
     * People of the CRM by name, e-mail or phone (a coach: their learners only, through the CRM rules).
     *
     * @param \stdClass $creator Trainer.
     * @param string $q Words.
     * @param string $like LIKE pattern.
     * @return array
     */
    protected static function people(\stdClass $creator, string $q, string $like): array {
        [$contacts] = contact_service::search($creator, ['q' => $q], 'created', 'DESC', 0, self::LIMIT);
        return array_values(array_map(fn($c) => (object) ['title' => trim($c->firstname . ' ' . $c->lastname) ?: $c->email,
            'subtitle' => $c->email, 'url' => new moodle_url('/local/oriskill/contact.php', ['id' => $c->id])], $contacts));
    }

    /**
     * Orders of the trainer by number, or by the buyer's name or e-mail.
     *
     * @param \stdClass $creator Trainer.
     * @param string $q Words.
     * @param string $like LIKE pattern.
     * @return array
     */
    protected static function orders(\stdClass $creator, string $q, string $like): array {
        global $DB;

        [$orders, $params] = transaction_service::orders_sql($creator);
        $records = $DB->get_records_sql("SELECT o.id, o.ordernumber, o.total, o.currency, o.status, o.timecreated,
                                                u.firstname, u.lastname, u.email
                                           FROM {local_moderncommerce_orders} o
                                           JOIN {user} u ON u.id = o.userid
                                          WHERE o.id IN ($orders) AND (" . $DB->sql_like('o.ordernumber', ':q1', false) . ' OR ' .
            $DB->sql_like('u.email', ':q2', false) . ' OR ' . $DB->sql_like($DB->sql_concat('u.firstname', "' '", 'u.lastname'),
            ':q3', false) . ')
                                       ORDER BY o.timecreated DESC', $params + ['q1' => $like, 'q2' => $like, 'q3' => $like],
            0, self::LIMIT);
        return array_values(array_map(fn($o) => (object) ['title' => $o->ordernumber . ' · ' .
            \local_moderncommerce\services\pricing_service::format_price((float) $o->total, $o->currency),
            'subtitle' => trim($o->firstname . ' ' . $o->lastname) . ' · ' . userdate($o->timecreated,
                get_string('strftimedatefullshort', 'langconfig')),
            'url' => new moodle_url('/local/oriskill/sale.php', ['id' => $o->id])], $records));
    }

    /**
     * Invoices, credit notes and quotes by number or buyer.
     *
     * @param \stdClass $creator Trainer.
     * @param string $q Words.
     * @param string $like LIKE pattern.
     * @return array
     */
    protected static function invoices(\stdClass $creator, string $q, string $like): array {
        global $DB;

        $records = $DB->get_records_select('local_oriskill_invoices', 'creatorid = :creator AND (' .
            $DB->sql_like('number', ':q1', false) . ' OR ' . $DB->sql_like('data', ':q2', false) . ')',
            ['creator' => $creator->id, 'q1' => $like, 'q2' => '%"name":"%' . $DB->sql_like_escape($q) . '%'],
            'timecreated DESC', '*', 0, self::LIMIT);
        return array_values(array_map(function($d) {
            $data = json_decode($d->data, true) ?: [];
            return (object) ['title' => $d->number . ' · ' . \local_moderncommerce\services\pricing_service::format_price(
                    ($d->type === 'credit' ? -1 : 1) * (float) $d->total, $d->currency),
                'subtitle' => ($data['buyer']['name'] ?? '') . ' · ' . userdate($d->timecreated,
                    get_string('strftimedatefullshort', 'langconfig')),
                'url' => billing_service::url($d)];
        }, $records));
    }

    /**
     * Courses of the trainer by name.
     *
     * @param \stdClass $creator Trainer.
     * @param string $q Words.
     * @param string $like LIKE pattern.
     * @return array
     */
    protected static function courses(\stdClass $creator, string $q, string $like): array {
        global $DB;

        $records = $DB->get_records_select('course', 'category = :cat AND (' . $DB->sql_like('fullname', ':q1', false) .
            ' OR ' . $DB->sql_like('shortname', ':q2', false) . ')', ['cat' => $creator->categoryid, 'q1' => $like,
            'q2' => $like], 'fullname', 'id, fullname, shortname', 0, self::LIMIT);
        return array_values(array_map(fn($c) => (object) ['title' => format_string($c->fullname), 'subtitle' => $c->shortname,
            'url' => new moodle_url('/local/oriskill/learners.php', ['courseid' => $c->id])], $records));
    }

    /**
     * Order forms by title or address.
     *
     * @param \stdClass $creator Trainer.
     * @param string $q Words.
     * @param string $like LIKE pattern.
     * @return array
     */
    protected static function orderforms(\stdClass $creator, string $q, string $like): array {
        global $DB;

        $records = $DB->get_records_select('local_oriskill_orderforms', 'creatorid = :creator AND (' .
            $DB->sql_like('title', ':q1', false) . ' OR ' . $DB->sql_like('slug', ':q2', false) . ')',
            ['creator' => $creator->id, 'q1' => $like, 'q2' => $like], 'title', 'id, title, slug, status', 0, self::LIMIT);
        return array_values(array_map(fn($f) => (object) ['title' => format_string($f->title), 'subtitle' => $f->slug .
            ' · ' . get_string('orderform:' . $f->status, 'local_oriskill'),
            'url' => new moodle_url('/local/oriskill/orderform.php', ['id' => $f->id])], $records));
    }

    /**
     * Tickets and ideas by subject or learner (a coach: their learners only).
     *
     * @param \stdClass $creator Trainer.
     * @param string $q Words.
     * @param string $like LIKE pattern.
     * @return array
     */
    protected static function tickets(\stdClass $creator, string $q, string $like): array {
        $found = [];
        foreach (ticket_service::KINDS as $kind) {
            [$tickets] = ticket_service::list($creator, '', $q, 0, $kind);
            foreach (array_slice($tickets, 0, self::LIMIT) as $t) {
                $found[] = (object) ['title' => '#' . $t->id . ' · ' . $t->subject, 'subtitle' => fullname($t) . ' · ' .
                    get_string('tickets:status_' . $t->status, 'local_oriskill'),
                    'url' => new moodle_url('/local/oriskill/tickets.php', ['id' => $t->id])];
            }
        }
        return array_slice($found, 0, self::LIMIT);
    }

    /**
     * Search box of the header of the trainer space.
     *
     * @param string $value Words already searched.
     * @return string HTML
     */
    public static function box(string $value = ''): string {
        return \html_writer::tag('form',
            \html_writer::tag('label', get_string('search:label', 'local_oriskill'), ['for' => 'oriskill-search',
                'class' => 'visually-hidden']) .
            \html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'id' => 'oriskill-search', 'value' => $value,
                'class' => 'form-control form-control-sm', 'minlength' => self::MINLENGTH,
                'placeholder' => get_string('search:placeholder', 'local_oriskill')]),
            ['method' => 'get', 'action' => (new moodle_url('/local/oriskill/search.php'))->out(false), 'role' => 'search',
                'class' => 'oriskill-search me-2', 'style' => 'min-width: 14rem']);
    }
}
