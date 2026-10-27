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
 * Multipart upload endpoint for large storefront media (hero videos).
 *
 * Web services use JSON/base64 which is unsuitable for large videos, so this small
 * endpoint accepts a normal multipart POST. It is fully gated: login + managestorefront
 * capability + sesskey, stores into the public `herovideo` file area, and returns JSON.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/moderncommerce:managestorefront', $context);
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

// Allowed video extensions mapped to their mime types.
$allowed = [
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogg' => 'video/ogg',
    'ogv' => 'video/ogg',
    'mov' => 'video/quicktime',
];
$maxbytes = 209715200; // 200 MB.

try {
    if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'] ?? '')) {
        throw new moodle_exception('p1_storefront_upload_nofile', 'local_moderncommerce');
    }
    $upload = $_FILES['file'];
    if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
        throw new moodle_exception(
            'p1_storefront_upload_errorcode',
            'local_moderncommerce',
            '',
            (int) $upload['error']
        );
    }
    if ((int) $upload['size'] <= 0 || (int) $upload['size'] > $maxbytes) {
        throw new moodle_exception('p1_storefront_upload_sizelimit', 'local_moderncommerce');
    }

    $ext = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
    if (!isset($allowed[$ext])) {
        throw new moodle_exception('p1_storefront_upload_unsupported', 'local_moderncommerce');
    }

    $fs = get_file_storage();
    $filename = clean_param(uniqid('herovideo_', true) . '.' . $ext, PARAM_FILE);
    $filerecord = [
        'contextid' => $context->id,
        'component' => 'local_moderncommerce',
        'filearea' => 'herovideo',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $filename,
    ];
    $file = $fs->create_file_from_pathname($filerecord, $upload['tmp_name']);

    $url = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    )->out(false);

    echo json_encode(['ok' => true, 'url' => $url, 'mimetype' => $allowed[$ext]]);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
