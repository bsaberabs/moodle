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
 * External API saving Modern Notify channel settings from the Modern Commerce shell.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\notifications;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Saves Modern Commerce notification send and webhook channel settings.
 */
class save_settings extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'batchsize' => new external_value(PARAM_INT, 'Send batch size.', VALUE_DEFAULT, 100),
            'slack_enabled' => new external_value(PARAM_BOOL, 'Whether Slack delivery is enabled.', VALUE_DEFAULT, false),
            'slack_url' => new external_value(PARAM_TEXT, 'Slack webhook URL.', VALUE_DEFAULT, ''),
            'slack_secret' => new external_value(PARAM_TEXT, 'New Slack signing secret.', VALUE_DEFAULT, ''),
            'teams_enabled' => new external_value(PARAM_BOOL, 'Whether Teams delivery is enabled.', VALUE_DEFAULT, false),
            'teams_url' => new external_value(PARAM_TEXT, 'Teams webhook URL.', VALUE_DEFAULT, ''),
            'teams_secret' => new external_value(PARAM_TEXT, 'New Teams signing secret.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $batchsize Send batch size.
     * @param bool $slackenabled Slack enabled.
     * @param string $slackurl Slack webhook URL.
     * @param string $slacksecret New Slack secret.
     * @param bool $teamsenabled Teams enabled.
     * @param string $teamsurl Teams webhook URL.
     * @param string $teamssecret New Teams secret.
     * @return array
     */
    public static function execute(
        int $batchsize = 100,
        bool $slackenabled = false,
        string $slackurl = '',
        string $slacksecret = '',
        bool $teamsenabled = false,
        string $teamsurl = '',
        string $teamssecret = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'batchsize' => $batchsize,
            'slack_enabled' => $slackenabled,
            'slack_url' => $slackurl,
            'slack_secret' => $slacksecret,
            'teams_enabled' => $teamsenabled,
            'teams_url' => $teamsurl,
            'teams_secret' => $teamssecret,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managenotifications', $context);

        set_config('notify_batchsize', max(1, (int) $params['batchsize']), 'local_moderncommerce');

        set_config('notify_slack_enabled', !empty($params['slack_enabled']) ? 1 : 0, 'local_moderncommerce');
        set_config('notify_slack_url', trim((string) $params['slack_url']), 'local_moderncommerce');
        $slacksecret = trim((string) $params['slack_secret']);
        if ($slacksecret !== '') {
            set_config('notify_slack_secret', $slacksecret, 'local_moderncommerce');
        }

        set_config('notify_teams_enabled', !empty($params['teams_enabled']) ? 1 : 0, 'local_moderncommerce');
        set_config('notify_teams_url', trim((string) $params['teams_url']), 'local_moderncommerce');
        $teamssecret = trim((string) $params['teams_secret']);
        if ($teamssecret !== '') {
            set_config('notify_teams_secret', $teamssecret, 'local_moderncommerce');
        }

        return [
            'success' => true,
            'message' => get_string('settingssaved', 'local_moderncommerce'),
            'settings' => [
                'batchsize' => max(1, (int) get_config('local_moderncommerce', 'notify_batchsize')),
                'slack_enabled' => (bool) get_config('local_moderncommerce', 'notify_slack_enabled'),
                'slack_url' => (string) get_config('local_moderncommerce', 'notify_slack_url'),
                'slack_secret_set' => (bool) get_config('local_moderncommerce', 'notify_slack_secret'),
                'teams_enabled' => (bool) get_config('local_moderncommerce', 'notify_teams_enabled'),
                'teams_url' => (string) get_config('local_moderncommerce', 'notify_teams_url'),
                'teams_secret_set' => (bool) get_config('local_moderncommerce', 'notify_teams_secret'),
            ],
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the settings were saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'settings' => get_notifications::settings_structure(),
            'warnings' => new external_warnings(),
        ]);
    }
}
