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
 * External API returning fixed DEMO products for the admin widget gallery.
 *
 * Mirrors {@see get_catalog}'s return shape exactly so the storefront product carousel
 * and catalog React components render perfectly even on a site with no real products.
 * Admin-only: never exposed to buyers.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\catalog;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\pricing_service;

/**
 * Returns a fixed demo catalog dataset for the gallery's product widgets.
 */
class get_demo_catalog extends external_api {
    /**
     * Parameters (mirror get_catalog so the same React components can call this method).
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Ignored in demo mode.', VALUE_DEFAULT, ''),
            'coursetype' => new external_value(PARAM_ALPHANUMEXT, 'Ignored in demo mode.', VALUE_DEFAULT, ''),
            'categoryid' => new external_value(PARAM_INT, 'Ignored in demo mode.', VALUE_DEFAULT, 0),
            'level' => new external_value(PARAM_TEXT, 'Ignored in demo mode.', VALUE_DEFAULT, ''),
            'minprice' => new external_value(PARAM_FLOAT, 'Ignored in demo mode.', VALUE_DEFAULT, 0),
            'maxprice' => new external_value(PARAM_FLOAT, 'Ignored in demo mode.', VALUE_DEFAULT, 0),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Sort key (applied to demo items).', VALUE_DEFAULT, 'popular'),
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Items per page.', VALUE_DEFAULT, 12),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $search Ignored.
     * @param string $coursetype Ignored.
     * @param int $categoryid Ignored.
     * @param string $level Ignored.
     * @param float $minprice Ignored.
     * @param float $maxprice Ignored.
     * @param string $sort Sort key.
     * @param int $page Zero-based page number.
     * @param int $perpage Items per page.
     * @return array
     */
    public static function execute(
        string $search = '',
        string $coursetype = '',
        int $categoryid = 0,
        string $level = '',
        float $minprice = 0,
        float $maxprice = 0,
        string $sort = 'popular',
        int $page = 0,
        int $perpage = 12
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search, 'coursetype' => $coursetype, 'categoryid' => $categoryid,
            'level' => $level, 'minprice' => $minprice, 'maxprice' => $maxprice,
            'sort' => $sort, 'page' => $page, 'perpage' => $perpage,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managestorefront', $context);

        $items = self::demo_items();
        $total = count($items);
        $perpage = min(60, max(1, (int)$params['perpage']));
        $pagenum = max(0, (int)$params['page']);
        $offset = $pagenum * $perpage;
        $pageditems = array_slice($items, $offset, $perpage);
        $totalpages = (int)ceil($total / $perpage);

        return [
            'items' => array_values($pageditems),
            'total' => $total,
            'page' => $pagenum,
            'perpage' => $perpage,
            'totalpages' => max(1, $totalpages),
            'hasmore' => ($offset + $perpage) < $total,
            'sort' => (string)$params['sort'],
            'filters' => [
                'search' => '', 'coursetype' => '', 'categoryid' => 0,
                'level' => '', 'minprice' => 0.0, 'maxprice' => 0.0,
            ],
            'currency' => self::currency_data(),
            'filteroptions' => [
                'categories' => [
                    ['id' => 1, 'name' => 'Business'],
                    ['id' => 2, 'name' => 'Technology'],
                    ['id' => 3, 'name' => 'Design'],
                    ['id' => 4, 'name' => 'Data & AI'],
                ],
                'coursetypes' => [
                    ['value' => 'Course', 'label' => 'Courses'],
                    ['value' => 'Bundle', 'label' => 'Bundles'],
                    ['value' => 'Program', 'label' => 'Programmes'],
                ],
                'levels' => [
                    ['value' => 'Beginner', 'label' => 'Beginner'],
                    ['value' => 'Intermediate', 'label' => 'Intermediate'],
                    ['value' => 'Advanced', 'label' => 'Advanced'],
                ],
                'maxprice' => 199.0,
            ],
            'state' => ['isloggedin' => isloggedin() && !isguestuser()],
            'urls' => [
                'catalog' => (new \moodle_url('/local/moderncommerce/index.php'))->out(false),
                'cart' => (new \moodle_url('/local/moderncommerce/cart.php'))->out(false),
                'login' => (new \moodle_url('/login/index.php'))->out(false),
                'register' => (new \moodle_url('/login/signup.php'))->out(false),
            ],
            'warnings' => [],
        ];
    }

