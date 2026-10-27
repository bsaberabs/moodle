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
 * Admin-only widget gallery for previewing storefront widget variants.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\storefront\gallery_builder;

require_login();
$context = context_system::instance();
require_capability('local/moderncommerce:managestorefront', $context);

$gallerystring = static function (string $identifier): string {
    return get_string($identifier, 'local_moderncommerce');
};

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/gallery.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->add_body_class('mc-widget-gallery-page');
$PAGE->add_body_class('mc-public-storefront-page');
$PAGE->set_title($gallerystring('widgetgallery_title'));

$showcase = gallery_builder::showcase();

// Labels the embedded catalog/product widgets expect.
$cataloglabels = get_string_manager()->load_component_strings('local_moderncommerce', current_language());
$cataloglabels = array_merge($cataloglabels, [
    'catalog' => get_string('catalog', 'local_moderncommerce'),
    'course' => get_string('course'),
    'login' => get_string('login'),
    'startsignup' => get_string('startsignup'),
]);

// Minimal labels map the storefront renderer reads when a selected preview is mounted.
$storefrontlabels = [
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
    'slides' => get_string('storefront_slides', 'local_moderncommerce'),
    'addslide' => get_string('storefront_add_slide', 'local_moderncommerce'),
    'save' => get_string('savechanges', 'local_moderncommerce'),
    'cancel' => get_string('cancel'),
    'loading' => get_string('loading', 'local_moderncommerce'),
];

