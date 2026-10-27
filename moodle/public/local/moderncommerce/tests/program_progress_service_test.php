<?php
// This file is part of Moodle and is licensed under the GNU GPL v3 or later.
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
 * Tests for program enrollment and completion behavior.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_moderncommerce\services\program_progress_service;

/**
 * Tests program access and completion.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(program_progress_service::class)]
final class program_progress_service_test extends advanced_testcase {
    public function test_program_grants_all_courses_and_persists_completion(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $first = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $second = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $programid = $this->create_program([$first->id, $second->id]);

        $progress = program_progress_service::enrol($user->id, $programid);
        $this->assertSame('active', $progress['status']);
        $this->assertTrue($progress['courses'][0]['unlocked']);
        $this->assertTrue($progress['courses'][1]['unlocked']);
        $this->assertTrue(is_enrolled(context_course::instance($first->id), $user->id));
        $this->assertTrue(is_enrolled(context_course::instance($second->id), $user->id));

        $this->complete_course($user->id, $first->id);
        $progress = program_progress_service::sync($user->id, $programid, null, false);
        $this->assertTrue($progress['courses'][1]['unlocked']);
        $this->assertTrue(is_enrolled(context_course::instance($second->id), $user->id));

        $this->complete_course($user->id, $second->id);
        $progress = program_progress_service::sync($user->id, $programid, null, false);
        $this->assertTrue($progress['completed']);
        $this->assertSame(100.0, $progress['progresspercentage']);
        $this->assertSame('completed', $DB->get_field('local_moderncommerce_program_progress', 'status', [
            'programid' => $programid, 'userid' => $user->id,
        ]));
        $this->assertSame(1, $DB->count_records('local_moderncommerce_notify_queue', [
            'eventkey' => 'program_completed',
            'recipientuserid' => $user->id,
            'channel' => 'email',
            'templatekey' => 'moderncommerce_program_completed',
        ]));

        // Recalculation is common; it must not produce another completion communication.
        program_progress_service::sync($user->id, $programid, null, false);
        $this->assertSame(1, $DB->count_records('local_moderncommerce_notify_queue', [
            'eventkey' => 'program_completed',
            'recipientuserid' => $user->id,
            'channel' => 'email',
        ]));
    }

    public function test_external_prerequisite_gates_completion_but_not_course_access(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $prerequisite = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $first = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $programid = $this->create_program([$first->id]);
        $DB->insert_record('local_moderncommerce_bundle_prereq', (object)[
            'bundleid' => $programid, 'courseid' => $prerequisite->id, 'timecreated' => time(),
        ]);

        $progress = program_progress_service::enrol($user->id, $programid);
        $this->assertSame('active', $progress['status']);
        $this->assertFalse($progress['prerequisitesmet']);
        $this->assertTrue(is_enrolled(context_course::instance($first->id), $user->id));

        $this->complete_course($user->id, $first->id);
        $progress = program_progress_service::sync($user->id, $programid, null, false);
        $this->assertFalse($progress['completed']);

        $this->complete_course($user->id, $prerequisite->id);
        $progress = program_progress_service::sync($user->id, $programid, null, false);
        $this->assertTrue($progress['prerequisitesmet']);
        $this->assertTrue($progress['completed']);
    }

    public function test_any_pass_policy_still_requires_every_course_to_be_complete(): void {
        global $DB;
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $first = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $second = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $programid = $this->create_program([$first->id, $second->id]);
        $DB->insert_record('local_moderncommerce_bundle_meta', (object)[
            'bundleid' => $programid,
            'pass_policy' => 'any_pass',
            'pass_grade' => 70,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        program_progress_service::enrol($user->id, $programid);
        $this->complete_course($user->id, $first->id);
        $progress = program_progress_service::sync($user->id, $programid, null, false);
        $this->assertFalse($progress['completed']);
        $this->assertSame(50.0, $progress['progresspercentage']);

        $this->complete_course($user->id, $second->id);
        $progress = program_progress_service::sync($user->id, $programid, null, false);
        $this->assertTrue($progress['completed']);
    }

    /**
     * Create a program containing the supplied courses.
     *
     * @param array $courseids Moodle course IDs.
     * @return int Program product ID.
     */
    private function create_program(array $courseids): int {
        global $DB;
        $id = (int)$DB->insert_record('local_moderncommerce_products', (object)[
            'producttype' => 'program', 'name' => 'Test program', 'slug' => 'test-program-' . uniqid(),
            'sku' => 'PROGRAM-' . uniqid(), 'status' => 'active', 'visible' => 1,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        foreach ($courseids as $sortorder => $courseid) {
            $DB->insert_record('local_moderncommerce_product_courses', (object)[
                'productid' => $id, 'courseid' => $courseid, 'relationtype' => 'included',
                'sortorder' => $sortorder, 'required' => 1, 'timecreated' => time(),
            ]);
        }
        return $id;
    }

    /**
     * Mark a Moodle course complete for a learner.
     *
     * @param int $userid Learner ID.
     * @param int $courseid Course ID.
     */
    private function complete_course(int $userid, int $courseid): void {
        global $DB;
        $record = $DB->get_record('course_completions', ['userid' => $userid, 'course' => $courseid]);
        if (!$record) {
            $record = (object)['userid' => $userid, 'course' => $courseid, 'timeenrolled' => time(), 'timestarted' => time()];
            $record->id = $DB->insert_record('course_completions', $record);
        }
        $record->timecompleted = time();
        $DB->update_record('course_completions', $record);
    }
}
