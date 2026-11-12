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

namespace local_oriskill\task;

use local_oriskill\pdf_import_service as pis;

/**
 * Add the modules of a large PDF import to its course in the background, then tell the trainer.
 *
 * Custom data: creatorid, token, courseid, visible, step, start. The task runs as the user who imported (their temporary folder).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_import extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task:pdfimport', 'local_oriskill');
    }

    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $userid = (int) $this->get_userid();
        $creator = $DB->get_record('local_oriskill_creators', ['id' => (int) $data->creatorid]);
        if (!$creator || !$userid) {
            return;
        }
        $name = '';
        if (empty($data->courseid)) {
            // Queued by an older version, before imports went into an existing training: nothing can be added.
            pis::notify($userid, 'pdfimport:failed:subject', 'pdfimport:failed:body',
                (object) ['name' => '-', 'error' => get_string('pdfimport:err:relaunch', 'local_oriskill')]);
            if (!empty($data->token)) {
                pis::discard($userid, (string) $data->token);
            }
            return;
        }
        try {
            $course = get_course((int) $data->courseid);
            $name = format_string($course->fullname);
            $plan = pis::load($userid, $data->token, true);
            $options = (object) ['visible' => (int) $data->visible, 'step' => (int) $data->step, 'start' => (int) $data->start];
            pis::import_modules($creator, $userid, $data->token, $plan, (int) $course->id, $options);
            pis::notify($userid, 'pdfimport:done:subject', 'pdfimport:done:body', $name,
                new \moodle_url('/course/view.php', ['id' => $course->id]));
        } catch (\Throwable $e) {
            if ($DB->is_transaction_started()) {
                $DB->force_transaction_rollback();
            }
            pis::notify($userid, 'pdfimport:failed:subject', 'pdfimport:failed:body',
                (object) ['name' => $name, 'error' => $e->getMessage()]);
        }
        pis::discard($userid, $data->token);
    }
}