$gallerylabels = \local_moderncommerce\output\validation_labels::all() + [
    'alignment' => $gallerystring('widgetgallery_alignment'),
    'aligncenter' => $gallerystring('widgetgallery_align_center'),
    'alignleft' => $gallerystring('widgetgallery_align_left'),
    'brandlabel' => $gallerystring('widgetgallery_brandlabel'),
    'breadcrumb_edititem' => $gallerystring('widgetgallery_breadcrumb_edititem'),
    'breadcrumb_fontcolor' => $gallerystring('widgetgallery_breadcrumb_fontcolor'),
    'breadcrumb_fontsize' => $gallerystring('widgetgallery_breadcrumb_fontsize'),
    'breadcrumb_item_breadcrumb' => $gallerystring('widgetgallery_breadcrumb_item_breadcrumb'),
    'breadcrumb_item_overlay' => $gallerystring('widgetgallery_breadcrumb_item_overlay'),
    'breadcrumb_item_padding' => $gallerystring('widgetgallery_breadcrumb_item_padding'),
    'breadcrumb_item_position' => $gallerystring('widgetgallery_breadcrumb_item_position'),
    'breadcrumb_item_subtitle' => $gallerystring('widgetgallery_breadcrumb_item_subtitle'),
    'breadcrumb_item_title' => $gallerystring('widgetgallery_breadcrumb_item_title'),
    'breadcrumb_overlaycolor' => $gallerystring('widgetgallery_breadcrumb_overlaycolor'),
    'breadcrumb_paddingbottom' => $gallerystring('widgetgallery_breadcrumb_paddingbottom'),
    'breadcrumb_paddingtop' => $gallerystring('widgetgallery_breadcrumb_paddingtop'),
    'breadcrumb_textposition' => $gallerystring('widgetgallery_breadcrumb_textposition'),
    'breadcrumb_transparency' => $gallerystring('widgetgallery_breadcrumb_transparency'),
    'countdown_bgcolor' => $gallerystring('widgetgallery_countdown_bgcolor'),
    'countdown_buttoncolor' => $gallerystring('widgetgallery_countdown_buttoncolor'),
    'countdown_buttontextcolor' => $gallerystring('widgetgallery_countdown_buttontextcolor'),
    'countdown_edititem' => $gallerystring('widgetgallery_countdown_edititem'),
    'countdown_expiredbgcolor' => $gallerystring('widgetgallery_countdown_expiredbgcolor'),
    'countdown_expiredtextcolor' => $gallerystring('widgetgallery_countdown_expiredtextcolor'),
    'countdown_headingcolor' => $gallerystring('widgetgallery_countdown_headingcolor'),
    'countdown_headingfontsize' => $gallerystring('widgetgallery_countdown_headingfontsize'),
    'countdown_item_background' => $gallerystring('widgetgallery_countdown_item_background'),
    'countdown_item_button' => $gallerystring('widgetgallery_countdown_item_button'),
    'countdown_item_expired' => $gallerystring('widgetgallery_countdown_item_expired'),
    'countdown_item_heading' => $gallerystring('widgetgallery_countdown_item_heading'),
    'countdown_item_spacing' => $gallerystring('widgetgallery_countdown_item_spacing'),
    'countdown_item_timer' => $gallerystring('widgetgallery_countdown_item_timer'),
    'countdown_paddingbottom' => $gallerystring('widgetgallery_countdown_paddingbottom'),
    'countdown_paddingtop' => $gallerystring('widgetgallery_countdown_paddingtop'),
    'countdown_textcolor' => $gallerystring('widgetgallery_countdown_textcolor'),
    'countdown_timerbgcolor' => $gallerystring('widgetgallery_countdown_timerbgcolor'),
    'countdown_timerlabelcolor' => $gallerystring('widgetgallery_countdown_timerlabelcolor'),
    'countdown_timerlabelfontsize' => $gallerystring('widgetgallery_countdown_timerlabelfontsize'),
    'countdown_timernumbercolor' => $gallerystring('widgetgallery_countdown_timernumbercolor'),
    'countdown_timernumberfontsize' => $gallerystring('widgetgallery_countdown_timernumberfontsize'),
    'categories_bgcolor' => $gallerystring('widgetgallery_categories_bgcolor'),
    'categories_cardbgcolor' => $gallerystring('widgetgallery_categories_cardbgcolor'),
    'categories_cardradius' => $gallerystring('widgetgallery_categories_cardradius'),
    'categories_cardtextcolor' => $gallerystring('widgetgallery_categories_cardtextcolor'),
    'categories_cardtextfontsize' => $gallerystring('widgetgallery_categories_cardtextfontsize'),
    'categories_countcolor' => $gallerystring('widgetgallery_categories_countcolor'),
    'categories_countfontsize' => $gallerystring('widgetgallery_categories_countfontsize'),
    'categories_edititem' => $gallerystring('widgetgallery_categories_edititem'),
    'categories_iconbgcolor' => $gallerystring('widgetgallery_categories_iconbgcolor'),
    'categories_iconcolor' => $gallerystring('widgetgallery_categories_iconcolor'),
    'categories_iconsize' => $gallerystring('widgetgallery_categories_iconsize'),
    'categories_item_cards' => $gallerystring('widgetgallery_categories_item_cards'),
    'categories_item_count' => $gallerystring('widgetgallery_categories_item_count'),
    'categories_item_heading' => $gallerystring('widgetgallery_categories_item_heading'),
    'categories_item_icons' => $gallerystring('widgetgallery_categories_item_icons'),
    'categories_item_layout' => $gallerystring('widgetgallery_categories_item_layout'),
    'categories_item_spacing' => $gallerystring('widgetgallery_categories_item_spacing'),
    'categories_paddingbottom' => $gallerystring('widgetgallery_categories_paddingbottom'),
    'categories_paddingtop' => $gallerystring('widgetgallery_categories_paddingtop'),
    'categories_style' => $gallerystring('widgetgallery_categories_style'),
    'categories_subtitlecolor' => $gallerystring('widgetgallery_categories_subtitlecolor'),
    'categories_subtitlefontsize' => $gallerystring('widgetgallery_categories_subtitlefontsize'),
    'categories_titlecolor' => $gallerystring('widgetgallery_categories_titlecolor'),
    'categories_titlefontsize' => $gallerystring('widgetgallery_categories_titlefontsize'),
    'categories_visiblecards' => $gallerystring('widgetgallery_categories_visiblecards'),
    'emptyvariants' => $gallerystring('widgetgallery_emptyvariants'),
    'errortitle' => $gallerystring('widgetgallery_errortitle'),
    'exit' => $gallerystring('widgetgallery_exit'),
    'eyebrow' => $gallerystring('widgetgallery_eyebrow'),
    'fullwidthsection' => $gallerystring('widgetgallery_fullwidthsection'),
    'genericdesc' => $gallerystring('widgetgallery_genericdesc'),
    'intro' => $gallerystring('widgetgallery_intro'),
    'nofields' => $gallerystring('widgetgallery_nofields'),
    'overlaymedium' => $gallerystring('widgetgallery_overlay_medium'),
    'overlaysoft' => $gallerystring('widgetgallery_overlay_soft'),
    'overlaystrength' => $gallerystring('widgetgallery_overlaystrength'),
    'overlaystrong' => $gallerystring('widgetgallery_overlay_strong'),
    'presetactions' => $gallerystring('widgetgallery_presetactions'),
    'presetname' => $gallerystring('widgetgallery_presetname'),
    'previewoptions' => $gallerystring('widgetgallery_previewoptions'),
    'selectedvariant' => $gallerystring('widgetgallery_selectedvariant'),
    'deletepreset' => $gallerystring('widgetgallery_deletepreset'),
    'fullwidthpreview' => $gallerystring('widgetgallery_fullwidthpreview'),
    'savepreset' => $gallerystring('widgetgallery_savepreset'),
    'showbreadcrumb' => $gallerystring('widgetgallery_showbreadcrumb'),
    'sidebarnav' => $gallerystring('widgetgallery_sidebarnav'),
    'subtitlefield' => $gallerystring('widgetgallery_subtitlefield'),
    'title' => $gallerystring('widgetgallery_title'),
    'titlefield' => $gallerystring('widgetgallery_titlefield'),
    'universalstyles' => $gallerystring('widgetpreset_universalstyles'),
    'updatepreset' => $gallerystring('widgetgallery_updatepreset'),
    'usepageimage' => $gallerystring('widgetgallery_usepageimage'),
    'variantsettings' => $gallerystring('widgetgallery_variantsettings'),
    'variantsettingsdesc' => $gallerystring('widgetgallery_variantsettingsdesc'),
    'videohero_accentcolor' => $gallerystring('widgetgallery_videohero_accentcolor'),
    'videohero_bgcolor' => $gallerystring('widgetgallery_videohero_bgcolor'),
    'videohero_bodysize' => $gallerystring('widgetgallery_videohero_bodysize'),
    'videohero_edititem' => $gallerystring('widgetgallery_videohero_edititem'),
    'videohero_headingcolor' => $gallerystring('widgetgallery_videohero_headingcolor'),
    'videohero_headingsize' => $gallerystring('widgetgallery_videohero_headingsize'),
    'videohero_infocardbgcolor' => $gallerystring('widgetgallery_videohero_infocardbgcolor'),
    'videohero_infoheadingcolor' => $gallerystring('widgetgallery_videohero_infoheadingcolor'),
    'videohero_infoheadingsize' => $gallerystring('widgetgallery_videohero_infoheadingsize'),
    'videohero_infoiconbgcolor' => $gallerystring('widgetgallery_videohero_infoiconbgcolor'),
    'videohero_infoiconcolor' => $gallerystring('widgetgallery_videohero_infoiconcolor'),
    'videohero_infotextcolor' => $gallerystring('widgetgallery_videohero_infotextcolor'),
    'videohero_item_background' => $gallerystring('widgetgallery_videohero_item_background'),
    'videohero_item_body' => $gallerystring('widgetgallery_videohero_item_body'),
    'videohero_item_buttons' => $gallerystring('widgetgallery_videohero_item_buttons'),
    'videohero_item_heading' => $gallerystring('widgetgallery_videohero_item_heading'),
    'videohero_item_infocard' => $gallerystring('widgetgallery_videohero_item_infocard'),
    'videohero_item_panel' => $gallerystring('widgetgallery_videohero_item_panel'),
    'videohero_item_spacing' => $gallerystring('widgetgallery_videohero_item_spacing'),
    'videohero_panelradius' => $gallerystring('widgetgallery_videohero_panelradius'),
    'videohero_primarybuttoncolor' => $gallerystring('widgetgallery_videohero_primarybuttoncolor'),
    'videohero_primarybuttontextcolor' => $gallerystring('widgetgallery_videohero_primarybuttontextcolor'),
    'videohero_secondarybuttoncolor' => $gallerystring('widgetgallery_videohero_secondarybuttoncolor'),
    'videohero_secondarybuttontextcolor' => $gallerystring('widgetgallery_videohero_secondarybuttontextcolor'),
    'videohero_sectionbgcolor' => $gallerystring('widgetgallery_videohero_sectionbgcolor'),
    'videohero_showquote' => $gallerystring('widgetgallery_videohero_showquote'),
    'videohero_spacingbottom' => $gallerystring('widgetgallery_videohero_spacingbottom'),
    'videohero_spacingtop' => $gallerystring('widgetgallery_videohero_spacingtop'),
    'videohero_textcolor' => $gallerystring('widgetgallery_videohero_textcolor'),
    'visualsettings' => $gallerystring('widgetgallery_visualsettings'),
    'widgetpreset' => $gallerystring('widgetpreset_label'),
    'widgetpresetnone' => $gallerystring('widgetpreset_none'),
];

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/storefront_gallery',
    'id' => 'mc-widget-gallery-app',
    'class' => 'mcg-root',
    'props' => [
        'getGalleryMethod' => 'local_moderncommerce_get_storefront_gallery',
        'pageType' => 'gallery',
        'renderContext' => ['hostpage' => 'about'],
        'defaultType' => 'breadcrumb',
        'defaultVariantStyle' => 'gradient',
        'exitUrl' => (new moodle_url('/local/moderncommerce/admin/index.php'))->out(false),
        'showcase' => array_values($showcase),
        'catalogLabels' => $cataloglabels,
        'storefrontLabels' => $storefrontlabels,
        'presetMethods' => [
            'list' => 'local_moderncommerce_list_widget_presets',
            'save' => 'local_moderncommerce_save_widget_preset',
            'delete' => 'local_moderncommerce_delete_widget_preset',
        ],
        'labels' => $gallerylabels,
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_moderncommerce/react_mount', [
    'region' => 'mc-widget-gallery',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-collection',
]);

echo $OUTPUT->footer();
