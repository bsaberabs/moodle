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
 * Seeds, audits, or resets Modern Commerce demo data.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_moderncommerce\demo\data_manager;
use local_moderncommerce\demo\role_account_manager;

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'seed' => false,
        'install-defaults' => false,
        'seed-role-users' => false,
        'remove-role-users' => false,
        'reset-empty' => false,
        'refresh' => false,
        'audit' => false,
        'yes' => false,
        'userid' => 0,
        'categories' => 12,
        'courses' => 25,
        'products' => 0,
        'orders' => 120,
        'coupons' => 12,
        'keys' => 24,
        'reviews' => 4,
    ],
    [
        'h' => 'help',
        'y' => 'yes',
    ]
);

if (!empty($unrecognized)) {
    $unrecognized = implode(PHP_EOL . '  ', $unrecognized);
    cli_error("Unknown options:\n  {$unrecognized}");
}

if (!empty($options['help'])) {
    echo "Manage Modern Commerce demo data.\n\n";
    echo "Modes:\n";
    echo "  --seed              Seed the full demo set for a new installation.\n";
    echo "  --install-defaults  Seed only install defaults: role presets, gateways, emails, storefront widgets.\n";
    echo "  --seed-role-users   Create demo users for each Modern Commerce role and the Moodle Manager role.\n";
    echo "  --remove-role-users Delete only the marked Modern Commerce demo role users.\n";
    echo "  --reset-empty       Delete all Modern Commerce table data and seeded Moodle demo courses.\n";
    echo "  --refresh           Run --reset-empty, then --seed.\n";
    echo "  --audit             Print Modern Commerce table counts and empty tables.\n\n";
    echo "Safety:\n";
    echo "  --reset-empty, --refresh, and --remove-role-users require --yes.\n\n";
    echo "Seed options:\n";
    echo "  --userid=N      User ID for user-scoped demo rows. Default: first site admin.\n";
    echo "  --categories=N  Number of demo Moodle course categories. Default: 12.\n";
    echo "  --courses=N     Number of demo Moodle courses. Default: 25.\n";
    echo "  --products=N    Number of demo products. Default: 0 (one product per demo course).\n";
    echo "  --orders=N      Number of demo orders. Default: 120.\n";
    echo "  --coupons=N     Number of demo coupons. Default: 12.\n";
    echo "  --keys=N        Number of demo enrolment keys. Default: 24.\n";
    echo "  --reviews=N     Number of reviews per demo course. Default: 4.\n\n";
    echo "Examples:\n";
    echo "  php public/local/moderncommerce/cli/demo_data.php --seed\n";
    echo "  php public/local/moderncommerce/cli/demo_data.php --seed-role-users\n";
    echo "  php public/local/moderncommerce/cli/demo_data.php --remove-role-users --yes\n";
    echo "  php public/local/moderncommerce/cli/demo_data.php --refresh --yes\n";
    echo "  php public/local/moderncommerce/cli/demo_data.php --reset-empty --yes\n";
    echo "  php public/local/moderncommerce/cli/demo_data.php --audit\n";
    exit(0);
}

$modes = array_filter([
    'seed' => !empty($options['seed']),
    'install-defaults' => !empty($options['install-defaults']),
    'seed-role-users' => !empty($options['seed-role-users']),
    'remove-role-users' => !empty($options['remove-role-users']),
    'reset-empty' => !empty($options['reset-empty']),
    'refresh' => !empty($options['refresh']),
    'audit' => !empty($options['audit']),
]);

if (empty($modes)) {
    cli_error('Choose one mode. Use --help for examples.');
}

if (count($modes) > 1) {
    cli_error('Choose only one mode at a time.');
}

$mode = array_key_first($modes);

if (in_array($mode, ['reset-empty', 'refresh', 'remove-role-users'], true) && empty($options['yes'])) {
    cli_error('This mode deletes data. Re-run with --yes to confirm.');
}

if ($mode === 'audit') {
    local_moderncommerce_demo_data_cli_print_audit(data_manager::audit_table_counts());
    exit(0);
}

if ($mode === 'install-defaults') {
    $result = data_manager::seed_install_defaults();
    cli_heading('Modern Commerce install defaults seeded');
    local_moderncommerce_demo_data_cli_print_key_values($result);
    exit(0);
}

if ($mode === 'seed-role-users') {
    $result = role_account_manager::seed_accounts();
    cli_heading('Modern Commerce demo role users seeded');
    local_moderncommerce_demo_data_cli_print_role_accounts($result);
    exit(0);
}

if ($mode === 'remove-role-users') {
    $result = role_account_manager::remove_accounts();
    cli_heading('Modern Commerce demo role users removed');
    local_moderncommerce_demo_data_cli_print_role_account_removal($result);
    exit(0);
}

if ($mode === 'reset-empty') {
    $result = data_manager::reset_to_empty();
    cli_heading('Modern Commerce data reset to empty');
    echo 'Tables cleared: ' . count($result['tables']) . PHP_EOL;
    echo 'Rows deleted: ' . array_sum($result['tables']) . PHP_EOL;
    echo 'Demo Moodle courses deleted: ' . $result['moodle']['courses'] . PHP_EOL;
    echo 'Demo Moodle categories deleted: ' . $result['moodle']['categories'] . PHP_EOL;
    exit(0);
}

