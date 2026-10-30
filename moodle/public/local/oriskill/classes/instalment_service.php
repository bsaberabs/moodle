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
use local_moderncommerce\services\price_resolver;
use local_moderncommerce\services\pricing_service;
use local_secureandpay\gateway;
use moodle_url;

/**
 * Trainer courses paid in monthly instalments through the trainer's SecureAndPay account.
 *
 * The first instalment is charged at purchase and enrols the buyer; SecureAndPay charges the next ones and
 * reports each of them. A refused instalment leaves a grace period, after which the access is suspended until
 * the next instalment is received. Buyers are put in a group named after how they paid.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instalment_service {

    /** Numbers of instalments a trainer can offer. */
    const COUNTS = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];

    /** Days between a refused instalment and the suspension of the access. */
    const GRACE_DAYS = 7;

    /**
     * Instalment offer of a course: number, amount of each and total, or null when the course is paid at once.
     *
     * Each instalment is the total divided by their number, rounded up to the cent; the total charged is
     * that amount times the number of instalments.
     *
     * @param int $courseid Course.
     * @return \stdClass|null {count, amount, total}
     */
    public static function offer(int $courseid): ?\stdClass {
        global $DB;

        $offer = $DB->get_record('local_oriskill_instoffers', ['courseid' => $courseid]);
        if (!$offer || !in_array((int) $offer->count, self::COUNTS, true) || (float) $offer->total <= 0) {
            return null;
        }
        $count = (int) $offer->count;
        $amount = ceil(round((float) $offer->total * 100 / $count, 4)) / 100;
        return (object) ['count' => $count, 'amount' => $amount, 'total' => round($amount * $count, 2)];
    }

    /**
     * Offer (or stop offering) a course in instalments.
     *
     * @param \stdClass $creator Trainer owning the course.
     * @param int $courseid Course.
     * @param int $count Number of instalments, 0 to stop.
     * @param float $total Total price in instalments.
     */
    public static function save_offer(\stdClass $creator, int $courseid, int $count, float $total): void {
        global $DB;

        learning_service::require_own_course($creator, $courseid);
        $existing = $DB->get_record('local_oriskill_instoffers', ['courseid' => $courseid]);
        if (!$count) {
            if ($existing) {
                $DB->delete_records('local_oriskill_instoffers', ['id' => $existing->id]);
            }
            return;
        }
        if (!in_array($count, self::COUNTS, true) || $total <= 0) {
            throw new \moodle_exception('error:instalments', 'local_oriskill');
        }
        $record = (object) ['courseid' => $courseid, 'count' => $count, 'total' => round($total, 2), 'timemodified' => time()];
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_oriskill_instoffers', $record);
        } else {
            $DB->insert_record('local_oriskill_instoffers', $record);
        }
    }

    /**
     * Whether a trainer can sell in instalments: SecureAndPay is the only gateway that charges them on its own.
     *
     * @param \stdClass $creator Trainer.
     * @return bool
     */
    public static function can_offer(\stdClass $creator): bool {
        return seller_service::merchant($creator) !== null;
    }

    /**
     * Offer of a course as the buyer sees it, or null when it cannot be bought in instalments right now.
     *
     * @param int $courseid Course.
     * @return \stdClass|null {count, amount, total, displayamount, displaytotal, url, label}
     */
    public static function buyable_offer(int $courseid): ?\stdClass {
        $creator = seller_service::owner_of_course($courseid);
        $offer = self::offer($courseid);
        if (!$creator || !$offer || $creator->status !== 'active' || !self::can_offer($creator)
                || seller_service::selling_blocker($creator) || !price_resolver::resolve_for_course($courseid, 1, true)) {
            return null;
        }
        $currency = pricing_service::get_currency_config()->currency;
        $offer->displayamount = pricing_service::format_price($offer->amount, $currency);
        $offer->displaytotal = pricing_service::format_price($offer->total, $currency);
        $offer->url = (new moodle_url('/local/oriskill/instalments.php', ['courseid' => $courseid]))->out(false);
        $offer->label = get_string('instalments:orshort', 'local_oriskill', $offer);
        return $offer;
    }

    /**
     * Create the order of a purchase in instalments.
     *
     * @param int $userid Buyer.
     * @param int $courseid Course.
     * @return array [order, SecureAndPay orderID]: the buyer then goes to /local/secureandpay/pay.php.
     */
    public static function create_order(int $userid, int $courseid): array {
        $offer = self::buyable_offer($courseid);
        if (!$offer) {
            throw new \moodle_exception('error:noinstalments', 'local_oriskill');
        }
        $order = order_api::create_course_order($userid, $courseid, $offer->total,
            ['instalments' => ['count' => $offer->count, 'amount' => $offer->amount]]);
        return [$order, gateway::start_instalments($order)];
    }

    // ----- Payments reported by SecureAndPay ---------------------------------------------------------------

    /**
     * An instalment was received (the first one at purchase): the purchase is up to date again.
     *
     * @param \local_secureandpay\event\instalment_paid $event Event.
     */
    public static function instalment_paid(\local_secureandpay\event\instalment_paid $event): void {
        global $DB;

        if (!$record = self::record_for_order((int) $event->objectid)) {
            return;
        }
        $wassuspended = $record->status === 'suspended';
        $paid = (int) $event->other['paid'];
        // A subscription (count 0) never completes: it runs until cancelled. Instalments caught up through a
        // payment link (extrapaid) count too.
        $DB->update_record('local_oriskill_instalments', (object) [
            'id' => $record->id,
            'paid' => $paid,
            'status' => $record->count && $paid + (int) $record->extrapaid >= (int) $record->count ? 'completed'
                : ($record->status === 'cancelled' ? 'cancelled' : 'active'),
            'latesince' => 0,
            'timemodified' => time(),
        ]);
        if (!$record->count && $paid > 1) {
            // Each further month of a subscription gets its own invoice.
            billing_service::issue_invoice((int) $record->orderid, $paid);
        }
        unpaid_service::charged($record);
        if ($wassuspended) {
            self::set_access($record, false);
            self::restored($record);
        }
    }

    /**
     * The access of a purchase suspended for non-payment was restored after payment: tell the learner and the trainer.
     *
     * @param \stdClass $record Instalment purchase.
     */
    public static function restored(\stdClass $record): void {
        self::notify($record, 'restored');
        if ($creator = seller_service::owner_of_course((int) $record->courseid)) {
            log_service::add($creator, 'learner_paidrestored', [], (int) $record->courseid, (int) $record->userid);
        }
    }

    /**
     * SecureAndPay could not charge an instalment: the grace period starts.
     *
     * @param \local_secureandpay\event\instalment_failed $event Event.
     */
    public static function instalment_failed(\local_secureandpay\event\instalment_failed $event): void {
        self::mark_unpaid((int) $event->objectid, 'late', 'failed');
    }

    /**
     * SecureAndPay stopped the schedule: if instalments are missing, the grace period starts.
     *
     * @param \local_secureandpay\event\instalments_stopped $event Event.
     */
    public static function instalments_stopped(\local_secureandpay\event\instalments_stopped $event): void {
        global $DB;

        $record = $DB->get_record('local_oriskill_instalments', ['orderid' => $event->objectid]);
        if ($record && !$record->count) {
            // A subscription stopped at SecureAndPay: cancelled, access kept for the month paid.
            self::end_subscription($record, false);
        } else if ((int) $event->other['paid'] < (int) $event->other['count']) {
            self::mark_unpaid((int) $event->objectid, 'stopped', 'stopped');
        }
    }

    /**
     * End of the period paid so far (monthly from the first payment).
     *
     * @param \stdClass $record Instalment purchase or subscription.
     * @return int
     */
    public static function paid_until(\stdClass $record): int {
        return strtotime('+' . ((int) $record->paid + (int) ($record->extrapaid ?? 0)) . ' month', (int) $record->timecreated);
    }

    /**
     * Subscriptions of a learner (sold by trainers), newest first.
     *
     * @param int $userid Learner.
     * @return array
     */
    public static function user_subscriptions(int $userid): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT i.*, o.ordernumber, o.currency, o.notes
               FROM {local_oriskill_instalments} i
               JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
              WHERE i.userid = ? AND i.count = 0
           ORDER BY i.timecreated DESC", [$userid]);
        foreach ($records as $record) {
            $notes = json_decode((string) $record->notes, true) ?: [];
            $offer = !empty($notes['offer']) ? $DB->get_field('local_oriskill_formoffers', 'label', ['id' => $notes['offer']]) : false;
            $record->label = $offer !== false ? format_string($offer)
                : format_string((string) $DB->get_field('course', 'fullname', ['id' => $record->courseid]));
            $record->paiduntil = self::paid_until($record);
        }
        return $records;
    }

    /**
     * Cancel a subscription: SecureAndPay stops charging, the access stays open until the end of the month paid.
     *
     * @param \stdClass $record Subscription (instalment record with count 0).
     * @param callable|null $send See gateway::stop_schedule() (tests).
     * @return bool Whether SecureAndPay confirmed (otherwise the trainer is asked to stop it in its back-office).
     */
    public static function cancel_subscription(\stdClass $record, ?callable $send = null): bool {
        global $DB;

        if ($record->count || in_array($record->status, ['cancelled'], true)) {
            throw new \moodle_exception('error:notsubscription', 'local_oriskill');
        }
        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $record->orderid], '*', MUST_EXIST);
        $notes = json_decode((string) $order->notes, true) ?: [];
        [$stopped, $answer] = gateway::stop_schedule($order, (string) ($notes['instalments']['secureandpay_reference'] ?? ''),
            $send);
        self::end_subscription($record, !$stopped, $answer);
        return $stopped;
    }

    /**
     * Mark a subscription cancelled and close the access at the end of the month paid.
     *
     * @param \stdClass $record Subscription.
     * @param bool $warntrainer SecureAndPay did not confirm: tell the trainer to stop it themselves.
     * @param string $answer SecureAndPay's answer.
     */
    private static function end_subscription(\stdClass $record, bool $warntrainer = false, string $answer = ''): void {
        global $DB;

        if ($record->status === 'cancelled') {
            return;
        }
        $until = self::paid_until($record);
        $DB->update_record('local_oriskill_instalments', (object) ['id' => $record->id, 'status' => 'cancelled',
            'latesince' => 0, 'timemodified' => time()]);
        $courseids = $DB->get_fieldset_select('local_moderncommerce_order_items', 'courseid',
            'orderid = ? AND courseid IS NOT NULL', [$record->orderid]) ?: [$record->courseid];
        foreach (array_unique(array_map('intval', $courseids)) as $courseid) {
            if ($course = $DB->get_record('course', ['id' => $courseid])) {
                learning_service::set_timeend($course, (int) $record->userid, $until);
                if ($record->status === 'suspended') {
                    learning_service::set_suspended($course, (int) $record->userid, false);
                }
            }
        }
        $record->status = 'cancelled';
        $record->latesince = $until;
        self::notify($record, $warntrainer ? 'cancelledmanual' : 'cancelled');
        if ($creator = seller_service::owner_of_course((int) $record->courseid)) {
            log_service::add($creator, 'subscription_cancelled', ['answer' => $answer], (int) $record->courseid,
                (int) $record->userid);
        }
    }

    /**
     * Suspend the access of buyers whose grace period is over. Run by a scheduled task.
     *
     * @param int|null $now Time to compare with, for tests.
     * @return int Accesses suspended.
     */
    public static function suspend_overdue(?int $now = null): int {
        global $DB;

        $now = $now ?? time();
        $records = $DB->get_records_select('local_oriskill_instalments',
            "status IN ('late', 'stopped') AND latesince > 0 AND latesince <= ?", [$now - self::GRACE_DAYS * DAYSECS]);
        foreach ($records as $record) {
            self::set_access($record, true);
            $DB->update_record('local_oriskill_instalments',
                (object) ['id' => $record->id, 'status' => 'suspended', 'timemodified' => $now]);
            self::notify($record, 'suspended');
            webhook_service::order_event((int) $record->orderid, 'access.suspended');
            if ($creator = seller_service::owner_of_course((int) $record->courseid)) {
                log_service::add($creator, 'learner_unpaidsuspended', [], (int) $record->courseid, (int) $record->userid);
            }
        }
        return count($records);
    }

    /**
     * Suspend or restore the learner's access to every course of the purchase (an order form offer may open several).
     *
     * @param \stdClass $record Instalment purchase.
     * @param bool $suspend Suspend (true) or restore (false).
     */
    public static function set_access(\stdClass $record, bool $suspend): void {
        global $DB;

        $courseids = $DB->get_fieldset_select('local_moderncommerce_order_items', 'courseid',
            'orderid = ? AND courseid IS NOT NULL', [$record->orderid]) ?: [$record->courseid];
        foreach (array_unique(array_map('intval', $courseids)) as $courseid) {
            if ($course = $DB->get_record('course', ['id' => $courseid])) {
                learning_service::set_suspended($course, (int) $record->userid, $suspend);
            }
        }
    }

    /**
     * Instalment purchases of a course, by learner.
     *
     * @param int $courseid Course.
     * @return array userid => record (the latest purchase)
     */
    public static function course_records(int $courseid): array {
        global $DB;

        $result = [];
        foreach ($DB->get_records('local_oriskill_instalments', ['courseid' => $courseid], 'timecreated ASC') as $record) {
            $result[(int) $record->userid] = $record;
        }
        return $result;
    }

    /**
     * A trainer's purchases in instalments, newest first, with what they bring and when the next instalment is due.
     *
     * @param \stdClass $creator Trainer.
     * @param string $status Only this status ('' for all).
     * @return array of records with: user, coursename (or offer), collected, remaining, nextdue (0 when none is due)
     */
    public static function trainer_records(\stdClass $creator, string $status = ''): array {
        global $DB;

        $courseids = array_keys(seller_service::priced_courses($creator));
        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $where = "i.courseid $insql";
        if ($status !== '') {
            $where .= ' AND i.status = :status';
            $params['status'] = $status;
        }
        $records = $DB->get_records_sql(
            "SELECT i.*, o.notes, o.ordernumber, o.currency, c.fullname AS coursename
               FROM {local_oriskill_instalments} i
               JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
               JOIN {course} c ON c.id = i.courseid
              WHERE $where
           ORDER BY i.timecreated DESC, i.id DESC", $params);
        foreach ($records as $record) {
            $record->user = \core_user::get_user($record->userid);
            $notes = json_decode((string) $record->notes, true) ?: [];
            // An order form offer can open several courses: show the offer's name.
            $offer = !empty($notes['offer'])
                ? $DB->get_field('local_oriskill_formoffers', 'label', ['id' => $notes['offer']]) : false;
            $record->label = $offer !== false ? format_string($offer) : format_string($record->coursename);
            // The first instalment can be a deposit of its own (firstamount), the others are equal.
            // Instalments caught up through a payment link (extrapaid) are at the usual amount.
            $first = (float) $record->firstamount > 0 ? (float) $record->firstamount : (float) $record->amount;
            $paid = (int) $record->paid + (int) $record->extrapaid;
            $record->collected = $record->paid ? round($first + ($paid - 1) * (float) $record->amount, 2) : 0.0;
            $record->remaining = round(max(0, (int) $record->count - max(1, $paid)) * (float) $record->amount
                + ($record->paid ? 0 : $first), 2);
            // Monthly from the first payment; once completed or stopped, nothing more is due.
            $record->nextdue = in_array($record->status, ['completed', 'stopped', 'cancelled'], true)
                || ($record->count && $paid >= $record->count) ? 0 : self::paid_until($record);
            if (!$record->count) {
                $record->remaining = 0.0;
            }
        }
        return $records;
    }

    /**
     * Badge showing where a learner's instalments stand.
     *
     * @param \stdClass $record Instalment purchase.
     * @return string HTML
     */
    public static function status_badge(\stdClass $record): string {
        $class = ['completed' => 'is-good', 'active' => '', 'late' => 'is-warn', 'stopped' => 'is-warn',
            'suspended' => 'is-warn', 'cancelled' => ''][$record->status] ?? '';
        // Subscriptions have their own wording (no number of instalments).
        $key = (!$record->count ? 'substatus:' : 'instalmentstatus:') . $record->status;
        $a = clone $record;
        $a->until = userdate(self::paid_until($record), get_string('strftimedate', 'langconfig'));
        return \html_writer::span(get_string($key, 'local_oriskill', $a), trim('oriskill-badge ' . $class));
    }

    /**
     * Paid in instalments: tracking record of a paid order (created at its first instalment), or null.
     *
     * @param int $orderid Order.
     * @return \stdClass|null
     */
    private static function record_for_order(int $orderid): ?\stdClass {
        global $DB;

        if ($record = $DB->get_record('local_oriskill_instalments', ['orderid' => $orderid])) {
            return $record;
        }
        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        $plan = $order ? gateway::instalment_plan($order) : null;
        $courseid = $plan ? (int) $DB->get_field_select('local_moderncommerce_order_items', 'courseid',
            'orderid = ? AND courseid IS NOT NULL', [$orderid], IGNORE_MULTIPLE) : 0;
        if (!$courseid) {
            return null;
        }
        $now = time();
        $record = (object) ['orderid' => $orderid, 'userid' => (int) $order->userid, 'courseid' => $courseid,
            'count' => $plan['count'], 'amount' => $plan['amount'],
            'firstamount' => $plan['first'] != $plan['amount'] ? $plan['first'] : 0, 'paid' => 0, 'extrapaid' => 0,
            'status' => 'active', 'latesince' => 0,
            'timecreated' => $now, 'timemodified' => $now];
        $record->id = $DB->insert_record('local_oriskill_instalments', $record);
        return $record;
    }

    /**
     * An instalment is missing: start the grace period (once) and tell the learner and the trainer.
     *
     * @param int $orderid Order.
     * @param string $status late or stopped.
     * @param string $message Message key part.
     */
    private static function mark_unpaid(int $orderid, string $status, string $message): void {
        global $DB;

        $record = self::record_for_order($orderid);
        if (!$record || $record->status === 'completed') {
            return;
        }
        unpaid_service::open($record, $message);
        if ($record->status === 'suspended') {
            return;
        }
        $record->latesince = (int) $record->latesince ?: time();
        $DB->update_record('local_oriskill_instalments', (object) ['id' => $record->id, 'status' => $status,
            'latesince' => $record->latesince, 'timemodified' => time()]);
        self::notify($record, $message);
    }

    /**
     * E-mail the learner and the trainer about the payments of a purchase.
     *
     * @param \stdClass $record Instalment purchase.
     * @param string $what failed, stopped, suspended or restored.
     */
    private static function notify(\stdClass $record, string $what): void {
        global $DB;

        $learner = $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0]);
        $course = $DB->get_record('course', ['id' => $record->courseid]);
        $creator = $course ? seller_service::owner_of_course((int) $course->id) : null;
        if (!$learner || !$course || !$creator) {
            return;
        }
        $a = (object) [
            'learner' => fullname($learner),
            'course' => format_string($course->fullname),
            'trainer' => format_string($creator->displayname),
            'paid' => (int) $record->paid,
            'count' => (int) $record->count,
            'days' => self::GRACE_DAYS,
            'deadline' => userdate((int) $record->latesince + ($record->status === 'cancelled' ? 0 : self::GRACE_DAYS * DAYSECS),
                get_string('strftimedate')),
            'url' => (new moodle_url('/local/oriskill/learner.php', ['courseid' => $course->id, 'userid' => $learner->id]))
                ->out(false),
        ];
        $from = \core_user::get_noreply_user();
        email_to_user($learner, $from, get_string("instalmentmail:{$what}_subject", 'local_oriskill', $a),
            get_string("instalmentmail:{$what}_learner", 'local_oriskill', $a));
        if ($trainer = $DB->get_record('user', ['id' => $creator->userid, 'deleted' => 0])) {
            email_to_user($trainer, $from, get_string("instalmentmail:{$what}_subject", 'local_oriskill', $a),
                get_string("instalmentmail:{$what}_trainer", 'local_oriskill', $a));
        }
    }

    // ----- Groups by payment ---------------------------------------------------------------------------------

    /**
     * A trainer course was paid (at once, or its first instalment): put the buyer in the group of how they paid.
     *
     * @param int $orderid Paid order.
     */
    public static function group_buyers(int $orderid): void {
        global $CFG, $DB;
        // Run from payment notifications, where the groups library is not loaded.
        require_once($CFG->dirroot . '/group/lib.php');

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        if (!$order) {
            return;
        }
        $plan = gateway::instalment_plan($order);
        $name = !$plan ? get_string('group:paidatonce', 'local_oriskill')
            : ($plan['subscription'] ? get_string('group:subscription', 'local_oriskill')
                : get_string('group:instalments', 'local_oriskill', $plan['count']));
        $courseids = $DB->get_fieldset_select('local_moderncommerce_order_items', 'courseid',
            'orderid = ? AND courseid IS NOT NULL', [$orderid]);
        foreach (array_unique(array_map('intval', $courseids)) as $courseid) {
            if (!seller_service::owner_of_course($courseid) || !is_enrolled(\context_course::instance($courseid), $order->userid)) {
                continue;
            }
            $group = groups_get_group_by_name($courseid, $name);
            $groupid = $group ?: groups_create_group((object) ['courseid' => $courseid, 'name' => $name]);
            if (!groups_is_member($groupid, $order->userid)) {
                groups_add_member($groupid, $order->userid);
            }
        }
    }
}
