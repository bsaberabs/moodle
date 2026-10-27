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

namespace local_moderncommerce\demo;

use context_system;
use local_moderncommerce\services\role_preset_service;

/**
 * Creates and removes demo users for Modern Commerce role testing.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_account_manager {
    /** @var string Stable demo password for all role-preview accounts. */
    public const PASSWORD = 'ModernCommerceDemo#2026!';

    /** @var string ID number prefix used to prove accounts are owned by the demo seeder. */
    private const IDNUMBER_PREFIX = 'MC-DEMO-ROLE-';

    /**
     * Demo account definitions.
     *
     * @return array<string,array{username:string,role:string,label:string,firstname:string,lastname:string}>
     */
    public static function accounts(): array {
        return [
            'manager' => [
                'username' => 'mcdemo_manager',
                'role' => 'manager',
                'label' => 'Moodle Manager',
                'firstname' => 'Demo',
                'lastname' => 'Moodle Manager',
            ],
            'admin' => [
                'username' => 'mcdemo_commerceadmin',
                'role' => 'moderncommerceadmin',
                'label' => 'Modern Commerce Administrator',
                'firstname' => 'Demo',
                'lastname' => 'Commerce Administrator',
            ],
            'finance' => [
                'username' => 'mcdemo_finance',
                'role' => 'moderncommercefinance',
                'label' => 'Modern Commerce Finance',
                'firstname' => 'Demo',
                'lastname' => 'Finance Manager',
            ],
            'product' => [
                'username' => 'mcdemo_product',
                'role' => 'moderncommerceproduct',
                'label' => 'Modern Commerce Product Manager',
                'firstname' => 'Demo',
                'lastname' => 'Product Manager',
            ],
            'reporting' => [
                'username' => 'mcdemo_reporting',
                'role' => 'moderncommercereporting',
                'label' => 'Modern Commerce Reporting Manager',
                'firstname' => 'Demo',
                'lastname' => 'Reporting Manager',
            ],
            'storefront' => [
                'username' => 'mcdemo_storefront',
                'role' => 'moderncommercestorefront',
                'label' => 'Modern Commerce Storefront Manager',
                'firstname' => 'Demo',
                'lastname' => 'Storefront Manager',
            ],
            'marketing' => [
                'username' => 'mcdemo_marketing',
                'role' => 'moderncommercemarketing',
                'label' => 'Modern Commerce Marketing Manager',
                'firstname' => 'Demo',
                'lastname' => 'Marketing Manager',
            ],
            'support' => [
                'username' => 'mcdemo_support',
                'role' => 'moderncommercesupport',
                'label' => 'Modern Commerce Support',
                'firstname' => 'Demo',
                'lastname' => 'Support Agent',
            ],
            'subscription' => [
                'username' => 'mcdemo_subscription',
                'role' => 'moderncommercesubscription',
                'label' => 'Modern Commerce Subscription Manager',
                'firstname' => 'Demo',
                'lastname' => 'Subscription Manager',
            ],
            'paymentops' => [
                'username' => 'mcdemo_paymentops',
                'role' => 'moderncommercepaymentops',
                'label' => 'Modern Commerce Payment Operations',
                'firstname' => 'Demo',
                'lastname' => 'Payment Operations',
            ],
        ];
    }

    /**
     * Create/update demo users and assign their system role.
     *
     * @return array Seed summary.
     */
    public static function seed_accounts(): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->libdir . '/accesslib.php');

        role_preset_service::seed_presets();

        $context = context_system::instance();
        $summary = self::empty_summary();
        foreach (self::accounts() as $key => $account) {
            $role = $DB->get_record('role', ['shortname' => $account['role']], 'id, shortname, name', IGNORE_MISSING);
            if (!$role) {
                $summary['skipped']++;
                $summary['accounts'][] = self::account_result($key, $account, 'skipped_missing_role', 0);
                continue;
            }

            $user = self::find_existing_user($key, $account);
            if ($user && !self::is_managed_user($key, $user)) {
                $summary['skipped']++;
                $summary['accounts'][] = self::account_result($key, $account, 'skipped_username_collision', (int) $user->id);
                continue;
            }

            if ($user) {
                self::update_user($key, $account, $user);
                $status = 'updated';
                $summary['updated']++;
            } else {
                $userid = self::create_user($key, $account);
                $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
                $status = 'created';
                $summary['created']++;
            }

            role_assign((int) $role->id, (int) $user->id, $context->id);
            $summary['assigned']++;
            $summary['accounts'][] = self::account_result($key, $account, $status, (int) $user->id);
        }

        return $summary;
    }

    /**
     * Remove all marked Modern Commerce role demo accounts.
     *
     * @return array Removal summary.
     */
    public static function remove_accounts(): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/accesslib.php');
        require_once($CFG->dirroot . '/user/lib.php');

        $summary = self::empty_summary();
        foreach (self::accounts() as $key => $account) {
            $user = self::find_existing_user($key, $account);
            if (!$user) {
                $summary['missing']++;
                $summary['accounts'][] = self::account_result($key, $account, 'missing', 0);
                continue;
            }

            if (!self::is_managed_user($key, $user)) {
                $summary['skipped']++;
                $summary['accounts'][] = self::account_result($key, $account, 'skipped_unmarked_user', (int) $user->id);
                continue;
            }

            $assignments = $DB->count_records('role_assignments', ['userid' => $user->id]);
            $deleted = delete_user($user);
            if ($deleted) {
                $summary['deleted']++;
                $summary['unassigned'] += (int) $assignments;
                $summary['accounts'][] = self::account_result($key, $account, 'deleted', (int) $user->id);
            } else {
                $summary['skipped']++;
                $summary['accounts'][] = self::account_result($key, $account, 'delete_failed', (int) $user->id);
            }
        }

        return $summary;
    }

    /**
     * Return documentation-ready credentials.
     *
     * @return array<int,array{role:string,username:string,password:string}>
     */
    public static function credentials(): array {
        $credentials = [];
        foreach (self::accounts() as $account) {
            $credentials[] = [
                'role' => $account['label'],
                'username' => $account['username'],
                'password' => self::PASSWORD,
            ];
        }

        return $credentials;
    }

    /**
     * Create a demo user.
     *
     * @param string $key Account key.
     * @param array $account Account definition.
     * @return int User ID.
     */
    private static function create_user(string $key, array $account): int {
        return user_create_user(self::user_record($key, $account), true, false);
    }

    /**
     * Update an existing managed demo user and reset its documented password.
     *
     * @param string $key Account key.
     * @param array $account Account definition.
     * @param object $user Existing user.
     */
    private static function update_user(string $key, array $account, object $user): void {
        $record = self::user_record($key, $account);
        $record->id = (int) $user->id;
        user_update_user($record, true, false);
    }

    /**
     * Build a Moodle user record.
     *
     * @param string $key Account key.
     * @param array $account Account definition.
     * @return \stdClass User record.
     */
    private static function user_record(string $key, array $account): \stdClass {
        global $CFG;

        return (object) [
            'auth' => 'manual',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $account['username'],
            'password' => self::PASSWORD,
            'firstname' => $account['firstname'],
            'lastname' => $account['lastname'],
            'email' => self::email_for($account['username']),
            'idnumber' => self::idnumber($key),
            'city' => 'Demo City',
            'country' => 'US',
            'description' => 'Modern Commerce demo role account. Remove with: '
                . 'php public/local/moderncommerce/cli/demo_data.php --remove-role-users --yes',
        ];
    }

    /**
     * Find an existing user by expected username or managed idnumber.
     *
     * @param string $key Account key.
     * @param array $account Account definition.
     * @return object|null User record.
     */
    private static function find_existing_user(string $key, array $account): ?object {
        global $DB;

        $user = $DB->get_record('user', ['username' => $account['username'], 'deleted' => 0], '*', IGNORE_MISSING);
        if ($user) {
            return $user;
        }

        $user = $DB->get_record('user', ['idnumber' => self::idnumber($key), 'deleted' => 0], '*', IGNORE_MISSING);
        return $user ?: null;
    }

    /**
     * Check whether a user is managed by this demo seeder.
     *
     * @param string $key Account key.
     * @param object $user User record.
     * @return bool
     */
    private static function is_managed_user(string $key, object $user): bool {
        return (string) ($user->idnumber ?? '') === self::idnumber($key);
    }

    /**
     * Build an account idnumber.
     *
     * @param string $key Account key.
     * @return string ID number.
     */
    private static function idnumber(string $key): string {
        return self::IDNUMBER_PREFIX . $key;
    }

    /**
     * Build an account email address.
     *
     * @param string $username Username.
     * @return string Email address.
     */
    private static function email_for(string $username): string {
        return $username . '@example.com';
    }

    /**
     * Empty summary structure.
     *
     * @return array Summary.
     */
    private static function empty_summary(): array {
        return [
            'created' => 0,
            'updated' => 0,
            'assigned' => 0,
            'deleted' => 0,
            'unassigned' => 0,
            'missing' => 0,
            'skipped' => 0,
            'password' => self::PASSWORD,
            'accounts' => [],
        ];
    }

    /**
     * Build an account result row.
     *
     * @param string $key Account key.
     * @param array $account Account definition.
     * @param string $status Status.
     * @param int $userid User ID.
     * @return array Result row.
     */
    private static function account_result(string $key, array $account, string $status, int $userid): array {
        return [
            'key' => $key,
            'role' => $account['label'],
            'roleshortname' => $account['role'],
            'username' => $account['username'],
            'password' => self::PASSWORD,
            'userid' => $userid,
            'status' => $status,
        ];
    }
}
