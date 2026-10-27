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
 * CLI script to send test Modern Commerce emails
 *
 * This sends one test copy of each of the five transactional emails
 * to a chosen user (default: site admin), using the current plugin
 * configuration and CCP email templates. Useful for verifying
 * global placeholders like {sitename}, {siteurl}, {supportemail},
 * {logo}, {user*}, etc.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Parse CLI options.
[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'userid' => 0,
], [
    'h' => 'help',
    'u' => 'userid',
]);

if (!empty($options['help'])) {
    $help = "Send test Modern Commerce emails to a user.\n\n" .
        "Options:\n" .
        "  -h, --help         Show this help\n" .
        "  -u, --userid=ID    User ID to send emails to (defaults to site admin)\n";
    cli_writeln($help);
    exit(0);
}

// Resolve target user.
if (!empty($options['userid'])) {
    $user = $DB->get_record('user', ['id' => $options['userid']], '*', MUST_EXIST);
} else {
    $user = get_admin();
}

// Impersonate user for the duration of this script so that
// global placeholders (userfirstname, useremail, etc.) are populated.
\core\session\manager::set_user($user);

// Select a course for course-related emails.
$course = $DB->get_record_select('course', 'id <> ?', [SITEID], '*', IGNORE_MULTIPLE);
if (!$course) {
    $course = get_site();
}

// Build a fake order and related records.
$now = time();
$currency = get_config('local_moderncommerce', 'primary_currency');

$order = (object) [
    'id' => 0,
    'userid' => $user->id,
    'ordernumber' => 'TEST-' . $now,
    'timecreated' => $now,
    'status' => 'completed',
    'subtotal' => 10000,
    'discount' => 1000,
    'tax' => 500,
    'total' => 9500,
    'currency' => $currency,
    'couponcode' => 'TESTCOUPON',
];

$items = [
    (object) [
        'coursename' => $course->fullname,
        'total' => 9500,
    ],
];

$transaction = (object) [
    'transactionid' => 'TX-' . $now,
    'gateway' => 'stripe',
    'amount' => 9500,
    'fee' => 0,
    'netamount' => 9500,
    'timecreated' => $now,
    'reference' => 'REF-' . $now,
];

$refund = (object) [
    'amount' => 3000,
    'reason' => 'Test refund',
    'timecreated' => $now,
    'reference' => 'RF-' . $now,
];

cli_writeln('Sending Modern Commerce test emails to: ' . $user->email);

$results = [];

// 1. Order Confirmation
try {
    $ok = \local_moderncommerce\email_notifications::send_order_confirmation($order, $items);
    $results[] = 'Order confirmation: ' . ($ok ? 'sent' : 'not sent (returned false)');
} catch (\Throwable $e) {
    $results[] = 'Order confirmation: ERROR - ' . $e->getMessage();
}

// 2. Payment Receipt
try {
    $ok = \local_moderncommerce\email_notifications::send_payment_receipt($order, $transaction, $items);
    $results[] = 'Payment receipt: ' . ($ok ? 'sent' : 'not sent (returned false)');
} catch (\Throwable $e) {
    $results[] = 'Payment receipt: ERROR - ' . $e->getMessage();
}

// 3. Enrollment Confirmation
try {
    $ok = \local_moderncommerce\email_notifications::send_enrollment_confirmation($user, $course, $order->ordernumber);
    $results[] = 'Enrollment confirmation: ' . ($ok ? 'sent' : 'not sent (returned false)');
} catch (\Throwable $e) {
    $results[] = 'Enrollment confirmation: ERROR - ' . $e->getMessage();
}

// 4. Key Redemption
try {
    $ok = \local_moderncommerce\email_notifications::send_key_redemption($user, $course, 'TESTKEY-1234');
    $results[] = 'Key redemption: ' . ($ok ? 'sent' : 'not sent (returned false)');
} catch (\Throwable $e) {
    $results[] = 'Key redemption: ERROR - ' . $e->getMessage();
}

// 5. Refund Confirmation
try {
    $ok = \local_moderncommerce\email_notifications::send_refund_confirmation($order, $refund);
    $results[] = 'Refund confirmation: ' . ($ok ? 'sent' : 'not sent (returned false)');
} catch (\Throwable $e) {
    $results[] = 'Refund confirmation: ERROR - ' . $e->getMessage();
}

cli_writeln('Results:');
foreach ($results as $line) {
    cli_writeln(' - ' . $line);
}

cli_writeln(
    'Done. Check the mailbox for ' . $user->email .
        ' to verify global placeholders (e.g. {sitename}, {siteurl}, {supportemail}, {logo}, {user*}).'
);
