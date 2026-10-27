<?php
// This file is part of Moodle and is licensed under the
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

/**
 * Shared checkout external API data helpers.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\checkout;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\api\cart_api;
use local_moderncommerce\api\coupon_api;
use local_moderncommerce\api\order_api;
use local_moderncommerce\api\pricing_api;
use local_moderncommerce\payment\gateway_manager;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * Shared data builder for buyer cart and checkout endpoints.
 */
class checkout_helper {
    /** @var string User preference storing the buyer's saved checkout contact info (JSON). */
    private const CONTACT_PREF = 'local_moderncommerce_contact';

    /**
     * Return cart data for the current user.
     *
     * @param int $userid User ID.
     * @param bool $refresh Whether to refresh price snapshots.
     * @return array
     */
    public static function cart_data(int $userid, bool $refresh = true): array {
        global $CFG, $SESSION;

        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

        if ($refresh) {
            cart_api::refresh_cart_prices($userid, false);
        }

        $courseitems = cart_api::get_cart_items($userid);
        $bundleitems = cart_api::get_bundle_cart_items($userid);
        $allitems = array_values(array_merge($courseitems, $bundleitems));
        $couponcode = !empty($SESSION->moderncommerce_coupon) ? (string)$SESSION->moderncommerce_coupon : '';
        $calculations = self::calculate_totals($allitems, $couponcode, $userid);
        if (!$calculations['hascoupon']) {
            unset($SESSION->moderncommerce_coupon);
            $couponcode = '';
        }

        $items = [];
        foreach ($courseitems as $item) {
            $items[] = self::course_cart_item($item);
        }
        foreach ($bundleitems as $item) {
            $items[] = self::bundle_cart_item($item);
        }

        return [
            'success' => true,
            'message' => '',
            'isempty' => empty($items),
            'itemcount' => count($items),
            'items' => $items,
            'totals' => self::totals_data($calculations),
            'coupon' => [
                'hascoupon' => $calculations['hascoupon'],
                'code' => $calculations['couponcode'] ?: $couponcode,
                'discount' => pricing_service::format_price((float)$calculations['discount']),
            ],
            'urls' => self::cart_urls(),
            'sesskey' => sesskey(),
        ];
    }

    /**
     * Return checkout data for the current user.
     *
     * @param int $userid User ID.
     * @param \stdClass|null $existingorder Existing pending order.
     * @return array
     */
    public static function checkout_data(int $userid, ?\stdClass $existingorder = null): array {
        global $DB, $SESSION, $USER;

        $cart = $existingorder ? self::empty_cart_data() : self::cart_data($userid, true);
        $items = $existingorder ? self::order_items((int)$existingorder->id) : $cart['items'];
        $calculations = $existingorder ? [
            'subtotal' => (float)$existingorder->subtotal,
            'discount' => (float)$existingorder->discount,
            'tax' => (float)$existingorder->tax,
            'total' => (float)$existingorder->total,
            'hascoupon' => !empty($existingorder->couponcode),
            'couponcode' => (string)($existingorder->couponcode ?? ''),
        ] : [
            'subtotal' => $cart['totals']['subtotalraw'],
            'discount' => $cart['totals']['discountraw'],
            'tax' => $cart['totals']['taxraw'],
            'total' => $cart['totals']['totalraw'],
            'hascoupon' => $cart['coupon']['hascoupon'],
            'couponcode' => $cart['coupon']['code'],
        ];

        $paymentmethods = self::payment_methods();
        $checkouturl = self::learner_app_url($existingorder ? 'checkout?orderid=' . (int)$existingorder->id : 'checkout');

        return [
            'success' => true,
            'message' => '',
            'cancheckout' => !empty($items) || $existingorder !== null,
            'iscontinuepayment' => $existingorder !== null,
            'existingorderid' => $existingorder ? (int)$existingorder->id : 0,
            'existingordernumber' => $existingorder ? (string)$existingorder->ordernumber : '',
            'user' => self::user_data($USER, $existingorder),
            'billingfields' => self::billing_fields(),
            'countries' => self::countries($USER->country ?? 'NG'),
            'paymentmethods' => $paymentmethods,
            'haspaymentmethods' => !empty($paymentmethods),
            'nopaymentmethods' => empty($paymentmethods),
            'items' => $items,
            'itemcount' => count($items),
            'totals' => self::totals_data($calculations),
            'coupon' => [
                'hascoupon' => (bool)$calculations['hascoupon'],
                'code' => (string)$calculations['couponcode'],
                'discount' => pricing_service::format_price((float)$calculations['discount']),
                'editable' => $existingorder === null,
            ],
            'urls' => [
                'cart' => self::learner_app_url('cart'),
                'catalog' => self::learner_app_url('library'),
                'checkout' => $checkouturl,
                'orders' => self::learner_app_url('orders'),
            ],
            'sesskey' => sesskey(),
        ];
    }

