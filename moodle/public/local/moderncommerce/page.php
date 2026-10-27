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
 * Dispatcher for admin-created custom public pages in Modern Commerce.
 *
 * Serves every page in the storepage table. The six built-in public pages keep
 * their own entry files (about.php, terms.php and friends).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');
// phpcs:enable moodle.Files.RequireLogin.Missing

use local_moderncommerce\output\public_page;
use local_moderncommerce\storefront\storepage_service;

// The context must be set before anything can throw: Moodle renders exceptions through
// $PAGE, and without a context the real 404 is masked by a "context not set" error.
$PAGE->set_context(context_system::instance());

$slug = required_param('slug', PARAM_ALPHANUMEXT);

if (!storepage_service::exists($slug)) {
    throw new moodle_exception('pagenotfound', 'error');
}

// Everything from here (publish check, title, layout, render) is identical to a built-in
// page; only the per-page body class is specific to custom pages.
$PAGE->add_body_class('mc-public-storefront-page--' . $slug);

public_page::render_page($slug, $OUTPUT);
