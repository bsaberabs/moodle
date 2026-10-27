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
 * Learner course library page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\learner_shell;

require_login();

$context = context_system::instance();
require_capability('local/moderncommerce:viewcatalog', $context);

$search = optional_param('search', '', PARAM_TEXT);
$coursetype = optional_param('coursetype', '', PARAM_ALPHANUMEXT);
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$level = optional_param('level', '', PARAM_TEXT);
$minprice = optional_param('minprice', 0, PARAM_FLOAT);
$maxprice = optional_param('maxprice', 0, PARAM_FLOAT);
$sort = optional_param('sort', 'popular', PARAM_ALPHANUMEXT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 12, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/library.php', [
    'search' => $search,
    'coursetype' => $coursetype,
    'categoryid' => $categoryid,
    'level' => $level,
    'minprice' => $minprice,
    'maxprice' => $maxprice,
    'sort' => $sort,
    'page' => $page,
    'perpage' => $perpage,
]));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('courselibrary', 'local_moderncommerce'));
$PAGE->set_heading(get_string('courselibrary', 'local_moderncommerce'));

$labels = get_string_manager()->load_component_strings('local_moderncommerce', current_language());
$labels['course'] = get_string('course', 'local_moderncommerce');

$shell = learner_shell::create('catalog');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_library',
    'id' => 'moderncommerce-learner-library-app',
    'class' => 'local-moderncommerce-learner-library',
    'props' => [
        'methodName' => 'local_moderncommerce_get_catalog',
        'cartMethodName' => 'local_moderncommerce_update_cart',
        'wishlistUpdateMethodName' => 'local_moderncommerce_update_learner_wishlist',
        'initialFilters' => [
            'search' => $search,
            'coursetype' => $coursetype,
            'categoryid' => $categoryid,
            'level' => $level,
            'minprice' => max(0, (float)$minprice),
            'maxprice' => max(0, (float)$maxprice),
            'sort' => $sort,
            'page' => max(0, (int)$page),
            'perpage' => max(1, (int)$perpage),
        ],
        'layout' => $shell->get_react_layout_context(),
        'labels' => $labels,
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-library',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-search',
]);

echo $OUTPUT->header();
echo $shell->set_content($contenthtml)
    ->add_breadcrumb(get_string('courselibrary', 'local_moderncommerce'), '', true)
    ->render($OUTPUT);
echo $OUTPUT->footer();
