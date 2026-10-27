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
 * External API that stores an uploaded hero-slide image and returns its URL.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\storefront;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use moodle_exception;
use moodle_url;

/**
 * Accepts a base64 image from the storefront editor and stores it as a public slide image.
 */
class upload_slide_image extends external_api {
    /** @var int Maximum decoded image size (5 MB). */
    private const MAX_BYTES = 5242880;

    /** @var string[] Allowed raster image extensions (SVG excluded to avoid script payloads). */
    private const ALLOWED = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            // PARAM_RAW (not PARAM_FILE): real-world names contain characters that PARAM_FILE
            // cleaning would change, which makes validate_parameters reject the whole call. We
            // only need the extension and sanitise the stored name ourselves below.
            'filename' => new external_value(PARAM_RAW, 'Original file name (used only for the extension).'),
            'content' => new external_value(PARAM_RAW, 'Base64-encoded file content.'),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $filename Original file name.
     * @param string $content Base64 file content.
     * @return array
     */
    public static function execute(string $filename, string $content): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'filename' => $filename,
            'content' => $content,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $ext = strtolower(pathinfo($params['filename'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED, true)) {
            throw new moodle_exception('error', 'moodle', '', null, 'Unsupported image type.');
        }

        // Strip an optional data-URL prefix, then decode.
        $raw = (string) preg_replace('#^data:[^;]+;base64,#', '', trim($params['content']));
        $binary = base64_decode($raw, true);
        if ($binary === false || $binary === '') {
            throw new moodle_exception('error', 'moodle', '', null, 'Invalid image data.');
        }
        if (strlen($binary) > self::MAX_BYTES) {
            throw new moodle_exception('error', 'moodle', '', null, 'Image exceeds the 5 MB limit.');
        }
        // Confirm the bytes really are an image of the claimed kind.
        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            throw new moodle_exception('error', 'moodle', '', null, 'File is not a valid image.');
        }

        $fs = get_file_storage();
        // The stored name is fully controlled (uniqid + validated extension) so no characters
        // from the user-supplied filename can ever reach the file system.
        $unique = clean_param(uniqid('slide_', true) . '.' . $ext, PARAM_FILE);
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'local_moderncommerce',
            'filearea' => 'slideimage',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $unique,
        ];
        $file = $fs->create_file_from_string($filerecord, $binary);

        $url = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);

        return [
            'url' => $url,
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
            'url' => new external_value(PARAM_RAW, 'Public URL of the stored image.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
