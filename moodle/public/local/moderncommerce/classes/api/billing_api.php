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
 * Billing API for managing user billing information.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\api;


/**
 * Billing API class.
 */
class billing_api {
    /** @var string Table name */
    const TABLE = 'local_moderncommerce_billing_profiles';
    /**
     * Get the default billing info for a user.
     *
     * @param int $userid The user ID
     * @return object|null The billing info or null if not found
     */
    public static function get_billing_info(int $userid): ?object {
        global $DB;

        $billing = $DB->get_record(self::TABLE, ['userid' => $userid, 'isdefault' => 1]);

        if ($billing) {
            return $billing;
        }

        // Fallback: get the most recent billing record.
        $billings = $DB->get_records_sql(
            "SELECT *
               FROM {" . self::TABLE . "}
              WHERE userid = :userid
           ORDER BY timemodified DESC, id DESC",
            ['userid' => $userid],
            0,
            1
        );
        $billing = reset($billings);

        return $billing ?: null;
    }

    /**
     * Get billing info from user's previous order if no billing record exists.
     *
     * @param int $userid The user ID
     * @return object|null The billing info or null
     */
    public static function get_billing_from_orders(int $userid): ?object {
        global $DB;

        // Get the most recent paid order email. Address fields live in the billing table,
        // not on the canonical order header.
        $orders = $DB->get_records_sql(
            "SELECT id, customeremail
               FROM {local_moderncommerce_orders}
              WHERE userid = :userid
                    AND customeremail IS NOT NULL
                    AND customeremail <> ''
           ORDER BY timecreated DESC, id DESC",
            ['userid' => $userid],
            0,
            1
        );
        $order = reset($orders);
        if (!$order) {
            return null;
        }

        $user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email');
        return (object)[
            'firstname' => $user->firstname ?? '',
            'lastname' => $user->lastname ?? '',
            'email' => $order->customeremail ?: ($user->email ?? ''),
            'phone' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'zipcode' => '',
        ];
    }

    /**
     * Get or create billing info for a user.
     * Merges stored billing data with user profile data (firstname, lastname, email from user table).
     *
     * @param int $userid The user ID
     * @return object The billing info merged with user profile
     */
    public static function get_or_create_billing_info(int $userid): object {
        global $DB;

        // Get user profile for firstname, lastname, email.
        $user = $DB->get_record(
            'user',
            ['id' => $userid],
            'id, firstname, lastname, email, phone1, phone2, address, city, country'
        );

        // Base billing info from user profile.
        $result = (object)[
            'userid' => $userid,
            'firstname' => $user->firstname ?? '',
            'lastname' => $user->lastname ?? '',
            'email' => $user->email ?? '',
            'phone' => $user->phone1 ?: ($user->phone2 ?? ''),
            'address' => $user->address ?? '',
            'city' => $user->city ?? '',
            'state' => '',
            'country' => $user->country ?? '',
            'zipcode' => '',
        ];

        // Try to get existing billing info (for phone, address, city, state, country, zipcode).
        $billing = self::get_billing_info($userid);
        if ($billing) {
            $result->firstname = $billing->firstname ?: $result->firstname;
            $result->lastname = $billing->lastname ?: $result->lastname;
            $result->email = $billing->email ?: $result->email;
            $result->phone = $billing->phone ?: $result->phone;
            $result->address = $billing->address1 ?: $result->address;
            $result->city = $billing->city ?: $result->city;
            $result->state = $billing->state ?? '';
            $result->country = $billing->country ?: $result->country;
            $result->zipcode = $billing->postcode ?? '';
            return $result;
        }
        // Try to get from previous orders.
        $orderbilling = self::get_billing_from_orders($userid);
        if ($orderbilling) {
            $result->phone = $orderbilling->phone ?: $result->phone;
            $result->address = $orderbilling->address ?: $result->address;
            $result->city = $orderbilling->city ?: $result->city;
            $result->state = $orderbilling->state ?? '';
            $result->country = $orderbilling->country ?: $result->country;
            $result->zipcode = $orderbilling->zipcode ?? '';
        }

        return $result;
    }

    /**
     * Save or update billing info for a user.
     *
     * @param int $userid The user ID
     * @param array $data The billing data
     * @return int The billing record ID
     */
    public static function save_billing_info(int $userid, array $data): int {
        global $DB;

        $now = time();

        // Check if billing record exists.
        $existing = $DB->get_record(self::TABLE, ['userid' => $userid, 'isdefault' => 1]);

        $record = new \stdClass();
        $record->userid = $userid;
        $record->firstname = $data['firstname'] ?? '';
        $record->lastname = $data['lastname'] ?? '';
        $record->company = $data['company'] ?? '';
        $record->email = $data['email'] ?? '';
        $record->phone = $data['phone'] ?? '';
        $record->address1 = $data['address'] ?? ($data['address1'] ?? '');
        $record->address2 = $data['address2'] ?? '';
        $record->city = $data['city'] ?? '';
        $record->state = $data['state'] ?? '';
        $record->country = $data['country'] ?? '';
        $record->postcode = $data['zipcode'] ?? ($data['postcode'] ?? '');
        $record->isdefault = 1;
        $record->timemodified = $now;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
            return $existing->id;
        } else {
            $record->timecreated = $now;
            return $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Check if user is a returning customer (has previous orders).
     *
     * @param int $userid The user ID
     * @return bool True if returning customer
     */
    public static function is_returning_customer(int $userid): bool {
        global $DB;

        return $DB->record_exists('local_moderncommerce_orders', [
            'userid' => $userid,
            'status' => 'paid',
        ]);
    }

    /**
     * Get order count for user.
     *
     * @param int $userid The user ID
     * @return int The order count
     */
    public static function get_order_count(int $userid): int {
        global $DB;

        return $DB->count_records('local_moderncommerce_orders', [
            'userid' => $userid,
            'status' => 'paid',
        ]);
    }
}
