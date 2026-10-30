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
use local_moderncommerce\payment\gateway_manager;
use local_moderncommerce\services\pricing_service;
use local_secureandpay\gateway;
use moodle_url;

/**
 * Order forms ("bons de commande"): a trainer's sales page offering one or more offers, each opening some of the
 * trainer's courses once paid, at once or in monthly instalments, through the trainer's SecureAndPay account.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class orderform_service {

    /** Order form image options. */
    const IMAGE_OPTIONS = ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 5242880,
        'accepted_types' => ['.png', '.jpg', '.jpeg', '.webp']];

    /**
     * Order forms of a trainer, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function forms(\stdClass $creator): array {
        global $DB;
        return $DB->get_records('local_oriskill_orderforms', ['creatorid' => $creator->id], 'timecreated DESC, id DESC');
    }

    /**
     * Offers of an order form, in order.
     *
     * @param \stdClass $form Order form.
     * @return array
     */
    public static function offers(\stdClass $form): array {
        global $DB;
        return $DB->get_records('local_oriskill_formoffers', ['formid' => $form->id], 'sortorder ASC, id ASC');
    }

    /**
     * Why an address part cannot be used for a trainer's order form, or null if it can.
     *
     * @param string $slug Address part.
     * @param \stdClass $creator Trainer.
     * @param int $formid The form it is for (0 for a new one).
     * @return string|null Language string key.
     */
    public static function slug_error(string $slug, \stdClass $creator, int $formid): ?string {
        global $DB;

        if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) < 3 || strlen($slug) > 60) {
            return 'error:formslug';
        }
        if ($DB->record_exists_select('local_oriskill_orderforms', 'creatorid = ? AND slug = ? AND id <> ?',
                [$creator->id, $slug, $formid])) {
            return 'error:formslugtaken';
        }
        return null;
    }

    /**
     * Save an order form and its offers.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data Form fields; offers as an array of {label, details, courseids[], price, instalcount,
     *                        instaltotal, highlight}.
     * @return \stdClass Saved order form.
     */
    public static function save(\stdClass $creator, \stdClass $data): \stdClass {
        global $DB;

        $own = array_keys(seller_service::priced_courses($creator));
        $now = time();
        $record = (object) [
            'creatorid' => $creator->id,
            'slug' => $data->slug,
            'title' => trim($data->title),
            'headline' => trim((string) $data->headline),
            'description' => $data->description,
            'descriptionformat' => $data->descriptionformat ?? FORMAT_HTML,
            'buttonlabel' => trim((string) $data->buttonlabel),
            'thankyou' => trim((string) $data->thankyou),
            'termsrequired' => empty($data->termsrequired) ? 0 : 1,
            'bumplabel' => trim((string) ($data->bumplabel ?? '')),
            'bumptext' => trim((string) ($data->bumptext ?? '')),
            'bumpcourseids' => implode(',', array_intersect(array_map('intval', (array) ($data->bumpcourseids ?? [])), $own)),
            'bumpprice' => round(max(0, (float) ($data->bumpprice ?? 0)), 2),
            'upselllabel' => trim((string) ($data->upselllabel ?? '')),
            'upselltext' => trim((string) ($data->upselltext ?? '')),
            'upsellcourseids' => implode(',', array_intersect(array_map('intval', (array) ($data->upsellcourseids ?? [])),
                $own)),
            'upsellprice' => round(max(0, (float) ($data->upsellprice ?? 0)), 2),
            'status' => $data->status === 'published' ? 'published' : 'draft',
            'timemodified' => $now,
        ];
        if (!empty($data->id)) {
            $existing = $DB->get_record('local_oriskill_orderforms', ['id' => $data->id, 'creatorid' => $creator->id],
                '*', MUST_EXIST);
            $record->id = $existing->id;
            $DB->update_record('local_oriskill_orderforms', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record('local_oriskill_orderforms', $record);
        }

        // Offers are replaced as a whole; only the trainer's own courses can be opened.
        $DB->delete_records('local_oriskill_formoffers', ['formid' => $record->id]);
        foreach (array_values($data->offers) as $index => $offer) {
            $courseids = array_values(array_intersect(array_map('intval', (array) $offer->courseids), $own));
            if (trim((string) $offer->label) === '' || !$courseids || (float) $offer->price <= 0) {
                continue;
            }
            $count = in_array((int) $offer->instalcount, instalment_service::COUNTS, true) ? (int) $offer->instalcount : 0;
            $instaltotal = $count ? round((float) $offer->instaltotal ?: (float) $offer->price, 2) : 0;
            // A first instalment of its own only makes sense below the total, the rest being spread over the others.
            $instalfirst = round(max(0, (float) ($offer->instalfirst ?? 0)), 2);
            $instalfirst = $count && $instalfirst < $instaltotal ? $instalfirst : 0;
            $DB->insert_record('local_oriskill_formoffers', (object) [
                'formid' => $record->id,
                'label' => trim($offer->label),
                'details' => trim((string) $offer->details),
                'courseids' => implode(',', $courseids),
                'price' => round((float) $offer->price, 2),
                'instalcount' => $count,
                'instaltotal' => $instaltotal,
                'instalfirst' => $instalfirst,
                'instalcut' => in_array((int) ($offer->instalcut ?? 1), instalment_service::CUTS, true) ? (int) $offer->instalcut : 1,
                'groupids' => self::offer_groupids((array) ($offer->groupids ?? []), $courseids),
                'highlight' => empty($offer->highlight) ? 0 : 1,
                'actags' => activecampaign_service::clean_tags((string) ($offer->actags ?? '')),
                'subprice' => round(max(0, (float) ($offer->subprice ?? 0)), 2),
                'sortorder' => $index,
            ]);
        }
        return $DB->get_record('local_oriskill_orderforms', ['id' => $record->id]);
    }

    /**
     * Groups an offer gives, kept only when they belong to the courses it opens.
     *
     * @param int[] $groupids Groups chosen.
     * @param int[] $courseids Courses of the offer.
     * @return string Comma-separated ids ('' for none).
     */
    protected static function offer_groupids(array $groupids, array $courseids): string {
        global $DB;

        $groupids = array_values(array_unique(array_filter(array_map('intval', $groupids))));
        if (!$groupids || !$courseids) {
            return '';
        }
        [$gsql, $gparams] = $DB->get_in_or_equal($groupids);
        [$csql, $cparams] = $DB->get_in_or_equal($courseids);
        $valid = $DB->get_fieldset_select('groups', 'id', "id $gsql AND courseid $csql", array_merge($gparams, $cparams));
        return implode(',', array_map('intval', array_intersect($groupids, array_map('intval', $valid))));
    }

    /**
     * Groups given by the offer an order was placed with (they open the modules reserved to them).
     *
     * @param \stdClass $order Order.
     * @return int[] Group ids.
     */
    public static function order_groupids(\stdClass $order): array {
        global $DB;

        $notes = json_decode((string) $order->notes, true) ?: [];
        if (empty($notes['offer'])) {
            return [];
        }
        $ids = (string) $DB->get_field('local_oriskill_formoffers', 'groupids', ['id' => (int) $notes['offer']]);
        return array_values(array_filter(array_map('intval', explode(',', $ids))));
    }

    /**
     * The order is paid: put the buyer in the groups its offer gives (once enrolled in their course).
     *
     * @param int $orderid Order.
     * @return bool Whether the offer gives groups (the automatic group of the payment is then not added).
     */
    public static function grant_groups(int $orderid): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        $groupids = $order ? self::order_groupids($order) : [];
        foreach ($groupids as $groupid) {
            $courseid = (int) $DB->get_field('groups', 'courseid', ['id' => $groupid]);
            if ($courseid && is_enrolled(\context_course::instance($courseid), $order->userid)
                    && !groups_is_member($groupid, $order->userid)) {
                groups_add_member($groupid, $order->userid);
            }
        }
        return (bool) $groupids;
    }

    /**
     * Published order form selling a course, to buy it from its page in the trainer's brand: a form whose offers
     * open only this course first, then any form offering it.
     *
     * @param \stdClass $creator Trainer.
     * @param int $courseid Course.
     * @return \stdClass|null
     */
    public static function form_for_course(\stdClass $creator, int $courseid): ?\stdClass {
        $best = null;
        foreach (self::forms($creator) as $form) {
            if ($form->status !== 'published') {
                continue;
            }
            foreach (self::offers($form) as $offer) {
                $courseids = array_map('intval', explode(',', (string) $offer->courseids));
                if (!in_array($courseid, $courseids, true)) {
                    continue;
                }
                if ($courseids === [$courseid]) {
                    return $form;
                }
                $best = $best ?? $form;
            }
        }
        return $best;
    }

    /**
     * Copy a form (a backup form for the same offers, or a variant): same content, offers, image and reminders,
     * as a draft under a free address. Its statistics and prospects start from zero.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Form to copy.
     * @return \stdClass The copy.
     */
    public static function duplicate(\stdClass $creator, int $id): \stdClass {
        global $DB;

        $form = $DB->get_record('local_oriskill_orderforms', ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
        $base = \core_text::substr(preg_replace('/-copie(-\d+)?$/', '', $form->slug), 0, 50) . '-copie';
        $slug = $base;
        for ($n = 2; self::slug_error($slug, $creator, 0) !== null; $n++) {
            $slug = $base . '-' . $n;
        }
        $copy = clone $form;
        unset($copy->id);
        $copy->slug = $slug;
        $copy->title = \core_text::substr(get_string('orderform:copyof', 'local_oriskill', $form->title), 0, 255);
        $copy->status = 'draft';
        $copy->timecreated = $copy->timemodified = time();
        $copy->id = $DB->insert_record('local_oriskill_orderforms', $copy);
        foreach (self::offers($form) as $offer) {
            unset($offer->id);
            $offer->formid = $copy->id;
            $DB->insert_record('local_oriskill_formoffers', $offer);
        }
        $context = \context_system::instance();
        $fs = get_file_storage();
        foreach ($fs->get_area_files($context->id, 'local_oriskill', 'orderform', $form->id, 'id', false) as $file) {
            $fs->create_file_from_storedfile(['itemid' => $copy->id], $file);
        }
        return $copy;
    }

    /**
     * Published order form a visitor asked for: by id, or by the trainer's own domain and the form's address.
     *
     * @param int $id Form id (0 when found by domain).
     * @param string $domain Trainer's domain.
     * @param string $slug Form address part.
     * @return array|null [form, trainer]
     */
    public static function public_form(int $id, string $domain = '', string $slug = ''): ?array {
        global $DB;

        if ($id) {
            $form = $DB->get_record('local_oriskill_orderforms', ['id' => $id, 'status' => 'published']);
            $creator = $form ? $DB->get_record('local_oriskill_creators', ['id' => $form->creatorid, 'status' => 'active']) : null;
        } else {
            $creator = creator_service::creator_by_domain($domain);
            $form = $creator ? $DB->get_record('local_oriskill_orderforms', ['creatorid' => $creator->id, 'slug' => $slug,
                'status' => 'published']) : null;
        }
        return $form && $creator ? [$form, $creator] : null;
    }

    /**
     * Public address of an order form: on the trainer's own domain when they have one, else on Oriskill.
     *
     * @param \stdClass $form Order form.
     * @param \stdClass $creator Trainer.
     * @return moodle_url
     */
    public static function url(\stdClass $form, \stdClass $creator): moodle_url {
        if (!empty($creator->customdomain)) {
            return new moodle_url('https://' . $creator->customdomain . '/bdc/' . $form->slug);
        }
        return new moodle_url('/local/oriskill/order.php', ['id' => $form->id]);
    }

    /**
     * How an offer is shown and paid: prices, the instalment option, the courses it opens.
     *
     * @param \stdClass $offer Offer.
     * @param string $currency Currency.
     * @return \stdClass {price, displayprice, instalments: {count, first, amount, total, displayfirst, displayamount,
     *                    displaytotal}|null (first: 0 when all instalments are equal),
     *                    subscription: {amount, displayamount}|null (monthly price),
     *                    courses: [{id, name}], details: string[]}
     */
    public static function describe_offer(\stdClass $offer, string $currency): \stdClass {
        global $DB;

        $instalments = null;
        if ($offer->instalcount && (float) $offer->instaltotal > 0) {
            $instalments = self::split((int) $offer->instalcount, (float) $offer->instaltotal,
                (float) ($offer->instalfirst ?? 0), $currency);
        }
        $courses = [];
        foreach (array_filter(explode(',', $offer->courseids)) as $courseid) {
            if ($name = $DB->get_field('course', 'fullname', ['id' => $courseid])) {
                $courses[] = (object) ['id' => (int) $courseid, 'name' => format_string($name)];
            }
        }
        $subscription = (float) ($offer->subprice ?? 0) > 0 ? (object) ['amount' => (float) $offer->subprice,
            'displayamount' => pricing_service::format_price((float) $offer->subprice, $currency)] : null;
        return (object) [
            'price' => (float) $offer->price,
            'displayprice' => pricing_service::format_price((float) $offer->price, $currency),
            'instalments' => $instalments,
            'subscription' => $subscription,
            'courses' => $courses,
            'details' => array_values(array_filter(array_map('trim', explode("\n", (string) $offer->details)))),
        ];
    }

    /**
     * Instalments of a total: an optional first instalment of its own, the rest in equal monthly instalments
     * rounded up to the cent.
     *
     * @param int $count Number of instalments, the first one included.
     * @param float $total Total to collect.
     * @param float $first First instalment (a deposit), 0 for equal instalments; ignored when not below the total.
     * @param string $currency Currency.
     * @return \stdClass {count, first, amount, total, displayfirst, displayamount, displaytotal, label}
     */
    public static function split(int $count, float $total, float $first, string $currency): \stdClass {
        $first = $first > 0 && $first < $total && $count > 1 ? round($first, 2) : 0.0;
        $amount = ceil(round(($total - $first) * 100 / ($first ? $count - 1 : $count), 4)) / 100;
        $collected = round($first ? $first + $amount * ($count - 1) : $amount * $count, 2);
        $split = (object) ['count' => $count, 'first' => $first, 'amount' => $amount, 'total' => $collected,
            'displayfirst' => pricing_service::format_price($first, $currency),
            'displayamount' => pricing_service::format_price($amount, $currency),
            'displaytotal' => pricing_service::format_price($collected, $currency),
            'others' => $count - 1];
        // How the offer reads: "3 × 70,00 €", or "300,00 € then 5 × 439,40 €".
        $split->label = get_string($first ? 'instalments:splitfirst' : 'instalments:split', 'local_oriskill', $split);
        return $split;
    }

    /**
     * Whether a trainer can take orders on their forms: active, allowed to sell, with a payment account
     * (SecureAndPay, Stripe or PayPal).
     *
     * @param \stdClass $creator Trainer.
     * @return bool
     */
    public static function can_sell(\stdClass $creator): bool {
        return $creator->status === 'active' && !seller_service::selling_blocker($creator);
    }

    /**
     * Whether a trainer can sell in instalments: only SecureAndPay charges the instalments on its own.
     *
     * @param \stdClass $creator Trainer.
     * @return bool
     */
    public static function can_sell_instalments(\stdClass $creator): bool {
        return self::can_sell($creator) && seller_service::merchant($creator) !== null;
    }

    /**
     * Extra of an order form offered at the order step (order bump), if it has a complete one.
     *
     * @param \stdClass $form Order form.
     * @return \stdClass|null {label, text, courseids, price}
     */
    public static function bump(\stdClass $form): ?\stdClass {
        return self::extra($form, 'bump');
    }

    /**
     * Offer of an order form shown after payment (upsell), if it has a complete one.
     *
     * @param \stdClass $form Order form.
     * @return \stdClass|null {label, text, courseids, price}
     */
    public static function upsell(\stdClass $form): ?\stdClass {
        return self::extra($form, 'upsell');
    }

    /**
     * Bump or upsell of a form.
     *
     * @param \stdClass $form Order form.
     * @param string $kind bump or upsell.
     * @return \stdClass|null
     */
    private static function extra(\stdClass $form, string $kind): ?\stdClass {
        $courseids = array_values(array_filter(array_map('intval', explode(',', (string) ($form->{$kind . 'courseids'} ?? '')))));
        $label = trim((string) ($form->{$kind . 'label'} ?? ''));
        $price = (float) ($form->{$kind . 'price'} ?? 0);
        if ($label === '' || !$courseids || $price <= 0) {
            return null;
        }
        return (object) ['label' => $label, 'text' => (string) $form->{$kind . 'text'}, 'courseids' => $courseids,
            'price' => round($price, 2)];
    }

    /**
     * What the buyer pays for an offer, with or without a promo code and the form's extra.
     *
     * The promo code applies to the offer; the extra is added at its own price (spread over the instalments when
     * paying in instalments).
     *
     * @param \stdClass $offer Offer.
     * @param string $mode once or instalments.
     * @param \stdClass|null $coupon Promo code that applies (see coupon_service::find()).
     * @param float $extra Price of the extra the buyer added, 0 for none.
     * @return \stdClass {base, extra, discount, total, instalments: {count, amount}|null, displaybase, displaytotal,
     *                    displayamount}
     */
    public static function quote(\stdClass $offer, string $mode, ?\stdClass $coupon = null, float $extra = 0): \stdClass {
        $currency = pricing_service::get_currency_config()->currency;
        $described = self::describe_offer($offer, $currency);
        if ($mode === 'subscription') {
            if (!$described->subscription) {
                throw new \moodle_exception('error:formunavailable', 'local_oriskill');
            }
            // Monthly until cancelled: what is charged now is one month (no promo code, no extra).
            $amount = $described->subscription->amount;
            return (object) ['base' => $amount, 'extra' => 0, 'discount' => 0, 'total' => $amount,
                'instalments' => (object) ['count' => 0, 'amount' => $amount, 'subscription' => true],
                'displaybase' => pricing_service::format_price($amount, $currency),
                'displaytotal' => pricing_service::format_price($amount, $currency),
                'displayamount' => pricing_service::format_price($amount, $currency)];
        }
        if ($mode === 'instalments') {
            if (!$described->instalments) {
                throw new \moodle_exception('error:formunavailable', 'local_oriskill');
            }
            $base = $described->instalments->total;
            $wanted = $base - ($coupon ? coupon_service::discount($coupon, $base) : 0) + $extra;
            // The first instalment stays as set; the promo code and the extra change the following ones.
            $instalments = self::split($described->instalments->count, $wanted, $described->instalments->first, $currency);
            $total = $instalments->total;
        } else {
            $base = $described->price;
            $total = round($base - ($coupon ? coupon_service::discount($coupon, $base) : 0) + $extra, 2);
            $instalments = null;
        }
        return (object) [
            'base' => $base,
            'extra' => $extra,
            'discount' => round(max(0, $base + $extra - $total), 2),
            'total' => $total,
            'instalments' => $instalments,
            'displaybase' => pricing_service::format_price($base + $extra, $currency),
            'displaytotal' => pricing_service::format_price($total, $currency),
            'displayamount' => pricing_service::format_price($instalments->amount ?? $total, $currency),
        ];
    }

    /**
     * Create the order of an offer and start its payment.
     *
     * @param int $userid Buyer.
     * @param \stdClass $form Published order form.
     * @param \stdClass $offer Offer of that form.
     * @param string $mode once or instalments.
     * @param \stdClass|null $coupon Promo code that applies (see coupon_service::find()).
     * @param bool $withextra The buyer ticked the form's extra (order bump).
     * @return array [order, moodle_url where the buyer pays]
     */
    public static function create_order(int $userid, \stdClass $form, \stdClass $offer, string $mode,
            ?\stdClass $coupon = null, bool $withextra = false): array {
        global $DB;

        $creator = $DB->get_record('local_oriskill_creators', ['id' => $form->creatorid], '*', MUST_EXIST);
        if ($form->status !== 'published' || (int) $offer->formid !== (int) $form->id || !self::can_sell($creator)) {
            throw new \moodle_exception('error:formunavailable', 'local_oriskill');
        }
        if ($coupon && ((int) $coupon->creatorid !== (int) $creator->id
                || coupon_service::find($form, $coupon->code, $mode)[1] !== null)) {
            throw new \moodle_exception('error:coupon', 'local_oriskill');
        }
        if (in_array($mode, ['instalments', 'subscription'], true) && !self::can_sell_instalments($creator)) {
            throw new \moodle_exception('error:formunavailable', 'local_oriskill');
        }
        if ($mode === 'subscription') {
            $coupon = null;
            $withextra = false;
        }
        $described = self::describe_offer($offer, pricing_service::get_currency_config()->currency);
        $courseids = array_column($described->courses, 'id');
        $enrolled = fn($id) => is_enrolled(\context_course::instance($id), $userid, '', true);
        if (!array_filter($courseids, fn($id) => !$enrolled($id))) {
            throw new \moodle_exception('error:alreadyhascourses', 'local_oriskill');
        }
        // The extra only when it brings a course the buyer does not have yet.
        $bump = $withextra ? self::bump($form) : null;
        $bumpcourses = $bump ? array_values(array_diff($bump->courseids, $courseids)) : [];
        if ($bump && !array_filter($bumpcourses, fn($id) => !$enrolled($id))) {
            $bump = null;
        }
        $quote = self::quote($offer, $mode, $coupon, $bump ? $bump->price : 0);

        $notes = ['orderform' => (int) $form->id, 'offer' => (int) $offer->id, 'returnpath' => '/local/oriskill/thanks.php'];
        if ($coupon) {
            $notes['coupon'] = (int) $coupon->id;
        }
        if ($bump) {
            $notes['bump'] = true;
        }
        if (self::upsell($form) && seller_service::merchant($creator)) {
            // SecureAndPay keeps the card, so the offer after payment is paid without typing it again.
            $notes['savecard'] = true;
        }
        if ($quote->instalments) {
            $notes['instalments'] = ['count' => $quote->instalments->count, 'amount' => $quote->instalments->amount]
                + (!empty($quote->instalments->first) ? ['first' => $quote->instalments->first] : [])
                + (!empty($quote->instalments->subscription) ? ['subscription' => true] : []);
        }
        // The order lines keep the full prices (offer shared over its courses, extra over its own); the promo code
        // is the order's discount (shown on the invoice).
        $prices = [];
        foreach ($courseids as $id) {
            $prices[$id] = round($quote->base / count($courseids), 2);
        }
        foreach ($bumpcourses as $id) {
            $prices[$id] = round($bump->price / count($bumpcourses), 2);
        }
        $order = order_api::create_course_order($userid, array_keys($prices), $quote->base + $quote->extra, $notes, $prices);
        if (abs($quote->total - ($quote->base + $quote->extra)) >= 0.005) {
            $DB->update_record('local_moderncommerce_orders', (object) ['id' => $order->id,
                'subtotal' => $quote->base + $quote->extra, 'discount' => $quote->discount, 'total' => $quote->total,
                'couponcode' => $coupon->code ?? null]);
            $order = order_api::get_order($order->id);
        }
        // A prospect until paid: reminded by the form's abandon sequence.
        abandon_service::checkout($form, $order);
        if ($quote->instalments) {
            $reference = gateway::start_instalments($order);
            return [$order, new moodle_url('/local/secureandpay/pay.php', ['orderid' => $order->id, 'ref' => $reference])];
        }
        return [$order, self::payment_url($creator, $order)];
    }

    /**
     * The offer after payment that a buyer can still take for a paid order of a form, if any.
     *
     * @param \stdClass $order Paid order placed on the form (not itself an upsell).
     * @return array|null [form, upsell, course ids the buyer does not have yet]
     */
    public static function upsell_for_order(\stdClass $order): ?array {
        global $DB;

        $notes = json_decode((string) $order->notes, true) ?: [];
        $form = self::form_of_order($order);
        if (!$form || !empty($notes['upsellof']) || !in_array($order->status, ['paid', 'completed'], true)) {
            return null;
        }
        $upsell = self::upsell($form);
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $form->creatorid]);
        if (!$upsell || !$creator || !self::can_sell($creator)) {
            return null;
        }
        $missing = array_values(array_filter($upsell->courseids,
            fn($id) => !is_enrolled(\context_course::instance($id), $order->userid, '', true)));
        // Taken already (paid or being paid).
        if (!$missing || $DB->record_exists_select('local_moderncommerce_orders',
                "userid = ? AND status IN ('paid', 'completed', 'pending') AND " . $DB->sql_like('notes', '?'),
                [$order->userid, '%"upsellof":' . (int) $order->id . ',%'])) {
            return null;
        }
        return [$form, $upsell, $missing];
    }

    /**
     * The buyer takes the offer after payment: order it and pay with the saved card when SecureAndPay has it.
     *
     * @param \stdClass $order Paid order of the buyer on the form.
     * @return array [upsell order, moodle_url where the buyer pays]
     */
    public static function take_upsell(\stdClass $order): array {
        global $DB;

        $found = self::upsell_for_order($order);
        if (!$found) {
            throw new \moodle_exception('error:formunavailable', 'local_oriskill');
        }
        [$form, $upsell, $courseids] = $found;
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $form->creatorid], '*', MUST_EXIST);
        $upsellorder = order_api::create_course_order((int) $order->userid, $courseids, $upsell->price, [
            'orderform' => (int) $form->id, 'upsellof' => (int) $order->id, 'returnpath' => '/local/oriskill/thanks.php',
            'savecard' => true]);
        return [$upsellorder, self::payment_url($creator, $upsellorder)];
    }

    /**
     * Where a buyer pays an order paid at once: straight to SecureAndPay when it is the trainer's only method (with
     * the saved card if it has one), else the store's payment page, which offers the trainer's methods.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $order Order.
     * @return moodle_url
     */
    public static function payment_url(\stdClass $creator, \stdClass $order): moodle_url {
        if (!seller_service::usable_gateways($creator)) {
            $start = gateway_manager::initialize_payment(gateway::GATEWAY, $order);
            return new moodle_url($start['authorization_url']);
        }
        return new moodle_url('/local/moderncommerce/checkout.php', ['orderid' => $order->id]);
    }

    /**
     * Order form an order was placed on, if any.
     *
     * @param \stdClass $order Order.
     * @return \stdClass|null
     */
    public static function form_of_order(\stdClass $order): ?\stdClass {
        global $DB;

        $notes = json_decode((string) $order->notes, true);
        $formid = (int) ($notes['orderform'] ?? 0);
        return $formid ? ($DB->get_record('local_oriskill_orderforms', ['id' => $formid]) ?: null) : null;
    }

    /**
     * Paid orders placed on each of a trainer's forms.
     *
     * @param \stdClass $creator Trainer.
     * @return array formid => number of paid orders
     */
    public static function paid_counts(\stdClass $creator): array {
        global $DB;

        $counts = [];
        foreach (self::forms($creator) as $form) {
            $counts[$form->id] = $DB->count_records_select('local_moderncommerce_orders',
                "status IN ('paid', 'completed') AND " . $DB->sql_like('notes', ':pattern'),
                ['pattern' => '%"orderform":' . (int) $form->id . ',%']);
        }
        return $counts;
    }
}
