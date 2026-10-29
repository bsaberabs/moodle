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
    $paid = orderform_service::paid_counts($creator);
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle oriskill-documents';
    $table->head = [get_string('orderform:title', 'local_oriskill'), get_string('orderform:address', 'local_oriskill'),
        get_string('orderform:offers', 'local_oriskill'), get_string('orderform:orders', 'local_oriskill'), ''];
    foreach ($forms as $form) {
        $public = orderform_service::url($form, $creator)->out(false);
        $published = $form->status === 'published';
        $table->data[] = [
            html_writer::span(format_string($form->title), 'fw-semibold') . ' ' .
                html_writer::span(get_string('orderform:' . $form->status, 'local_oriskill'),
                    'oriskill-badge ' . ($published ? 'is-good' : '')),
            $published ? html_writer::link($public, s($public), ['target' => '_blank', 'rel' => 'noopener', 'class' => 'small'])
                : html_writer::span(get_string('orderform:notpublished', 'local_oriskill'), 'small text-muted'),
            count(orderform_service::offers($form)),
            (int) ($paid[$form->id] ?? 0),
            html_writer::link(new moodle_url('/local/oriskill/orderform.php', ['id' => $form->id]),
                get_string('edit'), ['class' => 'btn btn-sm btn-outline-secondary']),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card');
}
echo creator_service::space_end();
echo $OUTPUT->footer();
