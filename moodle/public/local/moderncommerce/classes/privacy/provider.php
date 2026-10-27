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
 * Privacy provider for Modern Commerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\privacy;


use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use xmldb_table;

/**
 * Privacy provider for Modern Commerce.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Return metadata about personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection Metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::metadata_tables() as $table => $fields) {
            $collection->add_database_table(
                $table,
                self::metadata_fields($fields),
                'privacy:metadata:' . $table
            );
        }

        $collection->add_external_location_link(
            'stripe',
            self::payment_gateway_external_fields(),
            'privacy:metadata:external:stripe'
        );
        $collection->add_external_location_link(
            'paypal',
            self::payment_gateway_external_fields(),
            'privacy:metadata:external:paypal'
        );
        $collection->add_external_location_link(
            'paystack',
            self::payment_gateway_external_fields(),
            'privacy:metadata:external:paystack'
        );
        $collection->add_external_location_link(
            'flutterwave',
            self::payment_gateway_external_fields(),
            'privacy:metadata:external:flutterwave'
        );
        $collection->add_external_location_link(
            'outbound_http_webhook',
            [
                'recipientuserid' => 'privacy:metadata:external:field:recipientuserid',
                'recipientemail' => 'privacy:metadata:external:field:recipientemail',
                'subject' => 'privacy:metadata:external:field:subject',
                'body' => 'privacy:metadata:external:field:body',
            ],
            'privacy:metadata:external:outbound_http_webhook'
        );
        $collection->add_external_location_link(
            'google_recaptcha',
            [
                'ipaddress' => 'privacy:metadata:external:field:ipaddress',
                'recaptcharesponse' => 'privacy:metadata:external:field:recaptcharesponse',
            ],
            'privacy:metadata:external:google_recaptcha'
        );

        return $collection;
    }

    /**
     * Common personal data fields sent to payment gateway APIs.
     *
     * @return array
     */
    private static function payment_gateway_external_fields(): array {
        return [
            'userid' => 'privacy:metadata:external:field:userid',
            'firstname' => 'privacy:metadata:external:field:firstname',
            'lastname' => 'privacy:metadata:external:field:lastname',
            'email' => 'privacy:metadata:external:field:email',
            'phone' => 'privacy:metadata:external:field:phone',
            'billingaddress' => 'privacy:metadata:external:field:billingaddress',
            'orderid' => 'privacy:metadata:external:field:orderid',
            'ordernumber' => 'privacy:metadata:external:field:ordernumber',
            'amount' => 'privacy:metadata:external:field:amount',
            'currency' => 'privacy:metadata:external:field:currency',
            'ipaddress' => 'privacy:metadata:external:field:ipaddress',
        ];
    }

    /**
     * Get contexts containing user data.
     *
     * @param int $userid User ID.
     * @return contextlist Context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::user_has_data($userid)) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Get users with plugin data in a context.
     *
     * @param userlist $userlist User list.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        foreach (self::user_fields() as $table => $fields) {
            foreach ($fields as $field) {
                self::add_users_from_field($userlist, $table, $field);
            }
        }
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        if ($contextlist->count() === 0) {
            return;
        }

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }

            $data = self::collect_user_data($userid);
            foreach ($data as $name => $records) {
                if (empty($records)) {
                    continue;
                }
                writer::with_context($context)->export_related_data(
                    [get_string('pluginname', 'local_moderncommerce')],
                    $name,
                    (object)['records' => array_values($records)]
                );
            }
        }
    }

    /**
     * Delete all user data in a context.
     *
     * @param context $context Context.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        foreach (self::delete_all_tables() as $table) {
            if (self::table_exists($table)) {
                self::delete_records($table, []);
            }
        }

        foreach (self::anonymise_fields() as $table => $fields) {
            foreach ($fields as $field => $replacement) {
                self::set_field_select($table, $field, $replacement, '1 = 1', []);
            }
        }
    }

    /**
     * Delete data for all approved users.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        foreach ($userlist->get_users() as $user) {
            self::delete_user_data((int)$user->id);
        }
    }

    /**
     * Delete data for one user.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if ($contextlist->count() === 0) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::delete_user_data((int)$contextlist->get_user()->id);
                return;
            }
        }
    }

    /**
     * Tables and fields described to the Privacy API.
     *
     * @return array
     */
    private static function metadata_tables(): array {
        return [
            'local_moderncommerce_billing_profiles' => [
                'userid', 'firstname', 'lastname', 'company', 'email', 'phone', 'address1', 'address2', 'city',
                'state', 'country', 'postcode', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_carts' => [
                'userid', 'sessionid', 'couponcode', 'subtotal', 'discount', 'tax', 'total', 'timecreated',
                'timemodified',
            ],
            'local_moderncommerce_orders' => [
                'userid', 'ordernumber', 'status', 'total', 'currency', 'couponcode', 'customeremail', 'ipaddress',
                'useragent', 'referrer', 'notes', 'adminnotes', 'createdby', 'modifiedby', 'timecreated',
                'timemodified',
            ],
            'local_moderncommerce_order_addresses' => [
                'orderid', 'addresstype', 'firstname', 'lastname', 'company', 'email', 'phone', 'address1',
                'address2', 'city', 'state', 'country', 'postcode',
            ],
            'local_moderncommerce_order_status_history' => [
                'orderid', 'oldstatus', 'newstatus', 'actoruserid', 'source', 'note', 'timecreated',
            ],
            'local_moderncommerce_invoices' => [
                'orderid', 'userid', 'invoicenumber', 'status', 'total', 'currency', 'terms', 'createdby',
                'timecreated',
            ],
            'local_moderncommerce_payment_attempts' => [
                'orderid', 'gateway', 'reference', 'amount', 'currency', 'status', 'gatewaytransactionid',
                'redirecturl', 'errorcode', 'errormessage', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_payment_events' => [
                'orderid', 'attemptid', 'gateway', 'eventtype', 'gatewayeventid', 'reference', 'transactionid',
                'amount', 'currency', 'status', 'verified', 'payloadhash', 'rawpayload', 'processingerror',
                'timecreated',
            ],
            'local_moderncommerce_webhook_events' => [
                'gateway', 'eventtype', 'gatewayeventid', 'reference', 'signatureverified', 'payloadhash', 'payload',
                'status', 'attemptcount', 'lasterror', 'timecreated',
            ],
            'local_moderncommerce_payment_log' => [
                'orderid', 'gateway', 'action', 'reference', 'eventid', 'correlationid', 'result', 'payloadhash',
                'response', 'timecreated',
            ],
            'local_moderncommerce_refunds' => [
                'orderid', 'attemptid', 'amount', 'currency', 'reason', 'status', 'refundreference', 'requestedby',
                'processedby', 'adminnotes', 'timerequested', 'timeprocessed',
            ],
            'local_moderncommerce_coupon_usage' => [
                'couponid', 'userid', 'orderid', 'discountamount', 'timecreated',
            ],
            'local_moderncommerce_coupons' => [
                'createdby', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_enrollkeys' => [
                'keycode', 'requiredemail', 'notes', 'createdby', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_key_usage' => [
                'enrollkeyid', 'userid', 'orderid', 'productid', 'courseid', 'valueused', 'ipaddress', 'useragent',
                'timeredeemed',
            ],
            'local_moderncommerce_fulfillments' => [
                'orderid', 'userid', 'status', 'source', 'timecreated', 'timemodified', 'timecompleted',
            ],
            'local_moderncommerce_entitlements' => [
                'sourcekey', 'userid', 'productid', 'courseid', 'orderid', 'orderitemid', 'enrollkeyid',
                'entitlementtype', 'source', 'status', 'grantreason', 'timestart', 'timeend', 'timegranted',
                'metadata',
            ],
            'local_moderncommerce_entitlement_events' => [
                'entitlementid', 'eventuuid', 'eventtype', 'actoruserid', 'source', 'reason', 'correlationid',
                'eventdata', 'timecreated',
            ],
            'local_moderncommerce_wishlist' => [
                'userid', 'productid', 'timecreated',
            ],
            'local_moderncommerce_reviews' => [
                'courseid', 'userid', 'rating', 'comment', 'hidden', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_review_rxn' => [
                'reviewid', 'userid', 'reaction', 'timecreated',
            ],
            'local_moderncommerce_dashpref' => [
                'userid', 'chartslayout', 'panellayout', 'daterange', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_subscriber' => [
                'email', 'source', 'userid', 'timecreated',
            ],
            'local_moderncommerce_audit_log' => [
                'eventuuid', 'correlationid', 'actoruserid', 'subjectuserid', 'action', 'entitytype', 'entityid',
                'source', 'result', 'severity', 'ipaddress', 'useragent', 'olddata', 'newdata', 'timecreated',
            ],
            'local_moderncommerce_products' => [
                'createdby', 'modifiedby', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_course_meta' => [
                'courseid', 'usermodified', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_bundle_meta' => [
                'bundleid', 'usermodified', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_bundle_outline' => [
                'bundleid', 'usermodified', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_bundle_tags' => [
                'bundleid', 'usermodified', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_widget' => [
                'usermodified', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_widget_preset' => [
                'usermodified', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_widget_slide' => [
                'usermodified', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_emailtpl' => [
                'name', 'created_by', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_notify_queue' => [
                'recipientuserid', 'recipientemail', 'subject', 'body', 'timecreated',
            ],
            'local_moderncommerce_notify_log' => [
                'recipientuserid', 'recipientemail', 'subject', 'timecreated',
            ],
            'local_moderncommerce_notify_digest' => [
                'recipientuserid', 'timecreated',
            ],
            'local_moderncommerce_notify_identity' => [
                'userid', 'externalid',
            ],
            'local_moderncommerce_notify_suppression' => [
                'userid', 'email',
            ],
            'local_moderncommerce_contacts' => [
                'fullname', 'email', 'phone', 'subject', 'message', 'status', 'source', 'timecreated',
            ],
            'local_moderncommerce_contact_replies' => [
                'userid', 'message', 'timecreated',
            ],
            'local_moderncommerce_user_subscriptions' => [
                'userid', 'planid', 'status', 'start_date', 'end_date', 'stripe_subscription_id',
                'stripe_customer_id', 'stripe_payment_method_id', 'paypal_subscription_id',
                'paystack_subscription_code', 'flutterwave_subscription_id', 'timecreated',
            ],
            'local_moderncommerce_subscription_history' => [
                'userid', 'action', 'createdby', 'timecreated',
            ],
            'local_moderncommerce_subscription_access' => [
                'userid', 'courseid', 'granted_at',
            ],
            'local_moderncommerce_subscription_key_usage' => [
                'userid', 'ipaddress', 'timecreated',
            ],
            'local_moderncommerce_subscription_log' => [
                'userid', 'action', 'timecreated',
            ],
            'local_moderncommerce_subscription_keys' => [
                'keycode', 'planid', 'value', 'currency', 'batchid', 'batchname', 'status', 'userids', 'cohortids',
                'requiredemail', 'notes', 'createdby', 'timecreated', 'timemodified',
            ],
            'local_moderncommerce_subscription_plans' => [
                'name', 'code', 'currency', 'status', 'createdby', 'timecreated', 'timemodified',
            ],
        ];
    }

    /**
     * Build metadata field strings.
     *
     * @param array $fields Field names.
     * @return array
     */
    private static function metadata_fields(array $fields): array {
        $metadata = [];
        foreach ($fields as $field) {
            $metadata[$field] = 'privacy:metadata:field:' . $field;
        }

        return $metadata;
    }

    /**
     * User reference fields used for userlist discovery.
     *
     * @return array
     */
    private static function user_fields(): array {
        return [
            'local_moderncommerce_billing_profiles' => ['userid'],
            'local_moderncommerce_carts' => ['userid'],
            'local_moderncommerce_orders' => ['userid', 'createdby', 'modifiedby'],
            'local_moderncommerce_order_status_history' => ['actoruserid'],
            'local_moderncommerce_invoices' => ['userid', 'createdby'],
            'local_moderncommerce_refunds' => ['requestedby', 'processedby'],
            'local_moderncommerce_coupons' => ['createdby'],
            'local_moderncommerce_coupon_usage' => ['userid'],
            'local_moderncommerce_enrollkeys' => ['createdby'],
            'local_moderncommerce_key_usage' => ['userid'],
            'local_moderncommerce_fulfillments' => ['userid'],
            'local_moderncommerce_entitlements' => ['userid'],
            'local_moderncommerce_entitlement_events' => ['actoruserid'],
            'local_moderncommerce_wishlist' => ['userid'],
            'local_moderncommerce_reviews' => ['userid'],
            'local_moderncommerce_review_rxn' => ['userid'],
            'local_moderncommerce_dashpref' => ['userid'],
            'local_moderncommerce_subscriber' => ['userid'],
            'local_moderncommerce_audit_log' => ['actoruserid', 'subjectuserid'],
            'local_moderncommerce_products' => ['createdby', 'modifiedby'],
            'local_moderncommerce_course_meta' => ['usermodified'],
            'local_moderncommerce_bundle_meta' => ['usermodified'],
            'local_moderncommerce_bundle_outline' => ['usermodified'],
            'local_moderncommerce_bundle_tags' => ['usermodified'],
            'local_moderncommerce_widget' => ['usermodified'],
            'local_moderncommerce_widget_preset' => ['usermodified'],
            'local_moderncommerce_widget_slide' => ['usermodified'],
            'local_moderncommerce_emailtpl' => ['created_by'],
            'local_moderncommerce_notify_queue' => ['recipientuserid'],
            'local_moderncommerce_notify_log' => ['recipientuserid'],
            'local_moderncommerce_notify_digest' => ['recipientuserid'],
            'local_moderncommerce_notify_identity' => ['userid'],
            'local_moderncommerce_notify_suppression' => ['userid'],
            'local_moderncommerce_contact_replies' => ['userid'],
            'local_moderncommerce_user_subscriptions' => ['userid'],
            'local_moderncommerce_subscription_history' => ['userid', 'createdby'],
            'local_moderncommerce_subscription_access' => ['userid'],
            'local_moderncommerce_subscription_key_usage' => ['userid'],
            'local_moderncommerce_subscription_log' => ['userid'],
            'local_moderncommerce_subscription_keys' => ['createdby'],
            'local_moderncommerce_subscription_plans' => ['createdby'],
        ];
    }

    /**
     * Check whether a user has any stored data.
     *
     * @param int $userid User ID.
     * @return bool
     */
    private static function user_has_data(int $userid): bool {
        global $DB;

        foreach (self::user_fields() as $table => $fields) {
            if (!self::table_exists($table)) {
                continue;
            }
            foreach ($fields as $field) {
                if ($DB->record_exists($table, [$field => $userid])) {
                    return true;
                }
            }
        }

        return !empty(self::get_user_order_ids($userid));
    }

    /**
     * Add users from one user field.
     *
     * @param userlist $userlist User list.
     * @param string $table Table name.
     * @param string $field User field.
     */
    private static function add_users_from_field(userlist $userlist, string $table, string $field): void {
        if (!self::table_exists($table)) {
            return;
        }

        $sql = "SELECT DISTINCT {$field}
                  FROM {{$table}}
                 WHERE {$field} IS NOT NULL
                   AND {$field} <> 0";
        $userlist->add_from_sql($field, $sql, []);
    }

    /**
     * Collect exportable data for a user.
     *
     * @param int $userid User ID.
     * @return array
     */
    private static function collect_user_data(int $userid): array {
        $data = [];
        $useremail = self::get_user_email($userid);
        $orderids = self::get_user_order_ids($userid);
        $cartids = self::get_ids_by_field('local_moderncommerce_carts', 'userid', $userid);
        $invoiceids = self::get_related_invoice_ids($userid, $orderids);
        $attemptids = self::get_ids_by_values('local_moderncommerce_payment_attempts', 'orderid', $orderids);
        $refundids = self::get_ids_by_values('local_moderncommerce_refunds', 'orderid', $orderids);
        $fulfillmentids = self::get_related_fulfillment_ids($userid, $orderids);
        $entitlementids = self::get_related_entitlement_ids($userid, $orderids);
        $orderrefs = self::get_fields_by_values('local_moderncommerce_orders', 'ordernumber', 'id', $orderids);

        self::add_records($data, 'billing_profiles', 'local_moderncommerce_billing_profiles', ['userid' => $userid]);
        self::add_records_by_ids($data, 'carts', 'local_moderncommerce_carts', $cartids);
        self::add_records_by_values($data, 'cart_items', 'local_moderncommerce_cart_items', 'cartid', $cartids);
        self::add_records_by_ids($data, 'orders', 'local_moderncommerce_orders', $orderids);
        self::add_records_by_values($data, 'order_operational', 'local_moderncommerce_order_operational', 'orderid', $orderids);
        self::add_records_by_values($data, 'order_items', 'local_moderncommerce_order_items', 'orderid', $orderids);
        self::add_records_by_values($data, 'order_addresses', 'local_moderncommerce_order_addresses', 'orderid', $orderids);
        self::add_records_by_values($data, 'order_adjustments', 'local_moderncommerce_order_adjustments', 'orderid', $orderids);
        self::add_records_by_values(
            $data,
            'order_status_history',
            'local_moderncommerce_order_status_history',
            'orderid',
            $orderids
        );
        self::add_records(
            $data,
            'order_status_actor_references',
            'local_moderncommerce_order_status_history',
            ['actoruserid' => $userid]
        );
        self::add_records_by_ids($data, 'invoices', 'local_moderncommerce_invoices', $invoiceids);
        self::add_records($data, 'invoices_created_by', 'local_moderncommerce_invoices', ['createdby' => $userid]);
        self::add_records_by_values($data, 'invoice_items', 'local_moderncommerce_invoice_items', 'invoiceid', $invoiceids);
        self::add_records_by_ids($data, 'payment_attempts', 'local_moderncommerce_payment_attempts', $attemptids);
        self::add_records_by_values($data, 'payment_events', 'local_moderncommerce_payment_events', 'orderid', $orderids);
        self::add_records_by_values(
            $data,
            'payment_events_by_attempt',
            'local_moderncommerce_payment_events',
            'attemptid',
            $attemptids
        );
        self::add_records_by_values($data, 'webhook_events', 'local_moderncommerce_webhook_events', 'reference', $orderrefs);
        self::add_records_by_values($data, 'payment_log', 'local_moderncommerce_payment_log', 'orderid', $orderids);
        self::add_records_by_ids($data, 'refunds', 'local_moderncommerce_refunds', $refundids);
        self::add_records($data, 'refunds_requested_by', 'local_moderncommerce_refunds', ['requestedby' => $userid]);
        self::add_records($data, 'refunds_processed_by', 'local_moderncommerce_refunds', ['processedby' => $userid]);
        self::add_records_by_values($data, 'refund_items', 'local_moderncommerce_refund_items', 'refundid', $refundids);
        self::add_records($data, 'coupon_usage', 'local_moderncommerce_coupon_usage', ['userid' => $userid]);
        self::add_records($data, 'coupons_created_by', 'local_moderncommerce_coupons', ['createdby' => $userid]);
        self::add_records($data, 'enrollkeys_created_by', 'local_moderncommerce_enrollkeys', ['createdby' => $userid]);
        if ($useremail !== '') {
            self::add_records(
                $data,
                'enrollkeys_by_email',
                'local_moderncommerce_enrollkeys',
                ['requiredemail' => $useremail]
            );
        }
        self::add_records($data, 'key_usage', 'local_moderncommerce_key_usage', ['userid' => $userid]);
        self::add_records_by_ids($data, 'fulfillments', 'local_moderncommerce_fulfillments', $fulfillmentids);
        self::add_records_by_values(
            $data,
            'fulfillment_items',
            'local_moderncommerce_fulfillment_items',
            'fulfillmentid',
            $fulfillmentids
        );
        self::add_records_by_ids($data, 'entitlements', 'local_moderncommerce_entitlements', $entitlementids);
        self::add_records_by_values(
            $data,
            'entitlement_events',
            'local_moderncommerce_entitlement_events',
            'entitlementid',
            $entitlementids
        );
        self::add_records(
            $data,
            'entitlement_events_actor_references',
            'local_moderncommerce_entitlement_events',
            ['actoruserid' => $userid]
        );
        self::add_records($data, 'wishlist', 'local_moderncommerce_wishlist', ['userid' => $userid]);
        self::add_records($data, 'course_reviews', 'local_moderncommerce_reviews', ['userid' => $userid]);
        self::add_records($data, 'course_review_reactions', 'local_moderncommerce_review_rxn', ['userid' => $userid]);
        self::add_records($data, 'dashboard_preferences', 'local_moderncommerce_dashpref', ['userid' => $userid]);
        self::add_records($data, 'newsletter_subscriptions', 'local_moderncommerce_subscriber', ['userid' => $userid]);
        if ($useremail !== '') {
            self::add_records(
                $data,
                'newsletter_subscriptions_by_email',
                'local_moderncommerce_subscriber',
                ['email' => $useremail]
            );
        }
        self::add_records_select(
            $data,
            'audit_log',
            'local_moderncommerce_audit_log',
            'actoruserid = :actoruserid OR subjectuserid = :subjectuserid',
            ['actoruserid' => $userid, 'subjectuserid' => $userid]
        );
        self::add_records_select(
            $data,
            'admin_references',
            'local_moderncommerce_products',
            'createdby = :createdby OR modifiedby = :modifiedby',
            ['createdby' => $userid, 'modifiedby' => $userid]
        );
        self::add_records(
            $data,
            'course_meta_admin_references',
            'local_moderncommerce_course_meta',
            ['usermodified' => $userid]
        );
        self::add_records(
            $data,
            'bundle_meta_admin_references',
            'local_moderncommerce_bundle_meta',
            ['usermodified' => $userid]
        );
        self::add_records(
            $data,
            'bundle_outline_admin_references',
            'local_moderncommerce_bundle_outline',
            ['usermodified' => $userid]
        );
        self::add_records(
            $data,
            'bundle_tags_admin_references',
            'local_moderncommerce_bundle_tags',
            ['usermodified' => $userid]
        );
        self::add_records(
            $data,
            'widget_admin_references',
            'local_moderncommerce_widget',
            ['usermodified' => $userid]
        );
        self::add_records(
            $data,
            'widget_preset_admin_references',
            'local_moderncommerce_widget_preset',
            ['usermodified' => $userid]
        );
        self::add_records(
            $data,
            'widget_slide_admin_references',
            'local_moderncommerce_widget_slide',
            ['usermodified' => $userid]
        );
        self::add_records($data, 'notify_queue', 'local_moderncommerce_notify_queue', ['recipientuserid' => $userid]);
        self::add_records($data, 'notify_log', 'local_moderncommerce_notify_log', ['recipientuserid' => $userid]);
        self::add_records($data, 'notify_digest', 'local_moderncommerce_notify_digest', ['recipientuserid' => $userid]);
        self::add_records($data, 'notify_identity', 'local_moderncommerce_notify_identity', ['userid' => $userid]);
        self::add_records($data, 'notify_suppression', 'local_moderncommerce_notify_suppression', ['userid' => $userid]);
        if ($useremail !== '') {
            self::add_records(
                $data,
                'notify_suppression_by_email',
                'local_moderncommerce_notify_suppression',
                ['email' => $useremail]
            );
        }

        self::add_records($data, 'contact_replies', 'local_moderncommerce_contact_replies', ['userid' => $userid]);
        if ($useremail !== '') {
            self::add_records($data, 'contacts_by_email', 'local_moderncommerce_contacts', ['email' => $useremail]);
        }

        self::add_records($data, 'subscriptions', 'local_moderncommerce_user_subscriptions', ['userid' => $userid]);
        self::add_records($data, 'subscription_history', 'local_moderncommerce_subscription_history', ['userid' => $userid]);
        self::add_records(
            $data,
            'subscription_history_created_by',
            'local_moderncommerce_subscription_history',
            ['createdby' => $userid]
        );
        self::add_records($data, 'subscription_access', 'local_moderncommerce_subscription_access', ['userid' => $userid]);
        self::add_records($data, 'subscription_key_usage', 'local_moderncommerce_subscription_key_usage', ['userid' => $userid]);
        self::add_records($data, 'subscription_log', 'local_moderncommerce_subscription_log', ['userid' => $userid]);

        // Subscription keys and plans authored by, targeted at, or restricted to this user.
        self::add_records(
            $data,
            'subscription_keys_created_by',
            'local_moderncommerce_subscription_keys',
            ['createdby' => $userid]
        );
        if ($useremail !== '') {
            self::add_records(
                $data,
                'subscription_keys_by_email',
                'local_moderncommerce_subscription_keys',
                ['requiredemail' => $useremail]
            );
        }
        $targetedkeys = self::get_subscription_keys_targeting_user($userid);
        if (!empty($targetedkeys)) {
            $data['subscription_keys_targeting'] = self::normalise_records($targetedkeys);
        }
        self::add_records(
            $data,
            'subscription_plans_created_by',
            'local_moderncommerce_subscription_plans',
            ['createdby' => $userid]
        );
        self::add_records(
            $data,
            'email_templates_created_by',
            'local_moderncommerce_emailtpl',
            ['created_by' => $userid]
        );

        return $data;
    }

    /**
     * Fetch subscription keys whose JSON userids list restricts the key to this user.
     *
     * The userids column stores a JSON array (for example "[12,34]"), so membership
     * cannot be tested with an exact SQL condition; candidate rows are filtered in PHP.
     *
     * @param int $userid User ID.
     * @return array Matching key records keyed by id.
     */
    private static function get_subscription_keys_targeting_user(int $userid): array {
        global $DB;

        $table = 'local_moderncommerce_subscription_keys';
        if (!self::table_exists($table)) {
            return [];
        }

        $candidates = $DB->get_records_select(
            $table,
            $DB->sql_isnotempty($table, 'userids', true, true),
            null
        );

        $matches = [];
        foreach ($candidates as $id => $record) {
            $allowed = json_decode((string)$record->userids, true);
            if (is_array($allowed) && in_array($userid, array_map('intval', $allowed), true)) {
                $matches[$id] = $record;
            }
        }

        return $matches;
    }

    /**
     * Delete all data for one user.
     *
     * @param int $userid User ID.
     */
    private static function delete_user_data(int $userid): void {
        $useremail = self::get_user_email($userid);
        $orderids = self::get_user_order_ids($userid);
        $cartids = self::get_ids_by_field('local_moderncommerce_carts', 'userid', $userid);
        $invoiceids = self::get_related_invoice_ids($userid, $orderids);
        $attemptids = self::get_ids_by_values('local_moderncommerce_payment_attempts', 'orderid', $orderids);
        $refundids = self::get_ids_by_values('local_moderncommerce_refunds', 'orderid', $orderids);
        $fulfillmentids = self::get_related_fulfillment_ids($userid, $orderids);
        $entitlementids = self::get_related_entitlement_ids($userid, $orderids);
        $orderrefs = self::get_fields_by_values('local_moderncommerce_orders', 'ordernumber', 'id', $orderids);
        $reviewids = self::get_ids_by_field('local_moderncommerce_reviews', 'userid', $userid);

        self::delete_by_values('local_moderncommerce_cart_items', 'cartid', $cartids);
        self::delete_records('local_moderncommerce_carts', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_billing_profiles', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_wishlist', ['userid' => $userid]);
        self::delete_by_values('local_moderncommerce_review_rxn', 'reviewid', $reviewids);
        self::delete_records('local_moderncommerce_review_rxn', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_reviews', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_dashpref', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_subscriber', ['userid' => $userid]);
        if ($useremail !== '') {
            self::delete_records('local_moderncommerce_subscriber', ['email' => $useremail]);
        }
        self::delete_by_values('local_moderncommerce_invoice_items', 'invoiceid', $invoiceids);
        self::delete_by_ids('local_moderncommerce_invoices', $invoiceids);
        self::delete_by_values('local_moderncommerce_refund_items', 'refundid', $refundids);
        self::delete_by_ids('local_moderncommerce_refunds', $refundids);
        self::delete_by_values('local_moderncommerce_payment_events', 'attemptid', $attemptids);
        self::delete_by_values('local_moderncommerce_payment_events', 'orderid', $orderids);
        self::delete_by_values('local_moderncommerce_payment_attempts', 'orderid', $orderids);
        self::delete_by_values('local_moderncommerce_payment_log', 'orderid', $orderids);
        self::delete_by_values('local_moderncommerce_webhook_events', 'reference', $orderrefs);
        self::delete_by_values('local_moderncommerce_fulfillment_items', 'fulfillmentid', $fulfillmentids);
        self::delete_by_ids('local_moderncommerce_fulfillments', $fulfillmentids);
        self::delete_by_values('local_moderncommerce_entitlement_events', 'entitlementid', $entitlementids);
        self::delete_by_ids('local_moderncommerce_entitlements', $entitlementids);
        self::delete_records('local_moderncommerce_coupon_usage', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_key_usage', ['userid' => $userid]);
        self::delete_by_values('local_moderncommerce_order_operational', 'orderid', $orderids);
        self::delete_by_values('local_moderncommerce_inventory_reservations', 'orderid', $orderids);
        self::delete_by_values('local_moderncommerce_order_addresses', 'orderid', $orderids);
        self::delete_by_values('local_moderncommerce_order_adjustments', 'orderid', $orderids);
        self::delete_by_values('local_moderncommerce_order_status_history', 'orderid', $orderids);
        self::delete_by_values('local_moderncommerce_order_items', 'orderid', $orderids);
        self::delete_by_values(
            'local_moderncommerce_audit_log',
            'entityid',
            $orderids,
            'entitytype = :entitytype',
            ['entitytype' => 'order']
        );
        self::delete_records_select(
            'local_moderncommerce_audit_log',
            'actoruserid = :actoruserid OR subjectuserid = :subjectuserid',
            ['actoruserid' => $userid, 'subjectuserid' => $userid]
        );
        self::delete_by_ids('local_moderncommerce_orders', $orderids);

        if ($useremail !== '') {
            self::set_field_select(
                'local_moderncommerce_enrollkeys',
                'requiredemail',
                null,
                'requiredemail = :requiredemail',
                ['requiredemail' => $useremail]
            );
        }

        self::delete_records('local_moderncommerce_notify_queue', ['recipientuserid' => $userid]);
        self::delete_records('local_moderncommerce_notify_log', ['recipientuserid' => $userid]);
        self::delete_records('local_moderncommerce_notify_digest', ['recipientuserid' => $userid]);
        self::delete_records('local_moderncommerce_notify_identity', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_notify_suppression', ['userid' => $userid]);
        if ($useremail !== '') {
            self::delete_records('local_moderncommerce_notify_suppression', ['email' => $useremail]);
        }

        // Admin-authored contact replies, plus any submissions (and their replies) under the user's email.
        self::delete_records('local_moderncommerce_contact_replies', ['userid' => $userid]);
        if ($useremail !== '') {
            $contactids = self::get_ids_by_field('local_moderncommerce_contacts', 'email', $useremail);
            self::delete_by_values('local_moderncommerce_contact_replies', 'contactid', $contactids);
            self::delete_records('local_moderncommerce_contacts', ['email' => $useremail]);
        }

        // Subscriptions: remove the user's subscriptions and all child records tied to them.
        $subids = self::get_ids_by_field('local_moderncommerce_user_subscriptions', 'userid', $userid);
        self::delete_by_values('local_moderncommerce_subscription_history', 'subscriptionid', $subids);
        self::delete_by_values('local_moderncommerce_subscription_reminders', 'subscriptionid', $subids);
        self::delete_by_values('local_moderncommerce_subscription_access', 'subscriptionid', $subids);
        self::delete_by_values('local_moderncommerce_subscription_key_usage', 'subscriptionid', $subids);
        self::delete_by_values('local_moderncommerce_subscription_log', 'subscriptionid', $subids);
        self::delete_records('local_moderncommerce_subscription_history', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_subscription_access', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_subscription_key_usage', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_subscription_log', ['userid' => $userid]);
        self::delete_records('local_moderncommerce_user_subscriptions', ['userid' => $userid]);

        // Subscription keys are site-owned targeting records, not the user's own data, so scrub the
        // user's identifiers in place rather than deleting the key. Email/userid restrictions and the
        // createdby author reference are removed; the anonymise pass below clears createdby.
        if ($useremail !== '') {
            self::set_field_select(
                'local_moderncommerce_subscription_keys',
                'requiredemail',
                null,
                'requiredemail = :requiredemail',
                ['requiredemail' => $useremail]
            );
        }
        self::remove_user_from_subscription_keys($userid);

        self::anonymise_user_references($userid);
    }

    /**
     * Remove a user's ID from the JSON userids restriction list on any subscription key.
     *
     * @param int $userid User ID.
     */
    private static function remove_user_from_subscription_keys(int $userid): void {
        global $DB;

        $table = 'local_moderncommerce_subscription_keys';
        if (!self::table_exists($table)) {
            return;
        }

        $candidates = $DB->get_records_select(
            $table,
            $DB->sql_isnotempty($table, 'userids', true, true),
            null,
            '',
            'id, userids'
        );

        foreach ($candidates as $record) {
            $allowed = json_decode((string)$record->userids, true);
            if (!is_array($allowed)) {
                continue;
            }
            $filtered = array_values(array_filter(
                array_map('intval', $allowed),
                static function (int $id) use ($userid): bool {
                    return $id !== $userid;
                }
            ));
            if (count($filtered) !== count($allowed)) {
                $DB->set_field($table, 'userids', json_encode($filtered), ['id' => $record->id]);
            }
        }
    }

    /**
     * Anonymise non-owned records which only reference the user as an actor.
     *
     * @param int $userid User ID.
     */
    private static function anonymise_user_references(int $userid): void {
        foreach (self::anonymise_fields() as $table => $fields) {
            foreach ($fields as $field => $replacement) {
                self::set_field_select($table, $field, $replacement, "{$field} = :userid", ['userid' => $userid]);
            }
        }
    }

    /**
     * User reference fields which can be anonymised.
     *
     * @return array
     */
    private static function anonymise_fields(): array {
        return [
            'local_moderncommerce_products' => ['createdby' => null, 'modifiedby' => null],
            'local_moderncommerce_course_meta' => ['usermodified' => null],
            'local_moderncommerce_bundle_meta' => ['usermodified' => null],
            'local_moderncommerce_bundle_outline' => ['usermodified' => null],
            'local_moderncommerce_bundle_tags' => ['usermodified' => null],
            'local_moderncommerce_widget' => ['usermodified' => null],
            'local_moderncommerce_widget_preset' => ['usermodified' => null],
            'local_moderncommerce_widget_slide' => ['usermodified' => null],
            'local_moderncommerce_coupons' => ['createdby' => null],
            'local_moderncommerce_enrollkeys' => ['createdby' => null, 'requiredemail' => null],
            'local_moderncommerce_order_status_history' => ['actoruserid' => null],
            'local_moderncommerce_entitlement_events' => ['actoruserid' => null],
            'local_moderncommerce_invoices' => ['createdby' => null],
            'local_moderncommerce_refunds' => ['processedby' => null, 'requestedby' => 0],
            'local_moderncommerce_subscription_keys' => ['createdby' => null],
            'local_moderncommerce_subscription_plans' => ['createdby' => null],
            'local_moderncommerce_subscription_history' => ['createdby' => null],
            'local_moderncommerce_emailtpl' => ['created_by' => null],
        ];
    }

    /**
     * Tables cleared for full-system privacy deletion.
     *
     * @return array
     */
    private static function delete_all_tables(): array {
        return [
            'local_moderncommerce_cart_items',
            'local_moderncommerce_carts',
            'local_moderncommerce_billing_profiles',
            'local_moderncommerce_invoice_items',
            'local_moderncommerce_invoices',
            'local_moderncommerce_refund_items',
            'local_moderncommerce_refunds',
            'local_moderncommerce_payment_events',
            'local_moderncommerce_payment_attempts',
            'local_moderncommerce_payment_log',
            'local_moderncommerce_webhook_events',
            'local_moderncommerce_fulfillment_items',
            'local_moderncommerce_fulfillments',
            'local_moderncommerce_entitlement_events',
            'local_moderncommerce_entitlements',
            'local_moderncommerce_coupon_usage',
            'local_moderncommerce_key_usage',
            'local_moderncommerce_wishlist',
            'local_moderncommerce_review_rxn',
            'local_moderncommerce_reviews',
            'local_moderncommerce_dashpref',
            'local_moderncommerce_subscriber',
            'local_moderncommerce_inventory_reservations',
            'local_moderncommerce_order_addresses',
            'local_moderncommerce_order_adjustments',
            'local_moderncommerce_order_status_history',
            'local_moderncommerce_order_operational',
            'local_moderncommerce_order_items',
            'local_moderncommerce_audit_log',
            'local_moderncommerce_orders',
            'local_moderncommerce_notify_queue',
            'local_moderncommerce_notify_log',
            'local_moderncommerce_notify_digest',
            'local_moderncommerce_notify_identity',
            'local_moderncommerce_notify_suppression',
            'local_moderncommerce_contact_replies',
            'local_moderncommerce_contacts',
            // Subscription subsystem (children before parents for FK-friendly truncation).
            'local_moderncommerce_subscription_log',
            'local_moderncommerce_subscription_key_usage',
            'local_moderncommerce_subscription_keys',
            'local_moderncommerce_subscription_access',
            'local_moderncommerce_subscription_reminders',
            'local_moderncommerce_subscription_history',
            'local_moderncommerce_user_subscriptions',
            'local_moderncommerce_subscription_feature_map',
            'local_moderncommerce_subscription_access_rules',
            'local_moderncommerce_subscription_plan_features',
            'local_moderncommerce_subscription_features',
            'local_moderncommerce_subscription_emailtpl',
            'local_moderncommerce_subscription_plans',
        ];
    }

    /**
     * Add records matching exact conditions.
     *
     * @param array $data Data collection.
     * @param string $name Export name.
     * @param string $table Table name.
     * @param array $conditions Conditions.
     */
    private static function add_records(array &$data, string $name, string $table, array $conditions): void {
        global $DB;

        if (!self::table_exists($table)) {
            return;
        }

        $records = $DB->get_records($table, $conditions);
        if ($records) {
            $data[$name] = self::normalise_records($records);
        }
    }

    /**
     * Add records matching a custom select.
     *
     * @param array $data Data collection.
     * @param string $name Export name.
     * @param string $table Table name.
     * @param string $select SQL select.
     * @param array $params SQL params.
     */
    private static function add_records_select(
        array &$data,
        string $name,
        string $table,
        string $select,
        array $params
    ): void {
        global $DB;

        if (!self::table_exists($table)) {
            return;
        }

        $records = $DB->get_records_select($table, $select, $params);
        if ($records) {
            $data[$name] = self::normalise_records($records);
        }
    }

    /**
     * Add records by primary IDs.
     *
     * @param array $data Data collection.
     * @param string $name Export name.
     * @param string $table Table name.
     * @param array $ids IDs.
     */
    private static function add_records_by_ids(array &$data, string $name, string $table, array $ids): void {
        self::add_records_by_values($data, $name, $table, 'id', $ids);
    }

    /**
     * Add records by field values.
     *
     * @param array $data Data collection.
     * @param string $name Export name.
     * @param string $table Table name.
     * @param string $field Field name.
     * @param array $values Values.
     */
    private static function add_records_by_values(
        array &$data,
        string $name,
        string $table,
        string $field,
        array $values
    ): void {
        global $DB;

        $values = self::clean_values($values);
        if (empty($values) || !self::table_exists($table)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($values, SQL_PARAMS_NAMED);
        $records = $DB->get_records_select($table, "{$field} {$insql}", $params);
        if ($records) {
            $data[$name] = self::normalise_records($records);
        }
    }

    /**
     * Get order IDs owned by a user.
     *
     * @param int $userid User ID.
     * @return array
     */
    private static function get_user_order_ids(int $userid): array {
        return self::get_ids_by_field('local_moderncommerce_orders', 'userid', $userid);
    }

    /**
     * Get a user's email address.
     *
     * @param int $userid User ID.
     * @return string Email address.
     */
    private static function get_user_email(int $userid): string {
        global $DB;

        $email = $DB->get_field('user', 'email', ['id' => $userid], IGNORE_MISSING);
        return $email === false ? '' : (string)$email;
    }

    /**
     * Get related invoice IDs.
     *
     * @param int $userid User ID.
     * @param array $orderids Order IDs.
     * @return array
     */
    private static function get_related_invoice_ids(int $userid, array $orderids): array {
        return array_values(array_unique(array_merge(
            self::get_ids_by_field('local_moderncommerce_invoices', 'userid', $userid),
            self::get_ids_by_values('local_moderncommerce_invoices', 'orderid', $orderids)
        )));
    }

    /**
     * Get related fulfillment IDs.
     *
     * @param int $userid User ID.
     * @param array $orderids Order IDs.
     * @return array
     */
    private static function get_related_fulfillment_ids(int $userid, array $orderids): array {
        return array_values(array_unique(array_merge(
            self::get_ids_by_field('local_moderncommerce_fulfillments', 'userid', $userid),
            self::get_ids_by_values('local_moderncommerce_fulfillments', 'orderid', $orderids)
        )));
    }

    /**
     * Get related entitlement IDs.
     *
     * @param int $userid User ID.
     * @param array $orderids Order IDs.
     * @return array
     */
    private static function get_related_entitlement_ids(int $userid, array $orderids): array {
        return array_values(array_unique(array_merge(
            self::get_ids_by_field('local_moderncommerce_entitlements', 'userid', $userid),
            self::get_ids_by_values('local_moderncommerce_entitlements', 'orderid', $orderids)
        )));
    }

    /**
     * Get IDs by exact field value.
     *
     * @param string $table Table name.
     * @param string $field Field name.
     * @param mixed $value Field value.
     * @return array
     */
    private static function get_ids_by_field(string $table, string $field, $value): array {
        global $DB;

        if (!self::table_exists($table)) {
            return [];
        }

        return array_map('intval', $DB->get_fieldset_select($table, 'id', "{$field} = :value", ['value' => $value]));
    }

    /**
     * Get IDs by field values.
     *
     * @param string $table Table name.
     * @param string $field Field name.
     * @param array $values Values.
     * @return array
     */
    private static function get_ids_by_values(string $table, string $field, array $values): array {
        return array_map('intval', self::get_fields_by_values($table, 'id', $field, $values));
    }

    /**
     * Get field values by another field.
     *
     * @param string $table Table name.
     * @param string $returnfield Field to return.
     * @param string $field Field to filter by.
     * @param array $values Values.
     * @return array
     */
    private static function get_fields_by_values(string $table, string $returnfield, string $field, array $values): array {
        global $DB;

        $values = self::clean_values($values);
        if (empty($values) || !self::table_exists($table)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($values, SQL_PARAMS_NAMED);
        return $DB->get_fieldset_select($table, $returnfield, "{$field} {$insql}", $params);
    }

    /**
     * Delete records by exact conditions.
     *
     * @param string $table Table name.
     * @param array $conditions Conditions.
     */
    private static function delete_records(string $table, array $conditions): void {
        global $DB;

        if (self::table_exists($table)) {
            $DB->delete_records($table, $conditions);
        }
    }

    /**
     * Delete records by custom select.
     *
     * @param string $table Table name.
     * @param string $select SQL select.
     * @param array $params SQL params.
     */
    private static function delete_records_select(string $table, string $select, array $params): void {
        global $DB;

        if (self::table_exists($table)) {
            $DB->delete_records_select($table, $select, $params);
        }
    }

    /**
     * Delete by primary IDs.
     *
     * @param string $table Table name.
     * @param array $ids IDs.
     */
    private static function delete_by_ids(string $table, array $ids): void {
        self::delete_by_values($table, 'id', $ids);
    }

    /**
     * Delete by field values.
     *
     * @param string $table Table name.
     * @param string $field Field name.
     * @param array $values Values.
     * @param string $extra Extra select.
     * @param array $extraparams Extra params.
     */
    private static function delete_by_values(
        string $table,
        string $field,
        array $values,
        string $extra = '',
        array $extraparams = []
    ): void {
        global $DB;

        $values = self::clean_values($values);
        if (empty($values) || !self::table_exists($table)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($values, SQL_PARAMS_NAMED);
        $select = "{$field} {$insql}";
        if ($extra !== '') {
            $select = "({$select}) AND ({$extra})";
            $params = array_merge($params, $extraparams);
        }
        $DB->delete_records_select($table, $select, $params);
    }

    /**
     * Set a field by custom select.
     *
     * @param string $table Table name.
     * @param string $field Field name.
     * @param mixed $value Replacement value.
     * @param string $select SQL select.
     * @param array $params SQL params.
     */
    private static function set_field_select(string $table, string $field, $value, string $select, array $params): void {
        global $DB;

        if (self::table_exists($table)) {
            $DB->set_field_select($table, $field, $value, $select, $params);
        }
    }

    /**
     * Normalise records for export.
     *
     * @param array $records Records.
     * @return array
     */
    private static function normalise_records(array $records): array {
        $normalised = [];
        foreach ($records as $record) {
            $normalised[] = self::normalise_record($record);
        }

        return $normalised;
    }

    /**
     * Normalise one record for export.
     *
     * @param object $record Record.
     * @return array
     */
    private static function normalise_record(object $record): array {
        $data = (array)$record;
        foreach (['rawpayload', 'payload', 'response', 'metadata', 'eventdata', 'olddata', 'newdata'] as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                continue;
            }
            $decoded = json_decode((string)$data[$field], true);
            if (is_array($decoded)) {
                $data[$field] = self::payload_for_storage($decoded);
            }
        }

        return $data;
    }

    /**
     * Encode a redacted payload snapshot.
     *
     * @param mixed $payload Payload.
     * @return string JSON payload.
     */
    private static function payload_for_storage($payload): string {
        $json = json_encode(self::redact_payload($payload));
        return $json === false ? '{}' : $json;
    }

    /**
     * Redact personal and payment-card payload values.
     *
     * @param mixed $value Payload value.
     * @return mixed Redacted value.
     */
    private static function redact_payload($value) {
        $sensitive = [
            'address', 'address1', 'address2', 'authorization', 'billing', 'card', 'customer', 'customer_email',
            'email', 'first_name', 'firstname', 'ip', 'ipaddress', 'last_name', 'lastname', 'metadata', 'name',
            'payer', 'phone', 'receipt_email', 'shipping', 'user_agent', 'user_id', 'useragent', 'userid',
        ];

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && in_array(strtolower($key), $sensitive, true)) {
                    $redacted[$key] = '[redacted]';
                    continue;
                }
                $redacted[$key] = self::redact_payload($item);
            }
            return $redacted;
        }

        if (is_object($value)) {
            return self::redact_payload(get_object_vars($value));
        }

        if (is_string($value) && strlen($value) > 512) {
            return substr($value, 0, 512) . '...';
        }

        return $value;
    }

    /**
     * Clean ID/value list.
     *
     * @param array $values Values.
     * @return array
     */
    private static function clean_values(array $values): array {
        $values = array_filter($values, static function ($value): bool {
            return $value !== null && $value !== '';
        });

        return array_values(array_unique($values));
    }

    /**
     * Check whether a table exists.
     *
     * @param string $table Table name.
     * @return bool
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }
}
