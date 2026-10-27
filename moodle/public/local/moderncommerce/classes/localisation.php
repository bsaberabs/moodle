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
 * Localisation helpers for Modern Commerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce;

/**
 * Shared string lookup helpers.
 */
class localisation {
    /**
     * Localised status label with domain-specific key prefixes.
     *
     * @param string $status Stored status.
     * @param array<int, string> $prefixes String-key prefixes to try before generic status keys.
     * @return string
     */
    public static function status_label(string $status, array $prefixes = []): string {
        $normalised = strtolower(trim($status));
        if ($normalised === '') {
            return '';
        }

        $candidates = [];
        foreach ($prefixes as $prefix) {
            $prefix = trim($prefix);
            if ($prefix === '') {
                $candidates[] = $normalised;
                continue;
            }
            $candidates[] = $prefix . '_' . $normalised;
        }
        $candidates[] = 'status_' . $normalised;
        $candidates[] = $normalised;

        foreach (array_unique($candidates) as $key) {
            if (get_string_manager()->string_exists($key, 'local_moderncommerce')) {
                return get_string($key, 'local_moderncommerce');
            }
        }

        return ucfirst(str_replace('_', ' ', $normalised));
    }
}
