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
 * Helper for the single home hero slider instance.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront;

use local_moderncommerce\persistent\widget;
use local_moderncommerce\persistent\widget_slide;

/**
 * Convenience access to the catalog home hero slider for the admin UI.
 */
class hero {
    /**
     * Get the home hero slider instance, creating it if needed.
     *
     * @return widget
     */
    public static function get_slider(): widget {
        $existing = widget::get_record([
            'type' => 'slider',
            'zone' => zones::HOME_HERO,
            'pagetype' => zones::PAGE_CATALOG,
        ]);

        if ($existing) {
            return $existing;
        }

        $instance = new widget();
        $instance->set('type', 'slider');
        $instance->set('zone', zones::HOME_HERO);
        $instance->set('pagetype', zones::PAGE_CATALOG);
        $instance->set('title', 'Storefront hero');
        $instance->set('enabled', 1);
        $instance->set('settings', json_encode([
            'autoplay' => true,
            'interval' => 6000,
            'showarrows' => true,
            'showdots' => true,
        ]));
        $instance->create();

        return $instance;
    }

    /**
     * Slides for the hero slider, ordered for the admin list.
     *
     * @param int $instanceid Hero slider instance ID.
     * @return widget_slide[]
     */
    public static function slides(int $instanceid): array {
        return widget_slide::get_records(['instanceid' => $instanceid], 'sortorder', 'ASC');
    }
}
