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
 * Trainer's SecureAndPay account: their course sales are paid into it.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\form\payment_form;
use local_oriskill\seller_service;

$url = new moodle_url('/local/oriskill/payment.php');
$creator = creator_service::require_current_creator($url, 'paymentsettings');

$form = new payment_form($url, ['haskey' => !empty($creator->sap_key)]);
$form->set_data(['siteid' => $creator->sap_siteid, 'testmode' => $creator->sap_testmode]);
if ($data = $form->get_data()) {
    seller_service::save_payment_settings($creator, $data->siteid, (string) $data->key, !empty($data->testmode));
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/payment.php', get_string('paymentsettings', 'local_oriskill'));
echo html_writer::tag('p', get_string('paymentsettings_intro', 'local_oriskill'));
echo $OUTPUT->notification(get_string('paymentsettings_support', 'local_oriskill', (object) [
    'site' => (new moodle_url('/'))->out(false),
    'notify' => (new moodle_url('/local/secureandpay/notify.php'))->out(false),
]), 'info');
$form->display();
echo creator_service::space_end();
echo $OUTPUT->footer();
