<?php
// This file is part of Moodle - https://moodle.org/
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
 * Event observers.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Raised in the buyer's own request: the only moment their referral cookie can be read.
    [
        'eventname' => '\local_moderncommerce\event\order_created',
        'callback' => '\local_oriskill\observer::order_created',
    ],
    // Usually raised by the payment notification (server to server): commissions are computed from the referral.
    [
        'eventname' => '\local_moderncommerce\event\order_paid',
        'callback' => '\local_oriskill\observer::order_paid',
    ],
    // Payment in instalments (SecureAndPay charges them and reports each one).
    [
        'eventname' => '\local_secureandpay\event\instalment_paid',
        'callback' => '\local_oriskill\instalment_service::instalment_paid',
    ],
    [
        'eventname' => '\local_secureandpay\event\instalment_failed',
        'callback' => '\local_oriskill\instalment_service::instalment_failed',
    ],
    [
        'eventname' => '\local_secureandpay\event\instalment_failed',
        'callback' => '\local_oriskill\activecampaign_service::instalment_failed',
    ],
    [
        'eventname' => '\local_secureandpay\event\instalments_stopped',
        'callback' => '\local_oriskill\instalment_service::instalments_stopped',
    ],
    // A refund confirmed by SecureAndPay: credit note in the trainer's name.
    [
        'eventname' => '\local_secureandpay\event\payment_refunded',
        'callback' => '\local_oriskill\billing_service::payment_refunded',
    ],
    // CRM of the trainers: every learner of their courses is a contact.
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback' => '\local_oriskill\observer::user_enrolled',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\local_oriskill\observer::user_deleted',
    ],
    // Webhooks of the trainers.
    [
        'eventname' => '\local_secureandpay\event\instalment_paid',
        'callback' => '\local_oriskill\webhook_service::secureandpay_event',
    ],
    [
        'eventname' => '\local_secureandpay\event\instalment_failed',
        'callback' => '\local_oriskill\webhook_service::secureandpay_event',
    ],
    [
        'eventname' => '\local_secureandpay\event\instalments_stopped',
        'callback' => '\local_oriskill\webhook_service::secureandpay_event',
    ],
    [
        'eventname' => '\local_secureandpay\event\payment_refunded',
        'callback' => '\local_oriskill\webhook_service::secureandpay_event',
    ],
];
