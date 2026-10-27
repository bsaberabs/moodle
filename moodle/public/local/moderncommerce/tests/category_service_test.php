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
 * Tests for Modern Commerce product category resolution and counting.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_moderncommerce\services\category_service;

/**
 * Verifies the storefront reads product categories consistently.
 *
 * The catalog shows a product under exactly ONE category, so the tile counts must be derived from
 * that same canonical choice. Counting raw mapping rows would advertise products under a category
 * whose catalog page then returns nothing - the defect these tests lock down.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(category_service::class)]
final class category_service_test extends advanced_testcase {
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
     * Create a sellable product, optionally with an enabled regular price.
     *
     * @param string $producttype Product type.
     * @param string $status Product status.
     * @param int $visible Visibility flag.
     * @param bool $withprice Whether to attach an enabled regular price.
     * @return int Product id.
     */
    private function make_product(
        string $producttype = 'course',
        string $status = 'active',
        int $visible = 1,
        bool $withprice = true
    ): int {
        global $DB;

        $suffix = random_string(6);
        $productid = (int) $DB->insert_record('local_moderncommerce_products', (object) [
            'producttype' => $producttype,
            'name' => 'Product ' . $suffix,
            'slug' => 'product-' . $suffix,
            'sku' => 'SKU-' . $suffix,
            'status' => $status,
            'visible' => $visible,
            'featured' => 0,
            'taxable' => 1,
            'currentenrollment' => 0,
            'displayorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        if ($withprice) {
            $DB->insert_record('local_moderncommerce_product_prices', (object) [
                'productid' => $productid,
                'pricetype' => 'regular',
                'amount' => 49.00,
                'enabled' => 1,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }

        return $productid;
    }

    /**
     * Map a product to a category.
     *
     * @param int $productid Product id.
     * @param int $categoryid Category id.
     * @param int $isprimary Primary flag.
     * @return void
     */
    private function map(int $productid, int $categoryid, int $isprimary = 1): void {
        global $DB;

        $DB->insert_record('local_moderncommerce_product_category_map', (object) [
            'productid' => $productid,
            'categoryid' => $categoryid,
            'isprimary' => $isprimary,
            'timecreated' => time(),
        ]);
    }

    /**
     * Only visible categories are returned, in the admin's saved display order.
     */
    public function test_get_visible_categories_respects_visibility_and_order(): void {
        $last = $this->make_category('Zebra', 1, 30);
        $first = $this->make_category('Apple', 1, 10);
        $middle = $this->make_category('Mango', 1, 20);
        $this->make_category('Hidden', 0, 5);

        $categories = category_service::get_visible_categories();

        $this->assertCount(3, $categories, 'Hidden categories must not reach the storefront.');
        $this->assertSame(
            [$first, $middle, $last],
            array_map(static fn(array $c): int => $c['id'], $categories),
            'Categories must keep the drag-and-drop sortorder, not fall back to alphabetical.'
        );
    }

    /**
     * A product mapped to a hidden category is treated as uncategorised.
     */
    public function test_hidden_category_is_not_resolved_for_a_product(): void {
        $hidden = $this->make_category('Hidden', 0);
        $productid = $this->make_product();
        $this->map($productid, $hidden);

        $this->assertSame([], category_service::get_primary_category_map([$productid]));
        $this->assertArrayNotHasKey($hidden, category_service::count_visible_products());
    }

    /**
     * Legacy rows can flag several mappings primary; resolution must still be deterministic.
     */
    public function test_primary_map_breaks_ties_by_sortorder(): void {
        $second = $this->make_category('Second', 1, 20);
        $first = $this->make_category('First', 1, 10);
        $productid = $this->make_product();

        // Both flagged primary, inserted out of order: the lower sortorder must win.
        $this->map($productid, $second);
        $this->map($productid, $first);

        $map = category_service::get_primary_category_map([$productid]);
        $this->assertSame($first, $map[$productid]['id']);
    }

    /**
     * An explicit primary beats a non-primary mapping regardless of order.
     */
    public function test_primary_flag_wins_over_sortorder(): void {
        $firstbyorder = $this->make_category('First', 1, 10);
        $flaggedprimary = $this->make_category('Second', 1, 20);
        $productid = $this->make_product();

        $this->map($productid, $firstbyorder, 0);
        $this->map($productid, $flaggedprimary, 1);

        $map = category_service::get_primary_category_map([$productid]);
        $this->assertSame($flaggedprimary, $map[$productid]['id']);
    }

    /**
     * The regression: counts must follow the canonical winner, never raw mapping rows.
     *
     * A product mapped to two categories appears in the catalog under one of them. Counting rows
     * would credit both, so the losing category would advertise a product it never shows.
     */
    public function test_counts_follow_the_canonical_category_not_raw_mappings(): void {
        $winner = $this->make_category('Winner', 1, 10);
        $loser = $this->make_category('Loser', 1, 20);

        for ($i = 0; $i < 3; $i++) {
            $productid = $this->make_product();
            $this->map($productid, $winner);
            $this->map($productid, $loser);
        }

        $counts = category_service::count_visible_products();

        $this->assertSame(3, $counts[$winner], 'The resolved category must count its products.');
        $this->assertSame(
            0,
            $counts[$loser],
            'A category that never wins the tie-break must not advertise products it cannot show.'
        );
    }

    /**
     * Counts only include what the catalog can actually sell.
     */
    public function test_counts_exclude_unsellable_products(): void {
        $categoryid = $this->make_category('Catalogue');

        $this->map($this->make_product('course', 'active', 1, true), $categoryid);
        $this->map($this->make_product('course', 'draft', 1, true), $categoryid);
        $this->map($this->make_product('course', 'active', 0, true), $categoryid);
        // A course with no enabled regular price is not purchasable.
        $this->map($this->make_product('course', 'active', 1, false), $categoryid);
        // Types the catalog does not render.
        $this->map($this->make_product('subscription', 'active', 1, true), $categoryid);
        $this->map($this->make_product('digital', 'active', 1, true), $categoryid);

        $this->assertSame(1, category_service::count_visible_products()[$categoryid]);
    }

    /**
     * Bundles and programs count without needing a price row of their own.
     */
    public function test_bundles_and_programs_are_counted(): void {
        $categoryid = $this->make_category('Bundles');
        $this->map($this->make_product('bundle', 'active', 1, false), $categoryid);
        $this->map($this->make_product('program', 'active', 1, false), $categoryid);

        $this->assertSame(2, category_service::count_visible_products()[$categoryid]);
    }

    /**
     * Visible categories with nothing in them report zero rather than being absent.
     */
    public function test_empty_visible_category_reports_zero(): void {
        $categoryid = $this->make_category('Empty');

        $counts = category_service::count_visible_products();
        $this->assertArrayHasKey($categoryid, $counts);
        $this->assertSame(0, $counts[$categoryid]);
    }

    /**
     * Assigning a category replaces any previous assignment rather than accumulating rows.
     */
    public function test_set_primary_category_replaces_existing_assignment(): void {
        global $DB;

        $old = $this->make_category('Old');
        $new = $this->make_category('New');
        $productid = $this->make_product();

        category_service::set_primary_category($productid, $old);
        category_service::set_primary_category($productid, $new);

        $rows = $DB->get_records('local_moderncommerce_product_category_map', ['productid' => $productid]);
        $this->assertCount(1, $rows, 'Re-assigning must not leave the previous mapping behind.');
        $this->assertSame($new, category_service::get_primary_category_map([$productid])[$productid]['id']);
    }

    /**
     * Passing zero clears the product's category.
     */
    public function test_set_primary_category_with_zero_clears_the_assignment(): void {
        global $DB;

        $categoryid = $this->make_category('Temporary');
        $productid = $this->make_product();

        category_service::set_primary_category($productid, $categoryid);
        category_service::set_primary_category($productid, 0);

        $this->assertSame(0, $DB->count_records('local_moderncommerce_product_category_map', [
            'productid' => $productid,
        ]));
    }

    /**
     * An empty product list short-circuits instead of querying for everything.
     */
    public function test_primary_map_with_no_products_returns_empty(): void {
        $this->make_category('Unused');

        $this->assertSame([], category_service::get_primary_category_map([]));
        $this->assertSame([], category_service::get_primary_category_map([0, -1]));
    }

    /**
     * A blank name is reported against the field, not as a flat message.
     */
    public function test_validate_category_requires_a_name(): void {
        [, $errors] = category_service::validate_category(['name' => '']);

        $this->assertArrayHasKey('name', $errors);
        $this->assertSame(get_string('error:required', 'local_moderncommerce'), $errors['name']);
    }

    /**
     * Whitespace is not a value. PARAM_TEXT would let this through, which is
     * why the endpoint declares the name as PARAM_RAW_TRIMMED.
     */
    public function test_validate_category_rejects_a_whitespace_only_name(): void {
        [, $errors] = category_service::validate_category(['name' => '    ']);

        $this->assertArrayHasKey('name', $errors);
    }

    /**
     * Over-long values are rejected before they reach the database column.
     */
    public function test_validate_category_enforces_field_lengths(): void {
        [, $errors] = category_service::validate_category([
            'name' => str_repeat('x', 256),
            'description' => str_repeat('y', 1001),
        ]);

        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('description', $errors);
    }

    /**
     * Editing a category that has since been deleted is a form-level problem:
     * there is no field the user could retype to fix it.
     */
    public function test_validate_category_reports_a_missing_record_at_form_level(): void {
        [, $errors] = category_service::validate_category(['name' => 'Fine'], 99999);

        $this->assertArrayHasKey('_form', $errors);
        $this->assertArrayNotHasKey('name', $errors);
    }

    /**
     * Valid input yields no errors and trimmed, normalised values.
     */
    public function test_validate_category_normalises_valid_input(): void {
        [$normalised, $errors] = category_service::validate_category([
            'name' => '  Courses  ',
            'description' => '  All courses  ',
            'visible' => true,
        ]);

        $this->assertSame([], $errors);
        $this->assertSame('Courses', $normalised['name']);
        $this->assertSame('All courses', $normalised['description']);
        $this->assertTrue($normalised['visible']);
    }

    /**
     * Every problem is collected in one pass so the UI can highlight them all.
     */
    public function test_validate_category_collects_all_errors_at_once(): void {
        [, $errors] = category_service::validate_category([
            'name' => '',
            'description' => str_repeat('y', 1001),
        ], 99999);

        $this->assertCount(3, $errors);
    }
}
