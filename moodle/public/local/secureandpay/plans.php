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
 * Number of payments of each subscription plan paid with SecureAndPay (empty: billed until cancelled), and the
 * amount of its trial (0: free trial; a token amount such as 1).
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_secureandpay\gateway;

admin_externalpage_setup('local_secureandpay_plans');
$url = new moodle_url('/local/secureandpay/plans.php');
$plans = $DB->get_records('local_moderncommerce_subscription_plans', null, 'sortorder, name');

if (data_submitted() && confirm_sesskey()) {
    $values = optional_param_array('payments', [], PARAM_RAW_TRIMMED);
    $trialprices = optional_param_array('trialprice', [], PARAM_RAW_TRIMMED);
    $errors = [];
    foreach ($plans as $plan) {
        $trialprice = trim((string) ($trialprices[$plan->id] ?? ''));
        $trialamount = $trialprice === '' ? 0.0 : unformat_float($trialprice, true);
        if ($trialamount === false || $trialamount < 0 || $trialamount > (float) $plan->price) {
            $errors[] = format_string($plan->name);
            continue;
        }
        if ($trialamount > 0) {
            set_config('trialprice_' . $plan->id, round($trialamount, 2), 'local_secureandpay');
        } else {
            unset_config('trialprice_' . $plan->id, 'local_secureandpay');
        }
        $value = $values[$plan->id] ?? '';
        if ($value !== '' && (!ctype_digit($value) || (int) $value < 1 || (int) $value > 999)) {
            $errors[] = format_string($plan->name);
            continue;
        }
        if ($value === '' || (int) $value === 0) {
            unset_config('payments_' . $plan->id, 'local_secureandpay');
        } else {
            set_config('payments_' . $plan->id, (int) $value, 'local_secureandpay');
        }
    }
    redirect($url, $errors ? get_string('planpayments_invalid', 'local_secureandpay', implode(', ', $errors))
        : get_string('changessaved'), null,
        $errors ? \core\output\notification::NOTIFY_ERROR : \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('planpayments', 'local_secureandpay'));
echo html_writer::tag('p', get_string('planpayments_intro', 'local_secureandpay'));
if (!$plans) {
    echo $OUTPUT->notification(get_string('planpayments_none', 'local_secureandpay'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('planpayments_plan', 'local_secureandpay'), get_string('planpayments_price', 'local_secureandpay'),
    get_string('planpayments_trial', 'local_secureandpay'), get_string('planpayments_trialprice', 'local_secureandpay'),
    get_string('planpayments_number', 'local_secureandpay'),
    get_string('planpayments_summary', 'local_secureandpay')];
foreach ($plans as $plan) {
    $payments = gateway::plan_payments((int) $plan->id);
    $cycle = get_string('planpayments_cycle_' . ($plan->billing_cycle === 'yearly' ? 'yearly' : 'monthly'),
        'local_secureandpay');
    $trialprice = gateway::plan_trial_price((int) $plan->id);
    $a = (object) ['payments' => $payments, 'price' => format_float((float) $plan->price, 2) . ' ' . s($plan->currency),
        'cycle' => $cycle, 'trial' => (int) $plan->trial_days,
        'trialprice' => format_float($trialprice, 2) . ' ' . s($plan->currency)];
    $summary = $payments ? get_string('planpayments_fixed', 'local_secureandpay', $a)
        : get_string('planpayments_unlimited', 'local_secureandpay', $a);
    if ($plan->trial_days) {
        $summary = get_string($trialprice > 0 ? 'planpayments_withpaidtrial' : 'planpayments_withtrial',
            'local_secureandpay', $a) . ' ' . $summary;
    }
    $table->data[] = [
        format_string($plan->name) . html_writer::div(s($plan->code), 'small text-muted'),
        $a->price . ' / ' . $cycle,
        $plan->trial_days ? get_string('numdays', '', (int) $plan->trial_days) : '–',
        $plan->trial_days ? html_writer::empty_tag('input', ['type' => 'text', 'inputmode' => 'decimal',
            'name' => "trialprice[{$plan->id}]", 'value' => $trialprice > 0 ? format_float($trialprice, 2, true) : '',
            'class' => 'form-control', 'style' => 'max-width: 7rem', 'placeholder' => '0',
            'aria-label' => get_string('planpayments_trialprice', 'local_secureandpay')]) : '–',
        html_writer::empty_tag('input', ['type' => 'number', 'min' => 1, 'max' => 999, 'step' => 1,
            'name' => "payments[{$plan->id}]", 'value' => $payments ?: '', 'class' => 'form-control',
            'style' => 'max-width: 7rem', 'placeholder' => get_string('planpayments_unlimited_short', 'local_secureandpay'),
            'aria-label' => get_string('planpayments_number', 'local_secureandpay')]),
        $summary,
    ];
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::table($table);
echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
