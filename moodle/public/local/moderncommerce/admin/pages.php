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
 * Modern Commerce public page manager route (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\output\public_page;
use local_moderncommerce\output\validation_labels;
use local_moderncommerce\storefront\storepage_service;

require_login();

$context = context_system::instance();
require_capability('local/moderncommerce:managestorefront', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/pages.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('storepages', 'local_moderncommerce'));

$pages = [];
foreach (public_page::page_catalog() as $key => $page) {
    $pages[] = [
        'id' => (int) ($page['id'] ?? 0),
        'key' => $key,
        'title' => format_string(public_page::setting($key, 'title', $page['title'])),
        'summary' => format_string(public_page::setting($key, 'summary', $page['summary'] ?? '')),
        'icon' => preg_replace('/[^a-z0-9 -]/i', '', (string) ($page['icon'] ?? 'bi-file-text')),
        'url' => (new moodle_url($page['route'], $page['params'] ?? []))->out(false),
        'enabled' => public_page::is_enabled($key),
        'required' => !public_page::can_disable($key),
        'custom' => !empty($page['custom']),
        'template' => (string) ($page['template'] ?? ''),
    ];
}

$templates = [];
foreach (storepage_service::templates() as $value => $label) {
    $templates[] = [
        'value' => $value,
        'label' => $label,
        'description' => get_string('storepage_template_' . $value . '_desc', 'local_moderncommerce'),
    ];
}

$pagesreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/pages_admin',
    'id' => 'moderncommerce-pages-admin-app',
    'class' => 'local-moderncommerce-pages-admin',
    'props' => [
        'pages' => $pages,
        'methods' => [
            'getLayout' => 'local_moderncommerce_get_storefront_layout',
            'saveLayout' => 'local_moderncommerce_save_storefront_layout',
            'togglePage' => 'local_moderncommerce_toggle_storefront_page',
            'listPages' => 'local_moderncommerce_list_store_pages',
            'savePage' => 'local_moderncommerce_save_store_page',
            'deletePage' => 'local_moderncommerce_delete_store_page',
            'checkSlug' => 'local_moderncommerce_check_store_page_slug',
        ],
        'globalUrl' => (new moodle_url('/local/moderncommerce/admin/global.php'))->out(false),
        'templates' => $templates,
        'copySources' => storepage_service::copy_sources(),
        'iconOptions' => array_values(local_moderncommerce_icon_options()),
        'labels' => validation_labels::all() + [
            'title' => get_string('storepages', 'local_moderncommerce'),
            'storepages' => get_string('storepages', 'local_moderncommerce'),
            'storeglobalintro' => get_string('storeglobal_intro', 'local_moderncommerce'),
            'storeglobalmanage' => get_string('storeglobal_manage', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
            'publicurl' => get_string('publicurl', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'enabled' => get_string('enabled', 'local_moderncommerce'),
            'disabled' => get_string('disabled', 'local_moderncommerce'),
            'requiredpage' => get_string('requiredpage', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'managewidgets' => get_string('managewidgets', 'local_moderncommerce'),
            'preview' => get_string('preview', 'local_moderncommerce'),
            'enablepage' => get_string('enablepage', 'local_moderncommerce'),
            'disablepage' => get_string('disablepage', 'local_moderncommerce'),
            'pagewidgets' => get_string('pagewidgets', 'local_moderncommerce'),
            'close' => get_string('close', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'nowidgetsassigned' => get_string('nowidgetsassigned', 'local_moderncommerce'),
            'moveup' => get_string('moveup', 'local_moderncommerce'),
            'movedown' => get_string('movedown', 'local_moderncommerce'),
            'show' => get_string('show', 'local_moderncommerce'),
            'saving' => get_string('saving', 'local_moderncommerce'),
            'savechanges' => get_string('savechanges', 'local_moderncommerce'),
            'cancel' => get_string('cancel', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'storepage_addpage' => get_string('storepage_addpage', 'local_moderncommerce'),
            'storepage_copyfrom' => get_string('storepage_copyfrom', 'local_moderncommerce'),
            'storepage_copyfrom_existing' => get_string('storepage_copyfrom_existing', 'local_moderncommerce'),
            'storepage_copyfrom_help' => get_string('storepage_copyfrom_help', 'local_moderncommerce'),
            'storepage_slug_available' => get_string('storepage_slug_available', 'local_moderncommerce'),
            'storepage_slug_checking' => get_string('storepage_slug_checking', 'local_moderncommerce'),
            'storepage_deleteconfirm' => get_string('storepage_deleteconfirm', 'local_moderncommerce', '{$a}'),
            'storepage_deletepage' => get_string('storepage_deletepage', 'local_moderncommerce'),
            'storepage_editpage' => get_string('storepage_editpage', 'local_moderncommerce'),
            'storepage_error_slugrequired' => get_string('storepage_error_slugrequired', 'local_moderncommerce'),
            'storepage_error_titlerequired' => get_string('storepage_error_titlerequired', 'local_moderncommerce'),
            'storepage_icon' => get_string('storepage_icon', 'local_moderncommerce'),
            'storepage_icon_help' => get_string('storepage_icon_help', 'local_moderncommerce'),
            'storepage_newpage' => get_string('storepage_newpage', 'local_moderncommerce'),
            'storepage_published' => get_string('storepage_published', 'local_moderncommerce'),
            'storepage_published_help' => get_string('storepage_published_help', 'local_moderncommerce'),
            'storepage_published_no' => get_string('storepage_published_no', 'local_moderncommerce'),
            'storepage_published_yes' => get_string('storepage_published_yes', 'local_moderncommerce'),
            'storepage_slug' => get_string('storepage_slug', 'local_moderncommerce'),
            'storepage_slug_help' => get_string('storepage_slug_help', 'local_moderncommerce'),
            'storepage_sluglocked' => get_string('storepage_sluglocked', 'local_moderncommerce'),
            'storepage_summary' => get_string('storepage_summary', 'local_moderncommerce'),
            'storepage_template' => get_string('storepage_template', 'local_moderncommerce'),
            'storepage_templatelocked' => get_string('storepage_templatelocked', 'local_moderncommerce'),
            'storepage_title' => get_string('storepage_title', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/pages', [
    'pagesreactconfig' => $pagesreactconfig,
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'storepages',
    get_string('storepages', 'local_moderncommerce'),
    $contenthtml,
    get_string('storepages_desc', 'local_moderncommerce')
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