    /**
     * Apply or remove a session coupon.
     *
     * @param int $userid User ID.
     * @param string $action apply or remove.
     * @param string $couponcode Coupon code.
     * @return array
     */
    public static function coupon_action(int $userid, string $action, string $couponcode = ''): array {
        global $SESSION;

        if ($action === 'remove') {
            unset($SESSION->moderncommerce_coupon);
            $data = self::cart_data($userid, true);
            $data['message'] = get_string('couponremoved', 'local_moderncommerce');
            return $data;
        }

        $courseitems = cart_api::get_cart_items($userid);
        $bundleitems = cart_api::get_bundle_cart_items($userid);
        $allitems = array_values(array_merge($courseitems, $bundleitems));
        if (empty($allitems)) {
            throw new \moodle_exception('emptycart', 'local_moderncommerce');
        }

        $couponcode = \core_text::strtoupper(trim($couponcode));
        $validation = coupon_api::validate_coupon_result($couponcode, $allitems, $userid);
        if (!$validation['valid']) {
            throw new \moodle_exception($validation['messageid'], 'local_moderncommerce', '', $validation['a']);
        }

        $SESSION->moderncommerce_coupon = $couponcode;
        $data = self::cart_data($userid, true);
        $data['message'] = get_string('couponapplied', 'local_moderncommerce');
        return $data;
    }

    /**
     * Remove one cart item by cart item ID.
     *
     * @param int $userid User ID.
     * @param int $itemid Cart item ID.
     */
    public static function remove_cart_item(int $userid, int $itemid): void {
        global $DB;

        $cart = cart_api::get_active_cart($userid, false);
        if (!$cart) {
            return;
        }

        $item = $DB->get_record('local_moderncommerce_cart_items', [
            'id' => $itemid,
            'cartid' => $cart->id,
        ], '*', IGNORE_MISSING);
        if (!$item) {
            return;
        }

        $product = $DB->get_record('local_moderncommerce_products', ['id' => $item->productid], 'id, producttype');
        if ($product && (string)$product->producttype === 'course') {
            $courseid = (int)$DB->get_field('local_moderncommerce_product_courses', 'courseid', [
                'productid' => $product->id,
                'relationtype' => 'included',
            ]);
            if ($courseid > 0) {
                cart_api::remove_from_cart($userid, $courseid);
                return;
            }
        }

        cart_api::remove_bundle_from_cart($userid, (int)$item->productid);
    }

