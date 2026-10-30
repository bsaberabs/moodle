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
 * Public capture page of a trainer: free courses in exchange for an e-mail address. On the trainer's own domain it is
 * served at /p/{slug} (see README, "Domaine personnalisé"); the form posts to the address it was shown at.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_oriskill\capture_service;
use local_oriskill\creator_service;
use local_oriskill\hook_callbacks;
use local_oriskill\orderform_service;

$id = optional_param('id', 0, PARAM_INT);
$domain = optional_param('domain', '', PARAM_HOST);
$slug = optional_param('slug', '', PARAM_ALPHANUMEXT);
$context = context_system::instance();
$PAGE->set_context($context);

$found = capture_service::public_page($id, $domain, $slug);
if (!$found) {
    $PAGE->set_url(new moodle_url('/local/oriskill/capture.php', array_filter(['id' => $id, 'domain' => $domain,
        'slug' => $slug])));
    $PAGE->set_pagelayout('standard');
    send_header_404();
    $PAGE->set_title(get_string('error:formunavailable', 'local_oriskill'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('error:formunavailable', 'local_oriskill'), 'info');
    echo $OUTPUT->footer();
    exit;
}
[$page, $creator] = $found;
$pageurl = new moodle_url('/local/oriskill/capture.php', ['id' => $page->id]);
$PAGE->set_url($pageurl);
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('oriskill-vitrine-page');
$PAGE->set_title(format_string($page->title) . ' – ' . format_string($creator->displayname), false);
$logo = creator_service::logo_url($creator);
hook_callbacks::$headmeta = ['description' => $page->headline ?: format_string($page->title),
    'canonical' => capture_service::url($page, $creator)->out(false), 'icon' => $logo ? $logo->out(false) : ''];

$courses = [];
foreach (array_filter(array_map('intval', explode(',', (string) $page->courseids))) as $courseid) {
    if ($name = $DB->get_field('course', 'fullname', ['id' => $courseid])) {
        $courses[] = ['id' => $courseid, 'name' => format_string($name)];
    }
}

$error = '';
$done = false;
$values = ['firstname' => '', 'lastname' => '', 'email' => ''];
if (data_submitted()) {
    $values = ['firstname' => trim(optional_param('firstname', '', PARAM_NOTAGS)),
        'lastname' => trim(optional_param('lastname', '', PARAM_NOTAGS)), 'email' => trim(optional_param('email', '', PARAM_RAW_TRIMMED))];
    if (!confirm_sesskey()) {
        $error = get_string('capture:expired', 'local_oriskill');
    } else if (optional_param('website', '', PARAM_RAW) !== '') {
        // Robot: filled the field people do not see. Show the same thank-you page and do nothing.
        $done = true;
    } else if ($values['firstname'] === '' || $values['lastname'] === '' || !validate_email($values['email'])) {
        $error = get_string('capture:invalid', 'local_oriskill');
    } else {
        try {
            $consents = ['direct' => optional_param('consentdirect', 0, PARAM_BOOL),
                'partners' => optional_param('consentpartners', 0, PARAM_BOOL)];
            [$signedup, $created] = capture_service::signup($page, $values['firstname'], $values['lastname'], $values['email'],
                (bool) $consents['direct']);
            \local_oriskill\privacy_service::record($creator, (int) $signedup->id, $consents, $PAGE->url->out(false), 'capture');
            $done = true;
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        }
    }
} else {
    capture_service::track_view($page);
}

$redirect = null;
if ($done && $page->redirectformid
        && ($form = $DB->get_record('local_oriskill_orderforms', ['id' => $page->redirectformid, 'status' => 'published']))) {
    $redirect = ['url' => orderform_service::url($form, $creator)->out(false), 'label' => format_string($form->title)];
}
$files = get_file_storage()->get_area_files($context->id, 'local_oriskill', 'capturepage', $page->id, 'filename', false);
$image = ($file = reset($files)) ? moodle_url::make_pluginfile_url($context->id, 'local_oriskill', 'capturepage', $page->id, '/',
    $file->get_filename())->out(false) : '';

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_oriskill/capture', [
    'displayname' => format_string($creator->displayname),
    'logo' => $logo ? $logo->out(false) : '',
    'initial' => core_text::strtoupper(core_text::substr($creator->displayname, 0, 1)),
    'showcaseurl' => (!empty($creator->customdomain) ? new moodle_url('https://' . $creator->customdomain . '/')
        : creator_service::showcase_url($creator))->out(false),
    'style' => creator_service::showcase_style($creator),
    'title' => format_string($page->title),
    'headline' => format_string($page->headline),
    'description' => format_text($page->description, $page->descriptionformat, ['context' => $context]),
    'image' => $image,
    'courses' => $courses,
    // Posted back to the address the page was shown at (Oriskill or the trainer's domain), with the same session.
    'formaction' => '',
    'sesskey' => sesskey(),
    'buttonlabel' => format_string($page->buttonlabel ?: get_string('capture:buttondefault', 'local_oriskill')),
    'consents' => array_values(array_filter([
        ['name' => 'consentdirect', 'label' => \local_oriskill\privacy_service::consent_text($creator, 'direct')],
        \local_oriskill\privacy_service::settings($creator)->askpartners
            ? ['name' => 'consentpartners', 'label' => \local_oriskill\privacy_service::consent_text($creator, 'partners')] : null,
    ])),
    'error' => $error,
    'values' => $values,
    'done' => $done,
    'thankyou' => $page->thankyou,
    'doneintro' => get_string(!empty($created) ? 'capture:donenew' : 'capture:doneexisting', 'local_oriskill'),
    'courselinks' => array_map(fn($c) => ['name' => $c['name'],
        'url' => (new moodle_url('/course/view.php', ['id' => $c['id']]))->out(false)], $courses),
    'redirect' => $redirect,
    'year' => userdate(time(), '%Y'),
    'links' => [
        ['url' => (new moodle_url('/local/moderncommerce/terms.php'))->out(false),
         'label' => get_string('showcase:terms', 'local_oriskill')],
        ['url' => (new moodle_url('/local/moderncommerce/privacy.php'))->out(false),
         'label' => get_string('showcase:privacy', 'local_oriskill')],
    ],
]);
echo \local_oriskill\privacy_service::banner($creator, 'funnel');
echo $OUTPUT->footer();
