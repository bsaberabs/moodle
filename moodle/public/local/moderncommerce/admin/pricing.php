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
 * Admin product and pricing management page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\output\validation_labels;
use local_moderncommerce\services\commerce_settings_service;
use local_moderncommerce\services\pricing_service;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:managecourses', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/pricing.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('coursesandpricing', 'local_moderncommerce'));

$currencyconfig = pricing_service::get_currency_config();

$initialfilters = [
    'search' => optional_param('search', '', PARAM_TEXT),
    'status' => optional_param('status', '', PARAM_ALPHANUMEXT),
    'producttype' => optional_param('producttype', '', PARAM_ALPHANUMEXT),
    'pricingstatus' => optional_param('pricingstatus', '', PARAM_ALPHANUMEXT),
    'categoryid' => optional_param('categoryid', 0, PARAM_INT),
    'page' => optional_param('page', 0, PARAM_INT),
    'perpage' => optional_param('perpage', 10, PARAM_INT),
    'sort' => optional_param('sort', 'timecreated', PARAM_ALPHANUMEXT),
    'direction' => optional_param('direction', 'DESC', PARAM_ALPHA),
];

$pricingreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/pricing_admin',
    'id' => 'moderncommerce-pricing-admin-app',
    'class' => 'local-moderncommerce-pricing-admin',
    'props' => [
        'methodName' => 'local_moderncommerce_list_products',
        'saveMethodName' => 'local_moderncommerce_save_product',
        'archiveMethodName' => 'local_moderncommerce_archive_product',
        'searchCoursesMethodName' => 'local_moderncommerce_search_courses',
        'listPricesMethodName' => 'local_moderncommerce_list_product_prices',
        'savePriceMethodName' => 'local_moderncommerce_save_product_price',
        'archivePriceMethodName' => 'local_moderncommerce_archive_product_price',
        'initialFilters' => $initialfilters,
        // SKU and slug are generated internally; the product form only shows/edits them when
        // these store settings are enabled (both hidden by default).
        'showSku' => commerce_settings_service::show_product_sku(),
        'showSlug' => commerce_settings_service::show_product_slug(),
        'currency' => [
            'code' => $currencyconfig->currency,
            'symbol' => $currencyconfig->symbol,
            'position' => $currencyconfig->position,
            'decimals' => (int) $currencyconfig->decimals,
        ],
        'labels' => validation_labels::all() + validation_labels::crossfield() + [
            'error:invalidsku' => get_string('error:invalidsku', 'local_moderncommerce'),
            'active' => get_string('active', 'local_moderncommerce'),
            'addadvancedprice' => get_string('addadvancedprice', 'local_moderncommerce'),
            'advancedpricing' => get_string('advancedpricing', 'local_moderncommerce'),
            'advancedpricingdesc' => get_string('advancedpricingdesc', 'local_moderncommerce'),
            'all' => get_string('all'),
            'allcategories' => get_string('allcategories', 'local_moderncommerce'),
            'allprices' => get_string('allpricingstates', 'local_moderncommerce'),
            'allstatuses' => get_string('allstatuses', 'local_moderncommerce'),
            'alltypes' => get_string('allproducttypes', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'amount' => get_string('amount', 'local_moderncommerce'),
            'backorders' => get_string('backorders', 'local_moderncommerce'),
            'allowbackorder' => get_string('allowbackorder', 'local_moderncommerce'),
            'archiveconfirm' => get_string('archiveproductconfirm', 'local_moderncommerce'),
            'archiveprice' => get_string('archiveprice', 'local_moderncommerce'),
            'archivepriceconfirm' => get_string('archivepriceconfirm', 'local_moderncommerce'),
            'archiveproduct' => get_string('archiveproduct', 'local_moderncommerce'),
            'autogeneratedhint' => get_string('autogeneratedhint', 'local_moderncommerce'),
            'baseprice' => get_string('baseprice', 'local_moderncommerce'),
            'bundle' => get_string('bundle', 'local_moderncommerce'),
            'bundles' => get_string('bundles', 'local_moderncommerce'),
            'cancel' => get_string('cancel'),
            'category' => get_string('category'),
            'clearcourse' => get_string('clearcourse', 'local_moderncommerce'),
            'close' => get_string('close', 'local_moderncommerce'),
            'compareamount' => get_string('compareamount', 'local_moderncommerce'),
            'compareatprice' => get_string('compareatprice', 'local_moderncommerce'),
            'course' => get_string('course'),
            'coursealreadyhasproduct' => get_string('coursealreadyhasproduct', 'local_moderncommerce'),
            'coursealreadylinkedtoproduct' => get_string('coursealreadylinkedtoproduct', 'local_moderncommerce'),
            'coursehidden' => get_string('coursehidden', 'local_moderncommerce'),
            'courses' => get_string('courses'),
            'displayorder' => get_string('displayorder', 'local_moderncommerce'),
            'draft' => get_string('draft', 'local_moderncommerce'),
            'edit' => get_string('edit'),
            'editbaseprice' => get_string('editbaseprice', 'local_moderncommerce'),
            'editprice' => get_string('editprice', 'local_moderncommerce'),
            'editproduct' => get_string('editproduct', 'local_moderncommerce'),
            'enabled' => get_string('enabled', 'local_moderncommerce'),
            'enddate' => get_string('enddate', 'local_moderncommerce'),
            'featured' => get_string('featured', 'local_moderncommerce'),
            'freeproduct' => get_string('freeproduct', 'local_moderncommerce'),
            'hidden' => get_string('hidden', 'local_moderncommerce'),
            'inactive' => get_string('inactive', 'local_moderncommerce'),
            'inventory' => get_string('inventory', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'manageprices' => get_string('manageprices', 'local_moderncommerce'),
            'maxquantity' => get_string('maxquantity', 'local_moderncommerce'),
            'minquantity' => get_string('minquantity', 'local_moderncommerce'),
            'name' => get_string('name'),
            'newprice' => get_string('newprice', 'local_moderncommerce'),
            'newproduct' => get_string('newproduct', 'local_moderncommerce'),
            'next' => get_string('next'),
            'noprices' => get_string('noprices', 'local_moderncommerce'),
            'noprice' => get_string('notpriced', 'local_moderncommerce'),
            'nosaleprice' => get_string('nosaleprice', 'local_moderncommerce'),
            'nocoursesfound' => get_string('nocoursesfound', 'local_moderncommerce'),
            'noresults' => get_string('noproductmatches', 'local_moderncommerce'),
            'onsale' => get_string('onsale', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'price' => get_string('price', 'local_moderncommerce'),
            'priceenabled' => get_string('priceenabled', 'local_moderncommerce'),
            'pricepreview' => get_string('pricepreview', 'local_moderncommerce'),
            'prices' => get_string('prices', 'local_moderncommerce'),
            'pricetype' => get_string('pricetype', 'local_moderncommerce'),
            'pricewindow' => get_string('pricewindow', 'local_moderncommerce'),
            'pricing' => get_string('pricing', 'local_moderncommerce'),
            'priced' => get_string('priced', 'local_moderncommerce'),
            'productbasics' => get_string('productbasics', 'local_moderncommerce'),
            'products' => get_string('products', 'local_moderncommerce'),
            'productname' => get_string('productname', 'local_moderncommerce'),
            'producttype' => get_string('producttype', 'local_moderncommerce'),
            'purchasable' => get_string('purchasable', 'local_moderncommerce'),
            'putonsale' => get_string('putonsale', 'local_moderncommerce'),
            'quantityrange' => get_string('quantityrange', 'local_moderncommerce'),
            'refresh' => get_string('refresh'),
            'regularprice' => get_string('regularprice', 'local_moderncommerce'),
            'reservedstock' => get_string('reservedstock', 'local_moderncommerce'),
            'salehint' => get_string('salehint', 'local_moderncommerce'),
            'savechanges' => get_string('savechanges', 'local_moderncommerce'),
            'saveprice' => get_string('saveprice', 'local_moderncommerce'),
            'saveproduct' => get_string('saveproduct', 'local_moderncommerce'),
            'saveproductfirst' => get_string('saveproductfirst', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchcoursesplaceholder' => get_string('searchcoursesplaceholder', 'local_moderncommerce'),
            'searchplaceholder' => get_string('searchproductsplaceholder', 'local_moderncommerce'),
            'selectmoodlecourse' => get_string('selectmoodlecourse', 'local_moderncommerce'),
            'selectedcourse' => get_string('selectedcourse', 'local_moderncommerce'),
            'shortdescription' => get_string('shortdescription', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'slug' => get_string('slug', 'local_moderncommerce'),
            'sku' => get_string('sku', 'local_moderncommerce'),
            'sold' => get_string('sold', 'local_moderncommerce'),
            'startdate' => get_string('startdate', 'local_moderncommerce'),
            'status' => get_string('status'),
            'stock' => get_string('stock', 'local_moderncommerce'),
            'stockmanaged' => get_string('stockmanaged', 'local_moderncommerce'),
            'title' => get_string('coursesandpricing', 'local_moderncommerce'),
            'totalproducts' => get_string('totalproducts', 'local_moderncommerce'),
            'type' => get_string('type', 'local_moderncommerce'),
            'unlimited' => get_string('unlimited', 'local_moderncommerce'),
            'unpriced' => get_string('unpricedproducts', 'local_moderncommerce'),
            'updated' => get_string('updated', 'local_moderncommerce'),
            'visible' => get_string('visible', 'local_moderncommerce'),
            'yousave' => get_string('yousave', 'local_moderncommerce'),
        ],
        'productTypes' => [
            ['value' => '', 'label' => get_string('allproducttypes', 'local_moderncommerce')],
            ['value' => 'course', 'label' => get_string('course')],
            ['value' => 'bundle', 'label' => get_string('bundle', 'local_moderncommerce')],
            ['value' => 'program', 'label' => get_string('program', 'local_moderncommerce')],
            ['value' => 'subscription', 'label' => get_string('subscription', 'local_moderncommerce')],
            ['value' => 'digital', 'label' => get_string('digitalproduct', 'local_moderncommerce')],
        ],
        'statusOptions' => [
            ['value' => '', 'label' => get_string('allstatuses', 'local_moderncommerce')],
            ['value' => 'active', 'label' => get_string('active', 'local_moderncommerce')],
            ['value' => 'draft', 'label' => get_string('draft', 'local_moderncommerce')],
            ['value' => 'inactive', 'label' => get_string('inactive', 'local_moderncommerce')],
            ['value' => 'archived', 'label' => get_string('archived', 'local_moderncommerce')],
        ],
        'pricingStatuses' => [
            ['value' => '', 'label' => get_string('allpricingstates', 'local_moderncommerce')],
            ['value' => 'priced', 'label' => get_string('priced', 'local_moderncommerce')],
            ['value' => 'unpriced', 'label' => get_string('unpricedproducts', 'local_moderncommerce')],
            ['value' => 'onsale', 'label' => get_string('onsale', 'local_moderncommerce')],
        ],
        'priceTypes' => [
            ['value' => 'regular', 'label' => get_string('pricetype_regular', 'local_moderncommerce')],
            ['value' => 'sale', 'label' => get_string('pricetype_sale', 'local_moderncommerce')],
            ['value' => 'tier', 'label' => get_string('pricetype_tier', 'local_moderncommerce')],
            ['value' => 'subscription', 'label' => get_string('pricetype_subscription', 'local_moderncommerce')],
        ],
        'perPageOptions' => [10, 25, 50, 100],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/pricing', [
    'pricingreactconfig' => $pricingreactconfig,
]);

$shell = admin_shell::create('courses')
    ->set_title(get_string('coursesandpricing', 'local_moderncommerce'))
    ->set_subtitle(get_string('manageproductspricingdesc', 'local_moderncommerce'))
    ->set_actions(admin_shell::action_group([
        [
            'type' => 'button',
            'label' => get_string('refresh'),
            'icon' => 'bi-arrow-clockwise',
            'attributes' => ['id' => 'moderncommerce-pricing-refresh'],
        ],
    ]))
    ->set_content($contenthtml);

$shellhtml = $shell->render($OUTPUT);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
