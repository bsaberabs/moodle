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

    if ($oldversion < 2026103000) {
        // Affiliation: commission rate set by each trainer, affiliates, referred orders and commissions.
        $table = new xmldb_table('local_oriskill_creators');
        $field = new xmldb_field('affiliaterate', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0', 'sap_testmode');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        foreach (['local_oriskill_affiliates', 'local_oriskill_referrals', 'local_oriskill_commissions'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103000, 'local', 'oriskill');
    }
    if ($oldversion < 2026103005) {
        // Trainers' own Stripe and PayPal accounts.
        if (!$dbman->table_exists('local_oriskill_gateways')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_gateways');
        }
        upgrade_plugin_savepoint(true, 2026103005, 'local', 'oriskill');
    }
    if ($oldversion < 2026103008) {
        // Showcase appearance (colours, typeface) and own domain.
        $table = new xmldb_table('local_oriskill_creators');
        $previous = 'affiliaterate';
        foreach ([
            new xmldb_field('primarycolor', XMLDB_TYPE_CHAR, '7', null, null, null, null),
            new xmldb_field('accentcolor', XMLDB_TYPE_CHAR, '7', null, null, null, null),
            new xmldb_field('fontstyle', XMLDB_TYPE_CHAR, '20', null, null, null, null),
            new xmldb_field('customdomain', XMLDB_TYPE_CHAR, '255', null, null, null, null),
        ] as $field) {
            $field->setPrevious($previous);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $previous = $field->getName();
        }
        $index = new xmldb_index('customdomain', XMLDB_INDEX_NOTUNIQUE, ['customdomain']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026103008, 'local', 'oriskill');
    }
    if ($oldversion < 2026103009) {
        // Payment in instalments: offers per course and purchases being paid.
        foreach (['local_oriskill_instoffers', 'local_oriskill_instalments'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103009, 'local', 'oriskill');
    }
    if ($oldversion < 2026103010) {
        // Invoices and credit notes in the trainer's name.
        $table = new xmldb_table('local_oriskill_creators');
        $previous = 'customdomain';
        foreach ([
            new xmldb_field('legalname', XMLDB_TYPE_CHAR, '255', null, null, null, null),
            new xmldb_field('legaladdress', XMLDB_TYPE_TEXT, null, null, null, null, null),
            new xmldb_field('siret', XMLDB_TYPE_CHAR, '30', null, null, null, null),
            new xmldb_field('vatnumber', XMLDB_TYPE_CHAR, '30', null, null, null, null),
            new xmldb_field('vatrate', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null),
            new xmldb_field('invoicenotes', XMLDB_TYPE_TEXT, null, null, null, null, null),
        ] as $field) {
            $field->setPrevious($previous);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $previous = $field->getName();
        }
        if (!$dbman->table_exists('local_oriskill_invoices')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_invoices');
        }
        upgrade_plugin_savepoint(true, 2026103010, 'local', 'oriskill');
    }
    if ($oldversion < 2026103011) {
        // Collaborators of a trainer and the log of the trainer space.
        foreach (['local_oriskill_members', 'local_oriskill_log'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103011, 'local', 'oriskill');
    }
    if ($oldversion < 2026103013) {
        // Order forms and their offers.
        foreach (['local_oriskill_orderforms', 'local_oriskill_formoffers'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103013, 'local', 'oriskill');
    }
    if ($oldversion < 2026103014) {
        // ActiveCampaign: trainer accounts and tags per order form offer.
        if (!$dbman->table_exists('local_oriskill_activecampaign')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_activecampaign');
        }
        $field = new xmldb_field('actags', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'highlight');
        $table = new xmldb_table('local_oriskill_formoffers');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026103014, 'local', 'oriskill');
    }
    return true;
}
