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
 * External API for rendering the buyer cart dropdown.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\cart;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\api\cart_api;

/**
 * Render current buyer cart dropdown HTML.
 */
class get_dropdown extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute.
     *
     * @return array
     */
    public static function execute(): array {
        global $CFG, $PAGE, $USER;

        self::validate_parameters(self::execute_parameters(), []);
        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:purchase', $context);
        $PAGE->set_context($context);

        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

        return [
            'success' => true,
            'html' => local_moderncommerce_render_cart_dropdown_html((int)$USER->id),
            'count' => cart_api::get_cart_count((int)$USER->id),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded.'),
            'html' => new external_value(PARAM_RAW, 'Rendered cart dropdown HTML.'),
            'count' => new external_value(PARAM_INT, 'Current cart count.'),
        ]);
    }
}
