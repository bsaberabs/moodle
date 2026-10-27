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
 * Resolver for the instructor spotlight widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\style_controls;
use moodle_url;

/**
 * Builds the instructor list payload for an instructor spotlight widget.
 */
class instructors_resolver implements widget_resolver {
    /**
     * Build the instructors payload.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (unused).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();
        $instructors = [];

        foreach (($settings['instructors'] ?? []) as $person) {
            $name = trim((string)($person['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $photo = style_controls::sourced_image($person, 'photosource', 'photourl', 'photofile', 'photo');
            if ($photo !== '' && !preg_match('#^https?://#i', $photo) && $photo[0] === '/') {
                $photo = (new moodle_url($photo))->out(false);
            }
            $instructors[] = [
                'name' => $name,
                'role' => trim((string)($person['role'] ?? '')),
                'bio' => trim((string)($person['bio'] ?? '')),
                'photo' => $photo,
            ];
        }

        return [
            'instructors' => $instructors,
            'bgcolor' => style_controls::colour($settings['bgcolor'] ?? ''),
            'titlecolor' => style_controls::colour($settings['titlecolor'] ?? ''),
            'titlefontsize' => style_controls::number($settings['titlefontsize'] ?? 0, 0, 0, 96),
            'subtitlecolor' => style_controls::colour($settings['subtitlecolor'] ?? ''),
            'subtitlefontsize' => style_controls::number($settings['subtitlefontsize'] ?? 0, 0, 0, 96),
            'cardbgcolor' => style_controls::colour($settings['cardbgcolor'] ?? ''),
            'cardbordercolor' => style_controls::colour($settings['cardbordercolor'] ?? ''),
            'cardborderwidth' => style_controls::number($settings['cardborderwidth'] ?? 1, 1, 0, 24),
            'cardradius' => style_controls::number($settings['cardradius'] ?? 8, 8, 0, 96),
            'avatarbgcolor' => style_controls::colour($settings['avatarbgcolor'] ?? ''),
            'avatarcolor' => style_controls::colour($settings['avatarcolor'] ?? ''),
            'namecolor' => style_controls::colour($settings['namecolor'] ?? ''),
            'namefontsize' => style_controls::number($settings['namefontsize'] ?? 0, 0, 0, 96),
            'rolecolor' => style_controls::colour($settings['rolecolor'] ?? ''),
            'rolefontsize' => style_controls::number($settings['rolefontsize'] ?? 0, 0, 0, 96),
            'biocolor' => style_controls::colour($settings['biocolor'] ?? ''),
            'biofontsize' => style_controls::number($settings['biofontsize'] ?? 0, 0, 0, 96),
            'paddingtop' => style_controls::number($settings['paddingtop'] ?? 0),
            'paddingbottom' => style_controls::number($settings['paddingbottom'] ?? 0),
        ];
    }
}
