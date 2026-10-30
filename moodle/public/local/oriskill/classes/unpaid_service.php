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

use local_moderncommerce\api\order_api;
use moodle_url;

/**
 * Unpaid instalments of a trainer's buyers, and what the trainer does about them.
 *
 * An unpaid case opens when SecureAndPay refuses an instalment or stops a schedule before its end. It stays open
 * until it is settled (SecureAndPay charges again, the learner pays the payment link sent as a reminder, or the
 * trainer records a payment received elsewhere) or the trainer abandons it. Closed cases are kept, with the reason
 * given, as the history of the purchase.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unpaid_service {

    /** States of a case. */
    const STATES = ['open', 'settled', 'abandoned'];

    // ----- Opening and closing by the payments ---------------------------------------------------------------

    /**
     * An instalment is missing (refused, or the schedule was stopped): open a case, or update the open one.
     *
     * @param \stdClass $record Instalment purchase.
     * @param string $cause failed or stopped.
     */
    public static function open(\stdClass $record, string $cause): void {
        global $DB;

        if ($case = self::open_case((int) $record->id)) {
            // A stopped schedule outweighs a refused instalment: everything left is now due.
            if ($cause === 'stopped' && $case->cause !== 'stopped') {
                $DB->update_record('local_oriskill_unpaid', (object) ['id' => $case->id, 'cause' => 'stopped',
                    'amount' => self::amount_due($record, 'stopped'), 'timemodified' => time()]);
            }
            return;
        }
        $creator = seller_service::owner_of_course((int) $record->courseid);
        $now = time();
        $DB->insert_record('local_oriskill_unpaid', (object) [
            'instalmentid' => $record->id,
            'creatorid' => $creator ? $creator->id : 0,
            'userid' => $record->userid,
            'courseid' => $record->courseid,
            'cause' => $cause,
            'state' => 'open',
            'amount' => self::amount_due($record, $cause),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * SecureAndPay charged an instalment again: the open case of the purchase is settled.
     *
     * @param \stdClass $record Instalment purchase.
     */
    public static function charged(\stdClass $record): void {
        if ($case = self::open_case((int) $record->id)) {
            self::close($case, 'settled', 'auto', '');
        }
    }

    /**
     * A payment link sent as a reminder was paid: the missing instalments are paid, the access comes back.
     *
     * @param int $orderid Paid order.
     * @return bool Whether the order paid a case.
     */
    public static function order_paid(int $orderid): bool {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        $notes = $order ? (json_decode((string) $order->notes, true) ?: []) : [];
        if (empty($notes['unpaid'])) {
            return false;
        }
        $case = $DB->get_record('local_oriskill_unpaid', ['id' => (int) $notes['unpaid']]);
        if ($case && $case->state === 'open') {
            self::settle($case, (int) ($notes['catchup'] ?? 0), 'link', '', (float) $order->total);
        }
        return true;
    }

    // ----- What is due -------------------------------------------------------------------------------------

    /**
     * Instalments missing on a purchase: all those left once the schedule is stopped, otherwise those due by now
     * and not paid (at least one).
     *
     * @param \stdClass $record Instalment purchase.
     * @param string $cause failed or stopped.
     * @param int|null $now Time to compare with, for tests.
     * @return int
     */
    public static function missing(\stdClass $record, string $cause, ?int $now = null): int {
        $now = $now ?? time();
        $paid = (int) $record->paid + (int) $record->extrapaid;
        $left = $record->count ? max(0, (int) $record->count - $paid) : PHP_INT_MAX;
        if ($cause === 'stopped' && $record->count) {
            return $left;
        }
        // Monthly from the first payment, due on the same day of the month.
        $due = 1;
        while ((!$record->count || $due < (int) $record->count)
                && strtotime('+' . $due . ' month', (int) $record->timecreated) <= $now) {
            $due++;
        }
        return min($left, max(1, $due - $paid));
    }

    /**
     * Amount of the missing instalments (the first one is paid at purchase, so all are at the usual amount).
     *
     * @param \stdClass $record Instalment purchase.
     * @param string $cause failed or stopped.
     * @return float
     */
    public static function amount_due(\stdClass $record, string $cause): float {
        return round(self::missing($record, $cause) * (float) $record->amount, 2);
    }

    // ----- The trainer's list ------------------------------------------------------------------------------

    /**
     * A trainer's unpaid cases, with the purchase, learner and product.
     *
     * @param \stdClass $creator Trainer.
     * @param array $filters state ('' for all), courseid (0 for all), search (name or e-mail), sort (asc or desc).
     * @return array
     */
    public static function trainer_cases(\stdClass $creator, array $filters = []): array {
        global $DB;

        $where = 'u.creatorid = :creatorid';
        $params = ['creatorid' => $creator->id];
        if (!empty($filters['state'])) {
            $where .= ' AND u.state = :state';
            $params['state'] = $filters['state'];
        }
        if (!empty($filters['courseid'])) {
            $where .= ' AND u.courseid = :courseid';
            $params['courseid'] = (int) $filters['courseid'];
        }
        if (trim($filters['search'] ?? '') !== '') {
            $fullname = $DB->sql_concat('us.firstname', "' '", 'us.lastname');
            $where .= ' AND (' . $DB->sql_like($fullname, ':s1', false, false) . ' OR '
                . $DB->sql_like('us.email', ':s2', false, false) . ')';
            $params['s1'] = $params['s2'] = '%' . $DB->sql_like_escape(trim($filters['search'])) . '%';
        }
        $sort = ($filters['sort'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $cases = $DB->get_records_sql(
            "SELECT u.*, i.orderid, i.count, i.amount AS instalment, i.firstamount, i.paid, i.extrapaid, i.status,
                    i.timecreated AS purchased, o.ordernumber, o.currency, o.notes, c.fullname AS coursename
               FROM {local_oriskill_unpaid} u
               JOIN {local_oriskill_instalments} i ON i.id = u.instalmentid
               JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
               JOIN {course} c ON c.id = u.courseid
               JOIN {user} us ON us.id = u.userid
              WHERE $where
           ORDER BY u.timecreated $sort, u.id $sort", $params);
        // The access belongs to the purchase: only its latest case shows it.
        $latest = $DB->get_records_sql_menu("SELECT instalmentid, MAX(id) FROM {local_oriskill_unpaid}
                                              WHERE creatorid = ? GROUP BY instalmentid", [$creator->id]);
        foreach ($cases as $case) {
            $case->user = \core_user::get_user($case->userid);
            $notes = json_decode((string) $case->notes, true) ?: [];
            $offer = !empty($notes['offer'])
                ? $DB->get_field('local_oriskill_formoffers', 'label', ['id' => $notes['offer']]) : false;
            $case->label = $offer !== false ? format_string($offer) : format_string($case->coursename);
            if ($case->state === 'open') {
                // What is owed changes as months go by.
                $case->amount = self::amount_due(self::record_of($case), $case->cause);
            }
            $case->latest = (int) ($latest[$case->instalmentid] ?? 0) === (int) $case->id;
            $case->suspended = $case->latest && $case->status === 'suspended';
        }
        return $cases;
    }

    /**
     * Unpaid case of one of a trainer's buyers, or an exception.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Case.
     * @return \stdClass
     */
    public static function require_case(\stdClass $creator, int $id): \stdClass {
        global $DB;

        return $DB->get_record('local_oriskill_unpaid', ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
    }

    // ----- The trainer's actions ---------------------------------------------------------------------------

    /**
     * E-mail the learner a link to pay what is missing.
     *
     * @param \stdClass $case Open case.
     */
    public static function remind(\stdClass $case): void {
        global $DB;

        if ($case->state !== 'open') {
            throw new \moodle_exception('error:unpaidclosed', 'local_oriskill');
        }
        $record = $DB->get_record('local_oriskill_instalments', ['id' => $case->instalmentid], '*', MUST_EXIST);
        $learner = $DB->get_record('user', ['id' => $case->userid, 'deleted' => 0], '*', MUST_EXIST);
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $case->creatorid], '*', MUST_EXIST);
        $currency = (string) $DB->get_field('local_moderncommerce_orders', 'currency', ['id' => $record->orderid]);
        $amount = self::amount_due($record, $case->cause);
        $a = (object) [
            'learner' => fullname($learner),
            'course' => format_string((string) $DB->get_field('course', 'fullname', ['id' => $case->courseid])),
            'trainer' => format_string($creator->displayname),
            'amount' => \local_moderncommerce\services\pricing_service::format_price($amount, $currency),
            'missing' => self::missing($record, $case->cause),
            'url' => (new moodle_url('/local/oriskill/regularize.php', ['id' => $case->id]))->out(false),
        ];
        email_log::for_creator((int) $creator->id, 'transactional', fn() => email_to_user($learner,
            \core_user::get_noreply_user(), get_string('unpaidmail:subject', 'local_oriskill', $a),
            get_string('unpaidmail:body', 'local_oriskill', $a)));
        $DB->update_record('local_oriskill_unpaid', (object) ['id' => $case->id, 'amount' => $amount,
            'reminders' => (int) $case->reminders + 1, 'lastreminder' => time(), 'timemodified' => time()]);
        log_service::add($creator, 'unpaid_reminded', ['amount' => $a->amount], (int) $case->courseid, (int) $case->userid);
    }

    /**
     * The trainer closes a case: paid elsewhere (the missing instalments count as paid) or abandoned.
     *
     * @param \stdClass $case Open case.
     * @param string $state settled or abandoned.
     * @param string $reason Why, kept in the history.
     */
    public static function classify(\stdClass $case, string $state, string $reason): void {
        global $DB;

        if ($case->state !== 'open' || !in_array($state, ['settled', 'abandoned'], true)) {
            throw new \moodle_exception('error:unpaidclosed', 'local_oriskill');
        }
        $reason = trim($reason);
        if ($state === 'settled') {
            $record = $DB->get_record('local_oriskill_instalments', ['id' => $case->instalmentid], '*', MUST_EXIST);
            self::settle($case, self::missing($record, $case->cause), 'manual', $reason,
                self::amount_due($record, $case->cause));
        } else {
            self::close($case, 'abandoned', 'manual', $reason);
            // Given up: no automatic suspension any more, the trainer decides on the access.
            $DB->set_field('local_oriskill_instalments', 'latesince', 0, ['id' => $case->instalmentid]);
        }
        if ($creator = $DB->get_record('local_oriskill_creators', ['id' => $case->creatorid])) {
            log_service::add($creator, 'unpaid_' . $state, ['reason' => $reason], (int) $case->courseid,
                (int) $case->userid);
        }
    }

    /**
     * The trainer suspends or restores the learner's access from the list. Restoring it stops the automatic
     * suspension of this case: the trainer has decided.
     *
     * @param \stdClass $case Case.
     * @param bool $suspend Suspend (true) or restore (false).
     */
    public static function set_access(\stdClass $case, bool $suspend): void {
        global $DB;

        $record = $DB->get_record('local_oriskill_instalments', ['id' => $case->instalmentid], '*', MUST_EXIST);
        instalment_service::set_access($record, $suspend);
        if ($suspend) {
            $status = in_array($record->status, ['completed', 'cancelled'], true) ? $record->status : 'suspended';
        } else {
            $status = $record->status !== 'suspended' ? $record->status
                : ($case->state !== 'open' ? 'active' : ($case->cause === 'stopped' ? 'stopped' : 'late'));
        }
        $DB->update_record('local_oriskill_instalments', (object) ['id' => $record->id, 'status' => $status,
            'latesince' => $suspend ? $record->latesince : 0, 'timemodified' => time()]);
        if ($creator = $DB->get_record('local_oriskill_creators', ['id' => $case->creatorid])) {
            log_service::add($creator, $suspend ? 'learner_suspend' : 'learner_reactivate', [], (int) $case->courseid,
                (int) $case->userid);
        }
    }

    // ----- The learner pays --------------------------------------------------------------------------------

    /**
     * Order for what is missing on a case, and where the learner pays it (the trainer's payment methods).
     *
     * @param \stdClass $case Open case.
     * @return moodle_url
     */
    public static function payment_url(\stdClass $case): moodle_url {
        global $DB;

        if ($case->state !== 'open') {
            throw new \moodle_exception('error:unpaidclosed', 'local_oriskill');
        }
        $record = $DB->get_record('local_oriskill_instalments', ['id' => $case->instalmentid], '*', MUST_EXIST);
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $case->creatorid], '*', MUST_EXIST);
        $missing = self::missing($record, $case->cause);
        $amount = self::amount_due($record, $case->cause);
        // The order of an earlier click is reused while it is still to be paid for the same amount.
        $order = $case->payorderid ? $DB->get_record('local_moderncommerce_orders', ['id' => $case->payorderid]) : null;
        if (!$order || $order->status !== 'pending' || (float) $order->total !== $amount) {
            $order = order_api::create_course_order((int) $case->userid, (int) $case->courseid, $amount, [
                'unpaid' => (int) $case->id, 'catchup' => $missing, 'allowowned' => true,
                'returnpath' => '/local/oriskill/regularize.php']);
            $DB->set_field('local_oriskill_unpaid', 'payorderid', $order->id, ['id' => $case->id]);
        }
        return orderform_service::payment_url($creator, $order);
    }

    // ----- Internals ---------------------------------------------------------------------------------------

    /**
     * Open case of a purchase, if any.
     *
     * @param int $instalmentid Instalment purchase.
     * @return \stdClass|null
     */
    private static function open_case(int $instalmentid): ?\stdClass {
        global $DB;

        return $DB->get_record('local_oriskill_unpaid', ['instalmentid' => $instalmentid, 'state' => 'open'],
            '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Missing instalments paid outside SecureAndPay's schedule: count them, bring the access back, close the case.
     *
     * @param \stdClass $case Open case.
     * @param int $instalments Instalments paid.
     * @param string $how link or manual.
     * @param string $reason Trainer's reason.
     * @param float $amount Amount paid.
     */
    private static function settle(\stdClass $case, int $instalments, string $how, string $reason, float $amount): void {
        global $DB;

        $record = $DB->get_record('local_oriskill_instalments', ['id' => $case->instalmentid], '*', MUST_EXIST);
        $extrapaid = (int) $record->extrapaid + max(0, $instalments);
        $status = $record->count && (int) $record->paid + $extrapaid >= (int) $record->count ? 'completed'
            : ($record->status === 'cancelled' ? 'cancelled' : 'active');
        $DB->update_record('local_oriskill_instalments', (object) ['id' => $record->id, 'extrapaid' => $extrapaid,
            'status' => $status, 'latesince' => 0, 'timemodified' => time()]);
        $DB->set_field('local_oriskill_unpaid', 'amount', $amount, ['id' => $case->id]);
        self::close($case, 'settled', $how, $reason);
        if ($record->status === 'suspended') {
            instalment_service::set_access($record, false);
            instalment_service::restored($record);
        }
    }

    /**
     * Close a case.
     *
     * @param \stdClass $case Open case.
     * @param string $state settled or abandoned.
     * @param string $how auto, link or manual.
     * @param string $reason Trainer's reason.
     */
    private static function close(\stdClass $case, string $state, string $how, string $reason): void {
        global $DB, $USER;

        $DB->update_record('local_oriskill_unpaid', (object) ['id' => $case->id, 'state' => $state, 'how' => $how,
            'reason' => $reason, 'closedby' => $how === 'manual' ? (int) ($USER->id ?? 0) : 0,
            'timeclosed' => time(), 'timemodified' => time()]);
    }

    /**
     * Instalment purchase fields of a case read with trainer_cases().
     *
     * @param \stdClass $case Case with the purchase's fields.
     * @return \stdClass
     */
    private static function record_of(\stdClass $case): \stdClass {
        return (object) ['count' => $case->count, 'amount' => $case->instalment, 'paid' => $case->paid,
            'extrapaid' => $case->extrapaid, 'timecreated' => $case->purchased];
    }
}
