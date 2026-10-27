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
 * External API for the public catalog page.
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
use local_moderncommerce\output\catalog_page;
use local_moderncommerce\services\access_service;
use local_moderncommerce\services\pricing_service;
use local_moderncommerce\services\review_service;

/**
 * Returns the deduplicated public catalog dataset for React.
 */
class get_catalog extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search product title.', VALUE_DEFAULT, ''),
            'coursetype' => new external_value(PARAM_ALPHANUMEXT, 'Course, Bundle, Program, or empty.', VALUE_DEFAULT, ''),
            'categoryid' => new external_value(PARAM_INT, 'Modern Commerce product category ID.', VALUE_DEFAULT, 0),
            'level' => new external_value(PARAM_TEXT, 'Skill level filter.', VALUE_DEFAULT, ''),
            'minprice' => new external_value(PARAM_FLOAT, 'Minimum price.', VALUE_DEFAULT, 0),
            'maxprice' => new external_value(PARAM_FLOAT, 'Maximum price. Zero means no maximum.', VALUE_DEFAULT, 0),
            'sort' => new external_value(
                PARAM_ALPHANUMEXT,
                'Sort key: popular, newest, pricelow, pricehigh, title.',
                VALUE_DEFAULT,
                'popular'
            ),
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Items per page.', VALUE_DEFAULT, 12),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $search Search product title.
     * @param string $coursetype Course type filter.
     * @param int $categoryid Modern Commerce product category filter.
     * @param string $level Skill level filter.
     * @param float $minprice Minimum price.
     * @param float $maxprice Maximum price.
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
        global $CFG, $OUTPUT, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'search' => $search,
            'coursetype' => $coursetype,
            'categoryid' => $categoryid,
            'level' => $level,
            'minprice' => $minprice,
            'maxprice' => $maxprice,
            'sort' => $sort,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_system::instance();
        self::validate_public_catalog_context($context);

        require_once($CFG->dirroot . '/local/moderncommerce/lib.php');

        $params = self::normalise_parameters($params);
        $catalogpage = new catalog_page();
        $catalogdata = $catalogpage->export_for_template($OUTPUT);

        $items = array_map([self::class, 'normalise_item'], $catalogdata['courses'] ?? []);
        $items = self::apply_review_summaries($items);
        $items = self::filter_items($items, $params);
        $items = self::sort_items($items, $params['sort']);

        $total = count($items);
        $offset = $params['page'] * $params['perpage'];
        $pageditems = array_slice($items, $offset, $params['perpage']);
        $userid = isloggedin() && !isguestuser() ? (int)$USER->id : 0;
        $pageditems = array_map(static function (array $item) use ($userid): array {
            return self::add_access_state($item, $userid);
        }, $pageditems);
        $pageditems = self::add_wishlist_state($pageditems, $userid);
        $totalpages = $params['perpage'] > 0 ? (int)ceil($total / $params['perpage']) : 1;

        return [
            'items' => array_values($pageditems),
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'totalpages' => max(1, $totalpages),
            'hasmore' => ($offset + $params['perpage']) < $total,
            'sort' => $params['sort'],
            'filters' => [
                'search' => $params['search'],
                'coursetype' => $params['coursetype'],
                'categoryid' => $params['categoryid'],
                'level' => $params['level'],
                'minprice' => $params['minprice'],
                'maxprice' => $params['maxprice'],
            ],
            'currency' => self::currency_data(),
            'filteroptions' => [
                'categories' => self::normalise_filter_labels(array_values($catalogdata['categories'] ?? []), 'name'),
                'coursetypes' => self::normalise_filter_labels(array_values($catalogdata['coursetypes'] ?? []), 'label'),
                'levels' => self::normalise_filter_labels(array_values($catalogdata['levels'] ?? []), 'label'),
                'maxprice' => (float)($catalogdata['maxprice'] ?? 0),
            ],
            'state' => [
                'isloggedin' => isloggedin() && !isguestuser(),
            ],
            'urls' => [
                'catalog' => (new \moodle_url('/local/moderncommerce/index.php'))->out(false),
                'cart' => (new \moodle_url('/local/moderncommerce/cart.php'))->out(false),
                'login' => (new \moodle_url('/login/index.php', [
                    'wantsurl' => (new \moodle_url('/local/moderncommerce/index.php'))->out(false),
                ]))->out(false),
                'register' => (is_enabled_auth('ccp')
                    ? new \moodle_url('/auth/ccp/signup.php')
                    : new \moodle_url('/login/signup.php'))->out(false),
            ],
            'warnings' => [],
        ];
    }

    /**
     * Returns.
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
            'filters' => self::filters_structure(),
            'currency' => self::currency_structure(),
            'filteroptions' => self::filter_options_structure(),
            'state' => self::state_structure(),
            'urls' => self::url_structure(),
            'warnings' => new external_warnings(),
        ]);
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
     * Item structure.
     *
     * @return external_single_structure
     */
    private static function item_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Item ID. Course ID for course cards, product ID for bundles/programs.'),
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
     * Filters structure.
     *
     * @return external_single_structure
     */
    private static function filters_structure(): external_single_structure {
        return new external_single_structure([
            'search' => new external_value(PARAM_TEXT, 'Applied search.'),
            'coursetype' => new external_value(PARAM_ALPHANUMEXT, 'Applied course type.'),
            'categoryid' => new external_value(PARAM_INT, 'Applied category ID.'),
            'level' => new external_value(PARAM_TEXT, 'Applied level.'),
            'minprice' => new external_value(PARAM_FLOAT, 'Applied minimum price.'),
            'maxprice' => new external_value(PARAM_FLOAT, 'Applied maximum price.'),
        ]);
    }

    /**
     * Currency structure.
     *
     * @return external_single_structure
     */
    private static function currency_structure(): external_single_structure {
        return new external_single_structure([
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.'),
            'symbol' => new external_value(PARAM_TEXT, 'Currency symbol.'),
            'position' => new external_value(PARAM_ALPHA, 'Symbol position.'),
            'decimals' => new external_value(PARAM_INT, 'Decimal places.'),
        ]);
    }

    /**
     * Filter options structure.
     *
     * @return external_single_structure
     */
    private static function filter_options_structure(): external_single_structure {
        return new external_single_structure([
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
        ]);
    }

    /**
     * State structure.
     *
     * @return external_single_structure
     */
    private static function state_structure(): external_single_structure {
        return new external_single_structure([
            'isloggedin' => new external_value(PARAM_BOOL, 'Whether the visitor is logged in.'),
        ]);
    }

    /**
     * URL structure.
     *
     * @return external_single_structure
     */
    private static function url_structure(): external_single_structure {
        return new external_single_structure([
            'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
            'cart' => new external_value(PARAM_RAW, 'Cart URL.'),
            'login' => new external_value(PARAM_RAW, 'Login URL.'),
            'register' => new external_value(PARAM_RAW, 'Registration URL.'),
        ]);
    }

    /**
     * Normalise request parameters.
     *
     * @param array $params Raw parameters.
     * @return array
     */
    private static function normalise_parameters(array $params): array {
        $params['search'] = trim((string)$params['search']);
        $params['categoryid'] = max(0, (int)$params['categoryid']);
        $params['minprice'] = max(0, (float)$params['minprice']);
        $params['maxprice'] = max(0, (float)$params['maxprice']);
        $params['page'] = max(0, (int)$params['page']);
        $params['perpage'] = min(60, max(1, (int)$params['perpage']));

        $allowedtypes = ['', 'Course', 'Bundle', 'Program'];
        if (!in_array($params['coursetype'], $allowedtypes, true)) {
            $params['coursetype'] = '';
        }

        $allowedlevels = ['', 'Beginner', 'Intermediate', 'Advanced', 'All Levels'];
        if (!in_array($params['level'], $allowedlevels, true)) {
            $params['level'] = '';
        }

        $allowedsorts = ['popular', 'newest', 'pricelow', 'pricehigh', 'title'];
        if (!in_array($params['sort'], $allowedsorts, true)) {
            $params['sort'] = 'popular';
        }

        return $params;
    }

    /**
     * Convert template item into API item.
     *
     * @param array $item Template item.
     * @return array API item.
     */
    private static function normalise_item(array $item): array {
        $pricehtml = (string)($item['courseprice'] ?? '');

        return [
            'id' => (int)($item['id'] ?? 0),
            'productid' => (int)($item['productid'] ?? $item['id'] ?? 0),
            'itemtype' => (string)($item['itemtype'] ?? ''),
            'title' => self::plain_text((string)($item['title'] ?? '')),
            'thumbnail' => (string)($item['thumb'] ?? ''),
            'alt' => self::plain_text((string)($item['alt'] ?? '')),
            'category' => self::plain_text((string)($item['category'] ?? '')),
            'categoryid' => (int)($item['categoryid'] ?? 0),
            'coursetype' => self::plain_text((string)($item['coursetype'] ?? '')),
            'level' => self::plain_text((string)($item['level'] ?? '')),
            'duration' => self::plain_text((string)($item['courseduration'] ?? '')),
            'rating' => (float)($item['rating'] ?? 0),
            'reviewcount' => (int)($item['reviewcount'] ?? 0),
            'price' => (float)($item['pricevalue'] ?? 0),
            'originalprice' => (float)($item['originalprice'] ?? 0),
            'displayprice' => self::plain_text($pricehtml),
            'displayoriginalprice' => !empty($item['hasoriginalprice'])
                ? pricing_service::format_price((float)($item['originalprice'] ?? 0))
                : '',
            'pricehtml' => $pricehtml,
            'hasoriginalprice' => !empty($item['hasoriginalprice']),
            'isbundle' => !empty($item['isbundle']),
            'isprogram' => !empty($item['isprogram']),
            'bestseller' => !empty($item['bestseller']),
            'hasaccess' => false,
            'inwishlist' => false,
            'accessurl' => '',
            'detailsurl' => (string)($item['detailsurl'] ?? ''),
            'timecreated' => (int)($item['timecreated'] ?? 0),
        ];
    }

    /**
     * Convert a display string prepared for Moodle templates back to plain text for JSON APIs.
     *
     * React renders these values as text nodes, so decoding entities fixes visible labels while
     * preserving normal frontend escaping.
     *
     * @param string $value Display value.
     * @return string Plain text value.
     */
    private static function plain_text(string $value): string {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Normalise one text label field across filter option rows.
     *
     * @param array $options Filter options.
     * @param string $field Field to normalise.
     * @return array Normalised filter options.
     */
    private static function normalise_filter_labels(array $options, string $field): array {
        foreach ($options as $index => $option) {
            if (isset($option[$field])) {
                $options[$index][$field] = self::plain_text((string)$option[$field]);
            }
        }

        return $options;
    }

    /**
     * Apply real core review summaries to course cards.
     *
     * @param array $items Catalog items.
     * @return array Catalog items.
     */
    private static function apply_review_summaries(array $items): array {
        $courseids = [];
        foreach ($items as $item) {
            if (strtolower((string)($item['itemtype'] ?? '')) === 'course' && (int)($item['id'] ?? 0) > 0) {
                $courseids[] = (int)$item['id'];
            }
        }

        $summaries = review_service::get_course_summaries($courseids);
        if (empty($summaries)) {
            return $items;
        }

        return array_map(static function (array $item) use ($summaries): array {
            $courseid = (int)($item['id'] ?? 0);
            if (strtolower((string)($item['itemtype'] ?? '')) === 'course' && isset($summaries[$courseid])) {
                $item['rating'] = (float)$summaries[$courseid]['avgrating'];
                $item['reviewcount'] = (int)$summaries[$courseid]['reviewcount'];
            }

            return $item;
        }, $items);
    }

    /**
     * Add current learner access state to a catalog item.
     *
     * @param array $item Catalog item.
     * @param int $userid Current user ID, or zero for guests.
     * @return array Catalog item with access state.
     */
    private static function add_access_state(array $item, int $userid): array {
        $hasaccess = false;
        $itemid = (int)($item['id'] ?? 0);
        $itemtype = strtolower((string)($item['itemtype'] ?? ''));

        if ($userid > 0 && $itemid > 0) {
            if ($itemtype === 'course') {
                $hasaccess = access_service::user_has_course_access($userid, $itemid);
            } else if (in_array($itemtype, ['bundle', 'program'], true)) {
                $hasaccess = access_service::user_has_product_purchase_access($userid, $itemid);
            }
        }

        $item['hasaccess'] = $hasaccess;
        $item['accessurl'] = $hasaccess ? self::access_url_for_item($item, $itemtype) : '';

        return $item;
    }

    /**
     * Add current learner wishlist state to catalog items.
     *
     * @param array $items Catalog items.
     * @param int $userid Current user ID, or zero for guests.
     * @return array Catalog items with wishlist state.
     */
    private static function add_wishlist_state(array $items, int $userid): array {
        global $DB;

        if (empty($items)) {
            return $items;
        }

        foreach ($items as $index => $item) {
            $items[$index]['inwishlist'] = false;
        }

        if ($userid <= 0) {
            return $items;
        }

        $productids = array_values(array_unique(array_filter(array_map(static function (array $item): int {
            return (int)($item['productid'] ?? 0);
        }, $items))));

        if (empty($productids)) {
            return $items;
        }

        [$insql, $params] = $DB->get_in_or_equal($productids, SQL_PARAMS_NAMED, 'productid');
        $params['userid'] = $userid;
        $savedids = $DB->get_fieldset_sql(
            "SELECT productid
               FROM {local_moderncommerce_wishlist}
              WHERE userid = :userid
                AND productid {$insql}",
            $params
        );
        $savedmap = array_fill_keys(array_map('intval', $savedids), true);

        foreach ($items as $index => $item) {
            $productid = (int)($item['productid'] ?? 0);
            $items[$index]['inwishlist'] = isset($savedmap[$productid]);
        }

        return $items;
    }

    /**
     * Resolve the most useful destination for an already-owned catalog item.
     *
     * @param array $item Catalog item.
     * @param string $itemtype Normalised item type.
     * @return string Destination URL.
     */
    private static function access_url_for_item(array $item, string $itemtype): string {
        $itemid = (int)($item['id'] ?? 0);

        if ($itemtype === 'course' && $itemid > 0) {
            return access_service::course_view_url($itemid)->out(false);
        }

        if (in_array($itemtype, ['bundle', 'program'], true) && $itemid > 0) {
            return access_service::learner_product_access_url($itemid, $itemtype)->out(false);
        }

        return access_service::learner_dashboard_url()->out(false) . '#/access';
    }

    /**
     * Filter items.
     *
     * @param array $items Catalog items.
     * @param array $params Normalised params.
     * @return array Filtered items.
     */
    private static function filter_items(array $items, array $params): array {
        $search = strtolower($params['search']);

        return array_values(array_filter($items, static function (array $item) use ($params, $search): bool {
            if ($search !== '' && strpos(strtolower($item['title']), $search) === false) {
                return false;
            }
            if ($params['coursetype'] !== '' && $item['coursetype'] !== $params['coursetype']) {
                return false;
            }
            // Product categories are a flat list, so an exact match is the whole story: there are
            // no sub-categories to descend into.
            if ($params['categoryid'] > 0 && (int)$item['categoryid'] !== $params['categoryid']) {
                return false;
            }
            if ($params['level'] !== '' && $item['level'] !== $params['level']) {
                return false;
            }
            if ($params['minprice'] > 0 && (float)$item['price'] < $params['minprice']) {
                return false;
            }
            if ($params['maxprice'] > 0 && (float)$item['price'] > $params['maxprice']) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Sort items.
     *
     * @param array $items Catalog items.
     * @param string $sort Sort key.
     * @return array Sorted items.
     */
    private static function sort_items(array $items, string $sort): array {
        usort($items, static function (array $a, array $b) use ($sort): int {
            switch ($sort) {
                case 'newest':
                    return ($b['timecreated'] <=> $a['timecreated']) ?: strcmp($a['title'], $b['title']);
                case 'pricelow':
                    return ($a['price'] <=> $b['price']) ?: strcmp($a['title'], $b['title']);
                case 'pricehigh':
                    return ($b['price'] <=> $a['price']) ?: strcmp($a['title'], $b['title']);
                case 'title':
                    return strcmp($a['title'], $b['title']);
                case 'popular':
                default:
                    return ($b['rating'] <=> $a['rating']) ?: ($b['timecreated'] <=> $a['timecreated']);
            }
        });

        return $items;
    }

    /**
     * Currency data.
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
