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
 * Shared renderer for the React bundles admin app (list + builder).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;

use local_moderncommerce\services\category_service;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * Builds the bundles admin React mount used by both bundles.php and bundle_form.php.
 */
class bundles_page {
    /**
     * Render the bundles admin shell.
     *
     * @param object $output Page output renderer (or pre-header bootstrap renderer).
     * @param int $openbundleid Bundle ID to open in the builder on load (0 = none).
     * @return string Rendered shell HTML.
     */
    public static function render(object $output, int $openbundleid = 0): string {
        $statusoptions = [
            ['value' => 'active', 'label' => get_string('active', 'local_moderncommerce')],
            ['value' => 'draft', 'label' => get_string('draft', 'local_moderncommerce')],
            ['value' => 'inactive', 'label' => get_string('inactive', 'local_moderncommerce')],
            ['value' => 'archived', 'label' => get_string('archived', 'local_moderncommerce')],
        ];

        // Bundles and programs are products, so they use the same product categories as courses.
        $categoryoptions = [['value' => '0', 'label' => get_string('allcategories', 'local_moderncommerce')]];
        foreach (category_service::get_visible_categories() as $category) {
            $categoryoptions[] = ['value' => (string) $category['id'], 'label' => $category['name']];
        }

        $currencyconfig = pricing_service::get_currency_config();

        $config = json_encode([
            'component' => '@moodle/lms/local_moderncommerce/bundles_admin',
            'id' => 'moderncommerce-bundles-admin-app',
            'class' => 'local-moderncommerce-bundles-admin',
            'props' => [
                'listMethodName' => 'local_moderncommerce_admin_list_bundles',
                'getMethodName' => 'local_moderncommerce_admin_get_bundle',
                'saveMethodName' => 'local_moderncommerce_admin_save_bundle',
                'saveImageMethodName' => 'local_moderncommerce_admin_save_bundle_image',
                'archiveMethodName' => 'local_moderncommerce_admin_archive_bundle',
                'searchCoursesMethodName' => 'local_moderncommerce_search_courses',
                'openBundleId' => $openbundleid,
                'advancedUrlBase' => (new moodle_url('/local/moderncommerce/admin/advanced_bundle_features.php'))->out(false),
                'statusOptions' => $statusoptions,
                'categoryOptions' => $categoryoptions,
                'perPageOptions' => [10, 25, 50, 100],
                'currency' => [
                    'code' => $currencyconfig->currency,
                    'symbol' => $currencyconfig->symbol,
                    'position' => $currencyconfig->position,
                    'decimals' => (int) $currencyconfig->decimals,
                ],
                'labels' => self::labels(),
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $contenthtml = $output->render_from_template('local_moderncommerce/admin/bundles', [
            'bundlesreactconfig' => $config,
        ]);

        $actionshtml = admin_shell::action_group([
            [
                'type' => 'button',
                'label' => get_string('refresh'),
                'icon' => 'bi-arrow-clockwise',
                'attributes' => ['id' => 'moderncommerce-bundles-refresh'],
            ],
        ]);

        return admin_shell::render_page(
            $output,
            'bundles',
            get_string('managebundles', 'local_moderncommerce'),
            $contenthtml,
            get_string('managebundlesdesc', 'local_moderncommerce'),
            $actionshtml
        );
    }

    /**
     * Build the localisation map passed to the React app.
     *
     * @return array
     */
    private static function labels(): array {
        return validation_labels::all() + validation_labels::crossfield() + [
            'error:bundleneedscourses' => get_string('error:bundleneedscourses', 'local_moderncommerce'),
            'title' => get_string('managebundles', 'local_moderncommerce'),
            'createbundle' => get_string('createbundleprogram', 'local_moderncommerce'),
            'editbundle' => get_string('editbundleprogram', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchplaceholder' => get_string('searchbundles', 'local_moderncommerce'),
            'status' => get_string('status', 'local_moderncommerce'),
            'type' => get_string('type', 'local_moderncommerce'),
            'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
            'alltypes' => get_string('alltypes', 'local_moderncommerce'),
            'bundle' => get_string('bundle', 'local_moderncommerce'),
            'program' => get_string('program', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'next' => get_string('next'),
            'name' => get_string('bundlename', 'local_moderncommerce'),
            'coursecount' => get_string('coursecount', 'local_moderncommerce'),
            'price' => get_string('price', 'local_moderncommerce'),
            'saleprice' => get_string('saleprice', 'local_moderncommerce'),
            'enrollments' => get_string('enrollments', 'local_moderncommerce'),
            'featured' => get_string('featured', 'local_moderncommerce'),
            'visible' => get_string('visible', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'edit' => get_string('edit'),
            'archive' => get_string('archive', 'local_moderncommerce'),
            'archiveconfirm' => get_string('archivebundleconfirm', 'local_moderncommerce'),
            'advanced' => get_string('advanced', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'nobundles' => get_string('nobundlesfound', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'total' => get_string('totalbundles', 'local_moderncommerce'),
            'active' => get_string('activebundles', 'local_moderncommerce'),
            'bundles' => get_string('bundles', 'local_moderncommerce'),
            'programs' => get_string('programs', 'local_moderncommerce'),
            'bundleinfo' => get_string('bundleinfo', 'local_moderncommerce'),
            'shortdescription' => get_string('shortdescription', 'local_moderncommerce'),
            'description' => get_string('description', 'local_moderncommerce'),
            'pricingsettings' => get_string('pricingsettings', 'local_moderncommerce'),
            'coursessettings' => get_string('coursessettings', 'local_moderncommerce'),
            'isprogram' => get_string('isprogram', 'local_moderncommerce'),
            'maxenrollment' => get_string('maxenrollment', 'local_moderncommerce'),
            'category' => get_string('category', 'local_moderncommerce'),
            'displayorder' => get_string('displayorder', 'local_moderncommerce'),
            'selectcourses' => get_string('selectcourses', 'local_moderncommerce'),
            'searchcoursesplaceholder' => get_string('searchcoursesplaceholder', 'local_moderncommerce'),
            'nocoursesfound' => get_string('nocoursesfound', 'local_moderncommerce'),
            'nocoursesselected' => get_string('nocoursesselected', 'local_moderncommerce'),
            'addcourse' => get_string('addcourse', 'local_moderncommerce'),
            'includedcourses' => get_string('includedcourses', 'local_moderncommerce'),
            'reorder' => get_string('reorder', 'local_moderncommerce'),
            'savings' => get_string('savings', 'local_moderncommerce'),
            'save' => get_string('savechanges'),
            'cancel' => get_string('cancel'),
            'close' => get_string('close', 'local_moderncommerce'),
            'saved' => get_string('bundlesaved', 'local_moderncommerce'),
            'putonsale' => get_string('putonsale', 'local_moderncommerce'),
            'startdate' => get_string('startdate', 'local_moderncommerce'),
            'enddate' => get_string('enddate', 'local_moderncommerce'),
            'pricepreview' => get_string('pricepreview', 'local_moderncommerce'),
            'yousave' => get_string('yousave', 'local_moderncommerce'),
            'freeproduct' => get_string('freeproduct', 'local_moderncommerce'),
            'nosaleprice' => get_string('nosaleprice', 'local_moderncommerce'),
            'advancedfeatures' => get_string('advancedbundlefeatures', 'local_moderncommerce'),
            'image' => get_string('image', 'local_moderncommerce'),
            'uploadimage' => get_string('uploadimage', 'local_moderncommerce'),
            'changeimage' => get_string('changeimage', 'local_moderncommerce'),
            'removeimage' => get_string('removeimage', 'local_moderncommerce'),
            'noimage' => get_string('noimage', 'local_moderncommerce'),
            'imagehelp' => get_string('imagehelp', 'local_moderncommerce'),
            'imageinvalid' => get_string('imageinvalid', 'local_moderncommerce'),
        ];
    }
}
