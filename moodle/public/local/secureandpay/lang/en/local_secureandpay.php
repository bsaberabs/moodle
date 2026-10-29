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
 * English strings.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cancelinsecureandpay_body'] = 'The subscription of {$a->user} ({$a->email}) was cancelled on Oriskill, but SecureAndPay did not confirm that billing stopped (answer: {$a->answer}). Stop the subscription with orderID {$a->reference} in the SecureAndPay back office.';
$string['cancelinsecureandpay_subject'] = 'Action needed: stop SecureAndPay subscription {$a->reference}';
$string['cancelledatsecureandpay'] = 'Subscription stopped at SecureAndPay; access remains until the end of the paid period.';
$string['cardwillbesaved'] = 'Your card will be kept securely by SecureAndPay (Oriskill never sees it), for the next instalments or your next purchases.';
$string['continuetopayment'] = 'Continue to payment';
$string['invalidamount'] = 'The order amount is not valid for SecureAndPay.';
$string['invalidnotification'] = 'Invalid SecureAndPay notification.';
$string['invalidsignature'] = 'Invalid SecureAndPay signature.';
$string['lastpaymentreceived'] = 'Last of the {$a} payments received: the subscription ends with this period.';
$string['notconfigured'] = 'SecureAndPay is not configured: enter the site ID and the key in the payment gateway settings.';
$string['oneclick'] = 'One-click payment (upsell)';
$string['oneclick_desc'] = 'The first purchase registers the buyer\'s card at SecureAndPay; later purchases reuse it, so the buyer only confirms the payment. The buyer is told before paying that the card will be kept.';
$string['paymentrefused'] = 'This order cannot be paid: {$a}';
$string['planpayments'] = 'Number of payments per plan';
$string['planpayments_cycle_monthly'] = 'month';
$string['planpayments_cycle_yearly'] = 'year';
$string['planpayments_fixed'] = '{$a->payments} payments of {$a->price}, one per {$a->cycle}, then access stops.';
$string['planpayments_intro'] = 'For each subscription plan paid with SecureAndPay, enter the number of payments. Left empty, the subscription is billed until it is cancelled. After the last payment, access stops at the end of the paid period. The change applies to new subscriptions: schedules already running at SecureAndPay do not change.';
$string['planpayments_invalid'] = 'Invalid number of payments (a whole number from 1 to 999, or empty) for: {$a}';
$string['planpayments_none'] = 'No subscription plan yet.';
$string['planpayments_number'] = 'Number of payments';
$string['planpayments_plan'] = 'Plan';
$string['planpayments_price'] = 'Price';
$string['planpayments_summary'] = 'What the customer pays';
$string['planpayments_trial'] = 'Free trial';
$string['planpayments_unlimited'] = '{$a->price} per {$a->cycle}, until cancelled.';
$string['planpayments_unlimited_short'] = 'Unlimited';
$string['planpayments_withtrial'] = '{$a->trial} free days, then';
$string['pluginname'] = 'SecureAndPay';
$string['privacy:metadata:preference:card'] = 'Reference (token) of the card kept by SecureAndPay for one-click payments, and its expiry date.';
$string['privacy:metadata:secureandpay'] = 'To take the payment, buyer and order details are sent to SecureAndPay.';
$string['privacy:metadata:secureandpay:address'] = 'Postal address.';
$string['privacy:metadata:secureandpay:email'] = 'Email address.';
$string['privacy:metadata:secureandpay:firstname'] = 'First name.';
$string['privacy:metadata:secureandpay:lastname'] = 'Last name.';
$string['privacy:metadata:secureandpay:order'] = 'Order reference, amount and purchased items.';
$string['privacy:metadata:secureandpay:phone'] = 'Phone number.';
$string['redirecting'] = 'You are being redirected to the SecureAndPay secure payment page…';
$string['savedcardexpiry'] = 'Card saved at SecureAndPay, expiring {$a} (YYMM)';
$string['schedulenotstopped'] = 'SecureAndPay did not confirm that schedule {$a->reference} stopped (answer: {$a->answer}): the administrators were notified.';
$string['schedulestopped'] = 'Schedule {$a->reference} stopped at SecureAndPay.';
$string['trialorder'] = '{$a} — free trial';
$string['trialwithcard'] = 'Free trials with a registered card';
$string['trialwithcard_desc'] = 'A plan\'s free trial starts by registering the card at SecureAndPay for 0; SecureAndPay charges the plan price when the trial ends. When off, trials start without any payment method and end without being converted.';
$string['unsupportedcurrency'] = 'SecureAndPay does not accept the currency {$a}. Use EUR, USD or GBP.';
