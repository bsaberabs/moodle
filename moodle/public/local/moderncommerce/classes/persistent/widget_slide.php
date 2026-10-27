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
 * Persistent for a slide belonging to a slider widget.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\persistent;

use core\persistent;

/**
 * Represents a single slide within a slider widget instance.
 */
class widget_slide extends persistent {
    /** @var string Table name. */
    const TABLE = 'local_moderncommerce_widget_slide';

    /**
     * Property definitions.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'instanceid' => [
                'type' => PARAM_INT,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'image' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'heading' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'subheading' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'ctalabel' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'ctaurl' => [
                'type' => PARAM_RAW,
                'default' => '',
            ],
            'ctastyle' => [
                'type' => PARAM_ALPHA,
                'default' => 'primary',
                'choices' => ['primary', 'light', 'outline'],
            ],
            'bgcolor' => [
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            'enabled' => [
                'type' => PARAM_INT,
                'default' => 1,
            ],
        ];
    }
}
