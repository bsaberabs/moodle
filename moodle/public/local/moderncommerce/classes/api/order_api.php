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
 * Order API for Modern Commerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\api;


use local_moderncommerce\services\pricing_service;
use local_moderncommerce\services\price_resolver;
use local_moderncommerce\services\order_validation_service;

/**
 * Canonical order API backed by orders/order_items/product tables.
 */
class order_api {
    /**
     * Get the configured currency.
     *
     * @return string Currency code
     */
    public static function get_currency() {
        return pricing_service::get_currency_config()->currency;
    }

    /**
     * Create a new order from the user's active cart.
     *
     * @param int $userid
     * @param string $paymentmethod Payment method ID.
     * @param array|string $billingdetails Billing data.
     * @return object Order record
     */
    public static function create_order($userid, $paymentmethod = 'paystack', $billingdetails = []) {
        global $DB, $USER;

        $userid = (int) $userid;
        $paymentmethod = self::normalise_payment_method((string) $paymentmethod);
        $billingdetails = self::normalise_billing_details($billingdetails);

        $cart = cart_api::get_active_cart($userid, false);
        cart_api::refresh_cart_prices($userid, true);
        $cartitems = cart_api::get_cart_items($userid);
        $bundleitems = cart_api::get_bundle_cart_items($userid);
        $allcartitems = array_values(array_merge($cartitems, $bundleitems));

        if (empty($allcartitems)) {
            throw new \moodle_exception('emptycart', 'local_moderncommerce');
        }

        $couponcode = $billingdetails['couponcode'] ?? null;
        $calculations = pricing_api::calculate_cart_totals($allcartitems, $couponcode, !empty($couponcode));

        $transaction = $DB->start_delegated_transaction();

        try {
            $now = time();
            $ordernumber = self::generate_order_number();

            $order = (object) [
                'userid' => $userid,
                'ordernumber' => $ordernumber,
                'ordertype' => 'purchase',
                'status' => 'pending',
                'subtotal' => (float) $calculations['subtotal'],
                'discount' => (float) $calculations['discount'],
                'tax' => (float) $calculations['tax'],
                'fees' => 0,
                'total' => (float) $calculations['total'],
                'refundedtotal' => 0,
                'currency' => $calculations['currency'] ?: self::get_currency(),
                'exchangerate' => 1,
                'couponcode' => !empty($calculations['coupon']) ? $calculations['coupon']->code : null,
                'customeremail' => $billingdetails['email'] ?? ($USER->email ?? null),
                'ipaddress' => getremoteaddr(),
                'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'notes' => self::build_billing_note($billingdetails),
                'adminnotes' => null,
                'createdby' => $USER->id ?? $userid,
                'modifiedby' => $USER->id ?? $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ];

            pricing_service::apply_currency_snapshot($order);
            $orderid = (int) $DB->insert_record(
                'local_moderncommerce_orders',
                self::filter_record_fields('local_moderncommerce_orders', $order)
            );
            $order->id = $orderid;
            $order->paymentmethod = $paymentmethod;

            foreach ($allcartitems as $item) {
                self::insert_order_item($orderid, $item, (string) $order->currency);
            }

            self::create_order_operational($orderid, $cart ? (int) $cart->id : null);
            self::record_selected_payment_method($order, $paymentmethod);

            if (!empty($calculations['coupon'])) {
                coupon_api::record_usage($calculations['coupon']->id, $userid, $orderid, $calculations['discount']);
            }

            self::log_audit($userid, 'order_created', 'order', $orderid, null, [
                'ordernumber' => $ordernumber,
                'total' => $order->total,
                'currency' => $order->currency,
                'paymentmethod' => $paymentmethod,
            ]);

            $transaction->allow_commit();

            $event = \local_moderncommerce\event\order_created::create([
                'objectid' => $orderid,
                'context' => \context_system::instance(),
                'userid' => $userid,
                'other' => ['ordernumber' => $ordernumber],
            ]);
            $event->trigger();

            try {
                \local_moderncommerce\email_notifications::send_order_confirmation($order, self::get_order_items($orderid));
            } catch (\Throwable $e) {
                debugging('Failed to send order confirmation email: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            return $order;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw new \moodle_exception('error:ordercreationfailed', 'local_moderncommerce', '', null, $e->getMessage());
        }
    }

    /**
     * Create a pending order for a subscription plan purchase.
     *
     * Carries the plan and intent in the order notes JSON under a "subscription"
     * key; local_moderncommerce's payment observer reads that on order_paid
     * and creates/renews the subscription. Route the buyer to
     * checkout.php?orderid=<returned id> to pay it.
     *
     * @param int $userid Buyer user ID.
     * @param int $planid Subscription plan ID.
     * @param float $unitprice Price to charge.
     * @param string $planname Display name for the order line item.
     * @param string $currency Currency code; defaults to the site currency.
     * @param array $meta Extra subscription metadata for order notes (e.g. action, from_subscription_id).
     * @return object Created order record (with id).
     */
    public static function create_subscription_order(
        int $userid,
        int $planid,
        float $unitprice,
        string $planname,
        string $currency = '',
        array $meta = []
    ): object {
        global $DB, $USER;

        $unitprice = max(0, (float) $unitprice);
        $currency = $currency !== '' ? $currency : self::get_currency();

        $transaction = $DB->start_delegated_transaction();

        try {
            $now = time();
            $ordernumber = self::generate_order_number();
            $subscriptionnote = array_merge(['planid' => (int) $planid], $meta);

            $order = (object) [
                'userid' => $userid,
                'ordernumber' => $ordernumber,
                'ordertype' => 'purchase',
                'status' => 'pending',
                'subtotal' => $unitprice,
                'discount' => 0,
                'tax' => 0,
                'fees' => 0,
                'total' => $unitprice,
                'refundedtotal' => 0,
                'currency' => $currency,
                'exchangerate' => 1,
                'couponcode' => null,
                'customeremail' => $USER->email ?? null,
                'ipaddress' => getremoteaddr(),
                'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'notes' => json_encode(['subscription' => $subscriptionnote]),
                'adminnotes' => null,
                'createdby' => $USER->id ?? $userid,
                'modifiedby' => $USER->id ?? $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ];

            pricing_service::apply_currency_snapshot($order);
            $orderid = (int) $DB->insert_record(
                'local_moderncommerce_orders',
                self::filter_record_fields('local_moderncommerce_orders', $order)
            );
            $order->id = $orderid;

            self::insert_order_item($orderid, (object) [
                'itemtype' => 'subscription',
                'itemname' => $planname,
                'unitprice' => $unitprice,
                'quantity' => 1,
            ], (string) $order->currency);

            self::create_order_operational($orderid, null);

            self::log_audit($userid, 'order_created', 'order', $orderid, null, [
                'ordernumber' => $ordernumber,
                'total' => $unitprice,
                'currency' => $currency,
                'subscriptionplanid' => (int) $planid,
            ]);

            $transaction->allow_commit();

            $event = \local_moderncommerce\event\order_created::create([
                'objectid' => $orderid,
                'context' => \context_system::instance(),
                'userid' => $userid,
                'other' => ['ordernumber' => $ordernumber],
            ]);
            $event->trigger();

            return $order;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw new \moodle_exception('error:ordercreationfailed', 'local_moderncommerce', '', null, $e->getMessage());
        }
    }

    /**
     * Create manual invoice by admin.
     *
     * @param int $userid
     * @param array $courseids
     * @param array $invoicedetails
     * @return object Order record
     */
    public static function create_manual_invoice($userid, $courseids, $invoicedetails) {
        global $DB, $USER;

        $courseids = array_values(array_filter(array_map('intval', (array) $courseids)));
        if (empty($courseids)) {
            throw new \moodle_exception('error:invalidcourse', 'local_moderncommerce');
        }

        $transaction = $DB->start_delegated_transaction();

        try {
            $now = time();
            $ordernumber = self::generate_order_number();
            $invoicenumber = self::generate_invoice_number();
            $currency = self::get_currency();
            $items = [];
            $subtotal = 0.0;

            foreach ($courseids as $courseid) {
                $price = price_resolver::resolve_for_course($courseid, 1, true);
                if (!$price || empty($price->productid)) {
                    throw new \moodle_exception('error:invalidcourse', 'local_moderncommerce');
                }

                $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
                $item = (object) [
                    'productid' => (int) $price->productid,
                    'priceid' => (int) $price->priceid,
                    'courseid' => $courseid,
                    'itemtype' => 'course',
                    'productname' => $price->productname ?: $course->fullname,
                    'coursename' => $course->fullname,
                    'sku' => $price->sku,
                    'price' => (float) $price->unitprice,
                    'unitprice' => (float) $price->unitprice,
                    'quantity' => 1,
                    'enrolduration' => $price->enrolduration,
                ];
                $items[] = $item;
                $subtotal += (float) $item->price;
            }

            $discount = max(0, (float) ($invoicedetails['discount'] ?? 0));
            $tax = max(0, (float) ($invoicedetails['tax'] ?? 0));
            $total = max(0, $subtotal - $discount + $tax);

            $order = (object) [
                'userid' => (int) $userid,
                'ordernumber' => $ordernumber,
                'ordertype' => 'manual_invoice',
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'fees' => 0,
                'total' => $total,
                'refundedtotal' => 0,
                'currency' => $currency,
                'exchangerate' => 1,
                'couponcode' => null,
                'customeremail' => $invoicedetails['email'] ?? null,
                'notes' => $invoicedetails['notes'] ?? '',
                'adminnotes' => $invoicedetails['terms'] ?? '',
                'createdby' => $USER->id ?? null,
                'modifiedby' => $USER->id ?? null,
                'timecreated' => $now,
                'timemodified' => $now,
            ];

            pricing_service::apply_currency_snapshot($order);
            $orderid = (int) $DB->insert_record(
                'local_moderncommerce_orders',
                self::filter_record_fields('local_moderncommerce_orders', $order)
            );
            $order->id = $orderid;
            $order->invoicenumber = $invoicenumber;
            $order->paymentmethod = 'manual';

            foreach ($items as $item) {
                $item->orderitemid = self::insert_order_item($orderid, $item, $currency);
            }

            self::create_order_operational($orderid, null);
            self::record_selected_payment_method($order, 'manual');
            self::insert_invoice($order, $invoicenumber, $items, $invoicedetails);

            self::log_audit($USER->id ?? $userid, 'manual_invoice_created', 'order', $orderid, null, [
                'ordernumber' => $ordernumber,
                'invoicenumber' => $invoicenumber,
                'total' => $total,
            ]);

            $transaction->allow_commit();

            return $order;
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Update order status.
     *
     * @param int $orderid
     * @param string $status
     * @return bool
     */
    public static function update_order_status($orderid, $status) {
        global $DB, $USER;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => (int) $orderid], '*', MUST_EXIST);
        $status = self::normalise_order_status((string) $status);
        $oldstatus = self::normalise_order_status((string) $order->status);

        if ($oldstatus === $status) {
            return true;
        }

        if (!self::is_order_status_transition_allowed($oldstatus, $status)) {
            self::log_audit(
                (int) $order->userid,
                'order_status_change_ignored',
                'order',
                (int) $orderid,
                ['status' => $oldstatus],
                ['status' => $status]
            );
            return false;
        }

        if (
            in_array($status, ['paid', 'completed'], true)
            && !in_array($oldstatus, ['paid', 'completed'], true)
        ) {
            $validation = order_validation_service::validate_order_for_payment((int) $orderid, (int) $order->userid);
            if (!$validation['valid'] && $validation['cancel_order']) {
                order_validation_service::cancel_duplicate_order((int) $orderid, $validation['errors']);
                return false;
            }
        }

        $now = time();

        $order->status = $status;
        $order->modifiedby = $USER->id ?? $order->modifiedby ?? null;
        $order->timemodified = $now;

        if (in_array($status, ['paid', 'completed'], true) && empty($order->timepaid)) {
            $order->timepaid = $now;
        }
        if ($status === 'refunded' && empty($order->timerefunded)) {
            $order->timerefunded = $now;
        }
        if ($status === 'completed' && empty($order->timecompleted)) {
            $order->timecompleted = $now;
        }

        $DB->update_record('local_moderncommerce_orders', self::filter_record_fields('local_moderncommerce_orders', $order));
        self::update_order_operational_status((int) $orderid, (string) $status);
        self::record_status_history((int) $orderid, (string) $oldstatus, (string) $status);

        self::log_audit(
            (int) $order->userid,
            'order_status_changed',
            'order',
            (int) $orderid,
            ['status' => $oldstatus],
            ['status' => $status]
        );

        $enrollablestatuses = ['paid', 'completed'];
        $wasnotpaid = !in_array($oldstatus, $enrollablestatuses, true);
        $isnowpaid = in_array($status, $enrollablestatuses, true);

        if ($isnowpaid && $wasnotpaid) {
            cart_api::clear_cart((int) $order->userid);

            $event = \local_moderncommerce\event\order_paid::create([
                'objectid' => (int) $orderid,
                'context' => \context_system::instance(),
                'relateduserid' => (int) $order->userid,
                'other' => [
                    'ordernumber' => $order->ordernumber,
                    'total' => $order->total,
                ],
            ]);
            $event->trigger();
        }

        return true;
    }

    /**
     * Get order by ID.
     *
     * @param int $orderid
     * @return object
     */
    public static function get_order($orderid) {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => (int) $orderid], '*', MUST_EXIST);
        self::normalise_order_record($order);

        return $order;
    }

