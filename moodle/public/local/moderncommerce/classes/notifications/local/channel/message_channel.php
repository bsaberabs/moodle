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
 * In-app (Moodle notification bell) delivery via message_send().
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_channel implements channel_interface {
    #[\Override]
    public function key(): string {
        return 'inapp';
    }

    #[\Override]
    public function label(): string {
        return 'In-app notification';
    }

    #[\Override]
    public function is_enabled(): bool {
        return true;
    }

    #[\Override]
    public function is_endpoint(): bool {
        return false;
    }

    #[\Override]
    public function send(
        \stdClass $recipient,
        \stdClass $from,
        string $subject,
        string $plain,
        string $body,
        array $context
    ): bool {
        $message = new \core\message\message();
        $message->component = 'local_moderncommerce';
        $message->name = $context['provider'] ?? 'commerce';
        $message->userfrom = $from;
        $message->userto = $recipient;
        $message->subject = $subject;
        $message->fullmessage = $plain;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $body;
        $message->smallmessage = $subject;
        $message->notification = 1;

        $row = $context['row'] ?? null;
        if ($row && !empty($row->contexturl)) {
            $message->contexturl = $row->contexturl;
            $message->contexturlname = get_string('pluginname', 'local_moderncommerce');
        }

        return (bool) message_send($message);
    }
}
