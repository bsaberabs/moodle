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
 * Affiliate campaigns of a trainer: what an affiliate earns on the trainer's sales (a percentage before or after tax,
 * or a fixed amount per sale), on which order form, for how long after the click, after which guarantee period,
 * for everyone or for chosen affiliates, and what their sponsor earns (second level).
 *
 * A trainer with an enabled campaign pays commissions through campaigns only; without, their single rate applies
 * (affiliate_service::rate_for()). One commission per sale, to the affiliate of the last click.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_service {

    /** Campaigns. */
    const TABLE = 'local_oriskill_campaigns';

    /** Affiliates of the private campaigns. */
    const MEMBERS = 'local_oriskill_campaignaff';

    /**
     * Campaigns of a trainer, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function campaigns(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['creatorid' => $creator->id], 'timecreated DESC, id DESC');
    }

    /**
     * A campaign of the trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Campaign.
     * @return \stdClass
     */
    public static function require_own(\stdClass $creator, int $id): \stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
    }

    /**
     * Save a campaign.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data Form data (id 0 for a new one; affiliateids for a private campaign).
     * @return \stdClass Saved campaign.
     */
    public static function save(\stdClass $creator, \stdClass $data): \stdClass {
        global $DB;

        $now = time();
        $formid = (int) ($data->formid ?? 0);
        if ($formid && !$DB->record_exists('local_oriskill_orderforms', ['id' => $formid, 'creatorid' => $creator->id])) {
            $formid = 0;
        }
        $record = (object) [
            'creatorid' => $creator->id,
            'name' => \core_text::substr(trim((string) $data->name), 0, 255),
            'description' => trim((string) ($data->description ?? '')),
            'commtype' => ($data->commtype ?? '') === 'fixed' ? 'fixed' : 'percent',
            'commission' => round(max(0, (float) $data->commission), 2),
            'taxbase' => ($data->taxbase ?? '') === 'incl' ? 'incl' : 'excl',
            'level2' => round(max(0, min(100, (float) ($data->level2 ?? 0))), 2),
            'formid' => $formid,
            'trackingdays' => max(1, min(365, (int) ($data->trackingdays ?? 30))),
            'guaranteedays' => max(0, min(365, (int) ($data->guaranteedays ?? 14))),
            'visibility' => ($data->visibility ?? '') === 'private' ? 'private' : 'public',
            'newonly' => empty($data->newonly) ? 0 : 1,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'timemodified' => $now,
        ];
        if ($record->commtype === 'percent') {
            $record->commission = min(100, $record->commission);
        }
        if (!empty($data->id)) {
            $record->id = self::require_own($creator, (int) $data->id)->id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record(self::TABLE, $record);
        }
        $DB->delete_records(self::MEMBERS, ['campaignid' => $record->id]);
        if ($record->visibility === 'private') {
            foreach (array_unique(array_map('intval', (array) ($data->affiliateids ?? []))) as $affiliateid) {
                if ($DB->record_exists('local_oriskill_affiliates', ['id' => $affiliateid])) {
                    $DB->insert_record(self::MEMBERS, (object) ['campaignid' => $record->id, 'affiliateid' => $affiliateid]);
                }
            }
        }
        return $DB->get_record(self::TABLE, ['id' => $record->id]);
    }

    /**
     * Affiliates of a private campaign.
     *
     * @param \stdClass $campaign Campaign.
     * @return int[] Affiliate ids.
     */
    public static function members(\stdClass $campaign): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select(self::MEMBERS, 'affiliateid', 'campaignid = ?', [$campaign->id]));
    }

    /**
     * What a campaign pays, in words.
     *
     * @param \stdClass $campaign Campaign.
     * @return string
     */
    public static function describe(\stdClass $campaign): string {
        $currency = stats_service::currency();
        $text = $campaign->commtype === 'fixed'
            ? get_string('campaign:fixed', 'local_oriskill', \local_moderncommerce\services\pricing_service::format_price(
                (float) $campaign->commission, $currency))
            : get_string('campaign:percent_' . $campaign->taxbase, 'local_oriskill', format_float((float) $campaign->commission, 2,
                true, true));
        return $text . ((float) $campaign->level2 > 0 ? ' · ' . get_string('campaign:level2short', 'local_oriskill',
            format_float((float) $campaign->level2, 2, true, true)) : '');
    }

    /**
     * Whether a trainer pays commissions through campaigns.
     *
     * @param \stdClass $creator Trainer.
     * @return bool
     */
    public static function uses_campaigns(\stdClass $creator): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['creatorid' => $creator->id, 'enabled' => 1]);
    }

    /**
     * The campaign that pays an affiliate for an order of the trainer, if any: the one of the order form first, then
     * one for all the forms; private campaigns only for their affiliates; within its tracking time after the click.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $order Order.
     * @param \stdClass $affiliate Affiliate of the last click.
     * @param int $clicktime Time of that click (0 when unknown).
     * @return \stdClass|null
     */
    public static function applicable(\stdClass $creator, \stdClass $order, \stdClass $affiliate, int $clicktime): ?\stdClass {
        global $DB;

        $notes = json_decode((string) ($order->notes ?? ''), true) ?: [];
        $formid = (int) ($notes['orderform'] ?? 0);
        $campaigns = $DB->get_records_select(self::TABLE, 'creatorid = ? AND enabled = 1 AND (formid = 0 OR formid = ?)',
            [$creator->id, $formid], 'formid DESC, id ASC');
        foreach ($campaigns as $campaign) {
            if ($campaign->visibility === 'private' && !in_array((int) $affiliate->id, self::members($campaign), true)) {
                continue;
            }
            if ($clicktime && $clicktime < (int) $order->timecreated - (int) $campaign->trackingdays * DAYSECS) {
                continue;
            }
            return $campaign;
        }
        return null;
    }

    /**
     * Commissions of the trainer's part of a paid order (idempotent): one per sale for the affiliate, and one for
     * their sponsor when the campaign pays a second level.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $order Order.
     * @param array $items The order lines of the trainer's courses.
     * @param \stdClass $affiliate Affiliate of the last click.
     * @param int $clicktime Time of that click (0 when unknown).
     * @return int Commissions created.
     */
    public static function record(\stdClass $creator, \stdClass $order, array $items, \stdClass $affiliate, int $clicktime): int {
        global $DB;

        if (!$items || (int) $creator->userid === (int) $affiliate->userid) {
            return 0;
        }
        $campaign = self::applicable($creator, $order, $affiliate, $clicktime);
        if (!$campaign || $DB->record_exists('local_oriskill_commissions', ['orderid' => $order->id,
                'campaignid' => $campaign->id, 'level' => 1])) {
            return 0;
        }
        // Only for new customers: someone already among the trainer's contacts before the click earns nothing.
        if ($campaign->newonly) {
            $known = $DB->get_field('local_oriskill_contacts', 'timecreated', ['creatorid' => $creator->id,
                'userid' => $order->userid]);
            if ($known && (int) $known < ($clicktime ?: (int) $order->timecreated) - 60) {
                return 0;
            }
        }
        // What the buyer paid for the trainer's lines (after promo codes), with or without VAT.
        $allitems = $DB->get_records('local_moderncommerce_order_items', ['orderid' => $order->id]);
        $subtotal = array_sum(array_map(fn($i) => (float) $i->subtotal, $allitems));
        $net = max(0, (float) $order->subtotal - (float) $order->discount);
        $paid = $subtotal > 0 ? round($net * array_sum(array_map(fn($i) => (float) $i->subtotal, $items)) / $subtotal, 2) : 0;
        $base = $campaign->taxbase === 'excl' ? round($paid / (1 + (float) $creator->vatrate / 100), 2) : $paid;
        $amount = $campaign->commtype === 'fixed' ? (float) $campaign->commission : round($base * (float) $campaign->commission / 100, 2);
        if ($amount <= 0) {
            return 0;
        }
        $first = reset($items);
        $now = time();
        $common = ['orderid' => $order->id, 'orderitemid' => $first->id, 'courseid' => $first->courseid ?: null,
            'sellerid' => $creator->id, 'itemname' => \core_text::substr(implode(', ', array_map(fn($i) => $i->itemname, $items)),
                0, 255), 'base' => $base, 'currency' => $first->currency ?: $order->currency, 'status' => 'pending',
            'campaignid' => $campaign->id, 'validafter' => $now + (int) $campaign->guaranteedays * DAYSECS,
            'timecreated' => $now, 'timemodified' => $now];
        $DB->insert_record('local_oriskill_commissions', (object) ($common + ['affiliateid' => $affiliate->id,
            'rate' => $campaign->commtype === 'percent' ? (float) $campaign->commission : 0, 'amount' => $amount, 'level' => 1]));
        $created = 1;
        // The sponsor of the affiliate (who brought them in) earns a share of the commission.
        if ((float) $campaign->level2 > 0 && $affiliate->referrerid) {
            $sponsor = $DB->get_record('local_oriskill_affiliates', ['id' => $affiliate->referrerid, 'status' => 'active']);
            $share = round($amount * (float) $campaign->level2 / 100, 2);
            if ($sponsor && (int) $sponsor->userid !== (int) $order->userid && (int) $sponsor->userid !== (int) $creator->userid
                    && $share > 0) {
                $DB->insert_record('local_oriskill_commissions', (object) ($common + ['affiliateid' => $sponsor->id,
                    'rate' => (float) $campaign->level2, 'amount' => $share, 'level' => 2]));
                $created++;
            }
        }
        return $created;
    }

    /**
     * Figures of a campaign: sales and commissions (cancelled ones left out).
     *
     * @param \stdClass $campaign Campaign.
     * @return \stdClass {sales, amount (currency => amount)}
     */
    public static function figures(\stdClass $campaign): \stdClass {
        global $DB;

        $figures = (object) ['sales' => 0, 'amount' => []];
        foreach ($DB->get_records_select('local_oriskill_commissions', "campaignid = ? AND status <> 'cancelled'", [$campaign->id])
                as $commission) {
            $figures->sales += (int) $commission->level === 1 ? 1 : 0;
            $figures->amount[$commission->currency] = ($figures->amount[$commission->currency] ?? 0) + (float) $commission->amount;
        }
        return $figures;
    }

    /**
     * Affiliates working with a trainer (commissions on their sales, or chosen for a private campaign), with figures.
     *
     * @param \stdClass $creator Trainer.
     * @return array of {affiliate fields, name, email, commissions, amount (currency => amount), balance (due, unpaid)}
     */
    public static function affiliates(\stdClass $creator): array {
        global $DB;

        $ids = array_merge(
            $DB->get_fieldset_select('local_oriskill_commissions', 'DISTINCT affiliateid', 'sellerid = ?', [$creator->id]),
            $DB->get_fieldset_sql("SELECT DISTINCT m.affiliateid FROM {" . self::MEMBERS . "} m
                                     JOIN {" . self::TABLE . "} c ON c.id = m.campaignid WHERE c.creatorid = ?", [$creator->id]));
        $rows = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $affiliate = $DB->get_record('local_oriskill_affiliates', ['id' => $id]);
            $user = $affiliate ? \core_user::get_user($affiliate->userid) : null;
            if (!$user) {
                continue;
            }
            $affiliate->name = fullname($user);
            $affiliate->email = $user->email;
            $affiliate->commissions = 0;
            $affiliate->amount = [];
            $affiliate->balance = [];
            foreach ($DB->get_records('local_oriskill_commissions', ['affiliateid' => $id, 'sellerid' => $creator->id]) as $c) {
                if ($c->status === 'cancelled') {
                    continue;
                }
                $affiliate->commissions++;
                $affiliate->amount[$c->currency] = ($affiliate->amount[$c->currency] ?? 0) + (float) $c->amount;
                if ($c->status === 'approved') {
                    $affiliate->balance[$c->currency] = ($affiliate->balance[$c->currency] ?? 0) + (float) $c->amount;
                }
            }
            $rows[$id] = $affiliate;
        }
        \core_collator::asort_objects_by_property($rows, 'name');
        return $rows;
    }

    /**
     * A commission added by hand by the trainer (a sale made outside Oriskill, a bonus): due at once.
     *
     * @param \stdClass $creator Trainer.
     * @param int $affiliateid Affiliate.
     * @param float $amount Amount.
     * @param string $label What it is for.
     * @param int $campaignid Campaign, 0 for none.
     * @return int Commission id.
     */
    public static function add_manual(\stdClass $creator, int $affiliateid, float $amount, string $label, int $campaignid = 0): int {
        global $DB;

        if (!$DB->record_exists('local_oriskill_affiliates', ['id' => $affiliateid]) || $amount <= 0) {
            throw new \moodle_exception('invaliddata', 'error');
        }
        if ($campaignid) {
            self::require_own($creator, $campaignid);
        }
        $now = time();
        return $DB->insert_record('local_oriskill_commissions', (object) ['affiliateid' => $affiliateid, 'orderid' => 0,
            'orderitemid' => 0, 'courseid' => null, 'sellerid' => $creator->id, 'itemname' => \core_text::substr(trim($label), 0, 255),
            'base' => 0, 'rate' => 0, 'amount' => round($amount, 2), 'currency' => stats_service::currency(), 'status' => 'approved',
            'campaignid' => $campaignid ?: null, 'level' => 1, 'validafter' => 0, 'timecreated' => $now, 'timemodified' => $now]);
    }

    /**
     * Campaigns an affiliate can promote: the public ones of every trainer, and the private ones they were chosen for.
     *
     * @param \stdClass $affiliate Affiliate.
     * @return array of {campaign fields, trainer, url (with the affiliate's code), terms}
     */
    public static function for_affiliate(\stdClass $affiliate): array {
        global $DB;

        $campaigns = $DB->get_records_sql(
            "SELECT c.*, cr.displayname AS trainer
               FROM {" . self::TABLE . "} c
               JOIN {local_oriskill_creators} cr ON cr.id = c.creatorid
              WHERE c.enabled = 1 AND cr.status = 'active' AND cr.userid <> :userid
                AND (c.visibility = 'public' OR c.id IN (SELECT campaignid FROM {" . self::MEMBERS . "} WHERE affiliateid = :aff))
           ORDER BY cr.displayname, c.name", ['userid' => $affiliate->userid, 'aff' => $affiliate->id]);
        foreach ($campaigns as $campaign) {
            $creator = $DB->get_record('local_oriskill_creators', ['id' => $campaign->creatorid]);
            $form = $campaign->formid ? $DB->get_record('local_oriskill_orderforms', ['id' => $campaign->formid,
                'status' => 'published']) : null;
            $target = $form ? orderform_service::url($form, $creator) : creator_service::showcase_url($creator);
            $campaign->url = affiliate_service::link($affiliate, $target)->out(false);
            $campaign->terms = self::describe($campaign);
        }
        return $campaigns;
    }
}