    /**
     * Get order items.
     *
     * @param int $orderid
     * @return array
     */
    public static function get_order_items($orderid) {
        global $DB;

        $sql = "SELECT oi.*,
                       p.name AS productname,
                       p.producttype,
                       p.imageurl,
                       c.fullname AS coursefullname,
                       c.shortname AS courseshortname
                  FROM {local_moderncommerce_order_items} oi
             LEFT JOIN {local_moderncommerce_products} p ON p.id = oi.productid
             LEFT JOIN {course} c ON c.id = oi.courseid
                 WHERE oi.orderid = :orderid
              ORDER BY oi.id ASC";

        $items = $DB->get_records_sql($sql, ['orderid' => (int) $orderid]);
        foreach ($items as $item) {
            self::normalise_order_item($item);
        }

        return $items;
    }

    /**
     * Get user orders.
     *
     * @param int $userid
     * @param array $filters
     * @return array
     */
    public static function get_user_orders($userid, $filters = []) {
        global $DB;

        $params = ['userid' => (int) $userid];
        $where = ['userid = :userid'];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        $sql = "SELECT *
                  FROM {local_moderncommerce_orders}
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY timecreated DESC";

        $orders = $DB->get_records_sql($sql, $params);
        foreach ($orders as $order) {
            self::normalise_order_record($order);
        }

        return $orders;
    }

