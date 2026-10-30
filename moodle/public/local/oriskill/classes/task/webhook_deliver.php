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

use local_oriskill\webhook_service;

/**
 * Post one event to a trainer's webhook; an error makes Moodle retry the task later, up to MAX_ATTEMPTS tries.
 *
 * Custom data: sentid (delivery in the history); tasks queued before the history: hookid, event, body.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook_deliver extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task:webhookdeliver', 'local_oriskill');
    }

    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->sentid)) {
            $hook = $DB->get_record(webhook_service::TABLE, ['id' => $data->hookid]);
            if (!$hook) {
                return;
            }
            $data->sentid = webhook_service::record($hook, $data->event, $data->body);
            $this->set_custom_data($data);
        }
        if (webhook_service::attempt((int) $data->sentid)) {
            throw new \moodle_exception('error:webhookdelivery', 'local_oriskill', '', 'retry');
        }
        mtrace("Webhook delivery {$data->sentid} done");
    }
}
