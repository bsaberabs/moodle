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
 * Scheduled tasks
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_moderncommerce\task\cleanup_cart',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_moderncommerce\task\expire_keys',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '*/6',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_moderncommerce\task\cancel_abandoned_orders',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_moderncommerce\task\send_payment_reminders',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '9,15',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'local_moderncommerce\task\generate_sales_report',
        'blocking' => 0,
        'minute' => '5',
        'hour' => '1',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        // Invoice due/overdue reminders + admin sales digest, dispatched via the notification subsystem.
        'classname' => 'local_moderncommerce\task\notify_daily_scan',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '7',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        // Abandoned-cart recovery (1h/24h/72h marketing nudges) via the notification subsystem.
        'classname' => 'local_moderncommerce\task\abandoned_cart_recovery',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Notification subsystem: drain the delivery queue every minute (inline retry via backoff).
    [
        'classname' => 'local_moderncommerce\task\notify_send_queue',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Notification subsystem: return rows stuck in 'processing' (crashed worker) back to 'pending'.
    [
        'classname' => 'local_moderncommerce\task\notify_reap_stale',
        'blocking' => 0,
        'minute' => '*/10',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Notification subsystem: flush accumulated digests (daily 07:00).
    [
        'classname' => 'local_moderncommerce\task\notify_process_digests',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '7',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],

    // Subscription subsystem absorbed from local_modernsubscription.
    // Check for expiring subscriptions and send reminders.
    [
        'classname' => 'local_moderncommerce\subscription\task\check_expiring',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '8',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Process expired subscriptions (move to grace or suspend).
    [
        'classname' => 'local_moderncommerce\subscription\task\process_expired',
        'blocking' => 0,
        'minute' => '30',
        'hour' => '0',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Sync subscription access (ensure enrolments match active subscriptions).
    [
        'classname' => 'local_moderncommerce\subscription\task\sync_access',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '*/6',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Cleanup old expired/cancelled subscription data (monthly).
    [
        'classname' => 'local_moderncommerce\subscription\task\cleanup_old',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '3',
        'day' => '1',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Process pending plan changes (scheduled downgrades).
    [
        'classname' => 'local_moderncommerce\subscription\task\process_pending_changes',
        'blocking' => 0,
        'minute' => '15',
        'hour' => '0',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Process trial period expirations.
    [
        'classname' => 'local_moderncommerce\subscription\task\process_trials',
        'blocking' => 0,
        'minute' => '45',
        'hour' => '0',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    // Process recurring subscription payments.
    [
        'classname' => 'local_moderncommerce\subscription\task\process_recurring_payments',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '1',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
];
