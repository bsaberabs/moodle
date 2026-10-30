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

/**
 * The access e-mail of a course: when it goes out, from whom, and what it says.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accessmail_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'accessmail');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('advcheckbox', 'enabled', get_string('accessmail:enabled', 'local_oriskill'),
            get_string('accessmail:enabled_desc', 'local_oriskill'));
        $triggers = [];
        foreach (\local_oriskill\access_mail::TRIGGERS as $trigger) {
            $triggers[] = $mform->createElement('advcheckbox', 'on' . $trigger, '',
                get_string('accessmail:on' . $trigger, 'local_oriskill'));
        }
        $mform->addGroup($triggers, 'triggers', get_string('accessmail:triggers', 'local_oriskill'), '<br>', false);
        $mform->hideIf('triggers', 'enabled', 'notchecked');

        $mform->addElement('text', 'sendername', get_string('accessmail:sendername', 'local_oriskill'), ['size' => 40]);
        $mform->setType('sendername', PARAM_TEXT);
        $mform->addHelpButton('sendername', 'accessmail:sendername', 'local_oriskill');
        $mform->addElement('text', 'subject', get_string('subject'), ['size' => 60]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');
        $mform->addElement('editor', 'body', get_string('accessmail:body', 'local_oriskill'), ['rows' => 12],
            ['maxfiles' => 0, 'noclean' => false]);
        $mform->setType('body', PARAM_RAW);
        $mform->addRule('body', null, 'required', null, 'client');
        $mform->addElement('static', 'placeholders', '', get_string('accessmail:placeholders', 'local_oriskill',
            implode(' ', array_map(fn($p) => '<code>' . $p . '</code>', \local_oriskill\access_mail::PLACEHOLDERS))));

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'testbutton', get_string('accessmail:test', 'local_oriskill')),
        ];
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
    }
}
