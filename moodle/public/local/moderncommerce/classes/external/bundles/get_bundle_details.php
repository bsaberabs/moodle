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
 * External API for bundle/program details.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\bundles;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\api\bundle_api;
use local_moderncommerce\output\bundle_details_page;
use local_moderncommerce\services\access_service;
use moodle_url;

/**
 * Return one bundle/program details dataset for React storefront rendering.
 */
class get_bundle_details extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Bundle or program product ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Bundle or program product ID.
     * @return array Details response.
     */
    public static function execute(int $id): array {
        global $CFG, $PAGE, $USER;

        ['id' => $id] = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        $context = context_system::instance();
        self::validate_public_catalog_context($context);
        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

        if ($id <= 0) {
            return self::error_response(get_string('bundlenotfound', 'local_moderncommerce'));
        }

        $bundle = bundle_api::get($id);
        if (!$bundle) {
            return self::error_response(get_string('bundlenotfound', 'local_moderncommerce'));
        }

        if (empty($bundle->visible) || $bundle->status !== 'active') {
            return self::error_response(get_string('bundlenotavailable', 'local_moderncommerce'));
        }

        $page = new bundle_details_page($bundle);
        $renderer = $PAGE->get_renderer('core');
        $data = $page->export_for_template($renderer);
        $producttype = (string)($bundle->producttype ?? (!empty($bundle->isprogram) ? 'program' : 'bundle'));
        $userid = isloggedin() && !isguestuser() ? (int)$USER->id : 0;
        $canmanageblocks = is_siteadmin() || has_capability('moodle/site:config', $context);
        $accessurl = (
            !$canmanageblocks
            && isloggedin()
            && !isguestuser()
            && access_service::user_has_product_purchase_access((int)$USER->id, $id)
        )
            ? access_service::learner_product_access_url($id, $producttype)
            : null;

        return [
            'success' => true,
            'message' => '',
            'bundle' => self::normalise_bundle($data['bundle'] ?? []),
            'price' => self::normalise_price($data['price'] ?? []),
            'state' => [
                'purchased' => !empty($data['ispurchased']),
                'accessallcourses' => !empty($data['accessallcourses']),
                'hasownedcourses' => !empty($data['hasownedcourses']),
                'ownedcoursescount' => (int)($data['ownedcoursescount'] ?? 0),
                'totalcoursescount' => (int)($data['totalcoursescount'] ?? 0),
                'hascourses' => !empty($data['hascourses']),
                'isavailable' => !empty($data['isavailable']),
                'showfeatured' => !empty($data['showfeatured']),
                'showbestseller' => !empty($data['showbestseller']),
                'isloggedin' => $userid > 0,
                'productid' => $id,
                'inwishlist' => self::is_in_wishlist($id, $userid),
            ],
            'courses' => array_map([self::class, 'normalise_course'], $data['courses'] ?? []),
            'urls' => [
                'addtocart' => (string)($data['addtocarturl'] ?? ''),
                'checkout' => (string)($data['checkouturl'] ?? ''),
                'catalog' => (string)($data['catalogurl'] ?? (new moodle_url('/local/moderncommerce/index.php'))->out(false)),
                'launch' => $accessurl ? $accessurl->out(false) : '',
            ],
        ];
    }

    /**
     * Validate catalog access without forcing anonymous visitors to log in.
     *
     * @param context_system $context System context.
     */
    private static function validate_public_catalog_context(context_system $context): void {
        global $CFG, $PAGE;

        if (isloggedin() && !isguestuser()) {
            self::validate_context($context);
            require_capability('local/moderncommerce:viewcatalog', $context);
            return;
        }

        $PAGE->set_context($context);
        $guestuserid = !empty($CFG->siteguest) ? (int)$CFG->siteguest : 0;
        require_capability('local/moderncommerce:viewcatalog', $context, $guestuserid);
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether details loaded successfully.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'bundle' => self::bundle_structure(),
            'price' => self::price_structure(),
            'state' => self::state_structure(),
            'courses' => new external_multiple_structure(self::course_structure()),
            'urls' => self::urls_structure(),
        ]);
    }

    /**
     * Bundle structure.
     *
     * @return external_single_structure
     */
    private static function bundle_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Bundle product ID.'),
            'name' => new external_value(PARAM_TEXT, 'Bundle name.'),
            'description' => new external_value(PARAM_RAW, 'Plain bundle description.'),
            'shortdescription' => new external_value(PARAM_RAW, 'Plain short description.'),
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Bundle type.'),
            'isprogram' => new external_value(PARAM_BOOL, 'Whether this is a program.'),
            'coursecount' => new external_value(PARAM_INT, 'Included course count.'),
            'imageurl' => new external_value(PARAM_RAW, 'Bundle image URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether an image URL exists.'),
        ]);
    }

    /**
     * Price structure.
     *
     * @return external_single_structure
     */
    private static function price_structure(): external_single_structure {
        return new external_single_structure([
            'current' => new external_value(PARAM_TEXT, 'Current display price.'),
            'original' => new external_value(PARAM_TEXT, 'Original display price.'),
            'hassale' => new external_value(PARAM_BOOL, 'Whether sale pricing applies.'),
            'saleprice' => new external_value(PARAM_TEXT, 'Sale display price.'),
            'savings' => new external_value(PARAM_TEXT, 'Display savings.'),
        ]);
    }

    /**
     * State structure.
     *
     * @return external_single_structure
     */
    private static function state_structure(): external_single_structure {
        return new external_single_structure([
            'purchased' => new external_value(PARAM_BOOL, 'Whether user purchased this bundle.'),
            'accessallcourses' => new external_value(PARAM_BOOL, 'Whether user has access to all included courses.'),
            'hasownedcourses' => new external_value(PARAM_BOOL, 'Whether user owns any included courses.'),
            'ownedcoursescount' => new external_value(PARAM_INT, 'Owned included course count.'),
            'totalcoursescount' => new external_value(PARAM_INT, 'Total included course count.'),
            'hascourses' => new external_value(PARAM_BOOL, 'Whether bundle includes courses.'),
            'isavailable' => new external_value(PARAM_BOOL, 'Whether bundle can be purchased.'),
            'showfeatured' => new external_value(PARAM_BOOL, 'Whether featured badge should be shown.'),
            'showbestseller' => new external_value(PARAM_BOOL, 'Whether bestseller badge should be shown.'),
            'isloggedin' => new external_value(PARAM_BOOL, 'Whether the visitor is logged in.'),
            'productid' => new external_value(PARAM_INT, 'Product ID for this bundle.'),
            'inwishlist' => new external_value(PARAM_BOOL, 'Whether the current learner has saved this bundle.'),
        ]);
    }

    /**
     * Whether the given learner has saved this bundle.
     *
     * A bundle row IS the product row, so the bundle id is the product id.
     *
     * @param int $productid Product ID.
     * @param int $userid User ID (0 for anonymous visitors).
     * @return bool
     */
    private static function is_in_wishlist(int $productid, int $userid): bool {
        global $DB;

        if ($productid <= 0 || $userid <= 0) {
            return false;
        }

        return $DB->record_exists('local_moderncommerce_wishlist', [
            'userid' => $userid,
            'productid' => $productid,
        ]);
    }

    /**
     * Course structure.
     *
     * @return external_single_structure
     */
    private static function course_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course ID.'),
            'courseid' => new external_value(PARAM_INT, 'Course ID alias.'),
            'courseurl' => new external_value(PARAM_RAW, 'Commerce course details URL.'),
            'courseviewurl' => new external_value(PARAM_RAW, 'Moodle course view URL.'),
            'imageurl' => new external_value(PARAM_RAW, 'Course image URL.'),
            'hasimage' => new external_value(PARAM_BOOL, 'Whether an image URL exists.'),
            'name' => new external_value(PARAM_TEXT, 'Course display name.'),
            'summary' => new external_value(PARAM_RAW, 'Plain course summary.'),
            'categoryname' => new external_value(PARAM_TEXT, 'Course category name.'),
            'duration' => new external_value(PARAM_TEXT, 'Duration label.'),
            'activitycount' => new external_value(PARAM_INT, 'Activity count.'),
            'level' => new external_value(PARAM_TEXT, 'Skill level.'),
            'quizzescount' => new external_value(PARAM_INT, 'Quiz count.'),
            'isfree' => new external_value(PARAM_BOOL, 'Whether course is free.'),
            'isenrolled' => new external_value(PARAM_BOOL, 'Whether user is enrolled.'),
            'hassale' => new external_value(PARAM_BOOL, 'Whether course has sale pricing.'),
            'regularprice' => new external_value(PARAM_TEXT, 'Regular display price.'),
            'saleprice' => new external_value(PARAM_TEXT, 'Sale display price.'),
            'enrolledtext' => new external_value(PARAM_TEXT, 'Enrolled label.'),
        ]);
    }

    /**
     * URL structure.
     *
     * @return external_single_structure
     */
    private static function urls_structure(): external_single_structure {
        return new external_single_structure([
            'addtocart' => new external_value(PARAM_RAW, 'Add to cart URL.'),
            'checkout' => new external_value(PARAM_RAW, 'Checkout URL.'),
            'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
            'launch' => new external_value(PARAM_RAW, 'Course launch URL when already entitled.'),
        ]);
    }

    /**
     * Build a complete error response.
     *
     * @param string $message Error message.
     * @return array
     */
    private static function error_response(string $message): array {
        return [
            'success' => false,
            'message' => $message,
            'bundle' => self::normalise_bundle([]),
            'price' => self::normalise_price([]),
            'state' => [
                'purchased' => false,
                'accessallcourses' => false,
                'hasownedcourses' => false,
                'ownedcoursescount' => 0,
                'totalcoursescount' => 0,
                'hascourses' => false,
                'isavailable' => false,
                'showfeatured' => false,
                'showbestseller' => false,
            ],
            'courses' => [],
            'urls' => [
                'addtocart' => '',
                'checkout' => '',
                'catalog' => (new moodle_url('/local/moderncommerce/index.php'))->out(false),
                'launch' => '',
            ],
        ];
    }

    /**
     * Normalise bundle array.
     *
     * @param array $bundle Bundle data.
     * @return array
     */
    private static function normalise_bundle(array $bundle): array {
        return [
            'id' => (int)($bundle['id'] ?? 0),
            'name' => (string)($bundle['name'] ?? ''),
            'description' => (string)($bundle['description'] ?? ''),
            'shortdescription' => (string)($bundle['shortdescription'] ?? ''),
            'type' => (string)($bundle['type'] ?? 'bundle'),
            'isprogram' => !empty($bundle['isprogram']),
            'coursecount' => (int)($bundle['coursecount'] ?? 0),
            'imageurl' => (string)($bundle['imageurl'] ?? ''),
            'hasimage' => !empty($bundle['hasimage']),
        ];
    }

    /**
     * Normalise price array.
     *
     * @param array $price Price data.
     * @return array
     */
    private static function normalise_price(array $price): array {
        return [
            'current' => (string)($price['current'] ?? ''),
            'original' => (string)($price['original'] ?? ''),
            'hassale' => !empty($price['hassale']),
            'saleprice' => (string)($price['saleprice'] ?? ''),
            'savings' => (string)($price['savings'] ?? ''),
        ];
    }

    /**
     * Normalise course data.
     *
     * @param array $course Course data.
     * @return array
     */
    private static function normalise_course(array $course): array {
        return [
            'id' => (int)($course['id'] ?? 0),
            'courseid' => (int)($course['courseid'] ?? ($course['id'] ?? 0)),
            'courseurl' => (string)($course['courseurl'] ?? ''),
            'courseviewurl' => (string)($course['courseviewurl'] ?? ''),
            'imageurl' => (string)($course['imageurl'] ?? ''),
            'hasimage' => !empty($course['hasimage']),
            'name' => (string)($course['name'] ?? ''),
            'summary' => (string)($course['summary'] ?? ''),
            'categoryname' => (string)($course['categoryname'] ?? ''),
            'duration' => (string)($course['duration'] ?? ''),
            'activitycount' => (int)($course['activitycount'] ?? 0),
            'level' => (string)($course['level'] ?? ''),
            'quizzescount' => (int)($course['quizzescount'] ?? 0),
            'isfree' => !empty($course['isfree']),
            'isenrolled' => !empty($course['isenrolled']),
            'hassale' => !empty($course['hassale']),
            'regularprice' => (string)($course['regularprice'] ?? ''),
            'saleprice' => (string)($course['saleprice'] ?? ''),
            'enrolledtext' => (string)($course['enrolledtext'] ?? ''),
        ];
    }
}
