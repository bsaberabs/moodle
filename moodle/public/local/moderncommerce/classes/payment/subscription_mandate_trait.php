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
 * Shared handling for the reusable payment credential behind a subscription.
 *
 * Every gateway that can charge again later has to do the same two things: know
 * whether the order it just took was for a subscription plan, and stash whatever
 * token the provider handed back so the renewal task can reuse it. The tokens
 * differ per provider (Stripe customer + payment method, Paystack authorization
 * code, Flutterwave card token), so they are written into the order notes and
 * copied onto the subscription by the payment observer once it exists.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\payment;

/**
 * Stores the reusable payment credential captured during a subscription checkout.
 */
trait subscription_mandate_trait {
    /**
     * Whether an order buys a subscription plan.
     *
     * Subscription orders carry the plan id in their notes JSON and use the
     * 'subscription' line item type; either marker is enough to identify one.
     *
     * @param object $order Order record.
     * @return bool
     */
    public static function order_is_subscription($order): bool {
        global $DB;

        if (empty($order->id)) {
            return false;
        }

        if (!empty($order->notes)) {
            $notes = json_decode((string) $order->notes, true);
            if (is_array($notes) && !empty($notes['subscription']['planid'])) {
                return true;
            }
        }

        return $DB->record_exists('local_moderncommerce_order_items', [
            'orderid' => (int) $order->id,
            'itemtype' => 'subscription',
        ]);
    }

    /**
     * Write reusable credential fields into a subscription order's notes.
     *
     * Only non-empty values are stored, so a partial capture never blanks a
     * credential recorded by an earlier step.
     *
     * @param int $orderid Order the payment belongs to.
     * @param array $fields Column name to value, e.g. ['paystack_authorization_code' => 'AUTH_xx'].
     * @return bool Whether anything was written.
     */
    protected function store_mandate_fields(int $orderid, array $fields): bool {
        global $DB;

        if ($orderid <= 0) {
            return false;
        }

        $fields = array_filter(array_map(
            static fn($value): string => trim((string) $value),
            $fields
        ), static fn(string $value): bool => $value !== '');

        if (empty($fields)) {
            return false;
        }

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        if (!$order || !self::order_is_subscription($order)) {
            return false;
        }

        $notes = json_decode((string) ($order->notes ?? ''), true);
        if (!is_array($notes)) {
            $notes = [];
        }
        if (empty($notes['subscription']) || !is_array($notes['subscription'])) {
            $notes['subscription'] = [];
        }

        foreach ($fields as $key => $value) {
            $notes['subscription'][$key] = $value;
        }

        $DB->update_record('local_moderncommerce_orders', (object) [
            'id' => $orderid,
            'notes' => json_encode($notes),
            'timemodified' => time(),
        ]);

        return true;
    }
}
