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
 * Privacy and cookies of a trainer: texts of the consents to marketing, cookie banner, measurement tools.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class privacy_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $str = fn(string $key) => get_string('privacy:' . $key, 'local_oriskill');

        $mform->addElement('header', 'consentshdr', $str('consents'));
        $mform->setExpanded('consentshdr');
        $mform->addElement('static', 'consentsintro', '', $str('consents_intro'));
        $mform->addElement('textarea', 'textdirect', $str('textdirect'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('textdirect', PARAM_TEXT);
        $mform->addRule('textdirect', null, 'required', null, 'client');
        $mform->addElement('advcheckbox', 'askpartners', $str('askpartners'), $str('askpartners_desc'));
        $mform->addElement('textarea', 'textpartners', $str('textpartners'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('textpartners', PARAM_TEXT);
        $mform->hideIf('textpartners', 'askpartners', 'notchecked');

        $mform->addElement('header', 'bannerhdr', $str('bannerhdr'));
        $mform->setExpanded('bannerhdr');
        $mform->addElement('static', 'bannerintro', '', $str('banner_intro'));
        $mform->addElement('advcheckbox', 'bannersite', $str('bannersite'), $str('bannersite_desc'));
        $mform->addElement('advcheckbox', 'bannerfunnels', $str('bannerfunnels'), $str('bannerfunnels_desc'));
        $mform->addElement('advcheckbox', 'autopopup', $str('autopopup'), $str('autopopup_desc'));
        $mform->addElement('select', 'position', $str('position'), ['bottom' => $str('position_bottom'),
            'top' => $str('position_top')]);
        $mform->addElement('textarea', 'bannertext', $str('bannertext'), ['rows' => 3, 'cols' => 70]);
        $mform->setType('bannertext', PARAM_TEXT);
        $mform->addElement('text', 'policyurl', $str('policyurl'), ['size' => 60, 'placeholder' => 'https://']);
        $mform->setType('policyurl', PARAM_RAW_TRIMMED);
        foreach (['background', 'textcolor', 'buttoncolor'] as $name) {
            $mform->addElement('text', $name, $str($name), ['size' => 9, 'placeholder' => '#7c3aed']);
            $mform->setType($name, PARAM_RAW_TRIMMED);
        }

        $mform->addElement('header', 'toolshdr', $str('tools'));
        $mform->setExpanded('toolshdr');
        $mform->addElement('static', 'toolsintro', '', $str('tools_intro'));
        $mform->addElement('text', 'gaid', $str('gaid'), ['size' => 20, 'placeholder' => 'G-XXXXXXXXXX']);
        $mform->setType('gaid', PARAM_RAW_TRIMMED);
        $mform->addElement('text', 'metapixel', $str('metapixel'), ['size' => 20, 'placeholder' => '123456789012345']);
        $mform->setType('metapixel', PARAM_RAW_TRIMMED);

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        foreach (['background', 'textcolor', 'buttoncolor'] as $name) {
            if (trim((string) ($data[$name] ?? '')) !== '' && !preg_match('/^#[0-9a-f]{6}$/i', trim($data[$name]))) {
                $errors[$name] = get_string('orderform:btncolor_help', 'local_oriskill');
            }
        }
        if (trim((string) ($data['policyurl'] ?? '')) !== '' && !preg_match('~^https?://\S+$~i', trim($data['policyurl']))) {
            $errors['policyurl'] = get_string('invalidurl', 'error');
        }
        if (trim((string) ($data['gaid'] ?? '')) !== '' && !preg_match('/^(G|UA|AW)-[A-Z0-9-]{4,20}$/i', trim($data['gaid']))) {
            $errors['gaid'] = get_string('privacy:gaid_invalid', 'local_oriskill');
        }
        if (trim((string) ($data['metapixel'] ?? '')) !== '' && !preg_match('/^\d{6,20}$/', trim($data['metapixel']))) {
            $errors['metapixel'] = get_string('privacy:metapixel_invalid', 'local_oriskill');
        }
        if (!empty($data['askpartners']) && trim((string) ($data['textpartners'] ?? '')) === '') {
            $errors['textpartners'] = get_string('required');
        }
        return $errors;
    }
}
