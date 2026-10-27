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
 * External API deleting a storefront widget (cascades slides + uploaded images).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\storefront;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\persistent\widget_slide;

/**
 * Deletes a storefront widget and any child slides + their uploaded files.
 */
class delete_storefront_widget extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Widget id.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Widget id.
     * @return array
     */
    public static function execute(int $id): array {
        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $w = widget::get_record(['id' => $params['id']], MUST_EXIST);

        // Cascade slides + their uploaded slide images.
        if ((string) $w->get('type') === 'slider') {
            $fs = get_file_storage();
            foreach (widget_slide::get_records(['instanceid' => (int) $w->get('id')]) as $slide) {
                $fs->delete_area_files($context->id, 'local_moderncommerce', 'slideimage', (int) $slide->get('id'));
                $slide->delete();
            }
        }

        $w->delete();

        return [
            'success' => true,
            'message' => get_string('widget_deleted', 'local_moderncommerce'),
            'warnings' => [],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the widget was deleted.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
