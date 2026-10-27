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
 * External API for learner wishlist mutations.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use local_moderncommerce\api\cart_api;

/**
 * Add, remove, or move saved products.
 */
class update_wishlist extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Action: add, remove, movetocart.', VALUE_REQUIRED),
            'productid' => new external_value(PARAM_INT, 'Product ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $action Action.
     * @param int $productid Product ID.
     * @return array
     */
    public static function execute(string $action, int $productid): array {
        global $DB, $USER;

        ['action' => $action, 'productid' => $productid] = self::validate_parameters(self::execute_parameters(), [
            'action' => $action,
            'productid' => $productid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        $productid = max(0, (int)$productid);
        $product = $productid > 0 ? $DB->get_record('local_moderncommerce_products', ['id' => $productid]) : false;
        if (!$product) {
            throw new invalid_parameter_exception('Invalid product ID.');
        }

        $userid = (int)$USER->id;
        $message = '';

        switch ($action) {
            case 'add':
                if (!$DB->record_exists('local_moderncommerce_wishlist', ['userid' => $userid, 'productid' => $productid])) {
                    $DB->insert_record('local_moderncommerce_wishlist', (object)[
                        'userid' => $userid,
                        'productid' => $productid,
                        'timecreated' => time(),
                    ]);
                }
                $message = get_string('wishlistadded', 'local_moderncommerce');
                break;

            case 'remove':
                $DB->delete_records('local_moderncommerce_wishlist', ['userid' => $userid, 'productid' => $productid]);
                $message = get_string('wishlistremoved', 'local_moderncommerce');
                break;

            case 'movetocart':
                self::move_to_cart($userid, $product);
                $DB->delete_records('local_moderncommerce_wishlist', ['userid' => $userid, 'productid' => $productid]);
                $message = get_string('wishlistmovedtocart', 'local_moderncommerce');
                break;

            default:
                throw new invalid_parameter_exception('Invalid wishlist action.');
        }

        return list_wishlist::wishlist_data($userid, $message);
    }

    /**
     * Move a product to the current buyer cart.
     *
     * @param int $userid User ID.
     * @param \stdClass $product Product record.
     */
    private static function move_to_cart(int $userid, \stdClass $product): void {
        global $DB;

        $producttype = strtolower((string)$product->producttype);
        if ($producttype === 'course') {
            $courseid = (int)$DB->get_field('local_moderncommerce_product_courses', 'courseid', [
                'productid' => (int)$product->id,
                'relationtype' => 'included',
            ]);
            if ($courseid <= 0) {
                throw new invalid_parameter_exception('Course product is missing a course link.');
            }
            cart_api::add_to_cart($userid, $courseid);
            return;
        }

        if (in_array($producttype, ['bundle', 'program'], true)) {
            cart_api::add_bundle_to_cart($userid, (int)$product->id);
            return;
        }

        throw new invalid_parameter_exception('This product type cannot be moved to cart.');
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return list_wishlist::execute_returns();
    }
}
