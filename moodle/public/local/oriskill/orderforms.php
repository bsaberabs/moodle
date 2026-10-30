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
 * Trainer's order forms: list, address to share, paid orders.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\orderform_service;

$url = new moodle_url('/local/oriskill/orderforms.php');
$creator = creator_service::require_current_creator($url, 'menu:orderforms');
$newurl = new moodle_url('/local/oriskill/orderform.php');

if (optional_param('action', '', PARAM_ALPHA) === 'duplicate' && confirm_sesskey()) {
    $copy = orderform_service::duplicate($creator, required_param('id', PARAM_INT));
    \local_oriskill\log_service::add($creator, 'orderform_duplicated', ['title' => $copy->title]);
    redirect(new moodle_url('/local/oriskill/orderform.php', ['id' => $copy->id]),
        get_string('orderform:duplicated', 'local_oriskill'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/orderforms.php', get_string('menu:orderforms', 'local_oriskill'));
echo html_writer::div(
    html_writer::tag('p', get_string('orderforms:intro', 'local_oriskill'), ['class' => 'mb-0 text-muted']) .
    html_writer::link($newurl, '<i class="fa fa-plus me-1" aria-hidden="true"></i>' .
        get_string('orderform:new', 'local_oriskill'), ['class' => 'btn btn-primary']),
    'd-flex flex-wrap justify-content-between align-items-center gap-3 mb-4');
if (!orderform_service::can_sell($creator)) {
    echo $OUTPUT->notification(get_string('orderform:cannotsell', 'local_oriskill',
        (new moodle_url('/local/oriskill/payment.php'))->out(false)), 'warning');
}

$forms = orderform_service::forms($creator);
if (!$forms) {
    echo html_writer::div('<i class="fa fa-file-lines" aria-hidden="true"></i>' .
        get_string('orderforms:none', 'local_oriskill'), 'oriskill-card oriskill-empty');
} else {
    $currency = \local_moderncommerce\services\pricing_service::get_currency_config()->currency;
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle oriskill-documents';
    $table->head = [get_string('orderform:title', 'local_oriskill'), get_string('orderform:address', 'local_oriskill'),
        get_string('stats:views30', 'local_oriskill'), get_string('stats:orders', 'local_oriskill'),
        get_string('stats:conversion', 'local_oriskill'), get_string('stats:revenue', 'local_oriskill'), ''];
    foreach ($forms as $form) {
        $public = orderform_service::url($form, $creator)->out(false);
        $figures = \local_oriskill\formstats_service::figures($form, 30);
        $published = $form->status === 'published';
        $table->data[] = [
            html_writer::span(format_string($form->title), 'fw-semibold') . ' ' .
                html_writer::span(get_string('orderform:' . $form->status, 'local_oriskill'),
                    'oriskill-badge ' . ($published ? 'is-good' : '')),
            $published ? html_writer::link($public, s($public), ['target' => '_blank', 'rel' => 'noopener', 'class' => 'small'])
                : html_writer::span(get_string('orderform:notpublished', 'local_oriskill'), 'small text-muted'),
            $figures->views,
            $figures->orders,
            format_float($figures->conversion, 1, true, true) . ' %',
            \local_moderncommerce\services\pricing_service::format_price($figures->revenue, $currency),
            html_writer::link(new moodle_url('/local/oriskill/orderformstats.php', ['id' => $form->id]),
                get_string('stats:title', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary']) . ' ' .
            html_writer::link(new moodle_url('/local/oriskill/abandons.php', ['id' => $form->id]),
                get_string('abandon:title', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary']) . ' ' .
            html_writer::link(new moodle_url('/local/oriskill/orderform.php', ['id' => $form->id]),
                get_string('edit'), ['class' => 'btn btn-sm btn-outline-secondary']) . ' ' .
            html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'duplicate']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $form->id]) .
                html_writer::tag('button', get_string('orderform:duplicate', 'local_oriskill'),
                    ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary',
                     'title' => get_string('orderform:duplicate_help', 'local_oriskill')]),
                ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card');
}
echo creator_service::space_end();
echo $OUTPUT->footer();
