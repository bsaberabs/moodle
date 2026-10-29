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
 * Statistics of the order forms: visitors of the form page and of the order step (counted once per visitor and
 * day, robots and the trainer's team left out), paid orders and revenue, per offer and per day.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class formstats_service {

    /** Table. */
    const TABLE = 'local_oriskill_formstats';

    /** Robots and link previews, by user agent. */
    const ROBOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|headless|curl|wget|python|monitor/i';

    /**
     * Count a visit of a form page (offer 0, kind view) or of its order step (an offer, kind checkout).
     *
     * @param \stdClass $form Order form.
     * @param int $offerid Offer, 0 for the form page.
     * @param string $kind view or checkout.
     * @param int|null $time Time, for tests.
     * @return bool Whether the visit was counted.
     */
    public static function track(\stdClass $form, int $offerid, string $kind, ?int $time = null): bool {
        global $DB, $SESSION, $USER;

        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($agent === '' || preg_match(self::ROBOTS, $agent)) {
            return false;
        }
        // The trainer and their team looking at their own form are not visitors.
        if (isloggedin() && !isguestuser()) {
            $creator = $DB->get_record('local_oriskill_creators', ['id' => $form->creatorid]);
            if ($creator && ((int) $creator->userid === (int) $USER->id
                    || in_array((int) $USER->id, team_service::member_userids($creator), true))) {
                return false;
            }
        }
        $day = (int) date('Ymd', $time ?? time());
        $key = "{$form->id}-{$offerid}-{$kind}";
        $seen = $SESSION->local_oriskill_formseen ?? [];
        if (($seen[$key] ?? 0) === $day) {
            return false;
        }
        $seen[$key] = $day;
        $SESSION->local_oriskill_formseen = $seen;

        $conditions = ['formid' => $form->id, 'offerid' => $offerid, 'day' => $day, 'kind' => $kind];
        if (!$DB->record_exists(self::TABLE, $conditions)) {
            try {
                $DB->insert_record(self::TABLE, (object) ($conditions + ['hits' => 1]));
                return true;
            } catch (\dml_write_exception $e) {
                // Another visitor created the day's row at the same moment: add to it.
                debugging($e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        $DB->execute('UPDATE {' . self::TABLE . '} SET hits = hits + 1
                       WHERE formid = :formid AND offerid = :offerid AND day = :day AND kind = :kind', $conditions);
        return true;
    }

    /**
     * Figures of a form over the last days: visitors, order steps, paid orders, revenue, per offer and per day.
     *
     * @param \stdClass $form Order form.
     * @param int $days Number of days, today included.
     * @param int|null $now Time, for tests.
     * @return \stdClass {views, checkouts, orders, revenue, conversion (paid orders per 100 visitors), offers: offerid
     *                    => {label, checkouts, orders, revenue, instalments}, bumps, upsells, upsellrevenue,
     *                    days: YYYYMMDD => {views, checkouts, orders}}
     */
    public static function figures(\stdClass $form, int $days = 30, ?int $now = null): \stdClass {
        global $DB;

        $now = $now ?? time();
        $from = strtotime('today', $now) - ($days - 1) * DAYSECS;
        $result = (object) ['views' => 0, 'checkouts' => 0, 'orders' => 0, 'revenue' => 0.0, 'conversion' => 0.0,
            'offers' => [], 'bumps' => 0, 'upsells' => 0, 'upsellrevenue' => 0.0, 'days' => []];
        for ($t = $from; $t <= $now; $t += DAYSECS) {
            $result->days[(int) date('Ymd', $t)] = (object) ['views' => 0, 'checkouts' => 0, 'orders' => 0];
        }
        foreach (orderform_service::offers($form) as $offer) {
            $result->offers[$offer->id] = (object) ['label' => format_string($offer->label), 'checkouts' => 0, 'orders' => 0,
                'revenue' => 0.0, 'instalments' => 0];
        }

        foreach ($DB->get_records_select(self::TABLE, 'formid = ? AND day >= ?', [$form->id, (int) date('Ymd', $from)]) as $row) {
            $day = $result->days[(int) $row->day] ?? null;
            if ($row->kind === 'view') {
                $result->views += (int) $row->hits;
                if ($day) {
                    $day->views += (int) $row->hits;
                }
            } else {
                $result->checkouts += (int) $row->hits;
                if ($day) {
                    $day->checkouts += (int) $row->hits;
                }
                if (isset($result->offers[$row->offerid])) {
                    $result->offers[$row->offerid]->checkouts += (int) $row->hits;
                }
            }
        }

        $orders = $DB->get_records_select('local_moderncommerce_orders',
            "status IN ('paid', 'completed', 'refunded') AND timepaid >= :from AND " . $DB->sql_like('notes', ':pattern'),
            ['from' => $from, 'pattern' => '%"orderform":' . (int) $form->id . ',%'], '', 'id, notes, total, refundedtotal, timepaid');
        foreach ($orders as $order) {
            $notes = json_decode((string) $order->notes, true) ?: [];
            $amount = round((float) $order->total - (float) $order->refundedtotal, 2);
            if (!empty($notes['upsellof'])) {
                $result->upsells++;
                $result->upsellrevenue += $amount;
                $result->revenue += $amount;
                continue;
            }
            $result->orders++;
            $result->revenue += $amount;
            $result->bumps += empty($notes['bump']) ? 0 : 1;
            if ($day = $result->days[(int) date('Ymd', (int) $order->timepaid)] ?? null) {
                $day->orders++;
            }
            if ($offer = $result->offers[$notes['offer'] ?? 0] ?? null) {
                $offer->orders++;
                $offer->revenue += $amount;
                $offer->instalments += empty($notes['instalments']) ? 0 : 1;
            }
        }
        $result->conversion = $result->views ? round(100 * $result->orders / $result->views, 1) : 0.0;
        return $result;
    }
}
