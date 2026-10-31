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

use local_oriskill\billing_service;

/**
 * Hourly: a credit note in the trainer's name for each order of their courses refunded through the store.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_refunds extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task:creditrefunds', 'local_oriskill');
    }

    public function execute() {
        mtrace('Credit notes issued for refunded orders: ' . billing_service::credit_refunded_orders());
        // Housekeeping done with it: devices whose "stay logged in" has expired.
        mtrace('Expired stay-logged-in tokens removed: ' . \local_oriskill\remember_service::cleanup());
        mtrace('Library files erased from the bin: ' . \local_oriskill\library_service::empty_trash());
    }
}
