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

namespace local_moderncommerce\services;


/**
 * Bundle meta service: loads and persists advanced bundle features.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bundle_meta_service {
    /** @var string */
    private const TABLE_META = 'local_moderncommerce_bundle_meta';
    /** @var string */
    private const TABLE_OUTLINE = 'local_moderncommerce_bundle_outline';
    /** @var string */
    private const TABLE_MUSTPASS = 'local_moderncommerce_bundle_mustpass';
    /** @var string */
    private const TABLE_PREREQ = 'local_moderncommerce_bundle_prereq';
    /** @var string */
    private const TABLE_TAGS = 'local_moderncommerce_bundle_tags';
    /** @var string */
    private const TABLE_PRODUCT_COURSES = 'local_moderncommerce_product_courses';
    /** @var string */
    private const TABLE_PRODUCT_PRICES = 'local_moderncommerce_product_prices';
    /**
     * Return default meta for a bundle.
     *
     * @param int $bundleid
     * @return array
     */
    public static function get_meta(int $bundleid): array {
        global $DB, $USER;
        $defaults = [
            'bundleid' => $bundleid,
            'price' => null,
            'compare_at' => null,
            'visibility_mode' => 'Public',
            'avail_start' => null,
            'avail_end' => null,
            'badge_featured' => 0,
            'badge_bestseller' => 0,
            'badge_trending' => 0,
            'skill_level' => 'Beginner',
            'language' => 'English',
            'has_prereq' => 0,
            'auto_duration' => 1,
            'duration_hours' => 0,
            'duration_minutes' => 0,
            'auto_assessments' => 1,
            'assessments_count' => 0,
            'auto_outline' => 1,
            'pass_grade' => 70,
            'pass_policy' => 'all_must_pass',
            'bundle_cert_enabled' => 0,
            'certificate_template_id' => 0,
            'tags' => [],
        ];

        $defaults['courses_count'] = self::get_bundle_course_count($bundleid);
        $learninghours = self::get_learning_hours_summary($bundleid);
        $defaults['total_duration_minutes'] = $learninghours['totalminutes'];
        $defaults['learning_hours'] = $learninghours;
        $defaults['owned_value'] = self::get_owned_courses_value($bundleid, $USER->id ?? 0);
        $defaults['kpi_assessments'] = self::get_total_quizzes($bundleid);
        $defaults['outline_items'] = [];
        $defaults['tags'] = self::get_tags($bundleid);
        if (!self::table_exists(self::TABLE_META)) {
            return $defaults;
        }

        $record = $DB->get_record(self::TABLE_META, ['bundleid' => $bundleid]);
        if (!$record) {
            return $defaults;
        }
        // Map DB fields to UI keys.
        $meta = [
            'bundleid' => $bundleid,
            'price' => (isset($record->bundle_price) ? (float)$record->bundle_price : null),
            'compare_at' => (isset($record->compare_at) ? (float)$record->compare_at : null),
            'visibility_mode' => $record->visibility ?? 'Public',
            'avail_start' => (!empty($record->availstart) ? date('Y-m-d', (int)$record->availstart) : null),
            'avail_end' => (!empty($record->availend) ? date('Y-m-d', (int)$record->availend) : null),
            'badge_featured' => (int)($record->badge_featured ?? 0),
            'badge_bestseller' => (int)($record->badge_bestseller ?? 0),
            'badge_trending' => (int)($record->badge_trending ?? 0),
            'skill_level' => $record->skill_level ?? 'Beginner',
            'language' => $record->language ?? 'English',
            'has_prereq' => (int)($record->has_prereq ?? 0),
            'auto_duration' => (int)($record->auto_duration ?? 1),
            'duration_hours' => (int)($record->dur_hours ?? 0),
            'duration_minutes' => (int)($record->dur_mins ?? 0),
            'auto_assessments' => (int)($record->auto_assessments ?? 1),
            'assessments_count' => (int)($record->assessments_count ?? 0),
            'auto_outline' => (int)($record->auto_outline ?? 1),
            'pass_grade' => isset($record->pass_grade) ? (float)$record->pass_grade : 70,
            'pass_policy' => $record->pass_policy ?? 'all_must_pass',
            'bundle_cert_enabled' => (int)($record->bundle_cert ?? 0),
            'certificate_template_id' => (int)($record->certificate_template_id ?? 0),
        ];
        // Courses count from product-courses table.
        $meta['courses_count'] = self::get_bundle_course_count($bundleid);
        // Aggregate Moodle course custom-field learning hours for auto mode KPI.
        $learninghours = self::get_learning_hours_summary($bundleid);
        $meta['total_duration_minutes'] = $learninghours['totalminutes'];
        $meta['learning_hours'] = $learninghours;
        // Assessments are always the live number of Moodle quizzes in the included courses.
        $meta['kpi_assessments'] = self::get_total_quizzes($bundleid);
        // Owned value for current user.
        $meta['owned_value'] = self::get_owned_courses_value($bundleid, $USER->id ?? 0);
        // Attach outline for convenience.
        $meta['outline_items'] = self::get_outline($bundleid);
        // Attach tags for convenience.
        $meta['tags'] = self::get_tags($bundleid);
        return $meta;
    }

    /**
     * Count quizzes/tests across bundle courses from Moodle modules.
     *
     * @param int $bundleid
     * @return int
     */
    public static function get_total_quizzes(int $bundleid): int {

        global $DB;
        $sql = "SELECT COUNT(1)
                  FROM {" . self::TABLE_PRODUCT_COURSES . "} bc
                  JOIN {course_modules} cm ON cm.course = bc.courseid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE bc.productid = :bid
                   AND bc.relationtype = :relationtype
                   AND m.name = :modname
                   AND cm.deletioninprogress = 0";
        $sum = $DB->get_field_sql($sql, [
            'bid' => $bundleid, 'relationtype' => 'included', 'modname' => 'quiz',
        ]);
        return (int)($sum ?? 0);
    }
    /**
     * Get badge flags (featured/bestseller) for multiple bundles.
     *
     * @param int[] $bundleids
     * @return array bundleid => ['featured'=>int, 'bestseller'=>int]
     */
    public static function get_badge_flags_for_bundles(array $bundleids): array {

        global $DB;
        if (empty($bundleids)) {
            return [];
        }
        if (!self::table_exists(self::TABLE_META)) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $bundleids)));
        $ids = array_filter($ids, function ($v) {
            return $v > 0;
        });
        if (empty($ids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $recs = $DB->get_records_select(
            self::TABLE_META,
            "bundleid $insql",
            $params,
            '',
            'bundleid, badge_featured, badge_bestseller'
        );
        $out = [];
        foreach ($recs as $r) {
            $out[(int)$r->bundleid] = [
                'featured' => (int)($r->badge_featured ?? 0),
                'bestseller' => (int)($r->badge_bestseller ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Check whether an optional legacy/advanced bundle meta table exists.
     *
     * @param string $table Table name without Moodle prefix.
     * @return bool
     */
    private static function table_exists(string $table): bool {

        global $DB;
        return $DB->get_manager()->table_exists(new \xmldb_table($table));
    }

    /**
     * Count Moodle courses included in a bundle/program product.
     *
     * @param int $bundleid Product ID.
     * @return int
     */
    private static function get_bundle_course_count(int $bundleid): int {

        global $DB;
        return (int)$DB->count_records(self::TABLE_PRODUCT_COURSES, [
            'productid' => $bundleid, 'relationtype' => 'included',
        ]);
    }
    /**
     * Return tags for a bundle.
     *
     * @param int $bundleid
     * @return string[]
     */
    public static function get_tags(int $bundleid): array {

        global $DB;
        if (!self::table_exists(self::TABLE_TAGS)) {
            return [];
        }
        $records = $DB->get_records(self::TABLE_TAGS, ['bundleid' => $bundleid], 'id ASC', 'id, tag');
        if (!$records) {
            return [];
        }
        $out = [];
        foreach ($records as $r) {
            $t = trim((string)($r->tag ?? ''));
            if ($t !== '') {
                $out[] = $t;
            }
        }
        return $out;
    }

    /**
     * Add a single tag to a bundle (idempotent).
     *
     * @param int $bundleid
     * @param string $tag
     * @return bool true if present after operation
     */
    public static function add_tag(int $bundleid, string $tag): bool {

        global $DB, $USER;
        if (!self::table_exists(self::TABLE_TAGS)) {
            return false;
        }
        $t = trim((string)$tag);
        if ($t === '') {
            return false;
        }
        if (\core_text::strlen($t) > 100) {
            $t = \core_text::substr($t, 0, 100);
        }
        // Avoid duplicates (case-insensitive check where possible).
        $exists = $DB->record_exists(self::TABLE_TAGS, ['bundleid' => $bundleid, 'tag' => $t]);
        if ($exists) {
            return true;
        }
        $row = (object) [
            'bundleid' => $bundleid,
            'tag' => $t,
            'timecreated' => time(),
            'timemodified' => time(),
            'usermodified' => $USER->id ?? null,
        ];
        try {
            $DB->insert_record(self::TABLE_TAGS, $row);
            return true;
        } catch (\Throwable $e) {
            // If unique index prevents insert due to race/colllation, treat as success.
            return $DB->record_exists(self::TABLE_TAGS, ['bundleid' => $bundleid, 'tag' => $t]);
        }
    }

    /**
     * Remove a single tag from a bundle.
     *
     * @param int $bundleid
     * @param string $tag
     * @return bool true if no longer present after operation
     */
    public static function remove_tag(int $bundleid, string $tag): bool {

        global $DB;
        if (!self::table_exists(self::TABLE_TAGS)) {
            return true;
        }
        $t = trim((string)$tag);
        if ($t === '') {
            return true;
        }
        $DB->delete_records(self::TABLE_TAGS, ['bundleid' => $bundleid, 'tag' => $t]);
        return !$DB->record_exists(self::TABLE_TAGS, ['bundleid' => $bundleid, 'tag' => $t]);
    }

    /**
     * Get the bundle's Moodle course-level learning-hours coverage and total.
     *
     * Only explicitly stored, non-negative numeric values from a course custom field whose
     * shortname is "learninghours" are included. This lets callers distinguish a genuine
     * zero-hour value from a course which has no value at all.
     *
     * @param int $bundleid Bundle product ID.
     * @return array{fieldexists: bool, totalminutes: int, setcount: int, missingcount: int,
     *     coursecount: int, missingcourses: string[]}
     */
    public static function get_learning_hours_summary(int $bundleid): array {
        $courses = self::get_bundle_courses($bundleid);
        $summary = [
            'fieldexists' => false,
            'totalminutes' => 0,
            'setcount' => 0,
            'missingcount' => count($courses),
            'coursecount' => count($courses),
            'missingcourses' => array_column($courses, 'fullname'),
        ];

        $handler = \core_course\customfield\course_handler::create();
        $learninghoursfield = null;
        foreach ($handler->get_fields() as $field) {
            if (strtolower(trim((string)$field->get('shortname'))) === 'learninghours') {
                $learninghoursfield = $field;
                break;
            }
        }
        if ($learninghoursfield === null) {
            return $summary;
        }

        $summary['fieldexists'] = true;
        if (empty($courses)) {
            $summary['missingcourses'] = [];
            return $summary;
        }

        $courseids = array_column($courses, 'id');
        $instancesdata = $handler->get_instances_data($courseids, true);
        $fieldid = (int)$learninghoursfield->get('id');
        $missingcourses = [];

        foreach ($courses as $course) {
            $courseid = (int)$course['id'];
            $data = $instancesdata[$courseid][$fieldid] ?? null;
            // The instances-data method supplies defaults too; require a persisted course-level value.
            $hasstoredvalue = $data !== null && (int)$data->get('id') > 0;
            $value = $hasstoredvalue ? $data->get_value() : null;
            if ($value === null || $value === '' || !is_numeric($value) || (float)$value < 0) {
                $missingcourses[] = (string)$course['fullname'];
                continue;
            }

            $summary['setcount']++;
            $summary['totalminutes'] += (int)round((float)$value * 60);
        }

        $summary['missingcourses'] = $missingcourses;
        $summary['missingcount'] = count($missingcourses);
        return $summary;
    }

    /**
     * Compute total learning duration in minutes for all courses in a bundle.
     *
     * @param int $bundleid Bundle product ID.
     * @return int Total minutes from Moodle's learninghours course custom field.
     */
    public static function get_total_duration_minutes(int $bundleid): int {
        $summary = self::get_learning_hours_summary($bundleid);
        return $summary['totalminutes'];
    }
    /**
     * Sum the current user's owned value for courses in the bundle.
     *
     * @param int $bundleid
     * @param int $userid
     * @return float
     */
    public static function get_owned_courses_value(int $bundleid, int $userid): float {

        global $DB;
        if ($userid <= 0) {
            return 0.0;
        }
        $courseids = $DB->get_fieldset_select(
            self::TABLE_PRODUCT_COURSES,
            'courseid',
            'productid = :b AND relationtype = :relationtype',
            ['b' => $bundleid, 'relationtype' => 'included']
        ) ?: [];
        $sum = 0.0;
        foreach ($courseids as $cid) {
            $context = \context_course::instance((int)$cid, IGNORE_MISSING);
            if ($context && is_enrolled($context, $userid, '', true)) {
                $pricing = pricing_service::get_course_pricing((int)$cid);
                if ($pricing && empty($pricing->is_free) && isset($pricing->final_price)) {
                    $sum += (float)$pricing->final_price;
                }
            }
        }
        return $sum;
    }

    /**
     * Sum final prices (sale-aware) for bundle courses the given user does NOT own/enroll.
     * This is used to compute a realistic compare-at baseline that excludes owned courses.
     *
     * @param int $bundleid
     * @param int $userid
     * @return float
     */
    public static function get_compare_sum_excluding_owned(int $bundleid, int $userid): float {

        global $DB;
        $ownedids = [];
        if ($userid > 0) {
            $courseids = $DB->get_fieldset_select(
                self::TABLE_PRODUCT_COURSES,
                'courseid',
                'productid = :b AND relationtype = :relationtype',
                ['b' => $bundleid, 'relationtype' => 'included']
            ) ?: [];
            foreach ($courseids as $cid) {
                $context = \context_course::instance((int)$cid, IGNORE_MISSING);
                if ($context && is_enrolled($context, $userid, '', true)) {
                    $ownedids[] = (int)$cid;
                }
            }
        }
        $params = [
            'bid' => $bundleid, 'relationtypebundle' => 'included', 'relationtypecourse' => 'included',
        ];
        $exclusion = '';
        if (!empty($ownedids)) {
            [$notin, $inparams] = $DB->get_in_or_equal($ownedids, SQL_PARAMS_NAMED, 'oid', true);
            $exclusion = " AND bc.courseid $notin";
            $params = array_merge($params, $inparams);
        }
        $sql = "SELECT COALESCE(SUM(pr.amount), 0)
                  FROM {" . self::TABLE_PRODUCT_COURSES . "} bc
                  JOIN {" . self::TABLE_PRODUCT_COURSES . "} coursemap
                    ON coursemap.courseid = bc.courseid
                   AND coursemap.relationtype = :relationtypecourse
                  JOIN {local_moderncommerce_products} p
                    ON p.id = coursemap.productid
                   AND p.producttype = 'course'
             LEFT JOIN {local_moderncommerce_product_prices} pr
                    ON pr.productid = p.id
                   AND pr.pricetype = 'regular'
                   AND pr.enabled = 1
                 WHERE bc.productid = :bid
                   AND bc.relationtype = :relationtypebundle" . $exclusion;
        $sum = $DB->get_field_sql($sql, $params);
        return (float)$sum;
    }
    /**
     * Return outline items for a bundle.
     *
     * @param int $bundleid
     * @return array[] {position:int, title:string}
     */
    public static function get_outline(int $bundleid): array {

        global $DB;
        if (!self::table_exists(self::TABLE_OUTLINE)) {
            return [];
        }
        $items = $DB->get_records(self::TABLE_OUTLINE, ['bundleid' => $bundleid], 'sortorder ASC, id ASC');
        $out = [];
        foreach ($items as $it) {
            $out[] = [
                'position' => (int)($it->sortorder ?? 0),
                'title' => $it->item_text ?? '',
            ];
        }
        return $out;
    }

    /**
     * Return must-pass courses for a bundle.
     *
     * @param int $bundleid
     * @return int[] Moodle course ids
     */
    public static function get_mustpass(int $bundleid): array {

        global $DB;
        if (!self::table_exists(self::TABLE_MUSTPASS)) {
            return [];
        }
        $rows = $DB->get_records(self::TABLE_MUSTPASS, ['bundleid' => $bundleid], '', 'id, courseid');
        return array_values(array_map(function ($r) {
            return (int)$r->courseid;
        }, $rows));
    }

    /**
     * Return prerequisite course IDs for a bundle.
     *
     * @param int $bundleid
     * @return int[]
     */
    public static function get_prereq(int $bundleid): array {

        global $DB;
        if (!self::table_exists(self::TABLE_PREREQ)) {
            return [];
        }
        $rows = $DB->get_records(self::TABLE_PREREQ, ['bundleid' => $bundleid], '', 'id, courseid');
        return array_values(array_map(function ($r) {
            return (int)$r->courseid;
        }, $rows));
    }

    /**
     * Return prerequisite courses with names for a bundle.
     *
     * @param int $bundleid
     * @return array[] {id:int, fullname:string}
     */
    public static function get_prereq_courses(int $bundleid): array {

        global $DB;
        if (!self::table_exists(self::TABLE_PREREQ)) {
            return [];
        }
        $sql = "SELECT c.id, c.fullname
                  FROM {" . self::TABLE_PREREQ . "} p
            INNER JOIN {course} c ON c.id = p.courseid
                 WHERE p.bundleid = :bid
              ORDER BY c.fullname ASC";
        $recs = $DB->get_records_sql($sql, ['bid' => $bundleid]);
        $out = [];
        foreach ($recs as $r) {
            $out[] = [
                'id' => (int)$r->id,
                'fullname' => format_string($r->fullname, true, ['context' => \context_system::instance()]),
            ];
        }
        return $out;
    }

    /**
     * Return all courses in a bundle with id and fullname.
     *
     * @param int $bundleid
     * @return array[] {id:int, fullname:string}
     */
    public static function get_bundle_courses(int $bundleid): array {

        global $DB;
        $sql = "SELECT c.id, c.fullname
                  FROM {" . self::TABLE_PRODUCT_COURSES . "} bc
            INNER JOIN {course} c ON c.id = bc.courseid
                 WHERE bc.productid = :bid
                   AND bc.relationtype = :relationtype
              ORDER BY c.fullname ASC";
        $recs = $DB->get_records_sql($sql, ['bid' => $bundleid, 'relationtype' => 'included']);
        $out = [];
        foreach ($recs as $r) {
            $out[] = [
                'id' => (int)$r->id,
                'fullname' => format_string($r->fullname, true, ['context' => \context_system::instance()]),
            ];
        }
        return $out;
    }

    /**
     * Search prerequisite candidate courses: visible courses not in this bundle.
     *
     * @param int $bundleid
     * @param string $query
     * @param int $limit
     * @return array[] {id:int, fullname:string}
     */
    public static function search_prereq_candidates(int $bundleid, string $query, int $limit = 10): array {
        global $DB, $SITE;
        $q = trim($query);
        $params = ['bid' => $bundleid, 'siteid' => (int)$SITE->id];
        $like = '';
        if ($q !== '') {
            $like = " AND (" . $DB->sql_like('c.fullname', ':q', false) . " OR " . $DB->sql_like('c.shortname', ':q2', false) . ")";
            $params['q'] = "%$q%";
            $params['q2'] = "%$q%";
        }
        $sql = "SELECT c.id, c.fullname
                  FROM {course} c
                 WHERE c.visible = 1
                   AND c.id <> :siteid
                   AND NOT EXISTS (
                        SELECT 1 FROM {" . self::TABLE_PRODUCT_COURSES . "} bc
                         WHERE bc.productid = :bid
                           AND bc.courseid = c.id
                           AND bc.relationtype = 'included'
                   )" . $like . "
              ORDER BY c.fullname ASC";
        $recs = $DB->get_records_sql($sql, $params, 0, $limit);
        $out = [];
        foreach ($recs as $r) {
            $out[] = [
                'id' => (int)$r->id,
                'fullname' => format_string($r->fullname, true, ['context' => \context_system::instance()]),
            ];
        }
        return $out;
    }

    /**
     * Save meta (no-op until DB upgrade).
     *
     * @param int $bundleid
     * @param array $data
     * @return array result info
     */
    public static function save_meta(int $bundleid, array $data): array {

        global $DB, $USER;
        if (!self::table_exists(self::TABLE_META)) {
            self::save_product_price_from_meta($bundleid, $data);
            return [
                'status' => 'saved', 'meta' => self::get_meta($bundleid), 'outline' => [], 'mustpass' => [], 'prereq' => [],
            ];
        }

        // Normalize and map payload to DB fields.
        $visibility = isset($data['visibility_mode']) ? (string)$data['visibility_mode'] : 'Public';
        $availstart = !empty($data['avail_start']) ? strtotime($data['avail_start']) : null;
        $availend = !empty($data['avail_end']) ? strtotime($data['avail_end']) : null;
        if (!empty($availstart) && !empty($availend) && $availend < $availstart) {
            // Ensure availability end is not before start.
            $availend = $availstart;
        }
        $record = (object) [
            'bundleid' => $bundleid,
            'visibility' => $visibility,
            'availstart' => $availstart,
            'availend' => $availend,
            'badge_featured' => !empty($data['badge_featured']) ? 1 : 0,
            'badge_bestseller' => !empty($data['badge_bestseller']) ? 1 : 0,
            'badge_trending' => !empty($data['badge_trending']) ? 1 : 0,
            'bundle_price' => isset($data['price']) ? (float)$data['price'] : 0,
            'compare_at' => isset($data['compare_at']) ? (float)$data['compare_at'] : 0,
            'skill_level' => $data['skill_level'] ?? null,
            'language' => $data['language'] ?? null,
            'has_prereq' => !empty($data['has_prereq']) ? 1 : 0,
            'auto_duration' => !empty($data['auto_duration']) ? 1 : 0,
            'dur_hours' => isset($data['duration_hours']) ? max(0, (int)$data['duration_hours']) : 0,
            'dur_mins' => isset($data['duration_minutes']) ? max(0, min(59, (int)$data['duration_minutes'])) : 0,
            'auto_assessments' => !empty($data['auto_assessments']) ? 1 : 0,
            'assessments_count' => isset($data['assessments_count']) ? max(0, (int)$data['assessments_count']) : 0,
            'auto_outline' => !empty($data['auto_outline']) ? 1 : 0,
            'pass_grade' => isset($data['pass_grade']) ? max(0.0, min(100.0, (float)$data['pass_grade'])) : null,
            'pass_policy' => in_array(
                ($data['pass_policy'] ?? 'all_must_pass'),
                ['all_must_pass', 'weighted_avg', 'any_pass']
            ) ? $data['pass_policy'] : 'all_must_pass',
            'bundle_cert' => !empty($data['bundle_cert_enabled']) ? 1 : 0,
            'certificate_template_id' => !empty($data['certificate_template_id'])
                ? (int)$data['certificate_template_id']
                : null,
            'usermodified' => $USER->id ?? null,
            'timemodified' => time(),
        ];

        // Upsert main meta.
        $existing = $DB->get_record(self::TABLE_META, ['bundleid' => $bundleid]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record(self::TABLE_META, $record);
        } else {
            $record->timecreated = time();
            $DB->insert_record(self::TABLE_META, $record);
        }

        // Safety: ensure a single meta row per bundle.
        $all = $DB->get_records(self::TABLE_META, ['bundleid' => $bundleid], 'timemodified DESC, id DESC');
        if (count($all) > 1) {
            $keep = array_shift($all); // Keep the most recent.
            foreach ($all as $extra) {
                $DB->delete_records(self::TABLE_META, ['id' => $extra->id]);
            }
        }

        // Persist outline items if provided.
        if (isset($data['outline_items']) && is_array($data['outline_items']) && self::table_exists(self::TABLE_OUTLINE)) {
            $DB->delete_records(self::TABLE_OUTLINE, ['bundleid' => $bundleid]);
            $sort = 0;
            foreach ($data['outline_items'] as $item) {
                $text = is_array($item) ? ($item['title'] ?? '') : '';
                $pos = is_array($item) ? ($item['position'] ?? $sort) : $sort;
                $row = (object) [
                    'bundleid' => $bundleid,
                    'sortorder' => (int)$pos,
                    'item_text' => $text,
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'usermodified' => $USER->id ?? null,
                ];
                $DB->insert_record(self::TABLE_OUTLINE, $row);
                $sort++;
            }
        }

        // Persist must-pass courses if provided.
        if (
            isset($data['mustpass_courseids'])
            && is_array($data['mustpass_courseids'])
            && self::table_exists(self::TABLE_MUSTPASS)
        ) {
            $DB->delete_records(self::TABLE_MUSTPASS, ['bundleid' => $bundleid]);
            foreach ($data['mustpass_courseids'] as $cid) {
                $cid = (int)$cid;
                if ($cid <= 0) {
                    continue;
                }
                $row = (object) [
                    'bundleid' => $bundleid,
                    'courseid' => $cid,
                    'timecreated' => time(),
                ];
                $DB->insert_record(self::TABLE_MUSTPASS, $row);
            }
        }

        // Persist prerequisites if provided.
        if (isset($data['prereq_courseids']) && is_array($data['prereq_courseids']) && self::table_exists(self::TABLE_PREREQ)) {
            $DB->delete_records(self::TABLE_PREREQ, ['bundleid' => $bundleid]);
            foreach ($data['prereq_courseids'] as $cid) {
                $cid = (int)$cid;
                if ($cid <= 0) {
                    continue;
                }
                $row = (object) [
                    'bundleid' => $bundleid,
                    'courseid' => $cid,
                    'timecreated' => time(),
                ];
                $DB->insert_record(self::TABLE_PREREQ, $row);
            }
        }

        // Persist tags if provided.
        if (isset($data['tags']) && is_array($data['tags']) && self::table_exists(self::TABLE_TAGS)) {
            $DB->delete_records(self::TABLE_TAGS, ['bundleid' => $bundleid]);
            $seen = [];
            foreach ($data['tags'] as $tag) {
                $t = trim((string)$tag);
                if ($t === '') {
                    continue;
                }
                // Enforce max length as per schema.
                if (\core_text::strlen($t) > 100) {
                    $t = \core_text::substr($t, 0, 100);
                }
                $key = \core_text::strtolower($t);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $row = (object) [
                    'bundleid' => $bundleid,
                    'tag' => $t,
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'usermodified' => $USER->id ?? null,
                ];
                $DB->insert_record(self::TABLE_TAGS, $row);
            }
        }

        // Return the updated bundle meta.
        $meta = self::get_meta($bundleid);
        $outline = self::get_outline($bundleid);
        $mustpass = self::get_mustpass($bundleid);
        $prereq = self::get_prereq($bundleid);
        return [
            'status' => 'saved', 'meta' => $meta, 'outline' => $outline, 'mustpass' => $mustpass, 'prereq' => $prereq,
        ];
    }

    /**
     * Persist price fields to the product price table when advanced bundle meta is not installed.
     *
     * @param int $bundleid Product ID.
     * @param array $data Submitted meta payload.
     * @return void
     */
    private static function save_product_price_from_meta(int $bundleid, array $data): void {

        global $DB;
        if (!self::table_exists(self::TABLE_PRODUCT_PRICES)) {
            return;
        }

        if (!array_key_exists('price', $data) && !array_key_exists('compare_at', $data)) {
            return;
        }

        $record = $DB->get_record(self::TABLE_PRODUCT_PRICES, [
            'productid' => $bundleid, 'pricetype' => 'regular',
        ]);
        $now = time();
        if (!$record) {
            $record = (object) [
                'productid' => $bundleid,
                'pricetype' => 'regular',
                'amount' => 0,
                'compareamount' => null,
                'enabled' => 1,
                'timecreated' => $now,
            ];
        }

        if (array_key_exists('price', $data)) {
            $record->amount = max(0, (float)$data['price']);
        }

        if (array_key_exists('compare_at', $data)) {
            $compare = max(0, (float)$data['compare_at']);
            $record->compareamount = $compare > (float)$record->amount ? $compare : null;
        }

        $record->enabled = 1;
        $record->timemodified = $now;
        if (!empty($record->id)) {
            $DB->update_record(self::TABLE_PRODUCT_PRICES, $record);
        } else {
            $DB->insert_record(self::TABLE_PRODUCT_PRICES, $record);
        }
    }
}
