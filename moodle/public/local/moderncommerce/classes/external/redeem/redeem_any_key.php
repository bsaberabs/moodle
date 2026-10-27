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
 * External API redeeming any Modern Commerce key, whatever its type.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\redeem;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\key_redemption_service;
use local_moderncommerce\services\key_resolver_service;
use local_moderncommerce\subscription\external\subscribe_external;

/**
 * Resolve a key code to its type and redeem it through the matching path.
 *
 * The learner holds a code, not a classification, so this endpoint detects whether
 * the code is an enrollment key (grants courses) or a subscription key (activates a
 * plan) and dispatches accordingly, reporting back which happened.
 */
class redeem_any_key extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            // PARAM_TEXT, not PARAM_ALPHANUMEXT: subscription key codes may contain
            // separators such as hyphens, which ALPHANUMEXT would silently strip.
            'keycode' => new external_value(PARAM_TEXT, 'Key code.'),
            'orderid' => new external_value(PARAM_INT, 'Originating order ID.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $keycode Key code.
     * @param int $orderid Order ID.
     * @return array
     */
    public static function execute(string $keycode, int $orderid = 0): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'keycode' => $keycode,
            'orderid' => $orderid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);

        $userid = (int) $USER->id;
        $resolved = key_resolver_service::resolve($params['keycode']);

        if ($resolved['type'] === key_resolver_service::TYPE_UNKNOWN) {
            // Type-neutral: the code matched no table, so naming one key type would mislead.
            return self::failure(
                key_resolver_service::TYPE_UNKNOWN,
                get_string('keynotrecognised', 'local_moderncommerce')
            );
        }

        if ($resolved['type'] === key_resolver_service::TYPE_SUBSCRIPTION) {
            return self::redeem_subscription($resolved['key'], $context);
        }

        require_capability('local/moderncommerce:redeemkey', $context);

        // Only honour an order that belongs to this user.
        $orderid = $params['orderid'] > 0
            && $DB->record_exists('local_moderncommerce_orders', ['id' => $params['orderid'], 'userid' => $userid])
            ? (int) $params['orderid']
            : null;

        try {
            $result = key_redemption_service::redeem((string) $resolved['key']->keycode, $userid, $orderid);
        } catch (\moodle_exception $e) {
            return self::failure(key_resolver_service::TYPE_ENROLLMENT, $e->getMessage());
        }

        return [
            'success' => true,
            'type' => key_resolver_service::TYPE_ENROLLMENT,
            'message' => get_string('keyredeemedcourses', 'local_moderncommerce', count($result['courses'])),
            'courses' => $result['courses'],
            'planname' => '',
            'warnings' => [],
        ];
    }

    /**
     * Redeem a subscription key by delegating to the subscription service.
     *
     * @param \stdClass $key Key record.
     * @param context_system $context Context.
     * @return array
     */
    private static function redeem_subscription(\stdClass $key, context_system $context): array {
        require_capability('local/moderncommerce:subscribetoplan', $context);

        try {
            $result = subscribe_external::redeem_key((string) $key->keycode);
        } catch (\moodle_exception $e) {
            return self::failure(key_resolver_service::TYPE_SUBSCRIPTION, $e->getMessage());
        }

        return [
            'success' => !empty($result['activated']),
            'type' => key_resolver_service::TYPE_SUBSCRIPTION,
            'message' => (string) ($result['message'] ?? ''),
            'courses' => [],
            'planname' => (string) ($result['planname'] ?? ''),
            'warnings' => [],
        ];
    }

    /**
     * Build a failed response.
     *
     * @param string $type Resolved key type.
     * @param string $message Failure message.
     * @return array
     */
    private static function failure(string $type, string $message): array {
        return [
            'success' => false,
            'type' => $type,
            'message' => $message,
            'courses' => [],
            'planname' => '',
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
            'success' => new external_value(PARAM_BOOL, 'Whether the key was redeemed.'),
            'type' => new external_value(PARAM_ALPHA, 'Resolved key type: enrollment, subscription or unknown.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'courses' => new external_multiple_structure(validate_key::course_structure()),
            'planname' => new external_value(PARAM_TEXT, 'Activated plan name, for subscription keys.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
