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
 * Starts a plan's free trial through SecureAndPay: the card is registered for 0 and
 * SecureAndPay charges the plan price once the trial is over.
 *
 * Modern Commerce's "Start free trial" button is redirected here (see hook_callbacks).
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\api\order_api;
use local_moderncommerce\subscription\api\plan_api;
use local_moderncommerce\subscription\api\subscription_api;
use local_secureandpay\gateway;

$plancode = optional_param('plan', '', PARAM_ALPHANUMEXT);
$planid = optional_param('planid', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/secureandpay/trial.php', array_filter(['plan' => $plancode, 'planid' => $planid])));

require_login(null, false);
require_capability('local/moderncommerce:subscribetoplan', $context);

$chooseurl = new moodle_url('/local/moderncommerce/subscribe.php');
$plan = $plancode !== '' ? plan_api::get_by_code($plancode) : null;
if (!$plan && $planid > 0) {
    $plan = plan_api::get($planid, false);
}
if (!$plan || (string) $plan->status !== 'active' || (int) $plan->trial_days <= 0) {
    redirect($chooseurl, get_string('error:planinvalid', 'local_moderncommerce'), null,
        \core\output\notification::NOTIFY_ERROR);
}
if (subscription_api::get_active_for_user((int) $USER->id)) {
    redirect(new moodle_url('/local/moderncommerce/learner/subscription.php'),
        get_string('error:alreadysubscribed', 'local_moderncommerce'), null, \core\output\notification::NOTIFY_INFO);
}
if (!plan_api::has_available_seat($plan, (int) $USER->id)) {
    redirect($chooseurl, get_string('error:planfull', 'local_moderncommerce'), null,
        \core\output\notification::NOTIFY_ERROR);
}

$order = order_api::create_subscription_order((int) $USER->id, (int) $plan->id, 0.0,
    get_string('trialorder', 'local_secureandpay', format_string($plan->name)), '', ['action' => 'new', 'trial' => 1]);
$reference = (new gateway())->start_trial($order);

redirect(new moodle_url('/local/secureandpay/pay.php', ['orderid' => $order->id, 'ref' => $reference]));
