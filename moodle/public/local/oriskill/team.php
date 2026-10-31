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
 * Trainer's team: invite collaborators, change their role, remove them.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\team_service;

$url = new moodle_url('/local/oriskill/team.php');
$creator = creator_service::require_current_creator($url, 'menu:team');

if (($action = optional_param('action', '', PARAM_ALPHA)) && confirm_sesskey()) {
    $message = get_string('changessaved');
    $type = \core\output\notification::NOTIFY_SUCCESS;
    try {
        if ($action === 'invite') {
            $member = team_service::invite($creator, required_param('email', PARAM_RAW_TRIMMED),
                required_param('role', PARAM_ALPHANUM));
            $message = get_string('team:invited', 'local_oriskill', s($member->email));
        } else if ($action === 'saverole') {
            $saved = team_service::save_custom_role($creator, (object) ['id' => optional_param('roleid', 0, PARAM_INT),
                'name' => required_param('rolename', PARAM_TEXT), 'pages' => array_combine(array_map(fn($page) =>
                    '/local/oriskill/' . $page . '.php', array_keys($pages = optional_param_array('pages', [], PARAM_ALPHA))), $pages),
                'canexport' => optional_param('canexport', 0, PARAM_BOOL), 'mfa' => optional_param('mfa', 0, PARAM_BOOL)]);
            $message = get_string('customrole:saved', 'local_oriskill', format_string($saved->name));
        } else if ($action === 'deleterole') {
            team_service::delete_custom_role($creator, required_param('roleid', PARAM_INT));
        } else {
            $member = $DB->get_record('local_oriskill_members', ['id' => required_param('memberid', PARAM_INT),
                'creatorid' => $creator->id], '*', MUST_EXIST);
            if ($action === 'setrole') {
                team_service::set_role($creator, $member, required_param('role', PARAM_ALPHANUM));
            } else if ($action === 'setexport') {
                team_service::set_export($creator, $member, optional_param('canexport', 0, PARAM_BOOL));
            } else if ($action === 'resend') {
                team_service::send_invitation($creator, $member);
                $message = get_string('team:invited', 'local_oriskill', s($member->email));
            } else if ($action === 'remove') {
                team_service::remove($creator, $member);
                $message = get_string('team:removed', 'local_oriskill');
            }
        }
    } catch (moodle_exception $e) {
        $message = $e->getMessage();
        $type = \core\output\notification::NOTIFY_ERROR;
    }
    redirect($url, $message, null, $type);
}

$roles = [];
foreach (team_service::ROLES as $role) {
    $roles[$role] = get_string('role:' . $role, 'local_oriskill');
}
$customroles = team_service::custom_roles($creator);
foreach ($customroles as $role => $record) {
    $roles[$role] = format_string($record->name);
}
$hidden = fn(string $action, int $memberid = 0) =>
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
    html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]) .
    ($memberid ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'memberid', 'value' => $memberid]) : '');

echo $OUTPUT->header();
echo creator_service::space_start($creator, '/local/oriskill/team.php', get_string('menu:team', 'local_oriskill'));
echo html_writer::tag('p', get_string('team:intro', 'local_oriskill'));

// What each role may do.
$rolelist = '';
foreach (team_service::ROLES as $role) {
    $rolelist .= html_writer::tag('li', html_writer::tag('strong', $roles[$role]) . ' : ' .
        get_string('role:' . $role . '_desc', 'local_oriskill'));
}
echo html_writer::tag('ul', $rolelist, ['class' => 'small text-muted mb-4']);

// Team.
$owner = core_user::get_user($creator->userid);
$table = new html_table();
$table->attributes['class'] = 'table align-middle';
$table->head = [get_string('team:person', 'local_oriskill'), get_string('team:role', 'local_oriskill'),
    get_string('status'), ''];
