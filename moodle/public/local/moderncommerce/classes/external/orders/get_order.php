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
 * External API for one admin order detail view.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\orders;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\api\order_api;
use local_moderncommerce\localisation;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * Get one order with items, payments, refunds, and totals for the admin detail screen.
 */
class get_order extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Order ID.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Order ID.
     * @return array
     */
    public static function execute(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewallorders', $context);

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $params['id']], '*', MUST_EXIST);
        $customer = $DB->get_record('user', ['id' => $order->userid], '*', IGNORE_MISSING);
        $billing = self::parse_billing($order);

        $canmanage = has_capability('local/moderncommerce:manageorders', $context);
        $canrefund = $canmanage
            && in_array($order->status, ['paid', 'completed'], true)
            && has_capability('local/moderncommerce:processrefunds', $context);

        return [
            'id' => (int) $order->id,
            'ordernumber' => (string) $order->ordernumber,
            'ordertype' => (string) $order->ordertype,
            'status' => (string) $order->status,
            'statuslabel' => self::status_label((string) $order->status),
            'statusclass' => self::status_class((string) $order->status),
            'displaydate' => userdate((int) $order->timecreated, get_string('strftimedatetime', 'langconfig')),
            'timepaid' => self::format_time($order->timepaid ?? 0),
            'timecompleted' => self::format_time($order->timecompleted ?? 0),
            'timerefunded' => self::format_time($order->timerefunded ?? 0),
            'customerid' => $customer ? (int) $customer->id : 0,
            'customername' => $customer ? fullname($customer) : get_string('unknownuser', 'local_moderncommerce'),
            'customeremail' => $customer ? $customer->email : (string) ($order->customeremail ?? ''),
            'customerurl' => $customer
                ? (new moodle_url('/local/moderncommerce/admin/customer.php', ['id' => $customer->id]))->out(false)
                : (new moodle_url('/local/moderncommerce/admin/orders.php'))->out(false),
            'paymentmethod' => order_api::get_payment_method((int) $order->id) ?: '-',
            'couponcode' => (string) ($order->couponcode ?? ''),
            'billing' => $billing,
            'subtotal' => pricing_service::format_order_price($order->subtotal, $order),
            'discount' => pricing_service::format_order_price($order->discount, $order),
            'tax' => pricing_service::format_order_price($order->tax, $order),
            'fees' => pricing_service::format_order_price($order->fees ?? 0, $order),
            'total' => pricing_service::format_order_price($order->total, $order),
            'refundedtotal' => pricing_service::format_order_price($order->refundedtotal ?? 0, $order),
            'rawrefundedtotal' => (float) ($order->refundedtotal ?? 0),
            'rawtotal' => (float) $order->total,
            'items' => self::get_items((int) $order->id, $order),
            'transactions' => self::get_transactions((int) $order->id, $order),
            'refunds' => self::get_refunds((int) $order->id, $order),
            'customernotes' => (string) ($order->notes ?? ''),
            'adminnotes' => (string) ($order->adminnotes ?? ''),
            'canmanage' => $canmanage,
            'canrefund' => $canrefund,
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Order ID.'),
            'ordernumber' => new external_value(PARAM_TEXT, 'Order number.'),
            'ordertype' => new external_value(PARAM_ALPHANUMEXT, 'Order type.'),
            'status' => new external_value(PARAM_ALPHA, 'Order status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Localised status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Status badge class.'),
            'displaydate' => new external_value(PARAM_TEXT, 'Formatted order date.'),
            'timepaid' => new external_value(PARAM_TEXT, 'Formatted paid date.'),
            'timecompleted' => new external_value(PARAM_TEXT, 'Formatted completed date.'),
            'timerefunded' => new external_value(PARAM_TEXT, 'Formatted refunded date.'),
            'customerid' => new external_value(PARAM_INT, 'Customer user ID.'),
            'customername' => new external_value(PARAM_TEXT, 'Customer name.'),
            'customeremail' => new external_value(PARAM_TEXT, 'Customer email.'),
            'customerurl' => new external_value(PARAM_URL, 'Modern Commerce customer detail URL.'),
            'paymentmethod' => new external_value(PARAM_TEXT, 'Payment method label.'),
            'couponcode' => new external_value(PARAM_TEXT, 'Applied coupon code.'),
            'billing' => self::billing_structure(),
            'subtotal' => new external_value(PARAM_TEXT, 'Formatted subtotal.'),
            'discount' => new external_value(PARAM_TEXT, 'Formatted discount.'),
            'tax' => new external_value(PARAM_TEXT, 'Formatted tax.'),
            'fees' => new external_value(PARAM_TEXT, 'Formatted fees.'),
            'total' => new external_value(PARAM_TEXT, 'Formatted total.'),
            'refundedtotal' => new external_value(PARAM_TEXT, 'Formatted refunded total.'),
            'rawrefundedtotal' => new external_value(PARAM_FLOAT, 'Raw refunded total.'),
            'rawtotal' => new external_value(PARAM_FLOAT, 'Raw order total.'),
            'items' => new external_multiple_structure(self::item_structure()),
            'transactions' => new external_multiple_structure(self::transaction_structure()),
            'refunds' => new external_multiple_structure(self::refund_structure()),
            'customernotes' => new external_value(PARAM_TEXT, 'Customer notes.'),
            'adminnotes' => new external_value(PARAM_TEXT, 'Admin notes.'),
            'canmanage' => new external_value(PARAM_BOOL, 'Whether the user can change status.'),
            'canrefund' => new external_value(PARAM_BOOL, 'Whether the user can refund this order.'),
            'warnings' => new external_warnings(),
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
            'city' => new external_value(PARAM_TEXT, 'Billing city.'),
            'state' => new external_value(PARAM_TEXT, 'Billing state.'),
            'country' => new external_value(PARAM_TEXT, 'Billing country.'),
            'zipcode' => new external_value(PARAM_TEXT, 'Billing zip/postal code.'),
            'hasdetails' => new external_value(PARAM_BOOL, 'Whether any billing detail is present.'),
        ]);
    }

    /**
     * Item structure.
     *
     * @return external_single_structure
     */
    private static function item_structure(): external_single_structure {
        return new external_single_structure([
            'name' => new external_value(PARAM_TEXT, 'Item name.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Item type label.'),
            'sku' => new external_value(PARAM_TEXT, 'SKU.'),
            'url' => new external_value(PARAM_URL, 'Item link URL.'),
            'hasurl' => new external_value(PARAM_BOOL, 'Whether the item has a link.'),
            'unitprice' => new external_value(PARAM_TEXT, 'Formatted unit price.'),
            'quantity' => new external_value(PARAM_INT, 'Quantity.'),
            'total' => new external_value(PARAM_TEXT, 'Formatted line total.'),
        ]);
    }

    /**
     * Transaction structure.
     *
     * @return external_single_structure
     */
    private static function transaction_structure(): external_single_structure {
        return new external_single_structure([
            'reference' => new external_value(PARAM_TEXT, 'Transaction reference.'),
            'gateway' => new external_value(PARAM_TEXT, 'Gateway label.'),
            'amount' => new external_value(PARAM_TEXT, 'Formatted amount.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Status badge class.'),
            'date' => new external_value(PARAM_TEXT, 'Formatted date.'),
        ]);
    }

    /**
     * Refund structure.
     *
     * @return external_single_structure
     */
    private static function refund_structure(): external_single_structure {
        return new external_single_structure([
            'amount' => new external_value(PARAM_TEXT, 'Formatted amount.'),
            'reason' => new external_value(PARAM_TEXT, 'Refund reason.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Status badge class.'),
            'date' => new external_value(PARAM_TEXT, 'Formatted requested date.'),
        ]);
    }

    /**
     * Parse stored billing details from the order note.
     *
     * @param \stdClass $order Order record.
     * @return array
     */
    private static function parse_billing(\stdClass $order): array {
        global $DB;

        $user = $DB->get_record('user', ['id' => $order->userid], '*', IGNORE_MISSING);
        $billing = [
            'name' => $user ? fullname($user) : '',
            'email' => (string) ($order->customeremail ?? ($user->email ?? '')),
            'phone' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'zipcode' => '',
        ];

        // The order_api::build_billing_note() helper stores "key: value" lines in the order note.
        $firstname = '';
        $lastname = '';
        foreach (preg_split('/\r\n|\r|\n/', (string) ($order->notes ?? '')) as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            switch ($key) {
                case 'firstname':
                    $firstname = $value;
                    break;
                case 'lastname':
                    $lastname = $value;
                    break;
                case 'phone':
                case 'address':
                case 'city':
                case 'state':
                case 'country':
                    $billing[$key] = $value;
                    break;
                case 'zipcode':
                    $billing['zipcode'] = $value;
                    break;
            }
        }

        $composed = trim($firstname . ' ' . $lastname);
        if ($composed !== '') {
            $billing['name'] = $composed;
        }

        $billing['hasdetails'] = (bool) array_filter([
            $billing['phone'], $billing['address'], $billing['city'],
            $billing['state'], $billing['country'], $billing['zipcode'],
        ]);

        return $billing;
    }

    /**
     * Build the item list.
     *
     * @param int $orderid Order ID.
     * @param \stdClass $order Order record.
     * @return array
     */
    private static function get_items(int $orderid, \stdClass $order): array {
        $items = [];
        foreach (order_api::get_order_items($orderid) as $item) {
            $courseid = (int) ($item->courseid ?? 0);
            $hasurl = $courseid > 0;
            $items[] = [
                'name' => (string) ($item->itemname ?: $item->coursename),
                'typelabel' => self::item_type_label((string) $item->itemtype),
                'sku' => (string) ($item->sku ?? ''),
                'url' => $hasurl
                    ? (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false)
                    : (new moodle_url('/local/moderncommerce/admin/orders.php'))->out(false),
                'hasurl' => $hasurl,
                'unitprice' => pricing_service::format_order_price($item->unitprice, $order),
                'quantity' => (int) round((float) $item->quantity),
                'total' => pricing_service::format_order_price($item->total, $order),
            ];
        }

        return $items;
    }

    /**
     * Build the transaction list.
     *
     * @param int $orderid Order ID.
     * @param \stdClass $order Order record.
     * @return array
     */
    private static function get_transactions(int $orderid, \stdClass $order): array {
        global $DB;

        $dbman = $DB->get_manager();
        $records = [];
        if ($dbman->table_exists('local_moderncommerce_payment_attempts')) {
            $records = $DB->get_records('local_moderncommerce_payment_attempts', ['orderid' => $orderid], 'timecreated DESC');
        } else if ($dbman->table_exists('local_moderncommerce_transactions')) {
            $records = $DB->get_records('local_moderncommerce_transactions', ['orderid' => $orderid], 'timecreated DESC');
        }

        $transactions = [];
        foreach ($records as $txn) {
            $reference = $txn->gatewaytransactionid ?? $txn->transactionid ?? $txn->reference ?? '';
            $status = (string) $txn->status;
            $transactions[] = [
                'reference' => (string) $reference,
                'gateway' => ucfirst((string) $txn->gateway),
                'amount' => pricing_service::format_order_price($txn->amount, $order),
                'statuslabel' => localisation::status_label($status),
                'statusclass' => in_array($status, ['success', 'paid', 'completed'], true) ? 'success'
                    : (in_array($status, ['pending', 'processing'], true) ? 'warning' : 'danger'),
                'date' => userdate((int) $txn->timecreated, get_string('strftimedatetime', 'langconfig')),
            ];
        }

        return $transactions;
    }

    /**
     * Build the refund list.
     *
     * @param int $orderid Order ID.
     * @param \stdClass $order Order record.
     * @return array
     */
    private static function get_refunds(int $orderid, \stdClass $order): array {
        global $DB;

        $refunds = [];
        $records = $DB->get_records('local_moderncommerce_refunds', ['orderid' => $orderid], 'timerequested DESC');
        foreach ($records as $refund) {
            $status = (string) $refund->status;
            $refunds[] = [
                'amount' => pricing_service::format_order_price($refund->amount, $order),
                'reason' => (string) $refund->reason,
                'statuslabel' => localisation::status_label($status),
                'statusclass' => $status === 'completed' ? 'success' : ($status === 'failed' ? 'danger' : 'warning'),
                'date' => userdate((int) $refund->timerequested, get_string('strftimedatetime', 'langconfig')),
            ];
        }

        return $refunds;
    }

    /**
     * Format an optional timestamp.
     *
     * @param int $timestamp Timestamp.
     * @return string
     */
    private static function format_time($timestamp): string {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        return userdate($timestamp, get_string('strftimedatetime', 'langconfig'));
    }

    /**
     * Localised order item type label.
     *
     * @param string $itemtype Item type.
     * @return string
     */
    private static function item_type_label(string $itemtype): string {
        $key = $itemtype === '' ? 'course' : $itemtype;
        if (get_string_manager()->string_exists($key, 'local_moderncommerce')) {
            return get_string($key, 'local_moderncommerce');
        }
        if (get_string_manager()->string_exists($key, 'core')) {
            return get_string($key);
        }

        return ucfirst($itemtype);
    }

    /**
     * Localised status label.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_label(string $status): string {
        return localisation::status_label($status, ['orderstatus']);
    }

    /**
     * Status badge class.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_class(string $status): string {
        switch ($status) {
            case 'paid':
            case 'completed':
                return 'success';
            case 'pending':
            case 'processing':
                return 'warning';
            case 'failed':
            case 'cancelled':
                return 'danger';
            case 'refunded':
                return 'info';
            default:
                return 'neutral';
        }
    }
}
