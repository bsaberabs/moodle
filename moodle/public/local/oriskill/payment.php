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
 * Trainer's payment accounts (SecureAndPay, Stripe, PayPal): their course sales are paid into them.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\payment\gateway_manager;
use local_oriskill\creator_service;
use local_oriskill\form\gateway_form;
use local_oriskill\form\payment_form;
use local_oriskill\seller_service;

$url = new moodle_url('/local/oriskill/payment.php');
$creator = creator_service::require_current_creator($url, 'paymentsettings');

$sapform = new payment_form($url, ['haskey' => !empty($creator->sap_key)], 'post', '', ['id' => 'gw-secureandpay']);
$sapform->set_data(['siteid' => $creator->sap_siteid, 'testmode' => $creator->sap_testmode]);
if ($data = $sapform->get_data()) {
    seller_service::save_payment_settings($creator, $data->siteid, (string) $data->key, !empty($data->testmode));
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$forms = [];
$submitted = optional_param('gateway', '', PARAM_ALPHA);
foreach (seller_service::GATEWAYS as $gateway) {
    $account = seller_service::gateway_account($creator, $gateway);
    $form = new gateway_form(new moodle_url($url, ['gw' => $gateway]), ['gateway' => $gateway, 'account' => $account],
        'post', '', ['id' => 'gw-' . $gateway]);
    $form->set_data(['enabled' => $account->enabled ?? 0, 'testmode' => $account->testmode ?? 1,
        'publickey' => $account->publickey ?? '']);
    // Both forms share one class: only the one that was sent is read.
    if ($submitted === $gateway && ($data = $form->get_data())) {
        seller_service::save_gateway_account($creator, $gateway, !empty($data->enabled), !empty($data->testmode),
            (string) $data->publickey, (string) $data->secretkey, (string) $data->webhooksecret);
        redirect(new moodle_url($url, [], 'card-' . $gateway), get_string('changessaved'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
    $forms[$gateway] = [$form, $account];
}

/**
 * Header of a payment method card: name, state badge and short description.
 *
 * @param string $name Method name.
 * @param string $state on, off or incomplete.
 * @param string $description Short description.
 * @return string HTML.
 */
function local_oriskill_gateway_head(string $name, string $state, string $description): string {
    $class = ['on' => 'is-good', 'incomplete' => 'is-warn', 'off' => ''][$state];
    return html_writer::div(html_writer::tag('h3', s($name), ['class' => 'mb-0']) .
        html_writer::span(get_string('gwstate:' . $state, 'local_oriskill'), trim('oriskill-badge ' . $class)),
        'd-flex justify-content-between align-items-center gap-2 mb-2') .
        html_writer::tag('p', $description, ['class' => 'text-muted small']);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/payment.php', get_string('paymentsettings', 'local_oriskill'));
echo html_writer::tag('p', get_string('paymentsettings_intro', 'local_oriskill'));

// SecureAndPay.
ob_start();
$sapform->display();
echo html_writer::div(
    local_oriskill_gateway_head('SecureAndPay', seller_service::merchant($creator) ? 'on' : 'off',
        get_string('gw:secureandpay:desc', 'local_oriskill')) .
    $OUTPUT->notification(get_string('paymentsettings_support', 'local_oriskill', (object) [
        'site' => (new moodle_url('/'))->out(false),
        'notify' => (new moodle_url('/local/secureandpay/notify.php'))->out(false),
    ]), 'info', false) .
    ob_get_clean(),
    'oriskill-card mb-4', ['id' => 'card-secureandpay']);

// Stripe and PayPal.
foreach ($forms as $gateway => [$form, $account]) {
    $state = !$account || !$account->enabled ? 'off' : (seller_service::gateway_account_complete($account) ? 'on' : 'incomplete');
    $webhook = gateway_manager::webhook_url($gateway, 'creator-' . $creator->id)->out(false);
    ob_start();
    $form->display();
    echo html_writer::div(
        local_oriskill_gateway_head($gateway === 'stripe' ? 'Stripe' : 'PayPal', $state,
            get_string("gw:$gateway:desc", 'local_oriskill')) .
        html_writer::tag('details',
            html_writer::tag('summary', get_string('gw:howto', 'local_oriskill'), ['class' => 'fw-semibold']) .
            html_writer::div(get_string("gw:$gateway:howto", 'local_oriskill', (object) ['webhook' => s($webhook)]),
                'small mt-2'),
            ['class' => 'mb-3', 'open' => $account ? null : 'open']) .
        ob_get_clean(),
        'oriskill-card mb-4', ['id' => 'card-' . $gateway]);
}

echo creator_service::space_end();
echo $OUTPUT->footer();
