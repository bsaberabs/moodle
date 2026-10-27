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
 * Shared renderer for the React enrollment keys admin app.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;

/**
 * Builds the keys admin React mount used by keys.php, course_keys.php, and bundle_keys.php.
 */
class keys_page {
    /**
     * Render the keys admin shell.
     *
     * @param object $output Page output renderer (or pre-header bootstrap renderer).
     * @param string $targettype Preset target type filter ('', 'course', 'product').
     * @param int $targetid Preset target ID filter (0 = none).
     * @param string $targetname Preset target display name.
     * @param string $activenav Sidebar nav key to highlight ('keys', 'bundlekeys').
     * @param string $title Page heading (defaults to the generic "Manage keys").
     * @param string $subtitle Page subtitle (defaults to the generic keys description).
     * @param string $productkind Restrict listing by enrolment kind ('course', 'bundle', or '' for all).
     * @param bool $generateintableheader Whether to place the generate action in the React table header.
     * @return string Rendered shell HTML.
     */
    public static function render(
        object $output,
        string $targettype = '',
        int $targetid = 0,
        string $targetname = '',
        string $activenav = 'keys',
        string $title = '',
        string $subtitle = '',
        string $productkind = '',
        bool $generateintableheader = false
    ): string {
        if ($title === '') {
            $title = get_string('managekeys', 'local_moderncommerce');
        }
        if ($subtitle === '') {
            $subtitle = get_string('managekeys_desc', 'local_moderncommerce');
        }
        $statusoptions = [
            ['value' => 'active', 'label' => get_string('active', 'local_moderncommerce')],
            ['value' => 'inactive', 'label' => get_string('inactive', 'local_moderncommerce')],
            ['value' => 'expired', 'label' => get_string('expired', 'local_moderncommerce')],
            ['value' => 'depleted', 'label' => get_string('depleted', 'local_moderncommerce')],
        ];

        $config = json_encode([
            'component' => '@moodle/lms/local_moderncommerce/keys_admin',
            'id' => 'moderncommerce-keys-admin-app',
            'class' => 'local-moderncommerce-keys-admin',
            'props' => [
                'listMethodName' => 'local_moderncommerce_admin_list_keys',
                'generateMethodName' => 'local_moderncommerce_admin_generate_keys',
                'setStatusMethodName' => 'local_moderncommerce_admin_set_key_status',
                'searchCoursesMethodName' => 'local_moderncommerce_search_courses',
                'listBundlesMethodName' => 'local_moderncommerce_admin_list_bundles',
                'initialTargetType' => in_array($targettype, ['course', 'product'], true) ? $targettype : '',
                'initialTargetId' => $targetid,
                'initialTargetName' => $targetname,
                'initialProductKind' => in_array($productkind, ['course', 'bundle'], true) ? $productkind : '',
                'showTableGenerateAction' => $generateintableheader,
                'statusOptions' => $statusoptions,
                'perPageOptions' => [10, 20, 50, 100],
                'labels' => self::labels(),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $contenthtml = $output->render_from_template('local_moderncommerce/admin/keys', [
            'keysreactconfig' => $config,
        ]);

        $actions = [
            [
                'type' => 'button',
                'label' => get_string('refresh'),
                'icon' => 'bi-arrow-clockwise',
                'attributes' => ['id' => 'moderncommerce-keys-refresh'],
            ],
        ];
        if (!$generateintableheader) {
            $actions[] = [
                'type' => 'button',
                'label' => get_string('generatekeys', 'local_moderncommerce'),
                'icon' => 'bi-key',
                'primary' => true,
                'attributes' => ['id' => 'moderncommerce-keys-generate'],
            ];
        }
        $actionshtml = admin_shell::action_group($actions);

        return admin_shell::render_page(
            $output,
            $activenav,
            $title,
            $contenthtml,
            $subtitle,
            $actionshtml
        );
    }

    /**
     * Build the localisation map passed to the React app.
     *
     * @return array
     */
    private static function labels(): array {
        return [
            'title' => get_string('managekeys', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchplaceholder' => get_string('searchkeys', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
            'targettype' => get_string('targettype', 'local_moderncommerce'),
            'alltargets' => get_string('alltargets', 'local_moderncommerce'),
            'course' => get_string('course'),
            'bundleorprogram' => get_string('bundleorprogram', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'next' => get_string('next'),
            'keycode' => get_string('keycode', 'local_moderncommerce'),
            'keytype' => get_string('keytype', 'local_moderncommerce'),
            'target' => get_string('target', 'local_moderncommerce'),
            'uses' => get_string('uses', 'local_moderncommerce'),
            'expiry' => get_string('expiry', 'local_moderncommerce'),
            'created' => get_string('created', 'local_moderncommerce'),
            'createdby' => get_string('createdby', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'activate' => get_string('activate', 'local_moderncommerce'),
            'deactivate' => get_string('deactivate', 'local_moderncommerce'),
            'delete' => get_string('delete'),
            'copy' => get_string('copykey', 'local_moderncommerce'),
            'copied' => get_string('copied', 'local_moderncommerce'),
            'exportkeys' => get_string('exportkeys', 'local_moderncommerce'),
            'nokeys' => get_string('nokeysfound', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'deleteconfirm' => get_string('deactivatekeyconfirm', 'local_moderncommerce'),
            'totalkeys' => get_string('totalkeys', 'local_moderncommerce'),
            'activekeys' => get_string('activekeys', 'local_moderncommerce'),
            'usedkeys' => get_string('usedkeys', 'local_moderncommerce'),
            'expiredkeys' => get_string('expiredkeys', 'local_moderncommerce'),
            'keysettings' => get_string('keysettings', 'local_moderncommerce'),
            'selecttarget' => get_string('selecttarget', 'local_moderncommerce'),
            'searchcoursesplaceholder' => get_string('searchcoursesplaceholder', 'local_moderncommerce'),
            'searchbundles' => get_string('searchbundles', 'local_moderncommerce'),
            'quantity' => get_string('quantity', 'local_moderncommerce'),
            'singleuse' => get_string('singleuse', 'local_moderncommerce'),
            'multiuse' => get_string('multiuse', 'local_moderncommerce'),
            'maxuses' => get_string('maxuses', 'local_moderncommerce'),
            'validdays' => get_string('validdays', 'local_moderncommerce'),
            'noexpiry' => get_string('noexpiry', 'local_moderncommerce'),
            'generate' => get_string('generatekeys', 'local_moderncommerce'),
            'generatedkeys' => get_string('generatedkeys', 'local_moderncommerce'),
            'cancel' => get_string('cancel'),
        ];
    }
}
