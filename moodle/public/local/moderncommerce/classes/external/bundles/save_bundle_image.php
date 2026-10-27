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
 * External API for uploading or removing a bundle/program image.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\bundles;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Stores a bundle image in the bundleimage file area (system context, itemid = bundle id).
 */
class save_bundle_image extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        // Filename and imagecontent are accepted RAW and validated/sanitised in execute().
        // so a stray character never triggers a hard "Invalid parameter value detected".
        return new external_function_parameters([
            'bundleid' => new external_value(PARAM_INT, 'Bundle product ID.', VALUE_REQUIRED),
            'deletepicture' => new external_value(PARAM_BOOL, 'Whether to remove the current image.', VALUE_DEFAULT, false),
            'filename' => new external_value(PARAM_RAW, 'Uploaded image file name.', VALUE_DEFAULT, ''),
            'mimetype' => new external_value(PARAM_RAW, 'Uploaded image MIME type.', VALUE_DEFAULT, ''),
            'filesize' => new external_value(PARAM_INT, 'Uploaded image size in bytes.', VALUE_DEFAULT, 0),
            'imagecontent' => new external_value(PARAM_RAW, 'Base64-encoded image content.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $bundleid Bundle product ID.
     * @param bool $deletepicture Whether to remove the current image.
     * @param string $filename Uploaded image file name.
     * @param string $mimetype Uploaded image MIME type.
     * @param int $filesize Uploaded image size in bytes.
     * @param string $imagecontent Base64 image content.
     * @return array
     */
    public static function execute(
        $bundleid,
        $deletepicture = false,
        $filename = '',
        $mimetype = '',
        $filesize = 0,
        $imagecontent = ''
    ): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'bundleid' => $bundleid,
            'deletepicture' => $deletepicture,
            'filename' => $filename,
            'mimetype' => $mimetype,
            'filesize' => $filesize,
            'imagecontent' => $imagecontent,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $bundle = $DB->get_record_select(
            'local_moderncommerce_products',
            'id = :id AND producttype IN (:bundle, :program)',
            ['id' => $params['bundleid'], 'bundle' => 'bundle', 'program' => 'program']
        );
        if (!$bundle) {
            return self::failure(get_string('invalidbundle', 'local_moderncommerce'), (int) $params['bundleid']);
        }

        $fs = get_file_storage();
        // Replace any existing image first.
        $fs->delete_area_files($context->id, 'local_moderncommerce', 'bundleimage', $params['bundleid']);

        if (!$params['deletepicture']) {
            // Accept the content with or without a data-URL prefix / line breaks.
            $rawcontent = (string) $params['imagecontent'];
            if (strpos($rawcontent, 'base64,') !== false) {
                $rawcontent = substr($rawcontent, strpos($rawcontent, 'base64,') + 7);
            }
            $rawcontent = preg_replace('/\s+/', '', $rawcontent);

            if ($rawcontent === '') {
                return self::failure(get_string('imageinvalid', 'local_moderncommerce'), (int) $params['bundleid']);
            }
            if ($params['mimetype'] !== '' && strpos((string) $params['mimetype'], 'image/') !== 0) {
                return self::failure(get_string('imageinvalid', 'local_moderncommerce'), (int) $params['bundleid']);
            }

            $content = base64_decode($rawcontent, true);
            if ($content === false || $content === '') {
                return self::failure(get_string('imageinvalid', 'local_moderncommerce'), (int) $params['bundleid']);
            }

            $cleanname = clean_param((string) $params['filename'], PARAM_FILE);
            if ($cleanname === '') {
                $cleanname = 'bundle-' . $params['bundleid'] . '.png';
            }

            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'local_moderncommerce',
                'filearea' => 'bundleimage',
                'itemid' => $params['bundleid'],
                'filepath' => '/',
                'filename' => $cleanname,
            ], $content);
        }

        $existingfiles = $fs->get_area_files($context->id, 'local_moderncommerce', 'bundleimage', $params['bundleid'], 'id', false);

        return [
            'success' => true,
            'message' => $params['deletepicture']
                ? get_string('imageremoved', 'local_moderncommerce')
                : get_string('imagesaved', 'local_moderncommerce'),
            'bundleid' => (int) $params['bundleid'],
            'imageurl' => local_moderncommerce_get_bundle_image_url((int) $params['bundleid']),
            'hasimage' => !empty($existingfiles),
        ];
    }

    /**
     * Build a failed response.
     *
     * @param string $message Error message.
     * @param int $bundleid Bundle product ID.
     * @return array
     */
    private static function failure(string $message, int $bundleid): array {
        return [
            'success' => false,
            'message' => $message,
            'bundleid' => $bundleid,
            'imageurl' => '',
            'hasimage' => false,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the image was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'bundleid' => new external_value(PARAM_INT, 'Bundle product ID.'),
            'imageurl' => new external_value(PARAM_RAW, 'Resolved bundle image URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether a custom image is stored.'),
        ]);
    }
}
