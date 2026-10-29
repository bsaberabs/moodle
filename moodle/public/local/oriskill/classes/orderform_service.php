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
            $DB->insert_record('local_oriskill_formoffers', (object) [
                'formid' => $record->id,
                'label' => trim($offer->label),
                'details' => trim((string) $offer->details),
                'courseids' => implode(',', $courseids),
                'price' => round((float) $offer->price, 2),
                'instalcount' => $count,
                'instaltotal' => $count ? round((float) $offer->instaltotal ?: (float) $offer->price, 2) : 0,
                'highlight' => empty($offer->highlight) ? 0 : 1,
                'actags' => activecampaign_service::clean_tags((string) ($offer->actags ?? '')),
                'sortorder' => $index,
            ]);
        }
        return $DB->get_record('local_oriskill_orderforms', ['id' => $record->id]);
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
     * @return \stdClass {price, displayprice, instalments: {count, amount, total, displayamount, displaytotal}|null,
     *                    courses: [{id, name}], details: string[]}
     */
    public static function describe_offer(\stdClass $offer, string $currency): \stdClass {
        global $DB;

        $instalments = null;
        if ($offer->instalcount && (float) $offer->instaltotal > 0) {
            $count = (int) $offer->instalcount;
            $amount = ceil(round((float) $offer->instaltotal * 100 / $count, 4)) / 100;
            $instalments = (object) ['count' => $count, 'amount' => $amount, 'total' => round($amount * $count, 2),
                'displayamount' => pricing_service::format_price($amount, $currency),
                'displaytotal' => pricing_service::format_price($amount * $count, $currency)];
        }
        $courses = [];
        foreach (array_filter(explode(',', $offer->courseids)) as $courseid) {
            if ($name = $DB->get_field('course', 'fullname', ['id' => $courseid])) {
                $courses[] = (object) ['id' => (int) $courseid, 'name' => format_string($name)];
            }
        }
        return (object) [
            'price' => (float) $offer->price,
            'displayprice' => pricing_service::format_price((float) $offer->price, $currency),
            'instalments' => $instalments,
            'courses' => $courses,
            'details' => array_values(array_filter(array_map('trim', explode("\n", (string) $offer->details)))),
        ];
    }

    /**
     * Whether a trainer can take orders on their forms: active, allowed to sell, with a SecureAndPay account.
     *
     * @param \stdClass $creator Trainer.
     * @return bool
     */
    public static function can_sell(\stdClass $creator): bool {
        return $creator->status === 'active' && !seller_service::selling_blocker($creator)
            && seller_service::merchant($creator) !== null;
    }

    /**
     * Create the order of an offer and start its payment.
     *
     * @param int $userid Buyer.
     * @param \stdClass $form Published order form.
     * @param \stdClass $offer Offer of that form.
     * @param string $mode once or instalments.
     * @return array [order, moodle_url where the buyer pays]
     */
    public static function create_order(int $userid, \stdClass $form, \stdClass $offer, string $mode): array {
        global $DB;

        $creator = $DB->get_record('local_oriskill_creators', ['id' => $form->creatorid], '*', MUST_EXIST);
        if ($form->status !== 'published' || (int) $offer->formid !== (int) $form->id || !self::can_sell($creator)) {
            throw new \moodle_exception('error:formunavailable', 'local_oriskill');
        }
        $described = self::describe_offer($offer, pricing_service::get_currency_config()->currency);
        if ($mode === 'instalments' && !$described->instalments) {
            throw new \moodle_exception('error:formunavailable', 'local_oriskill');
        }
        $courseids = array_column($described->courses, 'id');
        $missing = array_filter($courseids, fn($id) => !is_enrolled(\context_course::instance($id), $userid, '', true));
        if (!$missing) {
            throw new \moodle_exception('error:alreadyhascourses', 'local_oriskill');
        }

        $notes = ['orderform' => (int) $form->id, 'offer' => (int) $offer->id, 'returnpath' => '/local/oriskill/thanks.php'];
        if ($mode === 'instalments') {
            $notes['instalments'] = ['count' => $described->instalments->count, 'amount' => $described->instalments->amount];
            $order = order_api::create_course_order($userid, $courseids, $described->instalments->total, $notes);
            $reference = gateway::start_instalments($order);
            return [$order, new moodle_url('/local/secureandpay/pay.php', ['orderid' => $order->id, 'ref' => $reference])];
        }
        $order = order_api::create_course_order($userid, $courseids, $described->price, $notes);
        $start = gateway_manager::initialize_payment(gateway::GATEWAY, $order);
        return [$order, new moodle_url($start['authorization_url'])];
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
