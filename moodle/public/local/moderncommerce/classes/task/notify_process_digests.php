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
 * Flushes accumulated notification digest items (daily, and weekly on Mondays).
 *
 * Digest accumulation is introduced with admin operational alerts; until a
 * producer accumulates rows this is a safe no-op.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify_process_digests extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('notify_task_process_digests', 'local_moderncommerce');
    }

    #[\Override]
    public function execute() {
        global $DB;

        $pending = $DB->count_records('local_moderncommerce_notify_digest', ['status' => 'pending']);
        if ($pending === 0) {
            return;
        }
        // Rollup implementation lands with admin digests; nothing to flush yet.
        mtrace("local_moderncommerce notifications: {$pending} digest items pending.");
    }
}
