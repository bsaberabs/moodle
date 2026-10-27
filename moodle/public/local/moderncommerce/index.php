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
 * Public Modern Commerce storefront landing page (widget-driven, admin-arrangeable).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Public storefront permits guest browsing; write actions enforce login.
// phpcs:disable moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../config.php');
// phpcs:enable moodle.Files.RequireLogin.Missing
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\api\cart_api;
use local_moderncommerce\output\public_page;
use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\zones;

$context = context_system::instance();

// The catalog title and all display settings are owned by the placed catalog widget
// (edited from the storefront side panel) - there is no store-wide catalog setting.
// Derive the browser/page title from the first enabled catalog widget on this page.
$cataloglabel = get_string('catalog', 'local_moderncommerce');
$catalogwidgets = widget::get_records(
    ['type' => 'catalog', 'pagetype' => 'catalog', 'enabled' => 1],
    'sortorder',
    'ASC'
);
foreach ($catalogwidgets as $catalogwidget) {
    $widgettitle = trim((string)($catalogwidget->get_settings_array()['title'] ?? ''));
    if ($widgettitle !== '') {
        $cataloglabel = $widgettitle;
        break;
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-public-catalog-page');
$PAGE->add_body_class('mc-public-storefront-page');
$PAGE->set_title($cataloglabel);
// Surface Moodle's standard "Edit mode" toggle for storefront managers.
$PAGE->set_blocks_editing_capability('local/moderncommerce:managestorefront');

// Keep old add-to-cart links working while the page itself renders through React.
$action = optional_param('action', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$bundleid = optional_param('bundleid', 0, PARAM_INT);
$quantity = max(1, optional_param('quantity', 1, PARAM_INT));

// Category tiles link in as ?categoryid=N, so the catalog opens filtered to that product category.
$categoryid = max(0, optional_param('categoryid', 0, PARAM_INT));

if (in_array($action, ['add', 'addtocart'], true) && confirm_sesskey()) {
    if (!isloggedin() || isguestuser()) {
        redirect(new moodle_url('/login/index.php', ['wantsurl' => $PAGE->url->out(false)]));
    }

    try {
        if ($bundleid > 0) {
            cart_api::add_bundle_to_cart((int)$USER->id, $bundleid, $quantity);
            redirect(
                $PAGE->url,
                get_string('bundleaddedtocart', 'local_moderncommerce'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        if ($courseid > 0) {
            cart_api::add_to_cart((int)$USER->id, $courseid, $quantity);
            redirect(
                $PAGE->url,
                get_string('addedtocart', 'local_moderncommerce'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }
}

// Labels for the embedded catalogue widget (the React Catalog component expects a full map).
$cataloglabels = get_string_manager()->load_component_strings('local_moderncommerce', current_language());
$cataloglabels = array_merge($cataloglabels, [
    'catalog' => $cataloglabel,
    'course' => get_string('course'),
    'login' => get_string('login'),
    'startsignup' => get_string('startsignup'),
]);

$editing = $PAGE->user_is_editing();
$canmanage = has_capability('local/moderncommerce:managestorefront', $context);
$toolbarid = 'moderncommerce-storefront-toolbar';

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/storefront',
    'id' => 'moderncommerce-storefront-app',
    'class' => 'local-moderncommerce-storefront',
    'props' => [
        'getPageMethod' => 'local_moderncommerce_get_storefront_page',
        'pageType' => zones::PAGE_CATALOG,
        'renderContext' => array_filter([
            'hostpage' => zones::PAGE_CATALOG,
            'categoryid' => $categoryid,
        ]),
        'layoutGetMethod' => 'local_moderncommerce_get_storefront_layout',
        'layoutSaveMethod' => 'local_moderncommerce_save_storefront_layout',
        'widgetGetMethod' => 'local_moderncommerce_get_storefront_widget',
        'widgetSaveMethod' => 'local_moderncommerce_save_storefront_widget',
        'presetListMethod' => 'local_moderncommerce_list_widget_presets',
        'addMethod' => 'local_moderncommerce_add_storefront_widget',
        'deleteMethod' => 'local_moderncommerce_delete_storefront_widget',
        'uploadMethod' => 'local_moderncommerce_upload_slide_image',
        'videoUploadUrl' => (new moodle_url('/local/moderncommerce/storefront_upload.php'))->out(false),
        'iconOptions' => local_moderncommerce_icon_options(),
        'editing' => $editing,
        'canManage' => $canmanage,
        'toolbarTargetId' => ($editing && $canmanage) ? $toolbarid : '',
        'catalogLabels' => $cataloglabels,
        'labels' => [
            'customize' => get_string('storefront_customize', 'local_moderncommerce'),
            'managetitle' => get_string('storefront_manage_title', 'local_moderncommerce'),
            'manageintro' => get_string('storefront_manage_intro', 'local_moderncommerce'),
            'show' => get_string('chart_show', 'local_moderncommerce'),
            'moveup' => get_string('chart_move_up', 'local_moderncommerce'),
            'movedown' => get_string('chart_move_down', 'local_moderncommerce'),
            'zone' => get_string('storefront_zone', 'local_moderncommerce'),
            'editwidget' => get_string('storefront_edit_widget', 'local_moderncommerce'),
            'deletewidget' => get_string('storefront_delete_widget', 'local_moderncommerce'),
            'addwidget' => get_string('storefront_add_widget', 'local_moderncommerce'),
            'choosetype' => get_string('storefront_choose_type', 'local_moderncommerce'),
            'applypreset' => get_string('widgetpreset_apply', 'local_moderncommerce'),
            'universalstyles' => get_string('widgetpreset_universalstyles', 'local_moderncommerce'),
            'widgetpreset' => get_string('widgetpreset_label', 'local_moderncommerce'),
            'widgetpresetempty' => get_string('widgetpreset_empty', 'local_moderncommerce'),
            'widgetpresetnone' => get_string('widgetpreset_none', 'local_moderncommerce'),
            'slides' => get_string('storefront_slides', 'local_moderncommerce'),
            'addslide' => get_string('storefront_add_slide', 'local_moderncommerce'),
            'save' => get_string('savechanges', 'local_moderncommerce'),
            'cancel' => get_string('cancel'),
            'loading' => get_string('loading', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo $OUTPUT->header();

if ($editing && $canmanage) {
    echo html_writer::div('', 'mc-sf-toolbar-host', ['id' => $toolbarid]);
}

// Site-wide global widgets render above and below the storefront on every page.
echo public_page::render_global_band(zones::GLOBAL_TOP, $OUTPUT, zones::PAGE_CATALOG);

echo $OUTPUT->render_from_template('local_moderncommerce/react_mount', [
    'region' => 'moderncommerce-storefront',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-grid',
]);

echo public_page::render_global_band(zones::GLOBAL_BOTTOM, $OUTPUT, zones::PAGE_CATALOG);

echo $OUTPUT->footer();
