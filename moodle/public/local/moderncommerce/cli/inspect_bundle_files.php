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
 * Inspect stored files for bundle images.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$bundleid = null;
foreach ($argv as $arg) {
    if (preg_match('/^--bundle=(\d+)$/', $arg, $m)) {
        $bundleid = (int)$m[1];
    }
}
if (!$bundleid) {
    fwrite(STDERR, "Usage: php inspect_bundle_files.php --bundle=<id>\n");
    exit(1);
}

$context = \context_system::instance();
$fs = get_file_storage();
$files = $fs->get_area_files($context->id, 'local_moderncommerce', 'bundleimage', $bundleid, 'itemid, filepath, filename', false);

echo "Files for bundle #{$bundleid} in filearea 'bundleimage':\n";
if (empty($files)) {
    echo " - (none)\n";
    exit(0);
}
foreach ($files as $f) {
    echo " - filename: " . $f->get_filename() . ", filesize: " . $f->get_filesize() . ", mimetype: " . $f->get_mimetype() . "\n";
}
