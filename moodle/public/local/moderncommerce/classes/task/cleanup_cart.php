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

namespace local_moderncommerce\task;


/**
 * Scheduled task to cleanup old cart items
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_cart extends \core\task\scheduled_task {
    /**
     * Get task name
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:cleanup_cart', 'local_moderncommerce');
    }

    /**
     * Execute task
     */
    public function execute() {

        $count = \local_moderncommerce\api\cart_api::cleanup_old_carts(7);
        if ($count > 0) {
            mtrace("Cleaned up $count old carts");
        } else {
            mtrace("No old carts to clean up");
        }
    }
}
