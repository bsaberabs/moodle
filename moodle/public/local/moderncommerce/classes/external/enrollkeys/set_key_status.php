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
 * External API changing an enrollment key status.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\enrollkeys;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Activate, deactivate, or delete an enrollment key.
 *
 * Keys with redemption history are deactivated rather than deleted to preserve the audit trail.
 */
class set_key_status extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'keyid' => new external_value(PARAM_INT, 'Key ID.'),
            'action' => new external_value(PARAM_ALPHA, 'Action: activate, deactivate, or delete.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $keyid Key ID.
     * @param string $action Action.
     * @return array
     */
    public static function execute(int $keyid, string $action): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'keyid' => $keyid,
            'action' => $action,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:generatekeys', $context);

        $key = $DB->get_record('local_moderncommerce_enrollkeys', ['id' => $params['keyid']], '*', MUST_EXIST);

        switch ($params['action']) {
            case 'activate':
                $DB->set_field('local_moderncommerce_enrollkeys', 'status', 'active', ['id' => $key->id]);
                $DB->set_field('local_moderncommerce_enrollkeys', 'timemodified', time(), ['id' => $key->id]);
                self::audit_key_action('enrollment_key_activated', $key, ['status' => 'active']);
                return self::result(true, get_string('keyactivated', 'local_moderncommerce'), 'active');

            case 'deactivate':
                $DB->set_field('local_moderncommerce_enrollkeys', 'status', 'inactive', ['id' => $key->id]);
                $DB->set_field('local_moderncommerce_enrollkeys', 'timemodified', time(), ['id' => $key->id]);
                self::audit_key_action('enrollment_key_deactivated', $key, ['status' => 'inactive']);
                return self::result(true, get_string('keydeactivated', 'local_moderncommerce'), 'inactive');

            case 'delete':
                if ($DB->record_exists('local_moderncommerce_key_usage', ['enrollkeyid' => $key->id])) {
                    // Preserve redeemed keys: deactivate instead of deleting.
                    $DB->set_field('local_moderncommerce_enrollkeys', 'status', 'inactive', ['id' => $key->id]);
                    $DB->set_field('local_moderncommerce_enrollkeys', 'timemodified', time(), ['id' => $key->id]);
                    self::audit_key_action('enrollment_key_deactivated', $key, [
                        'status' => 'inactive',
                        'reason' => 'delete_requested_with_usage',
                    ]);
                    return self::result(true, get_string('keydeactivated', 'local_moderncommerce'), 'inactive');
                }
                $DB->delete_records('local_moderncommerce_enrollkey_targets', ['enrollkeyid' => $key->id]);
                $DB->delete_records('local_moderncommerce_enrollkeys', ['id' => $key->id]);
                self::audit_key_action('enrollment_key_deleted', $key, null);
                return self::result(true, get_string('keydeleted', 'local_moderncommerce'), 'deleted');

            default:
                return self::result(false, get_string('invalidaction', 'local_moderncommerce'), (string) $key->status);
        }
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the action succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'status' => new external_value(PARAM_ALPHA, 'Resulting status (or "deleted").'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Build a result array.
     *
     * @param bool $success Success flag.
     * @param string $message Message.
     * @param string $status Resulting status.
     * @return array
     */
    private static function result(bool $success, string $message, string $status): array {
        return [
            'success' => $success,
            'message' => $message,
            'status' => $status,
            'warnings' => [],
        ];
    }

    /**
     * Record an enrollment key audit action.
     *
     * @param \stdClass $key Existing key record.
     * @param array|null $newdata New data snapshot.
     */
    private static function audit_key_action(string $action, \stdClass $key, ?array $newdata): void {
        \local_moderncommerce\audit\audit_service::record($action, 'enrollment_key', (int) $key->id, [
            'olddata' => $key,
            'newdata' => $newdata,
            'severity' => 'warning',
        ]);
    }
}
