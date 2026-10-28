<?php
// This file is part of Moodle - https://moodle.org/
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
 * Public trainer showcase, served at /{slug} (see docker/apache-moodle.conf).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\creator_service;
use local_oriskill\hook_callbacks;

$slug = required_param('slug', PARAM_ALPHANUMEXT);
$creator = $DB->get_record('local_oriskill_creators', ['slug' => $slug, 'status' => 'active']);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
if (!$creator) {
    $PAGE->set_url(new moodle_url('/local/oriskill/creator.php', ['slug' => $slug]));
    send_header_404();
    $PAGE->set_title(get_string('showcasenotfound', 'local_oriskill'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('showcasenotfound', 'local_oriskill'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$url = creator_service::showcase_url($creator);
$PAGE->set_url($url);
$PAGE->set_title(get_string('showcasetitle', 'local_oriskill', format_string($creator->displayname)));
hook_callbacks::$headmeta = ['description' => $creator->tagline ?: format_string($creator->displayname),
    'canonical' => $url->out(false)];

// Courses are offered only while the trainer can actually take payments.
$courses = \local_oriskill\seller_service::selling_blocker($creator) ? [] : creator_service::showcase_courses($creator);
$logo = creator_service::logo_url($creator);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_oriskill/showcase', [
    'displayname' => format_string($creator->displayname),
    'tagline' => format_string($creator->tagline),
    'bio' => format_text($creator->bio, $creator->bioformat, ['context' => context_system::instance()]),
    'logo' => $logo ? $logo->out(false) : '',
    'initial' => core_text::strtoupper(core_text::substr($creator->displayname, 0, 1)),
    'courses' => $courses,
    'hascourses' => !empty($courses),
]);
echo $OUTPUT->footer();
