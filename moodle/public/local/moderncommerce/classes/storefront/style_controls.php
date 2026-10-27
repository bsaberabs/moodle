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
 * Shared sanitizers for storefront widget visual controls.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

use local_moderncommerce\branding;
use moodle_url;

/**
 * Small helper for type-specific visual controls used by widget resolvers.
 */
final class style_controls {
    /**
     * Sanitise a runtime colour token.
     *
     * @param mixed $value Raw value.
     * @param string $fallback Fallback when the value is blank/invalid.
     * @return string
     */
    public static function colour($value, string $fallback = ''): string {
        return branding::runtime_colour((string) $value, $fallback, []);
    }

    /**
     * Clamp a numeric visual value.
     *
     * @param mixed $value Raw value.
     * @param int $default Default value when blank/non-numeric.
     * @param int $min Minimum value.
     * @param int $max Maximum value.
     * @return int
     */
    public static function number($value, int $default = 0, int $min = 0, int $max = 240): int {
        if ($value === '' || $value === null || !is_numeric($value)) {
            $value = $default;
        }
        return min($max, max($min, (int) round((float) $value)));
    }

    /**
     * Normalise a Bootstrap icon name.
     *
     * @param mixed $value Raw value.
     * @param string $fallback Fallback icon.
     * @return string
     */
    public static function icon($value, string $fallback = 'check-circle'): string {
        $icon = preg_replace('/^bi-/', '', preg_replace('/^bi\s+/', '', trim((string) $value)));
        return $icon !== '' ? $icon : $fallback;
    }

    /**
     * Resolve a stored URL or pluginfile path into a browser URL.
     *
     * @param mixed $value Raw URL/path.
     * @return string
     */
    public static function url($value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (
            preg_match('#^(https?:)?//#i', $value) || $value === '#' || str_starts_with($value, 'mailto:')
                || str_starts_with($value, 'tel:')
        ) {
            return $value;
        }
        if ($value[0] === '/') {
            return (new moodle_url($value))->out(false);
        }
        return $value;
    }

    /**
     * Resolve image controls that support URL/upload modes and old single-field data.
     *
     * @param array $settings Widget or row settings.
     * @param string $sourcekey Source selector key.
     * @param string $urlkey URL field key.
     * @param string $filekey Upload field key.
     * @param string $legacykey Legacy combined image key.
     * @return string
     */
    public static function sourced_image(
        array $settings,
        string $sourcekey,
        string $urlkey,
        string $filekey,
        string $legacykey = 'image'
    ): string {
        $source = (string) ($settings[$sourcekey] ?? '');
        if ($source === 'upload') {
            return self::url($settings[$filekey] ?? $settings[$legacykey] ?? '');
        }
        if ($source === 'url') {
            return self::url($settings[$urlkey] ?? $settings[$legacykey] ?? '');
        }
        $uploaded = self::url($settings[$filekey] ?? '');
        if ($uploaded !== '') {
            return $uploaded;
        }
        return self::url($settings[$legacykey] ?? $settings[$urlkey] ?? '');
    }
}
