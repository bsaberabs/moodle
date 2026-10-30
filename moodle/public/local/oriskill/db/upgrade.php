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
    if ($oldversion < 2026103016) {
        // Promo codes of the order forms.
        if (!$dbman->table_exists('local_oriskill_coupons')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_coupons');
        }
        upgrade_plugin_savepoint(true, 2026103016, 'local', 'oriskill');
    }
    if ($oldversion < 2026103017) {
        // Webhooks of the trainers.
        if (!$dbman->table_exists('local_oriskill_webhooks')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_webhooks');
        }
        upgrade_plugin_savepoint(true, 2026103017, 'local', 'oriskill');
    }
    if ($oldversion < 2026103018) {
        // Order forms: extra at the order step (order bump) and offer after payment (upsell).
        $table = new xmldb_table('local_oriskill_orderforms');
        $previous = 'termsrequired';
        foreach ([
            new xmldb_field('bumplabel', XMLDB_TYPE_CHAR, '255', null, null, null, null),
            new xmldb_field('bumptext', XMLDB_TYPE_TEXT, null, null, null, null, null),
            new xmldb_field('bumpcourseids', XMLDB_TYPE_CHAR, '255', null, null, null, null),
            new xmldb_field('bumpprice', XMLDB_TYPE_NUMBER, '20, 2', null, XMLDB_NOTNULL, null, '0'),
            new xmldb_field('upselllabel', XMLDB_TYPE_CHAR, '255', null, null, null, null),
            new xmldb_field('upselltext', XMLDB_TYPE_TEXT, null, null, null, null, null),
            new xmldb_field('upsellcourseids', XMLDB_TYPE_CHAR, '255', null, null, null, null),
            new xmldb_field('upsellprice', XMLDB_TYPE_NUMBER, '20, 2', null, XMLDB_NOTNULL, null, '0'),
        ] as $field) {
            $field->setPrevious($previous);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $previous = $field->getName();
        }
        upgrade_plugin_savepoint(true, 2026103018, 'local', 'oriskill');
    }
    if ($oldversion < 2026103019) {
        // Visits of the order forms.
        if (!$dbman->table_exists('local_oriskill_formstats')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_formstats');
        }
        upgrade_plugin_savepoint(true, 2026103019, 'local', 'oriskill');
    }
    if ($oldversion < 2026103020) {
        // Capture pages and their sign-ups.
        foreach (['local_oriskill_capturepages', 'local_oriskill_leads'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103020, 'local', 'oriskill');
    }
    if ($oldversion < 2026103021) {
        // Offers sold as a monthly subscription.
        $table = new xmldb_table('local_oriskill_formoffers');
        $field = new xmldb_field('subprice', XMLDB_TYPE_NUMBER, '20, 2', null, XMLDB_NOTNULL, null, '0', 'actags');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026103021, 'local', 'oriskill');
    }
    if ($oldversion < 2026103023) {
        // Payment in instalments with a first instalment of its own (a deposit).
        $field = new xmldb_field('instalfirst', XMLDB_TYPE_NUMBER, '20, 2', null, XMLDB_NOTNULL, null, '0', 'subprice');
        $table = new xmldb_table('local_oriskill_formoffers');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('firstamount', XMLDB_TYPE_NUMBER, '20, 2', null, XMLDB_NOTNULL, null, '0', 'amount');
        $table = new xmldb_table('local_oriskill_instalments');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026103023, 'local', 'oriskill');
    }
    if ($oldversion < 2026103024) {
        // Trainers (teachers of their courses) may log in as one of their learners, limited to that course.
        require_once(__DIR__ . '/install.php');
        local_oriskill_allow_trainer_loginas();
        upgrade_plugin_savepoint(true, 2026103024, 'local', 'oriskill');
    }
    if ($oldversion < 2026103025) {
        // Unpaid cases, and missing instalments paid outside SecureAndPay's schedule.
        $field = new xmldb_field('extrapaid', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0', 'paid');
        $table = new xmldb_table('local_oriskill_instalments');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        if (!$dbman->table_exists('local_oriskill_unpaid')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_unpaid');
        }
        // Purchases already unpaid get their open case.
        foreach ($DB->get_records_select('local_oriskill_instalments', "status IN ('late', 'stopped', 'suspended')") as $record) {
            \local_oriskill\unpaid_service::open($record, $record->status === 'stopped' ? 'stopped' : 'failed');
        }
        upgrade_plugin_savepoint(true, 2026103025, 'local', 'oriskill');
    }
    if ($oldversion < 2026103026) {
        // Abandoned order forms: the prospects and the reminder sequence of each form.
        $table = new xmldb_table('local_oriskill_orderforms');
        foreach ([new xmldb_field('abandonon', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'upsellprice'),
                new xmldb_field('abandonsteps', XMLDB_TYPE_TEXT, null, null, null, null, null, 'abandonon')] as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        if (!$dbman->table_exists('local_oriskill_abandons')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_abandons');
        }
        // Orders already placed on the forms, oldest first: unpaid ones make prospects, paid ones recover them.
        $orders = $DB->get_recordset_select('local_moderncommerce_orders', $DB->sql_like('notes', ':pattern'),
            ['pattern' => '%"orderform":%'], 'timecreated ASC, id ASC');
        foreach ($orders as $order) {
            $notes = json_decode((string) $order->notes, true) ?: [];
            $form = !empty($notes['orderform']) && empty($notes['upsellof'])
                ? $DB->get_record('local_oriskill_orderforms', ['id' => (int) $notes['orderform']]) : null;
            if (!$form) {
                continue;
            }
            \local_oriskill\abandon_service::checkout($form, $order);
            $DB->set_field('local_oriskill_abandons', 'timecreated', $order->timecreated,
                ['formid' => $form->id, 'userid' => $order->userid, 'state' => 'open']);
            if (in_array($order->status, ['paid', 'completed'], true)) {
                \local_oriskill\abandon_service::order_paid((int) $order->id);
            }
        }
        $orders->close();
        upgrade_plugin_savepoint(true, 2026103026, 'local', 'oriskill');
    }
    if ($oldversion < 2026103027) {
        // Abandon reminders actually sent, apart from those skipped.
        $field = new xmldb_field('sent', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'step');
        $table = new xmldb_table('local_oriskill_abandons');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026103027, 'local', 'oriskill');
    }
    if ($oldversion < 2026103028) {
        // Invoice e-mailed to the buyer with the PDF attached.
        $table = new xmldb_table('local_oriskill_creators');
        foreach ([new xmldb_field('invoicemail', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'invoicenotes'),
                new xmldb_field('invoicemailsubject', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'invoicemail'),
                new xmldb_field('invoicemailbody', XMLDB_TYPE_TEXT, null, null, null, null, null, 'invoicemailsubject')] as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026103028, 'local', 'oriskill');
    }
    if ($oldversion < 2026103029) {
        // Quotes in the trainer's name.
        if (!$dbman->table_exists('local_oriskill_quotes')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_quotes');
        }
        upgrade_plugin_savepoint(true, 2026103029, 'local', 'oriskill');
    }
    if ($oldversion < 2026103030) {
        // Accounting, partner and coach roles: export permission and coaches' learners.
        $field = new xmldb_field('canexport', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'role');
        $table = new xmldb_table('local_oriskill_members');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        if (!$dbman->table_exists('local_oriskill_coachees')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_coachees');
        }
        upgrade_plugin_savepoint(true, 2026103030, 'local', 'oriskill');
    }
    if ($oldversion < 2026103032) {
        // History of the webhook deliveries.
        if (!$dbman->table_exists('local_oriskill_webhooksent')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_webhooksent');
        }
        upgrade_plugin_savepoint(true, 2026103032, 'local', 'oriskill');
    }
    if ($oldversion < 2026103033) {
        // Time spent in the courses (CPF).
        foreach (['local_oriskill_studytime', 'local_oriskill_studymodes'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103033, 'local', 'oriskill');
    }
    if ($oldversion < 2026103034) {
        // CRM of the trainers, filled in the background from their learners, buyers and sign-ups.
        foreach (['local_oriskill_contacts', 'local_oriskill_contacttags'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        foreach ($DB->get_fieldset('local_oriskill_creators', 'id') as $creatorid) {
            $task = new \local_oriskill\task\contacts_backfill();
            $task->set_custom_data(['creatorid' => (int) $creatorid]);
            \core\task\manager::queue_adhoc_task($task, true);
        }
        upgrade_plugin_savepoint(true, 2026103034, 'local', 'oriskill');
    }
    if ($oldversion < 2026103035) {
        // Imports of contacts from CSV files.
        if (!$dbman->table_exists('local_oriskill_imports')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_imports');
        }
        upgrade_plugin_savepoint(true, 2026103035, 'local', 'oriskill');
    }
    if ($oldversion < 2026103036) {
        // Saved segments of the CRM.
        if (!$dbman->table_exists('local_oriskill_segments')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_segments');
        }
        upgrade_plugin_savepoint(true, 2026103036, 'local', 'oriskill');
    }
    if ($oldversion < 2026103037) {
        // E-mails sent to people, for the CRM.
        if (!$dbman->table_exists('local_oriskill_emails')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_emails');
        }
        upgrade_plugin_savepoint(true, 2026103037, 'local', 'oriskill');
    }
    if ($oldversion < 2026103038) {
        // Time and progress brought from the previous platform.
        if (!$dbman->table_exists('local_oriskill_priorprogress')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_priorprogress');
        }
        upgrade_plugin_savepoint(true, 2026103038, 'local', 'oriskill');
    }
    if ($oldversion < 2026103039) {
        // Numbering of invoices and credit notes chosen by the trainer.
        $table = new xmldb_table('local_oriskill_creators');
        $fields = [
            new xmldb_field('invoiceprefix', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'invoicemailbody'),
            new xmldb_field('invoicedigits', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '6', 'invoiceprefix'),
            new xmldb_field('invoicestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'invoicedigits'),
            new xmldb_field('creditprefix', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'invoicestart'),
            new xmldb_field('creditdigits', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '6', 'creditprefix'),
            new xmldb_field('creditstart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'creditdigits'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026103039, 'local', 'oriskill');
    }
    if ($oldversion < 2026103040) {
        // Unpaid instalments allowed before the access is cut, per offer (1 = as before).
        foreach ([['local_oriskill_instalments', 'cutafter', 'count'], ['local_oriskill_instoffers', 'cut', 'total'],
                ['local_oriskill_formoffers', 'instalcut', 'instalfirst']] as [$tablename, $fieldname, $after]) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field($fieldname, XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', $after);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026103040, 'local', 'oriskill');
    }
    if ($oldversion < 2026103041) {
        // Groups given by an order form offer.
        $table = new xmldb_table('local_oriskill_formoffers');
        $field = new xmldb_field('groupids', XMLDB_TYPE_TEXT, null, null, null, null, null, 'instalcut');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026103041, 'local', 'oriskill');
    }
    if ($oldversion < 2026103042) {
        // Access e-mail of each course, written by its trainer, with a link to choose a password.
        foreach (['local_oriskill_accessmails', 'local_oriskill_accesssent', 'local_oriskill_pwtokens'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103042, 'local', 'oriskill');
    }
    if ($oldversion < 2026103043) {
        // A trainer's own sending server.
        if (!$dbman->table_exists('local_oriskill_smtp')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', 'local_oriskill_smtp');
        }
        upgrade_plugin_savepoint(true, 2026103043, 'local', 'oriskill');
    }
    if ($oldversion < 2026103044) {
        // Several domains per trainer: the domain each had becomes their primary domain.
        foreach (['local_oriskill_domains', 'local_oriskill_coursedomains'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        foreach (['local_oriskill_orderforms', 'local_oriskill_capturepages'] as $tablename) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('domainid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        foreach ($DB->get_records_select('local_oriskill_creators', "customdomain IS NOT NULL AND customdomain <> ''") as $c) {
            if (!$DB->record_exists('local_oriskill_domains', ['domain' => $c->customdomain])) {
                $DB->insert_record('local_oriskill_domains', (object) ['creatorid' => $c->id, 'domain' => $c->customdomain,
                    'isprimary' => 1, 'status' => 'pending', 'lastcheck' => 0, 'timeactive' => 0, 'timecreated' => time()]);
            }
        }
        upgrade_plugin_savepoint(true, 2026103044, 'local', 'oriskill');
    }
    if ($oldversion < 2026103045) {
        // Notifications of the courses.
        foreach (['local_oriskill_coursenotifs', 'local_oriskill_notifsent'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103045, 'local', 'oriskill');
    }
    if ($oldversion < 2026103046) {
        // Support tickets.
        foreach (['local_oriskill_tickets', 'local_oriskill_ticketmsgs'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103046, 'local', 'oriskill');
    }
    if ($oldversion < 2026103047) {
        // Order forms: fields of the buyer, order button, page after payment; several payments in instalments per offer.
        $fields = [
            'local_oriskill_orderforms' => [
                new xmldb_field('buyerfields', XMLDB_TYPE_TEXT, null, null, null, null, null, 'domainid'),
                new xmldb_field('buttonstyle', XMLDB_TYPE_TEXT, null, null, null, null, null, 'buyerfields'),
                new xmldb_field('redirecturl', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'buttonstyle'),
            ],
            'local_oriskill_formoffers' => [
                new xmldb_field('instalplans', XMLDB_TYPE_TEXT, null, null, null, null, null, 'groupids'),
                new xmldb_field('defaultmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'once', 'instalplans'),
            ],
        ];
        foreach ($fields as $tablename => $list) {
            $table = new xmldb_table($tablename);
            foreach ($list as $field) {
                if (!$dbman->field_exists($table, $field)) {
                    $dbman->add_field($table, $field);
                }
            }
        }
        upgrade_plugin_savepoint(true, 2026103047, 'local', 'oriskill');
    }
    if ($oldversion < 2026103048) {
        // Privacy: consent texts, cookie banner, history of the consents.
        foreach (['local_oriskill_privacy', 'local_oriskill_consents'] as $name) {
            if (!$dbman->table_exists($name)) {
                $dbman->install_one_table_from_xmldb_file(__DIR__ . '/install.xml', $name);
            }
        }
        upgrade_plugin_savepoint(true, 2026103048, 'local', 'oriskill');
    }
    return true;
}
