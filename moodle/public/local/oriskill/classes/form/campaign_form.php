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
 * An affiliate campaign of a trainer.
 *
 * Custom data: 'forms' (formid => title), 'affiliates' (affiliateid => name and e-mail).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campaign_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $str = fn(string $key, $a = null) => get_string('campaign:' . $key, 'local_oriskill', $a);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tab', 'campaigns');
        $mform->setType('tab', PARAM_ALPHA);

        $mform->addElement('text', 'name', $str('name'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('textarea', 'description', $str('description'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addElement('advcheckbox', 'enabled', $str('enabled'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('header', 'commissionhdr', $str('commissionhdr'));
        $mform->setExpanded('commissionhdr');
        $mform->addElement('select', 'commtype', $str('commtype'), ['percent' => $str('commtype_percent'),
            'fixed' => $str('commtype_fixed')]);
        $mform->addElement('text', 'commission', $str('commission'), ['size' => 8]);
        $mform->setType('commission', PARAM_RAW_TRIMMED);
        $mform->addRule('commission', null, 'required', null, 'client');
        $mform->addElement('select', 'taxbase', $str('taxbase'), ['excl' => $str('taxbase_excl'), 'incl' => $str('taxbase_incl')]);
        $mform->hideIf('taxbase', 'commtype', 'eq', 'fixed');
        $mform->addElement('text', 'level2', $str('level2'), ['size' => 6]);
        $mform->setType('level2', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('level2', 'campaign:level2', 'local_oriskill');
        $mform->addElement('static', 'onepersale', '', $str('onepersale'));

        $mform->addElement('header', 'ruleshdr', $str('ruleshdr'));
        $mform->setExpanded('ruleshdr');
        $mform->addElement('select', 'formid', $str('formid'), [0 => $str('allforms')] + $this->_customdata['forms']);
        $mform->addElement('text', 'trackingdays', $str('trackingdays'), ['size' => 4]);
        $mform->setType('trackingdays', PARAM_INT);
        $mform->setDefault('trackingdays', 30);
        $mform->addHelpButton('trackingdays', 'campaign:trackingdays', 'local_oriskill');
        $mform->addElement('text', 'guaranteedays', $str('guaranteedays'), ['size' => 4]);
        $mform->setType('guaranteedays', PARAM_INT);
        $mform->setDefault('guaranteedays', 14);
        $mform->addHelpButton('guaranteedays', 'campaign:guaranteedays', 'local_oriskill');
        $mform->addElement('advcheckbox', 'newonly', $str('newonly'), $str('newonly_desc'));
        $mform->addElement('select', 'visibility', $str('visibility'), ['public' => $str('visibility_public'),
            'private' => $str('visibility_private')]);
        $mform->addElement('autocomplete', 'affiliateids', $str('affiliates'), $this->_customdata['affiliates'],
            ['multiple' => true, 'noselectionstring' => $str('noaffiliates')]);
        $mform->setType('affiliateids', PARAM_INT);
        $mform->hideIf('affiliateids', 'visibility', 'eq', 'public');

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $amount = unformat_float((string) $data['commission'], true);
        if (!$amount || $amount <= 0 || ($data['commtype'] === 'percent' && $amount > 100)) {
            $errors['commission'] = get_string('campaign:badcommission', 'local_oriskill');
        }
        $level2 = trim((string) ($data['level2'] ?? ''));
        if ($level2 !== '' && (($l = unformat_float($level2, true)) === false || $l < 0 || $l > 100)) {
            $errors['level2'] = get_string('campaign:badlevel2', 'local_oriskill');
        }
        if ((int) $data['trackingdays'] < 1 || (int) $data['trackingdays'] > 365) {
            $errors['trackingdays'] = get_string('notifs:baddays', 'local_oriskill');
        }
        if ((int) $data['guaranteedays'] < 0 || (int) $data['guaranteedays'] > 365) {
            $errors['guaranteedays'] = get_string('notifs:baddays', 'local_oriskill');
        }
        if ($data['visibility'] === 'private' && empty($data['affiliateids'])) {
            $errors['affiliateids'] = get_string('campaign:noaffiliates', 'local_oriskill');
        }
        return $errors;
    }
}
