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
 * Type a manual invoice (a sale outside the platform) or a quote, preview its PDF, issue it.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use local_oriskill\billing_service;
use local_oriskill\creator_service;

$type = optional_param('type', 'invoice', PARAM_ALPHA) === 'quote' ? 'quote' : 'invoice';
$url = new moodle_url('/local/oriskill/document.php', ['type' => $type]);
$creator = creator_service::require_current_creator($url, 'menu:invoices');
$back = new moodle_url('/local/oriskill/invoices.php');

// What was typed (kept when the page comes back with an error).
$buyer = ['name' => optional_param('name', '', PARAM_TEXT), 'email' => optional_param('email', '', PARAM_TEXT),
    'address' => optional_param('address', '', PARAM_TEXT)];
$lines = [];
for ($i = 0; $i < billing_service::FREE_LINES; $i++) {
    $lines[] = ['name' => optional_param('line' . $i, '', PARAM_TEXT),
        'quantity' => unformat_float(optional_param('qty' . $i, '1', PARAM_RAW_TRIMMED), true) ?: 0,
        'unitprice' => unformat_float(optional_param('price' . $i, '', PARAM_RAW_TRIMMED), true) ?: 0];
}
$options = ['method' => optional_param('method', 'transfer', PARAM_ALPHA), 'paid' => optional_param('paid', 0, PARAM_BOOL),
    'days' => optional_param('days', billing_service::QUOTE_DAYS, PARAM_INT), 'notes' => optional_param('notes', '', PARAM_TEXT)];
$error = '';

