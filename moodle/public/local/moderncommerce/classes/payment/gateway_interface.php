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
 * Payment gateway interface
 *
 * All payment gateways must implement this interface
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface gateway_interface {
    /**
     * Initialize payment
     *
     * @param object $order Order record
     * @return array Payment initialization data (contains authorization_url or redirect_url)
     */
    public function initialize_payment($order);

    /**
     * Verify payment
     *
     * @param string $reference Payment reference
     * @return object Payment verification data
     */
    public function verify_payment($reference);

    /**
     * Process webhook
     *
     * @param array $payload Webhook payload
     * @param array $headers Request headers
     * @param string|null $rawpayload Raw request body
     * @return bool Success status
     */
    public function process_webhook($payload, array $headers = [], ?string $rawpayload = null);
    /**
     * Get gateway configuration
     *
     * @return array Configuration settings
     */
    public function get_config();

    /**
     * Get gateway name
     *
     * @return string Gateway name
     */
    public function get_name();

    /**
     * Check if gateway is enabled
     *
     * @return bool
     */
    public function is_enabled();

    /**
     * Get supported currencies
     *
     * @return array Array of currency codes
     */
    public function get_supported_currencies();
}
