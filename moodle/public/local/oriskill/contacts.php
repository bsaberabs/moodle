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
 * CRM of the trainer: all their contacts (prospects, learners, customers), with views, search, filters and tags.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use local_oriskill\contact_service as cs;
use local_oriskill\creator_service;
use local_oriskill\log_service;
use local_moderncommerce\services\pricing_service;
use local_oriskill\team_service;

// Filters, kept in the address so that a filtered list can be bookmarked and exported.
$view = optional_param('view', 'all', PARAM_ALPHA);
$filters = [
    'view' => in_array($view, cs::VIEWS, true) ? $view : 'all',
    'q' => optional_param('q', '', PARAM_RAW_TRIMMED),
    'status' => optional_param('status', '', PARAM_ALPHA),
    'tag' => optional_param('tag', '', PARAM_RAW_TRIMMED),
    'course' => optional_param('course', '', PARAM_RAW_TRIMMED),
    'ltvmin' => optional_param('ltvmin', '', PARAM_RAW_TRIMMED),
    'ltvmax' => optional_param('ltvmax', '', PARAM_RAW_TRIMMED),
    'from' => optional_param('from', '', PARAM_RAW_TRIMMED),
    'to' => optional_param('to', '', PARAM_RAW_TRIMMED),
    'consent' => optional_param('consent', '', PARAM_ALPHA),
];
$sort = optional_param('sort', 'created', PARAM_ALPHA);
$sort = isset(cs::SORTS[$sort]) ? $sort : 'created';
$dir = optional_param('dir', 'DESC', PARAM_ALPHA) === 'ASC' ? 'ASC' : 'DESC';
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$perpage = in_array($perpage, cs::PERPAGE, true) ? $perpage : 25;
$params = array_filter($filters, fn($v) => $v !== '' && $v !== 'all') + ($sort !== 'created' ? ['sort' => $sort] : [])
    + ($dir !== 'DESC' ? ['dir' => $dir] : []) + ($perpage !== 25 ? ['perpage' => $perpage] : []);
$url = new moodle_url('/local/oriskill/contacts.php', $params);
$creator = creator_service::require_current_creator($url, 'menu:contacts');
$canchange = team_service::can_change(creator_service::current_role(), '/local/oriskill/contacts.php');

// What the filters mean for the search.
$day = fn(string $value, bool $end) => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)
    ? make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], $end ? 23 : 0, $end ? 59 : 0, $end ? 59 : 0) : 0;
[$courseid, $groupid] = array_map('intval', array_pad(explode(':', $filters['course']), 2, 0));
$search = ['view' => $filters['view'], 'q' => $filters['q'], 'status' => $filters['status'], 'tag' => $filters['tag'],
    'courseid' => $courseid, 'groupid' => $groupid,
    'ltvmin' => $filters['ltvmin'] === '' ? '' : unformat_float($filters['ltvmin'], true),
    'ltvmax' => $filters['ltvmax'] === '' ? '' : unformat_float($filters['ltvmax'], true),
    'from' => $day($filters['from'], false), 'to' => $day($filters['to'], true), 'consent' => $filters['consent']];

