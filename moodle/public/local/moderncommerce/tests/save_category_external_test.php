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
 * Tests the per-field validation error contract on the save_category endpoint.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_external\external_api;
use local_moderncommerce\external\categories\save_category;

/**
 * Locks down the errors[] contract the React layer binds to fields.
 *
 * Running the result through external_api::clean_returnvalue is the point of
 * these tests: it is what catches a field key the declared PARAM type would
 * silently strip, which would leave the UI with an error it cannot place.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(save_category::class)]
final class save_category_external_test extends advanced_testcase {
    /**
     * Reset the database and act as an administrator.
     */
    protected function setUp(): void {
        global $DB, $PAGE;

        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // This site defines required custom profile fields. Test users have no
        // values for them, so require_login() would redirect to the profile
        // editor, which PHPUnit reports as a fatal unsupported redirect. The
        // fields are irrelevant to what these tests cover.
        $DB->delete_records('user_info_field');

        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url('/local/moderncommerce/admin/categories.php');
    }

    /**
     * A blank name comes back as a field error, not an exception or a toast.
     */
    public function test_blank_name_returns_a_field_error(): void {
        $result = save_category::execute(0, '', '', true);
        $result = external_api::clean_returnvalue(save_category::execute_returns(), $result);

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['id']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('name', $result['errors'][0]['field']);
        $this->assertNotSame('', $result['errors'][0]['message']);
    }

    /**
     * A valid save reports success and an empty error list.
     */
    public function test_valid_save_returns_success_and_no_errors(): void {
        $result = save_category::execute(0, 'Courses', 'All courses', true);
        $result = external_api::clean_returnvalue(save_category::execute_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['id']);
        $this->assertSame([], $result['errors']);
    }

    /**
     * Editing a category that no longer exists is reported at form level.
     */
    public function test_missing_record_returns_a_form_level_error(): void {
        $result = save_category::execute(99999, 'Ghost', '', true);
        $result = external_api::clean_returnvalue(save_category::execute_returns(), $result);

        $this->assertFalse($result['success']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame('_form', $result['errors'][0]['field']);
    }

    /**
     * Managing categories is a privileged action; a plain user cannot.
     */
    public function test_capability_is_required(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\required_capability_exception::class);
        save_category::execute(0, 'Sneaky', '', true);
    }
}
