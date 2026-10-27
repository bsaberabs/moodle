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
 * Tests for the category tiles widget migration off Moodle course categories.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_moderncommerce\persistent\widget;
use local_moderncommerce\services\category_service;
use local_moderncommerce\storefront\resolver\categories_resolver;

/**
 * Verifies saved widget items are repointed from course categories to product categories.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(category_service::class)]
final class category_widget_migration_test extends advanced_testcase {
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
            'slug' => strtolower(str_replace([' ', '&'], '-', $name)) . '-' . random_string(4),
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
     * Create a categories widget with the given stored items.
     *
     * @param array $items Stored widget items.
     * @return int Widget id.
     */
    private function make_widget(array $items): int {
        $instance = new widget(0, (object) [
            'type' => 'categories',
            'zone' => 'home_featured',
            'pagetype' => 'home',
            'sortorder' => 0,
            'enabled' => 1,
            'settings' => json_encode(['items' => $items, 'showcount' => true]),
        ]);
        $instance->create();

        return (int) $instance->get('id');
    }

    /**
     * Read a widget's stored category ids back.
     *
     * @param int $widgetid Widget id.
     * @return int[]
     */
    private function stored_ids(int $widgetid): array {
        global $DB;

        $settings = json_decode((string) $DB->get_field('local_moderncommerce_widget', 'settings', [
            'id' => $widgetid,
        ]), true);

        return array_map(
            static fn(array $item): int => (int) $item['categoryid'],
            $settings['items'] ?? []
        );
    }

    /**
     * A course category is repointed to the product category sharing its name.
     */
    public function test_course_category_is_remapped_by_name(): void {
        $coursecategory = $this->getDataGenerator()->create_category(['name' => 'Business']);
        $productcategory = $this->make_category('Business');

        $widgetid = $this->make_widget([
            ['categoryid' => (string) $coursecategory->id, 'icon' => 'briefcase'],
        ]);

        $this->assertSame(1, category_service::migrate_widget_items_to_product_categories());
        $this->assertSame([$productcategory], $this->stored_ids($widgetid));
    }

    /**
     * Name matching ignores case and surrounding whitespace.
     */
    public function test_name_match_is_case_and_whitespace_insensitive(): void {
        $coursecategory = $this->getDataGenerator()->create_category(['name' => '  BUSINESS  ']);
        $productcategory = $this->make_category('business');

        $widgetid = $this->make_widget([
            ['categoryid' => (string) $coursecategory->id, 'icon' => 'briefcase'],
        ]);

        category_service::migrate_widget_items_to_product_categories();
        $this->assertSame([$productcategory], $this->stored_ids($widgetid));
    }

    /**
     * The blocker: unmatchable ids are dropped so the widget falls back instead of showing nothing.
     */
    public function test_unmatched_ids_are_dropped_so_the_widget_falls_back(): void {
        $coursecategory = $this->getDataGenerator()->create_category(['name' => 'Web Development']);
        $productcategory = $this->make_category('Demo Courses');

        $widgetid = $this->make_widget([
            ['categoryid' => (string) $coursecategory->id, 'icon' => 'code'],
        ]);

        category_service::migrate_widget_items_to_product_categories();
        $this->assertSame([], $this->stored_ids($widgetid), 'Unmatchable ids must not survive.');

        // Empty items means the resolver falls back to the visible product categories.
        $instance = new widget($widgetid);
        $payload = (new categories_resolver())->resolve($instance, []);
        $this->assertSame(
            [$productcategory],
            array_map(static fn(array $t): int => $t['id'], $payload['categories']),
            'An emptied item list must fall back to visible product categories, not render blank.'
        );
    }

    /**
     * Ids that are already product categories are left exactly as they are.
     */
    public function test_product_category_ids_are_left_untouched(): void {
        $first = $this->make_category('Demo Courses');
        $second = $this->make_category('Demo Bundles');

        $widgetid = $this->make_widget([
            ['categoryid' => (string) $first, 'icon' => 'mortarboard'],
            ['categoryid' => (string) $second, 'icon' => 'box-seam'],
        ]);

        category_service::migrate_widget_items_to_product_categories();
        $this->assertSame([$first, $second], $this->stored_ids($widgetid));
    }

    /**
     * Running the migration twice changes nothing the second time.
     */
    public function test_migration_is_idempotent(): void {
        $coursecategory = $this->getDataGenerator()->create_category(['name' => 'Business']);
        $productcategory = $this->make_category('Business');

        $widgetid = $this->make_widget([
            ['categoryid' => (string) $coursecategory->id, 'icon' => 'briefcase'],
        ]);

        category_service::migrate_widget_items_to_product_categories();
        $afterfirst = $this->stored_ids($widgetid);
        category_service::migrate_widget_items_to_product_categories();

        $this->assertSame($afterfirst, $this->stored_ids($widgetid));
        $this->assertSame([$productcategory], $this->stored_ids($widgetid));
    }

    /**
     * A mixed list keeps what it can and preserves the admin's ordering and icons.
     */
    public function test_mixed_list_keeps_matches_and_preserves_order(): void {
        $productcategory = $this->make_category('Business');
        $keep = $this->make_category('Demo Bundles');
        $matchable = $this->getDataGenerator()->create_category(['name' => 'Business']);
        $unmatchable = $this->getDataGenerator()->create_category(['name' => 'Nothing Alike']);

        $widgetid = $this->make_widget([
            ['categoryid' => (string) $keep, 'icon' => 'box-seam'],
            ['categoryid' => (string) $unmatchable->id, 'icon' => 'question'],
            ['categoryid' => (string) $matchable->id, 'icon' => 'briefcase'],
        ]);

        category_service::migrate_widget_items_to_product_categories();

        $this->assertSame([$keep, $productcategory], $this->stored_ids($widgetid));
    }

    /**
     * Widgets with no stored items are skipped rather than rewritten.
     */
    public function test_widget_without_items_is_left_alone(): void {
        global $DB;

        $this->make_category('Demo Courses');
        $widgetid = $this->make_widget([]);
        $before = $DB->get_field('local_moderncommerce_widget', 'settings', ['id' => $widgetid]);

        $this->assertSame(0, category_service::migrate_widget_items_to_product_categories());
        $this->assertSame($before, $DB->get_field('local_moderncommerce_widget', 'settings', ['id' => $widgetid]));
    }

    /**
     * Malformed stored items are discarded without breaking the migration.
     */
    public function test_malformed_items_are_discarded(): void {
        $productcategory = $this->make_category('Demo Courses');

        $widgetid = $this->make_widget([
            ['categoryid' => '0', 'icon' => 'x'],
            ['icon' => 'no-category-key'],
            ['categoryid' => (string) $productcategory, 'icon' => 'mortarboard'],
        ]);

        category_service::migrate_widget_items_to_product_categories();

        $this->assertSame([$productcategory], $this->stored_ids($widgetid));
    }
}
