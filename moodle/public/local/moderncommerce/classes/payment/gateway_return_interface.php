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

namespace local_moderncommerce\payment;


/**
 * Handles browser returns from hosted payment gateways.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface gateway_return_interface {
    /**
     * Process a gateway browser return.
     *
     * Implementations should verify or capture the payment and return a normalized result object.
     * Expected fields are: status, orderid, orderreference, gatewayreference,
     * gatewaytransactionid, rawdata, paidat, channel, and message.
     *
     * @param array $params Request parameters from the return URL.
     * @return \stdClass Normalized payment result.
     */
    public function process_return(array $params): \stdClass;
}
