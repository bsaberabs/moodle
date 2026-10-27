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

namespace local_moderncommerce\notifications\local\channel;

/**
 * Contract for a delivery channel (email, in-app, Slack, Teams).
 *
 * Adopted from the proven local_modernenrolnotifier channel contract.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface channel_interface {
    /**
     * Stable machine key stored on queue rows (e.g. 'email').
     *
     * @return string
     */
    public function key(): string;

    /**
     * Human-readable label.
     *
     * @return string
     */
    public function label(): string;

    /**
     * Whether this channel is switched on site-wide (and configured, if needed).
     *
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Whether this channel delivers to a configured endpoint rather than a person.
     *
     * Endpoint channels (e.g. an ops Slack webhook) do not require a recipient user.
     *
     * @return bool
     */
    public function is_endpoint(): bool;

    /**
     * Deliver the rendered notification.
     *
     * @param \stdClass $recipient Recipient user (or sender, for endpoint channels).
     * @param \stdClass $from Sender user.
     * @param string $subject Rendered subject.
     * @param string $plain Rendered plain-text body.
     * @param string $body Rendered HTML body.
     * @param array $context Extra context: ['provider' => string, 'row' => \stdClass].
     * @return bool True when accepted for delivery.
     */
    public function send(
        \stdClass $recipient,
        \stdClass $from,
        string $subject,
        string $plain,
        string $body,
        array $context
    ): bool;
}
