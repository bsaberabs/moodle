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
 * Shared Moodle reCAPTCHA helpers for public Modern Commerce forms.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

/**
 * Centralises reCAPTCHA v2 configuration and verification.
 */
class captcha_service {
    /** @var string Plugin component. */
    private const COMPONENT = 'local_moderncommerce';

    /**
     * Whether Moodle's global Google reCAPTCHA keys are configured.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        global $CFG;

        return !empty($CFG->recaptchapublickey) && !empty($CFG->recaptchaprivatekey);
    }

    /**
     * Return the browser config used by React storefront forms.
     *
     * @return array
     */
    public static function widget_config(): array {
        global $CFG;

        if (!self::is_enabled()) {
            return [
                'enabled' => false,
                'sitekey' => '',
                'apiurl' => '',
                'lang' => '',
                'requiredmessage' => get_string('captcha_required', self::COMPONENT),
                'errormessage' => get_string('captcha_error', self::COMPONENT),
            ];
        }

        self::require_core_library();

        return [
            'enabled' => true,
            'sitekey' => (string)$CFG->recaptchapublickey,
            'apiurl' => RECAPTCHA_API_URL,
            'lang' => recaptcha_lang(),
            'requiredmessage' => get_string('captcha_required', self::COMPONENT),
            'errormessage' => get_string('captcha_error', self::COMPONENT),
        ];
    }

    /**
     * Render Moodle's core reCAPTCHA HTML for non-React PHP forms.
     *
     * @return string
     */
    public static function render_html(): string {
        global $CFG;

        if (!self::is_enabled()) {
            return '';
        }

        self::require_core_library();

        return recaptcha_get_challenge_html(RECAPTCHA_API_URL, $CFG->recaptchapublickey);
    }

    /**
     * Read the standard Google response field from the current request.
     *
     * @return string
     */
    public static function request_response(): string {
        return trim(optional_param('g-recaptcha-response', '', PARAM_RAW_TRIMMED));
    }

    /**
     * Verify a posted reCAPTCHA response with Google's verification endpoint.
     *
     * @param string|null $response User response token.
     * @return true|string True when accepted, otherwise a user-facing error.
     */
    public static function verify(?string $response) {
        global $CFG;

        if (!self::is_enabled()) {
            return true;
        }

        $response = trim((string)$response);
        if ($response === '') {
            return get_string('captcha_required', self::COMPONENT);
        }

        self::require_core_library();

        $result = recaptcha_check_response(
            RECAPTCHA_VERIFY_URL,
            $CFG->recaptchaprivatekey,
            getremoteaddr(),
            $response
        );

        return !empty($result['isvalid']) ? true : get_string('captcha_error', self::COMPONENT);
    }

    /**
     * Load Moodle core's reCAPTCHA v2 library.
     */
    private static function require_core_library(): void {
        global $CFG;

        require_once($CFG->libdir . '/recaptchalib_v2.php');
    }
}
