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
 * Microsoft Teams incoming-webhook channel (ops channel posts).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teams_channel extends outbound_http_channel {
    #[\Override]
    public function key(): string {
        return 'teams';
    }

    #[\Override]
    public function label(): string {
        return 'Microsoft Teams (ops channel)';
    }

    #[\Override]
    protected function setting_prefix(): string {
        return 'teams';
    }

    #[\Override]
    protected function format_payload(string $subject, string $plain, array $context): array {
        // Legacy MessageCard format, accepted by Teams incoming-webhook connectors.
        $card = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'summary' => $subject,
            'themeColor' => '0F6CBF',
            'title' => $subject,
            'text' => $plain,
        ];
        $row = $context['row'] ?? null;
        if ($row && !empty($row->contexturl)) {
            $card['potentialAction'] = [[
                '@type' => 'OpenUri',
                'name' => 'Open',
                'targets' => [['os' => 'default', 'uri' => $row->contexturl]],
            ]];
        }
        return $card;
    }
}
