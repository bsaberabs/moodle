<?php
// This file is part of Moodle - https://moodle.org/
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
 * "Add to cart" clicked by a visitor of the store: they log in or sign up first (Moodle brings them back here), then the
 * course or bundle goes into their cart and they land on it.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = optional_param('course', 0, PARAM_INT);
$bundleid = optional_param('bundle', 0, PARAM_INT);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/oriskill/tocart.php', array_filter(['course' => $courseid, 'bundle' => $bundleid])));
require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('guest');
}

$cart = new moodle_url('/local/moderncommerce/cart.php');
try {
    if ($bundleid) {
        \local_moderncommerce\api\cart_api::add_bundle_to_cart((int) $USER->id, $bundleid);
    } else if ($courseid) {
        \local_moderncommerce\api\cart_api::add_to_cart((int) $USER->id, $courseid);
    }
} catch (moodle_exception $e) {
    // Already bought, already in the cart, not on sale: the cart says what it holds.
    redirect($cart, $e->getMessage(), null, \core\output\notification::NOTIFY_INFO);
}
redirect($cart);
