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
 * External API for learner order detail.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\api\order_api;
use local_moderncommerce\localisation;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * Returns a single order for the logged-in learner.
 */
class get_order extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Order ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Order ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB, $USER;

        ['id' => $id] = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);

        $order = order_api::get_order($id);
        if ((int)$order->userid === (int)$USER->id) {
            require_capability('local/moderncommerce:viewownorders', $context);
        } else {
            require_capability('local/moderncommerce:viewallorders', $context);
        }

        return [
            'success' => true,
            'message' => '',
            'order' => self::normalise_order($order),
            'items' => self::normalise_items($order, order_api::get_order_items($id)),
            'billing' => self::billing_data($order),
            'urls' => [
                'orders' => self::learner_app_url('orders'),
                'catalog' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
                'receipt' => (new moodle_url('/local/moderncommerce/download_invoice.php', [
                    'orderid' => $order->id,
                    'type' => 'receipt',
                ]))->out(false),
                'invoice' => (new moodle_url('/local/moderncommerce/download_invoice.php', [
                    'orderid' => $order->id,
                    'type' => 'invoice',
                ]))->out(false),
                'continuepayment' => self::learner_app_url('checkout?orderid=' . (int)$order->id),
            ],
            'sesskey' => sesskey(),
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
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether order loaded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'order' => self::order_structure(),
            'items' => new external_multiple_structure(self::item_structure()),
            'billing' => self::billing_structure(),
            'urls' => self::url_structure(),
            'sesskey' => new external_value(PARAM_RAW, 'Session key.'),
        ]);
    }

    /**
     * Normalise order.
     *
     * @param \stdClass $order Order record.
     * @return array
     */
    private static function normalise_order(\stdClass $order): array {
        return [
            'id' => (int)$order->id,
            'ordernumber' => (string)$order->ordernumber,
            'date' => userdate((int)$order->timecreated, get_string('strftimedatetime')),
            'status' => (string)$order->status,
            'statuslabel' => self::status_label((string)$order->status),
            'statusclass' => list_orders::status_class((string)$order->status),
            'ispaid' => in_array((string)$order->status, ['paid', 'completed'], true),
            'ispending' => (string)$order->status === 'pending',
            'isfailed' => in_array((string)$order->status, ['failed', 'cancelled'], true),
            'isrefunded' => (string)$order->status === 'refunded',
            'subtotal' => pricing_service::format_order_price((float)$order->subtotal, $order),
            'hasdiscount' => (float)$order->discount > 0,
            'discount' => pricing_service::format_order_price((float)$order->discount, $order),
            'hastax' => (float)$order->tax > 0,
            'tax' => pricing_service::format_order_price((float)$order->tax, $order),
            'total' => pricing_service::format_order_price((float)$order->total, $order),
            'paymentmethod' => (string)($order->paymentmethod ?? ''),
            'transactionid' => (string)($order->transactionid ?? ''),
            'couponcode' => (string)($order->couponcode ?? ''),
        ];
    }

    /**
     * Normalise order items.
     *
     * @param \stdClass $order Order record.
     * @param array $items Order item records.
     * @return array
     */
    private static function normalise_items(\stdClass $order, array $items): array {
        $data = [];

        foreach ($items as $item) {
            $producttype = (string)($item->producttype ?? $item->itemtype ?? '');
            $url = '#';
            if ((int)$item->bundleid > 0) {
                $url = (new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $item->bundleid]))->out(false);
            } else if (!empty($item->courseid)) {
                $url = (new moodle_url('/course/view.php', ['id' => $item->courseid]))->out(false);
            }

            $data[] = [
                'id' => (int)$item->id,
                'name' => format_string((string)$item->coursename),
                'producttype' => $producttype,
                'typelabel' => self::product_type_label($producttype),
                'quantity' => (float)$item->quantity,
                'quantitylabel' => format_float((float)$item->quantity, 2, true, true),
                'unitprice' => pricing_service::format_order_price((float)$item->unitprice, $order),
                'total' => pricing_service::format_order_price((float)$item->total, $order),
                'url' => $url,
                'hasurl' => $url !== '#',
                'iscourse' => !empty($item->courseid) && (int)$item->bundleid <= 0,
                'isbundle' => (int)$item->bundleid > 0,
                'issubscription' => in_array($producttype, ['subscription', 'plan'], true),
            ];
        }

        return $data;
    }

    /**
     * Billing data.
     *
     * @param \stdClass $order Order record.
     * @return array
     */
    private static function billing_data(\stdClass $order): array {
        $addressparts = array_filter([
            $order->billingaddress ?? '',
            trim(implode(', ', array_filter([
                $order->billingcity ?? '',
                $order->billingstate ?? '',
                $order->billingzip ?? '',
                $order->billingcountry ?? '',
            ]))),
        ]);

        return [
            'name' => (string)($order->billingname ?? ''),
            'email' => (string)($order->billingemail ?? ($order->customeremail ?? '')),
            'phone' => (string)($order->billingphone ?? ''),
            'address' => implode("\n", $addressparts),
            'hasaddress' => !empty($addressparts),
        ];
    }

    /**
     * Status label.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_label(string $status): string {
        return localisation::status_label($status, ['orderstatus']);
    }

    /**
     * Product type label.
     *
     * @param string $producttype Product type.
     * @return string
     */
    private static function product_type_label(string $producttype): string {
        if ($producttype === '') {
            return get_string('item', 'local_moderncommerce');
        }

        return get_string_manager()->string_exists($producttype, 'local_moderncommerce')
            ? get_string($producttype, 'local_moderncommerce')
            : ucfirst($producttype);
    }

    /**
     * Order structure.
     *
     * @return external_single_structure
     */
    private static function order_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Order ID.'),
            'ordernumber' => new external_value(PARAM_TEXT, 'Order number.'),
            'date' => new external_value(PARAM_TEXT, 'Date.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status key.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Status class.'),
            'ispaid' => new external_value(PARAM_BOOL, 'Whether paid.'),
            'ispending' => new external_value(PARAM_BOOL, 'Whether pending.'),
            'isfailed' => new external_value(PARAM_BOOL, 'Whether failed.'),
            'isrefunded' => new external_value(PARAM_BOOL, 'Whether refunded.'),
            'subtotal' => new external_value(PARAM_TEXT, 'Subtotal.'),
            'hasdiscount' => new external_value(PARAM_BOOL, 'Whether discount exists.'),
            'discount' => new external_value(PARAM_TEXT, 'Discount.'),
            'hastax' => new external_value(PARAM_BOOL, 'Whether tax exists.'),
            'tax' => new external_value(PARAM_TEXT, 'Tax.'),
            'total' => new external_value(PARAM_TEXT, 'Total.'),
            'paymentmethod' => new external_value(PARAM_TEXT, 'Payment method.'),
            'transactionid' => new external_value(PARAM_TEXT, 'Transaction ID.'),
            'couponcode' => new external_value(PARAM_TEXT, 'Coupon code.'),
        ]);
    }

    /**
     * Item structure.
     *
     * @return external_single_structure
     */
    private static function item_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Item ID.'),
            'name' => new external_value(PARAM_TEXT, 'Item name.'),
            'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Product type label.'),
            'quantity' => new external_value(PARAM_FLOAT, 'Quantity.'),
            'quantitylabel' => new external_value(PARAM_TEXT, 'Quantity label.'),
            'unitprice' => new external_value(PARAM_TEXT, 'Unit price.'),
            'total' => new external_value(PARAM_TEXT, 'Line total.'),
            'url' => new external_value(PARAM_RAW, 'Item URL.'),
            'hasurl' => new external_value(PARAM_BOOL, 'Whether item has URL.'),
            'iscourse' => new external_value(PARAM_BOOL, 'Whether course item.'),
            'isbundle' => new external_value(PARAM_BOOL, 'Whether bundle item.'),
            'issubscription' => new external_value(PARAM_BOOL, 'Whether subscription item.'),
        ]);
    }

    /**
     * Billing structure.
     *
     * @return external_single_structure
     */
    private static function billing_structure(): external_single_structure {
        return new external_single_structure([
            'name' => new external_value(PARAM_TEXT, 'Billing name.'),
            'email' => new external_value(PARAM_TEXT, 'Billing email.'),
            'phone' => new external_value(PARAM_TEXT, 'Billing phone.'),
            'address' => new external_value(PARAM_TEXT, 'Billing address.'),
            'hasaddress' => new external_value(PARAM_BOOL, 'Whether address exists.'),
        ]);
    }

    /**
     * URL structure.
     *
     * @return external_single_structure
     */
    private static function url_structure(): external_single_structure {
        return new external_single_structure([
            'orders' => new external_value(PARAM_RAW, 'Orders URL.'),
            'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
            'receipt' => new external_value(PARAM_RAW, 'Receipt URL.'),
            'invoice' => new external_value(PARAM_RAW, 'Invoice URL.'),
            'continuepayment' => new external_value(PARAM_RAW, 'Continue payment URL.'),
        ]);
    }
}
