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
 * Admin entry: trainer applications and spaces.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('courses', new admin_externalpage('local_oriskill_manage',
    get_string('managecreators', 'local_oriskill'), new moodle_url('/local/oriskill/manage.php'),
    'local/oriskill:managecreators'));
$ADMIN->add('courses', new admin_externalpage('local_oriskill_commissions',
    get_string('affiliatecommissions', 'local_oriskill'), new moodle_url('/local/oriskill/commissions.php'),
    'local/oriskill:manageaffiliates'));

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_oriskill', get_string('pluginname', 'local_oriskill'));
    $settings->add(new admin_setting_configtext('local_oriskill/creatorplancode',
        get_string('creatorplancode', 'local_oriskill'), get_string('creatorplancode_desc', 'local_oriskill'),
        '', PARAM_ALPHANUMEXT));

    $settings->add(new admin_setting_heading('local_oriskill/affiliation',
        get_string('affiliation', 'local_oriskill'), ''));
    $settings->add(new admin_setting_configcheckbox('local_oriskill/affiliateenabled',
        get_string('affiliateenabled', 'local_oriskill'), get_string('affiliateenabled_desc', 'local_oriskill'), 1));
    $settings->add(new admin_setting_configtext('local_oriskill/platformrate',
        get_string('platformrate', 'local_oriskill'), get_string('platformrate_desc', 'local_oriskill'),
        '20', PARAM_FLOAT, 5));
    $settings->add(new admin_setting_configtext('local_oriskill/cookiedays',
        get_string('cookiedays', 'local_oriskill'), get_string('cookiedays_desc', 'local_oriskill'),
        '30', PARAM_INT, 5));
    $settings->add(new admin_setting_configtext('local_oriskill/validationdays',
        get_string('validationdays', 'local_oriskill'), get_string('validationdays_desc', 'local_oriskill'),
        '14', PARAM_INT, 5));
    $ADMIN->add('localplugins', $settings);
}
