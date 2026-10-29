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
 * Upgrade steps.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade hook.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_local_secureandpay_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026102801) {
        // Stop asking for a webhook secret, which SecureAndPay does not use.
        \local_secureandpay\gateway::register();
        upgrade_plugin_savepoint(true, 2026102801, 'local', 'secureandpay');
    }

    if ($oldversion < 2026102901) {
        // Card tokens are now kept per merchant account: earlier ones were all saved on the platform's.
        $siteid = preg_replace('/[^A-Za-z0-9]/', '', \local_secureandpay\gateway::platform_merchant()->siteid);
        if ($siteid !== '') {
            $DB->set_field('user_preferences', 'name', 'local_secureandpay_card_' . $siteid,
                ['name' => 'local_secureandpay_card']);
        } else {
            $DB->delete_records('user_preferences', ['name' => 'local_secureandpay_card']);
        }
        upgrade_plugin_savepoint(true, 2026102901, 'local', 'secureandpay');
    }

    if ($oldversion < 2026102906) {
        // Refunds asked of SecureAndPay from the site.
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_secureandpay_refunds')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_secureandpay_refunds');
        }
        upgrade_plugin_savepoint(true, 2026102906, 'local', 'secureandpay');
    }
    return true;
}
