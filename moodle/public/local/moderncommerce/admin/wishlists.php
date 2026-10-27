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
 * Modern Commerce wishlist demand admin page (React + webservice driven).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;

require_login();
$context = context_system::instance();
require_capability('local/moderncommerce:viewreports', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/wishlists.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('wishlists', 'local_moderncommerce'));

$legacysearch = optional_param('search', '', PARAM_TEXT);
$legacytype = optional_param('producttype', '', PARAM_ALPHANUMEXT);
$legacyperpage = optional_param('perpage', 10, PARAM_INT);

$initialfilters = [
    'productsearch' => optional_param('productsearch', $legacysearch, PARAM_TEXT),
    'activitysearch' => optional_param('activitysearch', $legacysearch, PARAM_TEXT),
    'producttype' => $legacytype,
    'activitytype' => optional_param('activitytype', $legacytype, PARAM_ALPHANUMEXT),
    'productpage' => optional_param('productpage', 0, PARAM_INT),
    'activitypage' => optional_param('activitypage', 0, PARAM_INT),
    'productperpage' => optional_param('productperpage', $legacyperpage, PARAM_INT),
    'activityperpage' => optional_param('activityperpage', $legacyperpage, PARAM_INT),
    'productsort' => optional_param('productsort', 'savedcount', PARAM_ALPHANUMEXT),
    'productdirection' => optional_param('productdirection', 'DESC', PARAM_ALPHA),
    'activitysort' => optional_param('activitysort', 'timecreated', PARAM_ALPHANUMEXT),
    'activitydirection' => optional_param('activitydirection', 'DESC', PARAM_ALPHA),
];

$wishlistsreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/wishlists_admin',
    'id' => 'moderncommerce-wishlists-admin-app',
    'class' => 'local-moderncommerce-wishlists-admin',
    'props' => [
        'methodName' => 'local_moderncommerce_admin_list_wishlists',
        'initialFilters' => $initialfilters,
        'productTypes' => [
            ['value' => '', 'label' => get_string('allproducttypes', 'local_moderncommerce')],
            ['value' => 'course', 'label' => get_string('course')],
            ['value' => 'bundle', 'label' => get_string('bundle', 'local_moderncommerce')],
            ['value' => 'program', 'label' => get_string('program', 'local_moderncommerce')],
            ['value' => 'subscription', 'label' => get_string('subscription', 'local_moderncommerce')],
            ['value' => 'digital', 'label' => get_string('digitalproduct', 'local_moderncommerce')],
        ],
        'perPageOptions' => [10, 25, 50, 100],
        'labels' => [
            'title' => get_string('wishlists', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchplaceholder' => get_string('wishlistssearchplaceholder', 'local_moderncommerce'),
            'productsearchplaceholder' => get_string('wishlistproductsearchplaceholder', 'local_moderncommerce'),
            'activitysearchplaceholder' => get_string('wishlistactivitysearchplaceholder', 'local_moderncommerce'),
            'type' => get_string('type', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'next' => get_string('next'),
            'saveditems' => get_string('saveditems', 'local_moderncommerce'),
            'wishlistedproducts' => get_string('wishlistedproducts', 'local_moderncommerce'),
            'wishlistcustomers' => get_string('wishlistcustomers', 'local_moderncommerce'),
            'lastsaved' => get_string('lastsaved', 'local_moderncommerce'),
            'mostwishlistedproducts' => get_string('mostwishlistedproducts', 'local_moderncommerce'),
            'recentwishlistactivity' => get_string('recentwishlistactivity', 'local_moderncommerce'),
            'customer' => get_string('customer', 'local_moderncommerce'),
            'email' => get_string('email'),
            'product' => get_string('product', 'local_moderncommerce'),
            'sku' => get_string('sku', 'local_moderncommerce'),
            'price' => get_string('price', 'local_moderncommerce'),
            'date' => get_string('date', 'local_moderncommerce'),
            'customers' => get_string('customers', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'viewdetails' => get_string('viewdetails', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'noresults' => get_string('noresults', 'local_moderncommerce'),
            'nowishlistitems' => get_string('nowishlistitems', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/wishlists', [
    'wishlistsreactconfig' => $wishlistsreactconfig,
]);

$actionshtml = admin_shell::action_group([
    [
        'type' => 'button',
        'label' => get_string('refresh'),
        'icon' => 'bi-arrow-clockwise',
        'attributes' => ['id' => 'moderncommerce-wishlists-refresh'],
    ],
]);

$shellhtml = admin_shell::render_page(
    $OUTPUT,
    'wishlists',
    get_string('wishlists', 'local_moderncommerce'),
    $contenthtml,
    get_string('wishlists_desc', 'local_moderncommerce'),
    $actionshtml
);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
