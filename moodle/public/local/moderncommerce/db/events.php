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
 * Event observers
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\local_moderncommerce\event\order_paid',
        'callback' => '\local_moderncommerce\observer\enrollment_observer::order_paid',
        'internal' => true,
        'priority' => 100,
    ],
    [
        'eventname' => '\local_moderncommerce\event\order_status_changed',
        'callback' => '\local_moderncommerce\observer\order_observer::status_changed',
        'internal' => true,
    ],

    // Subscription subsystem absorbed from local_modernsubscription.
    // Activate/renew subscriptions when a Modern Commerce order is paid.
    [
        'eventname' => '\local_moderncommerce\event\order_paid',
        'callback' => '\local_moderncommerce\subscription\observer\payment_observer::order_paid',
        'internal' => true,
        'priority' => 100,
    ],
    // Track course completion for subscription progress.
    [
        'eventname' => '\core\event\course_completed',
        'callback' => '\local_moderncommerce\subscription\observer\course_observer::course_completed',
        'internal' => true,
        'priority' => 0,
    ],
    [
        'eventname' => '\core\event\course_completed',
        'callback' => '\local_moderncommerce\observer\program_observer::course_completed',
        'internal' => true,
        'priority' => 100,
    ],
    [
        'eventname' => '\local_moderncommerce\event\program_completed',
        'callback' => '\local_moderncommerce\observer\program_completion_observer::program_completed',
        'internal' => true,
        'priority' => 100,
    ],
    // GDPR cleanup of Modern Commerce data when a user is deleted.
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\local_moderncommerce\observer\user_observer::user_deleted',
        'internal' => true,
        'priority' => 0,
    ],
];