    /**
     * Get latest known payment method label for an order.
     *
     * @param int $orderid
     * @return string|null
     */
    public static function get_payment_method(int $orderid): ?string {
        global $DB;

        if (self::table_exists('local_moderncommerce_payment_attempts')) {
            $records = $DB->get_records(
                'local_moderncommerce_payment_attempts',
                ['orderid' => $orderid],
                'timecreated DESC, id DESC',
                'id, gateway',
                0,
                1
            );
            if ($records) {
                $attempt = reset($records);
                return ucfirst((string) $attempt->gateway);
            }
        }

        if (self::table_exists('local_moderncommerce_transactions')) {
            $records = $DB->get_records(
                'local_moderncommerce_transactions',
                ['orderid' => $orderid],
                'timecreated DESC, id DESC',
                'id, gateway',
                0,
                1
            );
            if ($records) {
                $transaction = reset($records);
                return ucfirst((string) $transaction->gateway);
            }
        }

        return null;
    }

    /**
     * Record selected offline/key payment method in the attempt ledger.
     *
     * @param object $order
     * @param string $paymentmethod
     */
    public static function record_selected_payment_method(object $order, string $paymentmethod): void {
        global $DB;

        $paymentmethod = self::normalise_payment_method($paymentmethod);
        if (!in_array($paymentmethod, ['manual', 'enrollkey'], true)) {
            return;
        }

        if (!self::table_exists('local_moderncommerce_payment_attempts')) {
            return;
        }

        $reference = $order->ordernumber ?? ('ORDER-' . (int) $order->id);
        $existing = $DB->get_record('local_moderncommerce_payment_attempts', [
            'gateway' => $paymentmethod,
            'reference' => $reference,
        ], '*', IGNORE_MISSING);

        $now = time();
        $record = (object) [
            'orderid' => (int) $order->id,
            'gateway' => $paymentmethod,
            'reference' => $reference,
            'amount' => (float) ($order->total ?? 0),
            'currency' => $order->currency ?? self::get_currency(),
            'status' => 'pending',
            'idempotencykey' => hash('sha256', (int) $order->id . '|' . $paymentmethod . '|' . $reference),
            'gatewaytransactionid' => null,
            'redirecturl' => null,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record(
                'local_moderncommerce_payment_attempts',
                self::filter_record_fields('local_moderncommerce_payment_attempts', $record)
            );
            return;
        }

        $record->timecreated = $now;
        $DB->insert_record(
            'local_moderncommerce_payment_attempts',
            self::filter_record_fields('local_moderncommerce_payment_attempts', $record)
        );
    }