    /**
     * Returns (identical structure to get_catalog).
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::item_structure()),
            'total' => new external_value(PARAM_INT, 'Total filtered items.'),
            'page' => new external_value(PARAM_INT, 'Current zero-based page.'),
            'perpage' => new external_value(PARAM_INT, 'Items per page.'),
            'totalpages' => new external_value(PARAM_INT, 'Total pages.'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether more pages are available.'),
            'sort' => new external_value(PARAM_ALPHANUMEXT, 'Applied sort key.'),
            'filters' => new external_single_structure([
                'search' => new external_value(PARAM_TEXT, 'Applied search.'),
                'coursetype' => new external_value(PARAM_ALPHANUMEXT, 'Applied course type.'),
                'categoryid' => new external_value(PARAM_INT, 'Applied category ID.'),
                'level' => new external_value(PARAM_TEXT, 'Applied level.'),
                'minprice' => new external_value(PARAM_FLOAT, 'Applied minimum price.'),
                'maxprice' => new external_value(PARAM_FLOAT, 'Applied maximum price.'),
            ]),
            'currency' => new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.'),
                'symbol' => new external_value(PARAM_TEXT, 'Currency symbol.'),
                'position' => new external_value(PARAM_ALPHA, 'Symbol position.'),
                'decimals' => new external_value(PARAM_INT, 'Decimal places.'),
            ]),
            'filteroptions' => new external_single_structure([
                'categories' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Category ID.'),
                    'name' => new external_value(PARAM_TEXT, 'Category name.'),
                ])),
                'coursetypes' => new external_multiple_structure(new external_single_structure([
                    'value' => new external_value(PARAM_TEXT, 'Filter value.'),
                    'label' => new external_value(PARAM_TEXT, 'Filter label.'),
                ])),
                'levels' => new external_multiple_structure(new external_single_structure([
                    'value' => new external_value(PARAM_TEXT, 'Filter value.'),
                    'label' => new external_value(PARAM_TEXT, 'Filter label.'),
                ])),
                'maxprice' => new external_value(PARAM_FLOAT, 'Maximum catalog price.'),
            ]),
            'state' => new external_single_structure([
                'isloggedin' => new external_value(PARAM_BOOL, 'Whether the visitor is logged in.'),
            ]),
            'urls' => new external_single_structure([
                'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
                'cart' => new external_value(PARAM_RAW, 'Cart URL.'),
                'login' => new external_value(PARAM_RAW, 'Login URL.'),
                'register' => new external_value(PARAM_RAW, 'Registration URL.'),
            ]),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Single product item structure (identical to get_catalog).
     *
     * @return external_single_structure
     */
    private static function item_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Item ID.'),
            'productid' => new external_value(PARAM_INT, 'Modern Commerce product ID.'),
            'itemtype' => new external_value(PARAM_ALPHANUMEXT, 'Item type.'),
            'title' => new external_value(PARAM_TEXT, 'Display title.'),
            'thumbnail' => new external_value(PARAM_RAW, 'Thumbnail URL.'),
            'alt' => new external_value(PARAM_TEXT, 'Image alt text.'),
            'category' => new external_value(PARAM_TEXT, 'Category name.'),
            'categoryid' => new external_value(PARAM_INT, 'Category ID.'),
            'coursetype' => new external_value(PARAM_TEXT, 'Course type label.'),
            'level' => new external_value(PARAM_TEXT, 'Skill level.'),
            'duration' => new external_value(PARAM_RAW, 'Duration label.'),
            'rating' => new external_value(PARAM_FLOAT, 'Rating.'),
            'reviewcount' => new external_value(PARAM_INT, 'Review count.'),
            'price' => new external_value(PARAM_FLOAT, 'Raw current price.'),
            'originalprice' => new external_value(PARAM_FLOAT, 'Raw original/compare-at price.'),
            'displayprice' => new external_value(PARAM_TEXT, 'Formatted price text.'),
            'displayoriginalprice' => new external_value(PARAM_TEXT, 'Formatted original price text.'),
            'pricehtml' => new external_value(PARAM_RAW, 'Formatted price HTML.'),
            'hasoriginalprice' => new external_value(PARAM_BOOL, 'Whether original price exists.'),
            'isbundle' => new external_value(PARAM_BOOL, 'Whether item is a bundle.'),
            'isprogram' => new external_value(PARAM_BOOL, 'Whether item is a program.'),
            'bestseller' => new external_value(PARAM_BOOL, 'Whether item is marked bestseller.'),
            'hasaccess' => new external_value(PARAM_BOOL, 'Whether the current learner already has access.'),
            'inwishlist' => new external_value(PARAM_BOOL, 'Whether the current learner has saved this item.'),
            'accessurl' => new external_value(PARAM_RAW, 'Learner dashboard URL for owned items.'),
            'detailsurl' => new external_value(PARAM_RAW, 'Details page URL.'),
            'timecreated' => new external_value(PARAM_INT, 'Creation timestamp used for sorting.'),
        ]);
    }

    /**
     * The fixed set of demo products.
     *
     * @return array
     */
    private static function demo_items(): array {
        $now = time();
        // Free, hotlink-friendly Unsplash photos (Unsplash License), topical per course.
        $defs = [
            ['Complete Web Development Bootcamp', 'Technology', 3, 'Course', 'Beginner', '52 hours', 4.8, 1290,
                89.0, 149.0, true, false, false, '1498050108023-c5249f4df085'],
            ['UX & Product Design Masterclass', 'Design', 3, 'Course', 'Intermediate', '38 hours', 4.7, 842,
                79.0, 0.0, false, false, false, '1561070791-2526d30994b5'],
            ['Data Science Career Track', 'Data & AI', 4, 'Program', 'Intermediate', '12 weeks', 4.9, 2103,
                199.0, 299.0, true, false, true, '1551288049-bebda4e38f71'],
            ['Digital Marketing Pro Bundle', 'Business', 1, 'Bundle', 'All Levels', '6 courses', 4.6, 564,
                129.0, 220.0, false, true, false, '1557838923-2985c318be48'],
            ['Python for Everybody', 'Technology', 3, 'Course', 'Beginner', '24 hours', 4.8, 3490,
                49.0, 0.0, true, false, false, '1526379095098-d400fd0bf935'],
            ['Leadership & Management Essentials', 'Business', 1, 'Course', 'Advanced', '18 hours', 4.5, 412,
                69.0, 99.0, false, false, false, '1542744173-8e7e53415bb0'],
            ['Cloud Architecture with AWS', 'Technology', 3, 'Course', 'Advanced', '30 hours', 4.7, 738,
                119.0, 0.0, false, false, false, '1451187580459-43490279c0fa'],
            ['Graphic Design Foundations', 'Design', 3, 'Course', 'Beginner', '16 hours', 4.6, 980,
                39.0, 59.0, false, false, false, '1626785774573-4b799315345d'],
        ];

        $items = [];
        $id = 9001;
        foreach ($defs as $i => $d) {
            [$title, $category, $catid, $type, $level, $duration, $rating, $reviews,
                $price, $original, $bestseller, $isbundle, $isprogram, $photo] = $d;
            $hasoriginal = $original > 0;
            $items[] = [
                'id' => $id,
                'productid' => $id,
                'itemtype' => $type,
                'title' => $title,
                'thumbnail' => 'https://images.unsplash.com/photo-' . $photo
                    . '?auto=format&fit=crop&q=80&w=640&h=360',
                'alt' => $title,
                'category' => $category,
                'categoryid' => $catid,
                'coursetype' => $type,
                'level' => $level,
                'duration' => $duration,
                'rating' => (float)$rating,
                'reviewcount' => (int)$reviews,
                'price' => (float)$price,
                'originalprice' => (float)$original,
                'displayprice' => pricing_service::format_price((float)$price),
                'displayoriginalprice' => $hasoriginal ? pricing_service::format_price((float)$original) : '',
                'pricehtml' => pricing_service::format_price((float)$price),
                'hasoriginalprice' => $hasoriginal,
                'isbundle' => (bool)$isbundle,
                'isprogram' => (bool)$isprogram,
                'bestseller' => (bool)$bestseller,
                'hasaccess' => false,
                'inwishlist' => false,
                'accessurl' => '',
                'detailsurl' => '#',
                'timecreated' => $now - ($i * DAYSECS),
            ];
            $id++;
        }

        return $items;
    }

    /**
     * Site currency descriptor (single source of truth: the pricing service).
     *
     * @return array
     */
    private static function currency_data(): array {
        $currency = pricing_service::get_currency_config();
        return [
            'code' => $currency->currency,
            'symbol' => $currency->symbol,
            'position' => $currency->position,
            'decimals' => (int)$currency->decimals,
        ];
    }
}
