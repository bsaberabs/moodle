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
 * Cart page renderable.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

use renderable;
use templatable;
use core\output\renderer_base;
use moodle_url;
use local_moderncommerce\api\cart_api;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\services\pricing_service;

/**
 * Cart page renderable class.
 */
class cart_page implements renderable, templatable {
    /** @var int User ID */
    protected $userid;

    /** @var array Course cart items */
    protected $cartitems;

    /** @var array Bundle cart items */
    protected $bundleitems;

    /** @var string Session key */
    protected $sesskey;

    /**
     * Constructor.
     *
     * @param int $userid User ID
     */
    public function __construct(int $userid) {
        $this->userid = $userid;
        $this->sesskey = sesskey();
        $this->load_cart_items();
    }

    /**
     * Load cart items from database.
     */
    protected function load_cart_items(): void {
        $this->cartitems = cart_api::get_cart_items($this->userid);
        $this->bundleitems = cart_api::get_bundle_cart_items($this->userid);
    }

    /**
     * Check if cart is empty.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return empty($this->cartitems) && empty($this->bundleitems);
    }

    /**
     * Get total item count.
     *
     * @return int
     */
    public function get_item_count(): int {
        return count($this->cartitems) + count($this->bundleitems);
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $data = [
            'isempty' => $this->is_empty(),
            'itemcount' => $this->get_item_count(),
            'sesskey' => $this->sesskey,
            'catalogurl' => (new moodle_url('/local/moderncommerce/index.php'))->out(false),
            'carturl' => (new moodle_url('/local/moderncommerce/cart.php'))->out(false),
            'checkouturl' => (new moodle_url('/local/moderncommerce/checkout.php'))->out(false),
        ];

        if ($this->is_empty()) {
            return $data;
        }

        $items = [];
        $subtotal = 0;

        // Process course items.
        foreach ($this->cartitems as $item) {
            $courseitem = $this->prepare_course_item($item);
            $items[] = $courseitem;
            $subtotal += $item->price * $item->quantity;
        }

        // Process bundle items.
        foreach ($this->bundleitems as $item) {
            $bundleitem = $this->prepare_bundle_item($item);
            $items[] = $bundleitem;
            $subtotal += $bundleitem['numericprice'] * $item->quantity;
        }

        $data['items'] = $items;
        $data['hasitems'] = !empty($items);

        // Calculate totals.
        $taxenabled = get_config('local_moderncommerce', 'enable_tax');
        $taxrate = 0;
        $tax = 0;

        if ($taxenabled) {
            $defaultrate = get_config('local_moderncommerce', 'default_tax_rate');
            if ($defaultrate) {
                $taxrate = floatval($defaultrate) / 100;
                $tax = $subtotal * $taxrate;
            }
        }

        $total = $subtotal + $tax;

        $data['subtotal'] = pricing_service::format_price($subtotal);
        $data['subtotalnumeric'] = $subtotal;
        $data['hastax'] = $taxenabled && $tax > 0;
        $data['tax'] = pricing_service::format_price($tax);
        $data['taxrate'] = round($taxrate * 100, 1);
        $data['total'] = pricing_service::format_price($total);
        $data['totalnumeric'] = $total;

        // Clear cart URL.
        $data['clearcarturl'] = (new moodle_url('/local/moderncommerce/cart.php', [
            'action' => 'clear',
            'sesskey' => $this->sesskey,
        ]))->out(false);

        return $data;
    }
    /**
     * Prepare course item data for template.
     *
     * @param object $item Cart item
     * @return array
     */
    protected function prepare_course_item(object $item): array {
        $course = get_course($item->courseid);
        $imageurl = \local_moderncommerce_get_course_image_url($item->courseid);

        return [
            'id' => $item->courseid,
            'type' => 'course',
            'iscourse' => true,
            'isbundle' => false,
            'name' => format_string($item->coursename),
            'shortname' => format_string($course->shortname),
            'imageurl' => $imageurl ?: '',
            'hasimage' => !empty($imageurl),
            'detailsurl' => (new moodle_url('/local/moderncommerce/course_details.php', ['id' => $item->courseid]))->out(false),
            'price' => pricing_service::format_price($item->price),
            'numericprice' => $item->price,
            'quantity' => $item->quantity,
            'removeurl' => (new moodle_url('/local/moderncommerce/cart.php', [
                'action' => 'remove',
                'courseid' => $item->courseid,
                'sesskey' => $this->sesskey,
            ]))->out(false),
        ];
    }

    /**
     * Prepare bundle item data for template.
     *
     * @param object $item Bundle cart item
     * @return array
     */
    protected function prepare_bundle_item(object $item): array {

        $bundle = bundle_api::get($item->bundleid);
        $adjustedprice = (float) $item->price;
        // Get bundle image.
        $imageurl = '';
        if (function_exists('local_moderncommerce_get_bundle_image_url')) {
            $imageurl = local_moderncommerce_get_bundle_image_url($item->bundleid);
        }

        // Count courses in bundle.
        $coursecount = 0;
        $coursecount = count(bundle_api::get_courses($item->bundleid));

        return [
            'id' => $item->bundleid,
            'type' => $item->itemtype ?? ($bundle->producttype ?? 'bundle'),
            'iscourse' => false,
            'isbundle' => true,
            'name' => format_string($item->bundlename ?: ($bundle->name ?? get_string('unknownbundle', 'local_moderncommerce'))),
            'shortname' => '',
            'imageurl' => $imageurl ?: '',
            'hasimage' => !empty($imageurl),
            'detailsurl' => (new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $item->bundleid]))->out(false),
            'price' => pricing_service::format_price($adjustedprice),
            'numericprice' => $adjustedprice,
            'quantity' => $item->quantity,
            'coursecount' => $coursecount,
            'removeurl' => (new moodle_url('/local/moderncommerce/cart.php', [
                'action' => 'remove',
                'bundleid' => $item->bundleid,
                'sesskey' => $this->sesskey,
            ]))->out(false),
        ];
    }
}
