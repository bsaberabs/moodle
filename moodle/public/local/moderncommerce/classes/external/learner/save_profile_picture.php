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
 * External API for learner profile image updates.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\services\learner_profile_service;

/**
 * Saves current learner profile image.
 */
class save_profile_picture extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User id.', VALUE_REQUIRED),
            'deletepicture' => new external_value(PARAM_BOOL, 'Whether to remove current picture.', VALUE_DEFAULT, false),
            'filename' => new external_value(PARAM_FILE, 'Uploaded image file name.', VALUE_DEFAULT, ''),
            'mimetype' => new external_value(PARAM_TEXT, 'Uploaded image MIME type.', VALUE_DEFAULT, ''),
            'filesize' => new external_value(PARAM_INT, 'Uploaded image size in bytes.', VALUE_DEFAULT, 0),
            'imagecontent' => new external_value(PARAM_BASE64, 'Base64-encoded image content.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $userid User id.
     * @param bool $deletepicture Whether to remove the current picture.
     * @param string $filename Uploaded image file name.
     * @param string $mimetype Uploaded image MIME type.
     * @param int $filesize Uploaded image size.
     * @param string $imagecontent Base64 image content.
     * @return array
     */
    public static function execute(
        $userid,
        $deletepicture = false,
        $filename = '',
        $mimetype = '',
        $filesize = 0,
        $imagecontent = ''
    ): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'deletepicture' => $deletepicture,
            'filename' => $filename,
            'mimetype' => $mimetype,
            'filesize' => $filesize,
            'imagecontent' => $imagecontent,
        ]);

        require_login();

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);
        require_capability('moodle/user:editownprofile', $context);
        $PAGE->set_context($context);

        $user = learner_profile_service::require_profile_edit_user($params['userid']);
        $draftitemid = 0;

        if (!$params['deletepicture']) {
            if ($params['imagecontent'] === '') {
                return self::failure(get_string('profileimagefilemissing', 'local_moderncommerce'));
            }

            if ($params['mimetype'] !== '' && strpos($params['mimetype'], 'image/') !== 0) {
                return self::failure(get_string('profileimageinvalid', 'local_moderncommerce'));
            }

            $content = base64_decode($params['imagecontent'], true);
            if ($content === false) {
                return self::failure(get_string('profileimageinvalid', 'local_moderncommerce'));
            }

            $temporarydir = make_temp_directory('local_moderncommerce');
            $temporaryfile = tempnam($temporarydir, 'profileimage_');
            if ($temporaryfile === false) {
                throw new \moodle_exception('error');
            }

            try {
                file_put_contents($temporaryfile, $content);
                $draftitemid = learner_profile_service::create_profile_picture_draft_from_path(
                    $temporaryfile,
                    $params['filename'],
                    $params['filesize'],
                    $user
                );
            } catch (\moodle_exception $exception) {
                return self::failure($exception->getMessage());
            } finally {
                @unlink($temporaryfile);
            }
        }

        $result = learner_profile_service::save_profile_picture(
            $params['userid'],
            $params['deletepicture'],
            $draftitemid
        );
        $result['errors'] = learner_profile_service::format_external_errors($result['errors'] ?? []);
        $result['profileimage'] = $result['profileimage'] ?? '';

        return $result;
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'errors' => self::errors_structure(),
            'profileimage' => new external_value(PARAM_URL, 'Updated profile image URL.'),
        ]);
    }

    /**
     * Build failed upload response.
     *
     * @param string $message Field error.
     * @return array
     */
    private static function failure(string $message): array {
        return [
            'success' => false,
            'message' => get_string('profileimageupdatefailed', 'local_moderncommerce'),
            'errors' => [
                [
                    'field' => 'profileimage',
                    'name' => 'profileimage',
                    'message' => $message,
                ],
            ],
            'profileimage' => '',
        ];
    }

    /**
     * Error list structure.
     *
     * @return external_multiple_structure
     */
    private static function errors_structure(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'field' => new external_value(PARAM_TEXT, 'Field the message belongs to.'),
            'name' => new external_value(PARAM_TEXT, 'Deprecated alias of field.'),
            'message' => new external_value(PARAM_TEXT, 'Error message.'),
        ]));
    }
}
