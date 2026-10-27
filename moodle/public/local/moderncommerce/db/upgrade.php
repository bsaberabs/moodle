<?php
// This file is part of Moodle and is licensed under the
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
 * Upgrade script for Modern Commerce.
 *
 * Versions before 2026061005 were beta schema-reset builds and cannot be upgraded safely.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Handles plugin upgrade.
 *
 * @param int $oldversion Installed plugin version.
 * @return bool
 * @throws moodle_exception This schema reset is clean-install only.
 */
function xmldb_local_moderncommerce_upgrade($oldversion) {
    global $DB;
    if ($oldversion < 2026061005) {
        // Beta schema-reset builds cannot be upgraded; force a clean install.
        throw new moodle_exception('cleaninstallrequired', 'local_moderncommerce');
        // The savepoint below is unreachable by design: the throw above always fires for these
        // versions. It is retained so the upgrade-savepoints check still pairs this if-block with a
        // matching savepoint call. Do not remove it.
        upgrade_plugin_savepoint(true, 2026061005, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061006) {
        upgrade_plugin_savepoint(true, 2026061006, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061007) {
        upgrade_plugin_savepoint(true, 2026061007, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061008) {
        upgrade_plugin_savepoint(true, 2026061008, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061100) {
        upgrade_plugin_savepoint(true, 2026061100, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061101) {
        upgrade_plugin_savepoint(true, 2026061101, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061102) {
        upgrade_plugin_savepoint(true, 2026061102, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061103) {
        upgrade_plugin_savepoint(true, 2026061103, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061104) {
        upgrade_plugin_savepoint(true, 2026061104, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061105) {
        upgrade_plugin_savepoint(true, 2026061105, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061106) {
        upgrade_plugin_savepoint(true, 2026061106, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061107) {
        upgrade_plugin_savepoint(true, 2026061107, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061108) {
        upgrade_plugin_savepoint(true, 2026061108, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061109) {
        upgrade_plugin_savepoint(true, 2026061109, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061110) {
        upgrade_plugin_savepoint(true, 2026061110, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061111) {
        upgrade_plugin_savepoint(true, 2026061111, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061112) {
        upgrade_plugin_savepoint(true, 2026061112, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061113) {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moderncommerce_orders');

        $field = new xmldb_field(
            'thousandseparator',
            XMLDB_TYPE_CHAR,
            '10',
            null,
            null,
            null,
            ',',
            'decimalplaces'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'decimalseparator',
            XMLDB_TYPE_CHAR,
            '10',
            null,
            null,
            null,
            '.',
            'thousandseparator'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026061113, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061308) {
        global $DB;

        $dbman = $DB->get_manager();

        // Advanced bundle merchandising metadata.
        $meta = new xmldb_table('local_moderncommerce_bundle_meta');
        $meta->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $meta->add_field('bundleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $meta->add_field('visibility', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'Public');
        $meta->add_field('availstart', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $meta->add_field('availend', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $meta->add_field('badge_featured', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $meta->add_field('badge_bestseller', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $meta->add_field('badge_trending', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $meta->add_field('bundle_price', XMLDB_TYPE_NUMBER, '20, 6', null, null, null, null);
        $meta->add_field('compare_at', XMLDB_TYPE_NUMBER, '20, 6', null, null, null, null);
        $meta->add_field('skill_level', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $meta->add_field('language', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $meta->add_field('has_prereq', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $meta->add_field('auto_duration', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $meta->add_field('dur_hours', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $meta->add_field('dur_mins', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $meta->add_field('auto_assessments', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $meta->add_field('assessments_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $meta->add_field('auto_outline', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $meta->add_field('pass_grade', XMLDB_TYPE_NUMBER, '5, 2', null, null, null, null);
        $meta->add_field('pass_policy', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'all_must_pass');
        $meta->add_field('bundle_cert', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $meta->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $meta->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $meta->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $meta->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $meta->add_key('bundleid_fk', XMLDB_KEY_FOREIGN_UNIQUE, ['bundleid'], 'local_moderncommerce_products', ['id']);
        $meta->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        if (!$dbman->table_exists($meta)) {
            $dbman->create_table($meta);
        }

        // Bundle curriculum outline items.
        $outline = new xmldb_table('local_moderncommerce_bundle_outline');
        $outline->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $outline->add_field('bundleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $outline->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $outline->add_field('item_text', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $outline->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $outline->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $outline->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $outline->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $outline->add_key('bundleid_fk', XMLDB_KEY_FOREIGN, ['bundleid'], 'local_moderncommerce_products', ['id']);
        $outline->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $outline->add_index('bundle_sort_ix', XMLDB_INDEX_NOTUNIQUE, ['bundleid', 'sortorder']);
        if (!$dbman->table_exists($outline)) {
            $dbman->create_table($outline);
        }

        // Must-pass courses for bundle completion.
        $mustpass = new xmldb_table('local_moderncommerce_bundle_mustpass');
        $mustpass->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $mustpass->add_field('bundleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $mustpass->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $mustpass->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $mustpass->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $mustpass->add_key('bundleid_fk', XMLDB_KEY_FOREIGN, ['bundleid'], 'local_moderncommerce_products', ['id']);
        $mustpass->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $mustpass->add_index('bundle_course_uix', XMLDB_INDEX_UNIQUE, ['bundleid', 'courseid']);
        if (!$dbman->table_exists($mustpass)) {
            $dbman->create_table($mustpass);
        }

        // Prerequisite courses recommended before a bundle.
        $prereq = new xmldb_table('local_moderncommerce_bundle_prereq');
        $prereq->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $prereq->add_field('bundleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $prereq->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $prereq->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $prereq->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $prereq->add_key('bundleid_fk', XMLDB_KEY_FOREIGN, ['bundleid'], 'local_moderncommerce_products', ['id']);
        $prereq->add_key('courseid_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $prereq->add_index('bundle_course_uix', XMLDB_INDEX_UNIQUE, ['bundleid', 'courseid']);
        if (!$dbman->table_exists($prereq)) {
            $dbman->create_table($prereq);
        }

        // Merchandising tags.
        $tags = new xmldb_table('local_moderncommerce_bundle_tags');
        $tags->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tags->add_field('bundleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $tags->add_field('tag', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $tags->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tags->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $tags->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $tags->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $tags->add_key('bundleid_fk', XMLDB_KEY_FOREIGN, ['bundleid'], 'local_moderncommerce_products', ['id']);
        $tags->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $tags->add_index('bundle_tag_uix', XMLDB_INDEX_UNIQUE, ['bundleid', 'tag']);
        if (!$dbman->table_exists($tags)) {
            $dbman->create_table($tags);
        }

        upgrade_plugin_savepoint(true, 2026061308, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061309) {
        // Registers the new local_moderncommerce_admin_save_bundle_image web service.
        upgrade_plugin_savepoint(true, 2026061309, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061703) {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moderncommerce_emailtpl');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('legacyid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('template_key', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('template_type', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('body', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('format', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'html');
        $table->add_field('placeholders', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $table->add_field('locked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('created_by', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('createdby_fk', XMLDB_KEY_FOREIGN, ['created_by'], 'user', ['id']);
        $table->add_index('templatekey_uix', XMLDB_INDEX_UNIQUE, ['template_key']);
        $table->add_index('legacyid_ix', XMLDB_INDEX_NOTUNIQUE, ['legacyid']);
        $table->add_index('component_ix', XMLDB_INDEX_NOTUNIQUE, ['component']);
        $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('templatetype_ix', XMLDB_INDEX_NOTUNIQUE, ['template_type']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        \local_moderncommerce\email\renderer::reset_shell_html();

        $oldtable = new xmldb_table('local_modernemailtemplates');
        if ($dbman->table_exists($oldtable)) {
            $oldrecords = $DB->get_records('local_modernemailtemplates', [], 'id ASC');
            foreach ($oldrecords as $oldrecord) {
                if ($DB->record_exists('local_moderncommerce_emailtpl', ['template_key' => $oldrecord->template_key])) {
                    continue;
                }
                $record = (object) [
                    'legacyid' => (int) $oldrecord->id,
                    'template_key' => (string) $oldrecord->template_key,
                    'component' => (string) ($oldrecord->component ?: 'local_moderncommerce'),
                    'name' => (string) $oldrecord->name,
                    'template_type' => $oldrecord->template_type ?? null,
                    'description' => $oldrecord->description ?? null,
                    'subject' => (string) $oldrecord->subject,
                    'body' => (string) $oldrecord->body,
                    'format' => (string) ($oldrecord->format ?: 'html'),
                    'placeholders' => $oldrecord->placeholders ?? null,
                    'status' => (string) ($oldrecord->status ?: 'active'),
                    'locked' => 0,
                    'created_by' => (int) ($oldrecord->created_by ?? 0),
                    'timecreated' => (int) ($oldrecord->timecreated ?? time()),
                    'timemodified' => (int) ($oldrecord->timemodified ?? time()),
                ];
                $DB->insert_record('local_moderncommerce_emailtpl', $record);
            }

            $commerceconfigs = [
                'orderconfirmation_template',
                'paymentreceipt_template',
                'enrollmentconfirmation_template',
                'keyredemption_template',
                'refundconfirmation_template',
            ];
            foreach ($commerceconfigs as $name) {
                $oldid = (int) get_config('local_moderncommerce', $name);
                if ($oldid > 0) {
                    $newid = $DB->get_field('local_moderncommerce_emailtpl', 'id', ['legacyid' => $oldid]);
                    if ($newid) {
                        set_config($name, (int) $newid, 'local_moderncommerce');
                    }
                }
            }

            foreach (['autoreply_template', 'adminnotify_template'] as $name) {
                $oldid = (int) get_config('local_moderncontact', $name);
                if ($oldid > 0) {
                    $newid = $DB->get_field('local_moderncommerce_emailtpl', 'id', ['legacyid' => $oldid]);
                    if ($newid) {
                        set_config($name, (int) $newid, 'local_moderncontact');
                    }
                }
            }
        }

        \local_moderncommerce\email\demo_seed::seed();

        upgrade_plugin_savepoint(true, 2026061703, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061706) {
        global $DB;
        $dbman = $DB->get_manager();

        // Per-admin dashboard layout preferences (each admin customises their own dashboard).
        $table = new xmldb_table('local_moderncommerce_dashpref');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('chartslayout', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('panellayout', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('daterange', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN_UNIQUE, ['userid'], 'user', ['id']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026061706, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061707) {
        global $DB;
        $dbman = $DB->get_manager();

        // Storefront widgets moved into core from the former local_modernwidgets add-on.
        $widget = new xmldb_table('local_moderncommerce_widget');
        if (!$dbman->table_exists($widget)) {
            $widget->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $widget->add_field('type', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'slider');
            $widget->add_field('zone', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
            $widget->add_field('pagetype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'catalog');
            $widget->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $widget->add_field('title', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $widget->add_field('subtitle', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $widget->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $widget->add_field('audience', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'all');
            $widget->add_field('settings', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $widget->add_field('styleconfig', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $widget->add_field('timestart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $widget->add_field('timeend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $widget->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $widget->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $widget->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $widget->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $widget->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
            $widget->add_index('page_zone_enabled_ix', XMLDB_INDEX_NOTUNIQUE, ['pagetype', 'zone', 'enabled']);
            $widget->add_index('zone_sort_ix', XMLDB_INDEX_NOTUNIQUE, ['zone', 'sortorder']);
            $widget->add_index('type_ix', XMLDB_INDEX_NOTUNIQUE, ['type']);
            $dbman->create_table($widget);
        }

        $slide = new xmldb_table('local_moderncommerce_widget_slide');
        if (!$dbman->table_exists($slide)) {
            $slide->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $slide->add_field('instanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $slide->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $slide->add_field('image', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
            $slide->add_field('heading', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $slide->add_field('subheading', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $slide->add_field('ctalabel', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $slide->add_field('ctaurl', XMLDB_TYPE_CHAR, '1333', null, null, null, null);
            $slide->add_field('ctastyle', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'primary');
            $slide->add_field('bgcolor', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $slide->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $slide->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $slide->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $slide->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $slide->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $slide->add_key('instanceid_fk', XMLDB_KEY_FOREIGN, ['instanceid'], 'local_moderncommerce_widget', ['id']);
            $slide->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
            $slide->add_index('instance_sort_ix', XMLDB_INDEX_NOTUNIQUE, ['instanceid', 'sortorder']);
            $slide->add_index('instance_enabled_ix', XMLDB_INDEX_NOTUNIQUE, ['instanceid', 'enabled']);
            $dbman->create_table($slide);
        }

        $subscriber = new xmldb_table('local_moderncommerce_subscriber');
        if (!$dbman->table_exists($subscriber)) {
            $subscriber->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $subscriber->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $subscriber->add_field('source', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $subscriber->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $subscriber->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $subscriber->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $subscriber->add_index('email_uix', XMLDB_INDEX_UNIQUE, ['email']);
            $dbman->create_table($subscriber);
        }

        upgrade_plugin_savepoint(true, 2026061707, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061724) {
        // Register new public page routes and wishlist AJAX services.
        upgrade_plugin_savepoint(true, 2026061724, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061725) {
        // Refresh Moodle's external service registry after the new wishlist services are available.
        upgrade_plugin_savepoint(true, 2026061725, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061727) {
        // Refresh page-aware widget service signatures.
        upgrade_plugin_savepoint(true, 2026061727, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061802) {
        // Reserve version for the About-page belief statement widget.
        upgrade_plugin_savepoint(true, 2026061802, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061803) {
        global $DB;

        // Adjust the About-page learning promise widget before the belief statement when present.

        $promise = $DB->get_record('local_moderncommerce_widget', [
            'pagetype' => 'about',
            'type' => 'learningpromise',
            'zone' => 'page_main',
        ]);
        $belief = $DB->get_record('local_moderncommerce_widget', [
            'pagetype' => 'about',
            'type' => 'belief',
            'zone' => 'page_main',
        ]);
        if ($promise && $belief && (int) $promise->sortorder === 2 && (int) $belief->sortorder === 2) {
            $DB->set_field('local_moderncommerce_widget', 'sortorder', 3, ['id' => $belief->id]);
        }

        upgrade_plugin_savepoint(true, 2026061803, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061804) {
        global $DB;

        // Adjust the About-page media story carousel between the learning promise and belief statement when present.

        $carousel = $DB->get_record('local_moderncommerce_widget', [
            'pagetype' => 'about',
            'type' => 'mediastorycarousel',
            'zone' => 'page_main',
        ]);
        $belief = $DB->get_record('local_moderncommerce_widget', [
            'pagetype' => 'about',
            'type' => 'belief',
            'zone' => 'page_main',
        ]);
        if ($carousel && $belief && (int) $carousel->sortorder === 3 && (int) $belief->sortorder === 3) {
            $DB->set_field('local_moderncommerce_widget', 'sortorder', 4, ['id' => $belief->id]);
        }

        upgrade_plugin_savepoint(true, 2026061804, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061805) {
        global $DB;

        // Existing installs should get enough starter slides to show carousel controls immediately.
        $carousel = $DB->get_record('local_moderncommerce_widget', [
            'pagetype' => 'about',
            'type' => 'mediastorycarousel',
            'zone' => 'page_main',
        ]);

        if ($carousel) {
            $settings = json_decode((string) $carousel->settings, true);
            if (!is_array($settings)) {
                $settings = [];
            }

            $slides = $settings['slides'] ?? [];
            if (is_array($slides) && count($slides) < 2) {
                $slides[] = [
                    'heading' => 'From purchase to progress',
                    'subheading' => 'Give learners a clear path from discovering the right course to secure checkout, '
                        . 'instant enrolment, and measurable skill growth.',
                    'mediatype' => 'image',
                    'mediasource' => 'url',
                    'imageurl' => '',
                    'imagefile' => '',
                    'videourl' => '',
                    'videofile' => '',
                    'posterurl' => '',
                    'posterimage' => '',
                    'alt' => '',
                ];
                $settings['slides'] = $slides;

                $DB->set_field(
                    'local_moderncommerce_widget',
                    'settings',
                    json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ['id' => $carousel->id]
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026061805, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061806) {
        global $DB;

        // Refresh only untouched starter slides with ecommerce course-selling copy and media.
        $carousel = $DB->get_record('local_moderncommerce_widget', [
            'pagetype' => 'about',
            'type' => 'mediastorycarousel',
            'zone' => 'page_main',
        ]);

        if ($carousel) {
            $settings = json_decode((string) $carousel->settings, true);
            if (!is_array($settings)) {
                $settings = [];
            }

            $starter = [
                [
                    'heading' => 'Courses that move careers forward',
                    'subheading' => 'Showcase programmes with clear outcomes, secure checkout, and instant access '
                        . 'so learners can start building valuable skills right away.',
                    'mediatype' => 'image',
                    'mediasource' => 'url',
                    'imageurl' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80',
                    'imagefile' => '',
                    'videourl' => '',
                    'videofile' => '',
                    'posterurl' => '',
                    'posterimage' => '',
                    'alt' => 'Learners collaborating in a course programme',
                ],
                [
                    'heading' => 'From checkout to course access',
                    'subheading' => 'Turn interest into enrolment with trusted product stories, flexible media, '
                        . 'and a focused path from discovery to purchase.',
                    'mediatype' => 'image',
                    'mediasource' => 'url',
                    'imageurl' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1200&q=80',
                    'imagefile' => '',
                    'videourl' => '',
                    'videofile' => '',
                    'posterurl' => '',
                    'posterimage' => '',
                    'alt' => 'Course team planning an online learning programme',
                ],
            ];

            $slides = $settings['slides'] ?? [];
            if (!is_array($slides)) {
                $slides = [];
            }

            $changed = false;
            $oldheadings = ['A meeting of the minds', 'From purchase to progress'];
            foreach ($starter as $index => $replacement) {
                $existing = $slides[$index] ?? null;
                if (!is_array($existing)) {
                    $slides[$index] = $replacement;
                    $changed = true;
                    continue;
                }

                $heading = (string) ($existing['heading'] ?? '');
                $isoldstarter = $heading === '' || in_array($heading, $oldheadings, true);
                if (!$isoldstarter) {
                    continue;
                }

                $videofile = $existing['videofile'] ?? '';
                $hasvideofile = is_array($videofile) ? !empty($videofile['url']) : !empty($videofile);
                $hasmedia = !empty($existing['imageurl'])
                    || !empty($existing['imagefile'])
                    || !empty($existing['videourl'])
                    || $hasvideofile;

                $existing['heading'] = $replacement['heading'];
                $existing['subheading'] = $replacement['subheading'];
                if (!$hasmedia) {
                    foreach (
                        [
                            'mediatype',
                            'mediasource',
                            'imageurl',
                            'imagefile',
                            'videourl',
                            'videofile',
                            'posterurl',
                            'posterimage',
                            'alt',
                        ] as $key
                    ) {
                        $existing[$key] = $replacement[$key];
                    }
                } else if (empty($existing['alt'])) {
                    $existing['alt'] = $replacement['alt'];
                }

                $slides[$index] = $existing;
                $changed = true;
            }

            if ($changed) {
                $settings['slides'] = array_values($slides);
                $DB->set_field(
                    'local_moderncommerce_widget',
                    'settings',
                    json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ['id' => $carousel->id]
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026061806, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061810) {
        global $DB;

        // Collapse separate previous/next carousel icon settings into one curated navigation style.
        $pairs = [
            'chevron' => ['chevron-left', 'chevron-right'],
            'arrow' => ['arrow-left', 'arrow-right'],
            'caret' => ['caret-left', 'caret-right'],
            'filled-caret' => ['caret-left-fill', 'caret-right-fill'],
            'compact-chevron' => ['chevron-compact-left', 'chevron-compact-right'],
            'double-chevron' => ['chevron-double-left', 'chevron-double-right'],
            'circle-arrow' => ['arrow-left-circle', 'arrow-right-circle'],
            'short-arrow' => ['arrow-left-short', 'arrow-right-short'],
        ];

        $cleanicon = static function (string $value): string {
            $value = strtolower(trim($value));
            return (string) preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', $value));
        };
        $stylefromlegacy = static function (string $prev, string $next) use ($pairs, $cleanicon): string {
            $prev = $cleanicon($prev);
            $next = $cleanicon($next);

            foreach ($pairs as $style => [$left, $right]) {
                if ($prev === $left && $next === $right) {
                    return $style;
                }
            }

            foreach ($pairs as $style => [$left, $right]) {
                if ($prev === $left || $next === $right) {
                    return $style;
                }
            }

            return 'chevron';
        };

        $records = $DB->get_records('local_moderncommerce_widget', ['type' => 'mediastorycarousel']);
        foreach ($records as $record) {
            $settings = json_decode((string) $record->settings, true);
            if (!is_array($settings)) {
                $settings = [];
            }

            $current = strtolower(trim((string) ($settings['navicon'] ?? '')));
            if (!array_key_exists($current, $pairs)) {
                $current = $stylefromlegacy(
                    (string) ($settings['previcon'] ?? ''),
                    (string) ($settings['nexticon'] ?? '')
                );
            }

            $settings['navicon'] = $current;
            unset($settings['previcon'], $settings['nexticon']);

            $DB->set_field(
                'local_moderncommerce_widget',
                'settings',
                json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ['id' => $record->id]
            );
        }

        upgrade_plugin_savepoint(true, 2026061810, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061811) {
        global $DB;

        // Store the selected navigation icon as an actual Bootstrap icon so the editor can preview it.
        $stylemap = [
            'chevron' => 'chevron-right',
            'arrow' => 'arrow-right',
            'caret' => 'caret-right',
            'filled-caret' => 'caret-right-fill',
            'compact-chevron' => 'chevron-compact-right',
            'double-chevron' => 'chevron-double-right',
            'circle-arrow' => 'arrow-right-circle',
            'short-arrow' => 'arrow-right-short',
        ];
        $allowed = [
            'chevron-right',
            'arrow-right',
            'caret-right',
            'caret-right-fill',
            'chevron-compact-right',
            'chevron-double-right',
            'arrow-right-circle',
            'arrow-right-short',
        ];

        $records = $DB->get_records('local_moderncommerce_widget', ['type' => 'mediastorycarousel']);
        foreach ($records as $record) {
            $settings = json_decode((string) $record->settings, true);
            if (!is_array($settings)) {
                $settings = [];
            }

            $current = strtolower(trim((string) ($settings['navicon'] ?? '')));
            $current = (string) preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', $current));
            if (array_key_exists($current, $stylemap)) {
                $current = $stylemap[$current];
            }
            if (!in_array($current, $allowed, true)) {
                $current = 'chevron-right';
            }

            $settings['navicon'] = $current;
            unset($settings['previcon'], $settings['nexticon']);

            $DB->set_field(
                'local_moderncommerce_widget',
                'settings',
                json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ['id' => $record->id]
            );
        }

        upgrade_plugin_savepoint(true, 2026061811, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026061812) {
        // Reserve version for the page-aware global breadcrumb widget.
        upgrade_plugin_savepoint(true, 2026061812, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062001) {
        global $DB;

        $dbman = $DB->get_manager();
        $legacyreviews = new xmldb_table('local_moderncoursereview');
        $legacyreactions = new xmldb_table('local_moderncoursereview_rxn');
        $corereviews = new xmldb_table('local_moderncommerce_reviews');
        $corereactions = new xmldb_table('local_moderncommerce_review_rxn');
        $reviewsrenamed = false;

        if (!$dbman->table_exists($corereviews) && $dbman->table_exists($legacyreviews)) {
            $dbman->rename_table($legacyreviews, 'local_moderncommerce_reviews');
            $reviewsrenamed = true;
        }

        if ($reviewsrenamed && !$dbman->table_exists($corereactions) && $dbman->table_exists($legacyreactions)) {
            $dbman->rename_table($legacyreactions, 'local_moderncommerce_review_rxn');
        }

        if (!$dbman->table_exists($corereviews)) {
            $corereviews->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $corereviews->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $corereviews->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $corereviews->add_field('rating', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
            $corereviews->add_field('comment', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $corereviews->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $corereviews->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $corereviews->add_field('hidden', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $corereviews->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $corereviews->add_key('course_fk', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
            $corereviews->add_key('user_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $corereviews->add_index('courseid_userid_uq', XMLDB_INDEX_UNIQUE, ['courseid', 'userid']);
            $corereviews->add_index('hidden_idx', XMLDB_INDEX_NOTUNIQUE, ['hidden']);
            $dbman->create_table($corereviews);
        }

        if (!$dbman->table_exists($corereactions)) {
            $corereactions->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $corereactions->add_field('reviewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $corereactions->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $corereactions->add_field('reaction', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
            $corereactions->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $corereactions->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $corereactions->add_key(
                'review_fk',
                XMLDB_KEY_FOREIGN,
                ['reviewid'],
                'local_moderncommerce_reviews',
                ['id']
            );
            $corereactions->add_key('user_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $corereactions->add_index('review_user_uq', XMLDB_INDEX_UNIQUE, ['reviewid', 'userid']);
            $dbman->create_table($corereactions);
        }

        $reviewhiddenidx = new xmldb_index('hidden_idx', XMLDB_INDEX_NOTUNIQUE, ['hidden']);
        if (!$dbman->index_exists($corereviews, $reviewhiddenidx)) {
            $dbman->add_index($corereviews, $reviewhiddenidx);
        }

        if ($dbman->table_exists($legacyreviews)) {
            $reviewmap = [];
            $legacyrecords = $DB->get_records('local_moderncoursereview', [], 'id ASC');
            foreach ($legacyrecords as $legacyrecord) {
                $existingid = $DB->get_field('local_moderncommerce_reviews', 'id', [
                    'courseid' => $legacyrecord->courseid,
                    'userid' => $legacyrecord->userid,
                ]);
                if ($existingid) {
                    $reviewmap[(int)$legacyrecord->id] = (int)$existingid;
                    continue;
                }

                $record = (object)[
                    'courseid' => $legacyrecord->courseid,
                    'userid' => $legacyrecord->userid,
                    'rating' => $legacyrecord->rating,
                    'comment' => $legacyrecord->comment,
                    'timecreated' => $legacyrecord->timecreated,
                    'timemodified' => $legacyrecord->timemodified,
                    'hidden' => $legacyrecord->hidden ?? 0,
                ];
                $reviewmap[(int)$legacyrecord->id] = (int)$DB->insert_record('local_moderncommerce_reviews', $record);
            }

            if ($dbman->table_exists($legacyreactions)) {
                $legacyreactionsrecords = $DB->get_records('local_moderncoursereview_rxn', [], 'id ASC');
                foreach ($legacyreactionsrecords as $legacyreaction) {
                    $reviewid = $reviewmap[(int)$legacyreaction->reviewid] ?? 0;
                    if ($reviewid <= 0) {
                        continue;
                    }
                    if (
                        $DB->record_exists(
                            'local_moderncommerce_review_rxn',
                            [
                                'reviewid' => $reviewid,
                                'userid' => $legacyreaction->userid,
                            ]
                        )
                    ) {
                        continue;
                    }

                    $DB->insert_record('local_moderncommerce_review_rxn', (object)[
                        'reviewid' => $reviewid,
                        'userid' => $legacyreaction->userid,
                        'reaction' => $legacyreaction->reaction,
                        'timecreated' => $legacyreaction->timecreated,
                    ]);
                }

                $dbman->drop_table($legacyreactions);
            }

            $dbman->drop_table($legacyreviews);
        }

        $legacyenabled = get_config('local_moderncoursereview', 'enable');
        if ($legacyenabled !== false && $legacyenabled !== null && $legacyenabled !== '') {
            set_config('reviews_enabled', empty($legacyenabled) ? 0 : 1, 'local_moderncommerce');
        } else if (get_config('local_moderncommerce', 'reviews_enabled') === false) {
            set_config('reviews_enabled', 1, 'local_moderncommerce');
        }

        upgrade_plugin_savepoint(true, 2026062001, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062500) {
        // Modern Notify has been absorbed into Modern Commerce core. Create the notification
        // delivery tables, then migrate any data/config from the retiring local_modernnotify plugin.
        global $CFG, $DB;
        $dbman = $DB->get_manager();

        $notifytables = [
            'local_moderncommerce_notify_queue',
            'local_moderncommerce_notify_log',
            'local_moderncommerce_notify_digest',
            'local_moderncommerce_notify_identity',
            'local_moderncommerce_notify_suppression',
        ];
        foreach ($notifytables as $tablename) {
            if (!$dbman->table_exists(new xmldb_table($tablename))) {
                $dbman->install_one_table_from_xmldb_file(
                    $CFG->dirroot . '/local/moderncommerce/db/install.xml',
                    $tablename
                );
            }
        }

        // Copy rows from the legacy modernnotify_* tables (suppression/identity first for continuity).
        $tablemap = [
            'modernnotify_suppression' => 'local_moderncommerce_notify_suppression',
            'modernnotify_identity'    => 'local_moderncommerce_notify_identity',
            'modernnotify_queue'       => 'local_moderncommerce_notify_queue',
            'modernnotify_log'         => 'local_moderncommerce_notify_log',
            'modernnotify_digest'      => 'local_moderncommerce_notify_digest',
        ];
        foreach ($tablemap as $oldtable => $newtable) {
            if ($dbman->table_exists(new xmldb_table($oldtable)) && !$DB->count_records($newtable)) {
                $rs = $DB->get_recordset($oldtable);
                foreach ($rs as $row) {
                    unset($row->id);
                    try {
                        $DB->insert_record($newtable, $row);
                    } catch (\Throwable $e) {
                        // Skip duplicates (e.g. dedupekey collisions) — best-effort migration.
                        continue;
                    }
                }
                $rs->close();
            }
        }

        // Migrate config from local_modernnotify to prefixed local_moderncommerce keys.
        $configmap = [
            'batchsize'     => 'notify_batchsize',
            'slack_enabled' => 'notify_slack_enabled',
            'slack_url'     => 'notify_slack_url',
            'slack_secret'  => 'notify_slack_secret',
            'teams_enabled' => 'notify_teams_enabled',
            'teams_url'     => 'notify_teams_url',
            'teams_secret'  => 'notify_teams_secret',
            'unsub_secret'  => 'notify_unsub_secret',
        ];
        foreach ($configmap as $oldkey => $newkey) {
            $value = get_config('local_modernnotify', $oldkey);
            if ($value !== false && get_config('local_moderncommerce', $newkey) === false) {
                set_config($newkey, $value, 'local_moderncommerce');
            }
        }

        upgrade_plugin_savepoint(true, 2026062500, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062600) {
        // Email template "type" is now a defined select bound to the canonical notification
        // categories. Normalise any legacy/free-text values (purchase, contact, null, ...).
        global $DB;
        if ($DB->get_manager()->table_exists(new xmldb_table('local_moderncommerce_emailtpl'))) {
            $rs = $DB->get_recordset('local_moderncommerce_emailtpl', null, '', 'id, template_type');
            foreach ($rs as $row) {
                $canon = \local_moderncommerce\notifications\local\category_registry::normalise($row->template_type);
                if ($canon !== $row->template_type) {
                    $DB->set_field('local_moderncommerce_emailtpl', 'template_type', $canon, ['id' => $row->id]);
                }
            }
            $rs->close();
        }

        upgrade_plugin_savepoint(true, 2026062600, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062605) {
        // Contact capture has been absorbed into Modern Commerce core (from the retired
        // local_moderncontact add-on). Create the contact tables on existing sites.
        global $CFG, $DB;
        $dbman = $DB->get_manager();

        // Create contacts before contact_replies (the replies FK references contacts).
        $contacttables = [
            'local_moderncommerce_contacts',
            'local_moderncommerce_contact_replies',
        ];
        foreach ($contacttables as $tablename) {
            if (!$dbman->table_exists(new xmldb_table($tablename))) {
                $dbman->install_one_table_from_xmldb_file(
                    $CFG->dirroot . '/local/moderncommerce/db/install.xml',
                    $tablename
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026062605, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062606) {
        // Subscriptions have been absorbed into Modern Commerce core (from the retired
        // local_modernsubscription add-on). Create the subscription tables on existing sites.
        global $CFG, $DB;
        $dbman = $DB->get_manager();

        // Parents before children so the FK indexes resolve cleanly.
        $subscriptiontables = [
            'local_moderncommerce_subscription_plans',
            'local_moderncommerce_subscription_features',
            'local_moderncommerce_subscription_plan_features',
            'local_moderncommerce_subscription_feature_map',
            'local_moderncommerce_subscription_access_rules',
            'local_moderncommerce_user_subscriptions',
            'local_moderncommerce_subscription_history',
            'local_moderncommerce_subscription_reminders',
            'local_moderncommerce_subscription_access',
            'local_moderncommerce_subscription_emailtpl',
            'local_moderncommerce_subscription_keys',
            'local_moderncommerce_subscription_key_usage',
            'local_moderncommerce_subscription_log',
        ];
        foreach ($subscriptiontables as $tablename) {
            if (!$dbman->table_exists(new xmldb_table($tablename))) {
                $dbman->install_one_table_from_xmldb_file(
                    $CFG->dirroot . '/local/moderncommerce/db/install.xml',
                    $tablename
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026062606, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062607) {
        global $CFG, $DB;
        $dbman = $DB->get_manager();

        if (!$dbman->table_exists(new xmldb_table('local_moderncommerce_audit_log'))) {
            $dbman->install_one_table_from_xmldb_file(
                $CFG->dirroot . '/local/moderncommerce/db/install.xml',
                'local_moderncommerce_audit_log'
            );
        }

        upgrade_plugin_savepoint(true, 2026062607, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062608) {
        global $CFG, $DB;
        $dbman = $DB->get_manager();

        if (!$dbman->table_exists(new xmldb_table('local_moderncommerce_widget_preset'))) {
            $dbman->install_one_table_from_xmldb_file(
                $CFG->dirroot . '/local/moderncommerce/db/install.xml',
                'local_moderncommerce_widget_preset'
            );
        }

        upgrade_plugin_savepoint(true, 2026062608, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062609) {
        global $DB;

        // Newsletter lead capture is now exposed through explicit core newsletter
        // services. Remove the old generic public function from installed sites.
        $DB->delete_records('external_services_functions', ['functionname' => 'local_moderncommerce_subscribe']);
        $DB->delete_records('external_functions', ['name' => 'local_moderncommerce_subscribe']);

        upgrade_plugin_savepoint(true, 2026062609, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062610) {
        global $CFG, $DB;

        $normaliselogoplaceholders = static function (?string $value): ?string {
            if ($value === null || $value === '') {
                return $value;
            }

            return str_replace(['{logo_dark}', '{logo_white}'], '{logo}', $value);
        };

        $migratefields = static function (string $tablename, array $fields) use ($DB, $normaliselogoplaceholders): void {
            if (!$DB->get_manager()->table_exists(new xmldb_table($tablename))) {
                return;
            }

            $fieldselect = 'id, ' . implode(', ', $fields);
            $rs = $DB->get_recordset($tablename, null, '', $fieldselect);
            foreach ($rs as $row) {
                $update = (object) [
                    'id' => (int) $row->id,
                ];
                $changed = false;

                foreach ($fields as $field) {
                    $current = $row->{$field} ?? null;
                    $normalised = $normaliselogoplaceholders($current);
                    if ($normalised !== $current) {
                        $update->{$field} = $normalised;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $update->timemodified = time();
                    $DB->update_record($tablename, $update);
                }
            }
            $rs->close();
        };

        // Logo colour variants were theme-specific aliases, not public template tokens.
        $migratefields('local_moderncommerce_emailtpl', ['subject', 'body', 'placeholders']);
        $migratefields('local_moderncommerce_subscription_emailtpl', ['custom_subject', 'custom_message']);

        $configs = $DB->get_records('config_plugins', ['plugin' => 'local_moderncommerce'], '', 'id, name, value');
        foreach ($configs as $config) {
            if (!preg_match('/_(subject|body)$/', (string) $config->name)) {
                continue;
            }

            $normalised = $normaliselogoplaceholders($config->value);
            if ($normalised !== $config->value) {
                set_config($config->name, $normalised, 'local_moderncommerce');
            }
        }

        \local_moderncommerce\services\role_preset_service::seed_presets();

        upgrade_plugin_savepoint(true, 2026062610, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062611) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/accesslib.php');
        update_capabilities('local_moderncommerce');

        \local_moderncommerce\services\role_preset_service::seed_presets();

        $managerrole = $DB->get_record('role', ['shortname' => 'manager'], 'id', IGNORE_MISSING);
        if ($managerrole) {
            $context = \context_system::instance();
            $restrictedcaps = [
                'local/moderncommerce:managesettings',
                'local/moderncommerce:managecategories',
                'local/moderncommerce:viewauditlog',
                'local/moderncommerce:configuregateways',
                'local/moderncommerce:manageemailtemplates',
                'local/moderncommerce:managenotifications',
                'local/moderncommerce:managesubscriptionplans',
                'local/moderncommerce:managesubscriptionfeatures',
            ];

            foreach ($restrictedcaps as $capability) {
                $permission = $DB->get_field('role_capabilities', 'permission', [
                    'roleid' => $managerrole->id,
                    'contextid' => $context->id,
                    'capability' => $capability,
                ], IGNORE_MISSING);

                if ((int) $permission === CAP_ALLOW) {
                    unassign_capability($capability, (int) $managerrole->id, $context->id, false);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026062611, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026062612) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/accesslib.php');
        update_capabilities('local_moderncommerce');

        \local_moderncommerce\services\role_preset_service::seed_presets();

        $productrole = $DB->get_record('role', ['shortname' => 'moderncommerceproduct'], '*', IGNORE_MISSING);
        if (
            $productrole
                && strpos(
                    (string) $productrole->description,
                    'Seeded role preset: local_moderncommerce:product'
                ) !== false
        ) {
            $context = \context_system::instance();
            foreach (
                [
                'local/moderncommerce:managesubscriptionplans',
                'local/moderncommerce:managesubscriptionfeatures',
                ] as $capability
            ) {
                $permission = $DB->get_field('role_capabilities', 'permission', [
                    'roleid' => $productrole->id,
                    'contextid' => $context->id,
                    'capability' => $capability,
                ], IGNORE_MISSING);

                if ((int) $permission === CAP_ALLOW) {
                    unassign_capability($capability, (int) $productrole->id, $context->id, false);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026062612, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081002) {
        // The storefront category tiles used to resolve Moodle course categories; they now resolve
        // Modern Commerce product categories, so saved widget items hold ids that no longer mean
        // anything and the widget renders empty. See the service method for the remapping rules.
        \local_moderncommerce\services\category_service::migrate_widget_items_to_product_categories();

        upgrade_plugin_savepoint(true, 2026081002, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081102) {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moderncommerce_bundle_meta');
        $field = new xmldb_field(
            'certificate_template_id',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            null,
            null,
            null,
            'bundle_cert'
        );

        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026081102, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081103) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moderncommerce_program_progress');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('orderid', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $table->add_field('totalcourses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('completedcourses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('passedcourses', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('averagegrade', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('progresspercentage', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('currentcourseid', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('certificateissueid', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('programid_fk', XMLDB_KEY_FOREIGN, ['programid'], 'local_moderncommerce_products', ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('program_user_uix', XMLDB_INDEX_UNIQUE, ['programid', 'userid']);
            $table->add_index('status_ix', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('certificateissue_ix', XMLDB_INDEX_NOTUNIQUE, ['certificateissueid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026081103, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081104) {
        // Refresh event observers and expose the global program certificate setting.
        upgrade_plugin_savepoint(true, 2026081104, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081105) {
        // Refresh external services for the program prerequisite search-ahead picker.
        upgrade_plugin_savepoint(true, 2026081105, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081106) {
        // Expose the global certificate template on its dedicated settings tab.
        upgrade_plugin_savepoint(true, 2026081106, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081107) {
        // Program prerequisite picker interaction update is a code-only change.
        upgrade_plugin_savepoint(true, 2026081107, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081108) {
        // Certificate template manager links are a code-only change.
        upgrade_plugin_savepoint(true, 2026081108, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081109) {
        // Pass-policy and must-pass course alignment is a code-only change.
        upgrade_plugin_savepoint(true, 2026081109, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081110) {
        // Must-pass course list styling is a code-only change.
        upgrade_plugin_savepoint(true, 2026081110, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081200) {
        // Finalize the Modern Commerce 2.2.0 program and certificate release metadata.
        upgrade_plugin_savepoint(true, 2026081200, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081601) {
        // Store the reusable Paystack authorization so subscription renewals can
        // charge the same card again without the buyer being present.
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moderncommerce_user_subscriptions');

        $field = new xmldb_field(
            'paystack_authorization_code',
            XMLDB_TYPE_CHAR,
            '100',
            null,
            null,
            null,
            null,
            'paystack_subscription_code'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'paystack_email',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            null,
            null,
            null,
            'paystack_authorization_code'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026081601, 'local', 'moderncommerce');
    }

    if ($oldversion < 2026081700) {
        // Custom storefront pages an administrator can create at runtime. The six
        // built-in pages are not stored here - they remain hardcoded and are merged
        // with these rows by public_page::page_catalog().
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moderncommerce_storepage');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('slug', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('summary', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('icon', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'bi-file-text');
            $table->add_field('template', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'content');
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

            $table->add_index('slug_uix', XMLDB_INDEX_UNIQUE, ['slug']);
            $table->add_index('enabled_sort_ix', XMLDB_INDEX_NOTUNIQUE, ['enabled', 'sortorder']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026081700, 'local', 'moderncommerce');
    }

    return true;
}
