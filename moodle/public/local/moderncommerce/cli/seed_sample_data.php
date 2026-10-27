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
 * Seeds a small Modern Commerce demo catalog and optional transaction data.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Reusable CLI seed include.
if (!defined('CLI_SCRIPT')) {
    define('CLI_SCRIPT', true);
}
// phpcs:enable moodle.Files.MoodleInternal.MoodleInternalGlobalState

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_moderncommerce\services\pricing_service;

if (local_moderncommerce_seed_sample_data_cli_should_run()) {
    local_moderncommerce_seed_sample_data_cli_main();
}

/**
 * Whether this file is the active CLI entrypoint.
 *
 * @return bool
 */
function local_moderncommerce_seed_sample_data_cli_should_run(): bool {
    return !defined('LOCAL_MODERNCOMMERCE_DEMO_DATA_INCLUDE');
}

/**
 * Seed sample data from the CLI entrypoint.
 */
function local_moderncommerce_seed_sample_data_cli_main(): void {
    [$options, $unrecognized] = cli_get_params(
        [
            'help' => false,
            'reset' => false,
            'with-order' => false,
            'userid' => 0,
            'categories' => 12,
            'courses' => 25,
            'products' => 0,
            'orders' => 120,
            'coupons' => 12,
            'keys' => 24,
            'reviews' => 4,
        ],
        [
            'h' => 'help',
            'r' => 'reset',
        ]
    );

    if (!empty($unrecognized)) {
        $unrecognized = implode(PHP_EOL . '  ', $unrecognized);
        cli_error("Unknown options:\n  {$unrecognized}");
    }

    if (!empty($options['help'])) {
        echo "Seed Modern Commerce sample data.\n\n";
        echo "Options:\n";
        echo "  --reset         Delete existing Modern Commerce demo rows before seeding.\n";
        echo "  --with-order    Also create completed demo order/payment/invoice rows.\n";
        echo "  --userid=N      User ID for the demo order/wishlist. Defaults to the first site admin.\n";
        echo "  --categories=N  Number of demo Moodle course categories to create. Default: 12.\n";
        echo "  --courses=N     Number of demo Moodle courses to create across those categories. Default: 25.\n";
        echo "  --products=N    Number of demo commerce products. Default: 0 (one product per demo course).\n";
        echo "  --orders=N      Number of demo orders to create when --with-order is used. Default: 120.\n";
        echo "  --coupons=N     Number of demo coupons to create. Default: 12.\n";
        echo "  --keys=N        Number of demo enrollment keys to create. Default: 24.\n";
        echo "  --reviews=N     Number of demo course reviews per course. Default: 4; use 0 to skip.\n";
        echo "  -h, --help      Show this help.\n\n";
        echo "Example:\n";
        echo "  php public/local/moderncommerce/cli/seed_sample_data.php --reset --with-order\n";
        exit(0);
    }

    if (!empty($options['reset'])) {
        local_moderncommerce_seed_delete_sample_data();
    }

    $result = local_moderncommerce_seed_sample_data(
        (bool)$options['with-order'],
        (int)$options['userid'],
        max(1, (int)$options['categories']),
        max(1, (int)$options['courses']),
        max(0, (int)$options['products']),
        max(0, (int)$options['orders']),
        max(1, (int)$options['coupons']),
        max(1, (int)$options['keys']),
        max(0, (int)$options['reviews'])
    );

    local_moderncommerce_seed_sample_data_cli_print_summary($result, !empty($options['with-order']));
}

/**
 * Print a seed summary.
 *
 * @param array $result Seed result.
 * @param bool $withorder Whether order rows were requested.
 */
function local_moderncommerce_seed_sample_data_cli_print_summary(array $result, bool $withorder): void {
    cli_heading('Modern Commerce sample data seeded');
    echo 'Moodle categories: ' . $result['moodlecategories'] . PHP_EOL;
    echo 'Moodle courses: ' . $result['moodlecourses'] . PHP_EOL;
    echo 'Categories widgets configured: ' . $result['categorieswidgets'] . PHP_EOL;
    echo 'Products: ' . $result['products'] . PHP_EOL;
    echo 'Bundle products: ' . $result['bundleproducts'] . PHP_EOL;
    echo 'Commerce categories: ' . $result['categories'] . PHP_EOL;
    echo 'Attributes: ' . $result['attributes'] . PHP_EOL;
    echo 'Coupons: ' . $result['coupons'] . PHP_EOL;
    echo 'Enrollment keys: ' . $result['enrollkeys'] . PHP_EOL;
    echo 'Course reviews: ' . $result['reviews'] . PHP_EOL;
    echo 'Review reactions: ' . $result['reviewreactions'] . PHP_EOL;
    if ($withorder) {
        echo 'Demo orders: ' . $result['orders'] . PHP_EOL;
    }
}

/**
 * Seed sample data.
 *
 * @param bool $withorder Whether to create order/payment/entitlement rows.
 * @param int $userid User ID for user-scoped records.
 * @param int $categorycount Number of demo Moodle course categories to create.
 * @param int $coursecount Number of demo Moodle courses to create.
 * @param int $producttarget Number of products to seed (0 = one per demo course).
 * @param int $ordertarget Number of orders to seed.
 * @param int $coupontarget Number of coupons to seed.
 * @param int $keytarget Number of enrollment keys to seed.
 * @param int $reviewtarget Number of reviews to seed per demo course.
 * @return array Summary.
 */
function local_moderncommerce_seed_sample_data(
    bool $withorder,
    int $userid,
    int $categorycount,
    int $coursecount,
    int $producttarget,
    int $ordertarget,
    int $coupontarget,
    int $keytarget,
    int $reviewtarget
): array {
    global $DB, $CFG;

    $now = time();
    $currency = pricing_service::get_currency_config();
    $userid = local_moderncommerce_seed_resolve_userid($userid);

    // Create the demo Moodle course categories + courses the catalog and category widget draw from.
    $catalog = local_moderncommerce_seed_moodle_catalog($categorycount, $coursecount, $now);
    $courses = $catalog['courses'];
    if (empty($courses)) {
        cli_error('Failed to create demo Moodle courses.');
    }

    // Default: one commerce product per demo course.
    if ($producttarget <= 0) {
        $producttarget = count($courses);
    }

    $categoryids = local_moderncommerce_seed_categories($now);
    $attributeids = local_moderncommerce_seed_attributes($now);

    $products = [];
    $prices = [0.000000, 29.000000, 49.000000, 79.000000, 99.000000, 129.000000, 149.000000, 199.000000, 249.000000];
    $levels = ['Beginner', 'Intermediate', 'Advanced', 'Professional'];
    $productnames = [
        'Essentials',
        'Accelerator',
        'Masterclass',
        'Workshop',
        'Bootcamp',
        'Certification Prep',
        'Applied Lab',
        'Leadership Track',
        'Advanced Practice',
        'Implementation Guide',
        'Executive Briefing',
        'Capstone',
    ];

    for ($position = 0; $position < $producttarget; $position++) {
        $course = $courses[$position % count($courses)];
        $variant = intdiv($position, count($courses)) + 1;
        $amount = $prices[$position % count($prices)];
        $level = $levels[$position % count($levels)];
        $product = local_moderncommerce_seed_course_product(
            $course,
            $amount,
            $level,
            $productnames[$position % count($productnames)],
            $variant,
            $position,
            $categoryids['courses'],
            $attributeids,
            $now
        );
        $products[] = $product;
    }

    $bundleids = [];
    $bundlecount = max(1, min(12, (int)floor(count($products) / 6)));
    for ($i = 1; $i <= $bundlecount && count($products) >= 2; $i++) {
        $bundleids[] = local_moderncommerce_seed_bundle_product($products, $categoryids['bundles'], $attributeids, $now, $i);
    }

    $couponids = local_moderncommerce_seed_coupons($coupontarget, $now);
    $enrollkeyids = local_moderncommerce_seed_enrollkeys($products, $currency->currency, $keytarget, $now);

    foreach (array_slice($bundleids, 0, 5) as $bundleid) {
        local_moderncommerce_seed_wishlist($userid, $bundleid, $now);
    }

    // Pre-configure the storefront Categories widget with the demo PRODUCT categories. The tiles
    // resolve Modern Commerce product categories, so seeding Moodle course-category ids here would
    // leave the widget unable to resolve a single tile.
    $categorieswidgets = local_moderncommerce_seed_categories_widget([
        ['id' => $categoryids['courses'], 'icon' => 'mortarboard'],
        ['id' => $categoryids['bundles'], 'icon' => 'box-seam'],
    ]);
    $reviews = local_moderncommerce_seed_course_reviews($courses, $userid, $reviewtarget, $now);

    $orders = 0;
    if ($withorder) {
        $orders = local_moderncommerce_seed_orders($userid, $products, $currency, $ordertarget, $now);
    }

    local_moderncommerce_seed_audit_log([
        'products' => count($products),
        'bundleids' => $bundleids,
        'couponids' => $couponids,
        'enrollkeyids' => $enrollkeyids,
        'reviews' => $reviews,
        'orders' => $orders,
    ], $now);

    return [
        'moodlecategories' => $categorycount,
        'moodlecourses' => count($courses),
        'categorieswidgets' => $categorieswidgets,
        'products' => count($products) + count($bundleids),
        'bundleproducts' => count($bundleids),
        'categories' => count($categoryids),
        'attributes' => count($attributeids),
        'coupons' => count($couponids),
        'enrollkeys' => count($enrollkeyids),
        'reviews' => $reviews['reviews'],
        'reviewreactions' => $reviews['reactions'],
        'orders' => $orders ?: 'not requested',
    ];
}

/**
 * Delete previously seeded sample rows.
 */
