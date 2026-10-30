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
 * Choose one's password from the link of the access e-mail (valid a few days, usable once), then go to the course.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\access_mail;

$token = optional_param('t', '', PARAM_ALPHANUM);
$url = new moodle_url('/local/oriskill/setpassword.php', ['t' => $token]);
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('setpassword:title', 'local_oriskill'));
$record = access_mail::token($token);

$error = '';
if ($record && data_submitted() && confirm_sesskey()) {
    $password = (string) optional_param('password', '', PARAM_RAW);
    if ($password !== (string) optional_param('password2', '', PARAM_RAW)) {
        $error = get_string('setpassword:mismatch', 'local_oriskill');
    } else {
        try {
            $user = access_mail::use_token($record, $password);
            complete_user_login($user);
            $target = $record->courseid ? new moodle_url('/course/view.php', ['id' => $record->courseid]) : new moodle_url('/my/');
            redirect($target, get_string('setpassword:done', 'local_oriskill'), null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        }
    }
}

echo $OUTPUT->header();
echo html_writer::start_div('card mx-auto', ['style' => 'max-width: 28rem']);
echo html_writer::start_div('card-body');
echo html_writer::tag('h2', get_string('setpassword:title', 'local_oriskill'), ['class' => 'h4 mb-3']);
if (!$record) {
    echo html_writer::tag('p', get_string('setpassword:invalid', 'local_oriskill'));
    echo html_writer::link(new moodle_url('/login/forgot_password.php'), get_string('setpassword:forgot', 'local_oriskill'),
        ['class' => 'btn btn-primary']);
} else {
    $user = core_user::get_user($record->userid);
    echo html_writer::tag('p', get_string('setpassword:intro', 'local_oriskill', s($user->email)), ['class' => 'text-muted']);
    if ($error !== '') {
        echo $OUTPUT->notification($error, 'error');
    }
    $field = fn(string $name, string $label) => html_writer::div(
        html_writer::tag('label', $label, ['for' => 'sp-' . $name, 'class' => 'form-label']) .
        html_writer::empty_tag('input', ['type' => 'password', 'name' => $name, 'id' => 'sp-' . $name, 'required' => 'required',
            'autocomplete' => 'new-password', 'class' => 'form-control']), 'mb-3');
    echo html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        $field('password', get_string('newpassword')) . $field('password2', get_string('newpassword') . ' (' . get_string('again') . ')') .
        html_writer::div(print_password_policy(), 'small text-muted mb-3') .
        html_writer::tag('button', get_string('setpassword:save', 'local_oriskill'), ['type' => 'submit', 'class' => 'btn btn-primary w-100']),
        ['method' => 'post', 'action' => $url->out(false)]);
}
echo html_writer::end_div() . html_writer::end_div();
echo $OUTPUT->footer();
