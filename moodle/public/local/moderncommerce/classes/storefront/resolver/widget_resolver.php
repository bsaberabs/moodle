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
 * Contract for widget data resolvers.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\storefront\resolver;

use local_moderncommerce\persistent\widget;

/**
 * A resolver turns a stored widget instance into its render payload.
 */
interface widget_resolver {
    /**
     * Build the type-specific data payload for a widget instance.
     *
     * @param widget $instance The widget instance.
     * @param array $context Page context (e.g. ['courseid' => 42]).
     * @return array Associative payload that is JSON-encoded into the widget's `data` field.
     */
    public function resolve(widget $instance, array $context): array;
}