    /**
     * Generate unique order number.
     *
     * @return string
     */
    public static function generate_order_number() {
        global $DB;

        do {
            $ordernumber = 'MC-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 8));
            $exists = $DB->record_exists('local_moderncommerce_orders', ['ordernumber' => $ordernumber]);
        } while ($exists);

        return $ordernumber;
    }

    /**
     * Generate unique invoice number.
     *
     * @return string
     */
    public static function generate_invoice_number() {
        global $DB;

        do {
            $invoicenumber = 'INV-' . date('Ymd') . '-' . str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $exists = self::table_exists('local_moderncommerce_invoices')
                ? $DB->record_exists('local_moderncommerce_invoices', ['invoicenumber' => $invoicenumber])
                : false;
        } while ($exists);

        return $invoicenumber;
    }

    /**
     * Log audit trail.
     *
     * @param int $userid
     * @param string $action
     * @param string $entitytype
     * @param int $entityid
     * @param mixed $olddata
     * @param mixed $newdata
     * @param string $result
     * @param string $severity
     */
    public static function log_audit(
        $userid,
        $action,
        $entitytype,
        $entityid,
        $olddata,
        $newdata,
        $result = 'success',
        $severity = 'info'
    ) {
        \local_moderncommerce\audit\audit_service::record((string) $action, (string) $entitytype, (int) $entityid, [
            'actoruserid' => (int) $userid,
            'subjectuserid' => (int) $userid,
            'olddata' => $olddata,
            'newdata' => $newdata,
            'result' => $result ?: 'success',
            'severity' => $severity ?: 'info',
        ]);
    }

