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
 * Registry of available delivery channels.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class channel_manager {
    /**
     * All known channels, keyed by machine key.
     *
     * @return channel_interface[]
     */
    public static function all(): array {
        $channels = [
            new email_channel(),
            new message_channel(),
            new slack_channel(),
            new teams_channel(),
        ];

        $map = [];
        foreach ($channels as $channel) {
            $map[$channel->key()] = $channel;
        }

        return $map;
    }

    /**
     * Get a channel by key.
     *
     * @param string $key Channel key.
     * @return channel_interface|null
     */
    public static function get(string $key): ?channel_interface {
        return self::all()[$key] ?? null;
    }
}