if ($mode === 'refresh') {
    $reset = data_manager::reset_to_empty();
    cli_heading('Modern Commerce data reset to empty');
    echo 'Tables cleared: ' . count($reset['tables']) . PHP_EOL;
    echo 'Rows deleted: ' . array_sum($reset['tables']) . PHP_EOL;
}

$result = data_manager::seed_full_demo([
    'userid' => (int) $options['userid'],
    'categories' => max(1, (int) $options['categories']),
    'courses' => max(1, (int) $options['courses']),
    'products' => max(0, (int) $options['products']),
    'orders' => max(1, (int) $options['orders']),
    'coupons' => max(1, (int) $options['coupons']),
    'keys' => max(1, (int) $options['keys']),
    'reviews' => max(0, (int) $options['reviews']),
]);

cli_heading('Modern Commerce full demo data seeded');
echo 'Install defaults:' . PHP_EOL;
local_moderncommerce_demo_data_cli_print_key_values($result['install']);
echo PHP_EOL . 'Catalog/order sample:' . PHP_EOL;
local_moderncommerce_demo_data_cli_print_key_values($result['sample']);
echo PHP_EOL . 'Subscription matrix:' . PHP_EOL;
local_moderncommerce_demo_data_cli_print_key_values($result['subscriptions']);
echo PHP_EOL . 'Supplemental lifecycle groups:' . PHP_EOL;
local_moderncommerce_demo_data_cli_print_key_values($result['supplemental']);
echo PHP_EOL . 'Demo role accounts:' . PHP_EOL;
local_moderncommerce_demo_data_cli_print_role_accounts($result['roleaccounts']);
echo PHP_EOL;
local_moderncommerce_demo_data_cli_print_audit($result['coverage']);

/**
 * Print key/value output.
 *
 * @param array $items Items.
 */
function local_moderncommerce_demo_data_cli_print_key_values(array $items): void {
    foreach ($items as $key => $value) {
        if (is_array($value)) {
            echo '  ' . $key . ': ' . json_encode($value) . PHP_EOL;
            continue;
        }
        echo '  ' . $key . ': ' . $value . PHP_EOL;
    }
}

/**
 * Print demo role account credentials.
 *
 * @param array $result Result from role_account_manager::seed_accounts().
 */
function local_moderncommerce_demo_data_cli_print_role_accounts(array $result): void {
    echo '  Created: ' . (int) ($result['created'] ?? 0) . PHP_EOL;
    echo '  Updated: ' . (int) ($result['updated'] ?? 0) . PHP_EOL;
    echo '  Assigned: ' . (int) ($result['assigned'] ?? 0) . PHP_EOL;
    echo '  Skipped: ' . (int) ($result['skipped'] ?? 0) . PHP_EOL;
    echo '  Password: ' . (string) ($result['password'] ?? role_account_manager::PASSWORD) . PHP_EOL;

    if (empty($result['accounts'])) {
        return;
    }

    echo PHP_EOL;
    foreach ($result['accounts'] as $account) {
        echo '  ' . $account['username'] . ' / ' . $account['password']
            . '  [' . $account['role'] . ']'
            . ' - ' . $account['status'] . PHP_EOL;
    }
}

/**
 * Print demo role account removal summary.
 *
 * @param array $result Result from role_account_manager::remove_accounts().
 */
function local_moderncommerce_demo_data_cli_print_role_account_removal(array $result): void {
    echo '  Deleted: ' . (int) ($result['deleted'] ?? 0) . PHP_EOL;
    echo '  Role assignments removed: ' . (int) ($result['unassigned'] ?? 0) . PHP_EOL;
    echo '  Missing: ' . (int) ($result['missing'] ?? 0) . PHP_EOL;
    echo '  Skipped: ' . (int) ($result['skipped'] ?? 0) . PHP_EOL;

    if (empty($result['accounts'])) {
        return;
    }

    echo PHP_EOL;
    foreach ($result['accounts'] as $account) {
        echo '  ' . $account['username'] . ' [' . $account['role'] . '] - ' . $account['status'] . PHP_EOL;
    }
}

/**
 * Print table audit output.
 *
 * @param array $audit Audit summary.
 */
function local_moderncommerce_demo_data_cli_print_audit(array $audit): void {
    cli_heading('Modern Commerce table coverage audit');
    echo 'Tables checked: ' . $audit['total'] . PHP_EOL;
    echo 'Empty tables: ' . count($audit['empty']) . PHP_EOL;
    foreach ($audit['tables'] as $table => $count) {
        echo str_pad((string) $count, 8, ' ', STR_PAD_LEFT) . '  ' . $table . PHP_EOL;
    }

    if (!empty($audit['empty'])) {
        echo PHP_EOL . 'Empty:' . PHP_EOL;
        foreach ($audit['empty'] as $table) {
            echo '  - ' . $table . PHP_EOL;
        }
    }
}
