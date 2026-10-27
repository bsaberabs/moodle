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
 * Enrollment key redemption page (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\output\redeem_react;
use local_moderncommerce\output\learner_shell;

require_login();

$orderid = optional_param('orderid', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/moderncommerce/redeem.php', $orderid ? ['orderid' => $orderid] : []));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('redeemkey', 'local_moderncommerce'));
$PAGE->set_heading(get_string('redeemkey', 'local_moderncommerce'));

local_moderncommerce_set_breadcrumb(
    get_string('redeemkey', 'local_moderncommerce'),
    (new moodle_url('/local/moderncommerce/index.php'))->out(false),
    get_string('catalog', 'local_moderncommerce')
);

$shell = learner_shell::create('redeem');

echo $OUTPUT->header();
echo redeem_react::render($OUTPUT, $orderid, $shell->get_react_layout_context(), 'redeem');
echo $OUTPUT->footer();
