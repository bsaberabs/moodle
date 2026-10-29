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
 * Promo codes of a trainer's order forms: a percentage or an amount off, for all forms or one, limited in uses and
 * dates, and allowed or not with payment in instalments. A code counts as used once the order is paid.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coupon_service {

    /** Table. */
    const TABLE = 'local_oriskill_coupons';

    /**
     * Codes of a trainer, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function coupons(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['creatorid' => $creator->id], 'timecreated DESC, id DESC');
    }

    /**
     * A code as stored: uppercase letters, digits, hyphens and underscores, or null when it is not one.
     *
     * @param string $code Code as typed.
     * @return string|null
     */
    public static function normalise(string $code): ?string {
        $code = \core_text::strtoupper(trim($code));
        return preg_match('/^[A-Z0-9_-]{3,40}$/', $code) ? $code : null;
    }

    /**
     * Save a code (new when $data->id is empty).
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data code, type, value, formid, instalments, maxuses, validfrom, validuntil, active.
     * @return \stdClass
     */
    public static function save(\stdClass $creator, \stdClass $data): \stdClass {
        global $DB;

        $record = (object) [
            'creatorid' => $creator->id,
            'code' => self::normalise((string) $data->code),
            'type' => $data->type === 'amount' ? 'amount' : 'percent',
            'value' => round((float) $data->value, 2),
            'formid' => (int) $data->formid ?: null,
            'instalments' => empty($data->instalments) ? 0 : 1,
            'maxuses' => max(0, (int) $data->maxuses),
            'validfrom' => (int) $data->validfrom,
            'validuntil' => (int) $data->validuntil,
            'active' => empty($data->active) ? 0 : 1,
        ];
        if ($record->code === null || $record->value <= 0 || ($record->type === 'percent' && $record->value >= 100)) {
            throw new \moodle_exception('error:coupon', 'local_oriskill');
        }
        if ($record->formid && !$DB->record_exists('local_oriskill_orderforms', ['id' => $record->formid,
                'creatorid' => $creator->id])) {
            throw new \moodle_exception('invaliddata', 'error');
        }
        if (!empty($data->id)) {
            $record->id = $DB->get_field(self::TABLE, 'id', ['id' => $data->id, 'creatorid' => $creator->id], MUST_EXIST);
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->timecreated = time();
            $record->id = $DB->insert_record(self::TABLE, $record);
        }
        return $DB->get_record(self::TABLE, ['id' => $record->id]);
    }

    /**
     * Whether a code is already used by another of the trainer's codes.
     *
     * @param \stdClass $creator Trainer.
     * @param string $code Normalised code.
     * @param int $id The code being edited (0 for a new one).
     * @return bool
     */
    public static function taken(\stdClass $creator, string $code, int $id): bool {
        global $DB;
        return $DB->record_exists_select(self::TABLE, 'creatorid = ? AND code = ? AND id <> ?', [$creator->id, $code, $id]);
    }

    /**
     * The code a buyer typed on an order form, if it applies there now.
     *
     * @param \stdClass $form Order form.
     * @param string $code Code as typed.
     * @param string $mode once or instalments.
     * @param int|null $now Time, for tests.
     * @return array [coupon or null, language string key of the reason it does not apply or null]
     */
    public static function find(\stdClass $form, string $code, string $mode, ?int $now = null): array {
        global $DB;

        $now = $now ?? time();
        $code = self::normalise($code);
        $coupon = $code ? $DB->get_record(self::TABLE, ['creatorid' => $form->creatorid, 'code' => $code]) : null;
        if (!$coupon || !$coupon->active || ($coupon->formid && (int) $coupon->formid !== (int) $form->id)) {
            return [null, 'coupon:unknown'];
        }
        if ($coupon->validfrom > $now || ($coupon->validuntil && $coupon->validuntil < $now)) {
            return [null, 'coupon:expired'];
        }
        if ($coupon->maxuses && $coupon->uses >= $coupon->maxuses) {
            return [null, 'coupon:usedup'];
        }
        if ($mode === 'instalments' && !$coupon->instalments) {
            return [null, 'coupon:notinstalments'];
        }
        return [$coupon, null];
    }

    /**
     * Amount a code takes off a price (never the whole price: something must be paid).
     *
     * @param \stdClass $coupon Code.
     * @param float $price Price.
     * @return float
     */
    public static function discount(\stdClass $coupon, float $price): float {
        $discount = $coupon->type === 'percent' ? round($price * (float) $coupon->value / 100, 2) : (float) $coupon->value;
        return round(max(0, min($discount, $price - 1)), 2);
    }

    /**
     * A paid order used a code: count it.
     *
     * @param int $orderid Paid order.
     */
    public static function order_paid(int $orderid): void {
        global $DB;

        $notes = json_decode((string) $DB->get_field('local_moderncommerce_orders', 'notes', ['id' => $orderid]), true);
        if (!empty($notes['coupon'])) {
            $DB->execute('UPDATE {' . self::TABLE . '} SET uses = uses + 1 WHERE id = ?', [(int) $notes['coupon']]);
        }
    }
}
