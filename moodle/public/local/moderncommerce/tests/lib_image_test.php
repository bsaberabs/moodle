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
 * Tests for image URL helpers in local_moderncommerce.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Image helper tests.
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class lib_image_test extends advanced_testcase {
    /**
     * Ensure course image helper returns a usable URL without theme dependencies.
     */
    public function test_course_image_returns_url_without_theme_dependency(): void {

        global $CFG;
        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');
        $this->resetAfterTest(true);
        // Create a course with no summary image.
        $course = self::getDataGenerator()->create_course();
        $url = local_moderncommerce_get_course_image_url($course->id);
        $this->assertNotEmpty($url);
        $this->assertStringStartsWith('data:image/svg+xml', $url);
        $this->assertStringNotContainsString('/course/generated/', $url);
    }
}
