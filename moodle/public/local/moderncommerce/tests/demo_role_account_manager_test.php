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
 * Tests for Modern Commerce demo role preview accounts.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_moderncommerce\demo\role_account_manager;

/**
 * Verifies demo role preview accounts are explicit, idempotent, and safe to remove.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(role_account_manager::class)]
final class demo_role_account_manager_test extends advanced_testcase {
    /**
     * Prepare an isolated test state.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        role_account_manager::remove_accounts();
    }

    /**
     * Seeding creates the expected users and assigns each role at system context.
     */
    public function test_seed_accounts_creates_users_and_system_role_assignments(): void {
        global $DB;

        $result = role_account_manager::seed_accounts();
        $this->assertSame(count(role_account_manager::accounts()), $result['created']);
        $this->assertSame(0, $result['skipped']);

        $context = context_system::instance();
        foreach (role_account_manager::accounts() as $key => $account) {
            $user = $DB->get_record('user', ['username' => $account['username'], 'deleted' => 0], '*', MUST_EXIST);
            $this->assertSame('MC-DEMO-ROLE-' . $key, $user->idnumber);

            $role = $DB->get_record('role', ['shortname' => $account['role']], 'id', MUST_EXIST);
            $this->assertSame(1, $DB->count_records('role_assignments', [
                'roleid' => $role->id,
                'userid' => $user->id,
                'contextid' => $context->id,
            ]));
        }
    }

    /**
     * Rerunning updates managed users and does not create duplicate role assignments.
     */
    public function test_seed_accounts_is_idempotent(): void {
        global $DB;

        role_account_manager::seed_accounts();
        $second = role_account_manager::seed_accounts();

        $this->assertSame(0, $second['created']);
        $this->assertSame(count(role_account_manager::accounts()), $second['updated']);

        $context = context_system::instance();
        foreach (role_account_manager::accounts() as $account) {
            $user = $DB->get_record('user', ['username' => $account['username'], 'deleted' => 0], 'id', MUST_EXIST);
            $role = $DB->get_record('role', ['shortname' => $account['role']], 'id', MUST_EXIST);
            $this->assertSame(1, $DB->count_records('role_assignments', [
                'roleid' => $role->id,
                'userid' => $user->id,
                'contextid' => $context->id,
            ]));
        }
    }

    /**
     * Removal deletes only the marked demo role accounts.
     */
    public function test_remove_accounts_deletes_marked_accounts(): void {
        global $DB;

        role_account_manager::seed_accounts();
        $result = role_account_manager::remove_accounts();

        $this->assertSame(count(role_account_manager::accounts()), $result['deleted']);
        foreach (role_account_manager::accounts() as $account) {
            $this->assertFalse($DB->record_exists('user', [
                'username' => $account['username'],
                'deleted' => 0,
            ]));
        }
    }

    /**
     * A normal Moodle user with a demo username is skipped and not modified.
     */
    public function test_unmarked_username_collision_is_skipped(): void {
        global $DB;

        $manualuser = self::getDataGenerator()->create_user([
            'username' => 'mcdemo_finance',
            'idnumber' => 'manual-finance-user',
        ]);

        $result = role_account_manager::seed_accounts();
        $this->assertGreaterThanOrEqual(1, $result['skipped']);

        $role = $DB->get_record('role', ['shortname' => 'moderncommercefinance'], 'id', MUST_EXIST);
        $this->assertSame(0, $DB->count_records('role_assignments', [
            'roleid' => $role->id,
            'userid' => $manualuser->id,
        ]));

        $stored = $DB->get_record('user', ['id' => $manualuser->id], '*', MUST_EXIST);
        $this->assertSame('manual-finance-user', $stored->idnumber);
    }
}