$table->data[] = [
    fullname($owner) . html_writer::div(s($owner->email), 'small text-muted'),
    html_writer::span(get_string('role:owner', 'local_oriskill'), 'oriskill-badge is-good'),
    get_string('team:active', 'local_oriskill'),
    '',
];
foreach (team_service::members($creator) as $member) {
    $user = $member->userid ? core_user::get_user($member->userid) : null;
    $roleform = html_writer::tag('form', $hidden('setrole', (int) $member->id) .
        html_writer::select($roles, 'role', $member->role, false, ['class' => 'form-select form-select-sm',
            'onchange' => 'this.form.submit()', 'aria-label' => get_string('team:role', 'local_oriskill')]),
        ['method' => 'post', 'action' => $url->out(false), 'style' => 'max-width: 14rem']);
    if ($member->role === 'partner') {
        // Partners do not take the customer base away unless allowed.
        $roleform .= html_writer::tag('form', $hidden('setexport', (int) $member->id) .
            html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'canexport', 'value' => 1,
                'id' => 'export' . $member->id, 'class' => 'form-check-input', 'onchange' => 'this.form.submit()'] +
                ($member->canexport ? ['checked' => 'checked'] : [])) .
                html_writer::label(get_string('team:canexport', 'local_oriskill'), 'export' . $member->id, true,
                    ['class' => 'form-check-label small']), 'form-check mt-1'),
            ['method' => 'post', 'action' => $url->out(false)]);
    }
    $actions = '';
    if ($member->status === 'invited') {
        $actions .= html_writer::tag('form', $hidden('resend', (int) $member->id) .
            html_writer::tag('button', get_string('team:resend', 'local_oriskill'), ['type' => 'submit',
                'class' => 'btn btn-sm btn-link']), ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline']);
    }
    $actions .= html_writer::tag('form', $hidden('remove', (int) $member->id) .
        html_writer::tag('button', get_string($member->status === 'invited' ? 'team:cancelinvite' : 'team:remove',
            'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']),
        ['method' => 'post', 'action' => $url->out(false), 'class' => 'd-inline',
         'onsubmit' => 'return confirm(' . json_encode(get_string('team:remove_confirm', 'local_oriskill')) . ')']);
    $table->data[] = [
        ($user ? fullname($user) . html_writer::div(s($user->email), 'small text-muted') : s($member->email)),
        $roleform,
        $member->status === 'invited'
            ? html_writer::span(get_string('team:pending', 'local_oriskill'), 'oriskill-badge is-warn') .
                html_writer::div(get_string('team:invitedon', 'local_oriskill',
                    userdate($member->timecreated, get_string('strftimedate', 'langconfig'))), 'small text-muted')
            : get_string('team:since', 'local_oriskill', userdate($member->timeaccepted, get_string('strftimedate', 'langconfig'))),
        $actions,
    ];
}
echo html_writer::div(html_writer::table($table), 'oriskill-card mb-4');

