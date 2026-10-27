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
 * Message providers for Modern Commerce notifications.
 *
 * Policy: store notifications are forced (locked on, no opt-out, no preferences
 * UI) so transactional commerce mail always reaches the bell. Email is disallowed
 * here because the subsystem owns email delivery via its own queue; this provider
 * only governs the in-app (popup) channel. Marketing opt-out is handled separately
 * by the suppression list, not by Moodle notification preferences.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // All learner/customer-facing store notifications.
    'commerce' => [
        'defaults' => [
            'popup' => MESSAGE_FORCED,
            'email' => MESSAGE_DISALLOWED,
        ],
    ],

    // Operational alerts for store admins (capability-gated).
    'adminops' => [
        'capability' => 'local/moderncommerce:receivenotificationops',
        'defaults' => [
            'popup' => MESSAGE_FORCED,
            'email' => MESSAGE_DISALLOWED,
        ],
    ],
];
