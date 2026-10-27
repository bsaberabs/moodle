<?php
// This file is part of Moodle and is licensed under the
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
 * One-click marketing unsubscribe landing page (token-authenticated, no login).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Public one-click unsubscribe is authenticated by a signed token.
require(__DIR__ . '/../../config.php');

use local_moderncommerce\notifications\local\suppression;

$userid = optional_param('u', 0, PARAM_INT);
$email = optional_param('e', '', PARAM_EMAIL);
$token = optional_param('t', '', PARAM_ALPHANUMEXT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/unsubscribe.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_moderncommerce'));
$PAGE->set_heading(get_string('pluginname', 'local_moderncommerce'));

$valid = ($userid > 0) && suppression::verify_token($userid, $email, $token);
if ($valid) {
    suppression::suppress_all($userid, $email ?: null, $token);
}

echo $OUTPUT->header();
if ($valid) {
    echo $OUTPUT->notification(
        get_string('notify_unsubscribed', 'local_moderncommerce'),
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    echo $OUTPUT->notification(
        get_string('notify_unsubscribe_invalid', 'local_moderncommerce'),
        \core\output\notification::NOTIFY_ERROR
    );
}
echo $OUTPUT->footer();