// Invitation.
echo html_writer::start_div('oriskill-card');
echo html_writer::tag('h3', get_string('team:invite', 'local_oriskill'), ['class' => 'h5']);
echo html_writer::tag('form', $hidden('invite') .
    html_writer::div(
        html_writer::div(html_writer::tag('label', get_string('email'), ['for' => 'team-email', 'class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'email', 'name' => 'email', 'id' => 'team-email', 'required' => 'required',
                'class' => 'form-control']), 'col-md-6') .
        html_writer::div(html_writer::tag('label', get_string('team:role', 'local_oriskill'), ['for' => 'team-role',
                'class' => 'form-label']) .
            html_writer::select($roles, 'role', 'teaching', false, ['id' => 'team-role', 'class' => 'form-select']), 'col-md-4') .
        html_writer::div(html_writer::tag('button', get_string('team:send', 'local_oriskill'),
            ['type' => 'submit', 'class' => 'btn btn-primary w-100']), 'col-md-2 d-flex align-items-end'),
        'row g-3'),
    ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::tag('p', get_string('team:invite_help', 'local_oriskill', team_service::INVITATION_DAYS),
    ['class' => 'small text-muted mt-2 mb-0']);
echo html_writer::end_div();

// Roles made by the trainer: page by page, see or change; export; two-factor authentication.
$editing = $customroles['custom' . optional_param('editrole', 0, PARAM_INT)] ?? null;
$levels = ['' => get_string('customrole:none', 'local_oriskill'), 'view' => get_string('customrole:view', 'local_oriskill'),
    'change' => get_string('customrole:change', 'local_oriskill')];
$pagelabels = array_merge(...array_values(team_service::customisable_pages()));
$list = '';
foreach ($customroles as $role => $record) {
    $summary = [];
    foreach ($record->pagelist as $path => $level) {
        $summary[] = ($pagelabels[$path] ?? '?') .
            ($level === 'view' ? ' (' . get_string('customrole:view', 'local_oriskill') . ')' : '');
    }
    $list .= html_writer::div(
        html_writer::div(html_writer::tag('strong', format_string($record->name)) .
            html_writer::div(s(implode(', ', $summary)), 'small text-muted') .
            html_writer::div(($record->canexport ? get_string('customrole:canexport', 'local_oriskill') : '') .
                ($record->mfa ? ' · ' . get_string('customrole:mfa', 'local_oriskill') : ''), 'small text-muted')) .
        html_writer::div(html_writer::link(new moodle_url($url, ['editrole' => $record->id], 'customroles'), get_string('edit'),
            ['class' => 'btn btn-sm btn-outline-primary']) . ' ' .
            html_writer::tag('form', $hidden('deleterole') . html_writer::empty_tag('input', ['type' => 'hidden',
                'name' => 'roleid', 'value' => $record->id]) . html_writer::tag('button', get_string('delete'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']), ['method' => 'post',
                'action' => $url->out(false), 'class' => 'd-inline']), 'text-nowrap'),
        'd-flex justify-content-between align-items-start gap-3 py-2 border-bottom');
}
$rows = '';
foreach (team_service::customisable_pages() as $group => $pages) {
    $rows .= html_writer::tag('tr', html_writer::tag('th', s($group), ['colspan' => 2, 'class' => 'small text-uppercase text-muted pt-3']));
    foreach ($pages as $path => $label) {
        $id = 'page-' . basename($path, '.php');
        $rows .= html_writer::tag('tr', html_writer::tag('td', html_writer::label(s($label), $id)) .
            html_writer::tag('td', html_writer::select($levels, 'pages[' . basename($path, '.php') . ']',
                $editing->pagelist[$path] ?? '', false,
                ['class' => 'form-select form-select-sm', 'id' => $id])));
    }
}
$check = fn(string $name, string $label, bool $on) => html_writer::div(html_writer::empty_tag('input', ['type' => 'checkbox',
    'name' => $name, 'value' => 1, 'id' => 'role-' . $name, 'class' => 'form-check-input'] + ($on ? ['checked' => 'checked'] : [])) .
    html_writer::label($label, 'role-' . $name, true, ['class' => 'form-check-label']), 'form-check');
echo html_writer::div(
    html_writer::tag('h3', get_string('customrole:title', 'local_oriskill'), ['class' => 'h5']) .
    html_writer::tag('p', get_string('customrole:intro', 'local_oriskill'), ['class' => 'small text-muted']) .
    $list .
    html_writer::tag('form', $hidden('saverole') . html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'roleid',
        'value' => $editing->id ?? 0]) .
        html_writer::tag('h4', get_string($editing ? 'customrole:edit' : 'customrole:new', 'local_oriskill'), ['class' => 'h6 mt-3']) .
        html_writer::div(html_writer::label(get_string('name'), 'role-name', true, ['class' => 'form-label']) .
            html_writer::empty_tag('input', ['type' => 'text', 'name' => 'rolename', 'id' => 'role-name', 'required' => 'required',
                'maxlength' => 100, 'class' => 'form-control', 'value' => $editing ? $editing->name : '']), 'mb-2') .
        html_writer::tag('table', $rows, ['class' => 'table table-sm align-middle mb-2', 'style' => 'max-width: 36rem']) .
        $check('canexport', get_string('customrole:canexport', 'local_oriskill'), (bool) ($editing->canexport ?? false)) .
        $check('mfa', get_string('customrole:mfa', 'local_oriskill'), (bool) ($editing->mfa ?? false)) .
        html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-2']),
        ['method' => 'post', 'action' => $url->out(false)]),
    'oriskill-card mt-4', ['id' => 'customroles']);

echo creator_service::space_end();
echo $OUTPUT->footer();