// Actions on the selected contacts, and adding one.
if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    $ids = optional_param_array('ids', [], PARAM_INT);
    if ($action === 'export') {
        // Below.
    } else {
        if (!$canchange) {
            throw new moodle_exception('error:notallowed', 'local_oriskill');
        }
        $message = get_string('changessaved');
        $type = \core\output\notification::NOTIFY_SUCCESS;
        try {
            if ($action === 'savesegment') {
                $name = required_param('name', PARAM_TEXT);
                cs::save_segment($creator, $name, $filters);
                log_service::add($creator, 'segment_saved', ['name' => $name]);
                redirect($url, get_string('segment:saved', 'local_oriskill', s($name)), null, $type);
            }
            if ($action === 'deletesegment') {
                cs::delete_segment($creator, required_param('segmentid', PARAM_INT));
                redirect($url, $message, null, $type);
            }
            if ($action === 'add') {
                $contact = cs::upsert($creator, required_param('email', PARAM_RAW_TRIMMED), [
                    'firstname' => optional_param('firstname', '', PARAM_NOTAGS),
                    'lastname' => optional_param('lastname', '', PARAM_NOTAGS),
                    'phone' => optional_param('phone', '', PARAM_NOTAGS),
                ], 'manual');
                cs::add_tags($creator, [$contact->id], cs::parse_tags(optional_param('tags', '', PARAM_RAW_TRIMMED)));
                log_service::add($creator, 'contact_added', ['email' => $contact->email]);
                redirect(new moodle_url('/local/oriskill/contact.php', ['id' => $contact->id]), $message, null, $type);
            }
            if (!$ids) {
                throw new moodle_exception('contacts:noneselected', 'local_oriskill');
            }
            if ($action === 'addtags' || $action === 'removetag') {
                $tags = cs::parse_tags(required_param('tags', PARAM_RAW_TRIMMED));
                if (!$tags) {
                    throw new moodle_exception('contacts:notag', 'local_oriskill');
                }
                if ($action === 'addtags') {
                    cs::add_tags($creator, $ids, $tags);
                } else {
                    foreach ($tags as $tag) {
                        cs::remove_tag($creator, $ids, $tag);
                    }
                }
                log_service::add($creator, 'contacts_' . $action, ['count' => count($ids), 'tags' => implode(', ', $tags)]);
            } else if ($action === 'disable' || $action === 'enable') {
                foreach ($ids as $id) {
                    cs::set_disabled($creator, cs::require_contact($creator, $id), $action === 'disable');
                }
                log_service::add($creator, 'contacts_' . $action, ['count' => count($ids)]);
            } else if ($action === 'delete') {
                $emails = $DB->get_fieldset_select(cs::TABLE, 'email', 'creatorid = ? AND id ' .
                    $DB->get_in_or_equal($ids ?: [0])[0], array_merge([$creator->id], $ids ?: [0]));
                cs::delete($creator, $ids);
                log_service::add($creator, 'contacts_delete', ['count' => count($ids)], null, null,
                    [get_string('change:deleted', 'local_oriskill') => [implode(', ', array_slice($emails, 0, 30)) .
                        (count($emails) > 30 ? ' …' : ''), '–']]);
            }
        } catch (moodle_exception $e) {
            $message = $e->getMessage();
            $type = \core\output\notification::NOTIFY_ERROR;
        }
        redirect($url, $message, null, $type);
    }
}

// CSV export: the selected contacts, or all the results of the filters.
if (optional_param('download', '', PARAM_ALPHA) === 'csv' || ($action ?? '') === 'export') {
    team_service::export_allowed($creator, get_string('menu:contacts', 'local_oriskill'));
    $ids = $action === 'export' ? optional_param_array('ids', [], PARAM_INT) : [];
    [$records] = cs::search($creator, $search + ($ids ? ['ids' => $ids] : []), $sort, $dir, 0, 0);
    $tags = cs::tags_of(array_keys($records));
    $rows = [[get_string('lastname'), get_string('firstname'), get_string('email'), get_string('phone'), get_string('address'),
        get_string('postcode', 'local_oriskill'), get_string('city'), get_string('country'), get_string('status'),
        get_string('contacts:created', 'local_oriskill'), get_string('contacts:source', 'local_oriskill'),
        get_string('contacts:ltv', 'local_oriskill'), get_string('contacts:consentdirect', 'local_oriskill'),
        get_string('contacts:consentpartners', 'local_oriskill'), get_string('contacts:tags', 'local_oriskill')]];
    foreach ($records as $c) {
        $rows[] = [$c->lastname, $c->firstname, $c->email, $c->phone, $c->address, $c->postcode, $c->city, $c->country,
            get_string('contacts:status_' . $c->status, 'local_oriskill'), userdate($c->timecreated, '%Y-%m-%d'),
            get_string('contacts:source_' . $c->source, 'local_oriskill'), format_float((float) $c->ltv, 2, true),
            get_string($c->consentdirect ? 'yes' : 'no'), get_string($c->consentpartners ? 'yes' : 'no'),
            implode(', ', $tags[$c->id] ?? [])];
    }
    csv_export_writer::download_array(clean_filename(get_string('menu:contacts', 'local_oriskill') . '-' . date('Y-m-d')), $rows);
    exit;
}

