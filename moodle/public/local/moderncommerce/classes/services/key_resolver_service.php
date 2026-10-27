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
 * Redeemable key type resolution for Modern Commerce.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

/**
 * Resolves which kind of key a learner-supplied code is.
 *
 * Modern Commerce issues two redeemable key types that live in separate tables:
 *
 * - enrollment keys ({local_moderncommerce_enrollkeys}) grant course access, either
 *   by targeting courses directly or by targeting a bundle/program product whose
 *   included courses are expanded at redemption time;
 * - subscription keys ({local_moderncommerce_subscription_keys}) activate a plan.
 *
 * A learner holds a code, not a classification, so the redeem screen asks the server
 * which table owns the code rather than making the learner choose. Enrollment keys are
 * resolved first; the two code spaces are independent, so a code present in both tables
 * is treated as an enrollment key.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class key_resolver_service {
    /** @var string An enrollment key granting course access. */
    public const TYPE_ENROLLMENT = 'enrollment';

    /** @var string A pre-paid subscription key activating a plan. */
    public const TYPE_SUBSCRIPTION = 'subscription';

    /** @var string The code matches no known key. */
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * Resolve a raw learner-supplied code to a key type and record.
     *
     * Performs no capability, validity or ownership checks — it answers only
     * "which table owns this code". Callers must still validate and authorise
     * before redeeming.
     *
     * @param string $keycode Raw key code as typed by the learner.
     * @return array ['type' => string, 'key' => \stdClass|null]
     */
    public static function resolve(string $keycode): array {
        global $DB;

        $code = self::normalise($keycode);
        if ($code === '') {
            return ['type' => self::TYPE_UNKNOWN, 'key' => null];
        }

        $enrollmentkey = $DB->get_record('local_moderncommerce_enrollkeys', ['keycode' => $code]);
        if ($enrollmentkey) {
            return ['type' => self::TYPE_ENROLLMENT, 'key' => $enrollmentkey];
        }

        $subscriptionkey = $DB->get_record('local_moderncommerce_subscription_keys', ['keycode' => $code]);
        if ($subscriptionkey) {
            return ['type' => self::TYPE_SUBSCRIPTION, 'key' => $subscriptionkey];
        }

        return ['type' => self::TYPE_UNKNOWN, 'key' => null];
    }

    /**
     * Normalise a learner-supplied code for lookup.
     *
     * Codes are stored uppercase; learners paste them with stray whitespace and in
     * whatever case their email client rendered.
     *
     * @param string $keycode Raw key code.
     * @return string Normalised code.
     */
    public static function normalise(string $keycode): string {
        return \core_text::strtoupper(trim($keycode));
    }
}
