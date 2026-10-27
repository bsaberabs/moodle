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
 * Shared per-field validation error helpers for Modern Commerce web services.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external;

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Formats validation errors so the React layer can bind them to a control.
 *
 * Save endpoints return a per-field error list rather than a single flat
 * message, so a rejected value can be rendered inline against the field that
 * caused it. Use this for anything the user can fix by typing something
 * different in a visible field; throw a moodle_exception for everything else
 * (missing capability, vanished record, gateway failure).
 */
trait field_errors_trait {
    /**
     * Convert a field-keyed message map into the wire format.
     *
     * Keys must be limited to the PARAM_ALPHANUMEXT character set (letters,
     * digits, underscore and hyphen). Use underscores to address repeated rows,
     * for example slides_0_title, because a dot would be silently stripped.
     *
     * @param array $errors Map of field name to message.
     * @return array List of ['field' => string, 'message' => string].
     */
    protected static function format_field_errors(array $errors): array {
        $formatted = [];

        foreach ($errors as $field => $message) {
            $formatted[] = [
                'field' => (string) $field,
                'message' => (string) $message,
            ];
        }

        return $formatted;
    }

    /**
     * Shared returns fragment so every save endpoint declares the same shape.
     *
     * @return external_multiple_structure
     */
    protected static function field_errors_structure(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'field' => new external_value(PARAM_ALPHANUMEXT, 'Field the message belongs to.'),
                'message' => new external_value(PARAM_TEXT, 'Validation message.'),
            ]),
            'Per-field validation errors.',
            VALUE_DEFAULT,
            []
        );
    }

    /**
     * Build the standard rejection payload for a failed validation pass.
     *
     * @param array $errors Map of field name to message.
     * @param string $message Optional form-level message.
     * @return array
     */
    protected static function field_errors_response(array $errors, string $message = ''): array {
        return [
            'success' => false,
            'message' => $message,
            'errors' => self::format_field_errors($errors),
            'warnings' => [],
        ];
    }
}
