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
 * Shared validation for public contact and support enquiries.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

/**
 * Validates the public enquiry forms.
 *
 * The contact form is posted from three places (the synchronous endpoint, its
 * AJAX twin, and the support widget). Keeping the rules here stops those copies
 * drifting apart, and returns one message per field so the caller can render it
 * against the control that caused it rather than as a single generic banner.
 */
class contact_service {
    /** @var string Component the strings belong to. */
    private const COMPONENT = 'local_moderncommerce';

    /**
     * Validate a contact enquiry.
     *
     * @param array $data Submitted values keyed by field name.
     * @return array Errors keyed by field name; empty when the enquiry is fine.
     */
    public static function validate_enquiry(array $data): array {
        $errors = [];

        $fullname = trim((string) ($data['fullname'] ?? ''));
        if ($fullname === '') {
            $errors['fullname'] = get_string('error:required', self::COMPONENT);
        } else if (\core_text::strlen($fullname) > 255) {
            $errors['fullname'] = get_string('error:maxlength', self::COMPONENT, 255);
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            $errors['email'] = get_string('error:required', self::COMPONENT);
        } else if (!validate_email($email)) {
            $errors['email'] = get_string('error:invalidemail', self::COMPONENT);
        }

        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '') {
            $errors['message'] = get_string('error:required', self::COMPONENT);
        } else if (\core_text::strlen($message) < 10) {
            $errors['message'] = get_string('error:minlength', self::COMPONENT, 10);
        } else if (\core_text::strlen($message) > 5000) {
            $errors['message'] = get_string('error:maxlength', self::COMPONENT, 5000);
        }

        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject !== '' && \core_text::strlen($subject) > 255) {
            $errors['subject'] = get_string('error:maxlength', self::COMPONENT, 255);
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^[0-9+()\s-]{1,20}$/', $phone)) {
            $errors['phone'] = get_string('error:invalidphone', self::COMPONENT);
        }

        return $errors;
    }

    /**
     * Format errors for the JSON contract the React layer consumes.
     *
     * @param array $errors Errors keyed by field name.
     * @return array List of ['field' => string, 'message' => string].
     */
    public static function format_errors(array $errors): array {
        $formatted = [];

        foreach ($errors as $field => $message) {
            $formatted[] = ['field' => (string) $field, 'message' => (string) $message];
        }

        return $formatted;
    }
}
