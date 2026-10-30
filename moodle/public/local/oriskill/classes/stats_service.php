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

use local_moderncommerce\services\pricing_service;

/**
 * Sales figures of a trainer, from the money actually collected: each invoice is a payment (an order paid at once,
 * each instalment on the day it is collected, a manual sale), each credit note a refund on the day of the refund.
 * Amounts include VAT, in the time zone of the trainer.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stats_service {

    /** Periods of the dashboard. */
    const PERIODS = ['today', '7d', '30d'];

    /**
     * Time zone of the trainer's account.
     *
     * @param \stdClass $creator Trainer.
     * @return string
     */
    public static function timezone(\stdClass $creator): string {
        return \core_date::get_user_timezone(\core_user::get_user($creator->userid) ?: null);
    }

    /**
     * A period of the dashboard and the one before, of the same length.
     *
     * @param \stdClass $creator Trainer.
     * @param string $key today, 7d or 30d.
     * @param int|null $now Time (tests).
     * @return array [from, to, previous from, previous to]
     */
    public static function period(\stdClass $creator, string $key, ?int $now = null): array {
        $now = $now ?? \core\di::get(\core\clock::class)->time();
        $midnight = usergetmidnight($now, self::timezone($creator));
        if ($key === 'today') {
            return [$midnight, $now, $midnight - DAYSECS, $now - DAYSECS];
        }
        $days = $key === '7d' ? 7 : 30;
        $from = strtotime('-' . ($days - 1) . ' days', $midnight);
        return [$from, $now, strtotime("-$days days", $from), $from - 1];
    }

    /**
     * Payments and refunds of a period, oldest first.
     *
     * @param \stdClass $creator Trainer.
     * @param int $from Start (included).
     * @param int $to End (included).
     * @return array of objects {id, time, type, amount (negative for a refund), currency, userid, orderid, number,
     *               buyer, lines [name => amount], method, country}
     */
    public static function movements(\stdClass $creator, int $from, int $to): array {
        global $DB;

        $documents = $DB->get_records_select('local_oriskill_invoices', "creatorid = ? AND type IN ('invoice', 'credit')
                AND timecreated >= ? AND timecreated <= ?", [$creator->id, $from, $to], 'timecreated ASC, id ASC');
        $orderids = array_filter(array_unique(array_map(fn($d) => (int) $d->orderid, $documents)));
        $orders = $orderids ? $DB->get_records_list('local_moderncommerce_orders', 'id', $orderids) : [];
        $countries = get_string_manager()->get_list_of_countries();
        $methods = [];
        $movements = [];
        foreach ($documents as $document) {
            $data = json_decode($document->data, true) ?: [];
            $order = $orders[$document->orderid] ?? null;
            $sign = $document->type === 'credit' ? -1 : 1;
            $total = round((float) $document->total, 2);
            // The amount of each product, the VAT included in proportion.
            $lines = [];
            $sum = array_sum(array_map(fn($l) => (float) ($l['total'] ?? 0), $data['lines'] ?? []));
            foreach ($data['lines'] ?? [] as $line) {
                $name = trim((string) ($line['name'] ?? '')) ?: '–';
                $lines[$name] = ($lines[$name] ?? 0) + ($sum > 0 ? $total * (float) $line['total'] / $sum : 0);
            }
            if (!$lines) {
                $lines['–'] = $total;
            }
            if ($order && !isset($methods[$order->id])) {
                $methods[$order->id] = billing_service::payment_method($order);
            }
            $billing = $order ? (json_decode((string) ($order->billingaddress ?? ''), true) ?: []) : [];
            $code = (string) ($billing['country'] ?? '');
            if ($code === '' && $document->userid) {
                $code = (string) $DB->get_field('user', 'country', ['id' => $document->userid]);
            }
            $movements[] = (object) [
                'id' => (int) $document->id,
                'time' => (int) $document->timecreated,
                'type' => $document->type,
                'amount' => $sign * $total,
                'currency' => $document->currency,
                'userid' => (int) $document->userid,
                'orderid' => (int) $document->orderid,
                'number' => $document->number,
                'buyer' => (string) ($data['buyer']['name'] ?? ''),
                'lines' => $lines,
                'method' => $order ? $methods[$order->id] : (string) ($data['paymentmethod'] ?? $data['method']
                    ?? get_string('stats:manual', 'local_oriskill')),
                'country' => $code !== '' ? ($countries[$code] ?? $code) : '–',
            ];
        }
        return $movements;
    }

    /**
     * Totals of payments: per currency, amount collected (refunds taken off), number of sales, refunds.
     *
     * @param array $movements movements().
     * @return array currency => {amount, count, refunds}
     */
    public static function totals(array $movements): array {
        $totals = [];
        foreach ($movements as $m) {
            $t = $totals[$m->currency] ?? (object) ['amount' => 0.0, 'count' => 0, 'refunds' => 0.0, 'gross' => 0.0];
            $t->amount += $m->amount;
            if ($m->amount >= 0) {
                $t->count++;
                $t->gross += $m->amount;
            } else {
                $t->refunds -= $m->amount;
            }
            $totals[$m->currency] = $t;
        }
        return $totals;
    }

    /**
     * Amounts per currency, written out.
     *
     * @param array $amounts currency => amount.
     * @return string
     */
    public static function money(array $amounts): string {
        $amounts = array_filter($amounts, fn($a) => abs($a) >= 0.005);
        if (!$amounts) {
            return pricing_service::format_price(0, self::currency());
        }
        $texts = [];
        foreach ($amounts as $currency => $amount) {
            $texts[] = pricing_service::format_price(round($amount, 2), $currency);
        }
        return implode(' + ', $texts);
    }

    /**
     * Currency of the platform.
     *
     * @return string
     */
    public static function currency(): string {
        return (string) (get_config('local_moderncommerce', 'currency') ?: 'EUR');
    }

    /**
     * Collected per hour (a single day) or per day, for a chart.
     *
     * @param \stdClass $creator Trainer.
     * @param array $movements movements() of the period.
     * @param int $from Start of the period.
     * @param int $to End of the period.
     * @return array of objects {label, amount, count, tip}
     */
    public static function series(\stdClass $creator, array $movements, int $from, int $to): array {
        $timezone = self::timezone($creator);
        $hourly = $to - $from <= DAYSECS;
        $buckets = [];
        for ($t = $from; $t <= $to; $t = $hourly ? $t + HOURSECS : strtotime('+1 day', $t)) {
            $key = $hourly ? userdate($t, '%H', $timezone) : userdate($t, '%Y-%m-%d', $timezone);
            $buckets[$key] = (object) ['label' => $hourly ? userdate($t, '%Hh', $timezone) : userdate($t, '%d/%m', $timezone),
                'amount' => 0.0, 'count' => 0, 'day' => $t];
        }
        foreach ($movements as $m) {
            $key = $hourly ? userdate($m->time, '%H', $timezone) : userdate($m->time, '%Y-%m-%d', $timezone);
            if (isset($buckets[$key]) && $m->currency === self::currency()) {
                $buckets[$key]->amount += $m->amount;
                $buckets[$key]->count += $m->amount >= 0 ? 1 : 0;
            }
        }
        foreach ($buckets as $bucket) {
            $bucket->tip = ($hourly ? $bucket->label : userdate($bucket->day, get_string('strftimedaydate', 'langconfig'),
                $timezone)) . ' : ' . pricing_service::format_price(round($bucket->amount, 2), self::currency()) . ' · ' .
                get_string('stats:sales', 'local_oriskill', $bucket->count);
        }
        return array_values($buckets);
    }

    /**
     * Column chart of a series (one measure, one axis; the number of sales is in the tooltip and the table).
     *
     * @param array $points series().
     * @param string $title Accessible title.
     * @return string HTML (inline SVG and a hidden table).
     */
    public static function chart(array $points, string $title): string {
        $width = 720;
        $height = 180;
        $left = 48;
        $bottom = 22;
        $top = 8;
        $plot = $height - $bottom - $top;
        $max = max(1, ...array_map(fn($p) => $p->amount, $points));
        // Clean ticks: 1, 2 or 5 times a power of ten.
        $step = 10 ** floor(log10($max / 3));
        foreach ([1, 2, 5, 10] as $m) {
            if ($max / ($step * $m) <= 4) {
                $step *= $m;
                break;
            }
        }
        $top_value = ceil($max / $step) * $step;
        $n = count($points);
        $slot = ($width - $left) / max(1, $n);
        $bar = min(24, max(2, $slot - 2));
        $svg = '';
        for ($v = 0; $v <= $top_value + 0.001; $v += $step) {
            $y = $top + $plot - $plot * $v / $top_value;
            $label = $v >= 1000 ? round($v / 1000, 1) . 'k' : (string) round($v);
            $svg .= '<line x1="' . $left . '" x2="' . $width . '" y1="' . $y . '" y2="' . $y .
                '" stroke="var(--oriskill-grid, #e7e5e4)" stroke-width="1"/>' .
                '<text x="' . ($left - 6) . '" y="' . ($y + 4) . '" text-anchor="end" font-size="11" fill="#78716c">' . $label .
                '</text>';
        }
        $every = max(1, (int) ceil($n / 10));
        foreach ($points as $i => $p) {
            $x = $left + $i * $slot + ($slot - $bar) / 2;
            $h = $p->amount > 0 ? max(2, $plot * $p->amount / $top_value) : 0;
            $y = $top + $plot - $h;
            $r = min(4, $h, $bar / 2);
            // Rounded at the data end, square on the baseline.
            $path = $h > 0 ? 'M' . $x . ',' . ($top + $plot) . ' V' . ($y + $r) . ' Q' . $x . ',' . $y . ' ' . ($x + $r) . ',' . $y .
                ' H' . ($x + $bar - $r) . ' Q' . ($x + $bar) . ',' . $y . ' ' . ($x + $bar) . ',' . ($y + $r) . ' V' . ($top + $plot) .
                ' Z' : '';
            $svg .= '<g class="oriskill-chart-bar"><title>' . s($p->tip) . '</title>' .
                '<rect x="' . ($left + $i * $slot) . '" y="' . $top . '" width="' . $slot . '" height="' . $plot .
                '" fill="transparent"/>' . ($path ? '<path d="' . $path . '" fill="#7c3aed"/>' : '') . '</g>';
            if ($i % $every === 0) {
                $svg .= '<text x="' . ($left + $i * $slot + $slot / 2) . '" y="' . ($height - 6) .
                    '" text-anchor="middle" font-size="11" fill="#78716c">' . s($p->label) . '</text>';
            }
        }
        $rows = '';
        foreach ($points as $p) {
            $rows .= '<tr><td>' . s($p->label) . '</td><td>' . s(pricing_service::format_price(round($p->amount, 2),
                self::currency())) . '</td><td>' . $p->count . '</td></tr>';
        }
        return '<svg class="oriskill-chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="' . s($title) .
            '" style="width: 100%; height: auto; display: block">' . $svg . '</svg>' .
            '<details class="small mt-1"><summary class="text-muted">' . get_string('stats:table', 'local_oriskill') .
            '</summary><table class="table table-sm mt-2"><thead><tr><th>' . get_string('date') . '</th><th>' .
            get_string('stats:collected', 'local_oriskill') . '</th><th>' . get_string('stats:salescount', 'local_oriskill') .
            '</th></tr></thead><tbody>' . $rows . '</tbody></table></details>';
    }

    /**
     * People whose first purchase from the trainer falls in a period.
     *
     * @param \stdClass $creator Trainer.
     * @param int $from Start.
     * @param int $to End.
     * @return int[] User ids.
     */
    public static function new_customers(\stdClass $creator, int $from, int $to): array {
        global $DB;

        return array_map('intval', array_keys($DB->get_records_sql_menu(
            "SELECT userid, MIN(timecreated) AS firstpaid
               FROM {local_oriskill_invoices}
              WHERE creatorid = ? AND type = 'invoice' AND userid > 0
           GROUP BY userid
             HAVING MIN(timecreated) >= ? AND MIN(timecreated) <= ?", [$creator->id, $from, $to])));
    }

    /**
     * What happened in a period, latest first: payments, refunds, enrolments, refused instalments.
     *
     * @param \stdClass $creator Trainer.
     * @param array $movements movements() of the period.
     * @param int $from Start.
     * @param int $to End.
     * @param int $limit Most events.
     * @return array of objects {time, icon, tone, text, userid}
     */
    public static function activity(\stdClass $creator, array $movements, int $from, int $to, int $limit = 30): array {
        global $DB;

        $events = [];
        foreach ($movements as $m) {
            $a = (object) ['name' => $m->buyer ?: '?', 'product' => implode(', ', array_keys($m->lines)),
                'amount' => pricing_service::format_price(abs($m->amount), $m->currency)];
            $events[] = (object) ['time' => $m->time, 'userid' => $m->userid,
                'icon' => $m->amount >= 0 ? 'fa-cart-shopping' : 'fa-rotate-left', 'tone' => $m->amount >= 0 ? 'good' : 'bad',
                'text' => get_string($m->amount >= 0 ? 'stats:act_paid' : 'stats:act_refund', 'local_oriskill', $a)];
        }
        $courses = $DB->get_records('course', ['category' => $creator->categoryid], '', 'id, fullname');
        if ($courses) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED);
            $members = team_service::member_userids($creator);
            $members[] = (int) $creator->userid;
            foreach ($DB->get_records_sql("SELECT ue.id, ue.userid, ue.timecreated, e.courseid
                                             FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                                            WHERE e.courseid $insql AND ue.timecreated >= :from AND ue.timecreated <= :to
                                         ORDER BY ue.timecreated DESC", $params + ['from' => $from, 'to' => $to], 0, 200)
                    as $ue) {
                if (in_array((int) $ue->userid, $members, true)) {
                    continue;
                }
                $user = \core_user::get_user($ue->userid);
                $events[] = (object) ['time' => (int) $ue->timecreated, 'userid' => (int) $ue->userid, 'icon' => 'fa-graduation-cap',
                    'tone' => '', 'text' => get_string('stats:act_enrolled', 'local_oriskill', (object) [
                        'name' => $user ? fullname($user) : '?', 'course' => format_string($courses[$ue->courseid]->fullname)])];
            }
            foreach ($DB->get_records_sql("SELECT i.id, i.userid, i.latesince, i.courseid FROM {local_oriskill_instalments} i
                                            WHERE i.courseid $insql AND i.latesince >= :from AND i.latesince <= :to",
                    $params + ['from' => $from, 'to' => $to]) as $late) {
                $user = \core_user::get_user($late->userid);
                $events[] = (object) ['time' => (int) $late->latesince, 'userid' => (int) $late->userid,
                    'icon' => 'fa-triangle-exclamation', 'tone' => 'bad', 'text' => get_string('stats:act_failed', 'local_oriskill',
                        (object) ['name' => $user ? fullname($user) : '?',
                            'course' => format_string($courses[$late->courseid]->fullname ?? '')])];
            }
        }
        usort($events, fn($a, $b) => $b->time <=> $a->time);
        return array_slice($events, 0, $limit);
    }

    /**
     * Instalments and subscriptions still to collect this month (from now to the end of the month).
     *
     * @param \stdClass $creator Trainer.
     * @param int|null $now Time (tests).
     * @return array currency => amount
     */
    public static function due_this_month(\stdClass $creator, ?int $now = null): array {
        $now = $now ?? \core\di::get(\core\clock::class)->time();
        $timezone = self::timezone($creator);
        $end = strtotime('+1 month', make_timestamp((int) userdate($now, '%Y', $timezone), (int) userdate($now, '%m', $timezone), 1,
            0, 0, 0, $timezone));
        $due = [];
        foreach (instalment_service::trainer_records($creator) as $record) {
            if ($record->nextdue >= $now && $record->nextdue < $end && in_array($record->status, ['active', 'late'], true)) {
                $due[$record->currency] = ($due[$record->currency] ?? 0) + (float) $record->amount;
            }
        }
        return $due;
    }

    /**
     * A breakdown of the payments of a period (refunds left out): by product, payment method or country.
     *
     * @param array $movements movements().
     * @param string $by product, method or country.
     * @return array label => {amount, count}, largest first (main currency only).
     */
    public static function breakdown(array $movements, string $by): array {
        $rows = [];
        foreach ($movements as $m) {
            if ($m->amount < 0 || $m->currency !== self::currency()) {
                continue;
            }
            $parts = $by === 'product' ? $m->lines : [($by === 'method' ? $m->method : $m->country) => $m->amount];
            foreach ($parts as $label => $amount) {
                $row = $rows[$label] ?? (object) ['amount' => 0.0, 'count' => 0];
                $row->amount += $amount;
                $row->count++;
                $rows[$label] = $row;
            }
        }
        uasort($rows, fn($a, $b) => $b->amount <=> $a->amount);
        return $rows;
    }
}
