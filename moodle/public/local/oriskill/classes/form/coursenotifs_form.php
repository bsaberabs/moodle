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

namespace local_oriskill\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_oriskill\course_notifications;

/**
 * Notifications of a course: module opened, progress reminder, login reminder.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursenotifs_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'notifs');
        $mform->setType('action', PARAM_ALPHA);

        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $hours[$h] = sprintf('%02d:00', $h);
        }
        foreach (course_notifications::KINDS as $kind) {
            $mform->addElement('header', 'h_' . $kind, get_string('notifs:' . $kind, 'local_oriskill'));
            $mform->setExpanded('h_' . $kind, false);
            $mform->addElement('advcheckbox', 'enabled_' . $kind, get_string('notifs:enabled', 'local_oriskill'),
                get_string('notifs:' . $kind . '_desc', 'local_oriskill'));
            if ($kind === 'unlock') {
                $mform->addElement('select', 'hour_' . $kind, get_string('notifs:hour', 'local_oriskill'), $hours);
                $mform->addHelpButton('hour_' . $kind, 'notifs:hour', 'local_oriskill');
            } else {
                $mform->addElement('text', 'days_' . $kind, get_string('notifs:days_' . $kind, 'local_oriskill'), ['size' => 4]);
                $mform->setType('days_' . $kind, PARAM_INT);
                $mform->addRule('days_' . $kind, null, 'numeric', null, 'client');
            }
            $mform->addElement('text', 'subject_' . $kind, get_string('subject'), ['size' => 60]);
            $mform->setType('subject_' . $kind, PARAM_TEXT);
            $mform->addRule('subject_' . $kind, null, 'required', null, 'client');
            $mform->addElement('editor', 'body_' . $kind, get_string('accessmail:body', 'local_oriskill'), ['rows' => 8],
                ['maxfiles' => 0, 'noclean' => false]);
            $mform->setType('body_' . $kind, PARAM_RAW);
            $mform->addRule('body_' . $kind, null, 'required', null, 'client');
        }
        $mform->addElement('header', 'h_variables', get_string('notifs:variables', 'local_oriskill'));
        $mform->addElement('static', 'placeholders', '', get_string('notifs:placeholders', 'local_oriskill',
            implode(' ', array_map(fn($p) => '<code>' . $p . '</code>', course_notifications::PLACEHOLDERS))));

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'testbutton', get_string('notifs:test', 'local_oriskill')),
        ];
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
        $mform->closeHeaderBefore('buttonar');
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['progress', 'inactive'] as $kind) {
            if (!empty($data['enabled_' . $kind]) && ((int) $data['days_' . $kind] < 1 || (int) $data['days_' . $kind] > 365)) {
                $errors['days_' . $kind] = get_string('notifs:baddays', 'local_oriskill');
            }
        }
        return $errors;
    }
}
