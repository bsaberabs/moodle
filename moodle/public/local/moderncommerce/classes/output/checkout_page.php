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
 * Checkout page renderable.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;


use renderable;
use templatable;
use renderer_base;
use moodle_url;
use stdClass;
use local_moderncommerce\api\cart_api;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\api\order_api;
use local_moderncommerce\api\pricing_api;
use local_moderncommerce\api\coupon_api;
use local_moderncommerce\services\pricing_service;
use local_moderncommerce\payment\gateway_manager;
/**
 * Checkout page renderable class.
 */
class checkout_page implements renderable, templatable {
    /** @var int User ID */
    protected $userid;

    /** @var object User object */
    protected $user;

    /** @var array Course cart items */
    protected $cartitems;

    /** @var array Bundle cart items */
    protected $bundleitems;

    /** @var string|null Applied coupon code */
    protected $couponcode;

    /** @var array Calculations */
    protected $calculations;

    /** @var string Session key */
    protected $sesskey;

    /** @var object|null Existing pending order for continue payment */
    protected $existingorder;

    /**
     * Constructor.
     *
     * @param object $user User object
     * @param string|null $couponcode Applied coupon code
     * @param object|null $existingorder Existing pending order (for continue payment)
     */
    public function __construct(object $user, ?string $couponcode = null, ?object $existingorder = null) {
        $this->user = $user;
        $this->userid = $user->id;
        $this->couponcode = $couponcode;
        $this->existingorder = $existingorder;
        $this->sesskey = sesskey();
        $this->load_cart_items();
        $this->calculate_totals();
    }

    /**
     * Load and process cart items.
     */
    protected function load_cart_items(): void {

        // If continuing payment on an existing order, load items from that order.
        if ($this->existingorder) {
            $this->cartitems = [];
            $this->bundleitems = [];
            return;
        }

        $this->cartitems = cart_api::get_cart_items($this->userid);
        $this->bundleitems = cart_api::get_bundle_cart_items($this->userid);
    }
    /**
     * Calculate cart totals.
     */
    protected function calculate_totals(): void {
        // If continuing payment on an existing order, use its totals.
        if ($this->existingorder) {
            $this->calculations = [
                'subtotal' => $this->existingorder->subtotal,
                'discount' => $this->existingorder->discount,
                'tax' => $this->existingorder->tax,
                'total' => $this->existingorder->total,
            ];
            return;
        }

        $allcartitems = array_merge($this->cartitems, $this->bundleitems);
        $this->calculations = pricing_api::calculate_cart_totals($allcartitems, $this->couponcode);
    }

    /**
     * Get billing field configuration.
     *
     * @return array
     */
    protected function get_billing_fields(): array {
        return [
            'phone' => get_config('local_moderncommerce', 'phone_field') ?: 'required',
            'address' => get_config('local_moderncommerce', 'address_field') ?: 'required',
            'city' => get_config('local_moderncommerce', 'city_field') ?: 'required',
            'state' => get_config('local_moderncommerce', 'state_field') ?: 'optional',
            'country' => get_config('local_moderncommerce', 'country_field') ?: 'required',
            'zipcode' => get_config('local_moderncommerce', 'zipcode_field') ?: 'optional',
        ];
    }

    /**
     * Get enabled payment methods.
     *
     * @return array
     */
    protected function get_payment_methods(): array {

        return gateway_manager::get_payment_methods();
    }
    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $SESSION, $DB;

        $checkouturl = new moodle_url('/local/moderncommerce/checkout.php');
        if ($this->existingorder) {
            $checkouturl->param('orderid', $this->existingorder->id);
        }

        $data = [
            'sesskey' => $this->sesskey,
            'checkouturl' => $checkouturl->out(false),
            'carturl' => (new moodle_url('/local/moderncommerce/cart.php'))->out(false),
            'iscontinuepayment' => !empty($this->existingorder),
            'existingorderid' => $this->existingorder ? $this->existingorder->id : null,
            'existingordernumber' => $this->existingorder ? $this->existingorder->ordernumber : null,
        ];

        // User info - use billing info from existing order if available.
        if ($this->existingorder) {
            $data['user'] = [
                'firstname' => $this->user->firstname,
                'lastname' => $this->user->lastname,
                'email' => $this->existingorder->customeremail ?: $this->user->email,
                'phone' => '',
                'address' => '',
                'city' => '',
                'state' => '',
                'country' => '',
                'zipcode' => '',
            ];
        } else {
            // Try to get billing info from user's last completed order.
            $lastorders = $DB->get_records_sql(
                "SELECT id, customeremail
                 FROM {local_moderncommerce_orders}
                 WHERE userid = :userid AND status IN ('paid', 'completed')
                 ORDER BY timecreated DESC",
                ['userid' => $this->userid],
                0,
                1
            );
            $lastorder = !empty($lastorders) ? reset($lastorders) : null;
            if ($lastorder && !empty($lastorder->customeremail)) {
                $data['user'] = [
                    'firstname' => $this->user->firstname,
                    'lastname' => $this->user->lastname,
                    'email' => $lastorder->customeremail ?: $this->user->email,
                    'phone' => '',
                    'address' => '',
                    'city' => '',
                    'state' => '',
                    'country' => '',
                    'zipcode' => '',
                ];
            } else {
                $data['user'] = [
                'firstname' => $this->user->firstname,
                'lastname' => $this->user->lastname,
                'email' => $this->user->email,
                'phone' => '',
                'address' => '',
                'city' => '',
                'state' => '',
                'country' => '',
                'zipcode' => '',
                ];
            }
        }

