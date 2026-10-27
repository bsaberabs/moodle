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

/**
 * Generic Modern Commerce audit event.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\event;

/**
 * Mirrors structured audit records into Moodle's standard log API.
 */
class audit_event extends \core\event\base {
    /**
     * Initialise event metadata.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_moderncommerce_audit_log';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:auditevent', 'local_moderncommerce');
    }

    /**
     * Event description.
     *
     * @return string
     */
    public function get_description() {
        $action = $this->other['action'] ?? 'unknown';
        $entitytype = $this->other['entitytype'] ?? 'unknown';
        $entityid = (int) ($this->other['entityid'] ?? 0);
        $result = $this->other['result'] ?? 'success';

        return "Modern Commerce audit action '{$action}' on {$entitytype} #{$entityid} completed with result '{$result}'.";
    }

    /**
     * Event URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/moderncommerce/admin/audit_log.php');
    }

    /**
     * Validate event payload.
     */
    protected function validate_data() {
        parent::validate_data();

        if (empty($this->other['action']) || empty($this->other['entitytype'])) {
            throw new \coding_exception('Modern Commerce audit events require action and entitytype.');
        }
    }
}
