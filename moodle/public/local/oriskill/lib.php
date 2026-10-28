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
 * Library callbacks.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Serve trainer logos. They are public: shown on showcases open to visitors.
 *
 * @param stdClass $course Course.
 * @param stdClass $cm Course module.
 * @param context $context Context.
 * @param string $filearea File area.
 * @param array $args Arguments.
 * @param bool $forcedownload Force download.
 * @param array $options Options.
 * @return bool False if the file is not found.
 */
function local_oriskill_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'logo') {
        return false;
    }
    $creatorid = (int) array_shift($args);
    if (!$DB->record_exists('local_oriskill_creators', ['id' => $creatorid, 'status' => 'active'])) {
        return false;
    }
    $file = get_file_storage()->get_file($context->id, 'local_oriskill', 'logo', $creatorid, '/', array_pop($args));
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

/**
 * For a visitor who is not logged in: present the page and offer to sign up or log in, then stop.
 *
 * The login is required on the whole site, so without this page the visitor would land on the bare login form
 * without knowing what they were asking for. The page is remembered and reopened after the login, or after
 * the e-mail confirmation of a new account (Moodle keeps it with the account while it is being confirmed).
 *
 * @param moodle_url $url This page.
 * @param string $intro Presentation of the page (HTML).
 */
function local_oriskill_visitor_page(moodle_url $url, string $intro): void {
    global $CFG, $OUTPUT, $SESSION;
    if (isloggedin() && !isguestuser()) {
        return;
    }
    require_once($CFG->libdir . '/authlib.php');
    $SESSION->wantsurl = $url->out(false);
    echo $OUTPUT->header();
    echo $intro;
    echo html_writer::tag('p', get_string('visitor_hint', 'local_oriskill'));
    $buttons = '';
    if (signup_is_enabled()) {
        $buttons .= html_writer::link(new moodle_url('/login/signup.php'), get_string('visitor_signup', 'local_oriskill'),
            ['class' => 'btn btn-primary']) . ' ';
    }
    $buttons .= html_writer::link(get_login_url(), get_string('visitor_haveaccount', 'local_oriskill'),
        ['class' => 'btn btn-outline-secondary']);
    echo html_writer::tag('p', $buttons);
    echo $OUTPUT->footer();
    exit;
}
