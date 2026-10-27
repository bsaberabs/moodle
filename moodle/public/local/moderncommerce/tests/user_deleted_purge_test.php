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
 * Tests for the user_deleted purge in local_moderncommerce.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Verifies that deleting a user purges their Modern Commerce identity data.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_moderncommerce\observer\user_observer::class)]
final class user_deleted_purge_test extends advanced_testcase {
    /**
     * Seed one row in every table the purge is responsible for.
     *
     * @param int $userid User ID.
     * @param string $email User email address.
     */
    private function seed_user_data(int $userid, string $email): void {
        global $DB;

        $now = time();

        $DB->insert_record('local_moderncommerce_notify_identity', (object)[
            'userid' => $userid,
            'provider' => 'slack',
            'externalid' => 'U123456',
            'status' => 'linked',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('local_moderncommerce_notify_queue', (object)[
            'component' => 'local_moderncommerce',
            'eventkey' => 'order_paid',
            'category' => 'transactional',
            'recipientuserid' => $userid,
            'recipientemail' => $email,
            'channel' => 'email',
            'dedupekey' => sha1("queue-{$userid}"),
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('local_moderncommerce_notify_log', (object)[
            'component' => 'local_moderncommerce',
            'eventkey' => 'order_paid',
            'category' => 'transactional',
            'recipientuserid' => $userid,
            'channel' => 'email',
            'result' => 'sent',
            'timecreated' => $now,
        ]);

        $DB->insert_record('local_moderncommerce_notify_digest', (object)[
            'recipientuserid' => $userid,
            'frequency' => 'daily',
            'category' => 'operational',
            'eventkey' => 'order_paid',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Two suppression rows: one keyed by userid, one keyed only by email.
        $DB->insert_record('local_moderncommerce_notify_suppression', (object)[
            'userid' => $userid,
            'scope' => 'all',
            'reason' => 'unsubscribe',
            'timecreated' => $now,
        ]);
        $DB->insert_record('local_moderncommerce_notify_suppression', (object)[
            'userid' => 0,
            'email' => $email,
            'scope' => 'all',
            'reason' => 'bounce',
            'timecreated' => $now,
        ]);

        $DB->insert_record('local_moderncommerce_wishlist', (object)[
            'userid' => $userid,
            'productid' => 1,
            'timecreated' => $now,
        ]);

        $DB->insert_record('local_moderncommerce_dashpref', (object)[
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $DB->insert_record('local_moderncommerce_subscriber', (object)[
            'email' => $email,
            'userid' => $userid,
            'timecreated' => $now,
        ]);

        $DB->insert_record('local_moderncommerce_entitlements', (object)[
            'sourcekey' => "test-entitlement-{$userid}",
            'userid' => $userid,
            'courseid' => 2,
            'status' => 'active',
            'timegranted' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Identity and contactability rows must never survive account deletion.
     */
    public function test_identity_data_is_always_purged(): void {
        global $DB;

        $this->resetAfterTest();

        // History retention on: financial history is kept, identity data still goes.
        set_config('keep_deleted_user_history', 1, 'local_moderncommerce');

        $user = $this->getDataGenerator()->create_user(['email' => 'buyer@example.com']);
        $this->seed_user_data((int)$user->id, 'buyer@example.com');

        delete_user($user);

        $this->assertSame(0, $DB->count_records('local_moderncommerce_notify_identity', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_moderncommerce_notify_queue', ['recipientuserid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_moderncommerce_notify_log', ['recipientuserid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_moderncommerce_notify_digest', ['recipientuserid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_moderncommerce_wishlist', ['userid' => $user->id]));
        $this->assertSame(0, $DB->count_records('local_moderncommerce_dashpref', ['userid' => $user->id]));
    }

    /**
     * Suppression rows are keyed on userid AND email; both must be removed.
     */
    public function test_email_keyed_rows_are_purged(): void {
        global $DB;

        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['email' => 'buyer@example.com']);
        $this->seed_user_data((int)$user->id, 'buyer@example.com');

        $this->assertSame(2, $DB->count_records('local_moderncommerce_notify_suppression'));

        delete_user($user);

        // Both the userid-keyed and the email-only row must be gone. The email-only row
        // is the regression guard: purging by userid alone would leave it behind.
        $this->assertSame(0, $DB->count_records('local_moderncommerce_notify_suppression'));
        $this->assertSame(0, $DB->count_records('local_moderncommerce_subscriber', ['email' => 'buyer@example.com']));
    }

    /**
     * With history retention on, entitlements are revoked rather than deleted.
     */
    public function test_entitlements_are_revoked_when_history_kept(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('keep_deleted_user_history', 1, 'local_moderncommerce');

        $user = $this->getDataGenerator()->create_user(['email' => 'buyer@example.com']);
        $this->seed_user_data((int)$user->id, 'buyer@example.com');

        delete_user($user);

        $entitlement = $DB->get_record('local_moderncommerce_entitlements', ['userid' => $user->id]);
        $this->assertNotFalse($entitlement, 'Entitlement should be retained when history is kept.');
        $this->assertSame('revoked', $entitlement->status);
        $this->assertGreaterThan(0, (int)$entitlement->timerevoked);
    }

    /**
     * With history retention off, entitlements and their events are deleted.
     */
    public function test_entitlements_are_deleted_when_history_discarded(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('keep_deleted_user_history', 0, 'local_moderncommerce');

        $user = $this->getDataGenerator()->create_user(['email' => 'buyer@example.com']);
        $this->seed_user_data((int)$user->id, 'buyer@example.com');

        $entitlementid = $DB->get_field('local_moderncommerce_entitlements', 'id', ['userid' => $user->id]);
        $DB->insert_record('local_moderncommerce_entitlement_events', (object)[
            'entitlementid' => $entitlementid,
            'eventuuid' => 'test-event-uuid-1',
            'eventtype' => 'granted',
            'newstatus' => 'active',
            'timecreated' => time(),
        ]);

        delete_user($user);

        $this->assertSame(0, $DB->count_records('local_moderncommerce_entitlements', ['userid' => $user->id]));
        $this->assertSame(
            0,
            $DB->count_records('local_moderncommerce_entitlement_events', ['entitlementid' => $entitlementid])
        );
    }

    /**
     * Deleting one user must not touch another user's rows.
     */
    public function test_other_users_are_unaffected(): void {
        global $DB;

        $this->resetAfterTest();

        $victim = $this->getDataGenerator()->create_user(['email' => 'buyer@example.com']);
        $bystander = $this->getDataGenerator()->create_user(['email' => 'other@example.com']);

        $this->seed_user_data((int)$victim->id, 'buyer@example.com');
        $this->seed_user_data((int)$bystander->id, 'other@example.com');

        delete_user($victim);

        $this->assertSame(1, $DB->count_records('local_moderncommerce_notify_identity', ['userid' => $bystander->id]));
        $this->assertSame(1, $DB->count_records('local_moderncommerce_wishlist', ['userid' => $bystander->id]));
        $this->assertSame(
            1,
            $DB->count_records('local_moderncommerce_notify_suppression', ['email' => 'other@example.com'])
        );
    }
}
