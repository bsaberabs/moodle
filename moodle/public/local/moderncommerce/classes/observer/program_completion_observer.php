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
 * Observer for learner communications after program completion.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\observer;

use local_moderncommerce\notifications\api;
use local_moderncommerce\notifications\notification;

/**
 * Queues learner communications for completed programs.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_completion_observer {
    /**
     * Queue the completion communication.
     *
     * @param \local_moderncommerce\event\program_completed $event Completion event.
     */
    public static function program_completed(\local_moderncommerce\event\program_completed $event): void {
        global $DB;

        $progress = $DB->get_record('local_moderncommerce_program_progress', ['id' => $event->objectid]);
        $programid = (int)($event->other['programid'] ?? 0);
        $program = $DB->get_record('local_moderncommerce_products', [
            'id' => $programid,
            'producttype' => 'program',
        ], 'id, name');
        if (!$progress || !$program || $progress->status !== 'completed') {
            return;
        }

        $programurl = new \moodle_url('/local/moderncommerce/learner/index.php');
        $programurl->set_anchor('/access/program/' . $programid);
        $certificateurl = new \moodle_url('/local/moderncommerce/learner/certificates.php');

        api::notify(
            (new notification('local_moderncommerce', 'program_completed'))
                ->category(notification::CAT_CELEBRATORY)
                ->template('moderncommerce_program_completed')
                ->to_user((int)$event->relateduserid)
                ->placeholders([
                    'program_name' => format_string($program->name),
                    'completed_courses' => (int)$progress->completedcourses,
                    'average_grade' => format_float((float)$progress->averagegrade, 2) . '%',
                    'completion_date' => userdate((int)$progress->timecompleted),
                    'program_url' => $programurl->out(false),
                    'certificate_url' => $certificateurl->out(false),
                ])
                ->context_url($programurl->out(false))
                ->related((int)$progress->id)
                ->dedup_tag('program-completed')
        );
    }
}
