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
 * Affiliate commissions of the whole platform: mark them paid, suspend affiliates.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\affiliate_service;

admin_externalpage_setup('local_oriskill_commissions');
$status = optional_param('status', 'approved', PARAM_ALPHA);
$url = new moodle_url('/local/oriskill/commissions.php', ['status' => $status]);

$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    $id = required_param('id', PARAM_INT);
    if ($action === 'markpaid') {
        affiliate_service::mark_paid($id);
    } else if (in_array($action, ['suspend', 'reactivate'], true)) {
        $DB->update_record('local_oriskill_affiliates', (object) ['id' => $id,
            'status' => $action === 'suspend' ? 'suspended' : 'active', 'timemodified' => time()]);
    }
    redirect($url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$sellers = $DB->get_records_menu('local_oriskill_creators', null, '', 'id, displayname');
$seller = fn($id) => $id ? format_string($sellers[$id] ?? '?') : get_string('platformseller', 'local_oriskill');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('affiliatecommissions', 'local_oriskill'));
echo html_writer::tag('p', get_string('affiliatecommissions_intro', 'local_oriskill',
    (new moodle_url('/local/oriskill/affiliate.php'))->out(false)));

$tabs = [];
foreach (['approved', 'pending', 'paid', 'cancelled'] as $tab) {
    $tabs[] = new tabobject($tab, new moodle_url('/local/oriskill/commissions.php', ['status' => $tab]),
        get_string('commission:' . $tab, 'local_oriskill'));
}
echo $OUTPUT->tabtree($tabs, $status);

$commissions = affiliate_service::commissions(['status' => $status]);
if ($commissions) {
    $table = new html_table();
    $table->head = [get_string('date'), get_string('ordercol', 'local_oriskill'), get_string('affiliatecol', 'local_oriskill'),
        get_string('payoutdetails', 'local_oriskill'), get_string('sellercol', 'local_oriskill'), get_string('course'),
        get_string('commissioncol', 'local_oriskill'), get_string('actions')];
    foreach ($commissions as $commission) {
        $table->data[] = [
            userdate($commission->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            s($commission->ordernumber),
            fullname($commission) . '<br><small>' . s($commission->code) . '</small>',
            format_text($commission->payoutdetails ?? '', FORMAT_PLAIN),
            $seller((int) $commission->sellerid),
            format_string($commission->itemname),
            pricing_service::format_price($commission->amount, $commission->currency)
                . ' <small class="text-muted">(' . format_float($commission->rate, 2, true, true) . ' %)</small>',
            $commission->status === 'approved' ? $OUTPUT->single_button(new moodle_url($url,
                ['action' => 'markpaid', 'id' => $commission->id, 'sesskey' => sesskey()]),
                get_string('markpaid', 'local_oriskill'), 'post', ['type' => 'primary']) : '',
        ];
    }
    foreach (affiliate_service::totals($commissions)[$status] ?? [] as $currency => $total) {
        echo html_writer::tag('p', get_string('commissionstotal', 'local_oriskill',
            pricing_service::format_price($total, $currency)), ['class' => 'lead']);
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nocommissions', 'local_oriskill'), 'info');
}

// Affiliates.
echo $OUTPUT->heading(get_string('affiliates', 'local_oriskill'), 3, 'mt-5');
$userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
$affiliates = $DB->get_records_sql(
    "SELECT a.*, u.email, $userfields,
            (SELECT COUNT(1) FROM {local_oriskill_referrals} r WHERE r.affiliateid = a.id) AS orders
       FROM {local_oriskill_affiliates} a JOIN {user} u ON u.id = a.userid
   ORDER BY a.timecreated DESC");
if ($affiliates) {
    $table = new html_table();
    $table->head = [get_string('user'), get_string('affiliatecode', 'local_oriskill'), get_string('stat:clicks', 'local_oriskill'),
        get_string('stat:orders', 'local_oriskill'), get_string('status'), get_string('actions')];
    foreach ($affiliates as $affiliate) {
        $active = $affiliate->status === 'active';
        $table->data[] = [
            html_writer::link(new moodle_url('/user/profile.php', ['id' => $affiliate->userid]), fullname($affiliate))
                . '<br>' . s($affiliate->email),
            s($affiliate->code),
            (int) $affiliate->clicks,
            (int) $affiliate->orders,
            get_string('status:' . $affiliate->status, 'local_oriskill'),
            html_writer::link(new moodle_url($url, ['action' => $active ? 'suspend' : 'reactivate', 'id' => $affiliate->id,
                'sesskey' => sesskey()]), get_string($active ? 'suspend' : 'reactivate', 'local_oriskill'),
                ['class' => 'btn btn-sm ' . ($active ? 'btn-outline-danger' : 'btn-outline-primary')]),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('noaffiliates', 'local_oriskill'), 'info');
}
echo $OUTPUT->footer();
