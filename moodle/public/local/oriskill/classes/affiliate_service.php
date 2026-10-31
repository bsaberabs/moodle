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
 * Affiliation: any user can recommend courses with a link carrying their code (?ref=code, on any page).
 *
 * The code is kept in a cookie (last click wins) and read when the visitor places an order. Once the order is
 * paid, each line earns a commission at the rate of its seller: the trainer's own rate for their courses (the
 * trainer pays it, since the money is in their account), the platform rate for Oriskill's. A commission stays
 * pending during the refund window, then becomes due (approved) unless the order was refunded or cancelled.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class affiliate_service {

    /** @var string Cookie holding the referral code. */
    const COOKIE = 'oriskill_ref';

    /** @var string Cookie holding the time of the click (tracking time of the campaigns). */
    const COOKIE_TIME = 'oriskill_ref_time';

    /**
     * Whether the affiliate programme is open.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (bool) get_config('local_oriskill', 'affiliateenabled');
    }

    /**
     * Affiliate record of a user, if they joined.
     *
     * @param int $userid User.
     * @return \stdClass|null
     */
    public static function get_for_user(int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('local_oriskill_affiliates', ['userid' => $userid]) ?: null;
    }

    /**
     * Join the programme (idempotent).
     *
     * @param int $userid User.
     * @return \stdClass Affiliate.
     */
    public static function join(int $userid): \stdClass {
        global $DB;

        if ($affiliate = self::get_for_user($userid)) {
            return $affiliate;
        }
        do {
            $code = strtolower(random_string(8));
        } while ($DB->record_exists('local_oriskill_affiliates', ['code' => $code]));
        $now = time();
        // Brought in by another affiliate's link: that one is their sponsor (second level of the campaigns).
        $sponsor = !empty($_COOKIE[self::COOKIE]) ? $DB->get_record('local_oriskill_affiliates',
            ['code' => strtolower(clean_param($_COOKIE[self::COOKIE], PARAM_ALPHANUM)), 'status' => 'active']) : null;
        $id = $DB->insert_record('local_oriskill_affiliates', (object) ['userid' => $userid, 'code' => $code,
            'status' => 'active', 'clicks' => 0, 'referrerid' => $sponsor && (int) $sponsor->userid !== $userid ? $sponsor->id : null,
            'timecreated' => $now, 'timemodified' => $now]);
        return $DB->get_record('local_oriskill_affiliates', ['id' => $id]);
    }

    /**
     * A page address carrying the affiliate's code.
     *
     * @param \stdClass $affiliate Affiliate.
     * @param moodle_url|null $url Page, the home page by default.
     * @return moodle_url
     */
    public static function link(\stdClass $affiliate, ?moodle_url $url = null): moodle_url {
        $url = $url ? new moodle_url($url) : new moodle_url('/');
        $url->param('ref', $affiliate->code);
        return $url;
    }

    /**
     * Remember the code of a visitor arriving through an affiliate link, and count the visit.
     *
     * Called on every web request (after_config). A repeated visit with the same code is not counted again.
     *
     * @param string $code Value of ?ref=.
     * @param int $campaignid Value of ?cmp= (link of an affiliate campaign), 0 when none.
     */
    public static function track_visit(string $code, int $campaignid = 0): void {
        global $CFG, $DB;

        $code = strtolower(clean_param($code, PARAM_ALPHANUM));
        if ($code === '' || !self::enabled() || ($_COOKIE[self::COOKIE] ?? '') === $code) {
            return;
        }
        $affiliate = $DB->get_record('local_oriskill_affiliates', ['code' => $code, 'status' => 'active']);
        if (!$affiliate) {
            return;
        }
        $days = max(1, (int) get_config('local_oriskill', 'cookiedays'));
        if (!headers_sent()) {
            $options = ['expires' => time() + $days * DAYSECS, 'path' => $CFG->sessioncookiepath ?: '/', 'secure' => is_https(),
                'httponly' => true, 'samesite' => 'Lax'];
            setcookie(self::COOKIE, $code, $options);
            setcookie(self::COOKIE_TIME, (string) time(), $options);
        }
        $_COOKIE[self::COOKIE] = $code;
        $_COOKIE[self::COOKIE_TIME] = (string) time();
        $DB->execute('UPDATE {local_oriskill_affiliates} SET clicks = clicks + 1 WHERE id = ?', [$affiliate->id]);
        if ($campaignid) {
            campaign_service::count_click($campaignid, $affiliate);
        }
    }

    /**
     * Attach an order to the affiliate whose code the buyer carries. Nobody earns on their own purchases.
     *
     * @param int $orderid Order.
     * @param string $code Referral code from the buyer's cookie.
     * @return bool Whether the order was attributed.
     */
    public static function attribute_order(int $orderid, string $code): bool {
        global $DB;

        $code = strtolower(clean_param($code, PARAM_ALPHANUM));
        if ($code === '' || !self::enabled() || $DB->record_exists('local_oriskill_referrals', ['orderid' => $orderid])) {
            return false;
        }
        $affiliate = $DB->get_record('local_oriskill_affiliates', ['code' => $code, 'status' => 'active']);
        $buyer = $DB->get_field('local_moderncommerce_orders', 'userid', ['id' => $orderid]);
        if (!$affiliate || !$buyer || (int) $buyer === (int) $affiliate->userid) {
            return false;
        }
        $DB->insert_record('local_oriskill_referrals', (object) ['orderid' => $orderid, 'affiliateid' => $affiliate->id,
            'clicktime' => (int) ($_COOKIE[self::COOKIE_TIME] ?? 0),
            'timecreated' => time()]);
        return true;
    }

    /**
     * Commission rate of a seller: the trainer's own rate, or the platform rate for Oriskill's courses.
     *
     * @param \stdClass|null $seller Trainer, or null for Oriskill.
     * @return float Percentage.
     */
    public static function rate_for(?\stdClass $seller): float {
        return $seller ? (float) $seller->affiliaterate : (float) get_config('local_oriskill', 'platformrate');
    }

    /**
     * Create the pending commissions of a paid order that came through an affiliate (idempotent).
     *
     * The commission base is what the buyer actually paid for the line, before tax: the order's net amount
     * (after coupons) shared between lines in proportion to their price.
     *
     * @param int $orderid Order.
     * @return int Number of commissions created.
     */
    public static function record_commissions(int $orderid): int {
        global $DB;

        $referral = $DB->get_record('local_oriskill_referrals', ['orderid' => $orderid]);
        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        if (!$referral || !$order) {
            return 0;
        }
        $affiliate = $DB->get_record('local_oriskill_affiliates', ['id' => $referral->affiliateid]);
        if (!$affiliate || $affiliate->status !== 'active') {
            return 0;
        }
        $items = $DB->get_records('local_moderncommerce_order_items', ['orderid' => $orderid]);
        // Trainers with campaigns pay through them: one commission per sale (see campaign_service).
        $bytrainer = [];
        foreach ($items as $id => $item) {
            $seller = $item->courseid ? seller_service::owner_of_course((int) $item->courseid) : null;
            if ($seller && campaign_service::uses_campaigns($seller)) {
                $bytrainer[$seller->id][0] = $seller;
                $bytrainer[$seller->id][1][$id] = $item;
            }
        }
        $created = 0;
        $subtotal = array_sum(array_map(fn($item) => (float) $item->subtotal, $items));
        foreach ($bytrainer as [$seller, $lines]) {
            $created += campaign_service::record($seller, $order, $lines, $affiliate, (int) ($referral->clicktime ?? 0));
            $items = array_diff_key($items, $lines);
        }
        $net = max(0, (float) $order->subtotal - (float) $order->discount);
        $now = time();
        foreach ($items as $item) {
            if ($DB->record_exists('local_oriskill_commissions', ['orderitemid' => $item->id])) {
                continue;
            }
            $seller = $item->courseid ? seller_service::owner_of_course((int) $item->courseid) : null;
            if ($seller && (int) $seller->userid === (int) $affiliate->userid) {
                // Recommending one's own course earns nothing.
                continue;
            }
            $rate = self::rate_for($seller);
            $base = $subtotal > 0 ? round($net * (float) $item->subtotal / $subtotal, 2) : 0;
            $amount = round($base * $rate / 100, 2);
            if ($rate <= 0 || $amount <= 0) {
                continue;
            }
            $DB->insert_record('local_oriskill_commissions', (object) ['affiliateid' => $affiliate->id,
                'orderid' => $orderid, 'orderitemid' => $item->id, 'courseid' => $item->courseid ?: null,
                'sellerid' => $seller->id ?? 0, 'itemname' => $item->itemname, 'base' => $base, 'rate' => $rate,
                'amount' => $amount, 'currency' => $item->currency ?: $order->currency, 'status' => 'pending',
                'timecreated' => $now, 'timemodified' => $now]);
            $created++;
        }
        return $created;
    }

    /**
     * End of the refund window: pending commissions become due, or are cancelled if their order was refunded
     * or cancelled. A refund cancels a pending commission at once, without waiting for the window to end.
     *
     * @param int|null $now Current time (for tests).
     * @return array ['approved' => n, 'cancelled' => n]
     */
    public static function process_pending(?int $now = null): array {
        global $DB;

        $now = $now ?? time();
        $window = max(0, (int) get_config('local_oriskill', 'validationdays')) * DAYSECS;
        $result = ['approved' => 0, 'cancelled' => 0];
        $pending = $DB->get_records_sql(
            "SELECT c.id, c.timecreated, c.validafter, c.orderid, o.status AS orderstatus
               FROM {local_oriskill_commissions} c
          LEFT JOIN {local_moderncommerce_orders} o ON o.id = c.orderid
              WHERE c.status = 'pending'");
        foreach ($pending as $commission) {
            if ($commission->orderid && !in_array($commission->orderstatus, ['paid', 'completed'], true)) {
                $status = 'cancelled';
            } else if ($commission->validafter ? $commission->validafter <= $now : $commission->timecreated <= $now - $window) {
                $status = 'approved';
            } else {
                continue;
            }
            $DB->update_record('local_oriskill_commissions', (object) ['id' => $commission->id, 'status' => $status,
                'timemodified' => $now]);
            $result[$status]++;
        }
        return $result;
    }

    /**
     * Mark a due commission as paid to the affiliate.
     *
     * @param int $commissionid Commission.
     * @param int|null $sellerid Restrict to this seller's commissions (a trainer), or null for an administrator.
     */
    public static function mark_paid(int $commissionid, ?int $sellerid = null): void {
        global $DB;

        $conditions = ['id' => $commissionid, 'status' => 'approved'];
        if ($sellerid !== null) {
            $conditions['sellerid'] = $sellerid;
        }
        $commission = $DB->get_record('local_oriskill_commissions', $conditions, '*', MUST_EXIST);
        $now = time();
        $DB->update_record('local_oriskill_commissions', (object) ['id' => $commission->id, 'status' => 'paid',
            'timepaid' => $now, 'timemodified' => $now]);
    }

    /**
     * Commissions, newest first, with the affiliate's name and payout details.
     *
     * @param array $conditions Any of: affiliateid, sellerid, status.
     * @return array
     */
    public static function commissions(array $conditions = []): array {
        global $DB;

        $where = [];
        $params = [];
        foreach (['affiliateid', 'sellerid', 'status'] as $field) {
            if (isset($conditions[$field]) && $conditions[$field] !== '') {
                $where[] = "c.$field = :$field";
                $params[$field] = $conditions[$field];
            }
        }
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        return array_values($DB->get_records_sql(
            "SELECT c.*, o.ordernumber, a.code, a.payoutdetails, a.userid AS affiliateuserid, $userfields
               FROM {local_oriskill_commissions} c
               JOIN {local_oriskill_affiliates} a ON a.id = c.affiliateid
               JOIN {user} u ON u.id = a.userid
          LEFT JOIN {local_moderncommerce_orders} o ON o.id = c.orderid
              " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
           ORDER BY c.timecreated DESC, c.id DESC", $params));
    }

    /**
     * Totals by status and currency.
     *
     * @param array $commissions Commission rows.
     * @return array status => currency => amount
     */
    public static function totals(array $commissions): array {
        $totals = [];
        foreach ($commissions as $commission) {
            $totals[$commission->status][$commission->currency] =
                ($totals[$commission->status][$commission->currency] ?? 0) + (float) $commission->amount;
        }
        return $totals;
    }

    /**
     * Courses an affiliate can recommend, with their commission: on sale, with a commission rate above zero.
     *
     * @param \stdClass $affiliate Affiliate (their own courses are left out).
     * @return array Rows: courseid, name, url (with the code), price, rate.
     */
    public static function promotable_courses(\stdClass $affiliate): array {
        global $DB;

        $courses = $DB->get_recordset_sql(
            "SELECT c.id, c.fullname, pp.amount
               FROM {local_moderncommerce_products} p
               JOIN {local_moderncommerce_product_courses} pc ON pc.productid = p.id
               JOIN {course} c ON c.id = pc.courseid
          LEFT JOIN {local_moderncommerce_product_prices} pp ON pp.productid = p.id AND pp.pricetype = 'regular'
              WHERE p.producttype = 'course' AND p.status = 'active' AND p.visible = 1 AND c.visible = 1
           ORDER BY c.fullname");
        $currency = \local_moderncommerce\services\pricing_service::get_currency_config()->currency;
        $result = [];
        foreach ($courses as $course) {
            if (isset($result[$course->id])) {
                continue;
            }
            $seller = seller_service::owner_of_course((int) $course->id);
            $rate = self::rate_for($seller);
            if ($rate <= 0 || ($seller && ((int) $seller->userid === (int) $affiliate->userid
                    || seller_service::selling_blocker($seller)))) {
                continue;
            }
            $result[$course->id] = (object) [
                'courseid' => $course->id,
                'name' => format_string($course->fullname),
                'url' => self::link($affiliate, new moodle_url('/local/moderncommerce/course_details.php',
                    ['id' => $course->id]))->out(false),
                'price' => $course->amount !== null
                    ? \local_moderncommerce\services\pricing_service::format_price($course->amount, $currency) : '',
                'rate' => format_float($rate, 2, true, true),
            ];
        }
        $courses->close();
        return array_values($result);
    }
}
