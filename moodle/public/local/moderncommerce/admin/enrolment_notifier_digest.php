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
 * Modern Commerce enrolment notifier digest route.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\output\enrolment_notifier_page;

$context = context_system::instance();
require_login();

$available = enrolment_notifier_page::addon_available();
enrolment_notifier_page::require_access($available, $context);
$view = 'digest';

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/enrolment_notifier_digest.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(enrolment_notifier_page::title($available, $view));

$shellhtml = admin_shell::create(enrolment_notifier_page::active_nav($view))
    ->set_title(enrolment_notifier_page::title($available, $view))
    ->set_subtitle(enrolment_notifier_page::subtitle($available, $view))
    ->set_actions(enrolment_notifier_page::actions($available))
    ->set_content(enrolment_notifier_page::content($OUTPUT, $available, $view))
    ->render($OUTPUT);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
