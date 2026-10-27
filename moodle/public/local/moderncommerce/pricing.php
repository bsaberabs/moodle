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
 * Public Modern Commerce pricing / value page.
 *
 * Free core vs premium add-ons, framed for prospective admins. No login required.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public marketing page; guest browsing is intentional.
// phpcs:disable moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');
// phpcs:enable moodle.Files.RequireLogin.Missing
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/pricing.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-public-pricing-page');
$PAGE->set_title(get_string('pricing_pagetitle', 'local_moderncommerce'));
$PAGE->set_heading(get_string('pricing_pagetitle', 'local_moderncommerce'));

// Where to buy the core licence, where to see a live demo, and where to browse add-ons.
$geturl = 'https://agunfoninteractivity.com/moderncommerce/';
$demourl = (new moodle_url('/local/moderncommerce/index.php'))->out(false);
$addonsurl = 'https://agunfoninteractivity.com/moderncommerce/addons/';

// Headline price for the one-time licence. Set to '' to show the offer terms only.
$price = ''; // E.g. '$199 USD'.

$templatecontext = [
    'geturl' => $geturl,
    'demourl' => $demourl,
    'addonsurl' => $addonsurl,
    'price' => $price,
    'comparison' => local_moderncommerce_addons_comparison(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moderncommerce/pricing', $templatecontext);
echo $OUTPUT->footer();