    /**
     * Normalise order status aliases.
     *
     * @param string $status Status.
     * @return string Normalised status.
     */
    private static function normalise_order_status(string $status): string {
        $status = strtolower(trim($status));

        if (in_array($status, ['success', 'successful', 'succeeded', 'paid'], true)) {
            return 'paid';
        }

        if (in_array($status, ['complete', 'completed'], true)) {
            return 'completed';
        }

        if (in_array($status, ['failure', 'declined', 'denied', 'error'], true)) {
            return 'failed';
        }

        if ($status === 'canceled') {
            return 'cancelled';
        }

        return $status;
    }

    /**
     * Check whether an order status transition is safe.
     *
     * @param string $oldstatus Current status.
     * @param string $newstatus New status.
     * @return bool
     */
    private static function is_order_status_transition_allowed(string $oldstatus, string $newstatus): bool {
        if ($oldstatus === $newstatus) {
            return false;
        }

        if ($oldstatus === 'refunded' && $newstatus !== 'refunded') {
            return false;
        }

        if (in_array($oldstatus, ['paid', 'completed'], true) && in_array($newstatus, ['failed', 'cancelled'], true)) {
            return false;
        }

        if ($oldstatus === 'completed' && $newstatus === 'paid') {
            return false;
        }

        return true;
    }

