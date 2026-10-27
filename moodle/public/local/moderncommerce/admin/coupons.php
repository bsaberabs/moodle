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
 * Admin coupon management page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use local_moderncommerce\output\admin_shell;
use local_moderncommerce\output\validation_labels;
use local_moderncommerce\services\pricing_service;

$context = context_system::instance();
require_login();
require_capability('local/moderncommerce:managecoupons', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/admin/coupons.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_secondary_navigation(false);
$PAGE->set_primary_active_tab('ccpadmin');
$PAGE->set_title(get_string('managecoupons', 'local_moderncommerce'));

$currencyconfig = pricing_service::get_currency_config();

$initialfilters = [
    'search' => optional_param('search', '', PARAM_TEXT),
    'status' => optional_param('status', '', PARAM_ALPHANUMEXT),
    'discounttype' => optional_param('discounttype', '', PARAM_ALPHANUMEXT),
    'page' => optional_param('page', 0, PARAM_INT),
    'perpage' => optional_param('perpage', 10, PARAM_INT),
    'sort' => optional_param('sort', 'timecreated', PARAM_ALPHANUMEXT),
    'direction' => optional_param('direction', 'DESC', PARAM_ALPHA),
];

$couponsreactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/coupons_admin',
    'id' => 'moderncommerce-coupons-admin-app',
    'class' => 'local-moderncommerce-coupons-admin',
    'props' => [
        'methodName' => 'local_moderncommerce_list_coupons',
        'saveMethodName' => 'local_moderncommerce_save_coupon',
        'archiveMethodName' => 'local_moderncommerce_archive_coupon',
        'listTargetsMethodName' => 'local_moderncommerce_list_coupon_targets',
        'saveTargetMethodName' => 'local_moderncommerce_save_coupon_target',
        'deleteTargetMethodName' => 'local_moderncommerce_delete_coupon_target',
        'searchTargetOptionsMethodName' => 'local_moderncommerce_search_coupon_target_options',
        'initialFilters' => $initialfilters,
        'currency' => [
            'code' => $currencyconfig->currency,
            'symbol' => $currencyconfig->symbol,
            'position' => $currencyconfig->position,
            'decimals' => (int) $currencyconfig->decimals,
        ],
        'labels' => validation_labels::all() + validation_labels::crossfield() + [
            'error:invalidcouponcode' => get_string('error:invalidcouponcode', 'local_moderncommerce'),
            'active' => get_string('active', 'local_moderncommerce'),
            'actions' => get_string('actions', 'local_moderncommerce'),
            'all' => get_string('all'),
            'allstatuses' => get_string('allcouponstatuses', 'local_moderncommerce'),
            'alltypes' => get_string('allcoupontypes', 'local_moderncommerce'),
            'addtarget' => get_string('addtarget', 'local_moderncommerce'),
            'appliesall' => get_string('appliesall', 'local_moderncommerce'),
            'appliesto' => get_string('appliesto', 'local_moderncommerce'),
            'archivecoupon' => get_string('archivecoupon', 'local_moderncommerce'),
            'archivecouponconfirm' => get_string('archivecouponconfirm', 'local_moderncommerce'),
            'archived' => get_string('archived', 'local_moderncommerce'),
            'cancel' => get_string('cancel'),
            'code' => get_string('couponcode', 'local_moderncommerce'),
            'constraints' => get_string('couponconstraints', 'local_moderncommerce'),
            'createcoupon' => get_string('createcoupon', 'local_moderncommerce'),
            'coupontargetrequiresselection' => get_string('coupontargetrequiresselection', 'local_moderncommerce'),
            'coupontargetrequiresvalue' => get_string('coupontargetrequiresvalue', 'local_moderncommerce'),
            'deletetarget' => get_string('deletetarget', 'local_moderncommerce'),
            'deletetargetconfirm' => get_string('deletetargetconfirm', 'local_moderncommerce'),
            'depleted' => get_string('depleted', 'local_moderncommerce'),
            'discounttotal' => get_string('discounttotal', 'local_moderncommerce'),
            'edit' => get_string('edit'),
            'editcoupon' => get_string('editcoupon', 'local_moderncommerce'),
            'enddate' => get_string('enddate', 'local_moderncommerce'),
            'exclude' => get_string('exclude', 'local_moderncommerce'),
            'expired' => get_string('expired', 'local_moderncommerce'),
            'include' => get_string('include', 'local_moderncommerce'),
            'includemode' => get_string('includemode', 'local_moderncommerce'),
            'inactive' => get_string('inactive', 'local_moderncommerce'),
            'leaveemptynoexpiry' => get_string('leaveemptynoexpiry', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'loadingtargets' => get_string('loadingtargets', 'local_moderncommerce'),
            'managecoupons' => get_string('managecoupons', 'local_moderncommerce'),
            'maxdiscount' => get_string('maxdiscount', 'local_moderncommerce'),
            'maxusesperuser' => get_string('maxusesperuser', 'local_moderncommerce'),
            'minitems' => get_string('minitems', 'local_moderncommerce'),
            'minpurchase' => get_string('minpurchase', 'local_moderncommerce'),
            'name' => get_string('couponname', 'local_moderncommerce'),
            'newcoupon' => get_string('newcoupon', 'local_moderncommerce'),
            'nocouponsfound' => get_string('nocouponsfound', 'local_moderncommerce'),
            'noresults' => get_string('nocouponmatches', 'local_moderncommerce'),
            'notargetmatches' => get_string('notargetmatches', 'local_moderncommerce'),
            'notargets' => get_string('notargets', 'local_moderncommerce'),
            'optional' => get_string('optional', 'local_moderncommerce'),
            'page' => get_string('page', 'local_moderncommerce'),
            'perpage' => get_string('perpage', 'local_moderncommerce'),
            'previous' => get_string('previous'),
            'refresh' => get_string('refresh'),
            'redemptions' => get_string('couponredemptions', 'local_moderncommerce'),
            'savecoupon' => get_string('savecoupon', 'local_moderncommerce'),
            'savecouponfirsttargets' => get_string('savecouponfirsttargets', 'local_moderncommerce'),
            'scheduled' => get_string('scheduled', 'local_moderncommerce'),
            'search' => get_string('search'),
            'searchplaceholder' => get_string('searchcouponsplaceholder', 'local_moderncommerce'),
            'searchtarget' => get_string('searchtarget', 'local_moderncommerce'),
            'searchtargetsplaceholder' => get_string('searchtargetsplaceholder', 'local_moderncommerce'),
            'selectedtarget' => get_string('selectedtarget', 'local_moderncommerce'),
            'showing' => get_string('showing', 'local_moderncommerce'),
            'stackable' => get_string('stackable', 'local_moderncommerce'),
            'startdate' => get_string('startdate', 'local_moderncommerce'),
            'status' => get_string('status'),
            'target' => get_string('target', 'local_moderncommerce'),
            'targettype' => get_string('targettype', 'local_moderncommerce'),
            'targetvalue' => get_string('targetvalue', 'local_moderncommerce'),
            'title' => get_string('managecoupons', 'local_moderncommerce'),
            'next' => get_string('next'),
            'totalcoupons' => get_string('totalcoupons', 'local_moderncommerce'),
            'totaldiscount' => get_string('totaldiscount', 'local_moderncommerce'),
            'type' => get_string('coupontype', 'local_moderncommerce'),
            'unlimited' => get_string('unlimited', 'local_moderncommerce'),
            'updated' => get_string('updated', 'local_moderncommerce'),
            'usage' => get_string('couponusage', 'local_moderncommerce'),
            'usagelimit' => get_string('couponusagelimit', 'local_moderncommerce'),
            'validity' => get_string('validity', 'local_moderncommerce'),
            'value' => get_string('couponvalue', 'local_moderncommerce'),
        ],
        'typeOptions' => [
            ['value' => '', 'label' => get_string('allcoupontypes', 'local_moderncommerce')],
            ['value' => 'percentage', 'label' => get_string('coupontype_percentage', 'local_moderncommerce')],
            ['value' => 'fixed', 'label' => get_string('coupontype_fixed', 'local_moderncommerce')],
        ],
        'targetTypes' => [
            ['value' => 'product', 'label' => get_string('targettype_product', 'local_moderncommerce')],
            ['value' => 'course', 'label' => get_string('targettype_course', 'local_moderncommerce')],
            ['value' => 'productcategory', 'label' => get_string('targettype_productcategory', 'local_moderncommerce')],
            ['value' => 'coursecategory', 'label' => get_string('targettype_coursecategory', 'local_moderncommerce')],
            ['value' => 'producttype', 'label' => get_string('targettype_producttype', 'local_moderncommerce')],
            ['value' => 'sku', 'label' => get_string('targettype_sku', 'local_moderncommerce')],
        ],
        'includeModes' => [
            ['value' => 'include', 'label' => get_string('include', 'local_moderncommerce')],
            ['value' => 'exclude', 'label' => get_string('exclude', 'local_moderncommerce')],
        ],
        'statusOptions' => [
            ['value' => '', 'label' => get_string('allcouponstatuses', 'local_moderncommerce')],
            ['value' => 'active', 'label' => get_string('active', 'local_moderncommerce')],
            ['value' => 'scheduled', 'label' => get_string('scheduled', 'local_moderncommerce')],
            ['value' => 'expired', 'label' => get_string('expired', 'local_moderncommerce')],
            ['value' => 'depleted', 'label' => get_string('depleted', 'local_moderncommerce')],
            ['value' => 'inactive', 'label' => get_string('inactive', 'local_moderncommerce')],
            ['value' => 'archived', 'label' => get_string('archived', 'local_moderncommerce')],
        ],
        'editableStatuses' => [
            ['value' => 'active', 'label' => get_string('active', 'local_moderncommerce')],
            ['value' => 'inactive', 'label' => get_string('inactive', 'local_moderncommerce')],
            ['value' => 'archived', 'label' => get_string('archived', 'local_moderncommerce')],
        ],
        'perPageOptions' => [10, 25, 50, 100],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$contenthtml = $OUTPUT->render_from_template('local_moderncommerce/admin/coupons', [
    'couponsreactconfig' => $couponsreactconfig,
]);

$shell = admin_shell::create('coupons')
    ->set_title(get_string('managecoupons', 'local_moderncommerce'))
    ->set_subtitle(get_string('managecouponssubtitle', 'local_moderncommerce'))
    ->set_actions(admin_shell::action_group([
        [
            'type' => 'button',
            'label' => get_string('refresh'),
            'icon' => 'bi-arrow-clockwise',
            'attributes' => ['id' => 'moderncommerce-coupons-refresh'],
        ],
        [
            'type' => 'button',
            'label' => get_string('newcoupon', 'local_moderncommerce'),
            'icon' => 'bi-plus',
            'primary' => true,
            'attributes' => ['id' => 'moderncommerce-coupons-new'],
        ],
    ]))
    ->set_content($contenthtml);

$shellhtml = $shell->render($OUTPUT);

echo $OUTPUT->header();
echo $shellhtml;
echo $OUTPUT->footer();
