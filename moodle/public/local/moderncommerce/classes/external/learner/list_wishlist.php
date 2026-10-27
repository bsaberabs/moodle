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
 * External API for learner wishlist.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\services\access_service;
use local_moderncommerce\services\pricing_service;
use moodle_url;

/**
 * Return saved products for the current learner.
 */
class list_wishlist extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        self::validate_parameters(self::execute_parameters(), []);
        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        return self::wishlist_data((int)$USER->id);
    }

    /**
     * Build wishlist response.
     *
     * @param int $userid User ID.
     * @param string $message Optional message.
     * @return array
     */
    public static function wishlist_data(int $userid, string $message = ''): array {
        global $DB;

        $sql = "SELECT w.id AS wishlistid,
                       w.timecreated AS savedtime,
                       p.id AS productid,
                       p.producttype,
                       p.name,
                       p.imageurl,
                       p.status,
                       p.visible,
                       pr.amount,
                       pr.compareamount,
                       pc.courseid,
                       c.fullname AS coursename,
                       c.category AS coursecategory,
                       cc.name AS categoryname
                  FROM {local_moderncommerce_wishlist} w
                  JOIN {local_moderncommerce_products} p ON p.id = w.productid
             LEFT JOIN {local_moderncommerce_product_prices} pr
                    ON pr.productid = p.id
                   AND pr.pricetype = :pricetype
                   AND pr.enabled = 1
             LEFT JOIN {local_moderncommerce_product_courses} pc
                    ON pc.productid = p.id
                   AND pc.relationtype = :relationtype
                   AND p.producttype = :courseproduct
             LEFT JOIN {course} c ON c.id = pc.courseid
             LEFT JOIN {course_categories} cc ON cc.id = c.category
                 WHERE w.userid = :userid
              ORDER BY w.timecreated DESC, w.id DESC";

        $records = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'pricetype' => 'regular',
            'relationtype' => 'included',
            'courseproduct' => 'course',
        ]);

        $items = [];
        foreach ($records as $record) {
            if (isset($items[(int)$record->productid])) {
                continue;
            }
            $items[(int)$record->productid] = self::normalise_item($record, $userid);
        }

        $items = array_values($items);

        return [
            'success' => true,
            'message' => $message,
            'items' => $items,
            'stats' => [
                'total' => count($items),
                'available' => count(array_filter($items, static fn(array $item): bool => $item['available'])),
            ],
            'urls' => [
                'catalog' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
                'cart' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/cart',
            ],
        ];
    }

    /**
     * Normalise a wishlist row.
     *
     * @param \stdClass $record DB record.
     * @param int $userid User ID.
     * @return array
     */
    private static function normalise_item(\stdClass $record, int $userid): array {
        $productid = (int)$record->productid;
        $producttype = strtolower((string)$record->producttype);
        $courseid = (int)($record->courseid ?? 0);
        $title = $courseid > 0 && !empty($record->coursename)
            ? format_string((string)$record->coursename)
            : format_string((string)$record->name);
        $price = $record->amount !== null ? (float)$record->amount : 0.0;
        $compare = $record->compareamount !== null ? (float)$record->compareamount : 0.0;
        $available = (string)$record->status === 'active' && !empty($record->visible);
        $thumbnail = (string)($record->imageurl ?? '');

        if ($thumbnail === '' && $courseid > 0 && function_exists('local_moderncommerce_get_course_image_url')) {
            $thumbnail = \local_moderncommerce_get_course_image_url($courseid);
        }
        if (
            $thumbnail === '' && in_array($producttype, ['bundle', 'program'], true)
            && function_exists('local_moderncommerce_get_bundle_image_url')
        ) {
            $thumbnail = \local_moderncommerce_get_bundle_image_url($productid);
        }

        $detailsurl = '#';
        if ($producttype === 'course' && $courseid > 0) {
            $detailsurl = (new moodle_url('/local/moderncommerce/course_details.php', ['id' => $courseid]))->out(false);
        } else if (in_array($producttype, ['bundle', 'program'], true)) {
            $detailsurl = (new moodle_url('/local/moderncommerce/bundle_details.php', ['id' => $productid]))->out(false);
        }

        $hasaccess = false;
        if ($producttype === 'course' && $courseid > 0) {
            $hasaccess = access_service::user_has_course_access($userid, $courseid);
        } else if (in_array($producttype, ['bundle', 'program'], true)) {
            $hasaccess = access_service::user_has_product_purchase_access($userid, $productid);
        }

        return [
            'wishlistid' => (int)$record->wishlistid,
            'productid' => $productid,
            'courseid' => $courseid,
            'title' => $title,
            'producttype' => $producttype,
            'typelabel' => ucfirst($producttype),
            'category' => format_string((string)($record->categoryname ?? '')),
            'thumbnail' => $thumbnail,
            'displayprice' => pricing_service::format_price($price),
            'displayoriginalprice' => $compare > $price ? pricing_service::format_price($compare) : '',
            'hasoriginalprice' => $compare > $price,
            'detailsurl' => $detailsurl,
            'saveddate' => userdate((int)$record->savedtime, get_string('strftimedate')),
            'available' => $available,
            'hasaccess' => $hasaccess,
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether wishlist loaded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'items' => new external_multiple_structure(new external_single_structure([
                'wishlistid' => new external_value(PARAM_INT, 'Wishlist row ID.'),
                'productid' => new external_value(PARAM_INT, 'Product ID.'),
                'courseid' => new external_value(PARAM_INT, 'Course ID where applicable.'),
                'title' => new external_value(PARAM_TEXT, 'Product title.'),
                'producttype' => new external_value(PARAM_ALPHANUMEXT, 'Product type.'),
                'typelabel' => new external_value(PARAM_TEXT, 'Product type label.'),
                'category' => new external_value(PARAM_TEXT, 'Category.'),
                'thumbnail' => new external_value(PARAM_RAW, 'Thumbnail URL.'),
                'displayprice' => new external_value(PARAM_TEXT, 'Formatted price.'),
                'displayoriginalprice' => new external_value(PARAM_TEXT, 'Formatted compare price.'),
                'hasoriginalprice' => new external_value(PARAM_BOOL, 'Whether compare price exists.'),
                'detailsurl' => new external_value(PARAM_RAW, 'Details URL.'),
                'saveddate' => new external_value(PARAM_TEXT, 'Saved date.'),
                'available' => new external_value(PARAM_BOOL, 'Whether product can be sold.'),
                'hasaccess' => new external_value(PARAM_BOOL, 'Whether learner already owns access.'),
            ])),
            'stats' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total saved items.'),
                'available' => new external_value(PARAM_INT, 'Available saved items.'),
            ]),
            'urls' => new external_single_structure([
                'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
                'cart' => new external_value(PARAM_RAW, 'Cart URL.'),
            ]),
        ]);
    }
}