    /**
     * Insert a canonical order item.
     *
     * @param int $orderid
     * @param object $item
     * @param string $currency
     * @return int
     */
    private static function insert_order_item(int $orderid, object $item, string $currency): int {
        global $DB;

        $quantity = max(1, (float) ($item->quantity ?? 1));
        $unitprice = (float) ($item->unitprice ?? ($item->price ?? 0));
        $subtotal = $unitprice * $quantity;
        $itemtype = (string) ($item->itemtype ?? 'course');
        $productid = !empty($item->productid) ? (int) $item->productid : null;
        $product = $productid ? $DB->get_record('local_moderncommerce_products', ['id' => $productid]) : false;

        if ($product && in_array($product->producttype, ['bundle', 'program'], true)) {
            $itemtype = $product->producttype;
        }

        $itemname = self::resolve_item_name($item, $product);

        $record = (object) [
            'orderid' => $orderid,
            'productid' => $productid,
            'priceid' => !empty($item->priceid) ? (int) $item->priceid : null,
            'courseid' => !empty($item->courseid) ? (int) $item->courseid : null,
            'itemtype' => $itemtype,
            'itemname' => $itemname,
            'sku' => $item->sku ?? ($product->sku ?? null),
            'unitprice' => $unitprice,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $subtotal,
            'currency' => $currency,
            'enrolduration' => $item->enrolduration ?? ($product->enrolduration ?? null),
            'timecreated' => time(),
        ];

        return (int) $DB->insert_record(
            'local_moderncommerce_order_items',
            self::filter_record_fields('local_moderncommerce_order_items', $record)
        );
    }

    /**
     * Resolve an order item display name.
     *
     * @param object $item
     * @param object|false $product
     * @return string
     */
    private static function resolve_item_name(object $item, $product): string {
        $name = $item->itemname
            ?? $item->coursename
            ?? $item->bundlename
            ?? $item->productname
            ?? ($product->name ?? '');

        if ($name !== '') {
            return (string) $name;
        }

        return get_string('item', 'local_moderncommerce');
    }

    /**
     * Create order operational row.
     *
     * @param int $orderid
     * @param int|null $cartid
     */
    private static function create_order_operational(int $orderid, ?int $cartid): void {
        global $DB, $SESSION;

        if (!self::table_exists('local_moderncommerce_order_operational')) {
            return;
        }

        if ($DB->record_exists('local_moderncommerce_order_operational', ['orderid' => $orderid])) {
            return;
        }

        $DB->insert_record('local_moderncommerce_order_operational', self::filter_record_fields(
            'local_moderncommerce_order_operational',
            (object) [
                'orderid' => $orderid,
                'cartid' => $cartid,
                'createdvia' => 'checkout',
                'checkoutsessionid' => $SESSION->sessionid ?? sesskey(),
                'cartchecksum' => null,
                'pricesincludetax' => 0,
                'couponusagerecorded' => 0,
                'inventoryreserved' => 0,
                'inventoryreduced' => 0,
                'receiptqueued' => 0,
                'receiptsent' => 0,
                'paymentstatus' => 'unpaid',
                'fulfillmentstatus' => 'unfulfilled',
                'lastpaymentattemptid' => null,
                'lastgatewayeventid' => null,
                'timemodified' => time(),
            ]
        ));
    }

