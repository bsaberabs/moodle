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
 * Trainer's SecureAndPay account. Custom data: 'haskey' (a key is already saved).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payment_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $haskey = !empty($this->_customdata['haskey']);

        $mform->addElement('text', 'siteid', get_string('field:sapsiteid', 'local_oriskill'), ['size' => 20]);
        $mform->setType('siteid', PARAM_ALPHANUMEXT);
        $mform->addRule('siteid', null, 'required', null, 'client');

        $mform->addElement('passwordunmask', 'key', get_string('field:sapkey', 'local_oriskill'), ['size' => 50]);
        $mform->setType('key', PARAM_RAW_TRIMMED);
        if ($haskey) {
            $mform->addElement('static', 'keyhint', '', get_string('field:sapkey_keep', 'local_oriskill'));
        } else {
            $mform->addRule('key', null, 'required', null, 'client');
        }

        $mform->addElement('advcheckbox', 'testmode', get_string('field:saptestmode', 'local_oriskill'),
            get_string('field:saptestmode_desc', 'local_oriskill'));
        $mform->setDefault('testmode', 1);

        $this->add_action_buttons(false);
    }
}