[$records, $total] = cs::search($creator, $search, $sort, $dir, $page, $perpage);
$tags = cs::tags_of(array_keys($records));
$counts = cs::counts($creator);
$alltags = cs::all_tags($creator);
$courses = $DB->get_records('course', ['category' => $creator->categoryid], 'fullname', 'id, fullname');

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/contacts.php', get_string('menu:contacts', 'local_oriskill'),
    team_service::can(creator_service::current_role(), '/local/oriskill/contactimport.php')
        ? html_writer::link(new moodle_url('/local/oriskill/contactimport.php'), '<i class="fa fa-file-import me-1" aria-hidden="true"></i>' .
            get_string('import:title', 'local_oriskill'), ['class' => 'btn btn-outline-primary']) : '');
echo html_writer::tag('p', get_string('contacts:intro', 'local_oriskill'), ['class' => 'text-muted']);

// Quick views.
$tabs = '';
foreach (cs::VIEWS as $name) {
    $tabs .= html_writer::link(new moodle_url($url, ['view' => $name, 'page' => 0]),
        get_string('contacts:view_' . $name, 'local_oriskill') . ' ' .
        html_writer::span(number_format($counts[$name], 0, ',', ' '), 'oriskill-badge ms-1'),
        ['class' => 'btn btn-sm ' . ($filters['view'] === $name ? 'btn-primary' : 'btn-outline-secondary')]);
}
echo html_writer::div($tabs, 'd-flex flex-wrap gap-1 mb-3');

// Saved segments: a click applies their filters; the current filters can be saved as one.
$sesskeyinput = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$chips = '';
foreach (cs::segments($creator) as $segment) {
    $segmenturl = new moodle_url('/local/oriskill/contacts.php', $segment->filters);
    $current = $segmenturl->params() == array_filter($filters, fn($v) => $v !== '' && $v !== 'all');
    $chips .= html_writer::span(
        html_writer::link($segmenturl, '<i class="fa fa-filter me-1" aria-hidden="true"></i>' . s($segment->name),
            ['class' => 'text-reset text-decoration-none']) .
        ($canchange ? html_writer::tag('form', $sesskeyinput .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deletesegment']) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'segmentid', 'value' => $segment->id]) .
            html_writer::tag('button', '×', ['type' => 'submit', 'class' => 'btn btn-link btn-sm p-0 ms-1 text-reset',
                'title' => get_string('segment:delete', 'local_oriskill', s($segment->name))]),
            ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline',
             'onsubmit' => 'return confirm(' . json_encode(get_string('segment:delete_confirm', 'local_oriskill')) . ')']) : ''),
        'oriskill-badge ' . ($current ? 'is-good' : '') . ' me-1');
}
$filtered = (bool) array_filter($filters, fn($v) => $v !== '' && $v !== 'all');
if ($chips || ($canchange && $filtered)) {
    echo html_writer::div(
        html_writer::span(get_string('segment:title', 'local_oriskill'), 'small text-muted me-1') . $chips .
        ($canchange && $filtered ? html_writer::tag('form', $sesskeyinput .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savesegment']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'required' => 'required', 'maxlength' => 100,
                'class' => 'form-control form-control-sm', 'style' => 'width: 14rem',
                'placeholder' => get_string('segment:name', 'local_oriskill'), 'aria-label' => get_string('segment:name', 'local_oriskill')]) .
            html_writer::tag('button', get_string('segment:save', 'local_oriskill'), ['type' => 'submit',
                'class' => 'btn btn-sm btn-outline-primary text-nowrap']),
            ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline-flex gap-1 ms-2']) : ''),
        'd-flex flex-wrap align-items-center gap-1 mb-3');
}

