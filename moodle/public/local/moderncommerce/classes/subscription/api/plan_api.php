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

namespace local_moderncommerce\subscription\api;

use local_moderncommerce\event\plan_created;
use local_moderncommerce\event\plan_updated;
use local_moderncommerce\event\plan_deleted;

/**
 * Plan API - CRUD operations for subscription plans.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_api {
    /** Monthly billing cycle. */
    const CYCLE_MONTHLY = 'monthly';

    /** Yearly billing cycle. */
    const CYCLE_YEARLY = 'yearly';

    /** @var array Static cache for plans */
    private static $cache = [];

    /**
     * Get a plan by ID.
     *
     * @param int $planid Plan ID.
     * @param bool $usecache Use cache.
     * @return object|false Plan record or false.
     */
    public static function get(int $planid, bool $usecache = true) {
        global $DB;

        if ($usecache && isset(self::$cache[$planid])) {
            return self::$cache[$planid];
        }

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $planid]);

        if ($plan) {
            self::$cache[$planid] = $plan;
        }

        return $plan;
    }

    /**
     * Seats currently occupied on a plan.
     *
     * Counts the states that still entitle the holder to access, so a cancelled
     * or expired subscriber releases their seat straight away.
     *
     * @param int $planid Plan ID.
     * @return int Number of seats in use.
     */
    public static function seats_in_use(int $planid): int {
        global $DB;

        return (int) $DB->count_records_select(
            'local_moderncommerce_user_subscriptions',
            "planid = :planid AND status IN ('active', 'trial', 'grace')",
            ['planid' => $planid]
        );
    }

    /**
     * Whether a plan has a seat available for a new subscriber.
     *
     * A max_seats of zero (the default) means unlimited. An existing subscriber
     * renewing or reactivating already holds their seat, so pass their user id
     * to exempt them from the count.
     *
     * @param object $plan Plan record.
     * @param int $userid User who wants the seat, or 0 for a new subscriber.
     * @return bool True when the plan can take another subscriber.
     */
    public static function has_available_seat(object $plan, int $userid = 0): bool {
        global $DB;

        $maxseats = (int) ($plan->max_seats ?? 0);
        if ($maxseats <= 0) {
            return true;
        }

        // Someone who already holds a live seat on this plan is not taking a new one.
        if ($userid > 0) {
            $holdsseat = $DB->record_exists_select(
                'local_moderncommerce_user_subscriptions',
                "planid = :planid AND userid = :userid AND status IN ('active', 'trial', 'grace')",
                ['planid' => (int) $plan->id, 'userid' => $userid]
            );
            if ($holdsseat) {
                return true;
            }
        }

        return self::seats_in_use((int) $plan->id) < $maxseats;
    }

    /**
     * Check if a plan has an active promo.
     *
     * @param object $plan Plan record.
     * @return bool True if promo is active.
     */
    public static function has_active_promo(object $plan): bool {
        if (empty($plan->promo_price) || $plan->promo_price <= 0) {
            return false;
        }

        // If no end date set, promo is always active.
        if (empty($plan->promo_end_date)) {
            return true;
        }

        // Check if promo hasn't expired.
        return $plan->promo_end_date > time();
    }

    /**
     * Get the effective price for a plan (promo or regular).
     *
     * @param object $plan Plan record.
     * @return float The effective price.
     */
    public static function get_effective_price(object $plan): float {
        if (self::has_active_promo($plan)) {
            return (float) $plan->promo_price;
        }
        return (float) $plan->price;
    }

    /**
     * Get plan by code.
     *
     * @param string $code Plan code.
     * @return object|false Plan record or false.
     */
    public static function get_by_code(string $code) {
        global $DB;

        return $DB->get_record('local_moderncommerce_subscription_plans', ['code' => $code]);
    }

    /**
     * Get all active plans.
     *
     * @param string|null $billingcycle Filter by billing cycle.
     * @return array Array of plan records.
     */
    public static function get_all(string $billingcycle = null): array {
        global $DB;

        $params = ['status' => 'active'];
        if ($billingcycle) {
            $params['billing_cycle'] = $billingcycle;
        }

        return $DB->get_records('local_moderncommerce_subscription_plans', $params, 'sortorder ASC, name ASC');
    }

    /**
     * Get all plans including disabled ones (admin view).
     *
     * @return array Array of plan records.
     */
    public static function get_all_admin(): array {
        global $DB;

        return $DB->get_records('local_moderncommerce_subscription_plans', [], 'sortorder ASC, name ASC');
    }

    /**
     * Create a new plan.
     *
     * @param object $data Plan data.
     * @return int New plan ID.
     * @throws \moodle_exception
     */
    public static function create(object $data): int {
        global $DB, $USER;

        // Validate required fields.
        self::validate_plan_data($data);

        // Auto-generate code if empty.
        if (empty($data->code)) {
            $data->code = self::generate_code($data->name);
        }

        // Check for duplicate code.
        if ($DB->record_exists('local_moderncommerce_subscription_plans', ['code' => $data->code])) {
            throw new \moodle_exception('error:duplicatecode', 'local_moderncommerce');
        }

        // Get global currency from moderncommerce settings.
        $globalcurrency = get_config('local_moderncommerce', 'primary_currency') ?: 'USD';

        // Prepare record.
        $record = new \stdClass();
        $record->name = $data->name;
        $record->code = $data->code;
        $record->description = $data->description ?? '';
        $record->billing_cycle = $data->billing_cycle ?? self::CYCLE_MONTHLY;
        $record->price = $data->price;
        $record->promo_price = !empty($data->promo_price) ? $data->promo_price : null;
        $record->promo_end_date = !empty($data->promo_end_date) ? $data->promo_end_date : null;
        $record->currency = $globalcurrency;
        $record->trial_days = $data->trial_days ?? 0;
        $record->grace_period_days = $data->grace_period_days ?? 7;
        $record->max_seats = $data->max_seats ?? 0;
        $record->status = $data->status ?? 'active';
        $record->sortorder = $data->sortorder ?? 0;
        $record->featured = $data->featured ?? 0;
        $record->createdby = $USER->id;
        $record->timecreated = time();
        $record->timemodified = time();

        $planid = $DB->insert_record('local_moderncommerce_subscription_plans', $record);

        // Clear cache.
        self::$cache = [];

        // Trigger event.
        $context = \context_system::instance();
        $event = plan_created::create([
            'context' => $context,
            'objectid' => $planid,
            'other' => ['name' => $record->name],
        ]);
        $event->trigger();

        return $planid;
    }

    /**
     * Update a plan.
     *
     * @param int $planid Plan ID.
     * @param object $data Updated data.
     * @return bool Success.
     * @throws \moodle_exception
     */
    public static function update(int $planid, object $data): bool {
        global $DB;

        $plan = self::get($planid, false);
        if (!$plan) {
            throw new \moodle_exception('error:plannotfound', 'local_moderncommerce');
        }

        // Validate.
        self::validate_plan_data($data, $planid);

        // Check for duplicate code.
        if (!empty($data->code) && $data->code !== $plan->code) {
            if ($DB->record_exists('local_moderncommerce_subscription_plans', ['code' => $data->code])) {
                throw new \moodle_exception('error:duplicatecode', 'local_moderncommerce');
            }
        }

        // Update fields.
        $update = new \stdClass();
        $update->id = $planid;

        $allowedfields = [
            'name', 'code', 'description', 'billing_cycle', 'price', 'promo_price', 'promo_end_date', 'currency',
            'trial_days', 'grace_period_days', 'max_seats', 'status', 'sortorder', 'featured',
        ];

        foreach ($allowedfields as $field) {
            if (property_exists($data, $field)) {
                $update->$field = $data->$field;
            }
        }

        $update->timemodified = time();

        $result = $DB->update_record('local_moderncommerce_subscription_plans', $update);

        // Clear cache.
        unset(self::$cache[$planid]);

        // Trigger event.
        $context = \context_system::instance();
        $event = plan_updated::create([
            'context' => $context,
            'objectid' => $planid,
            'other' => ['name' => $data->name ?? $plan->name],
        ]);
        $event->trigger();

        return $result;
    }

    /**
     * Delete a plan.
     *
     * @param int $planid Plan ID.
     * @param bool $force Force delete even if subscribers exist.
     * @return bool Success.
     * @throws \moodle_exception
     */
    public static function delete(int $planid, bool $force = false): bool {
        global $DB;

        $plan = self::get($planid, false);
        if (!$plan) {
            throw new \moodle_exception('error:plannotfound', 'local_moderncommerce');
        }

        // Check for active subscribers.
        if (!$force) {
            $activecount = $DB->count_records_select(
                'local_moderncommerce_user_subscriptions',
                "planid = :planid AND status IN ('active', 'trial', 'grace')",
                ['planid' => $planid]
            );

            if ($activecount > 0) {
                throw new \moodle_exception('error:planhassubscribers', 'local_moderncommerce', '', $activecount);
            }
        }

        // Start transaction.
        $transaction = $DB->start_delegated_transaction();

        try {
            // Delete access rules.
            $DB->delete_records('local_moderncommerce_subscription_access_rules', ['planid' => $planid]);

            // Delete the plan.
            $DB->delete_records('local_moderncommerce_subscription_plans', ['id' => $planid]);

            $transaction->allow_commit();
        } catch (\Exception $e) {
            $transaction->rollback($e);
            throw $e;
        }

        // Clear cache.
        unset(self::$cache[$planid]);

        // Trigger event.
        $context = \context_system::instance();
        $event = plan_deleted::create([
            'context' => $context,
            'objectid' => $planid,
            'other' => ['name' => $plan->name],
        ]);
        $event->trigger();

        return true;
    }

    /**
     * Add access rule to a plan.
     *
     * @param int $planid Plan ID.
     * @param string $accesstype Access type (course, category, bundle).
     * @param int $targetid Target ID.
     * @return int Rule ID.
     */
    public static function add_access_rule(int $planid, string $accesstype, int $targetid): int {
        global $DB;

        // Check for duplicate.
        $existing = $DB->get_record('local_moderncommerce_subscription_access_rules', [
            'planid' => $planid,
            'access_type' => $accesstype,
            'target_id' => $targetid,
        ]);

        if ($existing) {
            return $existing->id;
        }

        $rule = new \stdClass();
        $rule->planid = $planid;
        $rule->access_type = $accesstype;
        $rule->target_id = $targetid;
        $rule->timecreated = time();

        return $DB->insert_record('local_moderncommerce_subscription_access_rules', $rule);
    }

    /**
     * Remove access rule from a plan.
     *
     * @param int $planid Plan ID.
     * @param string $accesstype Access type.
     * @param int $targetid Target ID.
     * @return bool Success.
     */
    public static function remove_access_rule(int $planid, string $accesstype, int $targetid): bool {
        global $DB;

        return $DB->delete_records('local_moderncommerce_subscription_access_rules', [
            'planid' => $planid,
            'access_type' => $accesstype,
            'target_id' => $targetid,
        ]);
    }

    /**
     * Get access rules for a plan.
     *
     * @param int $planid Plan ID.
     * @return array Array of access rules with target details.
     */
    public static function get_access_rules(int $planid): array {
        global $DB;

        $rules = $DB->get_records('local_moderncommerce_subscription_access_rules', ['planid' => $planid]);

        foreach ($rules as $rule) {
            switch ($rule->access_type) {
                case 'course':
                    $course = $DB->get_record('course', ['id' => $rule->target_id], 'id, fullname, shortname');
                    $rule->target_name = $course ? $course->fullname : get_string('unknowncourse', 'local_moderncommerce');
                    break;

                case 'category':
                    $cat = $DB->get_record('course_categories', ['id' => $rule->target_id], 'id, name');
                    $rule->target_name = $cat ? $cat->name : get_string('unknowncategory', 'local_moderncommerce');
                    break;

                case 'bundle':
                    $bundle = $DB->get_record('local_moderncommerce_products', ['id' => $rule->target_id], 'id, name');
                    $rule->target_name = $bundle ? $bundle->name : get_string('unknownbundle', 'local_moderncommerce');
                    break;
            }
        }

        return $rules;
    }

    /**
     * Get subscriber count for a plan.
     *
     * @param int $planid Plan ID.
     * @param string|null $status Filter by status.
     * @return int Subscriber count.
     */
    public static function get_subscriber_count(int $planid, string $status = null): int {
        global $DB;

        $params = ['planid' => $planid];
        $where = 'planid = :planid';

        if ($status) {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }

        return $DB->count_records_select('local_moderncommerce_user_subscriptions', $where, $params);
    }

    /**
     * Validate plan data.
     *
     * @param object $data Plan data.
     * @param int|null $existingid Existing plan ID for updates.
     * @throws \moodle_exception
     */
    private static function validate_plan_data(object $data, int $existingid = null): void {
        if (empty($data->name)) {
            throw new \moodle_exception('error:namerequired', 'local_moderncommerce');
        }

        if (!isset($data->price) || $data->price < 0) {
            throw new \moodle_exception('error:invalidprice', 'local_moderncommerce');
        }

        if (!empty($data->billing_cycle)) {
            $validcycles = [self::CYCLE_MONTHLY, self::CYCLE_YEARLY];
            if (!in_array($data->billing_cycle, $validcycles)) {
                throw new \moodle_exception('error:invalidbillingcycle', 'local_moderncommerce');
            }
        }
    }

    /**
     * Generate a unique code from name.
     *
     * @param string $name Plan name.
     * @return string Generated code.
     */
    private static function generate_code(string $name): string {
        global $DB;

        $code = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $code = substr($code, 0, 20);

        // Ensure unique.
        $basecode = $code;
        $counter = 1;
        while ($DB->record_exists('local_moderncommerce_subscription_plans', ['code' => $code])) {
            $code = $basecode . $counter;
            $counter++;
        }

        return $code;
    }

    // Plan feature methods.

    /**
     * Get all features for a plan.
     *
     * @param int $planid Plan ID.
     * @return array Array of feature records.
     */
    public static function get_features(int $planid): array {
        global $DB;

        return $DB->get_records('local_moderncommerce_subscription_plan_features', ['planid' => $planid], 'sortorder ASC, id ASC');
    }

    /**
     * Add a feature to a plan.
     *
     * @param int $planid Plan ID.
     * @param string $feature Feature text.
     * @param string|null $icon Bootstrap icon name (optional).
     * @return int Feature ID.
     */
    public static function add_feature(int $planid, string $feature, ?string $icon = null): int {
        global $DB;

        // Get max sortorder.
        $maxsort = $DB->get_field_sql(
            "SELECT MAX(sortorder) FROM {local_moderncommerce_subscription_plan_features} WHERE planid = ?",
            [$planid]
        );

        $record = new \stdClass();
        $record->planid = $planid;
        $record->feature = trim($feature);
        $record->icon = $icon ?: 'check-circle';
        $record->sortorder = ($maxsort !== null) ? $maxsort + 1 : 0;
        $record->timecreated = time();

        return $DB->insert_record('local_moderncommerce_subscription_plan_features', $record);
    }

    /**
     * Update a feature.
     *
     * @param int $featureid Feature ID.
     * @param string $feature Feature text.
     * @param string|null $icon Bootstrap icon name (optional).
     * @return bool Success.
     */
    public static function update_feature(int $featureid, string $feature, ?string $icon = null): bool {
        global $DB;

        $record = new \stdClass();
        $record->id = $featureid;
        $record->feature = trim($feature);
        if ($icon !== null) {
            $record->icon = $icon;
        }

        return $DB->update_record('local_moderncommerce_subscription_plan_features', $record);
    }

    /**
     * Delete a feature.
     *
     * @param int $featureid Feature ID.
     * @return bool Success.
     */
    public static function delete_feature(int $featureid): bool {
        global $DB;

        return $DB->delete_records('local_moderncommerce_subscription_plan_features', ['id' => $featureid]);
    }

    /**
     * Delete all features for a plan.
     *
     * @param int $planid Plan ID.
     * @return bool Success.
     */
    public static function delete_all_features(int $planid): bool {
        global $DB;

        return $DB->delete_records('local_moderncommerce_subscription_plan_features', ['planid' => $planid]);
    }

    /**
     * Reorder features for a plan.
     *
     * @param int $planid Plan ID.
     * @param array $featureids Array of feature IDs in desired order.
     * @return bool Success.
     */
    public static function reorder_features(int $planid, array $featureids): bool {
        global $DB;

        $sortorder = 0;
        foreach ($featureids as $featureid) {
            $DB->set_field('local_moderncommerce_subscription_plan_features', 'sortorder', $sortorder, [
                'id' => $featureid,
                'planid' => $planid,
            ]);
            $sortorder++;
        }

        return true;
    }
}