    /**
     * Update operational status for an order status transition.
     *
     * @param int $orderid
     * @param string $status
     */
    private static function update_order_operational_status(int $orderid, string $status): void {
        global $DB;

        if (!self::table_exists('local_moderncommerce_order_operational')) {
            return;
        }

        $record = $DB->get_record('local_moderncommerce_order_operational', ['orderid' => $orderid]);
        if (!$record) {
            return;
        }

        if (in_array($status, ['paid', 'completed'], true)) {
            $record->paymentstatus = 'paid';
            $record->timepaid = $record->timepaid ?: time();
        } else if (in_array($status, ['failed', 'cancelled'], true)) {
            $record->paymentstatus = $status;
            if ($status === 'cancelled') {
                $record->timecancelled = !empty($record->timecancelled) ? $record->timecancelled : time();
            }
        } else if ($status === 'refunded') {
            $record->paymentstatus = 'refunded';
        }

        if ($status === 'completed') {
            $record->fulfillmentstatus = 'fulfilled';
            $record->timefulfilled = $record->timefulfilled ?: time();
        }

        $record->timemodified = time();
        $DB->update_record(
            'local_moderncommerce_order_operational',
            self::filter_record_fields('local_moderncommerce_order_operational', $record)
        );
    }

    /**
     * Record order status history when the table exists.
     *
     * @param int $orderid
     * @param string $oldstatus
     * @param string $newstatus
     */
    private static function record_status_history(int $orderid, string $oldstatus, string $newstatus): void {
        global $DB, $USER;

        if (!self::table_exists('local_moderncommerce_order_status_history')) {
            return;
        }

        $DB->insert_record('local_moderncommerce_order_status_history', self::filter_record_fields(
            'local_moderncommerce_order_status_history',
            (object) [
                'orderid' => $orderid,
                'oldstatus' => $oldstatus,
                'newstatus' => $newstatus,
                'actoruserid' => $USER->id ?? null,
                'note' => null,
                'timecreated' => time(),
            ]
        ));
    }

