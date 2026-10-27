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
 * Slack incoming-webhook channel (ops channel posts).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class slack_channel extends outbound_http_channel {
    #[\Override]
    public function key(): string {
        return 'slack';
    }

    #[\Override]
    public function label(): string {
        return 'Slack (ops channel)';
    }

    #[\Override]
    protected function setting_prefix(): string {
        return 'slack';
    }

    #[\Override]
    protected function format_payload(string $subject, string $plain, array $context): array {
        // Slack incoming webhooks render mrkdwn. Lead with the subject, then the body.
        $text = '*' . $subject . "*\n" . $plain;
        $row = $context['row'] ?? null;
        if ($row && !empty($row->contexturl)) {
            $text .= "\n<" . $row->contexturl . '|Open>';
        }
        return ['text' => $text];
    }
}
