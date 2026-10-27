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
 * Learner calendar page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/calendar/lib.php');

use local_moderncommerce\output\learner_shell;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/calendar.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('calendar', 'local_moderncommerce'));
$PAGE->set_heading(get_string('calendar', 'local_moderncommerce'));
$PAGE->requires->js_call_amd('core_calendar/popover');

$calendar = \calendar_information::create(time(), SITEID, null);
$renderer = $PAGE->get_renderer('core_calendar');

$monthhtml = $renderer->start_layout();
[$monthdata, $monthtemplate] = calendar_get_view($calendar, 'monthblock', isloggedin());
$monthdata->iscalendarblock = true;
$monthhtml .= $renderer->render_from_template($monthtemplate, $monthdata);

[$footerdata, $footertemplate] = calendar_get_footer_options($calendar, [
    'showfullcalendarlink' => true,
]);
$monthfooterhtml = $renderer->render_from_template($footertemplate, $footerdata);
$monthhtml .= $renderer->complete_layout();

[$upcomingdata, $upcomingtemplate] = calendar_get_view($calendar, 'upcoming_mini');
$upcominghtml = $renderer->render_from_template($upcomingtemplate, $upcomingdata);

$calendarurl = (new moodle_url('/calendar/view.php', ['view' => 'month']))->out(false);
$upcomingurl = (new moodle_url('/calendar/view.php', ['view' => 'upcoming']))->out(false);

$shell = learner_shell::create('calendar');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_calendar',
    'id' => 'moderncommerce-learner-calendar-app',
    'class' => 'local-moderncommerce-learner-calendar',
    'props' => [
        'layout' => $shell->get_react_layout_context(),
        'monthHtml' => $monthhtml,
        'monthFooterHtml' => $monthfooterhtml,
        'upcomingHtml' => $upcominghtml,
        'calendarUrl' => $calendarurl,
        'upcomingUrl' => $upcomingurl,
        'labels' => [
            'calendar' => get_string('calendar', 'local_moderncommerce'),
            'calendarintro' => get_string('calendarintro', 'local_moderncommerce'),
            'calendarupcomingdesc' => get_string('calendarupcomingdesc', 'local_moderncommerce'),
            'upcomingevents' => get_string('upcomingevents', 'local_moderncommerce'),
            'viewfullcalendar' => get_string('viewfullcalendar', 'local_moderncommerce'),
            'viewupcomingevents' => get_string('viewupcomingevents', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-calendar',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-calendar',
]);

echo $OUTPUT->header();
echo $contenthtml;
echo $OUTPUT->footer();
