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
 * Set bundle program and certificate template fields via API.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$bundleid = null;
$templateid = null;
foreach ($argv as $arg) {
    if (preg_match('/^--bundle=(\d+)$/', $arg, $m)) {
        $bundleid = (int)$m[1];
    } else if (preg_match('/^--template=(\d+)$/', $arg, $m)) {
        $templateid = (int)$m[1];
    }
}

if (!$bundleid) {
    fwrite(STDERR, "Usage: php set_bundle_template.php --bundle=<id> [--template=<id>]\n");
    exit(1);
}

if ($templateid !== null) {
    // Verify template exists.
    global $DB;
    if (!$DB->record_exists('tool_certificate_templates', ['id' => $templateid])) {
        fwrite(STDERR, "Template {$templateid} not found.\n");
        exit(1);
    }
}

$bundle = \local_moderncommerce\api\bundle_api::get($bundleid);
if (!$bundle) {
    fwrite(STDERR, "Bundle {$bundleid} not found.\n");
    exit(1);
}

$data = (object)[
    'isprogram' => 1,
];
if ($templateid !== null) {
    $data->certificatetemplateid = $templateid;
}

$ok = \local_moderncommerce\api\bundle_api::update($bundleid, $data);
if ($ok) {
    $updated = \local_moderncommerce\api\bundle_api::get($bundleid);
    $templatevalue = isset($updated->certificatetemplateid)
        ? ($updated->certificatetemplateid === null ? 'NULL' : $updated->certificatetemplateid)
        : 'NOT SET';
    echo "Updated bundle #{$bundleid}: isprogram=" . (int)$updated->isprogram .
        ", certificatetemplateid=" . $templatevalue . "\n";
} else {
    fwrite(STDERR, "Update failed.\n");
    exit(1);
}