    /**
     * Insert invoice and invoice items for manual invoices.
     *
     * @param object $order
     * @param string $invoicenumber
     * @param array $items
     * @param array $invoicedetails
     */
    private static function insert_invoice(object $order, string $invoicenumber, array $items, array $invoicedetails): void {
        global $DB, $USER;

        if (!self::table_exists('local_moderncommerce_invoices')) {
            return;
        }

        $invoice = (object) [
            'orderid' => (int) $order->id,
            'userid' => (int) $order->userid,
            'invoicenumber' => $invoicenumber,
            'status' => 'draft',
            'subtotal' => (float) $order->subtotal,
            'tax' => (float) $order->tax,
            'total' => (float) $order->total,
            'currency' => $order->currency,
            'duedate' => $invoicedetails['duedate'] ?? (time() + (30 * DAYSECS)),
            'issuedat' => time(),
            'paidat' => null,
            'filepath' => null,
            'notes' => $invoicedetails['notes'] ?? '',
            'terms' => $invoicedetails['terms'] ?? '',
            'createdby' => $USER->id ?? null,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $invoiceid = (int) $DB->insert_record(
            'local_moderncommerce_invoices',
            self::filter_record_fields('local_moderncommerce_invoices', $invoice)
        );

        if (!self::table_exists('local_moderncommerce_invoice_items')) {
            return;
        }

        foreach ($items as $item) {
            $DB->insert_record('local_moderncommerce_invoice_items', self::filter_record_fields(
                'local_moderncommerce_invoice_items',
                (object) [
                    'invoiceid' => $invoiceid,
                    'orderitemid' => $item->orderitemid ?? null,
                    'description' => self::resolve_item_name($item, false),
                    'quantity' => $item->quantity ?? 1,
                    'unitprice' => $item->unitprice ?? $item->price ?? 0,
                    'total' => (float) ($item->unitprice ?? $item->price ?? 0) * (float) ($item->quantity ?? 1),
                    'timecreated' => time(),
                ]
            ));
        }
    }

    /**
     * Add legacy aliases to an order item object.
     *
     * @param object $item
     */
    private static function normalise_order_item(object $item): void {
        $item->price = (float) $item->unitprice;
        $item->linetotal = (float) $item->total;
        $item->quantity = (float) $item->quantity;
        $item->bundleid = 0;
        $item->planid = 0;
        $item->enrolled = 0;

        $name = $item->itemname ?: ($item->coursefullname ?: ($item->productname ?: get_string('item', 'local_moderncommerce')));
        $item->coursename = $name;

        if (
            in_array($item->itemtype, ['bundle', 'program'], true)
                || in_array((string) ($item->producttype ?? ''), ['bundle', 'program'], true)
        ) {
            $item->bundleid = (int) $item->productid;
            $item->bundlename = $name;
        }
    }

    /**
     * Add transitional display aliases to an order object.
     *
     * @param object $order
     */
    private static function normalise_order_record(object $order): void {
        global $DB;

        $order->paymentmethod = self::get_payment_method((int) $order->id);
        $order->billingemail = $order->customeremail ?? '';
        $order->billingphone = '';
        $order->billingaddress = '';
        $order->billingcity = '';
        $order->billingstate = '';
        $order->billingcountry = '';
        $order->billingzip = '';

        $user = $DB->get_record('user', ['id' => (int) $order->userid], '*', IGNORE_MISSING);
        $order->billingname = $user ? fullname($user) : '';
        $order->invoicenumber = self::get_invoice_number((int) $order->id) ?: ($order->invoicenumber ?? null);
    }

    /**
     * Get invoice number for an order.
     *
     * @param int $orderid
     * @return string|null
     */
    private static function get_invoice_number(int $orderid): ?string {
        global $DB;

        if (!self::table_exists('local_moderncommerce_invoices')) {
            return null;
        }

        $value = $DB->get_field('local_moderncommerce_invoices', 'invoicenumber', ['orderid' => $orderid]);
        return $value === false ? null : (string) $value;
    }

    /**
     * Build a concise billing note because order headers intentionally avoid billing columns.
     *
     * @param array $billingdetails
     * @return string|null
     */
    private static function build_billing_note(array $billingdetails): ?string {
        $parts = [];
        foreach (['firstname', 'lastname', 'phone', 'address', 'city', 'state', 'country', 'zipcode'] as $key) {
            if (!empty($billingdetails[$key])) {
                $parts[] = $key . ': ' . $billingdetails[$key];
            }
        }

        return $parts ? implode("\n", $parts) : null;
    }

    /**
     * Normalise submitted billing details.
     *
     * @param array|string $billingdetails
     * @return array
     */
    private static function normalise_billing_details($billingdetails): array {
        if (is_string($billingdetails)) {
            $decoded = json_decode($billingdetails, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($billingdetails) ? $billingdetails : [];
    }

    /**
     * Normalise payment method IDs.
     *
     * @param string $paymentmethod
     * @return string
     */
    private static function normalise_payment_method(string $paymentmethod): string {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($paymentmethod))) ?: 'manual';
    }

    /**
     * Filter a record to real table columns.
     *
     * @param string $tablename
     * @param object $record
     * @return object
     */
    private static function filter_record_fields(string $tablename, object $record): object {
        global $DB;

        $columns = $DB->get_columns($tablename);
        $filtered = new \stdClass();
        foreach (get_object_vars($record) as $field => $value) {
            if (isset($columns[$field])) {
                $filtered->{$field} = $value;
            }
        }

        return $filtered;
    }

    /**
     * Check whether a table exists.
     *
     * @param string $tablename
     * @return bool
     */
    private static function table_exists(string $tablename): bool {
        global $DB;

        return $DB->get_manager()->table_exists($tablename);
    }

    /**
     * Get the last audit event hash.
     *
     * @return string|null
     */
    private static function get_previous_audit_hash(): ?string {
        global $DB;

        $records = $DB->get_records('local_moderncommerce_audit_log', null, 'id DESC', 'id, eventhash', 0, 1);
        if (!$records) {
            return null;
        }

        $record = reset($records);
        return $record->eventhash ?: null;
    }

    /**
     * Generate UUID v4.
     *
     * @return string
     */
    private static function generate_uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
