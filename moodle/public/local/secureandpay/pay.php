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
 * Posts the signed payment request to SecureAndPay from the buyer's browser.
 *
 * SecureAndPay only accepts a form post coming from the declared merchant site.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\api\order_api;
use local_secureandpay\gateway;

require_login();

$orderid = required_param('orderid', PARAM_INT);
$reference = required_param('ref', PARAM_ALPHANUM);

$order = order_api::get_order($orderid);
if (!$order || (int) $order->userid !== (int) $USER->id) {
    throw new moodle_exception('accessdenied', 'admin');
}
if ($order->status !== 'pending') {
    redirect(new moodle_url('/local/moderncommerce/success.php', ['orderid' => $orderid]));
}
// The reference must be the one recorded when this attempt (or free trial) started.
if (!gateway::reference_matches($order, $reference)) {
    throw new moodle_exception('invalidorder', 'local_moderncommerce');
}

$request = (new gateway())->build_request($order, $reference);
$sent = json_decode(base64_decode($request['data']), true);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/secureandpay/pay.php', ['orderid' => $orderid, 'ref' => $reference]));
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('redirecting', 'local_secureandpay'));

echo $OUTPUT->header();
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $request['url'], 'id' => 'secureandpay-form',
    'enctype' => 'application/x-www-form-urlencoded']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'data', 'value' => $request['data']]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'signature', 'value' => $request['signature']]);
echo html_writer::tag('p', get_string('redirecting', 'local_secureandpay'));
// Tell the buyer when the card is kept by SecureAndPay (trial, subscription or one-click payments).
if ((int) $sent['Clicktopay'] === 1) {
    echo html_writer::tag('p', get_string('cardwillbesaved', 'local_secureandpay'), ['class' => 'text-muted small']);
}
echo html_writer::tag('button', get_string('continuetopayment', 'local_secureandpay'),
    ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');
$PAGE->requires->js_amd_inline("document.getElementById('secureandpay-form').submit();");
echo $OUTPUT->footer();
