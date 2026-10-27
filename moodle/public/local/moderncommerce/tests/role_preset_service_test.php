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
 * Tests for Modern Commerce Moodle role preset seeding.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_moderncommerce\services\role_preset_service;

/**
 * Verifies the role preset seeder stays native, idempotent, and conservative.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(role_preset_service::class)]
final class role_preset_service_test extends advanced_testcase {
    /**
     * Prepare a clean role set for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->delete_preset_roles();
    }

    /**
     * Presets are created as system-context roles with expected capabilities.
     */
    public function test_presets_are_created_with_system_context_and_capabilities(): void {
        global $DB;

        $result = role_preset_service::seed_presets(false, 'moderncommercefinance');
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);

        $role = $this->get_role('moderncommercefinance');
        $this->assertNotEmpty($role->id);
        $this->assertStringContainsString('Seeded role preset: local_moderncommerce:finance', $role->description);

        $this->assertSame(1, $DB->count_records('role_context_levels', [
            'roleid' => $role->id,
            'contextlevel' => CONTEXT_SYSTEM,
        ]));

        $context = context_system::instance();
        foreach (role_preset_service::presets()['finance']['capabilities'] as $capability) {
            $this->assertSame(CAP_ALLOW, $this->capability_permission((int) $role->id, $capability, $context));
        }
    }

    /**
     * Rerunning the seeder leaves complete roles unchanged and restores only missing capabilities.
     */
    public function test_rerun_is_idempotent_and_adds_only_missing_capabilities(): void {
        global $DB;

        role_preset_service::seed_presets(false, 'moderncommercefinance');
        $role = $this->get_role('moderncommercefinance');
        $context = context_system::instance();

        $second = role_preset_service::seed_presets(false, 'moderncommercefinance');
        $this->assertSame(0, $second['capabilitiesadded']);
        $this->assertSame(1, $second['unchanged']);

        $DB->delete_records('role_capabilities', [
            'roleid' => $role->id,
            'contextid' => $context->id,
            'capability' => 'local/moderncommerce:processrefunds',
        ]);

        $third = role_preset_service::seed_presets(false, 'moderncommercefinance');
        $this->assertSame(1, $third['updated']);
        $this->assertSame(1, $third['capabilitiesadded']);
        $this->assertSame(CAP_ALLOW, $this->capability_permission(
            (int) $role->id,
            'local/moderncommerce:processrefunds',
            $context
        ));
    }

    /**
     * Admin-added capabilities are preserved when a marked seeded role is refreshed.
     */
    public function test_marked_roles_keep_admin_added_capabilities(): void {
        global $CFG;

        require_once($CFG->libdir . '/accesslib.php');

        role_preset_service::seed_presets(false, 'moderncommercesupport');
        $role = $this->get_role('moderncommercesupport');
        $context = context_system::instance();

        assign_capability('local/moderncommerce:viewreports', CAP_ALLOW, (int) $role->id, $context, false);

        $result = role_preset_service::seed_presets(false, 'moderncommercesupport');
        $this->assertSame(1, $result['unchanged']);
        $this->assertSame(CAP_ALLOW, $this->capability_permission(
            (int) $role->id,
            'local/moderncommerce:viewreports',
            $context
        ));
    }

    /**
     * The administrator preset owns global category structure.
     */
    public function test_admin_preset_includes_category_management(): void {
        role_preset_service::seed_presets(false, 'moderncommerceadmin');
        $role = $this->get_role('moderncommerceadmin');

        $this->assertSame(CAP_ALLOW, $this->capability_permission(
            (int) $role->id,
            'local/moderncommerce:managecategories',
            context_system::instance()
        ));
    }

    /**
     * Product managers can manage products without receiving global category structure access.
     */
    public function test_product_preset_does_not_include_category_management(): void {
        global $DB;

        role_preset_service::seed_presets(false, 'moderncommerceproduct');
        $role = $this->get_role('moderncommerceproduct');

        $this->assertFalse($DB->record_exists('role_capabilities', [
            'roleid' => $role->id,
            'contextid' => context_system::instance()->id,
            'capability' => 'local/moderncommerce:managecategories',
        ]));
    }

    /**
     * Product managers do not receive subscription setup capabilities by default.
     */
    public function test_product_preset_does_not_include_subscription_setup(): void {
        global $DB;

        role_preset_service::seed_presets(false, 'moderncommerceproduct');
        $role = $this->get_role('moderncommerceproduct');
        $context = context_system::instance();

        foreach (
            [
            'local/moderncommerce:managesubscriptionplans',
            'local/moderncommerce:managesubscriptionfeatures',
            ] as $capability
        ) {
            $this->assertFalse($DB->record_exists('role_capabilities', [
                'roleid' => $role->id,
                'contextid' => $context->id,
                'capability' => $capability,
            ]));
        }
    }

    /**
     * Existing roles using a preset shortname without the marker are not modified.
     */
    public function test_unmarked_shortname_collision_is_skipped(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/accesslib.php');

        $roleid = create_role(
            'Existing Finance Role',
            'moderncommercefinance',
            'A manually created role that happens to use the same shortname.'
        );

        $result = role_preset_service::seed_presets(false, 'moderncommercefinance');
        $this->assertSame(1, $result['skipped']);

        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
        $this->assertStringNotContainsString('Seeded role preset:', $role->description);
        $this->assertFalse($DB->record_exists('role_capabilities', [
            'roleid' => $roleid,
            'capability' => 'local/moderncommerce:viewreports',
        ]));
    }

    /**
     * Dry-run mode reports changes without creating roles.
     */
    public function test_dry_run_does_not_create_roles(): void {
        global $DB;

        $result = role_preset_service::seed_presets(true, 'moderncommercefinance');

        $this->assertSame(1, $result['wouldcreate']);
        $this->assertFalse($DB->record_exists('role', ['shortname' => 'moderncommercefinance']));
    }

    /**
     * The seeder defines roles only and never assigns users.
     */
    public function test_seeder_does_not_assign_users(): void {
        global $DB;

        self::getDataGenerator()->create_user();
        role_preset_service::seed_presets();

        foreach (role_preset_service::presets() as $preset) {
            $role = $DB->get_record('role', ['shortname' => $preset['shortname']], 'id', MUST_EXIST);
            $this->assertSame(0, $DB->count_records('role_assignments', ['roleid' => $role->id]));
        }
    }

    /**
     * Delete preset shortnames so tests do not depend on install-time seeding state.
     */
    private function delete_preset_roles(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/accesslib.php');

        foreach (role_preset_service::presets() as $preset) {
            $role = $DB->get_record('role', ['shortname' => $preset['shortname']], 'id', IGNORE_MISSING);
            if ($role) {
                delete_role((int) $role->id);
            }
        }
    }

    /**
     * Fetch a role by shortname.
     *
     * @param string $shortname Role shortname.
     * @return stdClass Role record.
     */
    private function get_role(string $shortname): stdClass {
        global $DB;

        return $DB->get_record('role', ['shortname' => $shortname], '*', MUST_EXIST);
    }

    /**
     * Read a role capability permission.
     *
     * @param int $roleid Role ID.
     * @param string $capability Capability name.
     * @param context_system $context System context.
     * @return int Permission constant.
     */
    private function capability_permission(int $roleid, string $capability, context_system $context): int {
        global $DB;

        return (int) $DB->get_field('role_capabilities', 'permission', [
            'roleid' => $roleid,
            'contextid' => $context->id,
            'capability' => $capability,
        ], MUST_EXIST);
    }
}
