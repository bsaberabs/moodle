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

use local_oriskill\coupon_service;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * A promo code of the trainer's order forms.
 *
 * Custom data: 'creator', 'couponid' (0 for a new one), 'forms' (formid => title).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coupon_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['couponid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'code', get_string('coupon:code', 'local_oriskill'), ['size' => 20]);
        $mform->setType('code', PARAM_RAW_TRIMMED);
        $mform->addRule('code', null, 'required', null, 'client');
        $mform->addHelpButton('code', 'coupon:code', 'local_oriskill');

        $mform->addElement('select', 'type', get_string('coupon:type', 'local_oriskill'), [
            'percent' => get_string('coupon:percent', 'local_oriskill'),
            'amount' => get_string('coupon:amount', 'local_oriskill'),
        ]);
        $mform->addElement('text', 'value', get_string('coupon:value', 'local_oriskill'), ['size' => 8]);
        $mform->setType('value', PARAM_RAW_TRIMMED);
        $mform->addRule('value', null, 'required', null, 'client');

        $mform->addElement('select', 'formid', get_string('coupon:form', 'local_oriskill'),
            [0 => get_string('coupon:allforms', 'local_oriskill')] + $this->_customdata['forms']);
        $mform->addElement('advcheckbox', 'instalments', get_string('coupon:instalments', 'local_oriskill'),
            get_string('coupon:instalments_desc', 'local_oriskill'));
        $mform->setDefault('instalments', 1);

        $mform->addElement('text', 'maxuses', get_string('coupon:maxuses', 'local_oriskill'), ['size' => 6]);
        $mform->setType('maxuses', PARAM_INT);
        $mform->setDefault('maxuses', 0);
        $mform->addHelpButton('maxuses', 'coupon:maxuses', 'local_oriskill');

        $mform->addElement('date_time_selector', 'validfrom', get_string('coupon:validfrom', 'local_oriskill'),
            ['optional' => true]);
        $mform->addElement('date_time_selector', 'validuntil', get_string('coupon:validuntil', 'local_oriskill'),
            ['optional' => true]);

        $mform->addElement('advcheckbox', 'active', get_string('coupon:active', 'local_oriskill'));
        $mform->setDefault('active', 1);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $code = coupon_service::normalise((string) $data['code']);
        if ($code === null) {
            $errors['code'] = get_string('coupon:badcode', 'local_oriskill');
        } else if (coupon_service::taken($this->_customdata['creator'], $code, (int) $data['id'])) {
            $errors['code'] = get_string('coupon:taken', 'local_oriskill');
        }
        $value = unformat_float((string) $data['value'], true);
        if (!$value || $value <= 0 || ($data['type'] === 'percent' && $value >= 100)) {
            $errors['value'] = get_string('coupon:badvalue', 'local_oriskill');
        }
        if (!empty($data['validfrom']) && !empty($data['validuntil']) && $data['validuntil'] <= $data['validfrom']) {
            $errors['validuntil'] = get_string('coupon:baddates', 'local_oriskill');
        }
        return $errors;
    }
}
