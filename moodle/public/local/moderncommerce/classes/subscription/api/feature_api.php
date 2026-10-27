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
 * Feature API for managing master features and plan-feature mappings.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\subscription\api;

/**
 * API class for managing subscription features.
 */
class feature_api {
    /**
     * Get all master features.
     *
     * @param bool $activeonly Only return active features.
     * @return array Array of feature records.
     */
    public static function get_all(bool $activeonly = true): array {
        global $DB;

        $params = [];
        if ($activeonly) {
            $params['status'] = 'active';
        }

        return $DB->get_records('local_moderncommerce_subscription_features', $params, 'sortorder ASC, name ASC');
    }

    /**
     * Get a single feature by ID.
     *
     * @param int $featureid Feature ID.
     * @return object|false Feature record or false.
     */
    public static function get(int $featureid) {
        global $DB;
        return $DB->get_record('local_moderncommerce_subscription_features', ['id' => $featureid]);
    }

    /**
     * Create a new master feature.
     *
     * @param string $name Feature name.
     * @param string $description Feature description.
     * @param string $icon Bootstrap icon name.
     * @return int New feature ID.
     */
    public static function create(string $name, string $description = '', string $icon = 'check-circle'): int {
        global $DB;

        $now = time();

        // Get next sort order.
        $maxsort = $DB->get_field_sql("SELECT MAX(sortorder) FROM {local_moderncommerce_subscription_features}");
        $sortorder = ($maxsort !== null) ? $maxsort + 1 : 0;

        $record = new \stdClass();
        $record->name = $name;
        $record->description = $description;
        $record->icon = $icon;
        $record->sortorder = $sortorder;
        $record->status = 'active';
        $record->timecreated = $now;
        $record->timemodified = $now;

        return $DB->insert_record('local_moderncommerce_subscription_features', $record);
    }

    /**
     * Update a master feature.
     *
     * @param int $featureid Feature ID.
     * @param string $name Feature name.
     * @param string $description Feature description.
     * @param string $icon Bootstrap icon name.
     * @return bool Success.
     */
    public static function update(int $featureid, string $name, string $description = '', string $icon = 'check-circle'): bool {
        global $DB;

        $record = new \stdClass();
        $record->id = $featureid;
        $record->name = $name;
        $record->description = $description;
        $record->icon = $icon;
        $record->timemodified = time();

        return $DB->update_record('local_moderncommerce_subscription_features', $record);
    }

    /**
     * Delete a master feature.
     *
     * @param int $featureid Feature ID.
     * @return bool Success.
     */
    public static function delete(int $featureid): bool {
        global $DB;

        // First remove all plan mappings.
        $DB->delete_records('local_moderncommerce_subscription_feature_map', ['featureid' => $featureid]);

        // Then delete the feature.
        return $DB->delete_records('local_moderncommerce_subscription_features', ['id' => $featureid]);
    }

    /**
     * Set feature status (active/inactive).
     *
     * @param int $featureid Feature ID.
     * @param string $status Status (active|inactive).
     * @return bool Success.
     */
    public static function set_status(int $featureid, string $status): bool {
        global $DB;

        return $DB->set_field('local_moderncommerce_subscription_features', 'status', $status, ['id' => $featureid]);
    }

    /**
     * Reorder features.
     *
     * @param array $featureids Ordered array of feature IDs.
     * @return bool Success.
     */
    public static function reorder(array $featureids): bool {
        global $DB;

        $sortorder = 0;
        foreach ($featureids as $featureid) {
            $DB->set_field('local_moderncommerce_subscription_features', 'sortorder', $sortorder, ['id' => $featureid]);
            $sortorder++;
        }

        return true;
    }

    // Plan-feature mapping methods.

    /**
     * Get all features for a specific plan.
     *
     * @param int $planid Plan ID.
     * @return array Array of feature records.
     */
    public static function get_plan_features(int $planid): array {
        global $DB;

        $sql = "SELECT f.*
                FROM {local_moderncommerce_subscription_features} f
                JOIN {local_moderncommerce_subscription_feature_map} m ON m.featureid = f.id
                WHERE m.planid = :planid AND f.status = 'active'
                ORDER BY f.sortorder ASC";

        return $DB->get_records_sql($sql, ['planid' => $planid]);
    }

