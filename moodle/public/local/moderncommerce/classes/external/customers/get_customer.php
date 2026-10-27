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
 * External API for one Modern Commerce customer detail view.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\customers;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\localisation;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * Return customer purchase, billing, and order history data for the admin UI.
 */
class get_customer extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Customer user ID.'),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Orders per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Customer user ID.
     * @param int $page Zero-based page.
     * @param int $perpage Orders per page.
     * @return array
     */
    public static function execute(int $id, int $page = 0, int $perpage = 10): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewallorders', $context);

        $customerid = (int) $params['id'];
        $page = max(0, (int) $params['page']);
        $perpage = min(100, max(10, (int) $params['perpage']));

        $customer = $DB->get_record(
            'user',
            ['id' => $customerid, 'deleted' => 0],
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename, email, phone1, ' .
                'phone2, city, country, timecreated, suspended',
            MUST_EXIST
        );

        $stats = self::get_stats($customerid);
        $orders = self::get_orders($customerid, $page, $perpage);
        $orderids = array_map('intval', array_keys($orders));
        $itemsbyorder = self::get_order_items($orderids);
        $paymentmethods = self::get_payment_methods($orderids);

        return [
            'customer' => self::format_customer($customer),
            'stats' => self::format_stats($stats, $customerid),
            'billing' => self::format_billing(self::get_latest_billing_address($customerid)),
            'orders' => self::format_orders($orders, $itemsbyorder, $paymentmethods),
            'total' => (int) ($stats->ordercount ?? 0),
            'page' => $page,
            'perpage' => $perpage,
            'totalpages' => (int) max(1, ceil(((int) ($stats->ordercount ?? 0)) / $perpage)),
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
            'customer' => self::customer_structure(),
            'stats' => self::stats_structure(),
            'billing' => self::billing_structure(),
            'orders' => new external_multiple_structure(self::order_structure()),
            'total' => new external_value(PARAM_INT, 'Total orders for this customer.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Orders per page.'),
            'totalpages' => new external_value(PARAM_INT, 'Total order pages.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Customer structure.
     *
     * @return external_single_structure
     */
    private static function customer_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Customer user ID.'),
            'fullname' => new external_value(PARAM_TEXT, 'Customer full name.'),
            'email' => new external_value(PARAM_TEXT, 'Customer email.'),
            'phone' => new external_value(PARAM_TEXT, 'Customer phone.'),
            'city' => new external_value(PARAM_TEXT, 'Customer city.'),
            'country' => new external_value(PARAM_TEXT, 'Customer country.'),
            'accountcreated' => new external_value(PARAM_TEXT, 'Formatted account creation date.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Customer account status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Customer account status badge class.'),
        ]);
    }

    /**
     * Stats structure.
     *
     * @return external_single_structure
     */
    private static function stats_structure(): external_single_structure {
        return new external_single_structure([
            'ordercount' => new external_value(PARAM_INT, 'Total order count.'),
            'paidorders' => new external_value(PARAM_INT, 'Paid/completed order count.'),
            'pendingorders' => new external_value(PARAM_INT, 'Pending/processing order count.'),
            'refundedorders' => new external_value(PARAM_INT, 'Refunded order count.'),
            'wishlistcount' => new external_value(PARAM_INT, 'Saved wishlist item count.'),
            'totalspent' => new external_value(PARAM_FLOAT, 'Raw paid/completed spend.'),
            'displaytotalspent' => new external_value(PARAM_TEXT, 'Formatted paid/completed spend.'),
            'refundedtotal' => new external_value(PARAM_FLOAT, 'Raw refunded total.'),
            'displayrefundedtotal' => new external_value(PARAM_TEXT, 'Formatted refunded total.'),
            'firstorder' => new external_value(PARAM_TEXT, 'Formatted first order date.'),
            'lastorder' => new external_value(PARAM_TEXT, 'Formatted last order date.'),
        ]);
    }

    /**
     * Billing structure.
     *
     * @return external_single_structure
     */
    private static function billing_structure(): external_single_structure {
        return new external_single_structure([
            'hasdetails' => new external_value(PARAM_BOOL, 'Whether billing details exist.'),
            'name' => new external_value(PARAM_TEXT, 'Billing name.'),
            'email' => new external_value(PARAM_TEXT, 'Billing email.'),
            'phone' => new external_value(PARAM_TEXT, 'Billing phone.'),
            'address' => new external_value(PARAM_TEXT, 'Billing address.'),
            'city' => new external_value(PARAM_TEXT, 'Billing city.'),
            'state' => new external_value(PARAM_TEXT, 'Billing state.'),
            'country' => new external_value(PARAM_TEXT, 'Billing country.'),
            'zipcode' => new external_value(PARAM_TEXT, 'Billing postal code.'),
        ]);
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
            'ordertype' => new external_value(PARAM_ALPHANUMEXT, 'Order type.'),
            'status' => new external_value(PARAM_ALPHA, 'Order status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Order status label.'),
            'statusclass' => new external_value(PARAM_ALPHA, 'Order status badge class.'),
            'itemcount' => new external_value(PARAM_INT, 'Purchased item count.'),
            'items' => new external_multiple_structure(self::order_item_structure()),
            'paymentmethod' => new external_value(PARAM_TEXT, 'Latest payment method.'),
            'displaytotal' => new external_value(PARAM_TEXT, 'Formatted order total.'),
            'displaydate' => new external_value(PARAM_TEXT, 'Formatted order date.'),
            'viewurl' => new external_value(PARAM_URL, 'Modern Commerce order detail URL.'),
        ]);
    }

    /**
     * Order item structure.
     *
     * @return external_single_structure
     */
    private static function order_item_structure(): external_single_structure {
        return new external_single_structure([
            'name' => new external_value(PARAM_TEXT, 'Item name.'),
            'typelabel' => new external_value(PARAM_TEXT, 'Item type label.'),
            'quantity' => new external_value(PARAM_INT, 'Quantity.'),
        ]);
    }

    /**
     * Format customer.
     *
     * @param \stdClass $customer Customer user.
     * @return array
     */
    private static function format_customer(\stdClass $customer): array {
        $phone = trim((string) ($customer->phone1 ?: ($customer->phone2 ?? '')));
        $suspended = !empty($customer->suspended);

        return [
            'id' => (int) $customer->id,
            'fullname' => fullname($customer),
            'email' => (string) $customer->email,
            'phone' => $phone,
            'city' => (string) ($customer->city ?? ''),
            'country' => (string) ($customer->country ?? ''),
            'accountcreated' => self::format_time((int) ($customer->timecreated ?? 0), false),
            'statuslabel' => $suspended ? get_string('suspended', 'local_moderncommerce') : get_string('active'),
            'statusclass' => $suspended ? 'danger' : 'success',
        ];
    }

    /**
     * Get raw customer stats.
     *
     * @param int $customerid Customer user ID.
     * @return \stdClass
     */
    private static function get_stats(int $customerid): \stdClass {
        global $DB;

        $sql = "SELECT COUNT(o.id) AS ordercount,
                       SUM(CASE WHEN o.status IN ('paid', 'completed') THEN 1 ELSE 0 END) AS paidorders,
                       SUM(CASE WHEN o.status IN ('pending', 'processing') THEN 1 ELSE 0 END) AS pendingorders,
                       SUM(CASE WHEN o.status = 'refunded' THEN 1 ELSE 0 END) AS refundedorders,
                       SUM(CASE WHEN o.status IN ('paid', 'completed') THEN o.total ELSE 0 END) AS totalspent,
                       SUM(o.refundedtotal) AS refundedtotal,
                       MIN(o.timecreated) AS firstorder,
                       MAX(o.timecreated) AS lastorder
                  FROM {local_moderncommerce_orders} o
                 WHERE o.userid = :userid";

        $stats = $DB->get_record_sql($sql, ['userid' => $customerid]);
        return $stats ?: (object) [
            'ordercount' => 0,
            'paidorders' => 0,
            'pendingorders' => 0,
            'refundedorders' => 0,
            'totalspent' => 0,
            'refundedtotal' => 0,
            'firstorder' => 0,
            'lastorder' => 0,
        ];
    }

    /**
     * Format customer stats.
     *
     * @param \stdClass $stats Raw stats.
     * @param int $customerid Customer user ID.
     * @return array
     */
    private static function format_stats(\stdClass $stats, int $customerid): array {
        global $DB;

        $wishlistcount = (int) $DB->count_records('local_moderncommerce_wishlist', ['userid' => $customerid]);
        $totalspent = (float) ($stats->totalspent ?? 0);
        $refundedtotal = (float) ($stats->refundedtotal ?? 0);

        return [
            'ordercount' => (int) ($stats->ordercount ?? 0),
            'paidorders' => (int) ($stats->paidorders ?? 0),
            'pendingorders' => (int) ($stats->pendingorders ?? 0),
            'refundedorders' => (int) ($stats->refundedorders ?? 0),
            'wishlistcount' => $wishlistcount,
            'totalspent' => $totalspent,
            'displaytotalspent' => pricing_service::format_price($totalspent),
            'refundedtotal' => $refundedtotal,
            'displayrefundedtotal' => pricing_service::format_price($refundedtotal),
            'firstorder' => self::format_time((int) ($stats->firstorder ?? 0), false),
            'lastorder' => self::format_time((int) ($stats->lastorder ?? 0), false),
        ];
    }

    /**
     * Get customer orders.
     *
     * @param int $customerid Customer user ID.
     * @param int $page Zero-based page.
     * @param int $perpage Page size.
     * @return array
     */
    private static function get_orders(int $customerid, int $page, int $perpage): array {
        global $DB;

        $sql = "SELECT o.*,
                       (SELECT COALESCE(SUM(oi.quantity), 0)
                          FROM {local_moderncommerce_order_items} oi
                         WHERE oi.orderid = o.id) AS itemcount
                  FROM {local_moderncommerce_orders} o
                 WHERE o.userid = :userid
              ORDER BY o.timecreated DESC, o.id DESC";

        return $DB->get_records_sql($sql, ['userid' => $customerid], $page * $perpage, $perpage);
    }

    /**
     * Format orders.
     *
     * @param array $orders Order records.
     * @param array $itemsbyorder Items keyed by order ID.
     * @param array $paymentmethods Payment method labels keyed by order ID.
     * @return array
     */
    private static function format_orders(array $orders, array $itemsbyorder, array $paymentmethods): array {
        $rows = [];
        foreach ($orders as $order) {
            $orderid = (int) $order->id;
            $rows[] = [
                'id' => $orderid,
                'ordernumber' => (string) $order->ordernumber,
                'ordertype' => (string) $order->ordertype,
                'status' => (string) $order->status,
                'statuslabel' => self::status_label((string) $order->status),
                'statusclass' => self::status_class((string) $order->status),
                'itemcount' => (int) round((float) $order->itemcount),
                'items' => $itemsbyorder[$orderid] ?? [],
                'paymentmethod' => $paymentmethods[$orderid] ?? '-',
                'displaytotal' => pricing_service::format_order_price((float) $order->total, $order),
                'displaydate' => self::format_time((int) $order->timecreated, true),
                'viewurl' => (new moodle_url('/local/moderncommerce/admin/order_view.php', ['id' => $orderid]))->out(false),
            ];
        }

        return $rows;
    }

    /**
     * Get order items keyed by order ID.
     *
     * @param array $orderids Order IDs.
     * @return array
     */
    private static function get_order_items(array $orderids): array {
        global $DB;

        if (empty($orderids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($orderids, SQL_PARAMS_NAMED, 'orderid');
        $sql = "SELECT oi.id, oi.orderid, oi.itemname, oi.itemtype, oi.quantity
                  FROM {local_moderncommerce_order_items} oi
                 WHERE oi.orderid {$insql}
              ORDER BY oi.orderid ASC, oi.id ASC";

        $itemsbyorder = [];
        foreach ($DB->get_records_sql($sql, $params) as $item) {
            $orderid = (int) $item->orderid;
            $itemsbyorder[$orderid][] = [
                'name' => format_string((string) $item->itemname),
                'typelabel' => self::item_type_label((string) $item->itemtype),
                'quantity' => (int) round((float) $item->quantity),
            ];
        }

        return $itemsbyorder;
    }

    /**
     * Get latest payment method labels for orders.
     *
     * @param array $orderids Order IDs.
     * @return array
     */
    private static function get_payment_methods(array $orderids): array {
        global $DB;

        if (empty($orderids) || !$DB->get_manager()->table_exists('local_moderncommerce_payment_attempts')) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($orderids, SQL_PARAMS_NAMED, 'payorderid');
        $sql = "SELECT pa.id, pa.orderid, pa.gateway
                  FROM {local_moderncommerce_payment_attempts} pa
                 WHERE pa.orderid {$insql}
              ORDER BY pa.orderid ASC, pa.timecreated DESC, pa.id DESC";

        $methods = [];
        foreach ($DB->get_records_sql($sql, $params) as $attempt) {
            $orderid = (int) $attempt->orderid;
            if (!isset($methods[$orderid])) {
                $methods[$orderid] = ucfirst((string) $attempt->gateway);
            }
        }

        return $methods;
    }

    /**
     * Get latest billing address snapshot.
     *
     * @param int $customerid Customer user ID.
     * @return \stdClass|null
     */
    private static function get_latest_billing_address(int $customerid): ?\stdClass {
        global $DB;

        $sql = "SELECT a.*
                  FROM {local_moderncommerce_order_addresses} a
                  JOIN {local_moderncommerce_orders} o ON o.id = a.orderid
                 WHERE o.userid = :userid
                   AND a.addresstype = :addresstype
              ORDER BY o.timecreated DESC, a.id DESC";
        $records = $DB->get_records_sql($sql, ['userid' => $customerid, 'addresstype' => 'billing'], 0, 1);

        return empty($records) ? null : reset($records);
    }

    /**
     * Format billing record.
     *
     * @param \stdClass|null $billing Billing address.
     * @return array
     */
    private static function format_billing(?\stdClass $billing): array {
        if (!$billing) {
            return [
                'hasdetails' => false,
                'name' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
                'city' => '',
                'state' => '',
                'country' => '',
                'zipcode' => '',
            ];
        }

        $name = trim((string) ($billing->firstname ?? '') . ' ' . (string) ($billing->lastname ?? ''));
        $address = trim((string) ($billing->address1 ?? '') . ' ' . (string) ($billing->address2 ?? ''));
        $hasdetails = (bool) array_filter([
            $name,
            $billing->email ?? '',
            $billing->phone ?? '',
            $address,
            $billing->city ?? '',
            $billing->state ?? '',
            $billing->country ?? '',
            $billing->postcode ?? '',
        ]);

        return [
            'hasdetails' => $hasdetails,
            'name' => $name,
            'email' => (string) ($billing->email ?? ''),
            'phone' => (string) ($billing->phone ?? ''),
            'address' => $address,
            'city' => (string) ($billing->city ?? ''),
            'state' => (string) ($billing->state ?? ''),
            'country' => (string) ($billing->country ?? ''),
            'zipcode' => (string) ($billing->postcode ?? ''),
        ];
    }

    /**
     * Format timestamp.
     *
     * @param int $timestamp Timestamp.
     * @param bool $datetime Whether to include time.
     * @return string
     */
    private static function format_time(int $timestamp, bool $datetime): string {
        if ($timestamp <= 0) {
            return '';
        }

        $format = $datetime ? get_string('strftimedatetime', 'langconfig') : get_string('strftimedate');
        return userdate($timestamp, $format);
    }

    /**
     * Localised item type label.
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
     * Localised order status label.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_label(string $status): string {
        return localisation::status_label($status, ['orderstatus']);
    }

    /**
     * Badge class for an order status.
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
