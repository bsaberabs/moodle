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
 * Tests for the storefront category tiles widget resolver.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_moderncommerce\persistent\widget;
use local_moderncommerce\storefront\resolver\categories_resolver;

/**
 * Verifies the tiles resolve Modern Commerce product categories, not Moodle course categories.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(categories_resolver::class)]
final class categories_resolver_test extends advanced_testcase {
    /**
     * Reset the database for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Create a product category.
     *
     * @param string $name Category name.
     * @param int $visible Visibility flag.
     * @param int $sortorder Display order.
     * @return int Category id.
     */
    private function make_category(string $name, int $visible = 1, int $sortorder = 10): int {
        global $DB;

        return (int) $DB->insert_record('local_moderncommerce_product_categories', (object) [
            'parentid' => null,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . random_string(4),
            'description' => null,
            'path' => null,
            'depth' => 0,
            'visible' => $visible,
            'sortorder' => $sortorder,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Create a sellable product mapped to a category.
     *
     * @param int $categoryid Category to map to.
     * @return int Product id.
     */
    private function make_mapped_product(int $categoryid): int {
        global $DB;

        $suffix = random_string(6);
        $productid = (int) $DB->insert_record('local_moderncommerce_products', (object) [
            'producttype' => 'course',
            'name' => 'Product ' . $suffix,
            'slug' => 'product-' . $suffix,
            'sku' => 'SKU-' . $suffix,
            'status' => 'active',
            'visible' => 1,
            'featured' => 0,
            'taxable' => 1,
            'currentenrollment' => 0,
            'displayorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('local_moderncommerce_product_prices', (object) [
            'productid' => $productid,
            'pricetype' => 'regular',
            'amount' => 49.00,
            'enabled' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('local_moderncommerce_product_category_map', (object) [
            'productid' => $productid,
            'categoryid' => $categoryid,
            'isprimary' => 1,
            'timecreated' => time(),
        ]);

        return $productid;
    }

    /**
     * Build a categories widget instance.
     *
     * @param array $settings Widget settings.
     * @return widget
     */
    private function make_widget(array $settings): widget {
        $instance = new widget(0, (object) [
            'type' => 'categories',
            'zone' => 'home_featured',
            'pagetype' => 'home',
            'sortorder' => 0,
            'enabled' => 1,
            'settings' => json_encode($settings),
        ]);
        $instance->create();

        return $instance;
    }

    /**
     * With no curated selection the widget falls back to every visible category, in saved order.
     */
    public function test_fallback_lists_visible_product_categories_in_order(): void {
        $second = $this->make_category('Bundles', 1, 20);
        $first = $this->make_category('Courses', 1, 10);
        $this->make_category('Archive', 0, 5);

        $payload = (new categories_resolver())->resolve($this->make_widget([]), []);

        $this->assertSame(
            [$first, $second],
            array_map(static fn(array $t): int => $t['id'], $payload['categories']),
            'The fallback must list visible product categories in the admin display order.'
        );
    }

    /**
     * The regression: stale Moodle course category ids must not silently produce tiles.
     */
    public function test_unknown_category_ids_resolve_to_no_tiles(): void {
        $this->make_category('Courses');

        // Ids that exist as Moodle course categories but not as product categories.
        $payload = (new categories_resolver())->resolve($this->make_widget([
            'items' => [
                ['categoryid' => '99001', 'icon' => 'collection'],
                ['categoryid' => '99002', 'icon' => 'collection'],
            ],
        ]), []);

        $this->assertSame([], $payload['categories']);
    }

    /**
     * A curated selection is honoured in the admin's chosen order, skipping hidden categories.
     */
    public function test_curated_items_are_resolved_in_the_given_order(): void {
        $courses = $this->make_category('Courses', 1, 10);
        $bundles = $this->make_category('Bundles', 1, 20);
        $hidden = $this->make_category('Hidden', 0, 30);

        $payload = (new categories_resolver())->resolve($this->make_widget([
            'items' => [
                ['categoryid' => (string) $bundles, 'icon' => 'box-seam'],
                ['categoryid' => (string) $hidden, 'icon' => 'eye-slash'],
                ['categoryid' => (string) $courses, 'icon' => 'mortarboard'],
            ],
        ]), []);

        $this->assertSame(
            [$bundles, $courses],
            array_map(static fn(array $t): int => $t['id'], $payload['categories']),
            'Curated order must be preserved and hidden categories dropped.'
        );
        $this->assertSame('box-seam', $payload['categories'][0]['icon']);
    }

    /**
     * Tile counts report sellable products and match the canonical category resolution.
     */
    public function test_tile_count_reports_sellable_products(): void {
        $categoryid = $this->make_category('Courses');
        $this->make_mapped_product($categoryid);
        $this->make_mapped_product($categoryid);

        $payload = (new categories_resolver())->resolve($this->make_widget(['showcount' => true]), []);

        $this->assertSame(2, $payload['categories'][0]['count']);
    }

    /**
     * Turning the count off zeroes it rather than leaking a stale number.
     */
    public function test_counts_can_be_switched_off(): void {
        $categoryid = $this->make_category('Courses');
        $this->make_mapped_product($categoryid);

        $payload = (new categories_resolver())->resolve($this->make_widget(['showcount' => false]), []);

        $this->assertSame(0, $payload['categories'][0]['count']);
    }

    /**
     * Each tile deep-links into the catalog filtered by that product category.
     */
    public function test_tile_links_to_the_filtered_catalog(): void {
        $categoryid = $this->make_category('Courses');

        $payload = (new categories_resolver())->resolve($this->make_widget([]), []);

        $query = [];
        parse_str((string) parse_url($payload['categories'][0]['url'], PHP_URL_QUERY), $query);
        $this->assertSame($categoryid, (int) ($query['categoryid'] ?? 0));
        $this->assertStringContainsString('/local/moderncommerce/index.php', $payload['categories'][0]['url']);
    }

    /**
     * Names reach React decoded, so the component's own escaping cannot double-encode them.
     */
    public function test_tile_name_is_decoded_for_react(): void {
        $this->make_category('Business & Finance');

        $payload = (new categories_resolver())->resolve($this->make_widget([]), []);

        $this->assertSame('Business & Finance', $payload['categories'][0]['name']);
    }

    /**
     * With no categories at all the widget degrades to an empty tile list.
     */
    public function test_no_categories_yields_no_tiles(): void {
        $payload = (new categories_resolver())->resolve($this->make_widget([]), []);

        $this->assertSame([], $payload['categories']);
    }
}
