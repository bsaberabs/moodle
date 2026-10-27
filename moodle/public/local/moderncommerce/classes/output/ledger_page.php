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
 * Shared renderer for the generic React admin ledger app.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;

/**
 * Builds the generic ledger React mount used by payment_events / webhook_events / audit_log.
 */
class ledger_page {
    /**
     * Render a ledger page.
     *
     * @param object $output Page output renderer.
     * @param string $activenav Active admin nav key.
     * @param string $methodname List web-service function name.
     * @param string $title Page title.
     * @param string $subtitle Page subtitle.
     * @param array $statusoptions Status filter options (each ['value','label']); empty hides the filter.
     * @return string Rendered shell HTML.
     */
    public static function render(
        object $output,
        string $activenav,
        string $methodname,
        string $title,
        string $subtitle,
        array $statusoptions = []
    ): string {
        $config = json_encode([
            'component' => '@moodle/lms/local_moderncommerce/ledger_admin',
            'id' => 'moderncommerce-ledger-app',
            'class' => 'local-moderncommerce-ledger',
            'props' => [
                'methodName' => $methodname,
                'statusOptions' => array_values($statusoptions),
                'perPageOptions' => [10, 25, 50, 100],
                'labels' => [
                    'title' => $title,
                    'search' => get_string('search'),
                    'allgateways' => get_string('allgateways', 'local_moderncommerce'),
                    'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
                    'gateway' => get_string('gateway', 'local_moderncommerce'),
                    'status' => get_string('status', 'local_moderncommerce'),
                    'perpage' => get_string('perpage', 'local_moderncommerce'),
                    'showing' => get_string('showing', 'local_moderncommerce'),
                    'page' => get_string('page', 'local_moderncommerce'),
                    'previous' => get_string('previous'),
                    'next' => get_string('next'),
                    'noevents' => get_string('noevents', 'local_moderncommerce'),
                    'loading' => get_string('loading', 'local_moderncommerce'),
                    'showdetails' => get_string('showdetails', 'local_moderncommerce'),
                    'hidedetails' => get_string('hidedetails', 'local_moderncommerce'),
                ],
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $contenthtml = $output->render_from_template('local_moderncommerce/admin/ledger', [
            'ledgerreactconfig' => $config,
        ]);

        $actionshtml = admin_shell::action_group([
            [
                'type' => 'button',
                'label' => get_string('refresh'),
                'icon' => 'bi-arrow-clockwise',
                'attributes' => ['id' => 'moderncommerce-ledger-refresh'],
            ],
        ]);

        return admin_shell::render_page($output, $activenav, $title, $contenthtml, $subtitle, $actionshtml);
    }
}
