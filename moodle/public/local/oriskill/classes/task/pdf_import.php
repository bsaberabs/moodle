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
 * Create the course of a large PDF import in the background, then tell the trainer.
 *
 * Custom data: creatorid, token, fullname, shortname. The task runs as the user who imported (their temporary folder).
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
        try {
            $plan = pis::load($userid, $data->token, true);
            $courseid = pis::create_course($creator, $userid, $data->token, $plan, $data->fullname, $data->shortname);
            pis::notify($userid, 'pdfimport:done:subject',
                pis::$pdfnotattached ? 'pdfimport:done:bodywarn' : 'pdfimport:done:body', $data->fullname,
                new \moodle_url('/course/view.php', ['id' => $courseid]));
        } catch (\Throwable $e) {
            pis::notify($userid, 'pdfimport:failed:subject', 'pdfimport:failed:body',
                (object) ['name' => $data->fullname, 'error' => $e->getMessage()]);
        }
        pis::discard($userid, $data->token);
    }
}
