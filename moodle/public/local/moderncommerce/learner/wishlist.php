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
 * Learner wishlist page.
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

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/learner/wishlist.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('wishlist', 'local_moderncommerce'));
$PAGE->set_heading(get_string('wishlist', 'local_moderncommerce'));

$shell = learner_shell::create('wishlist');
$labels = get_string_manager()->load_component_strings('local_moderncommerce', current_language());

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/learner_wishlist',
    'id' => 'moderncommerce-learner-wishlist-app',
    'class' => 'local-moderncommerce-learner-wishlist',
    'props' => [
        'methodName' => 'local_moderncommerce_list_learner_wishlist',
        'updateMethodName' => 'local_moderncommerce_update_learner_wishlist',
        'layout' => $shell->get_react_layout_context(),
        'labels' => $labels,
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/learner/react_mount', [
    'region' => 'moderncommerce-learner-wishlist',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-heart',
]);

echo $OUTPUT->header();
echo $contenthtml;
echo $OUTPUT->footer();