        // Billing fields.
        $billingfields = $this->get_billing_fields();
        $data['billingfields'] = [];
        $hasbillingfields = false;

        foreach ($billingfields as $field => $setting) {
            if ($setting !== 'hidden') {
                $hasbillingfields = true;
                $data['billingfields'][] = [
                    'field' => $field,
                    'label' => get_string($field, 'local_moderncommerce'),
                    'required' => $setting === 'required',
                    'isphone' => $field === 'phone',
                    'isaddress' => $field === 'address',
                    'iscity' => $field === 'city',
                    'isstate' => $field === 'state',
                    'iscountry' => $field === 'country',
                    'iszipcode' => $field === 'zipcode',
                ];
            }
        }
        $data['hasbillingfields'] = $hasbillingfields;

        // Countries for dropdown - select user's saved country if available.
        $usercountry = strtoupper((string)($data['user']['country'] ?? 'NG'));
        $countrylist = get_string_manager()->get_list_of_countries();
        if (!array_key_exists($usercountry, $countrylist)) {
            $usercountry = array_key_exists('NG', $countrylist) ? 'NG' : '';
        }
        $data['countries'] = [];
        foreach ($countrylist as $code => $name) {
            $data['countries'][] = [
                'code' => $code,
                'name' => $name,
                'selected' => ($code === $usercountry),
            ];
        }

        // Payment methods.
        $paymentmethods = $this->get_payment_methods();
        $data['paymentmethods'] = [];
        $firstmethod = null;

        foreach ($paymentmethods as $id => $method) {
            if ($firstmethod === null) {
                $firstmethod = $id;
            }
            $method['checked'] = ($id === $firstmethod);
            $data['paymentmethods'][] = $method;
        }

        $data['haspaymentmethods'] = !empty($paymentmethods);
        $data['nopaymentmethods'] = empty($paymentmethods);

        // Cart items - load from existing order if continuing payment.
        $items = [];

        if ($this->existingorder) {
            // Load items from the existing order.
            $orderitems = order_api::get_order_items((int) $this->existingorder->id);
            foreach ($orderitems as $orderitem) {
                $iscourse = !empty($orderitem->courseid);
                $isbundle = !empty($orderitem->bundleid);
                $issubscription = !empty($orderitem->planid);
                $itemname = format_string(
                    $orderitem->coursename ?? $orderitem->itemname ?? get_string('item', 'local_moderncommerce')
                );

                $items[] = [
                    'name' => $itemname, 'iscourse' => $iscourse,
                    'isbundle' => $isbundle,
                    'issubscription' => $issubscription,
                    'quantity' => $orderitem->quantity,
                    'price' => pricing_service::format_price($orderitem->unitprice),
                    'linetotal' => pricing_service::format_price($orderitem->total),
                ];
            }
        } else {
            // Load from cart.
            foreach ($this->cartitems as $item) {
                $items[] = [
                    'name' => format_string($item->coursename),
                    'iscourse' => true,
                    'isbundle' => false,
                    'quantity' => $item->quantity,
                    'price' => pricing_service::format_price($item->price),
                    'linetotal' => pricing_service::format_price($item->price * $item->quantity),
                ];
            }

            foreach ($this->bundleitems as $item) {
                $items[] = [
                    'name' => format_string($item->bundlename),
                    'iscourse' => false,
                    'isbundle' => true,
                    'quantity' => $item->quantity,
                    'price' => pricing_service::format_price($item->price),
                    'linetotal' => pricing_service::format_price($item->price * $item->quantity),
                    'isadjusted' => true,
                ];
            }
        }

        $data['items'] = $items;
        $data['hasitems'] = !empty($items);
        $data['itemcount'] = count($items);

        // Coupon - not applicable for continue payment (order already has its pricing).
        $data['showcouponform'] = !$this->existingorder;
        $appliedcoupon = null;

        if ($this->existingorder && !empty($this->existingorder->couponcode)) {
            // Show the coupon already applied to the order.
            $data['hascoupon'] = true;
            $data['coupon'] = [
                'code' => $this->existingorder->couponcode,
                'removeurl' => null, // Cannot remove coupon from existing order.
            ];
        } else if (!$this->existingorder && !empty($this->couponcode)) {
            try {
                $allcartitems = array_values(array_merge($this->cartitems, $this->bundleitems));
                $appliedcoupon = coupon_api::validate_coupon($this->couponcode, $allcartitems, $this->userid);
            } catch (\Exception $e) {
                $appliedcoupon = null;
            }

            $data['hascoupon'] = !empty($appliedcoupon);
            if ($appliedcoupon) {
                $data['coupon'] = [
                    'code' => $appliedcoupon->code,
                    'removeurl' => (new moodle_url('/local/moderncommerce/checkout.php', [
                        'action' => 'removecoupon',
                        'sesskey' => $this->sesskey,
                    ]))->out(false),
                ];
            }
            if (!$appliedcoupon) {
                unset($SESSION->moderncommerce_coupon);
            }
        } else {
            $data['hascoupon'] = false;
        }
        // Calculations.
        $data['subtotal'] = pricing_service::format_price($this->calculations['subtotal']);
        $data['hasdiscount'] = $this->calculations['discount'] > 0;
        $data['discount'] = pricing_service::format_price($this->calculations['discount']);
        $data['hastax'] = $this->calculations['tax'] > 0;
        $data['tax'] = pricing_service::format_price($this->calculations['tax']);
        $data['total'] = pricing_service::format_price($this->calculations['total']);

        return $data;
    }
}
