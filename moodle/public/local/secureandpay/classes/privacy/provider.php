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

namespace local_secureandpay\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider: this plugin stores nothing itself, payment records live in Modern Commerce.
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\data_provider {

    /**
     * Data sent to SecureAndPay.
     *
     * @param collection $collection Collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('secureandpay', [
            'email' => 'privacy:metadata:secureandpay:email',
            'firstname' => 'privacy:metadata:secureandpay:firstname',
            'lastname' => 'privacy:metadata:secureandpay:lastname',
            'phone' => 'privacy:metadata:secureandpay:phone',
            'address' => 'privacy:metadata:secureandpay:address',
            'order' => 'privacy:metadata:secureandpay:order',
        ], 'privacy:metadata:secureandpay');
        return $collection;
    }
}
