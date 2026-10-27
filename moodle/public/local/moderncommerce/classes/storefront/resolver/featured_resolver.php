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
 * Resolver for the featured / related products widget.
 *
 * Emits the configuration the React product carousel needs to fetch items from
 * Modern Commerce's catalog web service and render them with add-to-cart.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use local_moderncommerce\branding;
use local_moderncommerce\persistent\widget;

/**
 * Builds the configuration payload for a featured/related products widget.
 */
class featured_resolver implements widget_resolver {
    /**
     * Build the product carousel configuration.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (e.g. ['courseid' => 42]).
     * @return array
     */
    public function resolve(widget $instance, array $context): array {
        $settings = $instance->get_resolved_settings_array();

        $coursetype = (string)($settings['coursetype'] ?? '');
        if (!in_array($coursetype, ['', 'Course', 'Bundle', 'Program'], true)) {
            $coursetype = '';
        }

        $sort = (string)($settings['sort'] ?? 'popular');
        if (!in_array($sort, ['popular', 'newest', 'pricelow', 'pricehigh'], true)) {
            $sort = 'popular';
        }

        $layout = (string)($settings['layout'] ?? 'carousel');
        if (!in_array($layout, ['carousel', 'grid'], true)) {
            $layout = 'carousel';
        }

        $align = (string)($settings['align'] ?? 'left');
        if (!in_array($align, ['left', 'center'], true)) {
            $align = 'left';
        }

        $navposition = (string)($settings['navposition'] ?? 'topright');
        $validnav = ['topleft', 'topcenter', 'topright', 'bottomleft', 'bottomcenter', 'bottomright'];
        if (!in_array($navposition, $validnav, true)) {
            $navposition = 'topright';
        }

        return [
            'method' => 'local_moderncommerce_get_catalog',
            'cartmethod' => 'local_moderncommerce_update_cart',
            'wishlistmethod' => 'local_moderncommerce_update_learner_wishlist',
            'filters' => [
                'coursetype' => $coursetype,
                'categoryid' => max(0, (int)($settings['categoryid'] ?? 0)),
                'sort' => $sort,
                'perpage' => min(24, max(2, (int)($settings['perpage'] ?? 8))),
            ],
            'layout' => $layout,
            'align' => $align,
            'navposition' => $navposition,
            'columns' => min(5, max(2, (int)($settings['columns'] ?? 4))),
            'buttoncolor' => branding::runtime_colour((string)($settings['buttoncolor'] ?? ''), '', []),
            'buttontextcolor' => branding::runtime_colour((string)($settings['buttontextcolor'] ?? ''), '', []),
            'cardbgcolor' => branding::runtime_colour((string)($settings['cardbgcolor'] ?? ''), '', []),
            'cardbordercolor' => branding::runtime_colour((string)($settings['cardbordercolor'] ?? ''), '', []),
            'cardborderwidth' => min(24, max(0, (int)($settings['cardborderwidth'] ?? 0))),
            'labels' => [
                'addtocart' => get_string('addtocart', 'local_moderncommerce'),
                'viewdetails' => get_string('viewdetails', 'local_moderncommerce'),
                'bestseller' => get_string('bestseller', 'local_moderncommerce'),
                'owned' => get_string('owned', 'local_moderncommerce'),
                'loginrequired' => get_string('loginrequired', 'local_moderncommerce'),
                'savetowishlist' => get_string('savetowishlist', 'local_moderncommerce'),
                'removefromwishlist' => get_string('removefromwishlist', 'local_moderncommerce'),
            ],
        ];
    }
}
