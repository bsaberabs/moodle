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

use local_secureandpay\merchant;

/**
 * A trainer as a seller: their payment accounts, whether they may sell, their prices and their sales.
 *
 * Trainers are paid directly into their own accounts: SecureAndPay, and Stripe or PayPal if they choose to
 * (local_oriskill_gateways). Oriskill earns through a trainer subscription plan (setting
 * local_oriskill/creatorplancode), required to sell when it is set.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class seller_service {

    /** Gateways a trainer can connect with their own account, besides SecureAndPay. */
    const GATEWAYS = ['stripe', 'paypal'];

    /**
     * Trainer whose category holds a course, or null for a platform course.
     *
     * @param int $courseid Course.
     * @return \stdClass|null
     */
    public static function owner_of_course(int $courseid): ?\stdClass {
        global $DB;

        $path = $DB->get_field_sql('SELECT cc.path FROM {course} c JOIN {course_categories} cc ON cc.id = c.category
                                     WHERE c.id = ?', [$courseid]);
        if (!$path) {
            return null;
        }
        $categoryids = array_filter(array_map('intval', explode('/', $path)));
        [$insql, $params] = $DB->get_in_or_equal($categoryids);
        return $DB->get_record_select('local_oriskill_creators', "categoryid $insql", $params, '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Trainer's SecureAndPay account, if configured.
     *
     * @param \stdClass $creator Trainer.
     * @return merchant|null
     */
    public static function merchant(\stdClass $creator): ?merchant {
        if (empty($creator->sap_siteid) || empty($creator->sap_key)) {
            return null;
        }
        return new merchant((string) $creator->sap_siteid, \core\encryption::decrypt($creator->sap_key),
            (bool) $creator->sap_testmode, 'creator:' . $creator->id);
    }

    /**
     * Save the trainer's SecureAndPay account. The key is stored encrypted; an empty key keeps the current one.
     *
     * @param \stdClass $creator Trainer.
     * @param string $siteid IdSite.
     * @param string $key Signing key, or '' to keep the saved one.
     * @param bool $testmode Test environment.
     */
    public static function save_payment_settings(\stdClass $creator, string $siteid, string $key, bool $testmode): void {
        global $DB;

        $record = (object) ['id' => $creator->id, 'sap_siteid' => trim($siteid), 'sap_testmode' => (int) $testmode,
            'timemodified' => time()];
        if ($key !== '') {
            $record->sap_key = \core\encryption::encrypt(trim($key));
        }
        $DB->update_record('local_oriskill_creators', $record);
    }

    /**
     * The trainer's account for a gateway (Stripe or PayPal), if saved.
     *
     * @param \stdClass $creator Trainer.
     * @param string $gateway Gateway ID.
     * @return \stdClass|null
     */
    public static function gateway_account(\stdClass $creator, string $gateway): ?\stdClass {
        global $DB;
        return $DB->get_record('local_oriskill_gateways', ['creatorid' => $creator->id, 'gateway' => $gateway]) ?: null;
    }

    /**
     * Whether a saved gateway account has everything needed to take payments.
     *
     * @param \stdClass|null $account Account from gateway_account().
     * @return bool
     */
    public static function gateway_account_complete(?\stdClass $account): bool {
        return $account && !empty($account->secretkey) && !empty($account->webhooksecret)
            && ($account->gateway !== 'paypal' || !empty($account->publickey));
    }

    /**
     * Stripe / PayPal gateways the trainer has turned on with a complete account.
     *
     * @param \stdClass $creator Trainer.
     * @return string[] Gateway IDs.
     */
    public static function usable_gateways(\stdClass $creator): array {
        return array_values(array_filter(self::GATEWAYS, function($gateway) use ($creator) {
            $account = self::gateway_account($creator, $gateway);
            return $account && $account->enabled && self::gateway_account_complete($account);
        }));
    }

    /**
     * Save the trainer's account for a gateway. Secrets are stored encrypted; an empty secret keeps the saved one.
     *
     * @param \stdClass $creator Trainer.
     * @param string $gateway Gateway ID (stripe or paypal).
     * @param bool $enabled Offer this gateway on the trainer's courses.
     * @param bool $testmode Test keys (Stripe) or sandbox (PayPal).
     * @param string $publickey Stripe publishable key or PayPal client ID.
     * @param string $secretkey Stripe secret key or PayPal secret, '' to keep.
     * @param string $webhooksecret Stripe signing secret or PayPal webhook ID, '' to keep.
     */
    public static function save_gateway_account(\stdClass $creator, string $gateway, bool $enabled, bool $testmode,
            string $publickey, string $secretkey, string $webhooksecret): void {
        global $DB;

        if (!in_array($gateway, self::GATEWAYS)) {
            throw new \coding_exception('Unknown gateway ' . $gateway);
        }
        $record = ['enabled' => (int) $enabled, 'testmode' => (int) $testmode, 'publickey' => trim($publickey),
            'timemodified' => time()];
        if (trim($secretkey) !== '') {
            $record['secretkey'] = \core\encryption::encrypt(trim($secretkey));
        }
        if (trim($webhooksecret) !== '') {
            $record['webhooksecret'] = \core\encryption::encrypt(trim($webhooksecret));
        }
        if ($current = self::gateway_account($creator, $gateway)) {
            $DB->update_record('local_oriskill_gateways', (object) (['id' => $current->id] + $record));
        } else {
            $DB->insert_record('local_oriskill_gateways', (object) (['creatorid' => $creator->id, 'gateway' => $gateway]
                + $record));
        }
    }

    /**
     * Gateway settings for a purchase of the trainer's courses (local_moderncommerce resolve_gateway_config).
     *
     * The store's keys are never kept: without a usable account of the trainer the gateway is turned off.
     *
     * @param \stdClass $creator Trainer.
     * @param string $gateway Gateway ID.
     * @param array $storeconfig The store's settings for the gateway (currencies and other options are kept).
     * @return array
     */
    public static function gateway_config(\stdClass $creator, string $gateway, array $storeconfig): array {
        $account = in_array($gateway, self::GATEWAYS) ? self::gateway_account($creator, $gateway) : null;
        $usable = $account && $account->enabled && self::gateway_account_complete($account)
            && self::selling_blocker($creator) === null;
        $secret = $usable ? \core\encryption::decrypt($account->secretkey) : '';
        $webhook = $usable ? \core\encryption::decrypt($account->webhooksecret) : '';
        $testmode = (bool) ($account->testmode ?? true);
        return array_merge($storeconfig, [
            'enabled' => $usable,
            'testmode' => $testmode,
            'sandbox_mode' => $testmode,
            'public_key' => $usable ? (string) $account->publickey : '',
            'client_id' => $usable ? (string) $account->publickey : '',
            'merchantid' => $usable ? (string) $account->publickey : '',
            'secret_key' => $secret,
            'webhook_secret' => $webhook,
            'webhook_id' => $webhook,
            'secret_hash' => $webhook,
        ]);
    }

    /**
     * Sellers of a purchase: trainer id => trainer record, 0 => null for Oriskill's own courses.
     *
     * @param object|null $order Order, or null for the buyer's cart.
     * @param int $userid Buyer, for the cart.
     * @return array
     */
    public static function purchase_sellers(?object $order, int $userid): array {
        global $DB;

        if ($order) {
            $courseids = $DB->get_fieldset_select('local_moderncommerce_order_items', 'courseid',
                'orderid = ? AND courseid IS NOT NULL', [$order->id]);
        } else if ($userid && !isguestuser($userid)) {
            $courseids = array_column(\local_moderncommerce\api\cart_api::get_cart_items($userid), 'courseid');
        } else {
            return [];
        }
        $sellers = [];
        foreach (array_filter(array_map('intval', $courseids)) as $courseid) {
            $owner = self::owner_of_course($courseid);
            $sellers[$owner->id ?? 0] = $owner;
        }
        return $sellers;
    }

    /**
     * Why a trainer cannot sell right now, or null if they can.
     *
     * @param \stdClass $creator Trainer.
     * @return string|null Language string key in local_oriskill.
     */
    public static function selling_blocker(\stdClass $creator): ?string {
        if ($creator->status !== 'active') {
            return 'blocker:inactive';
        }
        if (!self::merchant($creator) && !self::usable_gateways($creator)) {
            return 'blocker:nopayment';
        }
        $plan = self::creator_plan();
        if ($plan && !self::has_plan((int) $creator->userid, (int) $plan->id)) {
            return 'blocker:nosubscription';
        }
        return null;
    }

    /**
     * Subscription plan a trainer must hold to sell, if one is set.
     *
     * @return \stdClass|null
     */
    public static function creator_plan(): ?\stdClass {
        global $DB;
        $code = trim((string) get_config('local_oriskill', 'creatorplancode'));
        return $code === '' ? null
            : ($DB->get_record('local_moderncommerce_subscription_plans', ['code' => $code, 'status' => 'active']) ?: null);
    }

    /**
     * Whether a user holds a plan (trial and grace included).
     *
     * @param int $userid User.
     * @param int $planid Plan.
     * @return bool
     */
    private static function has_plan(int $userid, int $planid): bool {
        global $DB;
        return $DB->record_exists_select('local_moderncommerce_user_subscriptions',
            "userid = ? AND planid = ? AND status IN ('active', 'trial', 'grace')", [$userid, $planid]);
    }

    /**
     * Courses of a trainer (all of them, hidden ones included), with their current Modern Commerce price.
     *
     * @param \stdClass $creator Trainer.
     * @return array courseid => {course, productid, amount, compareamount, onsale}
     */
    public static function priced_courses(\stdClass $creator): array {
        global $DB;

        $path = $DB->get_field('course_categories', 'path', ['id' => $creator->categoryid]);
        if (!$path) {
            return [];
        }
        $courses = $DB->get_records_sql(
            "SELECT c.* FROM {course} c JOIN {course_categories} cc ON cc.id = c.category
              WHERE cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':path') . " ORDER BY c.fullname",
            ['categoryid' => $creator->categoryid, 'path' => $path . '/%']);
        $result = [];
        foreach ($courses as $course) {
            $product = $DB->get_record_sql(
                "SELECT p.id, p.status, pp.amount, pp.compareamount
                   FROM {local_moderncommerce_products} p
                   JOIN {local_moderncommerce_product_courses} pc ON pc.productid = p.id
              LEFT JOIN {local_moderncommerce_product_prices} pp ON pp.productid = p.id AND pp.pricetype = 'regular'
                  WHERE pc.courseid = ? AND p.producttype = 'course'", [$course->id], IGNORE_MULTIPLE);
            $result[$course->id] = (object) [
                'course' => $course,
                'productid' => $product->id ?? null,
                'amount' => isset($product->amount) ? (float) $product->amount : null,
                'compareamount' => isset($product->compareamount) ? (float) $product->compareamount : null,
                'onsale' => ($product->status ?? '') === 'active',
            ];
        }
        return $result;
    }

    /**
     * Number of learners in each of the trainer's courses (the trainer excluded).
     *
     * @param \stdClass $creator Trainer.
     * @param int[] $courseids Courses of the trainer.
     * @return array courseid => number of learners
     */
    public static function learner_counts(\stdClass $creator, array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        // Neither the trainer nor their collaborators are learners.
        [$staffsql, $staffparams] = $DB->get_in_or_equal(
            array_merge([(int) $creator->userid], team_service::member_userids($creator)), SQL_PARAMS_NAMED, 'staff', false);
        return $DB->get_records_sql_menu(
            "SELECT e.courseid, COUNT(DISTINCT ue.userid)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid $insql AND ue.userid $staffsql
           GROUP BY e.courseid", $params + $staffparams);
    }

    /**
     * Set the price of one of the trainer's courses, creating its Modern Commerce product on first use.
     *
     * @param \stdClass $creator Trainer.
     * @param int $courseid Course, which must belong to the trainer.
     * @param float $amount Price.
     * @param float|null $compareamount Crossed-out price, or null.
     * @param bool $onsale Product active in the store.
     */
    public static function set_course_price(\stdClass $creator, int $courseid, float $amount, ?float $compareamount,
            bool $onsale): void {
        global $DB;

        $owner = self::owner_of_course($courseid);
        if (!$owner || (int) $owner->id !== (int) $creator->id) {
            throw new \moodle_exception('nopermissions', 'error', '', 'price');
        }
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $now = time();
        $current = self::priced_courses($creator)[$courseid] ?? null;
        $status = $onsale ? 'active' : 'draft';

        $productid = $current->productid ?? null;
        if (!$productid) {
            $productid = $DB->insert_record('local_moderncommerce_products', (object) [
                'producttype' => 'course', 'name' => $course->fullname, 'slug' => 'ori-' . $creator->id . '-' . $courseid,
                'sku' => 'ORI-' . $creator->id . '-' . $courseid, 'status' => $status, 'visible' => 1, 'featured' => 0,
                'shortdescription' => shorten_text(html_to_text($course->summary, 0), 250), 'taxable' => 1,
                'taxcategory' => 'standard', 'enrolduration' => 0, 'currentenrollment' => 0, 'displayorder' => 100,
                'createdby' => $creator->userid, 'modifiedby' => $creator->userid, 'timecreated' => $now, 'timemodified' => $now,
            ]);
            $DB->insert_record('local_moderncommerce_product_courses', (object) ['productid' => $productid,
                'courseid' => $courseid, 'relationtype' => 'included', 'sortorder' => 10, 'required' => 1, 'timecreated' => $now]);
            $DB->insert_record('local_moderncommerce_product_inventory', (object) ['productid' => $productid,
                'stockmanaged' => 0, 'reservedstock' => 0, 'allowbackorder' => 0, 'timemodified' => $now]);
        } else {
            $DB->update_record('local_moderncommerce_products', (object) ['id' => $productid, 'name' => $course->fullname,
                'status' => $status, 'modifiedby' => $creator->userid, 'timemodified' => $now]);
        }

        $price = $DB->get_record('local_moderncommerce_product_prices', ['productid' => $productid, 'pricetype' => 'regular']);
        $values = ['amount' => $amount, 'compareamount' => $compareamount, 'enabled' => 1, 'timemodified' => $now];
        if ($price) {
            $DB->update_record('local_moderncommerce_product_prices', (object) (['id' => $price->id] + $values));
        } else {
            $DB->insert_record('local_moderncommerce_product_prices', (object) ($values + ['productid' => $productid,
                'pricetype' => 'regular', 'minquantity' => 1, 'timecreated' => $now]));
        }
    }

    /**
     * Paid sales of the trainer's courses, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @return array Rows: ordernumber, status, timepaid, buyer firstname/lastname, itemname, total, currency.
     */
    public static function sales(\stdClass $creator): array {
        global $DB;

        $path = $DB->get_field('course_categories', 'path', ['id' => $creator->categoryid]);
        if (!$path) {
            return [];
        }
        return array_values($DB->get_records_sql(
            "SELECT oi.id, o.ordernumber, o.status, COALESCE(o.timepaid, o.timecreated) AS timepaid,
                    u.firstname, u.lastname, oi.itemname, oi.total, oi.currency
               FROM {local_moderncommerce_order_items} oi
               JOIN {local_moderncommerce_orders} o ON o.id = oi.orderid
               JOIN {course} c ON c.id = oi.courseid
               JOIN {course_categories} cc ON cc.id = c.category
               JOIN {user} u ON u.id = o.userid
              WHERE o.status IN ('paid', 'completed', 'refunded')
                AND (cc.id = :categoryid OR " . $DB->sql_like('cc.path', ':path') . ")
           ORDER BY COALESCE(o.timepaid, o.timecreated) DESC",
            ['categoryid' => $creator->categoryid, 'path' => $path . '/%']));
    }
}
