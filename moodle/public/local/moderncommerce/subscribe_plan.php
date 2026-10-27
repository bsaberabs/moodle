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
 * Start checkout for a single subscription plan chosen on a storefront page.
 *
 * The public pricing widget links here with a plan code so a visitor who has
 * already picked a plan is not asked to pick it again on subscribe.php. This
 * creates the pending subscription order and forwards straight to checkout,
 * which mirrors how subscription purchases work in Stripe Checkout and Shopify:
 * a recurring commitment is its own flow rather than a cart line, because the
 * cart models "N items charged once" and a plan models a billing cycle.
 *
 * Guests are sent through login first and land back here, so the chosen plan
 * survives the round trip.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\api\order_api;
use local_moderncommerce\subscription\api\plan_api;
use local_moderncommerce\subscription\api\subscription_api;
use local_moderncommerce\subscription\services\subscription_service;

$plancode = optional_param('plan', '', PARAM_ALPHANUMEXT);
$planid = optional_param('planid', 0, PARAM_INT);
$starttrial = optional_param('trial', 0, PARAM_BOOL);

$context = context_system::instance();
$thisurl = new moodle_url('/local/moderncommerce/subscribe_plan.php', array_filter([
    'plan' => $plancode,
    'planid' => $planid ?: null,
    'trial' => $starttrial ?: null,
]));

$PAGE->set_context($context);
$PAGE->set_url($thisurl);

// A guest arriving from a public pricing table must sign in before an order can
// be raised against their account; they return to this exact URL afterwards.
require_login(null, false);

$chooseurl = new moodle_url('/local/moderncommerce/subscribe.php');

if (!has_capability('local/moderncommerce:subscribetoplan', $context)) {
    redirect(
        new moodle_url('/local/moderncommerce/index.php'),
        get_string('nopermissions', 'error', get_string('subscriptions', 'local_moderncommerce')),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Resolve the plan from either the human-readable code used in public links or
// a raw id, and only ever accept an active one.
$plan = null;
if ($plancode !== '') {
    $plan = plan_api::get_by_code($plancode);
}
if (!$plan && $planid > 0) {
    $plan = plan_api::get($planid, false);
}

if (!$plan || (string) $plan->status !== 'active') {
    redirect(
        $chooseurl,
        get_string('error:planinvalid', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$userid = (int) $USER->id;
$current = subscription_api::get_active_for_user($userid);

// Already on this plan: nothing to buy, send them to the management screen.
if ($current && (int) $current->planid === (int) $plan->id) {
    redirect(
        new moodle_url('/local/moderncommerce/learner/subscription.php'),
        get_string('error:alreadysubscribed', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

// A capped plan must not oversell; existing holders keep their seat.
if (!plan_api::has_available_seat($plan, $userid)) {
    redirect(
        $chooseurl,
        get_string('error:planfull', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$price = (float) plan_api::get_effective_price($plan);
$wanttrial = $starttrial && (int) $plan->trial_days > 0 && !$current;

// Free plans and trial starts need no payment, so they activate here and skip
// checkout entirely.
if ($wanttrial || $price <= 0) {
    subscription_service::activate($userid, (int) $plan->id, null, $wanttrial);
    redirect(
        new moodle_url('/local/moderncommerce/learner/subscription.php'),
        $wanttrial
            ? get_string('trialstarted', 'local_moderncommerce')
            : get_string('subscriptionactivated', 'local_moderncommerce'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Paid plan: raise the pending subscription order and hand off to the standard
// checkout, which already knows how to render and pay an order-mode basket.
$planname = format_string($plan->name);
$cycle = (string) $plan->billing_cycle === 'yearly'
    ? get_string('billingcycle_yearly', 'local_moderncommerce')
    : get_string('billingcycle_monthly', 'local_moderncommerce');

$order = order_api::create_subscription_order(
    $userid,
    (int) $plan->id,
    $price,
    $planname . ' (' . $cycle . ')',
    '',
    ['action' => 'new']
);

redirect(new moodle_url('/local/moderncommerce/checkout.php', ['orderid' => (int) $order->id]));
