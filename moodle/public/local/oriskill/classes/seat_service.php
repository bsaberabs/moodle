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
 * Several places bought at once on an order form: the buyer gets the access, and one code for each other place, to give to
 * the people concerned; each code opens the courses and groups of the offer to whoever types it.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seat_service {

    /** Codes. */
    const TABLE = 'local_oriskill_seatcodes';

    /** Most places of one order. */
    const MAX = 100;

    /**
     * Places the buyer may choose for an offer (1 when it sells one place only).
     *
     * @param \stdClass $offer Offer.
     * @param int $wanted Places asked.
     * @param string $mode Payment (places are sold paid at once only).
     * @return int
     */
    public static function seats(\stdClass $offer, int $wanted, string $mode): int {
        $max = min(self::MAX, (int) ($offer->maxseats ?? 0));
        return $mode === 'once' && $max > 1 ? max(1, min($max, $wanted)) : 1;
    }

    /**
     * A paid order of several places: one code for each place beyond the buyer's, sent to the buyer. Once.
     *
     * @param int $orderid Order.
     * @return int Codes made.
     */
    public static function order_paid(int $orderid): int {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        $notes = $order ? (json_decode((string) $order->notes, true) ?: []) : [];
        $seats = (int) ($notes['seats'] ?? 1);
        if ($seats < 2 || empty($notes['offer']) || $DB->record_exists(self::TABLE, ['orderid' => $orderid])) {
            return 0;
        }
        $offer = $DB->get_record('local_oriskill_formoffers', ['id' => $notes['offer']]);
        $form = $offer ? $DB->get_record('local_oriskill_orderforms', ['id' => $offer->formid]) : null;
        if (!$form) {
            return 0;
        }
        for ($i = 1; $i < $seats; $i++) {
            do {
                $code = strtoupper(implode('-', str_split(random_string(12), 4)));
            } while ($DB->record_exists(self::TABLE, ['code' => $code]));
            $DB->insert_record(self::TABLE, (object) ['creatorid' => $form->creatorid, 'orderid' => $orderid,
                'offerid' => $offer->id, 'code' => $code, 'redeemedby' => 0, 'timeredeemed' => 0, 'timecreated' => time()]);
        }
        self::mail_codes($order, $form, $offer);
        return $seats - 1;
    }

    /**
     * Codes of an order.
     *
     * @param int $orderid Order.
     * @return array
     */
    public static function codes(int $orderid): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['orderid' => $orderid], 'id');
    }

    /**
     * Use a code: the courses of its offer open to the user, in the offer's groups.
     *
     * @param \stdClass $user User typing it.
     * @param string $code Code.
     * @return array Names of the courses opened.
     */
    public static function redeem(\stdClass $user, string $code): array {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['code' => strtoupper(trim($code))]);
        if (!$record) {
            throw new \moodle_exception('seat:unknown', 'local_oriskill');
        }
        if ($record->redeemedby) {
            throw new \moodle_exception('seat:used', 'local_oriskill');
        }
        // Taken only once, even if two people type it at the same moment.
        $DB->execute('UPDATE {' . self::TABLE . '} SET redeemedby = ?, timeredeemed = ? WHERE id = ? AND redeemedby = 0',
            [$user->id, time(), $record->id]);
        if ((int) $DB->get_field(self::TABLE, 'redeemedby', ['id' => $record->id]) !== (int) $user->id) {
            throw new \moodle_exception('seat:used', 'local_oriskill');
        }
        $offer = $DB->get_record('local_oriskill_formoffers', ['id' => $record->offerid], '*', MUST_EXIST);
        $groupids = array_filter(array_map('intval', explode(',', (string) ($offer->groupids ?? ''))));
        $names = [];
        foreach (array_filter(array_map('intval', explode(',', (string) $offer->courseids))) as $courseid) {
            if (!$course = $DB->get_record('course', ['id' => $courseid])) {
                continue;
            }
            $group = 0;
            foreach ($groupids as $groupid) {
                if ($DB->record_exists('groups', ['id' => $groupid, 'courseid' => $courseid])) {
                    $group = $groupid;
                    break;
                }
            }
            learning_service::offer_access($course, $user->email, $user->firstname, $user->lastname, 0, $group, false);
            $names[] = format_string($course->fullname);
        }
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $record->creatorid]);
        if ($creator) {
            log_service::add($creator, 'seat_redeemed', ['code' => $record->code], null, (int) $user->id);
        }
        return $names;
    }

    /**
     * Send the codes to the buyer.
     *
     * @param \stdClass $order Order.
     * @param \stdClass $form Order form.
     * @param \stdClass $offer Offer.
     */
    protected static function mail_codes(\stdClass $order, \stdClass $form, \stdClass $offer): void {
        global $DB;

        $buyer = \core_user::get_user($order->userid);
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $form->creatorid]);
        if (!$buyer || !$creator) {
            return;
        }
        $a = (object) ['firstname' => $buyer->firstname, 'offer' => format_string($offer->label),
            'codes' => implode("\n", array_map(fn($c) => '- ' . $c->code, self::codes((int) $order->id))),
            'url' => (new moodle_url('/local/oriskill/seat.php'))->out(false), 'trainer' => format_string($creator->displayname)];
        $from = \core_user::get_noreply_user();
        $from->firstname = format_string($creator->displayname);
        $from->lastname = '';
        $text = get_string('seat:mail_body', 'local_oriskill', $a);
        try {
            email_log::for_creator((int) $creator->id, 'transactional', fn() => email_to_user($buyer, $from,
                get_string('seat:mail_subject', 'local_oriskill', $a), $text, text_to_html(s($text), false, false, true)));
        } catch (\Throwable $e) {
            debugging('Seat codes e-mail: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The codes of an order, for the buyer or the team.
     *
     * @param int $orderid Order.
     * @return string HTML, empty without codes.
     */
    public static function codes_html(int $orderid): string {
        $codes = self::codes($orderid);
        if (!$codes) {
            return '';
        }
        $items = '';
        foreach ($codes as $code) {
            $who = $code->redeemedby ? \core_user::get_user($code->redeemedby) : null;
            $items .= \html_writer::tag('li', \html_writer::tag('code', s($code->code), ['class' => 'user-select-all']) . ' ' .
                ($code->redeemedby ? \html_writer::span(get_string('seat:usedby', 'local_oriskill', $who ? fullname($who) : '?'),
                    'small text-muted') : \html_writer::span(get_string('seat:free', 'local_oriskill'), 'small text-success')));
        }
        return \html_writer::div(\html_writer::tag('h3', get_string('seat:title', 'local_oriskill', count($codes)), ['class' => 'h6']) .
            \html_writer::tag('p', get_string('seat:help', 'local_oriskill', (new moodle_url('/local/oriskill/seat.php'))->out(false)),
                ['class' => 'small']) . \html_writer::tag('ul', $items, ['class' => 'list-unstyled mb-0']), 'oriskill-seats mt-3');
    }
}
