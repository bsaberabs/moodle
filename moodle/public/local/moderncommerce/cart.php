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
 * Buyer shopping cart page.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use local_moderncommerce\api\cart_api;
use local_moderncommerce\output\learner_shell;
use local_moderncommerce\output\validation_labels;

require_login();

$context = context_system::instance();
require_capability('local/moderncommerce:purchase', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/moderncommerce/cart.php'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('mc-learner-layout-wide');
$PAGE->add_body_class('mc-learner-dashboard-layout');
$PAGE->set_title(get_string('cart', 'local_moderncommerce'));
$PAGE->set_heading(get_string('cart', 'local_moderncommerce'));

// Keep old add/remove URLs working while the page itself renders through React.
$action = optional_param('action', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);
$bundleid = optional_param('bundleid', 0, PARAM_INT);
$quantity = optional_param('quantity', 1, PARAM_INT);

if ($action && confirm_sesskey()) {
    try {
        switch ($action) {
            case 'add':
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
                throw new moodle_exception('error:invalidcourse', 'local_moderncommerce');
                break;

            case 'remove':
                if ($bundleid > 0) {
                    cart_api::remove_bundle_from_cart((int)$USER->id, $bundleid);
                } else if ($courseid > 0) {
                    cart_api::remove_from_cart((int)$USER->id, $courseid);
                }
                redirect(
                    $PAGE->url,
                    get_string('removedcart', 'local_moderncommerce'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
                break;

            case 'update':
                cart_api::update_quantity((int)$USER->id, $courseid, $quantity);
                redirect(
                    $PAGE->url,
                    get_string('cartupdated', 'local_moderncommerce'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
                break;

            case 'clear':
                unset($SESSION->moderncommerce_coupon);
                cart_api::clear_cart((int)$USER->id);
                redirect(
                    $PAGE->url,
                    get_string('cartcleared', 'local_moderncommerce'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
        }
    } catch (Exception $e) {
        \core\notification::error($e->getMessage());
    }
}

local_moderncommerce_set_breadcrumb(
    get_string('cart', 'local_moderncommerce'),
    (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
    get_string('catalog', 'local_moderncommerce')
);

$shell = learner_shell::create('cart');

$reactconfig = json_encode([
    'component' => '@moodle/lms/local_moderncommerce/cart',
    'id' => 'moderncommerce-cart-app',
    'class' => 'local-moderncommerce-cart',
    'props' => [
        'methodName' => 'local_moderncommerce_get_cart',
        'updateMethodName' => 'local_moderncommerce_update_cart',
        'layout' => $shell->get_react_layout_context(),
        'labels' => validation_labels::all() + [
            'applycoupon' => get_string('applycoupon', 'local_moderncommerce'),
            'browsecatalog' => get_string('browsecatalog', 'local_moderncommerce'),
            'cartandcheckout' => get_string('cartandcheckout', 'local_moderncommerce'),
            'carttotal' => get_string('carttotal', 'local_moderncommerce'),
            'cancel' => get_string('cancel'),
            'clearcart' => get_string('clearcart', 'local_moderncommerce'),
            'clearcartconfirm' => get_string('clearcartconfirm', 'local_moderncommerce'),
            'clearcartconfirmlabel' => get_string('clearcart', 'local_moderncommerce'),
            'clearcarttitle' => get_string('clearcart', 'local_moderncommerce'),
            'continueshopping' => get_string('continueshopping', 'local_moderncommerce'),
            'couponcode' => get_string('couponcode', 'local_moderncommerce'),
            'checkoutdesc' => get_string('checkoutdesc', 'local_moderncommerce'),
            'discount' => get_string('discount', 'local_moderncommerce'),
            'emptycart' => get_string('emptycart', 'local_moderncommerce'),
            'emptycartmessage' => get_string('emptycartmessage', 'local_moderncommerce'),
            'entercouponcode' => get_string('entercouponcode', 'local_moderncommerce'),
            'error' => get_string('error', 'local_moderncommerce'),
            'loading' => get_string('loading', 'local_moderncommerce'),
            'ordersummary' => get_string('ordersummary', 'local_moderncommerce'),
            'proceedtocheckout' => get_string('proceedtocheckout', 'local_moderncommerce'),
            'removeconfirm' => get_string('removeconfirm', 'local_moderncommerce'),
            'removeconfirmlabel' => get_string('remove'),
            'removetitle' => get_string('removefromcart', 'local_moderncommerce'),
            'removefromcart' => get_string('removefromcart', 'local_moderncommerce'),
            'removecoupon' => get_string('removecoupon', 'local_moderncommerce'),
            'retry' => get_string('retry', 'local_moderncommerce'),
            'securepayment' => get_string('securepayment', 'local_moderncommerce'),
            'shoppingcart' => get_string('shoppingcart', 'local_moderncommerce'),
            'subtotal' => get_string('cartsubtotal', 'local_moderncommerce'),
            'tax' => get_string('tax', 'local_moderncommerce'),
        ],
    ],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_moderncommerce/react_mount', [
    'region' => 'moderncommerce-cart',
    'reactconfig' => $reactconfig,
    'icon' => 'bi-cart',
]);
echo $OUTPUT->footer();
