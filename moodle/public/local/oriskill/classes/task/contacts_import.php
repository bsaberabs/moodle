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

use local_oriskill\contact_import;

/**
 * Import the next batch of rows of a contact import, then queue the following batch.
 *
 * Custom data: importid.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contacts_import extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task:contactsimport', 'local_oriskill');
    }

    public function execute() {
        $importid = (int) $this->get_custom_data()->importid;
        if (contact_import::run($importid)) {
            contact_import::queue($importid);
        }
    }
}
