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
 * Program completion event.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\event;

/**
 * Event emitted once when a learner first satisfies every program completion rule.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_completed extends \core\event\base {
    /**
     * Configure the event metadata.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['objecttable'] = 'local_moderncommerce_program_progress';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Return the localized event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:programcompleted', 'local_moderncommerce');
    }

    /**
     * Return the event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "User {$this->relateduserid} completed program {$this->other['programid']} "
            . "with progress record {$this->objectid}.";
    }

    /**
     * Return the completed program URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        $url = new \moodle_url('/local/moderncommerce/learner/index.php');
        $url->set_anchor('/access/program/' . (int)$this->other['programid']);
        return $url;
    }

    /**
     * Validate required event data.
     *
     * @return void
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (empty($this->relateduserid) || empty($this->other['programid'])) {
            throw new \coding_exception('Program completion events require relateduserid and other[programid].');
        }
    }
}
