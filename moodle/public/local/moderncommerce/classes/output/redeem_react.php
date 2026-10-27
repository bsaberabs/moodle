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
 * Shared renderer for the React buyer key redemption app.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;

use moodle_url;

/**
 * Builds the React redeem mount used by redeem.php, redeem_multiple.php, and redeem_bundle.php.
 */
class redeem_react {
    /**
     * Render the redeem app mount.
     *
     * @param object $output Page output renderer (or pre-header bootstrap renderer).
     * @param int $orderid Optional originating order ID.
     * @param array|null $layout Optional learner layout context.
     * @param string $activenav Active learner navigation key.
     * @return string Rendered mount HTML.
     */
    public static function render(object $output, int $orderid = 0, ?array $layout = null, string $activenav = 'redeem'): string {
        $config = json_encode([
            'component' => '@moodle/lms/local_moderncommerce/redeem',
            'id' => 'moderncommerce-redeem-app',
            'class' => 'local-moderncommerce-redeem',
            'props' => [
                'validateMethodName' => 'local_moderncommerce_validate_key',
                'redeemMethodName' => 'local_moderncommerce_redeem_key',
                'orderId' => $orderid,
                'catalogUrl' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
                'coursesUrl' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/courses',
                'layout' => $layout,
                'activeNav' => $activenav,
                'labels' => validation_labels::all() + [
                    'error:invalidkeyformat' => get_string('error:invalidkeyformat', 'local_moderncommerce'),
                    'title' => get_string('redeemkey', 'local_moderncommerce'),
                    'intro' => get_string('redeemintro', 'local_moderncommerce'),
                    'help' => get_string('redeemhelp', 'local_moderncommerce'),
                    'keycode' => get_string('keycode', 'local_moderncommerce'),
                    'enterkeycode' => get_string('enterkeycode', 'local_moderncommerce'),
                    'validatekey' => get_string('validatekey', 'local_moderncommerce'),
                    'redeemkey' => get_string('redeemkey', 'local_moderncommerce'),
                    'grantsaccess' => get_string('grantsaccess', 'local_moderncommerce'),
                    'alreadyenrolled' => get_string('alreadyenrolled', 'local_moderncommerce'),
                    'accesscourse' => get_string('accesscourse', 'local_moderncommerce'),
                    'redeemanother' => get_string('redeemanother', 'local_moderncommerce'),
                    'redeemsuccess' => get_string('redeemsuccess', 'local_moderncommerce'),
                    'mycourses' => get_string('mycourses', 'local_moderncommerce'),
                    'browsecatalog' => get_string('browsecatalog', 'local_moderncommerce'),
                    'loading' => get_string('loading', 'local_moderncommerce'),
                    'retry' => get_string('retry', 'local_moderncommerce'),
                ],
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return $output->render_from_template('local_moderncommerce/react_mount', [
            'region' => 'moderncommerce-redeem',
            'reactconfig' => $config,
            'icon' => 'bi-key',
        ]);
    }
}
