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
 * What the trainer's invoices state about them as the seller.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class billing_form extends \moodleform {

    /** VAT rates offered (French rates); 0 = not subject to VAT. */
    public const VAT_RATES = ['0', '20', '10', '5.5', '2.1'];

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'legalname', get_string('billing:legalname', 'local_oriskill'), ['size' => 50]);
        $mform->setType('legalname', PARAM_TEXT);
        $mform->addRule('legalname', null, 'required', null, 'client');
        $mform->addHelpButton('legalname', 'billing:legalname', 'local_oriskill');

        $mform->addElement('textarea', 'legaladdress', get_string('billing:legaladdress', 'local_oriskill'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('legaladdress', PARAM_TEXT);
        $mform->addRule('legaladdress', null, 'required', null, 'client');

        $mform->addElement('text', 'siret', get_string('billing:siret', 'local_oriskill'), ['size' => 20]);
        $mform->setType('siret', PARAM_TEXT);
        $mform->addRule('siret', null, 'required', null, 'client');

        $rates = [];
        foreach (self::VAT_RATES as $rate) {
            $rates[$rate] = $rate === '0' ? get_string('billing:novat', 'local_oriskill')
                : get_string('billing:vatrate_value', 'local_oriskill', format_float((float) $rate, 1, true, true));
        }
        $mform->addElement('select', 'vatrate', get_string('billing:vatrate', 'local_oriskill'), $rates);
        $mform->addHelpButton('vatrate', 'billing:vatrate', 'local_oriskill');

        $mform->addElement('text', 'vatnumber', get_string('billing:vatnumber', 'local_oriskill'), ['size' => 20]);
        $mform->setType('vatnumber', PARAM_TEXT);
        $mform->hideIf('vatnumber', 'vatrate', 'eq', '0');

        $mform->addElement('textarea', 'invoicenotes', get_string('billing:notes', 'local_oriskill'),
            ['rows' => 3, 'cols' => 50]);
        $mform->setType('invoicenotes', PARAM_TEXT);
        $mform->addHelpButton('invoicenotes', 'billing:notes', 'local_oriskill');

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!in_array((string) $data['vatrate'], self::VAT_RATES, true)) {
            $errors['vatrate'] = get_string('invaliddata', 'error');
        }
        if ((string) $data['vatrate'] !== '0' && trim((string) $data['vatnumber']) === '') {
            $errors['vatnumber'] = get_string('billing:vatnumberrequired', 'local_oriskill');
        }
        return $errors;
    }
}
