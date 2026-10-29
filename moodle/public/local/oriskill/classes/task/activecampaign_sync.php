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

use local_oriskill\activecampaign_service;

/**
 * Add a buyer to their trainer's ActiveCampaign account; an error makes Moodle retry the task later.
 *
 * Custom data: creatorid, userid, tags, subscribe.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activecampaign_sync extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task:activecampaignsync', 'local_oriskill');
    }

    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $creator = $DB->get_record('local_oriskill_creators', ['id' => $data->creatorid]);
        $account = $creator ? activecampaign_service::account($creator) : null;
        $user = $DB->get_record('user', ['id' => $data->userid, 'deleted' => 0]);
        if (!$account || !$account->enabled || !$user) {
            return;
        }
        activecampaign_service::sync($account, $user, (array) $data->tags, (bool) $data->subscribe);
        mtrace('ActiveCampaign: ' . $user->email . ' synchronised for trainer ' . $creator->id);
    }
}
