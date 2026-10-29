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

namespace local_moderncommerce\event;

/**
 * The subscriber cancelled: the subscription stops renewing and ends with the paid period.
 *
 * Oriskill addition (not in upstream Modern Commerce): lets a gateway that bills on its own (SecureAndPay
 * ClicktoPay) stop billing right away, rather than when subscription_cancelled fires at the period's end.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscription_cancel_scheduled extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_moderncommerce_user_subscriptions';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event:subscriptioncancelscheduled', 'local_moderncommerce');
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->relateduserid}' cancelled the subscription with id '{$this->objectid}' "
            . "at the end of its paid period.";
    }

    /**
     * Get URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/moderncommerce/admin/subscriptions.php', ['subscriptionid' => $this->objectid]);
    }
}
