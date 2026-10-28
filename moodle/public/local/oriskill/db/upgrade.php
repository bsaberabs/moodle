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
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade hook.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_local_oriskill_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026102901) {
        // Each trainer is paid into their own SecureAndPay account.
        $table = new xmldb_table('local_oriskill_creators');
        $fields = [
            new xmldb_field('sap_siteid', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'status'),
            new xmldb_field('sap_key', XMLDB_TYPE_TEXT, null, null, null, null, null, 'sap_siteid'),
            new xmldb_field('sap_testmode', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'sap_key'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026102901, 'local', 'oriskill');
    }
    return true;
}
