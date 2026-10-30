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
 * Public page of a trainer's course, in the trainer's brand: description, program, price and how to buy it.
 *
 * Reached from the showcase, and on the trainer's own domain as /formation/<id> (the proxy adds ?domain=).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_moderncommerce\services\price_resolver;
use local_moderncommerce\services\pricing_service;
use local_oriskill\creator_service;
use local_oriskill\hook_callbacks;
use local_oriskill\orderform_service;
use local_oriskill\seller_service;

$id = required_param('id', PARAM_INT);
$domain = optional_param('domain', '', PARAM_HOST);

$PAGE->set_context(context_system::instance());
$course = $DB->get_record('course', ['id' => $id, 'visible' => 1]);
$creator = $course ? seller_service::owner_of_course((int) $course->id) : null;
$price = $course ? price_resolver::resolve_for_course((int) $course->id, 1, true) : null;
// Only a course of an active trainer, on sale, and on that trainer's own domain when reached through one.
if (!$creator || $creator->status !== 'active' || !$price
        || ($domain !== '' && !\local_oriskill\domain_service::belongs($creator, $domain))) {
    $PAGE->set_pagelayout('standard');
    $PAGE->set_url(new moodle_url('/local/oriskill/coursepage.php', ['id' => $id]));
    send_header_404();
    $PAGE->set_title(get_string('coursepage:notfound', 'local_oriskill'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('coursepage:notfound', 'local_oriskill'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// In the trainer's brand only, like the showcase.
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('oriskill-vitrine-page');
$url = creator_service::course_page_url($creator, (int) $course->id);
$PAGE->set_url(new moodle_url('/local/oriskill/coursepage.php', ['id' => $course->id]));
$coursename = format_string($course->fullname);
$name = format_string($creator->displayname);
$PAGE->set_title($coursename . ' – ' . $name, false);
$logo = creator_service::logo_url($creator);
$summary = format_text($course->summary, $course->summaryformat,
    ['context' => context_course::instance($course->id), 'noclean' => false]);
hook_callbacks::$headmeta = ['description' => shorten_text(html_to_text($summary, 0), 160),
    'canonical' => $url->out(false), 'icon' => $logo ? $logo->out(false) : ''];

$listelement = new core_course_list_element($course);
$image = '';
foreach ($listelement->get_course_overviewfiles() as $file) {
    if ($file->is_valid_image()) {
        $image = moodle_url::make_pluginfile_url($file->get_contextid(), 'course', 'overviewfiles', null, '/',
            $file->get_filename())->out(false);
        break;
    }
}

// How to get in: already a learner; otherwise the trainer's order form for it (their brand), or the shop.
$loggedin = isloggedin() && !isguestuser();
$enrolled = $loggedin && is_enrolled(context_course::instance($course->id), $USER, '', true);
$selling = !seller_service::selling_blocker($creator);
$form = $selling ? orderform_service::form_for_course($creator, (int) $course->id) : null;
$instalments = $selling && !$form ? \local_oriskill\instalment_service::buyable_offer((int) $course->id) : null;
if ($enrolled) {
    $cta = ['url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
        'label' => get_string('coursepage:access', 'local_oriskill')];
} else if ($form) {
    $cta = ['url' => orderform_service::url($form, $creator)->out(false),
        'label' => get_string('coursepage:choose', 'local_oriskill')];
} else if ($selling) {
    $cta = ['url' => (new moodle_url('/local/moderncommerce/checkout.php', ['courseid' => $course->id]))->out(false),
        'label' => get_string('coursepage:buy', 'local_oriskill')];
} else {
    $cta = null;
}

$program = creator_service::course_program($course);
$activities = array_sum(array_column($program, 'count'));
$showcase = !empty($creator->customdomain) ? 'https://' . $creator->customdomain . '/'
    : creator_service::showcase_url($creator)->out(false);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_oriskill/coursepage', [
    'displayname' => $name,
    'tagline' => format_string($creator->tagline),
    'logo' => $logo ? $logo->out(false) : '',
    'initial' => core_text::strtoupper(core_text::substr($creator->displayname, 0, 1)),
    'style' => creator_service::showcase_style($creator),
    'homeurl' => $showcase,
    'coursesurl' => $showcase . '#oriskill-courses',
    'accounturl' => (new moodle_url($loggedin ? '/local/moderncommerce/learner/index.php' : '/login/index.php'))->out(false),
    'accountlabel' => get_string($loggedin ? 'showcase:mylearning' : 'showcase:login', 'local_oriskill'),
    'coursename' => $coursename,
    'summary' => $summary,
    'image' => $image,
    'price' => pricing_service::format_price($price->final_price, $price->currency),
    'compareprice' => !empty($price->on_sale) ? pricing_service::format_price($price->regularprice, $price->currency) : '',
    'instalments' => $instalments ? ['label' => $instalments->label, 'url' => $instalments->url] : null,
    'cta' => $cta,
    'enrolled' => $enrolled,
    'facts' => get_string('coursepage:facts', 'local_oriskill', (object) ['modules' => count($program),
        'activities' => $activities]),
    'program' => array_map(fn($module, $i) => $module + ['number' => $i + 1, 'hasactivities' => $module['count'] > 0],
        $program, array_keys($program)),
    'hasprogram' => !empty($program),
    'year' => userdate(time(), '%Y'),
    'links' => [
        ['url' => (new moodle_url('/local/moderncommerce/terms.php'))->out(false),
         'label' => get_string('showcase:terms', 'local_oriskill')],
        ['url' => (new moodle_url('/local/moderncommerce/privacy.php'))->out(false),
         'label' => get_string('showcase:privacy', 'local_oriskill')],
    ],
]);
echo $OUTPUT->footer();
