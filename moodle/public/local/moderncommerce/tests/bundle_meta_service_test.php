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
 * Tests for bundle duration and assessment aggregation.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_moderncommerce\services\bundle_meta_service;

/**
 * Verifies bundle learning hours and quiz counts come from Moodle course data.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(bundle_meta_service::class)]
final class bundle_meta_service_test extends advanced_testcase {
    /**
     * Test absent, partial, and complete learninghours coverage plus quiz aggregation.
     */
    public function test_learning_hours_and_quizzes_are_aggregated_from_bundle_courses(): void {
        global $DB;

        $this->resetAfterTest(true);

        $courseone = $this->getDataGenerator()->create_course(['fullname' => 'Course One']);
        $coursetwo = $this->getDataGenerator()->create_course(['fullname' => 'Course Two']);
        $coursethree = $this->getDataGenerator()->create_course(['fullname' => 'Course Three']);

        $bundleid = (int)$DB->insert_record('local_moderncommerce_products', (object)[
            'producttype' => 'bundle',
            'name' => 'Aggregation bundle',
            'slug' => 'aggregation-bundle',
            'sku' => 'AGGREGATION-BUNDLE',
            'status' => 'active',
            'visible' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        foreach ([$courseone, $coursetwo, $coursethree] as $sortorder => $course) {
            $DB->insert_record('local_moderncommerce_product_courses', (object)[
                'productid' => $bundleid,
                'courseid' => $course->id,
                'relationtype' => 'included',
                'sortorder' => $sortorder,
                'required' => 1,
                'timecreated' => time(),
            ]);
        }

        $summary = bundle_meta_service::get_learning_hours_summary($bundleid);
        $this->assertFalse($summary['fieldexists']);
        $this->assertSame(0, $summary['totalminutes']);
        $this->assertSame(3, $summary['missingcount']);

        /** @var core_customfield_generator $customfieldgenerator */
        $customfieldgenerator = $this->getDataGenerator()->get_plugin_generator('core_customfield');
        $category = $customfieldgenerator->create_category();
        $field = $customfieldgenerator->create_field([
            'categoryid' => $category->get('id'),
            'name' => 'Learning hours',
            'shortname' => 'learninghours',
            'type' => 'number',
            'configdata' => ['decimalplaces' => 2],
        ]);

        $summary = bundle_meta_service::get_learning_hours_summary($bundleid);
        $this->assertTrue($summary['fieldexists']);
        $this->assertSame(0, $summary['setcount']);
        $this->assertSame(3, $summary['missingcount']);

        // One configured course must contribute to the total while the other two are reported missing.
        $customfieldgenerator->add_instance_data($field, $courseone->id, 1.5);
        $summary = bundle_meta_service::get_learning_hours_summary($bundleid);
        $this->assertSame(90, $summary['totalminutes']);
        $this->assertSame(1, $summary['setcount']);
        $this->assertSame(2, $summary['missingcount']);
        $this->assertEqualsCanonicalizing(['Course Two', 'Course Three'], $summary['missingcourses']);

        $customfieldgenerator->add_instance_data($field, $coursetwo->id, 2);
        $customfieldgenerator->add_instance_data($field, $coursethree->id, 0.5);
        $summary = bundle_meta_service::get_learning_hours_summary($bundleid);
        $this->assertSame(240, $summary['totalminutes']);
        $this->assertSame(3, $summary['setcount']);
        $this->assertSame(0, $summary['missingcount']);
        $this->assertSame([], $summary['missingcourses']);

        $this->getDataGenerator()->create_module('quiz', ['course' => $courseone->id]);
        $this->getDataGenerator()->create_module('quiz', ['course' => $coursetwo->id]);
        $this->assertSame(2, bundle_meta_service::get_total_quizzes($bundleid));
    }
}