// Search and filters.
$hidden = '';
foreach (['view' => $filters['view'], 'sort' => $sort, 'dir' => $dir, 'perpage' => $perpage] as $name => $value) {
    $hidden .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
$field = fn(string $label, string $control) => html_writer::div(html_writer::tag('label', $label, ['class' => 'form-label small mb-1']) .
    $control, 'col-sm-6 col-lg-3');
$courseoptions = ['' => get_string('all')];
foreach ($courses as $course) {
    $courseoptions[$course->id] = format_string($course->fullname);
    foreach (groups_get_all_groups($course->id) as $group) {
        $courseoptions[$course->id . ':' . $group->id] = '— ' . format_string($group->name);
    }
}
$filtersopen = count(array_filter(array_diff_key($filters, ['view' => 1, 'q' => 1]), fn($v) => $v !== '')) > 0;
echo html_writer::tag('form', $hidden .
    html_writer::div(
        html_writer::empty_tag('input', ['type' => 'search', 'name' => 'q', 'value' => $filters['q'], 'class' => 'form-control',
            'placeholder' => get_string('contacts:search', 'local_oriskill'), 'aria-label' => get_string('search')]) .
        html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-primary']),
        'd-flex gap-2 mb-2', ['style' => 'max-width: 36rem']) .
    html_writer::tag('details', html_writer::tag('summary', get_string('contacts:filters', 'local_oriskill'), ['class' => 'small']) .
        html_writer::div(
            $field(get_string('status'), html_writer::select(['active' => get_string('contacts:status_active', 'local_oriskill'),
                'disabled' => get_string('contacts:status_disabled', 'local_oriskill')], 'status', $filters['status'],
                ['' => get_string('all')], ['class' => 'form-select form-select-sm'])) .
            $field(get_string('contacts:tag', 'local_oriskill'), html_writer::select(array_combine(array_keys($alltags),
                array_map(fn($t, $n) => "$t ($n)", array_keys($alltags), $alltags)) ?: [], 'tag', $filters['tag'],
                ['' => get_string('all')], ['class' => 'form-select form-select-sm'])) .
            $field(get_string('contacts:course', 'local_oriskill'), html_writer::select($courseoptions, 'course', $filters['course'],
                false, ['class' => 'form-select form-select-sm'])) .
            $field(get_string('contacts:consentdirect', 'local_oriskill'), html_writer::select(['yes' => get_string('yes'),
                'no' => get_string('no')], 'consent', $filters['consent'], ['' => get_string('all')],
                ['class' => 'form-select form-select-sm'])) .
            $field(get_string('contacts:ltvmin', 'local_oriskill'), html_writer::empty_tag('input', ['type' => 'text',
                'name' => 'ltvmin', 'value' => $filters['ltvmin'], 'class' => 'form-control form-control-sm', 'inputmode' => 'decimal'])) .
            $field(get_string('contacts:ltvmax', 'local_oriskill'), html_writer::empty_tag('input', ['type' => 'text',
                'name' => 'ltvmax', 'value' => $filters['ltvmax'], 'class' => 'form-control form-control-sm', 'inputmode' => 'decimal'])) .
            $field(get_string('contacts:from', 'local_oriskill'), html_writer::empty_tag('input', ['type' => 'date', 'name' => 'from',
                'value' => $filters['from'], 'class' => 'form-control form-control-sm'])) .
            $field(get_string('contacts:to', 'local_oriskill'), html_writer::empty_tag('input', ['type' => 'date', 'name' => 'to',
                'value' => $filters['to'], 'class' => 'form-control form-control-sm'])) .
            html_writer::div(html_writer::tag('button', get_string('contacts:apply', 'local_oriskill'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']) . ' ' .
                html_writer::link(new moodle_url('/local/oriskill/contacts.php', ['view' => $filters['view']]),
                    get_string('contacts:reset', 'local_oriskill'), ['class' => 'btn btn-sm btn-outline-secondary']), 'col-12'),
            'row g-2 mt-1'),
        ['open' => $filtersopen ? 'open' : null]),
    ['method' => 'get', 'action' => (new moodle_url('/local/oriskill/contacts.php'))->out(false),
     'class' => 'oriskill-card mb-3']);

// Add a contact.
if ($canchange) {
    $input = fn(string $name, string $type, string $label, bool $required = false) => html_writer::div(
        html_writer::tag('label', $label, ['class' => 'form-label small mb-1']) .
        html_writer::empty_tag('input', ['type' => $type, 'name' => $name, 'class' => 'form-control form-control-sm',
            'required' => $required ? 'required' : null]), 'col-sm-6 col-lg');
    echo html_writer::tag('details', html_writer::tag('summary', '<i class="fa fa-user-plus me-1" aria-hidden="true"></i>' .
        get_string('contacts:add', 'local_oriskill'), ['class' => 'fw-semibold']) .
        html_writer::tag('form',
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
            html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']) .
            html_writer::div($input('email', 'email', get_string('email'), true) . $input('firstname', 'text', get_string('firstname')) .
                $input('lastname', 'text', get_string('lastname')) . $input('phone', 'text', get_string('phone')) .
                $input('tags', 'text', get_string('contacts:tags_help', 'local_oriskill')) .
                html_writer::div(html_writer::tag('button', get_string('add'), ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']),
                    'col-auto align-self-end'), 'row g-2 mt-1'),
            ['method' => 'post', 'action' => $url->out(false)]),
        ['class' => 'oriskill-card mb-3']);
}

// The list.
$sortlink = function(string $key, string $label) use ($url, $sort, $dir) {
    $next = $sort === $key && $dir === 'ASC' ? 'DESC' : 'ASC';
    $arrow = $sort === $key ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    return html_writer::link(new moodle_url($url, ['sort' => $key, 'dir' => $next, 'page' => 0]), $label . $arrow,
        ['class' => 'text-reset text-decoration-none']);
};
$formid = 'oriskill-contacts';
if (!$records) {
    echo html_writer::div(get_string('contacts:empty', 'local_oriskill'), 'oriskill-card text-muted');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'table align-middle';
    $table->head = [
        html_writer::empty_tag('input', ['type' => 'checkbox', 'class' => 'form-check-input', 'title' => get_string('selectall'),
            'onclick' => "document.querySelectorAll('#$formid input[name=\"ids[]\"]').forEach(c => c.checked = this.checked)"]),
        $sortlink('name', get_string('name')), $sortlink('email', get_string('email')), $sortlink('status', get_string('status')),
        $sortlink('created', get_string('contacts:created', 'local_oriskill')), $sortlink('ltv', get_string('contacts:ltv', 'local_oriskill')),
        get_string('contacts:tags', 'local_oriskill'),
    ];
    $dateformat = get_string('strftimedatefullshort', 'langconfig');
    foreach ($records as $c) {
        $name = trim($c->firstname . ' ' . $c->lastname) ?: $c->email;
        $initials = core_text::strtoupper(core_text::substr($c->firstname ?: $c->email, 0, 1) . core_text::substr($c->lastname, 0, 1));
        $link = new moodle_url('/local/oriskill/contact.php', ['id' => $c->id]);
        $ctags = $tags[$c->id] ?? [];
        $table->data[] = [
            html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'ids[]', 'value' => $c->id, 'form' => $formid,
                'class' => 'form-check-input', 'aria-label' => s($name)]),
            html_writer::link($link, html_writer::span(s($initials), 'oriskill-initials me-2') . s($name),
                ['class' => 'fw-semibold text-nowrap']),
            html_writer::span(s($c->email), 'small text-break'),
            html_writer::span(get_string('contacts:status_' . $c->status, 'local_oriskill'),
                'oriskill-badge ' . ($c->status === 'active' ? 'is-good' : 'is-warn')),
            userdate($c->timecreated, $dateformat),
            $c->ltv > 0 ? pricing_service::format_price((float) $c->ltv, 'EUR') : '–',
            html_writer::div(implode(' ', array_map(fn($t) => html_writer::span(s($t), 'oriskill-badge'), array_slice($ctags, 0, 2))) .
                (count($ctags) > 2 ? ' ' . html_writer::span('+' . (count($ctags) - 2), 'oriskill-badge',
                    ['title' => s(implode(', ', array_slice($ctags, 2)))]) : ''), 'd-flex flex-wrap gap-1',
                ['style' => 'white-space: normal; min-width: 7rem']),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'oriskill-card table-responsive');

    // Actions on the selection.
    $bulk = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $actions = team_service::can_export() ? ['export' => get_string('contacts:exportselected', 'local_oriskill')] : [];
    if ($canchange) {
        $actions += ['addtags' => get_string('contacts:addtags', 'local_oriskill'),
            'removetag' => get_string('contacts:removetags', 'local_oriskill'),
            'disable' => get_string('contacts:disable', 'local_oriskill'), 'enable' => get_string('contacts:enable', 'local_oriskill'),
            'delete' => get_string('contacts:delete', 'local_oriskill')];
    }
    if ($actions) {
        echo html_writer::tag('form', $bulk .
            html_writer::span(get_string('contacts:withselected', 'local_oriskill'), 'small text-muted') .
            html_writer::select($actions, 'action', '', false, ['class' => 'form-select form-select-sm w-auto']) .
            ($canchange ? html_writer::empty_tag('input', ['type' => 'text', 'name' => 'tags', 'class' => 'form-control form-control-sm w-auto',
                'placeholder' => get_string('contacts:tags_help', 'local_oriskill')]) : '') .
            html_writer::tag('button', get_string('go'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary']),
            ['method' => 'post', 'action' => $url->out(false), 'id' => $formid, 'class' => 'd-flex flex-wrap align-items-center gap-2 mt-2',
             'onsubmit' => "return this.action.value !== 'delete' || confirm(" .
                json_encode(get_string('contacts:delete_confirm', 'local_oriskill')) . ')']);
    }
}

// Pages, rows per page, export of the results.
$per = '';
foreach (cs::PERPAGE as $n) {
    $per .= html_writer::link(new moodle_url($url, ['perpage' => $n, 'page' => 0]), $n,
        ['class' => 'btn btn-sm ' . ($n === $perpage ? 'btn-secondary' : 'btn-outline-secondary')]);
}
echo html_writer::div(
    html_writer::div(get_string('contacts:results', 'local_oriskill', number_format($total, 0, ',', ' ')) . ' · ' .
        get_string('contacts:perpage', 'local_oriskill') . ' ' . html_writer::span($per, 'd-inline-flex gap-1'), 'small text-muted') .
    (team_service::can_export() && $total ? html_writer::link(new moodle_url($url, ['download' => 'csv']),
        '<i class="fa fa-download me-1" aria-hidden="true"></i>' . get_string('contacts:exportall', 'local_oriskill'),
        ['class' => 'btn btn-sm btn-outline-secondary']) : ''),
    'd-flex flex-wrap justify-content-between align-items-center gap-2 mt-3');
echo $OUTPUT->paging_bar($total, $page, $perpage, $url);

echo creator_service::space_end();
echo $OUTPUT->footer();
