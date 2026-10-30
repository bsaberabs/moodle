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
 * Abandoned order forms: prospects who placed an order on a trainer's form without paying, and the e-mails that
 * remind them (the form's abandon sequence).
 *
 * A prospect is recorded once per form and learner when they place an order, and is recovered when an order of
 * theirs on the form is paid. The sequence has up to three e-mails, each sent a number of hours after the order;
 * one sent much later than planned (sequence turned on afterwards) is skipped. The prospect can stop them.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class abandon_service {

    /** E-mails a sequence can have. */
    const STEPS = 3;

    /** A reminder later than this after its planned time is skipped. */
    const LATE = 2 * DAYSECS;

    /** States of a prospect. */
    const STATES = ['open', 'recovered', 'stopped'];

    /** Words replaced in the e-mails. */
    const PLACEHOLDERS = ['{firstname}', '{lastname}', '{form}', '{link}', '{trainer}'];

    // ----- Prospects -----------------------------------------------------------------------------------------

    /**
     * An order was placed on a form: record the prospect (or bring them back to it with this order).
     *
     * @param \stdClass $form Order form.
     * @param \stdClass $order Order just created.
     */
    public static function checkout(\stdClass $form, \stdClass $order): void {
        global $DB;

        $notes = json_decode((string) $order->notes, true) ?: [];
        $now = time();
        $row = $DB->get_record('local_oriskill_abandons', ['formid' => $form->id, 'userid' => $order->userid]);
        $fields = ['orderid' => $order->id, 'offerid' => (int) ($notes['offer'] ?? 0), 'amount' => $order->total,
            'currency' => $order->currency, 'timemodified' => $now];
        if (!$row) {
            $DB->insert_record('local_oriskill_abandons', (object) ($fields + ['formid' => $form->id,
                'creatorid' => $form->creatorid, 'userid' => $order->userid, 'state' => 'open', 'step' => 0,
                'sent' => 0, 'lastsent' => 0, 'timerecovered' => 0, 'timecreated' => $now]));
        } else if ($row->state === 'recovered') {
            // A customer back for another offer: a new prospect, reminded from the start.
            $DB->update_record('local_oriskill_abandons', (object) ($fields + ['id' => $row->id, 'state' => 'open',
                'step' => 0, 'sent' => 0, 'lastsent' => 0, 'timerecovered' => 0, 'timecreated' => $now]));
        } else {
            // Still deciding (or asked for no more e-mails): the latest order counts, the reminders go on.
            $DB->update_record('local_oriskill_abandons', (object) ($fields + ['id' => $row->id]));
        }
    }

    /**
     * An order was paid: its buyer is no longer a prospect of the form.
     *
     * @param int $orderid Paid order.
     */
    public static function order_paid(int $orderid): void {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        $notes = $order ? (json_decode((string) $order->notes, true) ?: []) : [];
        if (empty($notes['orderform']) || !empty($notes['upsellof'])) {
            return;
        }
        $row = $DB->get_record('local_oriskill_abandons', ['formid' => (int) $notes['orderform'], 'userid' => $order->userid]);
        if ($row && $row->state !== 'recovered') {
            $DB->update_record('local_oriskill_abandons', (object) ['id' => $row->id, 'state' => 'recovered',
                'orderid' => $orderid, 'amount' => $order->total, 'timerecovered' => time(), 'timemodified' => time()]);
        }
    }

    /**
     * Prospects of a form, newest first, with the learner and the offer.
     *
     * @param \stdClass $form Order form.
     * @param string $state Only this state ('' for all).
     * @return array
     */
    public static function prospects(\stdClass $form, string $state = ''): array {
        global $DB;

        $params = ['formid' => $form->id];
        if ($state !== '') {
            $params['state'] = $state;
        }
        $rows = $DB->get_records('local_oriskill_abandons', $params, 'timecreated DESC, id DESC');
        $offers = $DB->get_records_menu('local_oriskill_formoffers', ['formid' => $form->id], '', 'id, label');
        foreach ($rows as $row) {
            $row->user = \core_user::get_user($row->userid);
            $row->offer = isset($offers[$row->offerid]) ? format_string($offers[$row->offerid]) : '';
        }
        return $rows;
    }

    /**
     * How many prospects of a form are in each state.
     *
     * @param \stdClass $form Order form.
     * @return array state => count
     */
    public static function counts(\stdClass $form): array {
        global $DB;

        $counts = array_fill_keys(self::STATES, 0);
        foreach ($DB->get_records_sql_menu("SELECT state, COUNT(1) FROM {local_oriskill_abandons}
                                             WHERE formid = ? GROUP BY state", [$form->id]) as $state => $count) {
            $counts[$state] = (int) $count;
        }
        return $counts;
    }

    // ----- Sequence ----------------------------------------------------------------------------------------

    /**
     * The form's sequence: e-mails in order (with their delay in hours), or suggested ones when never set.
     *
     * @param \stdClass $form Order form.
     * @return array of {delay, subject, body}
     */
    public static function sequence(\stdClass $form): array {
        $steps = json_decode((string) ($form->abandonsteps ?? ''), true);
        if (!is_array($steps)) {
            $steps = [];
            foreach ([1, 24] as $i => $delay) {
                $steps[] = ['delay' => $delay, 'subject' => get_string('abandon:default' . ($i + 1) . '_subject', 'local_oriskill'),
                    'body' => get_string('abandon:default' . ($i + 1) . '_body', 'local_oriskill')];
            }
        }
        return array_map(fn($s) => (object) $s, array_values($steps));
    }

    /**
     * Save a form's sequence. E-mails without a subject or text are dropped, the others are sorted by delay.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $form Order form.
     * @param bool $enabled Send the e-mails.
     * @param array $steps of ['delay' => hours, 'subject' => string, 'body' => string]
     */
    public static function save_sequence(\stdClass $creator, \stdClass $form, bool $enabled, array $steps): void {
        global $DB;

        $clean = [];
        foreach (array_slice($steps, 0, self::STEPS) as $step) {
            $subject = trim((string) ($step['subject'] ?? ''));
            $body = trim((string) ($step['body'] ?? ''));
            if ($subject !== '' && $body !== '') {
                $clean[] = ['delay' => max(1, min(720, (int) ($step['delay'] ?? 1))), 'subject' => \core_text::substr($subject, 0, 200),
                    'body' => $body];
            }
        }
        usort($clean, fn($a, $b) => $a['delay'] <=> $b['delay']);
        $DB->update_record('local_oriskill_orderforms', (object) ['id' => $form->id, 'abandonon' => $enabled && $clean ? 1 : 0,
            'abandonsteps' => json_encode($clean), 'timemodified' => time()]);
        log_service::add($creator, 'abandon_saved', ['title' => format_string($form->title),
            'count' => $enabled ? count($clean) : 0]);
    }

    // ----- Sending -----------------------------------------------------------------------------------------

    /**
     * Send the reminders that are due. Run by a scheduled task.
     *
     * @param int|null $now Time, for tests.
     * @param callable|null $mail fn(user, from, subject, text): bool, for tests (email_to_user otherwise).
     * @return int E-mails sent.
     */
    public static function send_due(?int $now = null, ?callable $mail = null): int {
        global $DB;

        $now = $now ?? time();
        $mail = $mail ?? fn($user, $from, $subject, $text) => email_to_user($user, $from, $subject, $text);
        $sent = 0;
        $forms = $DB->get_records('local_oriskill_orderforms', ['abandonon' => 1]);
        foreach ($forms as $form) {
            $steps = self::sequence($form);
            $creator = $DB->get_record('local_oriskill_creators', ['id' => $form->creatorid]);
            if (!$steps || !$creator) {
                continue;
            }
            $rows = $DB->get_records_select('local_oriskill_abandons', 'formid = ? AND state = ? AND step < ?',
                [$form->id, 'open', count($steps)]);
            foreach ($rows as $row) {
                $step = (int) $row->step;
                // Skip the e-mails whose time is long past, send the next one when it is due.
                while ($step < count($steps) && $now > (int) $row->timecreated + $steps[$step]->delay * HOURSECS + self::LATE) {
                    $step++;
                }
                if ($step >= count($steps) || $now < (int) $row->timecreated + $steps[$step]->delay * HOURSECS) {
                    if ($step !== (int) $row->step) {
                        $DB->set_field('local_oriskill_abandons', 'step', $step, ['id' => $row->id]);
                    }
                    continue;
                }
                if (self::paid_since($row) || !($user = $DB->get_record('user', ['id' => $row->userid, 'deleted' => 0]))) {
                    $DB->set_field('local_oriskill_abandons', 'state', self::paid_since($row) ? 'recovered' : 'stopped',
                        ['id' => $row->id]);
                    continue;
                }
                [$subject, $text] = self::compose($steps[$step], $form, $creator, $user, $row);
                $from = \core_user::get_noreply_user();
                $from->firstname = format_string($creator->displayname);
                $from->lastname = '';
                $delivered = (bool) $mail($user, $from, $subject, $text);
                $sent += (int) $delivered;
                $DB->update_record('local_oriskill_abandons', (object) ['id' => $row->id, 'step' => $step + 1,
                    'sent' => (int) $row->sent + (int) $delivered, 'lastsent' => $delivered ? $now : $row->lastsent,
                    'timemodified' => $now]);
            }
        }
        return $sent;
    }

    /**
     * Subject and text of a reminder, with the words replaced and the link to stop the reminders.
     *
     * @param \stdClass $step E-mail of the sequence.
     * @param \stdClass $form Order form.
     * @param \stdClass $creator Trainer.
     * @param \stdClass $user Prospect.
     * @param \stdClass $row Prospect record.
     * @return array [subject, text]
     */
    public static function compose(\stdClass $step, \stdClass $form, \stdClass $creator, \stdClass $user,
            \stdClass $row): array {
        $values = [$user->firstname, $user->lastname, format_string($form->title),
            orderform_service::url($form, $creator)->out(false), format_string($creator->displayname)];
        $subject = str_replace(self::PLACEHOLDERS, $values, $step->subject);
        $text = str_replace(self::PLACEHOLDERS, $values, $step->body) . "\n\n--\n" .
            get_string('abandon:stopline', 'local_oriskill', self::stop_url($row)->out(false));
        return [$subject, $text];
    }

    /**
     * Link that stops the reminders of a prospect (no login needed).
     *
     * @param \stdClass $row Prospect.
     * @return moodle_url
     */
    public static function stop_url(\stdClass $row): moodle_url {
        return new moodle_url('/local/oriskill/abandonstop.php', ['id' => $row->id, 'key' => self::key($row)]);
    }

    /**
     * The prospect asked for no more reminders.
     *
     * @param int $id Prospect.
     * @param string $key Key from the link.
     * @return bool Whether the link was valid.
     */
    public static function stop(int $id, string $key): bool {
        global $DB;

        $row = $DB->get_record('local_oriskill_abandons', ['id' => $id]);
        if (!$row || !hash_equals(self::key($row), $key)) {
            return false;
        }
        if ($row->state === 'open') {
            $DB->update_record('local_oriskill_abandons', (object) ['id' => $row->id, 'state' => 'stopped',
                'timemodified' => time()]);
        }
        return true;
    }

    /**
     * Key of a prospect's stop link.
     *
     * @param \stdClass $row Prospect.
     * @return string
     */
    private static function key(\stdClass $row): string {
        global $CFG;

        return substr(hash_hmac('sha256', 'abandon:' . $row->id . ':' . $row->userid, $CFG->siteidentifier), 0, 32);
    }

    /**
     * Whether the prospect paid an order on the form since (missed by the observer, or paid elsewhere on the form).
     *
     * @param \stdClass $row Prospect.
     * @return bool
     */
    private static function paid_since(\stdClass $row): bool {
        global $DB;

        return $DB->record_exists_select('local_moderncommerce_orders',
            "userid = :userid AND status IN ('paid', 'completed') AND timecreated >= :since AND " .
            $DB->sql_like('notes', ':pattern'),
            ['userid' => $row->userid, 'since' => (int) $row->timecreated - HOURSECS,
                'pattern' => '%"orderform":' . (int) $row->formid . ',%']);
    }
}
