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
 * A trainer's own Stripe or PayPal account.
 *
 * Custom data: gateway (stripe or paypal), account (saved record or null).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $gateway = $this->_customdata['gateway'];
        $account = $this->_customdata['account'] ?? null;

        $mform->addElement('hidden', 'gateway', $gateway);
        $mform->setType('gateway', PARAM_ALPHA);

        $mform->addElement('advcheckbox', 'enabled', get_string("gw:$gateway:enabled", 'local_oriskill'),
            get_string('gw:enabled_desc', 'local_oriskill'));
        $mform->addElement('advcheckbox', 'testmode', get_string("gw:$gateway:testmode", 'local_oriskill'),
            get_string("gw:$gateway:testmode_desc", 'local_oriskill'));
        $mform->setDefault('testmode', 1);

        $mform->addElement('text', 'publickey', get_string("gw:$gateway:publickey", 'local_oriskill'), ['size' => 50]);
        $mform->setType('publickey', PARAM_RAW_TRIMMED);

        foreach (['secretkey', 'webhooksecret'] as $field) {
            $element = $gateway === 'paypal' && $field === 'webhooksecret' ? 'text' : 'passwordunmask';
            $mform->addElement($element, $field, get_string("gw:$gateway:$field", 'local_oriskill'), ['size' => 50]);
            $mform->setType($field, PARAM_RAW_TRIMMED);
            if (!empty($account->$field)) {
                $mform->addElement('static', $field . 'hint', '', get_string('gw:keep', 'local_oriskill'));
            }
        }
        $this->add_action_buttons(false);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $gateway = $this->_customdata['gateway'];
        $account = $this->_customdata['account'] ?? null;
        if (empty($data['enabled'])) {
            return $errors;
        }
        // Prefixes of the keys (Stripe: pk_/sk_ or rk_/whsec_, test or live matching the mode).
        $live = empty($data['testmode']);
        $checks = $gateway === 'stripe' ? [
            'publickey' => '/^pk_' . ($live ? 'live' : 'test') . '_/',
            'secretkey' => '/^(sk|rk)_' . ($live ? 'live' : 'test') . '_/',
            'webhooksecret' => '/^whsec_/',
        ] : ['publickey' => '/^\S{10,}$/', 'secretkey' => '/^\S{10,}$/', 'webhooksecret' => '/^\S{5,}$/'];
        foreach ($checks as $field => $pattern) {
            $value = trim((string) ($data[$field] ?? ''));
            // Saved secrets are kept when left empty; Stripe Checkout does not need the publishable key.
            if ($value === '' && ($field === 'publickey' ? $gateway === 'stripe' : !empty($account->$field))) {
                continue;
            }
            if ($value === '') {
                $errors[$field] = get_string('required');
            } else if (!preg_match($pattern, $value)) {
                $errors[$field] = get_string("gw:$gateway:{$field}_invalid", 'local_oriskill');
            }
        }
        return $errors;
    }
}