if (data_submitted() && confirm_sesskey()) {
    try {
        if (optional_param('preview', '', PARAM_RAW) !== '') {
            [$filename, $content] = billing_service::preview($creator, $type, $buyer, $lines, $options);
            send_file($content, $filename, 0, 0, true, false, 'application/pdf');
        }
        $document = $type === 'quote' ? billing_service::issue_quote($creator, $buyer, $lines, $options)
            : billing_service::issue_manual_invoice($creator, $buyer, $lines, $options);
        $sent = optional_param('send', 0, PARAM_BOOL) && billing_service::mail_invoice($document, true);
        redirect($back, get_string('document:issued_' . $type, 'local_oriskill', $document->number) .
            ($sent ? ' ' . get_string('invoicemail:sent', 'local_oriskill', $document->number) : ''), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/invoices.php', get_string('document:new_' . $type, 'local_oriskill'));
echo html_writer::div(html_writer::link($back, '<i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' .
    get_string('menu:invoices', 'local_oriskill')), 'mb-3');
echo html_writer::tag('p', get_string('document:intro_' . $type, 'local_oriskill', format_float((float) $creator->vatrate, 1,
    true, true)), ['class' => 'text-muted']);
if (!billing_service::details_complete($creator)) {
    echo $OUTPUT->notification(get_string('billing:incomplete', 'local_oriskill'), 'warning');
}
if ($error !== '') {
    echo $OUTPUT->notification($error, 'error');
}

$field = function(string $name, string $label, string $value, array $attrs = []) {
    $id = 'doc-' . $name;
    $input = ($attrs['type'] ?? '') === 'textarea'
        ? html_writer::tag('textarea', s($value), ['name' => $name, 'id' => $id, 'rows' => 3, 'class' => 'form-control'])
        : html_writer::empty_tag('input', ['type' => 'text', 'name' => $name, 'id' => $id, 'value' => $value,
            'class' => 'form-control'] + $attrs);
    return html_writer::div(html_writer::label($label, $id, true, ['class' => 'form-label small']) . $input, 'mb-2');
};

// Customer.
$customer = html_writer::tag('h3', get_string('document:customer', 'local_oriskill'), ['class' => 'h6']) .
    html_writer::tag('p', get_string('document:customerhelp', 'local_oriskill'), ['class' => 'small text-muted']) .
    $field('email', get_string('email'), $buyer['email'], ['type' => 'email']) .
    $field('name', get_string('document:name', 'local_oriskill'), $buyer['name']) .
    $field('address', get_string('address'), $buyer['address'], ['type' => 'textarea']);

// Lines.
$rows = '';
foreach ($lines as $i => $line) {
    $rows .= html_writer::tag('tr',
        html_writer::tag('td', html_writer::empty_tag('input', ['type' => 'text', 'name' => 'line' . $i, 'value' => $line['name'],
            'class' => 'form-control form-control-sm', 'maxlength' => 255,
            'aria-label' => get_string('invoice:description', 'local_oriskill') . ' ' . ($i + 1)])) .
        html_writer::tag('td', html_writer::empty_tag('input', ['type' => 'text', 'inputmode' => 'decimal', 'name' => 'qty' . $i,
            'value' => $line['quantity'] ? format_float($line['quantity'], -1, true) : '1', 'class' => 'form-control form-control-sm',
            'style' => 'width: 5rem', 'aria-label' => get_string('invoice:quantity', 'local_oriskill')])) .
        html_writer::tag('td', html_writer::empty_tag('input', ['type' => 'text', 'inputmode' => 'decimal', 'name' => 'price' . $i,
            'value' => $line['unitprice'] ? format_float($line['unitprice'], 2, true) : '', 'class' => 'form-control form-control-sm',
            'style' => 'width: 8rem', 'aria-label' => get_string('document:unitpriceincl', 'local_oriskill')])));
}
$linestable = html_writer::tag('h3', get_string('document:lines', 'local_oriskill'), ['class' => 'h6']) .
    html_writer::div(html_writer::tag('table',
        html_writer::tag('thead', html_writer::tag('tr', html_writer::tag('th', get_string('invoice:description', 'local_oriskill')) .
            html_writer::tag('th', get_string('invoice:quantity', 'local_oriskill')) .
            html_writer::tag('th', get_string('document:unitpriceincl', 'local_oriskill')))) .
        html_writer::tag('tbody', $rows), ['class' => 'table table-sm align-middle mb-1']), 'table-responsive');

// Payment (invoice) or validity (quote), mentions.
$methods = [];
foreach (billing_service::METHODS as $method) {
    $methods[$method] = get_string('invoice:method_' . $method, 'local_oriskill');
}
$settings = $type === 'invoice'
    ? html_writer::div(html_writer::label(get_string('journal:method', 'local_oriskill'), 'doc-method', true,
        ['class' => 'form-label small']) . html_writer::select($methods, 'method', $options['method'], false,
        ['id' => 'doc-method', 'class' => 'form-select']), 'mb-2') .
      html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'paid', 'value' => 1, 'id' => 'doc-paid',
        'class' => 'form-check-input'] + ($options['paid'] || !data_submitted() ? ['checked' => 'checked'] : [])) .
        html_writer::label(get_string('document:paid', 'local_oriskill'), 'doc-paid', true, ['class' => 'form-check-label']),
        'form-check mb-2')
    : $field('days', get_string('document:days', 'local_oriskill'), (string) $options['days'],
        ['type' => 'number', 'min' => 1, 'max' => 365, 'style' => 'max-width: 8rem']);
$settings .= $field('notes', get_string('document:notes', 'local_oriskill'), $options['notes'], ['type' => 'textarea']) .
    html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'send', 'value' => 1, 'id' => 'doc-send',
        'class' => 'form-check-input']) .
        html_writer::label(get_string('document:send', 'local_oriskill'), 'doc-send', true, ['class' => 'form-check-label']),
        'form-check mb-3');

echo html_writer::tag('form',
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'type', 'value' => $type]) .
    html_writer::div($customer, 'oriskill-card mb-3') .
    html_writer::div($linestable, 'oriskill-card mb-3') .
    html_writer::div($settings, 'oriskill-card mb-3') .
    html_writer::div(
        html_writer::tag('button', '<i class="fa fa-eye me-1" aria-hidden="true"></i>' .
            get_string('document:previewbutton', 'local_oriskill'), ['type' => 'submit', 'name' => 'preview', 'value' => 1,
            'formtarget' => '_blank', 'class' => 'btn btn-outline-secondary']) .
        html_writer::tag('button', get_string('document:issue_' . $type, 'local_oriskill'), ['type' => 'submit',
            'class' => 'btn btn-primary', 'onclick' => 'return confirm(' .
            json_encode(get_string('document:confirm_' . $type, 'local_oriskill')) . ')']),
        'd-flex gap-2'),
    ['method' => 'post', 'action' => $url->out(false)]);

echo creator_service::space_end();
echo $OUTPUT->footer();
