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

namespace local_moderncommerce\api;


/**
 * Bundle API backed by the ModernCommerce product schema.
 *
 * Bundles and programs are stored as products:
 * - local_moderncommerce_products.producttype = bundle|program
 * - local_moderncommerce_product_prices stores the regular selling price
 * - local_moderncommerce_product_courses stores included Moodle courses
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bundle_api {
    /** @var string Bundle product type. */
    private const TYPE_BUNDLE = 'bundle';

    /** @var string Program product type. */
    private const TYPE_PROGRAM = 'program';

    /**
     * Create a new bundle/program product.
     *
     * @param object $data Bundle data.
     * @return int Product ID.
     */
    public static function create($data): int {
        global $DB, $USER;

        $now = time();
        $record = (object) [
            'producttype' => !empty($data->isprogram) ? self::TYPE_PROGRAM : self::TYPE_BUNDLE,
            'name' => $data->name,
            'slug' => self::generate_slug($data->name),
            'sku' => self::generate_sku($data->name),
            'status' => $data->status ?? 'active',
            'visible' => $data->visible ?? 1,
            'featured' => $data->featured ?? 0,
            'shortdescription' => $data->shortdescription ?? '',
            'description' => $data->description ?? '',
            'imageurl' => $data->imageurl ?? '',
            'maxenrollment' => $data->maxenrollment ?? null,
            'currentenrollment' => 0,
            'displayorder' => $data->displayorder ?? 0,
            'createdby' => $USER->id ?? null,
            'modifiedby' => $USER->id ?? null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $productid = (int) $DB->insert_record('local_moderncommerce_products', $record);
        self::save_price($productid, $data);

        if (!empty($data->courseids) && is_array($data->courseids)) {
            foreach (array_values($data->courseids) as $sortorder => $courseid) {
                self::add_course($productid, (int) $courseid, ['sortorder' => $sortorder]);
            }
        }

        return $productid;
    }

    /**
     * Get bundle/program by product ID.
     *
     * @param int $bundleid Product ID.
     * @return object|false
     */
    public static function get($bundleid) {
        global $DB;

        $sql = self::base_select_sql() . "
             WHERE p.id = :id
               AND p.producttype IN (:bundle, :program)";
        $record = $DB->get_record_sql($sql, [
            'id' => $bundleid,
            'bundle' => self::TYPE_BUNDLE,
            'program' => self::TYPE_PROGRAM,
        ]);

        return $record ? self::normalise_bundle_record($record) : false;
    }

    /**
     * Update bundle/program product.
     *
     * @param int $bundleid Product ID.
     * @param object $data Bundle data.
     * @return bool
     */
    public static function update($bundleid, $data): bool {
        global $DB, $USER;

        $product = $DB->get_record('local_moderncommerce_products', ['id' => $bundleid], '*', MUST_EXIST);
        $wasnotprogram = $product->producttype !== self::TYPE_PROGRAM;
        $isnowprogram = isset($data->isprogram) && (int) $data->isprogram === 1;

        $fieldmap = [
            'name' => 'name',
            'description' => 'description',
            'shortdescription' => 'shortdescription',
            'imageurl' => 'imageurl',
            'featured' => 'featured',
            'visible' => 'visible',
            'status' => 'status',
            'maxenrollment' => 'maxenrollment',
            'displayorder' => 'displayorder',
        ];

        foreach ($fieldmap as $source => $target) {
            if (isset($data->{$source})) {
                $product->{$target} = $data->{$source};
            }
        }

        if (isset($data->name) && trim((string) $data->name) !== trim((string) $product->name)) {
            $product->slug = self::generate_slug($data->name, $bundleid);
        }

        if (isset($data->isprogram)) {
            $product->producttype = !empty($data->isprogram) ? self::TYPE_PROGRAM : self::TYPE_BUNDLE;
        }

        $product->timemodified = time();
        $product->modifiedby = $USER->id ?? null;

        $result = $DB->update_record('local_moderncommerce_products', $product);
        self::save_price($bundleid, $data);

        if ($wasnotprogram && $isnowprogram && $result) {
            self::convert_to_program($bundleid);
        }

        return (bool) $result;
    }

    /**
     * Delete bundle/program product and direct child rows.
     *
     * @param int $bundleid Product ID.
     * @return bool
     */
    public static function delete($bundleid): bool {
        global $DB;

        $DB->delete_records('local_moderncommerce_program_progress', ['programid' => $bundleid]);
        $DB->delete_records('local_moderncommerce_product_courses', ['productid' => $bundleid]);
        $DB->delete_records('local_moderncommerce_product_prices', ['productid' => $bundleid]);
        $DB->delete_records('local_moderncommerce_product_inventory', ['productid' => $bundleid]);
        $DB->delete_records('local_moderncommerce_product_category_map', ['productid' => $bundleid]);
        $DB->delete_records('local_moderncommerce_product_tags', ['productid' => $bundleid]);
        $DB->delete_records_select(
            'local_moderncommerce_product_relations',
            'parentproductid = :id OR childproductid = :id2',
            ['id' => $bundleid, 'id2' => $bundleid]
        );

        return $DB->delete_records('local_moderncommerce_products', ['id' => $bundleid]);
    }

    /**
     * Add a Moodle course to a bundle/program product.
     *
     * @param int $bundleid Product ID.
     * @param int $courseid Course ID.
     * @param array $options Options.
     * @return int Link record ID.
     */
    public static function add_course($bundleid, $courseid, $options = []): int {
        global $DB;

        if ($courseid <= 0) {
            throw new \moodle_exception('invalidcourseid', 'error');
        }

        if (
            $DB->record_exists('local_moderncommerce_product_courses', [
            'productid' => $bundleid,
            'courseid' => $courseid,
            'relationtype' => 'included',
            ])
        ) {
            throw new \moodle_exception('coursealreadyinbundle', 'local_moderncommerce');
        }

        return (int) $DB->insert_record('local_moderncommerce_product_courses', (object) [
            'productid' => $bundleid,
            'courseid' => $courseid,
            'relationtype' => 'included',
            'sortorder' => $options['sortorder'] ?? self::get_next_sortorder($bundleid),
            'required' => $options['required'] ?? 1,
            'timecreated' => time(),
        ]);
    }

    /**
     * Remove a Moodle course from a bundle/program product.
     *
     * @param int $bundleid Product ID.
     * @param int $courseid Course ID.
     * @return bool
     */
    public static function remove_course($bundleid, $courseid): bool {
        global $DB;

        return $DB->delete_records('local_moderncommerce_product_courses', [
            'productid' => $bundleid,
            'courseid' => $courseid,
            'relationtype' => 'included',
        ]);
    }

    /**
     * Get courses in a bundle/program.
     *
     * @param int $bundleid Product ID.
     * @return array
     */
    public static function get_courses($bundleid): array {
        global $DB;

        $sql = "SELECT pc.id,
                       pc.productid AS bundleid,
                       pc.courseid,
                       pc.sortorder,
                       pc.required,
                       pc.timecreated,
                       c.fullname,
                       c.shortname,
                       c.visible
                  FROM {local_moderncommerce_product_courses} pc
                  JOIN {course} c ON c.id = pc.courseid
                 WHERE pc.productid = :bundleid
                   AND pc.relationtype = :relationtype
              ORDER BY pc.sortorder ASC, c.fullname ASC";

        return $DB->get_records_sql($sql, [
            'bundleid' => $bundleid,
            'relationtype' => 'included',
        ]);
    }

    /**
     * Reorder courses in a bundle/program.
     *
     * @param int $bundleid Product ID.
     * @param array $courseorder Course IDs in new order.
     * @return bool
     */
    public static function reorder_courses($bundleid, $courseorder): bool {
        global $DB;

        $sortorder = 0;
        foreach ($courseorder as $courseid) {
            $DB->set_field('local_moderncommerce_product_courses', 'sortorder', $sortorder, [
                'productid' => $bundleid,
                'courseid' => (int) $courseid,
                'relationtype' => 'included',
            ]);
            $sortorder++;
        }

        return true;
    }

    /**
     * Get active selling price for a bundle/program.
     *
     * @param int $bundleid Product ID.
     * @return float
     */
    public static function get_active_price($bundleid): float {
        $price = self::get_price_record($bundleid);
        return $price ? (float) $price->amount : 0.0;
    }

    /**
     * Calculate bundle savings against included course products.
     *
     * @param int $bundleid Product ID.
     * @return array
     */
    public static function calculate_savings($bundleid): array {
        global $DB;

        $sql = "SELECT COALESCE(SUM(pr.amount), 0)
                  FROM {local_moderncommerce_product_courses} pc
                  JOIN {local_moderncommerce_product_courses} coursemap
                    ON coursemap.courseid = pc.courseid
                   AND coursemap.relationtype = 'included'
                  JOIN {local_moderncommerce_products} p
                    ON p.id = coursemap.productid
                   AND p.producttype = 'course'
             LEFT JOIN {local_moderncommerce_product_prices} pr
                    ON pr.productid = p.id
                   AND pr.pricetype = 'regular'
                   AND pr.enabled = 1
                 WHERE pc.productid = :bundleid
                   AND pc.relationtype = 'included'";

        $total = (float) $DB->get_field_sql($sql, ['bundleid' => $bundleid]);
        $bundleprice = self::get_active_price($bundleid);
        $savings = max(0, $total - $bundleprice);
        $percentage = $total > 0 ? round(($savings / $total) * 100) : 0;

        return [
            'total' => $total,
            'bundle' => $bundleprice,
            'savings' => $savings,
            'percentage' => $percentage,
        ];
    }

    /**
     * Get all bundles/programs with filters.
     *
     * @param array $filters Filters.
     * @return array
     */
    public static function get_all($filters = []): array {
        global $DB;

        $conditions = ['p.producttype IN (:bundle, :program)'];
        $params = [
            'bundle' => self::TYPE_BUNDLE,
            'program' => self::TYPE_PROGRAM,
        ];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $conditions[] = 'p.status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['visible'])) {
            $conditions[] = 'p.visible = :visible';
            $params['visible'] = (int) $filters['visible'];
        }

        if (isset($filters['featured'])) {
            $conditions[] = 'p.featured = :featured';
            $params['featured'] = (int) $filters['featured'];
        }

        if (isset($filters['isprogram']) && (int) $filters['isprogram'] >= 0) {
            $conditions[] = 'p.producttype = :programfilter';
            $params['programfilter'] = !empty($filters['isprogram']) ? self::TYPE_PROGRAM : self::TYPE_BUNDLE;
        }

        if (!empty($filters['search'])) {
            $conditions[] = '(' .
                $DB->sql_like('p.name', ':searchname', false) .
                ' OR ' . $DB->sql_like('p.shortdescription', ':searchshort', false) .
                ' OR ' . $DB->sql_like('p.description', ':searchdesc', false) .
                ')';
            $escaped = '%' . $DB->sql_like_escape($filters['search']) . '%';
            $params['searchname'] = $escaped;
            $params['searchshort'] = $escaped;
            $params['searchdesc'] = $escaped;
        }

        $sql = self::base_select_sql() . '
             WHERE ' . implode(' AND ', $conditions) . '
          ORDER BY p.timecreated DESC, p.id DESC';

        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $id => $record) {
            $records[$id] = self::normalise_bundle_record($record);
        }

        return $records;
    }

    /**
     * Get featured bundles/programs.
     *
     * @param int $limit Limit.
     * @return array
     */
    public static function get_featured($limit = 10): array {
        global $DB;

        $sql = self::base_select_sql() . "
             WHERE p.featured = 1
               AND p.visible = 1
               AND p.status = 'active'
               AND p.producttype IN (:bundle, :program)
          ORDER BY p.timecreated DESC, p.id DESC";

        $records = $DB->get_records_sql($sql, [
            'bundle' => self::TYPE_BUNDLE,
            'program' => self::TYPE_PROGRAM,
        ], 0, $limit);

        foreach ($records as $id => $record) {
            $records[$id] = self::normalise_bundle_record($record);
        }

        return $records;
    }

    /**
     * Get enrollment/sales count for bundle product.
     *
     * @param int $bundleid Product ID.
     * @return int
     */
    public static function get_enrollment_count($bundleid): int {
        global $DB;

        $sql = "SELECT COALESCE(SUM(i.quantity), 0)
                  FROM {local_moderncommerce_order_items} i
                  JOIN {local_moderncommerce_orders} o ON o.id = i.orderid
                 WHERE i.productid = :bundleid
                   AND o.status IN ('paid', 'completed')";

        return (int) $DB->get_field_sql($sql, ['bundleid' => $bundleid]);
    }

    /**
     * Convert a bundle product to program type.
     *
     * @param int $bundleid Product ID.
     * @return array
     */
    public static function convert_to_program($bundleid): array {
        global $DB;

        $DB->set_field('local_moderncommerce_products', 'producttype', self::TYPE_PROGRAM, ['id' => $bundleid]);
        $DB->set_field('local_moderncommerce_products', 'timemodified', time(), ['id' => $bundleid]);

        // Existing purchasers need a real program record immediately. This also
        // evaluates their current Moodle completions and issues any earned award.
        return \local_moderncommerce\services\program_progress_service::initialize_existing((int)$bundleid);
    }

    /**
     * Check if a user has purchased a bundle/program.
     *
     * @param int $bundleid Product ID.
     * @param int|null $userid User ID.
     * @return bool
     */
    public static function is_purchased($bundleid, $userid = null): bool {
        global $DB, $USER;

        if ($userid === null) {
            $userid = $USER->id;
        }

        if (!$userid || isguestuser($userid)) {
            return false;
        }

        return $DB->record_exists_sql("
            SELECT 1
              FROM {local_moderncommerce_orders} o
              JOIN {local_moderncommerce_order_items} i ON i.orderid = o.id
             WHERE o.userid = :userid
               AND i.productid = :bundleid
               AND o.status IN ('paid', 'completed')
        ", ['userid' => $userid, 'bundleid' => $bundleid]);
    }

    /**
     * Build base bundle select SQL.
     *
     * @return string
     */
    private static function base_select_sql(): string {
        return "SELECT p.*,
                       pr.amount AS regularprice,
                       pr.compareamount,
                       pr.startdate AS salestartdate,
                       pr.enddate AS saleenddate
                  FROM {local_moderncommerce_products} p
             LEFT JOIN {local_moderncommerce_product_prices} pr
                    ON pr.id = (
                        SELECT prmin.id
                          FROM {local_moderncommerce_product_prices} prmin
                         WHERE prmin.productid = p.id
                           AND prmin.pricetype = 'regular'
                           AND prmin.enabled = 1
                           AND prmin.id = (
                               SELECT MIN(prfirst.id)
                                 FROM {local_moderncommerce_product_prices} prfirst
                                WHERE prfirst.productid = p.id
                                  AND prfirst.pricetype = 'regular'
                                  AND prfirst.enabled = 1
                           )
                    )";
    }

    /**
     * Add legacy bundle properties expected by existing admin templates.
     *
     * @param object $record Product record.
     * @return object
     */
    private static function normalise_bundle_record(object $record): object {
        $price = $record->regularprice !== null ? (float) $record->regularprice : 0.0;
        $compareamount = $record->compareamount !== null ? (float) $record->compareamount : 0.0;
        $onsale = $compareamount > $price && $price > 0;

        $record->isprogram = $record->producttype === self::TYPE_PROGRAM ? 1 : 0;
        $record->price = $onsale ? $compareamount : $price;
        $record->saleprice = $onsale ? $price : null;
        $record->currency = \local_moderncommerce\services\pricing_service::get_currency_config()->currency;
        $record->bestseller = 0;
        $record->certificatetemplateid = null;

        return $record;
    }

    /**
     * Save regular price using product_prices.
     *
     * @param int $productid Product ID.
     * @param object $data Submitted data.
     * @return void
     */
    private static function save_price(int $productid, object $data): void {
        global $DB;

        $regular = isset($data->price) ? max(0, (float) $data->price) : 0.0;
        $sale = isset($data->saleprice) ? max(0, (float) $data->saleprice) : 0.0;
        $amount = $sale > 0 && $sale < $regular ? $sale : $regular;
        $compare = $sale > 0 && $sale < $regular ? $regular : null;

        $records = $DB->get_records('local_moderncommerce_product_prices', [
            'productid' => $productid,
            'pricetype' => 'regular',
        ], 'id ASC', '*', 0, 1);
        $record = $records ? reset($records) : false;

        $now = time();
        if (!$record) {
            $record = (object) [
                'productid' => $productid,
                'pricetype' => 'regular',
                'timecreated' => $now,
            ];
        }

        $record->amount = $amount;
        $record->compareamount = $compare;
        $record->startdate = !empty($data->salestartdate) ? (int) $data->salestartdate : null;
        $record->enddate = !empty($data->saleenddate) ? (int) $data->saleenddate : null;
        $record->enabled = 1;
        $record->timemodified = $now;

        if (!empty($record->id)) {
            $DB->update_record('local_moderncommerce_product_prices', $record);
        } else {
            $DB->insert_record('local_moderncommerce_product_prices', $record);
        }
    }

    /**
     * Get regular price record.
     *
     * @param int $productid Product ID.
     * @return object|false
     */
    private static function get_price_record(int $productid) {
        global $DB;

        $records = $DB->get_records('local_moderncommerce_product_prices', [
            'productid' => $productid,
            'pricetype' => 'regular',
            'enabled' => 1,
        ], 'id ASC', '*', 0, 1);

        return $records ? reset($records) : false;
    }

    /**
     * Generate unique product slug.
     *
     * @param string $name Product name.
     * @param int $ignoreid Product ID to ignore.
     * @return string
     */
    private static function generate_slug($name, int $ignoreid = 0): string {
        global $DB;

        $slug = strtolower(trim((string) $name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'bundle';
        }

        $originalslug = $slug;
        $counter = 1;
        while (self::slug_exists($slug, $ignoreid)) {
            $slug = $originalslug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check slug uniqueness.
     *
     * @param string $slug Slug.
     * @param int $ignoreid Product ID to ignore.
     * @return bool
     */
    private static function slug_exists(string $slug, int $ignoreid = 0): bool {
        global $DB;

        if ($ignoreid > 0) {
            return $DB->record_exists_select(
                'local_moderncommerce_products',
                'slug = :slug AND id <> :id',
                ['slug' => $slug, 'id' => $ignoreid]
            );
        }

        return $DB->record_exists('local_moderncommerce_products', ['slug' => $slug]);
    }

    /**
     * Generate unique SKU.
     *
     * @param string $name Product name.
     * @return string
     */
    private static function generate_sku(string $name): string {
        global $DB;

        $base = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($name, 0, 8)));
        if ($base === '') {
            $base = 'BUNDLE';
        }

        do {
            $sku = 'BND-' . $base . '-' . random_string(6);
        } while ($DB->record_exists('local_moderncommerce_products', ['sku' => $sku]));

        return $sku;
    }

    /**
     * Get next course sort order.
     *
     * @param int $bundleid Product ID.
     * @return int
     */
    private static function get_next_sortorder($bundleid): int {
        global $DB;

        $max = $DB->get_field_sql(
            "SELECT MAX(sortorder)
               FROM {local_moderncommerce_product_courses}
              WHERE productid = :productid
                AND relationtype = 'included'",
            ['productid' => $bundleid]
        );

        return ($max !== false && $max !== null) ? ((int) $max + 1) : 0;
    }
}
