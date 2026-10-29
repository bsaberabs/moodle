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

use local_oriskill\activecampaign_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The trainer's ActiveCampaign account and what buyers get there.
 *
 * Custom data: 'haskey' (a key is saved), 'lists' (id => name, once loaded).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activecampaign_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('advcheckbox', 'enabled', get_string('ac:enabled', 'local_oriskill'),
            get_string('ac:enabled_desc', 'local_oriskill'));

        $mform->addElement('text', 'apiurl', get_string('ac:apiurl', 'local_oriskill'),
            ['size' => 50, 'placeholder' => 'https://votre-compte.api-us1.com']);
        $mform->setType('apiurl', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('apiurl', 'ac:apiurl', 'local_oriskill');

        $mform->addElement('passwordunmask', 'apikey', get_string('ac:apikey', 'local_oriskill'), ['size' => 50]);
        $mform->setType('apikey', PARAM_RAW_TRIMMED);
        if (!empty($this->_customdata['haskey'])) {
            $mform->addElement('static', 'apikeykept', '', get_string('gw:keep', 'local_oriskill'));
        }

        $lists = $this->_customdata['lists'] ?? [];
        if ($lists) {
            $mform->addElement('select', 'listid', get_string('ac:list', 'local_oriskill'),
                [0 => get_string('ac:nolist', 'local_oriskill')] + $lists);
        } else {
            $mform->addElement('text', 'listid', get_string('ac:list', 'local_oriskill'), ['size' => 6]);
            $mform->addHelpButton('listid', 'ac:list', 'local_oriskill');
        }
        $mform->setType('listid', PARAM_INT);

        $mform->addElement('text', 'tags', get_string('ac:tags', 'local_oriskill'), ['size' => 50]);
        $mform->setType('tags', PARAM_TEXT);
        $mform->addHelpButton('tags', 'ac:tags', 'local_oriskill');

        $mform->addElement('advcheckbox', 'coursetags', get_string('ac:coursetags', 'local_oriskill'),
            get_string('ac:coursetags_desc', 'local_oriskill'));
        $mform->setDefault('coursetags', 1);

        $mform->addElement('text', 'unpaidtag', get_string('ac:unpaidtag', 'local_oriskill'), ['size' => 30]);
        $mform->setType('unpaidtag', PARAM_TEXT);
        $mform->addHelpButton('unpaidtag', 'ac:unpaidtag', 'local_oriskill');

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'testbutton', get_string('ac:test', 'local_oriskill')),
        ];
        $mform->addGroup($buttons, 'buttonar', '', ' ', false);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((!empty($data['enabled']) || trim((string) $data['apiurl']) !== '')
                && activecampaign_service::normalise_url((string) $data['apiurl']) === null) {
            $errors['apiurl'] = get_string('ac:badurl', 'local_oriskill');
        }
        if (!empty($data['enabled']) && empty($this->_customdata['haskey']) && trim((string) $data['apikey']) === '') {
            $errors['apikey'] = get_string('required');
        }
        return $errors;
    }
}