    /**
     * Return external structure for cart responses.
     *
     * @return external_single_structure
     */
    public static function cart_structure(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether request succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'isempty' => new external_value(PARAM_BOOL, 'Whether the cart is empty.'),
            'itemcount' => new external_value(PARAM_INT, 'Cart item count.'),
            'items' => new external_multiple_structure(self::item_structure()),
            'totals' => self::totals_structure(),
            'coupon' => self::coupon_structure(),
            'urls' => self::cart_url_structure(),
            'sesskey' => new external_value(PARAM_RAW, 'Session key.'),
        ]);
    }

    /**
     * Return external structure for checkout responses.
     *
     * @return external_single_structure
     */
    public static function checkout_structure(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether request succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'cancheckout' => new external_value(PARAM_BOOL, 'Whether checkout can continue.'),
            'iscontinuepayment' => new external_value(PARAM_BOOL, 'Whether paying existing order.'),
            'existingorderid' => new external_value(PARAM_INT, 'Existing order ID.'),
            'existingordernumber' => new external_value(PARAM_TEXT, 'Existing order number.'),
            'user' => self::user_structure(),
            'billingfields' => new external_multiple_structure(self::billing_field_structure()),
            'countries' => new external_multiple_structure(self::country_structure()),
            'paymentmethods' => new external_multiple_structure(self::payment_method_structure()),
            'haspaymentmethods' => new external_value(PARAM_BOOL, 'Whether methods exist.'),
            'nopaymentmethods' => new external_value(PARAM_BOOL, 'Whether no methods exist.'),
            'items' => new external_multiple_structure(self::item_structure()),
            'itemcount' => new external_value(PARAM_INT, 'Item count.'),
            'totals' => self::totals_structure(),
            'coupon' => self::checkout_coupon_structure(),
            'urls' => self::checkout_url_structure(),
            'sesskey' => new external_value(PARAM_RAW, 'Session key.'),
        ]);
    }

    /**
     * Build cart item data.
     *
     * @param \stdClass $item Item record.
     * @return array
     */
    private static function course_cart_item(\stdClass $item): array {
        $imageurl = function_exists('local_moderncommerce_get_course_image_url')
            ? local_moderncommerce_get_course_image_url((int)$item->courseid)
            : '';

        return [
            'id' => (int)$item->id,
            'productid' => (int)$item->productid,
            'courseid' => (int)$item->courseid,
            'itemtype' => 'course',
            'typelabel' => get_string('course', 'local_moderncommerce'),
            'name' => format_string((string)$item->coursename),
            'shortname' => format_string((string)$item->courseshortname),
            'imageurl' => $imageurl,
            'hasimage' => $imageurl !== '',
            'quantity' => (float)$item->quantity,
            'quantitylabel' => format_float((float)$item->quantity, 0),
            'price' => pricing_service::format_price((float)$item->price),
            'linetotal' => pricing_service::format_price((float)$item->price * (float)$item->quantity),
            'detailsurl' => (new moodle_url('/local/moderncommerce/course_details.php', ['id' => $item->courseid]))->out(false),
            'iscourse' => true,
            'isbundle' => false,
            'isprogram' => false,
            'issubscription' => false,
        ];
    }

    /**
     * Build bundle/program cart item data.
     *
     * @param \stdClass $item Item record.
     * @return array
     */
    private static function bundle_cart_item(\stdClass $item): array {
        $type = (string)($item->producttype ?? $item->itemtype ?? 'bundle');
        $imageurl = function_exists('local_moderncommerce_get_bundle_image_url')
            ? local_moderncommerce_get_bundle_image_url((int)$item->bundleid)
            : (string)($item->imageurl ?? '');

        return [
            'id' => (int)$item->id,
            'productid' => (int)$item->productid,
            'courseid' => 0,
            'itemtype' => $type,
            'typelabel' => self::product_type_label($type),
            'name' => format_string((string)($item->bundlename ?: $item->productname)),
            'shortname' => '',
            'imageurl' => $imageurl,
            'hasimage' => $imageurl !== '',
            'quantity' => (float)$item->quantity,
            'quantitylabel' => format_float((float)$item->quantity, 0),
            'price' => pricing_service::format_price((float)$item->price),
            'linetotal' => pricing_service::format_price((float)$item->price * (float)$item->quantity),
            'detailsurl' => (new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $item->bundleid]))->out(false),
            'iscourse' => false,
            'isbundle' => $type === 'bundle',
            'isprogram' => $type === 'program',
            'issubscription' => in_array($type, ['subscription', 'plan'], true),
        ];
    }

    /**
     * Build order item data for continue-payment checkout.
     *
     * @param int $orderid Order ID.
     * @return array
     */
    private static function order_items(int $orderid): array {
        $items = [];
        foreach (order_api::get_order_items($orderid) as $item) {
            $type = (string)($item->producttype ?? $item->itemtype ?? '');
            $items[] = [
                'id' => (int)$item->id,
                'productid' => (int)($item->productid ?? 0),
                'courseid' => (int)($item->courseid ?? 0),
                'itemtype' => $type,
                'typelabel' => self::product_type_label($type),
                'name' => format_string((string)$item->coursename),
                'shortname' => '',
                'imageurl' => (string)($item->imageurl ?? ''),
                'hasimage' => !empty($item->imageurl),
                'quantity' => (float)$item->quantity,
                'quantitylabel' => format_float((float)$item->quantity, 0),
                'price' => pricing_service::format_price((float)$item->unitprice),
                'linetotal' => pricing_service::format_price((float)$item->total),
                'detailsurl' => self::item_details_url($item),
                'iscourse' => !empty($item->courseid) && empty($item->bundleid),
                'isbundle' => $type === 'bundle',
                'isprogram' => $type === 'program',
                'issubscription' => in_array($type, ['subscription', 'plan'], true),
            ];
        }

        return $items;
    }

    /**
     * Calculate totals for cart lines and coupon.
     *
     * @param array $items Cart items.
     * @param string $couponcode Coupon code.
     * @param int $userid User ID.
     * @return array
     */
    private static function calculate_totals(array $items, string $couponcode, int $userid): array {
        $hascoupon = false;
        $couponcode = \core_text::strtoupper(trim($couponcode));

        if ($couponcode !== '' && !empty($items)) {
            $validation = coupon_api::validate_coupon_result($couponcode, $items, $userid);
            $hascoupon = $validation['valid'];
        }

        $calculations = pricing_api::calculate_cart_totals($items, $hascoupon ? $couponcode : null, $hascoupon);
        $calculations['hascoupon'] = $hascoupon;
        $calculations['couponcode'] = $hascoupon ? $couponcode : '';

        return $calculations;
    }

    /**
     * Totals data.
     *
     * @param array $calculations Calculation array.
     * @return array
     */
    private static function totals_data(array $calculations): array {
        $subtotal = (float)($calculations['subtotal'] ?? 0);
        $discount = (float)($calculations['discount'] ?? 0);
        $tax = (float)($calculations['tax'] ?? 0);
        $total = (float)($calculations['total'] ?? 0);

        return [
            'subtotal' => pricing_service::format_price($subtotal),
            'subtotalraw' => $subtotal,
            'hasdiscount' => $discount > 0,
            'discount' => pricing_service::format_price($discount),
            'discountraw' => $discount,
            'hastax' => $tax > 0,
            'tax' => pricing_service::format_price($tax),
            'taxraw' => $tax,
            'total' => pricing_service::format_price($total),
            'totalraw' => $total,
        ];
    }

    /**
     * Build user checkout defaults.
     *
     * @param \stdClass $user User.
     * @param \stdClass|null $existingorder Existing order.
     * @return array
     */
    private static function user_data(\stdClass $user, ?\stdClass $existingorder): array {
        $saved = self::contact_info_enabled() ? self::saved_contact((int)$user->id) : [];

        return [
            'firstname' => (string)$user->firstname,
            'lastname' => (string)$user->lastname,
            'email' => $existingorder && !empty($existingorder->customeremail)
                ? (string)$existingorder->customeremail
                : (string)$user->email,
            'phone' => (string)($saved['phone'] ?? ''),
            'address' => (string)($saved['address'] ?? ''),
            'city' => (string)($saved['city'] ?? ''),
            'state' => (string)($saved['state'] ?? ''),
            'country' => (string)(!empty($saved['country']) ? $saved['country'] : ($user->country ?: 'NG')),
            'zipcode' => (string)($saved['zipcode'] ?? ''),
        ];
    }

    /**
     * Whether contact information is collected at checkout.
     *
     * None of the supported gateways require a billing address and tax is a flat site
     * rate, so the contact block is opt-in via the admin setting.
     *
     * @return bool
     */
    public static function contact_info_enabled(): bool {
        return !empty(get_config('local_moderncommerce', 'contact_info_enabled'));
    }

    /**
     * Persist the buyer's contact info so it prefills on future checkouts.
     *
     * @param int $userid User ID.
     * @param array $billing Billing/contact values.
     */
    public static function save_contact_preference(int $userid, array $billing): void {
        if ($userid <= 0 || !self::contact_info_enabled()) {
            return;
        }

        $contact = [
            'phone' => (string)($billing['phone'] ?? ''),
            'address' => (string)($billing['address'] ?? ''),
            'city' => (string)($billing['city'] ?? ''),
            'state' => (string)($billing['state'] ?? ''),
            'country' => (string)($billing['country'] ?? ''),
            'zipcode' => (string)($billing['zipcode'] ?? ''),
        ];

        set_user_preference(self::CONTACT_PREF, json_encode($contact), $userid);
    }

    /**
     * Load the buyer's saved contact info for prefill.
     *
     * @param int $userid User ID.
     * @return array
     */
    private static function saved_contact(int $userid): array {
        $raw = (string)get_user_preferences(self::CONTACT_PREF, '', $userid);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Billing/contact field configs. Empty when contact collection is disabled.
     *
     * @return array
     */
    private static function billing_fields(): array {
        if (!self::contact_info_enabled()) {
            return [];
        }

        $settings = [
            'phone' => get_config('local_moderncommerce', 'phone_field') ?: 'optional',
            'address' => get_config('local_moderncommerce', 'address_field') ?: 'hidden',
            'city' => get_config('local_moderncommerce', 'city_field') ?: 'hidden',
            'state' => get_config('local_moderncommerce', 'state_field') ?: 'hidden',
            'country' => get_config('local_moderncommerce', 'country_field') ?: 'hidden',
            'zipcode' => get_config('local_moderncommerce', 'zipcode_field') ?: 'hidden',
        ];

        $fields = [];
        foreach ($settings as $field => $setting) {
            if ($setting === 'hidden') {
                continue;
            }
            $fields[] = [
                'field' => $field,
                'label' => get_string($field, 'local_moderncommerce'),
                'required' => $setting === 'required',
                'type' => $field === 'phone' ? 'tel' : 'text',
            ];
        }

        return $fields;
    }

    /**
     * Country choices.
     *
     * @param string $selected Selected code.
     * @return array
     */
    private static function countries(string $selected): array {
        $selected = strtoupper($selected ?: 'NG');
        $countrylist = get_string_manager()->get_list_of_countries();
        if (!array_key_exists($selected, $countrylist)) {
            $selected = array_key_exists('NG', $countrylist) ? 'NG' : '';
        }

        $countries = [];
        foreach ($countrylist as $code => $name) {
            $countries[] = [
                'code' => $code,
                'name' => $name,
                'selected' => $code === $selected,
            ];
        }

        return $countries;
    }

    /**
     * Payment methods.
     *
     * @return array
     */
    private static function payment_methods(): array {
        $methods = [];
        foreach (gateway_manager::get_payment_methods() as $id => $method) {
            $methods[] = [
                'id' => (string)$id,
                'name' => (string)$method['name'],
                'description' => (string)($method['description'] ?? ''),
                'icon' => (string)($method['icon'] ?? 'credit-card-2-front'),
                'methodtype' => (string)($method['methodtype'] ?? 'redirect'),
            ];
        }

        return $methods;
    }

    /**
     * Product type label.
     *
     * @param string $type Product type.
     * @return string
     */
    private static function product_type_label(string $type): string {
        if ($type === '') {
            return get_string('item', 'local_moderncommerce');
        }

        return get_string_manager()->string_exists($type, 'local_moderncommerce')
            ? get_string($type, 'local_moderncommerce')
            : ucfirst($type);
    }

    /**
     * Details URL for an order item.
     *
     * @param \stdClass $item Order item.
     * @return string
     */
    private static function item_details_url(\stdClass $item): string {
        if (!empty($item->bundleid)) {
            return (new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $item->bundleid]))->out(false);
        }
        if (!empty($item->courseid)) {
            return (new moodle_url('/local/moderncommerce/course_details.php', ['id' => $item->courseid]))->out(false);
        }

        return '#';
    }

    /**
     * Cart URLs.
     *
     * @return array
     */
    private static function cart_urls(): array {
        return [
            'catalog' => self::learner_app_url('library'),
            'cart' => self::learner_app_url('cart'),
            'checkout' => self::learner_app_url('checkout'),
        ];
    }

    /**
     * Build a learner app hash route URL.
     *
     * @param string $route Route without leading hash.
     * @return string
     */
    private static function learner_app_url(string $route): string {
        return (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/' . ltrim($route, '/');
    }

    /**
     * Empty cart data for continue-payment checkout.
     *
     * @return array
     */
    private static function empty_cart_data(): array {
        return [
            'items' => [],
            'totals' => [
                'subtotalraw' => 0,
                'discountraw' => 0,
                'taxraw' => 0,
                'totalraw' => 0,
            ],
            'coupon' => [
                'hascoupon' => false,
                'code' => '',
            ],
        ];
    }

    /**
     * Item structure.
     *
     * @return external_single_structure
     */
    private static function item_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Cart or order item ID.'),
            'productid' => new external_value(PARAM_INT, 'Product ID.'),
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'itemtype' => new external_value(PARAM_ALPHANUMEXT, 'Item type.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Type label.'),
            'name' => new external_value(PARAM_TEXT, 'Item name.'),
            'shortname' => new external_value(PARAM_TEXT, 'Short name.'),
            'imageurl' => new external_value(PARAM_RAW, 'Image URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether image exists.'),
            'quantity' => new external_value(PARAM_FLOAT, 'Quantity.'),
            'quantitylabel' => new external_value(PARAM_TEXT, 'Quantity label.'),
            'price' => new external_value(PARAM_TEXT, 'Formatted unit price.'),
            'linetotal' => new external_value(PARAM_TEXT, 'Formatted line total.'),
            'detailsurl' => new external_value(PARAM_RAW, 'Details URL.'),
            'iscourse' => new external_value(PARAM_BOOL, 'Whether course item.'),
            'isbundle' => new external_value(PARAM_BOOL, 'Whether bundle item.'),
            'isprogram' => new external_value(PARAM_BOOL, 'Whether program item.'),
            'issubscription' => new external_value(PARAM_BOOL, 'Whether subscription item.'),
        ]);
    }

    /**
     * Totals structure.
     *
     * @return external_single_structure
     */
    private static function totals_structure(): external_single_structure {
        return new external_single_structure([
            'subtotal' => new external_value(PARAM_TEXT, 'Formatted subtotal.'),
            'subtotalraw' => new external_value(PARAM_FLOAT, 'Raw subtotal.'),
            'hasdiscount' => new external_value(PARAM_BOOL, 'Whether discount exists.'),
            'discount' => new external_value(PARAM_TEXT, 'Formatted discount.'),
            'discountraw' => new external_value(PARAM_FLOAT, 'Raw discount.'),
            'hastax' => new external_value(PARAM_BOOL, 'Whether tax exists.'),
            'tax' => new external_value(PARAM_TEXT, 'Formatted tax.'),
            'taxraw' => new external_value(PARAM_FLOAT, 'Raw tax.'),
            'total' => new external_value(PARAM_TEXT, 'Formatted total.'),
            'totalraw' => new external_value(PARAM_FLOAT, 'Raw total.'),
        ]);
    }

    /**
     * Cart coupon structure.
     *
     * @return external_single_structure
     */
    private static function coupon_structure(): external_single_structure {
        return new external_single_structure([
            'hascoupon' => new external_value(PARAM_BOOL, 'Whether coupon is applied.'),
            'code' => new external_value(PARAM_TEXT, 'Coupon code.'),
            'discount' => new external_value(PARAM_TEXT, 'Formatted discount.'),
        ]);
    }

    /**
     * Checkout coupon structure.
     *
     * @return external_single_structure
     */
    private static function checkout_coupon_structure(): external_single_structure {
        return new external_single_structure([
            'hascoupon' => new external_value(PARAM_BOOL, 'Whether coupon is applied.'),
            'code' => new external_value(PARAM_TEXT, 'Coupon code.'),
            'discount' => new external_value(PARAM_TEXT, 'Formatted discount.'),
            'editable' => new external_value(PARAM_BOOL, 'Whether coupon can be edited.'),
        ]);
    }

    /**
     * Cart URL structure.
     *
     * @return external_single_structure
     */
    private static function cart_url_structure(): external_single_structure {
        return new external_single_structure([
            'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
            'cart' => new external_value(PARAM_RAW, 'Cart URL.'),
            'checkout' => new external_value(PARAM_RAW, 'Checkout URL.'),
        ]);
    }

    /**
     * Checkout URL structure.
     *
     * @return external_single_structure
     */
    private static function checkout_url_structure(): external_single_structure {
        return new external_single_structure([
            'cart' => new external_value(PARAM_RAW, 'Cart URL.'),
            'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
            'checkout' => new external_value(PARAM_RAW, 'Checkout URL.'),
            'orders' => new external_value(PARAM_RAW, 'Orders URL.'),
        ]);
    }

    /**
     * User structure.
     *
     * @return external_single_structure
     */
    private static function user_structure(): external_single_structure {
        return new external_single_structure([
            'firstname' => new external_value(PARAM_TEXT, 'First name.'),
            'lastname' => new external_value(PARAM_TEXT, 'Last name.'),
            'email' => new external_value(PARAM_EMAIL, 'Email.'),
            'phone' => new external_value(PARAM_TEXT, 'Phone.'),
            'address' => new external_value(PARAM_TEXT, 'Address.'),
            'city' => new external_value(PARAM_TEXT, 'City.'),
            'state' => new external_value(PARAM_TEXT, 'State.'),
            'country' => new external_value(PARAM_TEXT, 'Country.'),
            'zipcode' => new external_value(PARAM_TEXT, 'Zip code.'),
        ]);
    }

    /**
     * Billing field structure.
     *
     * @return external_single_structure
     */
    private static function billing_field_structure(): external_single_structure {
        return new external_single_structure([
            'field' => new external_value(PARAM_ALPHANUMEXT, 'Field key.'),
            'label' => new external_value(PARAM_TEXT, 'Field label.'),
            'required' => new external_value(PARAM_BOOL, 'Whether required.'),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Input type.'),
        ]);
    }

    /**
     * Country structure.
     *
     * @return external_single_structure
     */
    private static function country_structure(): external_single_structure {
        return new external_single_structure([
            'code' => new external_value(PARAM_ALPHA, 'Country code.'),
            'name' => new external_value(PARAM_TEXT, 'Country name.'),
            'selected' => new external_value(PARAM_BOOL, 'Whether selected.'),
        ]);
    }

    /**
     * Payment method structure.
     *
     * @return external_single_structure
     */
    private static function payment_method_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_ALPHANUMEXT, 'Method ID.'),
            'name' => new external_value(PARAM_TEXT, 'Method name.'),
            'description' => new external_value(PARAM_TEXT, 'Description.'),
            'icon' => new external_value(PARAM_TEXT, 'Icon class.'),
            'methodtype' => new external_value(PARAM_ALPHANUMEXT, 'Method type.'),
        ]);
    }
}
