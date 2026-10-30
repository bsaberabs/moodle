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
 * The trainer's own domains: add one, the DNS records to create, its state, the primary one, the domain of each course.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\domain_service as ds;
use local_oriskill\log_service;

$url = new moodle_url('/local/oriskill/domains.php');
$creator = creator_service::require_current_creator($url, 'menu:domains');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    $message = get_string('changessaved');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    try {
        $id = optional_param('id', 0, PARAM_INT);
        if ($action === 'add') {
            $domain = ds::add($creator, required_param('domain', PARAM_RAW_TRIMMED));
            log_service::add($creator, 'domain_added', ['domain' => $domain->domain]);
            $message = get_string($domain->status === 'active' ? 'domains:addedactive' : 'domains:added', 'local_oriskill',
                s($domain->domain));
        } else if ($action === 'primary') {
            $old = (string) $creator->customdomain;
            ds::set_primary($creator, $id);
            log_service::add($creator, 'domain_primary', ['domain' => ds::require_own($creator, $id)->domain], null, null,
                [get_string('domains:primary', 'local_oriskill') => [$old ?: '–', ds::require_own($creator, $id)->domain]]);
        } else if ($action === 'check') {
            $domain = ds::check(ds::require_own($creator, $id));
            $message = get_string($domain->status === 'active' ? 'domains:checkok' : 'domains:checkpending', 'local_oriskill',
                s($domain->domain));
            $type = $domain->status === 'active' ? $type : \core\output\notification::NOTIFY_WARNING;
        } else if ($action === 'delete') {
            $name = ds::require_own($creator, $id)->domain;
            ds::delete($creator, $id);
            log_service::add($creator, 'domain_deleted', ['domain' => $name]);
        } else if ($action === 'coursedomain') {
            ds::set_course_domain($creator, required_param('courseid', PARAM_INT), optional_param('domainid', 0, PARAM_INT));
        }
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect($url, $message, null, $type);
}

$domains = ds::domains($creator);
$hidden = fn(string $action, int $id = 0) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) .
    ($id ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]) : '');
$button = fn(string $action, int $id, string $label, string $class = 'btn-outline-secondary', ?string $confirm = null) =>
    html_writer::tag('form', $hidden($action, $id) . html_writer::tag('button', $label, ['type' => 'submit',
        'class' => 'btn btn-sm ' . $class]), ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline',
        'onsubmit' => $confirm ? 'return confirm(' . json_encode($confirm) . ')' : null]);

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/domains.php', get_string('menu:domains', 'local_oriskill'));
echo html_writer::tag('p', get_string('domains:intro', 'local_oriskill'), ['class' => 'text-muted']);

// The domains.
if ($domains) {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [get_string('domains:domain', 'local_oriskill'), get_string('domains:type', 'local_oriskill'),
        get_string('status'), get_string('domains:usedby', 'local_oriskill'), ''];
    foreach ($domains as $domain) {
        $usage = ds::usage($domain);
        $used = array_filter([
            $usage['orderforms'] ? get_string('domains:used_orderforms', 'local_oriskill', count($usage['orderforms'])) : '',
            $usage['capturepages'] ? get_string('domains:used_capturepages', 'local_oriskill', count($usage['capturepages'])) : '',
            $usage['courses'] ? get_string('domains:used_courses', 'local_oriskill', count($usage['courses'])) : '',
        ]);
        $active = $domain->status === 'active';
        $usedlist = implode(', ', array_map('format_string', array_merge($usage['orderforms'], $usage['capturepages'],
            $usage['courses'])));
        $table->data[] = [
            html_writer::link('https://' . $domain->domain . '/', s($domain->domain), ['target' => '_blank', 'rel' => 'noopener',
                'class' => 'fw-semibold']),
            html_writer::span(get_string($domain->isprimary ? 'domains:primary' : 'domains:secondary', 'local_oriskill'),
                'oriskill-badge' . ($domain->isprimary ? ' is-good' : '')),
            html_writer::span(get_string($active ? 'domains:active' : 'domains:pending', 'local_oriskill'),
                'oriskill-badge ' . ($active ? 'is-good' : 'is-warn')) .
                ($domain->lastcheck ? html_writer::div(get_string('domains:checked', 'local_oriskill',
                    userdate($domain->lastcheck, get_string('strftimedatetimeshort', 'langconfig'))), 'small text-muted') : ''),
            html_writer::div(($used ? implode(' · ', $used) : get_string('domains:usedby_showcase', 'local_oriskill')),
                'small', ['style' => 'white-space: normal']),
            html_writer::div(
                $button('check', (int) $domain->id, get_string('domains:check', 'local_oriskill')) .
                (!$domain->isprimary ? $button('primary', (int) $domain->id, get_string('domains:setprimary', 'local_oriskill')) : '') .
                $button('delete', (int) $domain->id, get_string('delete'), 'btn-outline-danger',
                    get_string('domains:delete_confirm', 'local_oriskill', (object) ['domain' => $domain->domain,
                        'used' => $usedlist ?: '–'])),
                'd-flex flex-wrap gap-1'),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card table-responsive mb-3');
}

// Add one, and what to put in its DNS.
echo html_writer::div(
    html_writer::tag('h3', get_string('domains:add', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('form', $hidden('add') . html_writer::div(
        html_writer::empty_tag('input', ['type' => 'text', 'name' => 'domain', 'required' => 'required', 'class' => 'form-control',
            'placeholder' => 'lionessclosing.com', 'aria-label' => get_string('domains:domain', 'local_oriskill')]) .
        html_writer::tag('button', get_string('add'), ['type' => 'submit', 'class' => 'btn btn-primary']),
        'd-flex gap-2', ['style' => 'max-width: 32rem']), ['method' => 'post', 'action' => $url->out(false)]) .
    html_writer::div(get_string('domains:dns', 'local_oriskill', (object) ['ip' => creator_service::SERVER_IPV4]),
        'small text-muted mt-3'),
    'oriskill-card mb-3');

// Domain of each course page.
$others = array_filter($domains, fn($d) => !$d->isprimary);
if ($others) {
    $options = ds::options($creator);
    $rows = '';
    foreach ($DB->get_records('course', ['category' => $creator->categoryid], 'fullname', 'id, fullname') as $course) {
        $rows .= html_writer::tag('li', html_writer::div(format_string($course->fullname), 'oriskill-row-main') .
            html_writer::tag('form', $hidden('coursedomain') .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $course->id]) .
                html_writer::select($options, 'domainid', ds::course_domainid((int) $course->id), false,
                    ['class' => 'form-select form-select-sm', 'onchange' => 'this.form.submit()',
                     'aria-label' => get_string('domains:coursedomain', 'local_oriskill')]),
                ['method' => 'post', 'action' => $url->out(false)]));
    }
    echo html_writer::div(html_writer::tag('h3', get_string('domains:courses', 'local_oriskill'), ['class' => 'h5']) .
        html_writer::tag('p', get_string('domains:courses_help', 'local_oriskill'), ['class' => 'small text-muted']) .
        html_writer::tag('ul', $rows, ['class' => 'oriskill-rows']), 'oriskill-card');
}
echo creator_service::space_end();
echo $OUTPUT->footer();