function local_moderncommerce_seed_delete_sample_data(): void {
    global $DB, $CFG;

    $productids = $DB->get_fieldset_select(
        'local_moderncommerce_products',
        'id',
        $DB->sql_like('sku', ':sku', false),
        ['sku' => 'MC-DEMO-%']
    );
    $orderids = $DB->get_fieldset_select(
        'local_moderncommerce_orders',
        'id',
        $DB->sql_like('ordernumber', ':ordernumber', false),
        ['ordernumber' => 'MC-DEMO-%']
    );
    $couponids = $DB->get_fieldset_select(
        'local_moderncommerce_coupons',
        'id',
        $DB->sql_like('code', ':code', false),
        ['code' => 'MODERN%']
    );
    $enrollkeyids = $DB->get_fieldset_select(
        'local_moderncommerce_enrollkeys',
        'id',
        $DB->sql_like('keycode', ':keycode', false),
        ['keycode' => 'MODERN-DEMO-%']
    );
    $categoryids = $DB->get_fieldset_select(
        'local_moderncommerce_product_categories',
        'id',
        $DB->sql_like('slug', ':slug', false),
        ['slug' => 'moderncommerce-demo%']
    );
    $attributeids = $DB->get_fieldset_select(
        'local_moderncommerce_product_attributes',
        'id',
        $DB->sql_like('code', ':code', false),
        ['code' => 'demo_%']
    );
    $entitlementids = $DB->get_fieldset_select(
        'local_moderncommerce_entitlements',
        'id',
        $DB->sql_like('sourcekey', ':sourcekey', false),
        ['sourcekey' => 'sample:%']
    );
    $democourseids = $DB->get_fieldset_select(
        'course',
        'id',
        $DB->sql_like('idnumber', ':idn', false),
        ['idn' => 'MCDEMO-COURSE-%']
    );
    $reviewids = local_moderncommerce_seed_get_ids_by_parent(
        'local_moderncommerce_reviews',
        'id',
        'courseid',
        $democourseids
    );
    $orderitemids = local_moderncommerce_seed_get_ids_by_parent(
        'local_moderncommerce_order_items',
        'id',
        'orderid',
        $orderids
    );
    $invoiceids = local_moderncommerce_seed_get_ids_by_parent(
        'local_moderncommerce_invoices',
        'id',
        'orderid',
        $orderids
    );
    $fulfillmentids = local_moderncommerce_seed_get_ids_by_parent(
        'local_moderncommerce_fulfillments',
        'id',
        'orderid',
        $orderids
    );
    $refundids = local_moderncommerce_seed_get_ids_by_parent(
        'local_moderncommerce_refunds',
        'id',
        'orderid',
        $orderids
    );
    $reportdates = local_moderncommerce_seed_get_report_dates($orderids);

    $disablefk = in_array($CFG->dbtype, ['mariadb', 'mysqli'], true);
    if ($disablefk) {
        $DB->execute('SET FOREIGN_KEY_CHECKS=0');
    }

    try {
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_review_rxn', 'reviewid', $reviewids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_reviews', 'id', $reviewids);

        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_entitlement_events', 'entitlementid', $entitlementids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_entitlements', 'id', $entitlementids);

        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_report_products', 'productid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_wishlist', 'productid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_attribute_values', 'productid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_category_map', 'productid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_relations', 'parentproductid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_relations', 'childproductid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_courses', 'productid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_prices', 'productid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_inventory', 'productid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_tags', 'productid', $productids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_products', 'id', $productids);

        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_category_map', 'categoryid', $categoryids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_categories', 'id', $categoryids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_attribute_values', 'attributeid', $attributeids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_product_attributes', 'id', $attributeids);

        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_coupon_targets', 'couponid', $couponids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_coupon_usage', 'couponid', $couponids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_coupon_usage', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_coupons', 'id', $couponids);

        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_enrollkey_targets', 'enrollkeyid', $enrollkeyids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_key_usage', 'enrollkeyid', $enrollkeyids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_key_usage', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_enrollkeys', 'id', $enrollkeyids);

        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_report_daily', 'reportdate', $reportdates);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_report_gateways', 'reportdate', $reportdates);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_order_operational', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_invoice_items', 'invoiceid', $invoiceids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_fulfillment_items', 'fulfillmentid', $fulfillmentids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_refund_items', 'refundid', $refundids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_refunds', 'id', $refundids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_inventory_reservations', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_inventory_reservations', 'orderitemid', $orderitemids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_order_items', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_order_addresses', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_order_adjustments', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_order_status_history', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_payment_attempts', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_payment_events', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_payment_log', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_invoices', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_fulfillments', 'orderid', $orderids);
        local_moderncommerce_seed_delete_by_ids('local_moderncommerce_orders', 'id', $orderids);

        $DB->delete_records_select(
            'local_moderncommerce_audit_log',
            'source = :source AND entitytype = :entitytype',
            ['source' => 'cli', 'entitytype' => 'sample_seed']
        );
    } finally {
        if ($disablefk) {
            $DB->execute('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    // Remove the demo Moodle courses and categories created by the seeder.
    require_once($CFG->dirroot . '/course/lib.php');
    foreach ($democourseids as $democourseid) {
        delete_course((int)$democourseid, false);
    }
    $democats = $DB->get_records_select(
        'course_categories',
        $DB->sql_like('idnumber', ':idn', false),
        ['idn' => 'MCDEMO-CAT-%'],
        'depth DESC, id DESC',
        'id'
    );
    foreach ($democats as $democat) {
        $category = core_course_category::get((int)$democat->id, IGNORE_MISSING);
        if ($category) {
            $category->delete_full(false);
        }
    }

    echo 'Deleted existing Modern Commerce demo rows.' . PHP_EOL;
}

/**
 * Delete records by IDs.
 *
 * @param string $table Table name.
 * @param string $field Field name.
 * @param array $ids IDs.
 */
function local_moderncommerce_seed_delete_by_ids(string $table, string $field, array $ids): void {
    global $DB;

    if (empty($ids)) {
        return;
    }

    [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'seed');
    $DB->delete_records_select($table, "{$field} {$insql}", $params);
}

/**
 * Get child IDs for a parent ID list.
 *
 * @param string $table Table name.
 * @param string $idfield ID field to return.
 * @param string $parentfield Parent field.
 * @param array $parentids Parent IDs.
 * @return array Child IDs.
 */
function local_moderncommerce_seed_get_ids_by_parent(
    string $table,
    string $idfield,
    string $parentfield,
    array $parentids
): array {
    global $DB;

    if (empty($parentids)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal($parentids, SQL_PARAMS_NAMED, 'parent');

    return $DB->get_fieldset_select($table, $idfield, "{$parentfield} {$insql}", $params);
}

/**
 * Get report dates touched by demo orders.
 *
 * @param array $orderids Order IDs.
 * @return array Report date timestamps.
 */
function local_moderncommerce_seed_get_report_dates(array $orderids): array {
    global $DB;

    if (empty($orderids)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal($orderids, SQL_PARAMS_NAMED, 'order');
    $timestamps = $DB->get_fieldset_select('local_moderncommerce_orders', 'timecreated', "id {$insql}", $params);
    $reportdates = [];
    foreach ($timestamps as $timestamp) {
        $reportdates[] = strtotime(date('Y-m-d 00:00:00', (int)$timestamp));
    }

    return array_values(array_unique($reportdates));
}

/**
 * Ensure the demo Moodle course categories and courses exist, returning the course records.
 *
 * Categories/courses are marked with an "MCDEMO-" idnumber so they are idempotent and
 * removable via --reset. Courses are distributed round-robin across the categories.
 *
 * @param int $categorycount Number of demo categories to ensure.
 * @param int $coursecount Number of demo courses to ensure.
 * @param int $now Current time.
 * @return array ['courses' => stdClass[], 'categories' => array<array{id:int,icon:string}>]
 */
function local_moderncommerce_seed_moodle_catalog(int $categorycount, int $coursecount, int $now): array {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/course/lib.php');

    // Category name + a fitting Bootstrap icon (bare name; the widget renders "bi bi-<name>").
    $categorydefs = [
        ['Web Development', 'code-slash'],
        ['UI/UX Design', 'brush'],
        ['Data Science', 'graph-up'],
        ['Digital Marketing', 'megaphone'],
        ['Business & Entrepreneurship', 'briefcase'],
        ['Personal Finance', 'cash-stack'],
        ['IT & Software', 'laptop'],
        ['Health & Fitness', 'heart-pulse'],
        ['Photography & Video', 'camera'],
        ['Music & Audio', 'music-note-beamed'],
        ['Language Learning', 'translate'],
        ['Leadership & Management', 'people'],
        ['Project Management', 'kanban'],
        ['Cloud & DevOps', 'cloud'],
        ['Cybersecurity', 'shield-lock'],
        ['Graphic Design', 'palette'],
    ];

    $coursetitles = [
        'Modern JavaScript from Scratch', 'Responsive Web Design Mastery', 'Figma for Product Designers',
        'Design Systems in Practice', 'Python for Data Analysis', 'Machine Learning Foundations',
        'SEO & Content Strategy', 'Social Media Marketing', 'Launching Your First Startup',
        'Pitching to Investors', 'Personal Budgeting Essentials', 'Investing for Beginners',
        'Linux Administration Basics', 'Networking Fundamentals', 'Strength Training 101',
        'Mindful Yoga & Mobility', 'Portrait Photography', 'Video Editing with DaVinci',
        'Music Production Crash Course', 'Mixing & Mastering Audio', 'Conversational Spanish',
        'Business English Communication', 'Leading High-Performing Teams', 'Agile Project Management',
        'AWS Cloud Practitioner', 'Docker & Kubernetes Intro', 'Ethical Hacking Foundations',
        'Brand Identity Design', 'Advanced Excel for Business', 'Public Speaking Confidence',
    ];

    // Ensure categories (idempotent by idnumber); track id + icon for the widget seeder.
    $categoryids = [];
    $categories = [];
    for ($i = 0; $i < $categorycount; $i++) {
        $def = $categorydefs[$i % count($categorydefs)];
        $idnumber = 'MCDEMO-CAT-' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
        $existing = $DB->get_record('course_categories', ['idnumber' => $idnumber], 'id', IGNORE_MULTIPLE);
        if ($existing) {
            $catid = (int)$existing->id;
        } else {
            $category = core_course_category::create((object)[
                'name' => $def[0],
                'idnumber' => $idnumber,
                'description' => 'Demo category seeded by Modern Commerce.',
                'descriptionformat' => FORMAT_HTML,
            ]);
            $catid = (int)$category->id;
        }
        $categoryids[] = $catid;
        $categories[] = ['id' => $catid, 'icon' => $def[1]];
    }

    // Ensure courses (idempotent by idnumber), spread across the categories.
    $courses = [];
    $fields = 'id, category, shortname, fullname, summary, visible';
    for ($i = 0; $i < $coursecount; $i++) {
        $seq = str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
        $idnumber = 'MCDEMO-COURSE-' . $seq;
        $existing = $DB->get_record('course', ['idnumber' => $idnumber], $fields, IGNORE_MULTIPLE);
        if ($existing) {
            $courses[] = $existing;
            continue;
        }
        $created = create_course((object)[
            'category' => $categoryids[$i % count($categoryids)],
            'fullname' => $coursetitles[$i % count($coursetitles)],
            'shortname' => 'mcdemo-c' . $seq,
            'idnumber' => $idnumber,
            'summary' => 'Demo course seeded by Modern Commerce for catalog and category previews.',
            'summaryformat' => FORMAT_HTML,
            'format' => 'topics',
            'visible' => 1,
            'startdate' => $now,
        ]);
        $courses[] = $DB->get_record('course', ['id' => $created->id], $fields, MUST_EXIST);
    }

    return ['courses' => $courses, 'categories' => $categories];
}

/**
 * Pre-configure the storefront Categories widget(s) with the seeded categories + matching icons.
 *
 * Sets each widget's `items` list (categoryid + bare icon name) so the curated grid shows the
 * demo categories. Existing categories widgets are updated in place; none are created.
 *
 * The ids MUST be Modern Commerce product category ids (local_moderncommerce_product_categories),
 * not Moodle course category ids - the tiles resolver only ever looks products up by the former.
 *
 * @param array $categories List of ['id' => int, 'icon' => string].
 * @return int Number of categories widgets updated.
 */
function local_moderncommerce_seed_categories_widget(array $categories): int {
    if (empty($categories)) {
        return 0;
    }

    $items = [];
    foreach ($categories as $category) {
        $items[] = [
            'categoryid' => (string)$category['id'],
            'icon' => (string)$category['icon'],
        ];
    }

    $count = 0;
    foreach (\local_moderncommerce\persistent\widget::get_records(['type' => 'categories']) as $widget) {
        $settings = json_decode((string)$widget->get('settings'), true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings['items'] = $items;
        if (!array_key_exists('showcount', $settings)) {
            $settings['showcount'] = true;
        }
        $widget->set('settings', json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $widget->update();
        $count++;
    }

    return $count;
}

/**
 * Resolve the sample user.
 *
 * @param int $userid Requested user ID.
 * @return int User ID.
 */
function local_moderncommerce_seed_resolve_userid(int $userid): int {
    global $DB, $CFG;

    if ($userid > 0 && $DB->record_exists('user', ['id' => $userid, 'deleted' => 0])) {
        return $userid;
    }

    $siteadmins = array_filter(array_map('intval', explode(',', $CFG->siteadmins ?? '')));
    foreach ($siteadmins as $adminid) {
        if ($DB->record_exists('user', ['id' => $adminid, 'deleted' => 0])) {
            return $adminid;
        }
    }

    return (int)$DB->get_field_select('user', 'id', 'deleted = 0 AND id <> :guestid', ['guestid' => 1], IGNORE_MULTIPLE);
}

/**
 * Get users to rotate through sample orders.
 *
 * @param int $preferreduserid Preferred user ID.
 * @return array User records.
 */
function local_moderncommerce_seed_get_users(int $preferreduserid): array {
    global $DB;

    $users = $DB->get_records_select(
        'user',
        'deleted = 0 AND id <> :guestid',
        ['guestid' => 1],
        'id ASC',
        'id, firstname, lastname, email',
        0,
        25
    );

    if ($preferreduserid > 0) {
        $preferred = $DB->get_record('user', ['id' => $preferreduserid, 'deleted' => 0], 'id, firstname, lastname, email');
        if ($preferred) {
            unset($users[$preferreduserid]);
            array_unshift($users, $preferred);
        }
    }

    return array_values($users);
}

/**
 * Seed demo categories.
 *
 * Product categories are a FLAT list: the admin screen (category_service::save_category) never
 * writes a parent, and the storefront filter, the category tiles and coupon targeting all match on
 * a single category id with no hierarchy. Seeding a parent "Modern Commerce Demo" wrapper would
 * therefore surface an extra category that holds no products of its own - a dead entry in the
 * catalog filter and a zero-count tile linking to an empty page. Every seeded category here is
 * top-level and carries products.
 *
 * @param int $now Current time.
 * @return array ['courses' => int, 'bundles' => int] Product category IDs.
 */
function local_moderncommerce_seed_categories(int $now): array {
    global $DB;

    $coursesid = local_moderncommerce_seed_upsert('local_moderncommerce_product_categories', [
        'slug' => 'moderncommerce-demo-courses',
    ], [
        'parentid' => null,
        'name' => 'Demo Courses',
        'slug' => 'moderncommerce-demo-courses',
        'description' => 'Sample individual course products.',
        'path' => '',
        'depth' => 0,
        'visible' => 1,
        'sortorder' => 10,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
    $DB->set_field('local_moderncommerce_product_categories', 'path', '/' . $coursesid . '/', ['id' => $coursesid]);

    $bundlesid = local_moderncommerce_seed_upsert('local_moderncommerce_product_categories', [
        'slug' => 'moderncommerce-demo-bundles',
    ], [
        'parentid' => null,
        'name' => 'Demo Bundles',
        'slug' => 'moderncommerce-demo-bundles',
        'description' => 'Sample bundle and program products.',
        'path' => '',
        'depth' => 0,
        'visible' => 1,
        'sortorder' => 20,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
    $DB->set_field('local_moderncommerce_product_categories', 'path', '/' . $bundlesid . '/', ['id' => $bundlesid]);

    return [
        'courses' => $coursesid,
        'bundles' => $bundlesid,
    ];
}

/**
 * Seed demo product attributes.
 *
 * @param int $now Current time.
 * @return array Attribute IDs.
 */
function local_moderncommerce_seed_attributes(int $now): array {
    $definitions = [
        'demo_difficulty' => ['Difficulty', 'text', 'select', 1, 1],
        'demo_delivery' => ['Delivery mode', 'text', 'select', 1, 1],
        'demo_certificate' => ['Certificate included', 'boolean', 'checkbox', 1, 0],
        'demo_duration_hours' => ['Duration hours', 'number', 'number', 0, 0],
    ];

    $ids = [];
    $sortorder = 10;
    foreach ($definitions as $code => $definition) {
        $ids[$code] = local_moderncommerce_seed_upsert('local_moderncommerce_product_attributes', ['code' => $code], [
            'code' => $code,
            'name' => $definition[0],
            'datatype' => $definition[1],
            'inputtype' => $definition[2],
            'required' => 0,
            'searchable' => $definition[4],
            'filterable' => $definition[3],
            'visible' => 1,
            'multivalue' => 0,
            'sortorder' => $sortorder,
            'configdata' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $sortorder += 10;
    }

    return $ids;
}

/**
 * Seed one course product.
 *
 * @param stdClass $course Course record.
 * @param float $amount Price.
 * @param string $level Difficulty level.
 * @param string $variantname Variant display name.
 * @param int $variant Variant number.
 * @param int $position Position.
 * @param int $categoryid Category ID.
 * @param array $attributeids Attribute IDs.
 * @param int $now Current time.
 * @return stdClass Product summary.
 */
function local_moderncommerce_seed_course_product(
    stdClass $course,
    float $amount,
    string $level,
    string $variantname,
    int $variant,
    int $position,
    int $categoryid,
    array $attributeids,
    int $now
): stdClass {
    $suffix = $course->id . '-' . str_pad((string)$variant, 2, '0', STR_PAD_LEFT);
    $sku = 'MC-DEMO-COURSE-' . $suffix;
    $slug = 'moderncommerce-demo-course-' . $suffix;
    $name = $course->fullname . ' - ' . $variantname;
    $created = $now - (($position + 1) * HOURSECS);

    $productid = local_moderncommerce_seed_upsert('local_moderncommerce_products', ['sku' => $sku], [
        'producttype' => 'course',
        'name' => $name,
        'slug' => $slug,
        'sku' => $sku,
        'status' => ($position % 17 === 0) ? 'draft' : 'active',
        'visible' => ($position % 19 === 0) ? 0 : 1,
        'featured' => ($position % 5 === 0 || $amount <= 79) ? 1 : 0,
        'shortdescription' => 'Demo commerce product linked to Moodle course: ' . $course->shortname,
        'description' => 'Sample product generated for Modern Commerce pagination, sorting, filtering, and UI review.',
        'imageurl' => null,
        'taxable' => 1,
        'taxcategory' => 'standard',
        'enrolduration' => 0,
        'maxenrollment' => ($position % 4 === 0) ? 50 + $position : null,
        'currentenrollment' => $position % 11,
        'displayorder' => $position + 1,
        'createdby' => null,
        'modifiedby' => null,
        'timecreated' => $created,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_product_courses', [
        'productid' => $productid,
        'courseid' => $course->id,
        'relationtype' => 'included',
    ], [
        'productid' => $productid,
        'courseid' => $course->id,
        'relationtype' => 'included',
        'sortorder' => 10,
        'required' => 1,
        'timecreated' => $created,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_product_prices', [
        'productid' => $productid,
        'pricetype' => 'regular',
    ], [
        'productid' => $productid,
        'pricetype' => 'regular',
        'amount' => $amount,
        'compareamount' => round($amount * 1.25, 6),
        'minquantity' => 1,
        'maxquantity' => null,
        'startdate' => null,
        'enddate' => null,
        'enabled' => 1,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_product_inventory', ['productid' => $productid], [
        'productid' => $productid,
        'stockmanaged' => ($position % 3 === 0) ? 1 : 0,
        'stock' => ($position % 3 === 0) ? 20 + $position : null,
        'reservedstock' => ($position % 7 === 0) ? 2 : 0,
        'allowbackorder' => ($position % 13 === 0) ? 1 : 0,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_category_map($productid, $categoryid, $now);
    local_moderncommerce_seed_tags(
        $productid,
        ['demo', 'course', strtolower($level), strtolower(str_replace(' ', '-', $variantname))],
        $now
    );
    local_moderncommerce_seed_attribute_values($productid, $attributeids, $level, 4 + ($position % 24), $now);
    local_moderncommerce_seed_course_meta($course, $level, $now);

    return (object)[
        'id' => $productid,
        'courseid' => (int)$course->id,
        'name' => $name,
        'sku' => $sku,
        'amount' => $amount,
    ];
}

/**
 * Seed a bundle product from course products.
 *
 * @param array $products Product summaries.
 * @param int $categoryid Category ID.
 * @param array $attributeids Attribute IDs.
 * @param int $now Current time.
 * @param int $bundleindex Bundle index.
 * @return int Bundle product ID.
 */
function local_moderncommerce_seed_bundle_product(
    array $products,
    int $categoryid,
    array $attributeids,
    int $now,
    int $bundleindex
): int {
    $offset = (($bundleindex - 1) * 3) % max(1, count($products));
    $rotated = array_merge(array_slice($products, $offset), array_slice($products, 0, $offset));
    $bundleproducts = array_slice($rotated, 0, min(4, count($rotated)));
    $sum = array_sum(array_map(static function ($product) {
        return $product->amount;
    }, $bundleproducts));
    $amount = max(1, round($sum * 0.8, 6));
    $bundlecode = str_pad((string)$bundleindex, 2, '0', STR_PAD_LEFT);
    $bundletypes = ['Starter', 'Professional', 'Leadership', 'Implementation', 'Certification', 'Executive'];
    $bundletype = $bundletypes[($bundleindex - 1) % count($bundletypes)];
    $sku = 'MC-DEMO-BUNDLE-' . $bundlecode;
    $slug = 'moderncommerce-demo-bundle-' . $bundlecode;

    $bundleid = local_moderncommerce_seed_upsert('local_moderncommerce_products', ['sku' => $sku], [
        'producttype' => 'bundle',
        'name' => 'Modern Commerce ' . $bundletype . ' Bundle',
        'slug' => $slug,
        'sku' => $sku,
        'status' => 'active',
        'visible' => 1,
        'featured' => $bundleindex <= 3 ? 1 : 0,
        'shortdescription' => 'Demo bundle combining multiple Moodle course products.',
        'description' => 'Sample bundle product used to validate product relationships, bundle pricing, and entitlement planning.',
        'imageurl' => null,
        'taxable' => 1,
        'taxcategory' => 'standard',
        'enrolduration' => 0,
        'maxenrollment' => null,
        'currentenrollment' => 0,
        'displayorder' => 1000 + $bundleindex,
        'createdby' => null,
        'modifiedby' => null,
        'timecreated' => $now - (($bundleindex + 1) * DAYSECS),
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_product_prices', [
        'productid' => $bundleid,
        'pricetype' => 'regular',
    ], [
        'productid' => $bundleid,
        'pricetype' => 'regular',
        'amount' => $amount,
        'compareamount' => round($sum, 6),
        'minquantity' => 1,
        'maxquantity' => null,
        'startdate' => null,
        'enddate' => null,
        'enabled' => 1,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_product_inventory', ['productid' => $bundleid], [
        'productid' => $bundleid,
        'stockmanaged' => 0,
        'stock' => null,
        'reservedstock' => 0,
        'allowbackorder' => 0,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_category_map($bundleid, $categoryid, $now);
    local_moderncommerce_seed_tags($bundleid, ['demo', 'bundle', strtolower($bundletype)], $now);
    local_moderncommerce_seed_attribute_values($bundleid, $attributeids, 'Intermediate', 18, $now);

    $sortorder = 10;
    foreach ($bundleproducts as $product) {
        local_moderncommerce_seed_upsert('local_moderncommerce_product_courses', [
            'productid' => $bundleid,
            'courseid' => $product->courseid,
            'relationtype' => 'included',
        ], [
            'productid' => $bundleid,
            'courseid' => $product->courseid,
            'relationtype' => 'included',
            'sortorder' => $sortorder,
            'required' => 1,
            'timecreated' => $now,
        ]);

        local_moderncommerce_seed_upsert('local_moderncommerce_product_relations', [
            'parentproductid' => $bundleid,
            'childproductid' => $product->id,
            'relationtype' => 'bundle_item',
        ], [
            'parentproductid' => $bundleid,
            'childproductid' => $product->id,
            'relationtype' => 'bundle_item',
            'quantity' => 1,
            'required' => 1,
            'pricingmode' => 'included',
            'sortorder' => $sortorder,
            'configdata' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $sortorder += 10;
    }

    return $bundleid;
}

/**
 * Seed a product/category map row.
 *
 * @param int $productid Product ID.
 * @param int $categoryid Category ID.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_category_map(int $productid, int $categoryid, int $now): void {
    local_moderncommerce_seed_upsert('local_moderncommerce_product_category_map', [
        'productid' => $productid,
        'categoryid' => $categoryid,
    ], [
        'productid' => $productid,
        'categoryid' => $categoryid,
        'isprimary' => 1,
        'timecreated' => $now,
    ]);
}

/**
 * Seed tags.
 *
 * @param int $productid Product ID.
 * @param array $tags Tags.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_tags(int $productid, array $tags, int $now): void {
    foreach ($tags as $tag) {
        local_moderncommerce_seed_upsert('local_moderncommerce_product_tags', [
            'productid' => $productid,
            'tag' => $tag,
        ], [
            'productid' => $productid,
            'tag' => $tag,
            'timecreated' => $now,
        ]);
    }
}

/**
 * Seed attribute values.
 *
 * @param int $productid Product ID.
 * @param array $attributeids Attribute IDs.
 * @param string $difficulty Difficulty.
 * @param int $hours Duration hours.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_attribute_values(
    int $productid,
    array $attributeids,
    string $difficulty,
    int $hours,
    int $now
): void {
    local_moderncommerce_seed_attribute_value(
        $productid,
        $attributeids['demo_difficulty'],
        $difficulty,
        null,
        null,
        null,
        10,
        $now
    );
    local_moderncommerce_seed_attribute_value($productid, $attributeids['demo_delivery'], 'Self-paced', null, null, null, 20, $now);
    local_moderncommerce_seed_attribute_value($productid, $attributeids['demo_certificate'], 'Included', null, null, 1, 30, $now);
    local_moderncommerce_seed_attribute_value($productid, $attributeids['demo_duration_hours'], null, $hours, null, null, 40, $now);
}

/**
 * Seed one attribute value.
 *
 * @param int $productid Product ID.
 * @param int $attributeid Attribute ID.
 * @param string|null $valuechar Char value.
 * @param float|null $valuenumber Number value.
 * @param int|null $valueint Integer value.
 * @param int|null $valuebool Boolean value.
 * @param int $sortorder Sort order.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_attribute_value(
    int $productid,
    int $attributeid,
    ?string $valuechar,
    ?float $valuenumber,
    ?int $valueint,
    ?int $valuebool,
    int $sortorder,
    int $now
): void {
    $rawvalue = $valuechar ?? ($valuenumber ?? ($valueint ?? $valuebool));
    $valuehash = hash('sha256', (string)$rawvalue);

    local_moderncommerce_seed_upsert('local_moderncommerce_product_attribute_values', [
        'productid' => $productid,
        'attributeid' => $attributeid,
        'valuehash' => $valuehash,
    ], [
        'productid' => $productid,
        'attributeid' => $attributeid,
        'valuehash' => $valuehash,
        'valuechar' => $valuechar,
        'valuetext' => null,
        'valuenumber' => $valuenumber,
        'valueint' => $valueint,
        'valuebool' => $valuebool,
        'sortorder' => $sortorder,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
}

/**
 * Seed course catalog metadata.
 *
 * @param stdClass $course Course.
 * @param string $level Skill level.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_course_meta(stdClass $course, string $level, int $now): void {
    local_moderncommerce_seed_upsert('local_moderncommerce_course_meta', ['courseid' => $course->id], [
        'courseid' => $course->id,
        'durationminutes' => 360 + (($course->id % 6) * 60),
        'skilllevel' => $level,
        'language' => 'English',
        'passgrade' => 70,
        'certificateenabled' => 1,
        'overview' => 'Demo catalog overview for ' . $course->fullname . '.',
        'featured' => 1,
        'bestseller' => $course->id % 2,
        'trending' => 1,
        'usermodified' => null,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
}

/**
 * Seed course reviews and reactions for every demo course.
 *
 * @param array $courses Course records.
 * @param int $preferreduserid Preferred user ID.
 * @param int $reviewspercourse Number of reviews per course.
 * @param int $now Current time.
 * @return array Seed counts.
 */
function local_moderncommerce_seed_course_reviews(
    array $courses,
    int $preferreduserid,
    int $reviewspercourse,
    int $now
): array {
    if (empty($courses) || $reviewspercourse <= 0) {
        return ['reviews' => 0, 'reactions' => 0];
    }

    $users = local_moderncommerce_seed_get_users($preferreduserid);
    if (empty($users)) {
        return ['reviews' => 0, 'reactions' => 0];
    }

    $reviewcount = 0;
    $reactioncount = 0;
    $ratings = [5, 5, 4, 4, 5, 3, 4, 5, 4, 3];
    $courses = array_values($courses);
    $limit = min($reviewspercourse, count($users));

    foreach ($courses as $courseindex => $course) {
        for ($reviewindex = 0; $reviewindex < $limit; $reviewindex++) {
            $user = $users[($courseindex + $reviewindex) % count($users)];
            $rating = $ratings[($courseindex + $reviewindex) % count($ratings)];
            $created = $now - ((($courseindex * $limit) + $reviewindex + 1) * HOURSECS);
            $reviewid = local_moderncommerce_seed_upsert('local_moderncommerce_reviews', [
                'courseid' => (int)$course->id,
                'userid' => (int)$user->id,
            ], [
                'courseid' => (int)$course->id,
                'userid' => (int)$user->id,
                'rating' => $rating,
                'comment' => local_moderncommerce_seed_review_comment($course, $courseindex, $reviewindex),
                'timecreated' => $created,
                'timemodified' => $now,
                'hidden' => $reviewindex > 0 && (($courseindex + $reviewindex) % 13 === 0) ? 1 : 0,
            ]);

            $reviewcount++;
            $reactioncount += local_moderncommerce_seed_review_reactions(
                $reviewid,
                (int)$user->id,
                $users,
                ($courseindex * $limit) + $reviewindex,
                $now
            );
        }
    }

    return ['reviews' => $reviewcount, 'reactions' => $reactioncount];
}

/**
 * Build a deterministic review comment.
 *
 * @param stdClass $course Course record.
 * @param int $courseindex Course index.
 * @param int $reviewindex Review index.
 * @return string Review comment.
 */
function local_moderncommerce_seed_review_comment(stdClass $course, int $courseindex, int $reviewindex): string {
    $templates = [
        'Clear lessons, practical examples, and a pace that made {$course} easy to apply.',
        '{$course} gave me a useful structure and enough hands-on practice to feel confident.',
        'The examples were relevant, the checkpoints were helpful, and the course stayed focused.',
        'Strong course for building momentum. I especially liked the applied exercises and summaries.',
        'A practical learning path with concise explanations and realistic assignments.',
        'Good balance of theory and practice. The course helped me connect the concepts quickly.',
        'Well organized modules, useful examples, and a smooth learning experience overall.',
        'The course felt current, direct, and easy to revisit when I needed a refresher.',
    ];
    $template = $templates[($courseindex + $reviewindex) % count($templates)];

    return str_replace('{$course}', $course->fullname, $template);
}

/**
 * Seed reactions for one review.
 *
 * @param int $reviewid Review ID.
 * @param int $reviewerid Reviewer user ID.
 * @param array $users Candidate users.
 * @param int $seed Deterministic seed.
 * @param int $now Current time.
 * @return int Number of reaction rows created or updated.
 */
function local_moderncommerce_seed_review_reactions(
    int $reviewid,
    int $reviewerid,
    array $users,
    int $seed,
    int $now
): int {
    $reactors = [];
    foreach ($users as $user) {
        if ((int)$user->id !== $reviewerid) {
            $reactors[] = $user;
        }
    }

    if (empty($reactors)) {
        return 0;
    }

    $count = 0;
    $reactions = [3, 1, 1, 3, 1, 2];
    $limit = min(4, count($reactors));
    for ($i = 0; $i < $limit; $i++) {
        $user = $reactors[($seed + $i) % count($reactors)];
        local_moderncommerce_seed_upsert('local_moderncommerce_review_rxn', [
            'reviewid' => $reviewid,
            'userid' => (int)$user->id,
        ], [
            'reviewid' => $reviewid,
            'userid' => (int)$user->id,
            'reaction' => $reactions[($seed + $i) % count($reactions)],
            'timecreated' => $now - (($seed + $i + 1) * 900),
        ]);
        $count++;
    }

    return $count;
}

/**
 * Seed coupons.
 *
 * @param int $count Number of coupons.
 * @param int $now Current time.
 * @return array Coupon IDs.
 */
function local_moderncommerce_seed_coupons(int $count, int $now): array {
    $ids = [];
    $discounts = [10, 15, 20, 25, 30, 40];
    $types = ['percentage', 'fixed'];

    for ($i = 1; $i <= $count; $i++) {
        $code = $i === 1 ? 'MODERN20' : 'MODERN' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $discount = $discounts[($i - 1) % count($discounts)];
        $type = $types[($i - 1) % count($types)];
        $status = ($i % 8 === 0) ? 'inactive' : 'active';

        $ids[] = local_moderncommerce_seed_upsert('local_moderncommerce_coupons', ['code' => $code], [
            'code' => $code,
            'name' => 'Modern Commerce demo ' . $discount . ($type === 'percentage' ? '% off' : ' off'),
            'discounttype' => $type,
            'value' => $discount,
            'maxdiscount' => $type === 'percentage' ? 100 : null,
            'minpurchase' => ($i % 3 === 0) ? 100 : null,
            'minitems' => ($i % 4 === 0) ? 2 : null,
            'maxuses' => 100 + ($i * 10),
            'usedcount' => $i % 6,
            'maxusesperuser' => 1,
            'stackable' => $i % 5 === 0 ? 1 : 0,
            'status' => $status,
            'startdate' => $now - (($i + 1) * DAYSECS),
            'enddate' => $now + ((15 + $i) * DAYSECS),
            'createdby' => null,
            'timecreated' => $now - ($i * HOURSECS),
            'timemodified' => $now,
        ]);
    }

    return $ids;
}

/**
 * Seed enrollment keys.
 *
 * @param array $products Product summaries.
 * @param string $currency Currency.
 * @param int $count Number of keys.
 * @param int $now Current time.
 * @return array Key IDs.
 */
function local_moderncommerce_seed_enrollkeys(array $products, string $currency, int $count, int $now): array {
    $ids = [];

    for ($i = 1; $i <= $count; $i++) {
        $product = $products[($i - 1) % count($products)];
        $keycode = $i === 1 ? 'MODERN-DEMO-KEY' : 'MODERN-DEMO-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT);
        $status = ($i % 10 === 0) ? 'inactive' : 'active';

        $keyid = local_moderncommerce_seed_upsert('local_moderncommerce_enrollkeys', ['keycode' => $keycode], [
            'keycode' => $keycode,
            'keytype' => 'course',
            'value' => 0,
            'currency' => $currency,
            'remainingvalue' => 0,
            'maxuses' => 1 + ($i % 5),
            'usedcount' => $i % 3 === 0 ? 1 : 0,
            'maxusesperuser' => 1,
            'batchid' => 'MODERN-DEMO',
            'batchname' => 'Modern Commerce demo keys',
            'status' => $status,
            'startdate' => $now - (($i + 1) * DAYSECS),
            'expirydate' => $now + ((30 + $i) * DAYSECS),
            'requiredemail' => null,
            'enrolduration' => 0,
            'roleid' => null,
            'notes' => 'Sample enrollment key generated by seed_sample_data.php.',
            'createdby' => null,
            'timecreated' => $now - ($i * HOURSECS),
            'timemodified' => $now,
        ]);

        local_moderncommerce_seed_upsert('local_moderncommerce_enrollkey_targets', [
            'enrollkeyid' => $keyid,
            'targettype' => 'product',
            'targetid' => $product->id,
        ], [
            'enrollkeyid' => $keyid,
            'targettype' => 'product',
            'targetid' => $product->id,
            'targetvalue' => null,
            'includemode' => 'include',
            'timecreated' => $now,
        ]);

        $ids[] = $keyid;
    }

    return $ids;
}

/**
 * Seed wishlist row.
 *
 * @param int $userid User ID.
 * @param int $productid Product ID.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_wishlist(int $userid, int $productid, int $now): void {
    if ($userid <= 0) {
        return;
    }

    local_moderncommerce_seed_upsert('local_moderncommerce_wishlist', [
        'userid' => $userid,
        'productid' => $productid,
    ], [
        'userid' => $userid,
        'productid' => $productid,
        'timecreated' => $now,
    ]);
}

/**
 * Seed many orders with varied lifecycle states.
 *
 * @param int $preferreduserid Preferred user ID.
 * @param array $products Product summaries.
 * @param stdClass $currency Currency config.
 * @param int $count Number of orders.
 * @param int $now Current time.
 * @return int Created/updated order count.
 */
function local_moderncommerce_seed_orders(
    int $preferreduserid,
    array $products,
    stdClass $currency,
    int $count,
    int $now
): int {
    global $CFG;

    if ($count <= 0 || empty($products)) {
        return 0;
    }

    $users = local_moderncommerce_seed_get_users($preferreduserid);
    if (empty($users)) {
        cli_error('No usable Moodle users found. Create at least one non-guest user before seeding orders.');
    }

    $statuses = ['completed', 'paid', 'processing', 'pending', 'failed', 'cancelled', 'refunded'];
    $gateways = ['manual', 'paystack', 'flutterwave', 'stripe', 'paypal'];
    $created = 0;

    for ($i = 1; $i <= $count; $i++) {
        $sequence = str_pad((string)$i, 5, '0', STR_PAD_LEFT);
        $product = $products[($i - 1) % count($products)];
        $user = $users[($i - 1) % count($users)];
        $status = $statuses[($i - 1) % count($statuses)];
        $gateway = $gateways[($i - 1) % count($gateways)];
        $ordernumber = 'MC-DEMO-ORDER-' . $sequence;
        $reference = 'MC-DEMO-PAY-' . $sequence;
        $transactionid = 'MC-DEMO-TXN-' . $sequence;
        $gatewayeventid = 'MC-DEMO-EVENT-' . $sequence;
        $createdtime = $now - ($i * HOURSECS);
        $quantity = ($i % 11 === 0) ? 2 : 1;
        $subtotal = round($product->amount * $quantity, 6);
        $discount = ($subtotal > 0 && $i % 5 === 0) ? min($subtotal, round($subtotal * 0.15, 6)) : 0;
        $taxable = max(0, $subtotal - $discount);
        $tax = ($taxable > 0 && $i % 4 === 0) ? round($taxable * 0.075, 6) : 0;
        $fees = ($subtotal > 0 && $i % 6 === 0) ? 2.500000 : 0;
        $total = round(max(0, $taxable + $tax + $fees), 6);
        $ispaid = local_moderncommerce_seed_order_is_paid_status($status);
        $isrefunded = $status === 'refunded';
        $paidtime = $ispaid ? $createdtime + 900 : null;
        $completedtime = local_moderncommerce_seed_order_is_fulfilled_status($status) ? $createdtime + 1800 : null;
        $refundedtotal = $isrefunded ? $total : 0;
        $refundedtime = $isrefunded ? $createdtime + 3600 : null;

        $orderid = local_moderncommerce_seed_upsert('local_moderncommerce_orders', ['ordernumber' => $ordernumber], [
            'userid' => $user->id,
            'ordernumber' => $ordernumber,
            'ordertype' => 'sample',
            'status' => $status,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'fees' => $fees,
            'total' => $total,
            'refundedtotal' => $refundedtotal,
            'currency' => $currency->currency,
            'currencysymbol' => $currency->symbol,
            'currencyposition' => $currency->position,
            'decimalplaces' => $currency->decimals,
            'exchangerate' => 1,
            'couponcode' => $discount > 0 ? 'MODERN20' : null,
            'customeremail' => $user->email,
            'ipaddress' => '127.0.0.1',
            'useragent' => 'Modern Commerce seed script',
            'referrer' => 'sample-data',
            'notes' => 'Sample order generated for pagination, sorting, filtering, and lifecycle review.',
            'adminnotes' => 'Safe to delete with --reset.',
            'createdby' => $preferreduserid ?: $user->id,
            'modifiedby' => $preferreduserid ?: $user->id,
            'timecreated' => $createdtime,
            'timemodified' => $now,
            'timepaid' => $paidtime,
            'timecompleted' => $completedtime,
            'timerefunded' => $refundedtime,
        ]);

        $itemtotal = round(max(0, $subtotal - $discount + $tax), 6);
        $orderitemid = local_moderncommerce_seed_upsert('local_moderncommerce_order_items', [
            'orderid' => $orderid,
            'sku' => $product->sku,
        ], [
            'orderid' => $orderid,
            'productid' => $product->id,
            'priceid' => local_moderncommerce_seed_priceid($product->id),
            'courseid' => $product->courseid,
            'itemtype' => 'course',
            'itemname' => $product->name,
            'sku' => $product->sku,
            'unitprice' => $product->amount,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $itemtotal,
            'currency' => $currency->currency,
            'enrolduration' => null,
            'timecreated' => $createdtime,
        ]);

        local_moderncommerce_seed_order_adjustments(
            $orderid,
            $orderitemid,
            $ordernumber,
            $discount,
            $tax,
            $fees,
            $currency->currency,
            $createdtime
        );

        local_moderncommerce_seed_upsert('local_moderncommerce_order_addresses', [
            'orderid' => $orderid,
            'addresstype' => 'billing',
        ], [
            'orderid' => $orderid,
            'addresstype' => 'billing',
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'company' => 'Modern Commerce Demo',
            'email' => $user->email,
            'phone' => '+1 555 0100',
            'address1' => '100 Demo Commerce Street',
            'address2' => 'Suite ' . (($i % 30) + 1),
            'city' => 'Demo City',
            'state' => 'NY',
            'country' => 'US',
            'postcode' => '10001',
            'timecreated' => $createdtime,
            'timemodified' => $now,
        ]);

        local_moderncommerce_seed_upsert('local_moderncommerce_order_status_history', [
            'orderid' => $orderid,
            'newstatus' => $status,
            'source' => 'sample',
        ], [
            'orderid' => $orderid,
            'oldstatus' => $status === 'pending' ? null : 'pending',
            'newstatus' => $status,
            'actoruserid' => $preferreduserid ?: $user->id,
            'source' => 'sample',
            'note' => 'Seeded sample order lifecycle.',
            'timecreated' => $createdtime + 60,
        ]);

        $attemptstatus = local_moderncommerce_seed_payment_attempt_status($status);
        $attemptcompleted = in_array($attemptstatus, ['success', 'failed', 'cancelled'], true);
        $attemptid = local_moderncommerce_seed_upsert('local_moderncommerce_payment_attempts', [
            'gateway' => $gateway,
            'reference' => $reference,
        ], [
            'orderid' => $orderid,
            'gateway' => $gateway,
            'reference' => $reference,
            'amount' => $total,
            'currency' => $currency->currency,
            'status' => $attemptstatus,
            'idempotencykey' => hash('sha256', $reference),
            'gatewaytransactionid' => $attemptcompleted ? $transactionid : null,
            'redirecturl' => $attemptstatus === 'pending' ? $CFG->wwwroot . '/local/moderncommerce/checkout.php' : null,
            'errorcode' => $attemptstatus === 'failed' ? 'sample_declined' : null,
            'errormessage' => $attemptstatus === 'failed' ? 'Sample failed payment for UI testing.' : null,
            'timecreated' => $createdtime + 120,
            'timemodified' => $now,
            'timecompleted' => $attemptcompleted ? $createdtime + 600 : null,
        ]);

        $eventtype = 'payment.' . $attemptstatus;
        $processed = in_array($attemptstatus, ['success', 'failed', 'cancelled'], true) ? 1 : 0;
        local_moderncommerce_seed_upsert('local_moderncommerce_payment_events', [
            'dedupekey' => hash('sha256', $gateway . '|' . $eventtype . '|' . $reference),
        ], [
            'orderid' => $orderid,
            'attemptid' => $attemptid,
            'gateway' => $gateway,
            'dedupekey' => hash('sha256', $gateway . '|' . $eventtype . '|' . $reference),
            'eventtype' => $eventtype,
            'gatewayeventid' => $gatewayeventid,
            'reference' => $reference,
            'transactionid' => $attemptcompleted ? $transactionid : null,
            'amount' => $total,
            'currency' => $currency->currency,
            'status' => $processed ? 'processed' : 'received',
            'verified' => $attemptstatus === 'pending' ? 0 : 1,
            'payloadhash' => hash('sha256', $ordernumber . '|payment-event'),
            'rawpayload' => json_encode(['sample' => true, 'reference' => $reference, 'status' => $attemptstatus]),
            'processed' => $processed,
            'processingerror' => $attemptstatus === 'failed' ? 'Sample processor decline.' : null,
            'timecreated' => $createdtime + 180,
            'timeprocessed' => $processed ? $createdtime + 660 : null,
        ]);

        local_moderncommerce_seed_upsert('local_moderncommerce_payment_log', [
            'gateway' => $gateway,
            'action' => 'sample_seed',
            'reference' => $reference,
        ], [
            'orderid' => $orderid,
            'gateway' => $gateway,
            'action' => 'sample_seed',
            'reference' => $reference,
            'eventid' => $gatewayeventid,
            'correlationid' => $ordernumber,
            'result' => $attemptstatus,
            'payloadhash' => hash('sha256', $ordernumber . '|payment-log'),
            'redacted' => 1,
            'response' => json_encode(['sample' => true, 'status' => $attemptstatus]),
            'timecreated' => $createdtime + 190,
        ]);

        local_moderncommerce_seed_upsert('local_moderncommerce_order_operational', ['orderid' => $orderid], [
            'orderid' => $orderid,
            'cartid' => null,
            'createdvia' => 'seed',
            'checkoutsessionid' => 'MC-DEMO-CHECKOUT-' . $sequence,
            'cartchecksum' => hash('sha256', $ordernumber . '|cart'),
            'pricesincludetax' => 0,
            'couponusagerecorded' => $discount > 0 ? 1 : 0,
            'inventoryreserved' => in_array($status, ['processing', 'pending'], true) ? 1 : 0,
            'inventoryreduced' => local_moderncommerce_seed_order_is_fulfilled_status($status) ? 1 : 0,
            'receiptqueued' => $ispaid ? 0 : 1,
            'receiptsent' => $ispaid ? 1 : 0,
            'paymentstatus' => local_moderncommerce_seed_payment_status($status),
            'fulfillmentstatus' => local_moderncommerce_seed_fulfillment_status($status),
            'lastpaymentattemptid' => $attemptid,
            'lastgatewayeventid' => $gatewayeventid,
            'timepaid' => $paidtime,
            'timefulfilled' => $completedtime,
            'timecancelled' => $status === 'cancelled' ? $createdtime + 900 : null,
            'timemodified' => $now,
        ]);

        $invoiceid = local_moderncommerce_seed_upsert('local_moderncommerce_invoices', [
            'invoicenumber' => 'MC-DEMO-INV-' . $sequence,
        ], [
            'orderid' => $orderid,
            'userid' => $user->id,
            'invoicenumber' => 'MC-DEMO-INV-' . $sequence,
            'status' => local_moderncommerce_seed_invoice_status($status),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'currency' => $currency->currency,
            'duedate' => $createdtime + (14 * DAYSECS),
            'issuedat' => $createdtime + 300,
            'paidat' => $paidtime,
            'filepath' => null,
            'notes' => 'Sample invoice generated for Modern Commerce UI testing.',
            'terms' => 'Demo data only.',
            'createdby' => $preferreduserid ?: $user->id,
            'timecreated' => $createdtime + 300,
            'timemodified' => $now,
        ]);

        local_moderncommerce_seed_upsert('local_moderncommerce_invoice_items', [
            'invoiceid' => $invoiceid,
            'orderitemid' => $orderitemid,
        ], [
            'invoiceid' => $invoiceid,
            'orderitemid' => $orderitemid,
            'description' => $product->name,
            'quantity' => $quantity,
            'unitprice' => $product->amount,
            'total' => $itemtotal,
            'timecreated' => $createdtime + 300,
        ]);

        if (local_moderncommerce_seed_order_is_fulfilled_status($status)) {
            local_moderncommerce_seed_fulfillment_and_entitlement(
                $orderid,
                $orderitemid,
                $user->id,
                $preferreduserid ?: $user->id,
                $product,
                $ordernumber,
                $status,
                $createdtime,
                $now
            );
        }

        if ($isrefunded) {
            local_moderncommerce_seed_refund(
                $orderid,
                $orderitemid,
                $attemptid,
                $user->id,
                $preferreduserid ?: $user->id,
                $total,
                $tax,
                $currency->currency,
                $sequence,
                $createdtime
            );
        }

        local_moderncommerce_seed_increment_reports(
            $product,
            $gateway,
            $currency->currency,
            $quantity,
            $subtotal,
            $discount,
            $tax,
            $fees,
            $total,
            $refundedtotal,
            $status,
            $createdtime,
            $now
        );

        $created++;
    }

    return $created;
}

/**
 * Seed order adjustment rows.
 *
 * @param int $orderid Order ID.
 * @param int $orderitemid Order item ID.
 * @param string $ordernumber Order number.
 * @param float $discount Discount amount.
 * @param float $tax Tax amount.
 * @param float $fees Fee amount.
 * @param string $currency Currency.
 * @param int $timecreated Created time.
 */
function local_moderncommerce_seed_order_adjustments(
    int $orderid,
    int $orderitemid,
    string $ordernumber,
    float $discount,
    float $tax,
    float $fees,
    string $currency,
    int $timecreated
): void {
    if ($discount > 0) {
        local_moderncommerce_seed_upsert('local_moderncommerce_order_adjustments', [
            'orderid' => $orderid,
            'adjustmenttype' => 'discount',
            'sourcecode' => $ordernumber . '-DISCOUNT',
        ], [
            'orderid' => $orderid,
            'orderitemid' => $orderitemid,
            'adjustmenttype' => 'discount',
            'label' => 'Demo coupon MODERN20',
            'sourceid' => null,
            'sourcecode' => $ordernumber . '-DISCOUNT',
            'amount' => -1 * $discount,
            'currency' => $currency,
            'taxrate' => null,
            'timecreated' => $timecreated + 30,
        ]);
    }

    if ($tax > 0) {
        local_moderncommerce_seed_upsert('local_moderncommerce_order_adjustments', [
            'orderid' => $orderid,
            'adjustmenttype' => 'tax',
            'sourcecode' => $ordernumber . '-TAX',
        ], [
            'orderid' => $orderid,
            'orderitemid' => $orderitemid,
            'adjustmenttype' => 'tax',
            'label' => 'Demo sales tax',
            'sourceid' => null,
            'sourcecode' => $ordernumber . '-TAX',
            'amount' => $tax,
            'currency' => $currency,
            'taxrate' => 7.5000,
            'timecreated' => $timecreated + 35,
        ]);
    }

    if ($fees > 0) {
        local_moderncommerce_seed_upsert('local_moderncommerce_order_adjustments', [
            'orderid' => $orderid,
            'adjustmenttype' => 'fee',
            'sourcecode' => $ordernumber . '-FEE',
        ], [
            'orderid' => $orderid,
            'orderitemid' => null,
            'adjustmenttype' => 'fee',
            'label' => 'Demo processing fee',
            'sourceid' => null,
            'sourcecode' => $ordernumber . '-FEE',
            'amount' => $fees,
            'currency' => $currency,
            'taxrate' => null,
            'timecreated' => $timecreated + 40,
        ]);
    }
}

/**
 * Seed fulfillment, fulfillment item, entitlement, and entitlement event.
 *
 * @param int $orderid Order ID.
 * @param int $orderitemid Order item ID.
 * @param int $userid User ID.
 * @param int $actoruserid Actor user ID.
 * @param stdClass $product Product summary.
 * @param string $ordernumber Order number.
 * @param string $orderstatus Order status.
 * @param int $createdtime Created time.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_fulfillment_and_entitlement(
    int $orderid,
    int $orderitemid,
    int $userid,
    int $actoruserid,
    stdClass $product,
    string $ordernumber,
    string $orderstatus,
    int $createdtime,
    int $now
): void {
    $revoked = $orderstatus === 'refunded';
    $fulfilledtime = $createdtime + 1800;
    $revokedtime = $revoked ? $createdtime + 3600 : null;

    $fulfillmentid = local_moderncommerce_seed_upsert('local_moderncommerce_fulfillments', ['orderid' => $orderid], [
        'orderid' => $orderid,
        'userid' => $userid,
        'status' => $revoked ? 'revoked' : 'completed',
        'source' => 'sample',
        'timecreated' => $createdtime + 1200,
        'timemodified' => $now,
        'timecompleted' => $fulfilledtime,
    ]);

    $fulfillmentitemid = local_moderncommerce_seed_upsert('local_moderncommerce_fulfillment_items', [
        'orderitemid' => $orderitemid,
        'courseid' => $product->courseid,
    ], [
        'fulfillmentid' => $fulfillmentid,
        'orderitemid' => $orderitemid,
        'productid' => $product->id,
        'courseid' => $product->courseid,
        'itemtype' => 'course',
        'enrolid' => null,
        'status' => $revoked ? 'revoked' : 'completed',
        'timestart' => $fulfilledtime,
        'timeend' => null,
        'timefulfilled' => $fulfilledtime,
        'timerevoked' => $revokedtime,
    ]);

    $sourcekey = 'sample:orderitem:' . $orderitemid . ':course:' . $product->courseid;
    $entitlementid = local_moderncommerce_seed_upsert('local_moderncommerce_entitlements', [
        'sourcekey' => $sourcekey,
    ], [
        'sourcekey' => $sourcekey,
        'userid' => $userid,
        'productid' => $product->id,
        'courseid' => $product->courseid,
        'orderid' => $orderid,
        'orderitemid' => $orderitemid,
        'fulfillmentid' => $fulfillmentid,
        'fulfillmentitemid' => $fulfillmentitemid,
        'enrollkeyid' => null,
        'userenrolmentid' => null,
        'enrolinstanceid' => null,
        'entitlementtype' => 'course_access',
        'source' => 'sample',
        'status' => $revoked ? 'revoked' : 'active',
        'grantreason' => 'sample_order',
        'timestart' => $fulfilledtime,
        'timeend' => null,
        'timegranted' => $fulfilledtime,
        'timerevoked' => $revokedtime,
        'timeexpired' => null,
        'revokereason' => $revoked ? 'Sample refund lifecycle.' : null,
        'metadata' => json_encode(['moodle_enrolment_created' => false, 'reason' => 'enrol/moderncommerce not installed']),
        'timecreated' => $fulfilledtime,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_entitlement_events', [
        'eventuuid' => hash('sha256', $ordernumber . '|entitlement-granted'),
    ], [
        'entitlementid' => $entitlementid,
        'eventuuid' => hash('sha256', $ordernumber . '|entitlement-granted'),
        'eventtype' => 'granted',
        'oldstatus' => null,
        'newstatus' => 'active',
        'actoruserid' => $actoruserid,
        'source' => 'sample',
        'reason' => 'sample_order',
        'correlationid' => $ordernumber,
        'eventdata' => json_encode(['sample' => true, 'orderid' => $orderid, 'orderitemid' => $orderitemid]),
        'timecreated' => $fulfilledtime,
    ]);

    if ($revoked) {
        local_moderncommerce_seed_upsert('local_moderncommerce_entitlement_events', [
            'eventuuid' => hash('sha256', $ordernumber . '|entitlement-revoked'),
        ], [
            'entitlementid' => $entitlementid,
            'eventuuid' => hash('sha256', $ordernumber . '|entitlement-revoked'),
            'eventtype' => 'revoked',
            'oldstatus' => 'active',
            'newstatus' => 'revoked',
            'actoruserid' => $actoruserid,
            'source' => 'sample',
            'reason' => 'sample_refund',
            'correlationid' => $ordernumber,
            'eventdata' => json_encode(['sample' => true, 'orderid' => $orderid, 'orderitemid' => $orderitemid]),
            'timecreated' => $revokedtime,
        ]);
    }
}

/**
 * Seed a refund and refund item.
 *
 * @param int $orderid Order ID.
 * @param int $orderitemid Order item ID.
 * @param int $attemptid Payment attempt ID.
 * @param int $userid User ID.
 * @param int $actoruserid Actor user ID.
 * @param float $total Refund total.
 * @param float $tax Tax amount.
 * @param string $currency Currency.
 * @param string $sequence Sequence.
 * @param int $createdtime Created time.
 */
function local_moderncommerce_seed_refund(
    int $orderid,
    int $orderitemid,
    int $attemptid,
    int $userid,
    int $actoruserid,
    float $total,
    float $tax,
    string $currency,
    string $sequence,
    int $createdtime
): void {
    $refundreference = 'MC-DEMO-REF-' . $sequence;
    $refundid = local_moderncommerce_seed_upsert('local_moderncommerce_refunds', [
        'refundreference' => $refundreference,
    ], [
        'orderid' => $orderid,
        'attemptid' => $attemptid,
        'amount' => $total,
        'currency' => $currency,
        'reason' => 'Sample refund generated for refund workflow testing.',
        'status' => 'processed',
        'refundreference' => $refundreference,
        'requestedby' => $userid,
        'processedby' => $actoruserid,
        'adminnotes' => 'Demo refund.',
        'timerequested' => $createdtime + 3000,
        'timeprocessed' => $createdtime + 3600,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_refund_items', [
        'refundid' => $refundid,
        'orderitemid' => $orderitemid,
    ], [
        'refundid' => $refundid,
        'orderitemid' => $orderitemid,
        'quantity' => 1,
        'amount' => max(0, $total - $tax),
        'tax' => $tax,
        'total' => $total,
        'currency' => $currency,
        'timecreated' => $createdtime + 3600,
    ]);
}

/**
 * Whether an order status represents captured payment.
 *
 * @param string $status Order status.
 * @return bool
 */
function local_moderncommerce_seed_order_is_paid_status(string $status): bool {
    return in_array($status, ['completed', 'paid', 'refunded'], true);
}

/**
 * Whether an order status should produce fulfillment rows.
 *
 * @param string $status Order status.
 * @return bool
 */
function local_moderncommerce_seed_order_is_fulfilled_status(string $status): bool {
    return in_array($status, ['completed', 'paid', 'refunded'], true);
}

/**
 * Map order status to payment attempt status.
 *
 * @param string $status Order status.
 * @return string Payment attempt status.
 */
function local_moderncommerce_seed_payment_attempt_status(string $status): string {
    if (local_moderncommerce_seed_order_is_paid_status($status)) {
        return 'success';
    }

    if ($status === 'failed') {
        return 'failed';
    }

    if ($status === 'cancelled') {
        return 'cancelled';
    }

    if ($status === 'processing') {
        return 'processing';
    }

    return 'pending';
}

/**
 * Map order status to operational payment status.
 *
 * @param string $status Order status.
 * @return string Operational payment status.
 */
function local_moderncommerce_seed_payment_status(string $status): string {
    if ($status === 'refunded') {
        return 'refunded';
    }

    if (in_array($status, ['completed', 'paid'], true)) {
        return 'paid';
    }

    if ($status === 'failed') {
        return 'failed';
    }

    if ($status === 'cancelled') {
        return 'cancelled';
    }

    if ($status === 'processing') {
        return 'authorized';
    }

    return 'unpaid';
}

/**
 * Map order status to operational fulfillment status.
 *
 * @param string $status Order status.
 * @return string Operational fulfillment status.
 */
function local_moderncommerce_seed_fulfillment_status(string $status): string {
    if ($status === 'refunded') {
        return 'revoked';
    }

    if (in_array($status, ['completed', 'paid'], true)) {
        return 'fulfilled';
    }

    if ($status === 'processing') {
        return 'queued';
    }

    return 'unfulfilled';
}

/**
 * Map order status to invoice status.
 *
 * @param string $status Order status.
 * @return string Invoice status.
 */
function local_moderncommerce_seed_invoice_status(string $status): string {
    if (local_moderncommerce_seed_order_is_paid_status($status)) {
        return 'paid';
    }

    if ($status === 'cancelled') {
        return 'cancelled';
    }

    if ($status === 'failed') {
        return 'void';
    }

    if ($status === 'processing') {
        return 'sent';
    }

    return 'draft';
}

/**
 * Increment report snapshots for a sample order.
 *
 * @param stdClass $product Product summary.
 * @param string $gateway Payment gateway.
 * @param string $currency Currency.
 * @param float $quantity Quantity.
 * @param float $subtotal Subtotal.
 * @param float $discount Discount.
 * @param float $tax Tax.
 * @param float $fees Fees.
 * @param float $total Total.
 * @param float $refundedtotal Refunded total.
 * @param string $status Order status.
 * @param int $createdtime Order created time.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_increment_reports(
    stdClass $product,
    string $gateway,
    string $currency,
    float $quantity,
    float $subtotal,
    float $discount,
    float $tax,
    float $fees,
    float $total,
    float $refundedtotal,
    string $status,
    int $createdtime,
    int $now
): void {
    global $DB;

    $reportdate = strtotime(date('Y-m-d 00:00:00', $createdtime));
    $paid = local_moderncommerce_seed_order_is_paid_status($status);
    $refunded = $status === 'refunded';
    $failed = $status === 'failed';
    $grossincrement = $paid ? $subtotal : 0;
    $discountincrement = $paid ? $discount : 0;
    $taxincrement = $paid ? $tax : 0;
    $netincrement = $paid ? max(0, $total - $refundedtotal) : 0;

    $daily = $DB->get_record('local_moderncommerce_report_daily', [
        'reportdate' => $reportdate,
        'currency' => $currency,
    ]);
    if ($daily) {
        $daily->orders = (int)$daily->orders + 1;
        $daily->paidorders = (int)$daily->paidorders + ($paid ? 1 : 0);
        $daily->refunds = (int)$daily->refunds + ($refunded ? 1 : 0);
        $daily->gross = round((float)$daily->gross + $grossincrement, 6);
        $daily->discount = round((float)$daily->discount + $discountincrement, 6);
        $daily->tax = round((float)$daily->tax + $taxincrement, 6);
        $daily->net = round((float)$daily->net + $netincrement, 6);
        $daily->timemodified = $now;
        $DB->update_record('local_moderncommerce_report_daily', $daily);
    } else {
        $DB->insert_record('local_moderncommerce_report_daily', (object)[
            'reportdate' => $reportdate,
            'currency' => $currency,
            'orders' => 1,
            'paidorders' => $paid ? 1 : 0,
            'refunds' => $refunded ? 1 : 0,
            'gross' => $grossincrement,
            'discount' => $discountincrement,
            'tax' => $taxincrement,
            'net' => $netincrement,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    if ($paid) {
        $productreport = $DB->get_record('local_moderncommerce_report_products', [
            'reportdate' => $reportdate,
            'productid' => $product->id,
            'currency' => $currency,
        ]);
        if ($productreport) {
            $productreport->quantity = round((float)$productreport->quantity + $quantity, 6);
            $productreport->gross = round((float)$productreport->gross + $subtotal, 6);
            $productreport->discount = round((float)$productreport->discount + $discount, 6);
            $productreport->net = round((float)$productreport->net + $netincrement, 6);
            $productreport->timemodified = $now;
            $DB->update_record('local_moderncommerce_report_products', $productreport);
        } else {
            $DB->insert_record('local_moderncommerce_report_products', (object)[
                'reportdate' => $reportdate,
                'productid' => $product->id,
                'currency' => $currency,
                'quantity' => $quantity,
                'gross' => $subtotal,
                'discount' => $discount,
                'net' => $netincrement,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    $gatewayreport = $DB->get_record('local_moderncommerce_report_gateways', [
        'reportdate' => $reportdate,
        'gateway' => $gateway,
        'currency' => $currency,
    ]);
    if ($gatewayreport) {
        $gatewayreport->attempts = (int)$gatewayreport->attempts + 1;
        $gatewayreport->successful = (int)$gatewayreport->successful + ($paid ? 1 : 0);
        $gatewayreport->failed = (int)$gatewayreport->failed + ($failed ? 1 : 0);
        $gatewayreport->amount = round((float)$gatewayreport->amount + ($paid ? $total : 0), 6);
        $gatewayreport->fees = round((float)$gatewayreport->fees + ($paid ? $fees : 0), 6);
        $gatewayreport->timemodified = $now;
        $DB->update_record('local_moderncommerce_report_gateways', $gatewayreport);
    } else {
        $DB->insert_record('local_moderncommerce_report_gateways', (object)[
            'reportdate' => $reportdate,
            'gateway' => $gateway,
            'currency' => $currency,
            'attempts' => 1,
            'successful' => $paid ? 1 : 0,
            'failed' => $failed ? 1 : 0,
            'amount' => $paid ? $total : 0,
            'fees' => $paid ? $fees : 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}

/**
 * Seed a completed order and related commerce records.
 *
 * @param int $userid User ID.
 * @param stdClass $product Product summary.
 * @param stdClass $currency Currency config.
 * @param int $now Current time.
 * @return string Order number.
 */
function local_moderncommerce_seed_order(int $userid, stdClass $product, stdClass $currency, int $now): string {
    global $DB;

    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $ordernumber = 'MC-DEMO-ORDER-001';
    $total = $product->amount;

    $orderid = local_moderncommerce_seed_upsert('local_moderncommerce_orders', ['ordernumber' => $ordernumber], [
        'userid' => $userid,
        'ordernumber' => $ordernumber,
        'ordertype' => 'sample',
        'status' => 'completed',
        'subtotal' => $total,
        'discount' => 0,
        'tax' => 0,
        'fees' => 0,
        'total' => $total,
        'refundedtotal' => 0,
        'currency' => $currency->currency,
        'currencysymbol' => $currency->symbol,
        'currencyposition' => $currency->position,
        'decimalplaces' => $currency->decimals,
        'exchangerate' => 1,
        'couponcode' => null,
        'customeremail' => $user->email,
        'ipaddress' => null,
        'useragent' => 'Modern Commerce seed script',
        'referrer' => null,
        'notes' => 'Sample completed order generated by seed_sample_data.php.',
        'adminnotes' => 'Safe to delete with --reset.',
        'createdby' => $userid,
        'modifiedby' => $userid,
        'timecreated' => $now,
        'timemodified' => $now,
        'timepaid' => $now,
        'timecompleted' => $now,
        'timerefunded' => null,
    ]);

    $orderitemid = local_moderncommerce_seed_upsert('local_moderncommerce_order_items', [
        'orderid' => $orderid,
        'sku' => $product->sku,
    ], [
        'orderid' => $orderid,
        'productid' => $product->id,
        'priceid' => local_moderncommerce_seed_priceid($product->id),
        'courseid' => $product->courseid,
        'itemtype' => 'course',
        'itemname' => $product->name,
        'sku' => $product->sku,
        'unitprice' => $total,
        'quantity' => 1,
        'subtotal' => $total,
        'discount' => 0,
        'tax' => 0,
        'total' => $total,
        'currency' => $currency->currency,
        'enrolduration' => null,
        'timecreated' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_order_addresses', [
        'orderid' => $orderid,
        'addresstype' => 'billing',
    ], [
        'orderid' => $orderid,
        'addresstype' => 'billing',
        'firstname' => $user->firstname,
        'lastname' => $user->lastname,
        'company' => 'Modern Commerce Demo',
        'email' => $user->email,
        'phone' => null,
        'address1' => 'Demo address line 1',
        'address2' => null,
        'city' => 'Demo City',
        'state' => 'Demo State',
        'country' => 'US',
        'postcode' => '10001',
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_order_status_history', [
        'orderid' => $orderid,
        'newstatus' => 'completed',
        'source' => 'sample',
    ], [
        'orderid' => $orderid,
        'oldstatus' => 'pending',
        'newstatus' => 'completed',
        'actoruserid' => $userid,
        'source' => 'sample',
        'note' => 'Seeded sample order lifecycle.',
        'timecreated' => $now,
    ]);

    $attemptid = local_moderncommerce_seed_upsert('local_moderncommerce_payment_attempts', [
        'gateway' => 'manual',
        'reference' => 'MC-DEMO-PAY-001',
    ], [
        'orderid' => $orderid,
        'gateway' => 'manual',
        'reference' => 'MC-DEMO-PAY-001',
        'amount' => $total,
        'currency' => $currency->currency,
        'status' => 'success',
        'idempotencykey' => hash('sha256', 'MC-DEMO-PAY-001'),
        'gatewaytransactionid' => 'MC-DEMO-TXN-001',
        'redirecturl' => null,
        'errorcode' => null,
        'errormessage' => null,
        'timecreated' => $now,
        'timemodified' => $now,
        'timecompleted' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_payment_events', [
        'dedupekey' => hash('sha256', 'manual|payment.success|MC-DEMO-PAY-001'),
    ], [
        'orderid' => $orderid,
        'attemptid' => $attemptid,
        'gateway' => 'manual',
        'dedupekey' => hash('sha256', 'manual|payment.success|MC-DEMO-PAY-001'),
        'eventtype' => 'payment.success',
        'gatewayeventid' => 'MC-DEMO-EVENT-001',
        'reference' => 'MC-DEMO-PAY-001',
        'transactionid' => 'MC-DEMO-TXN-001',
        'amount' => $total,
        'currency' => $currency->currency,
        'status' => 'processed',
        'verified' => 1,
        'payloadhash' => hash('sha256', 'moderncommerce-demo-payment'),
        'rawpayload' => json_encode(['sample' => true, 'reference' => 'MC-DEMO-PAY-001']),
        'processed' => 1,
        'processingerror' => null,
        'timecreated' => $now,
        'timeprocessed' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_order_operational', ['orderid' => $orderid], [
        'orderid' => $orderid,
        'cartid' => null,
        'createdvia' => 'seed',
        'checkoutsessionid' => 'MC-DEMO-CHECKOUT-001',
        'cartchecksum' => hash('sha256', 'moderncommerce-demo-cart'),
        'pricesincludetax' => 0,
        'couponusagerecorded' => 0,
        'inventoryreserved' => 0,
        'inventoryreduced' => 0,
        'receiptqueued' => 0,
        'receiptsent' => 1,
        'paymentstatus' => 'paid',
        'fulfillmentstatus' => 'fulfilled',
        'lastpaymentattemptid' => $attemptid,
        'lastgatewayeventid' => 'MC-DEMO-EVENT-001',
        'timepaid' => $now,
        'timefulfilled' => $now,
        'timecancelled' => null,
        'timemodified' => $now,
    ]);

    $invoiceid = local_moderncommerce_seed_upsert('local_moderncommerce_invoices', [
        'invoicenumber' => 'MC-DEMO-INV-001',
    ], [
        'orderid' => $orderid,
        'userid' => $userid,
        'invoicenumber' => 'MC-DEMO-INV-001',
        'status' => 'paid',
        'subtotal' => $total,
        'tax' => 0,
        'total' => $total,
        'currency' => $currency->currency,
        'duedate' => $now + (14 * DAYSECS),
        'issuedat' => $now,
        'paidat' => $now,
        'filepath' => null,
        'notes' => 'Sample invoice.',
        'terms' => 'Demo data only.',
        'createdby' => $userid,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_invoice_items', [
        'invoiceid' => $invoiceid,
        'orderitemid' => $orderitemid,
    ], [
        'invoiceid' => $invoiceid,
        'orderitemid' => $orderitemid,
        'description' => $product->name,
        'quantity' => 1,
        'unitprice' => $total,
        'total' => $total,
        'timecreated' => $now,
    ]);

    $fulfillmentid = local_moderncommerce_seed_upsert('local_moderncommerce_fulfillments', ['orderid' => $orderid], [
        'orderid' => $orderid,
        'userid' => $userid,
        'status' => 'completed',
        'source' => 'sample',
        'timecreated' => $now,
        'timemodified' => $now,
        'timecompleted' => $now,
    ]);

    $fulfillmentitemid = local_moderncommerce_seed_upsert('local_moderncommerce_fulfillment_items', [
        'orderitemid' => $orderitemid,
        'courseid' => $product->courseid,
    ], [
        'fulfillmentid' => $fulfillmentid,
        'orderitemid' => $orderitemid,
        'productid' => $product->id,
        'courseid' => $product->courseid,
        'itemtype' => 'course',
        'enrolid' => null,
        'status' => 'completed',
        'timestart' => $now,
        'timeend' => null,
        'timefulfilled' => $now,
        'timerevoked' => null,
    ]);

    $entitlementid = local_moderncommerce_seed_upsert('local_moderncommerce_entitlements', [
        'sourcekey' => 'sample:orderitem:' . $orderitemid . ':course:' . $product->courseid,
    ], [
        'sourcekey' => 'sample:orderitem:' . $orderitemid . ':course:' . $product->courseid,
        'userid' => $userid,
        'productid' => $product->id,
        'courseid' => $product->courseid,
        'orderid' => $orderid,
        'orderitemid' => $orderitemid,
        'fulfillmentid' => $fulfillmentid,
        'fulfillmentitemid' => $fulfillmentitemid,
        'enrollkeyid' => null,
        'userenrolmentid' => null,
        'enrolinstanceid' => null,
        'entitlementtype' => 'course_access',
        'source' => 'sample',
        'status' => 'active',
        'grantreason' => 'sample_order',
        'timestart' => $now,
        'timeend' => null,
        'timegranted' => $now,
        'timerevoked' => null,
        'timeexpired' => null,
        'revokereason' => null,
        'metadata' => json_encode(['moodle_enrolment_created' => false, 'reason' => 'enrol/moderncommerce not installed']),
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_entitlement_events', [
        'eventuuid' => hash('sha256', 'MC-DEMO-ENTITLEMENT-GRANTED-001'),
    ], [
        'entitlementid' => $entitlementid,
        'eventuuid' => hash('sha256', 'MC-DEMO-ENTITLEMENT-GRANTED-001'),
        'eventtype' => 'granted',
        'oldstatus' => null,
        'newstatus' => 'active',
        'actoruserid' => $userid,
        'source' => 'sample',
        'reason' => 'sample_order',
        'correlationid' => $ordernumber,
        'eventdata' => json_encode(['sample' => true, 'orderid' => $orderid, 'orderitemid' => $orderitemid]),
        'timecreated' => $now,
    ]);

    local_moderncommerce_seed_reports($product, $currency->currency, $total, $now);

    return $ordernumber;
}

/**
 * Get regular price ID.
 *
 * @param int $productid Product ID.
 * @return int|null Price ID.
 */
function local_moderncommerce_seed_priceid(int $productid): ?int {
    global $DB;

    $priceid = $DB->get_field('local_moderncommerce_product_prices', 'id', [
        'productid' => $productid,
        'pricetype' => 'regular',
    ]);

    return $priceid ? (int)$priceid : null;
}

/**
 * Seed report snapshots.
 *
 * @param stdClass $product Product summary.
 * @param string $currency Currency.
 * @param float $amount Amount.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_reports(stdClass $product, string $currency, float $amount, int $now): void {
    $reportdate = strtotime(date('Y-m-d 00:00:00', $now));

    local_moderncommerce_seed_upsert('local_moderncommerce_report_daily', [
        'reportdate' => $reportdate,
        'currency' => $currency,
    ], [
        'reportdate' => $reportdate,
        'currency' => $currency,
        'orders' => 1,
        'paidorders' => 1,
        'refunds' => 0,
        'gross' => $amount,
        'discount' => 0,
        'tax' => 0,
        'net' => $amount,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_report_products', [
        'reportdate' => $reportdate,
        'productid' => $product->id,
        'currency' => $currency,
    ], [
        'reportdate' => $reportdate,
        'productid' => $product->id,
        'currency' => $currency,
        'quantity' => 1,
        'gross' => $amount,
        'discount' => 0,
        'net' => $amount,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);

    local_moderncommerce_seed_upsert('local_moderncommerce_report_gateways', [
        'reportdate' => $reportdate,
        'gateway' => 'manual',
        'currency' => $currency,
    ], [
        'reportdate' => $reportdate,
        'gateway' => 'manual',
        'currency' => $currency,
        'attempts' => 1,
        'successful' => 1,
        'failed' => 0,
        'amount' => $amount,
        'fees' => 0,
        'timecreated' => $now,
        'timemodified' => $now,
    ]);
}

/**
 * Seed audit log.
 *
 * @param array $payload Payload.
 * @param int $now Current time.
 */
function local_moderncommerce_seed_audit_log(array $payload, int $now): void {
    $data = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $hash = hash('sha256', $data ?: '');

    local_moderncommerce_seed_upsert('local_moderncommerce_audit_log', [
        'eventuuid' => hash('sha256', 'moderncommerce-demo-seed'),
    ], [
        'eventuuid' => hash('sha256', 'moderncommerce-demo-seed'),
        'correlationid' => 'moderncommerce-demo-seed',
        'actoruserid' => null,
        'subjectuserid' => null,
        'action' => 'sample_seeded',
        'entitytype' => 'sample_seed',
        'entityid' => 0,
        'source' => 'cli',
        'result' => 'success',
        'severity' => 'info',
        'ipaddress' => null,
        'useragent' => 'cli',
        'olddata' => null,
        'newdata' => $data,
        'oldhash' => null,
        'newhash' => $hash,
        'previoushash' => null,
        'eventhash' => $hash,
        'redacted' => 1,
        'timecreated' => $now,
    ]);
}

/**
 * Insert or update a record by unique conditions.
 *
 * @param string $table Table name.
 * @param array $conditions Unique conditions.
 * @param array $data Record data.
 * @return int Record ID.
 */
function local_moderncommerce_seed_upsert(string $table, array $conditions, array $data): int {
    global $DB;

    $record = (object)$data;
    $existing = $DB->get_record($table, $conditions, '*', IGNORE_MULTIPLE);
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record($table, $record);
        return (int)$existing->id;
    }

    return (int)$DB->insert_record($table, $record);
}
