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

/**
 * Plugin settings. Credentials are entered in the Modern Commerce gateway settings.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_secureandpay', get_string('pluginname', 'local_secureandpay'));
    $settings->add(new admin_setting_configcheckbox('local_secureandpay/trialwithcard',
        get_string('trialwithcard', 'local_secureandpay'), get_string('trialwithcard_desc', 'local_secureandpay'), 1));
    $settings->add(new admin_setting_configcheckbox('local_secureandpay/oneclick',
        get_string('oneclick', 'local_secureandpay'), get_string('oneclick_desc', 'local_secureandpay'), 0));
    $ADMIN->add('localplugins', $settings);
    $ADMIN->add('localplugins', new admin_externalpage('local_secureandpay_plans',
        get_string('planpayments', 'local_secureandpay'), new moodle_url('/local/secureandpay/plans.php')));
}
