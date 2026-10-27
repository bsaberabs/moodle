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
 * External API resolving and previewing any redeemable key.
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
use local_moderncommerce\subscription\api\plan_api;

/**
 * Detect which kind of key a code is and preview what redeeming it would grant.
 */
class resolve_key extends external_api {
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
        ]);
    }

    /**
     * Execute.
     *
     * @param string $keycode Key code.
     * @return array
     */
    public static function execute(string $keycode): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['keycode' => $keycode]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);

        $userid = (int) $USER->id;
        $resolved = key_resolver_service::resolve($params['keycode']);

        if ($resolved['type'] === key_resolver_service::TYPE_ENROLLMENT) {
            return self::enrollment_preview($resolved['key'], $userid, $context);
        }

        if ($resolved['type'] === key_resolver_service::TYPE_SUBSCRIPTION) {
            return self::subscription_preview($resolved['key'], $userid, $context);
        }

        // Type-neutral: the code matched no table, so naming one key type would mislead.
        return self::response(
            key_resolver_service::TYPE_UNKNOWN,
            false,
            get_string('keynotrecognised', 'local_moderncommerce')
        );
    }

    /**
     * Preview an enrollment key.
     *
     * @param \stdClass $key Key record.
     * @param int $userid User ID.
     * @param context_system $context Context.
     * @return array
     */
    private static function enrollment_preview(\stdClass $key, int $userid, context_system $context): array {
        if (!has_capability('local/moderncommerce:redeemkey', $context)) {
            return self::response(
                key_resolver_service::TYPE_ENROLLMENT,
                false,
                get_string('nopermissions', 'error', get_string('redeemkey', 'local_moderncommerce'))
            );
        }

        $preview = key_redemption_service::preview((string) $key->keycode, $userid);

        return self::response(
            key_resolver_service::TYPE_ENROLLMENT,
            (bool) $preview['valid'],
            $preview['errorkey'] !== null ? get_string($preview['errorkey'], 'local_moderncommerce') : '',
            array_map(static function (array $course): array {
                return [
                    'id' => $course['id'],
                    'fullname' => $course['fullname'],
                    'alreadyenrolled' => $course['alreadyenrolled'],
                    'url' => $course['url'],
                ];
            }, $preview['courses'])
        );
    }

    /**
     * Preview a subscription key.
     *
     * @param \stdClass $key Key record.
     * @param int $userid User ID.
     * @param context_system $context Context.
     * @return array
     */
    private static function subscription_preview(\stdClass $key, int $userid, context_system $context): array {
        if (!has_capability('local/moderncommerce:subscribetoplan', $context)) {
            return self::response(
                key_resolver_service::TYPE_SUBSCRIPTION,
                false,
                get_string('nopermissions', 'error', get_string('redeemkey', 'local_moderncommerce'))
            );
        }

        $plan = plan_api::get((int) $key->planid, false);
        if (!$plan || (string) $plan->status !== 'active') {
            return self::response(
                key_resolver_service::TYPE_SUBSCRIPTION,
                false,
                get_string('invalidplan', 'local_moderncommerce')
            );
        }

        $errorkey = self::subscription_error($key, $userid);

        return self::response(
            key_resolver_service::TYPE_SUBSCRIPTION,
            $errorkey === null,
            $errorkey !== null ? get_string($errorkey, 'local_moderncommerce') : '',
            [],
            format_string((string) $plan->name)
        );
    }

    /**
     * Language error key for an unredeemable subscription key, or null when valid.
     *
     * Mirrors the validation performed at redemption time so the preview cannot
     * promise something the redeem call would then refuse.
     *
     * @param \stdClass $key Key record.
     * @param int $userid User ID.
     * @return string|null
     */
    private static function subscription_error(\stdClass $key, int $userid): ?string {
        global $DB;

        if ((string) $key->status !== 'active') {
            return 'keyinactive';
        }

        $now = time();
        if (!empty($key->startdate) && $now < (int) $key->startdate) {
            return 'keynotstarted';
        }
        if (!empty($key->expirydate) && $now > (int) $key->expirydate) {
            return 'keyexpired';
        }

        $usedcount = (int) $DB->count_records('local_moderncommerce_subscription_key_usage', ['keyid' => $key->id]);
        if ((int) $key->maxuses > 0 && $usedcount >= (int) $key->maxuses) {
            return 'keymaxuses';
        }

        $useruses = (int) $DB->count_records('local_moderncommerce_subscription_key_usage', [
            'keyid' => $key->id,
            'userid' => $userid,
        ]);
        if ((int) $key->maxusesperuser > 0 && $useruses >= (int) $key->maxusesperuser) {
            return 'keyalreadyused';
        }

        return null;
    }

    /**
     * Build a uniform response.
     *
     * @param string $type Resolved key type.
     * @param bool $valid Whether the key can be redeemed.
     * @param string $message Message when not valid.
     * @param array $courses Granted courses (enrollment keys only).
     * @param string $planname Granted plan name (subscription keys only).
     * @return array
     */
    private static function response(
        string $type,
        bool $valid,
        string $message,
        array $courses = [],
        string $planname = ''
    ): array {
        return [
            'type' => $type,
            'valid' => $valid,
            'message' => $message,
            'courses' => $courses,
            'planname' => $planname,
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
            'type' => new external_value(PARAM_ALPHA, 'Resolved key type: enrollment, subscription or unknown.'),
            'valid' => new external_value(PARAM_BOOL, 'Whether the key can be redeemed.'),
            'message' => new external_value(PARAM_TEXT, 'Validation message when not valid.'),
            'courses' => new external_multiple_structure(validate_key::course_structure()),
            'planname' => new external_value(PARAM_TEXT, 'Plan the key activates, for subscription keys.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
