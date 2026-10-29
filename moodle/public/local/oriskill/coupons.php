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
 * Trainer's promo codes for their order forms.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\pricing_service;
use local_oriskill\coupon_service;
use local_oriskill\creator_service;
use local_oriskill\form\coupon_form;
use local_oriskill\log_service;
use local_oriskill\orderform_service;

$id = optional_param('id', 0, PARAM_INT);
$editing = $id || optional_param('new', 0, PARAM_BOOL);
$listurl = new moodle_url('/local/oriskill/coupons.php');
$creator = creator_service::require_current_creator($listurl, 'menu:coupons');
$forms = array_map(fn($f) => format_string($f->title), orderform_service::forms($creator));
$currency = pricing_service::get_currency_config()->currency;

if ($editing) {
    $coupon = $id ? $DB->get_record('local_oriskill_coupons', ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST)
        : null;
    $url = new moodle_url($listurl, $id ? ['id' => $id] : ['new' => 1]);
    $PAGE->set_url($url);
    $mform = new coupon_form($url, ['creator' => $creator, 'couponid' => $id, 'forms' => $forms]);
    if ($coupon) {
        $mform->set_data((array) $coupon + ['value' => format_float((float) $coupon->value, 2, true)]);
    }
    if ($mform->is_cancelled()) {
        redirect($listurl);
    }
    if ($data = $mform->get_data()) {
        $data->value = unformat_float($data->value, true);
        $saved = coupon_service::save($creator, $data);
        log_service::add($creator, 'coupon_saved', ['code' => $saved->code]);
        redirect($listurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/coupons.php', get_string('menu:coupons', 'local_oriskill'));
if ($editing) {
    echo html_writer::div(html_writer::link($listurl, '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' .
        get_string('menu:coupons', 'local_oriskill')), 'mb-3');
    echo html_writer::div($mform->render(), 'oriskill-card');
} else {
    echo html_writer::div(
        html_writer::tag('p', get_string('coupons:intro', 'local_oriskill'), ['class' => 'mb-0 text-muted']) .
        html_writer::link(new moodle_url($listurl, ['new' => 1]), '<i class="fa fa-plus me-1" aria-hidden="true"></i>' .
            get_string('coupon:new', 'local_oriskill'), ['class' => 'btn btn-primary']),
        'd-flex flex-wrap justify-content-between align-items-center gap-3 mb-4');
    $coupons = coupon_service::coupons($creator);
    if (!$coupons) {
        echo html_writer::div('<i class="fa fa-ticket" aria-hidden="true"></i>' .
            get_string('coupons:none', 'local_oriskill'), 'oriskill-card oriskill-empty');
    } else {
        $dateformat = get_string('strftimedate', 'langconfig');
        $table = new html_table();
        $table->attributes['class'] = 'table align-middle';
        $table->head = [get_string('coupon:code', 'local_oriskill'), get_string('coupon:value', 'local_oriskill'),
            get_string('coupon:form', 'local_oriskill'), get_string('coupon:uses', 'local_oriskill'),
            get_string('coupon:validity', 'local_oriskill'), ''];
        $now = time();
        foreach ($coupons as $c) {
            $expired = ($c->validuntil && $c->validuntil < $now) || ($c->maxuses && $c->uses >= $c->maxuses);
            $state = !$c->active ? ['coupon:inactive', ''] : ($expired ? ['coupon:ended', 'is-warn'] : ['coupon:live', 'is-good']);
            $table->data[] = [
                html_writer::span(s($c->code), 'fw-semibold font-monospace') . ' ' .
                    html_writer::span(get_string($state[0], 'local_oriskill'), trim('oriskill-badge ' . $state[1])),
                $c->type === 'percent' ? format_float((float) $c->value, 2, true, true) . ' %'
                    : pricing_service::format_price((float) $c->value, $currency),
                $c->formid ? ($forms[$c->formid] ?? '?') : get_string('coupon:allforms', 'local_oriskill'),
                $c->uses . ($c->maxuses ? ' / ' . $c->maxuses : ''),
                ($c->validfrom ? userdate($c->validfrom, $dateformat) : '…') . ' → ' .
                    ($c->validuntil ? userdate($c->validuntil, $dateformat) : '…'),
                html_writer::link(new moodle_url($listurl, ['id' => $c->id]), get_string('edit'),
                    ['class' => 'btn btn-sm btn-outline-secondary']),
            ];
        }
        echo html_writer::div(html_writer::table($table), 'oriskill-card');
    }
}
echo creator_service::space_end();
echo $OUTPUT->footer();
