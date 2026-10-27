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
 * Manage Modern Commerce product categories.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\output\validation_labels;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:managecategories', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/categories.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('managecategories', 'local_moderncommerce'));

$config = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/categories_admin',
    'id' => 'moderncommerce-categories-admin-app',
    'class' => 'local-moderncommerce-categories-admin',
    'props' => [
        'methods' => [
            'list' => 'local_moderncommerce_list_categories',
            'save' => 'local_moderncommerce_save_category',
            'delete' => 'local_moderncommerce_delete_category',
            'reorder' => 'local_moderncommerce_reorder_categories',
        ],
        'labels' => validation_labels::all() + [
            'title' => get_string('managecategories', 'local_moderncommerce'),
            'newcategory' => get_string('newcategory', 'local_moderncommerce'),
            'editcategory' => get_string('editcategory', 'local_moderncommerce'),
            'categorydetails' => get_string('categorydetails', 'local_moderncommerce'),
            'close' => get_string('close', 'local_moderncommerce'),
            'name' => get_string('name'),
            'slug' => get_string('slug', 'local_moderncommerce'),
            'description' => get_string('description'),
            'visible' => get_string('visible', 'local_moderncommerce'),
            'hidden' => get_string('hidden', 'local_moderncommerce'),
            'dragtoreorder' => get_string('dragtoreorder', 'local_moderncommerce'),
            'products' => get_string('products', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'edit' => get_string('edit'),
            'delete' => get_string('delete'),
            'save' => get_string('savechanges', 'local_moderncommerce'),
            'cancel' => get_string('cancel'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'nocategories' => get_string('nocategories', 'local_moderncommerce'),
            'nocategoriesdesc' => get_string('nocategories_desc', 'local_moderncommerce'),
            'deleteconfirm' => get_string('categorydeleteconfirm', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/categories', [
    'categoriesreactconfig' => $config,
]);

$shell = admin_shell::create('categories')
    ->set_title(get_string('managecategories', 'local_moderncommerce'))
    ->set_subtitle(get_string('managecategories_desc', 'local_moderncommerce'))
    ->set_actions(admin_shell::action_group([
        [
            'type' => 'button',
            'label' => get_string('refresh'),
            'icon' => 'bi-arrow-clockwise',
            'attributes' => ['id' => 'moderncommerce-categories-refresh'],
        ],
        [
            'type' => 'button',
            'label' => get_string('newcategory', 'local_moderncommerce'),
            'icon' => 'bi-plus',
            'primary' => true,
            'attributes' => ['id' => 'moderncommerce-categories-new'],
        ],
    ]))
    ->set_content($contenthtml);

$shellhtml = $shell->render($OUTPUT);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
