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

namespace local_moderncommerce\observer;

use local_moderncommerce\subscription\services\subscription_service;

/**
 * User observer - cleans up Modern Commerce data when a user account is deleted.
 *
 * Deletion is tiered:
 *
 * 1. Identity and contactability data is ALWAYS purged. These rows either point at a
 *    live external account (chat identity links) or would cause the site to keep
 *    messaging a deleted person. Retaining them has no accounting value, so the
 *    keep_deleted_user_history setting deliberately does not apply.
 * 2. Commercial history (subscriptions, entitlements) honours keep_deleted_user_history:
 *    deleted outright when off, anonymised or revoked when on.
 *
 * Order, invoice and payment records are intentionally NOT touched here - those are
 * financial records with their own retention requirements, and are removed only through
 * an explicit GDPR request via \local_moderncommerce\privacy\provider.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_observer {
    /**
     * Handle the user deleted event.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        $data = $event->get_data();
        $userid = (int)$data['objectid'];

        if ($userid <= 0) {
            return;
        }

        // Core scrambles the user record on delete, so the original address must come
        // from the event payload. It is a required field of core\event\user_deleted.
        $email = (string)($data['other']['email'] ?? '');

        $keephistory = (bool)get_config('local_moderncommerce', 'keep_deleted_user_history');

        self::purge_identity_data($userid, $email);
        self::purge_entitlements($userid, $keephistory);
        self::purge_subscriptions($userid, $keephistory);
    }

    /**
     * Purge identity, contactability and personal-preference data.
     *
     * Always runs in full - see the class docblock for why history retention does not apply.
     *
     * @param int $userid User ID.
     * @param string $email Original email address from the event payload.
     */
    private static function purge_identity_data(int $userid, string $email): void {
        global $DB;

        // Linked external chat accounts (Slack/Teams). These authorise delivery to a real
        // third-party account, so they must never outlive the Moodle user.
        $DB->delete_records('local_moderncommerce_notify_identity', ['userid' => $userid]);

        // Pending and historical notifications addressed to this user.
        $DB->delete_records('local_moderncommerce_notify_queue', ['recipientuserid' => $userid]);
        $DB->delete_records('local_moderncommerce_notify_log', ['recipientuserid' => $userid]);
        $DB->delete_records('local_moderncommerce_notify_digest', ['recipientuserid' => $userid]);

        // Suppression rows are keyed on userid AND email. They are deleted rather than
        // anonymised because userid = 0 is the column default, so anonymising would make
        // them indistinguishable from a genuine anonymous unsubscribe.
        $DB->delete_records('local_moderncommerce_notify_suppression', ['userid' => $userid]);

        // Shopping state and personal preferences.
        $cartids = $DB->get_fieldset_select('local_moderncommerce_carts', 'id', 'userid = :userid', ['userid' => $userid]);
        if (!empty($cartids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($cartids, SQL_PARAMS_NAMED, 'cart');
            $DB->delete_records_select('local_moderncommerce_cart_items', "cartid {$insql}", $inparams);
        }
        $DB->delete_records('local_moderncommerce_carts', ['userid' => $userid]);
        $DB->delete_records('local_moderncommerce_billing_profiles', ['userid' => $userid]);
        $DB->delete_records('local_moderncommerce_wishlist', ['userid' => $userid]);
        $DB->delete_records('local_moderncommerce_dashpref', ['userid' => $userid]);
        $DB->delete_records('local_moderncommerce_subscriber', ['userid' => $userid]);

        if ($email === '') {
            return;
        }

        // Email-keyed rows survive the userid purge above and must be cleared separately.
        $DB->delete_records('local_moderncommerce_notify_suppression', ['email' => $email]);
        $DB->delete_records('local_moderncommerce_subscriber', ['email' => $email]);

        // Contact submissions are stored against the submitting email address.
        $contactids = $DB->get_fieldset_select('local_moderncommerce_contacts', 'id', 'email = :email', ['email' => $email]);
        if (!empty($contactids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($contactids, SQL_PARAMS_NAMED, 'contact');
            $DB->delete_records_select('local_moderncommerce_contact_replies', "contactid {$insql}", $inparams);
        }
        $DB->delete_records('local_moderncommerce_contacts', ['email' => $email]);
        $DB->delete_records('local_moderncommerce_contact_replies', ['userid' => $userid]);

        // Enrolment keys restricted to this address would otherwise stay pinned to it.
        $DB->set_field_select(
            'local_moderncommerce_enrollkeys',
            'requiredemail',
            null,
            'requiredemail = :requiredemail',
            ['requiredemail' => $email]
        );
    }

    /**
     * Revoke or remove the user's course entitlements.
     *
     * @param int $userid User ID.
     * @param bool $keephistory Whether to retain records for reporting.
     */
    private static function purge_entitlements(int $userid, bool $keephistory): void {
        global $DB;

        $entitlementids = $DB->get_fieldset_select(
            'local_moderncommerce_entitlements',
            'id',
            'userid = :userid',
            ['userid' => $userid]
        );

        if (empty($entitlementids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($entitlementids, SQL_PARAMS_NAMED, 'ent');

        if ($keephistory) {
            // Keep the row for reporting, but make sure it no longer reads as live access.
            $now = time();
            $DB->execute(
                "UPDATE {local_moderncommerce_entitlements}
                    SET status = :status, timerevoked = :timerevoked, revokereason = :reason, timemodified = :now
                  WHERE id {$insql} AND status <> :revoked",
                $inparams + [
                    'status' => 'revoked',
                    'timerevoked' => $now,
                    'reason' => 'User account deleted',
                    'now' => $now,
                    'revoked' => 'revoked',
                ]
            );
            return;
        }

        $DB->delete_records_select('local_moderncommerce_entitlement_events', "entitlementid {$insql}", $inparams);
        $DB->delete_records_select('local_moderncommerce_entitlements', "id {$insql}", $inparams);
    }

    /**
     * Cancel and clean up the user's subscriptions.
     *
     * @param int $userid User ID.
     * @param bool $keephistory Whether to retain records for reporting.
     */
    private static function purge_subscriptions(int $userid, bool $keephistory): void {
        global $DB;

        $subscriptions = $DB->get_records('local_moderncommerce_user_subscriptions', ['userid' => $userid]);

        foreach ($subscriptions as $subscription) {
            // Cancel active subscriptions so downstream billing stops.
            if (in_array($subscription->status, ['active', 'trial', 'grace'])) {
                try {
                    subscription_service::cancel($subscription->id, 'User account deleted');
                } catch (\Exception $e) {
                    // Log but continue - one failed cancellation must not abort the purge.
                    debugging("Error cancelling subscription {$subscription->id}: " . $e->getMessage());
                }
            }

            // Delete access records.
            $DB->delete_records('local_moderncommerce_subscription_access', ['subscriptionid' => $subscription->id]);

            // Delete reminder records.
            $DB->delete_records('local_moderncommerce_subscription_reminders', ['subscriptionid' => $subscription->id]);
        }

        if (!$keephistory) {
            // Delete history records.
            $DB->execute(
                "DELETE FROM {local_moderncommerce_subscription_history}
                 WHERE subscriptionid IN (SELECT id FROM {local_moderncommerce_user_subscriptions} WHERE userid = :userid)",
                ['userid' => $userid]
            );

            // Delete subscription records.
            $DB->delete_records('local_moderncommerce_user_subscriptions', ['userid' => $userid]);
        } else {
            // Anonymize subscription records (keep for historical/financial records).
            $DB->execute(
                "UPDATE {local_moderncommerce_user_subscriptions}
                 SET userid = 0, timemodified = :now
                 WHERE userid = :userid",
                ['now' => time(), 'userid' => $userid]
            );
        }
    }
}