    /**
     * Get feature IDs for a specific plan.
     *
     * @param int $planid Plan ID.
     * @return array Array of feature IDs.
     */
    public static function get_plan_feature_ids(int $planid): array {
        global $DB;

        $records = $DB->get_records('local_moderncommerce_subscription_feature_map', ['planid' => $planid], '', 'featureid');
        return array_keys($records);
    }

    /**
     * Check if a plan has a specific feature.
     *
     * @param int $planid Plan ID.
     * @param int $featureid Feature ID.
     * @return bool True if plan has the feature.
     */
    public static function plan_has_feature(int $planid, int $featureid): bool {
        global $DB;
        return $DB->record_exists('local_moderncommerce_subscription_feature_map', [
            'planid' => $planid,
            'featureid' => $featureid,
        ]);
    }

    /**
     * Add a feature to a plan.
     *
     * @param int $planid Plan ID.
     * @param int $featureid Feature ID.
     * @return int|bool Map record ID or false if already exists.
     */
    public static function add_feature_to_plan(int $planid, int $featureid) {
        global $DB;

        // Check if already mapped.
        if (self::plan_has_feature($planid, $featureid)) {
            return false;
        }

        $record = new \stdClass();
        $record->planid = $planid;
        $record->featureid = $featureid;
        $record->timecreated = time();

        return $DB->insert_record('local_moderncommerce_subscription_feature_map', $record);
    }

    /**
     * Remove a feature from a plan.
     *
     * @param int $planid Plan ID.
     * @param int $featureid Feature ID.
     * @return bool Success.
     */
    public static function remove_feature_from_plan(int $planid, int $featureid): bool {
        global $DB;

        return $DB->delete_records('local_moderncommerce_subscription_feature_map', [
            'planid' => $planid,
            'featureid' => $featureid,
        ]);
    }

    /**
     * Set all features for a plan (replaces existing mappings).
     *
     * @param int $planid Plan ID.
     * @param array $featureids Array of feature IDs to assign.
     * @return bool Success.
     */
    public static function set_plan_features(int $planid, array $featureids): bool {
        global $DB;

        // Remove existing mappings.
        $DB->delete_records('local_moderncommerce_subscription_feature_map', ['planid' => $planid]);

        // Add new mappings.
        $now = time();
        foreach ($featureids as $featureid) {
            $record = new \stdClass();
            $record->planid = $planid;
            $record->featureid = (int)$featureid;
            $record->timecreated = $now;
            $DB->insert_record('local_moderncommerce_subscription_feature_map', $record);
        }

        return true;
    }

    /**
     * Get the feature matrix data for all plans.
     *
     * Returns structured data for displaying in a matrix view.
     *
     * @return array ['features' => [...], 'plans' => [...], 'matrix' => [...]]
     */
    public static function get_matrix_data(): array {
        global $DB;

        // Get all active features.
        $features = self::get_all(true);

        // Get all active plans.
        $plans = $DB->get_records('local_moderncommerce_subscription_plans', ['status' => 'active'], 'sortorder ASC, name ASC');

        // Build matrix (which features are enabled for which plans).
        $matrix = [];
        foreach ($features as $feature) {
            $matrix[$feature->id] = [];
            foreach ($plans as $plan) {
                $matrix[$feature->id][$plan->id] = self::plan_has_feature($plan->id, $feature->id);
            }
        }

        return [
            'features' => $features,
            'plans' => $plans,
            'matrix' => $matrix,
        ];
    }

    /**
     * Save the entire feature matrix.
     *
     * @param array $matrixdata Associative array [featureid => [planid => bool, ...], ...]
     * @return bool Success.
     */
    public static function save_matrix(array $matrixdata): bool {
        global $DB;

        foreach ($matrixdata as $featureid => $planstates) {
            foreach ($planstates as $planid => $enabled) {
                if ($enabled) {
                    self::add_feature_to_plan($planid, $featureid);
                } else {
                    self::remove_feature_from_plan($planid, $featureid);
                }
            }
        }

        return true;
    }
}
