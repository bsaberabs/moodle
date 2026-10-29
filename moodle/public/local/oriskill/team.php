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
                required_param('role', PARAM_ALPHA));
            $message = get_string('team:invited', 'local_oriskill', s($member->email));
        } else {
            $member = $DB->get_record('local_oriskill_members', ['id' => required_param('memberid', PARAM_INT),
                'creatorid' => $creator->id], '*', MUST_EXIST);
            if ($action === 'setrole') {
                team_service::set_role($creator, $member, required_param('role', PARAM_ALPHA));
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

echo creator_service::space_end();
echo $OUTPUT->footer();
