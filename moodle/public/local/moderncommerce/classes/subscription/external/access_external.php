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
 * External APIs for subscription plan access rules (course/category/bundle grants).
 *
 * Consumed by the Modern Commerce plan-access React screen.
 *
 * @package    local_moderncommerce
 * @copyright  2026 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\subscription\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\subscription\api\feature_api;
use local_moderncommerce\subscription\api\plan_api;

/**
 * Plan access webservice methods.
 */
class access_external extends external_api {
    /** @var string[] Supported access types. */
    private const TYPES = ['course', 'category', 'bundle'];

    /**
     * Parameters for get_plan_access.
     *
     * @return external_function_parameters
     */
    public static function get_plan_access_parameters(): external_function_parameters {
        return new external_function_parameters([
            'planid' => new external_value(PARAM_INT, 'Subscription plan ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get a plan's access rules plus pickers for categories and bundles.
     *
     * @param int $planid Plan ID.
     * @return array
     */
    public static function get_plan_access(int $planid): array {
        $params = self::validate_parameters(self::get_plan_access_parameters(), ['planid' => $planid]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $plan = plan_api::get((int) $params['planid'], false);
        if (!$plan) {
            throw new \moodle_exception('error:plannotfound', 'local_moderncommerce');
        }

        [$rules, $totalcourses] = self::format_rules((int) $plan->id);

        return [
            'plan' => self::format_plan($plan),
            'rules' => $rules,
            'totalcourses' => $totalcourses,
            'features' => self::plan_features((int) $plan->id),
            'categories' => self::category_options(),
            'bundles' => self::bundle_options(),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_plan_access.
     *
     * @return external_single_structure
     */
    public static function get_plan_access_returns(): external_single_structure {
        return new external_single_structure([
            'plan' => self::plan_structure(),
            'rules' => new external_multiple_structure(self::rule_structure()),
            'totalcourses' => new external_value(PARAM_INT, 'Unique courses granted by the plan.'),
            'features' => new external_multiple_structure(self::feature_structure()),
            'categories' => new external_multiple_structure(self::option_structure()),
            'bundles' => new external_multiple_structure(self::option_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for save_plan_features.
     *
     * @return external_function_parameters
     */
    public static function save_plan_features_parameters(): external_function_parameters {
        return new external_function_parameters([
            'planid' => new external_value(PARAM_INT, 'Subscription plan ID.', VALUE_REQUIRED),
            'featureids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Master feature ID enabled for the plan.')
            ),
        ]);
    }

    /**
     * Set the master features enabled for a plan.
     *
     * @param int $planid Plan ID.
     * @param int[] $featureids Enabled feature IDs.
     * @return array
     */
    public static function save_plan_features(int $planid, array $featureids): array {
        $params = self::validate_parameters(self::save_plan_features_parameters(), [
            'planid' => $planid,
            'featureids' => $featureids,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $plan = plan_api::get((int) $params['planid'], false);
        if (!$plan) {
            throw new \moodle_exception('error:plannotfound', 'local_moderncommerce');
        }

        $ids = array_values(array_unique(array_map('intval', $params['featureids'])));
        feature_api::set_plan_features((int) $plan->id, $ids);

        return [
            'success' => true,
            'message' => get_string('masterfeaturesaved', 'local_moderncommerce'),
            'features' => self::plan_features((int) $plan->id),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for save_plan_features.
     *
     * @return external_single_structure
     */
    public static function save_plan_features_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the change succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'features' => new external_multiple_structure(self::feature_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for add_plan_access.
     *
     * @return external_function_parameters
     */
    public static function add_plan_access_parameters(): external_function_parameters {
        return new external_function_parameters([
            'planid' => new external_value(PARAM_INT, 'Subscription plan ID.', VALUE_REQUIRED),
            'accesstype' => new external_value(PARAM_ALPHA, 'course, category, or bundle.', VALUE_REQUIRED),
            'targetid' => new external_value(PARAM_INT, 'Course, category, or bundle ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Add an access rule to a plan.
     *
     * @param int $planid Plan ID.
     * @param string $accesstype Access type.
     * @param int $targetid Target ID.
     * @return array
     */
    public static function add_plan_access(int $planid, string $accesstype, int $targetid): array {
        $params = self::validate_parameters(self::add_plan_access_parameters(), [
            'planid' => $planid,
            'accesstype' => $accesstype,
            'targetid' => $targetid,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $plan = plan_api::get((int) $params['planid'], false);
        if (!$plan) {
            throw new \moodle_exception('error:plannotfound', 'local_moderncommerce');
        }
        if (!in_array($params['accesstype'], self::TYPES, true)) {
            throw new \moodle_exception('invalidrequest', 'error');
        }
        if (!self::target_exists($params['accesstype'], (int) $params['targetid'])) {
            throw new \moodle_exception('invalidrequest', 'error', '', 'Access target not found.');
        }

        plan_api::add_access_rule((int) $plan->id, $params['accesstype'], (int) $params['targetid']);

        return self::access_result((int) $plan->id, get_string('accessruleadded', 'local_moderncommerce'));
    }

    /**
     * Return structure for add_plan_access.
     *
     * @return external_single_structure
     */
    public static function add_plan_access_returns(): external_single_structure {
        return self::access_result_structure();
    }

    /**
     * Parameters for remove_plan_access.
     *
     * @return external_function_parameters
     */
    public static function remove_plan_access_parameters(): external_function_parameters {
        return new external_function_parameters([
            'planid' => new external_value(PARAM_INT, 'Subscription plan ID.', VALUE_REQUIRED),
            'accesstype' => new external_value(PARAM_ALPHA, 'course, category, or bundle.', VALUE_REQUIRED),
            'targetid' => new external_value(PARAM_INT, 'Course, category, or bundle ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Remove an access rule from a plan.
     *
     * @param int $planid Plan ID.
     * @param string $accesstype Access type.
     * @param int $targetid Target ID.
     * @return array
     */
    public static function remove_plan_access(int $planid, string $accesstype, int $targetid): array {
        $params = self::validate_parameters(self::remove_plan_access_parameters(), [
            'planid' => $planid,
            'accesstype' => $accesstype,
            'targetid' => $targetid,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $plan = plan_api::get((int) $params['planid'], false);
        if (!$plan) {
            throw new \moodle_exception('error:plannotfound', 'local_moderncommerce');
        }

        plan_api::remove_access_rule((int) $plan->id, $params['accesstype'], (int) $params['targetid']);

        return self::access_result((int) $plan->id, get_string('accessruledeleted', 'local_moderncommerce'));
    }

    /**
     * Return structure for remove_plan_access.
     *
     * @return external_single_structure
     */
    public static function remove_plan_access_returns(): external_single_structure {
        return self::access_result_structure();
    }

    /**
     * Build the shared add/remove result payload.
     *
     * @param int $planid Plan ID.
     * @param string $message Result message.
     * @return array
     */
    private static function access_result(int $planid, string $message): array {
        [$rules, $totalcourses] = self::format_rules($planid);
        return [
            'success' => true,
            'message' => $message,
            'rules' => $rules,
            'totalcourses' => $totalcourses,
            'warnings' => [],
        ];
    }

    /**
     * Format a plan's access rules and compute the unique granted-course total.
     *
     * @param int $planid Plan ID.
     * @return array [rules, totalcourses]
     */
    private static function format_rules(int $planid): array {
        global $DB;

        $records = $DB->get_records('local_moderncommerce_subscription_access_rules', ['planid' => $planid], 'id ASC');
        $rules = [];
        $allcourses = [];

        foreach ($records as $record) {
            $courseids = self::resolve_courseids((string) $record->access_type, (int) $record->target_id);
            $allcourses = array_merge($allcourses, $courseids);
            $rules[] = [
                'id' => (int) $record->id,
                'accesstype' => (string) $record->access_type,
                'targetid' => (int) $record->target_id,
                'targetname' => self::target_name((string) $record->access_type, (int) $record->target_id),
                'coursecount' => count($courseids),
            ];
        }

        return [$rules, count(array_unique($allcourses))];
    }

    /**
     * Resolve an access rule to the list of Moodle course IDs it grants.
     *
     * @param string $accesstype Access type.
     * @param int $targetid Target ID.
     * @return int[]
     */
    private static function resolve_courseids(string $accesstype, int $targetid): array {
        global $DB;

        switch ($accesstype) {
            case 'course':
                return $DB->record_exists('course', ['id' => $targetid]) ? [$targetid] : [];
            case 'category':
                return array_map('intval', $DB->get_fieldset_select(
                    'course',
                    'id',
                    'category = :catid AND visible = 1 AND id != 1',
                    ['catid' => $targetid]
                ));
            case 'bundle':
                return array_map('intval', $DB->get_fieldset_select(
                    'local_moderncommerce_product_courses',
                    'courseid',
                    'productid = :productid AND relationtype = :relationtype',
                    ['productid' => $targetid, 'relationtype' => 'included']
                ));
            default:
                return [];
        }
    }

    /**
     * Resolve a friendly name for an access target.
     *
     * @param string $accesstype Access type.
     * @param int $targetid Target ID.
     * @return string
     */
    private static function target_name(string $accesstype, int $targetid): string {
        global $DB;

        switch ($accesstype) {
            case 'course':
                $course = $DB->get_record('course', ['id' => $targetid], 'id, fullname');
                return $course ? format_string($course->fullname) : get_string('unknowncourse', 'local_moderncommerce');
            case 'category':
                $cat = $DB->get_record('course_categories', ['id' => $targetid], 'id, name');
                return $cat ? format_string($cat->name) : get_string('unknowncategory', 'local_moderncommerce');
            case 'bundle':
                $bundle = $DB->get_record('local_moderncommerce_products', ['id' => $targetid], 'id, name');
                return $bundle ? format_string($bundle->name) : get_string('unknownbundle', 'local_moderncommerce');
            default:
                return '';
        }
    }

    /**
     * Whether an access target exists.
     *
     * @param string $accesstype Access type.
     * @param int $targetid Target ID.
     * @return bool
     */
    private static function target_exists(string $accesstype, int $targetid): bool {
        global $DB;

        switch ($accesstype) {
            case 'course':
                return $DB->record_exists('course', ['id' => $targetid]) && $targetid != SITEID;
            case 'category':
                return $DB->record_exists('course_categories', ['id' => $targetid]);
            case 'bundle':
                return $DB->record_exists_select(
                    'local_moderncommerce_products',
                    "id = :id AND producttype IN ('bundle', 'program')",
                    ['id' => $targetid]
                );
            default:
                return false;
        }
    }

    /**
     * Category options for the picker.
     *
     * @return array
     */
    private static function category_options(): array {
        global $DB;

        $categories = $DB->get_records_select('course_categories', 'visible = 1', null, 'sortorder ASC, name ASC', 'id, name');
        $options = [];
        foreach ($categories as $category) {
            $options[] = [
                'id' => (int) $category->id,
                'name' => format_string($category->name),
                'coursecount' => count(self::resolve_courseids('category', (int) $category->id)),
            ];
        }
        return $options;
    }

    /**
     * Bundle options for the picker (canonical bundle/program products).
     *
     * @return array
     */
    private static function bundle_options(): array {
        global $DB;

        $bundles = $DB->get_records_select(
            'local_moderncommerce_products',
            "producttype IN ('bundle', 'program') AND status = 'active'",
            null,
            'name ASC',
            'id, name'
        );
        $options = [];
        foreach ($bundles as $bundle) {
            $options[] = [
                'id' => (int) $bundle->id,
                'name' => format_string($bundle->name),
                'coursecount' => count(self::resolve_courseids('bundle', (int) $bundle->id)),
            ];
        }
        return $options;
    }

    /**
     * Build the per-plan master-feature list (all active features with an enabled flag).
     *
     * @param int $planid Plan ID.
     * @return array
     */
    private static function plan_features(int $planid): array {
        $features = feature_api::get_all(true);
        $enabled = array_flip(array_map('intval', feature_api::get_plan_feature_ids($planid)));
        $out = [];
        foreach ($features as $feature) {
            $out[] = [
                'id' => (int) $feature->id,
                'name' => format_string($feature->name),
                'icon' => (string) ($feature->icon ?: 'check-circle'),
                'enabled' => isset($enabled[(int) $feature->id]),
            ];
        }
        return $out;
    }

    /**
     * Format a plan for the access screen header.
     *
     * @param \stdClass $plan Plan record.
     * @return array
     */
    private static function format_plan(\stdClass $plan): array {
        $price = (float) ($plan->price ?? 0);

        return [
            'id' => (int) $plan->id,
            'name' => (string) $plan->name,
            'code' => (string) ($plan->code ?? ''),
            'billingcycle' => (string) ($plan->billing_cycle ?? 'monthly'),
            'displayprice' => self::format_price($price),
            'status' => (string) ($plan->status ?? 'active'),
        ];
    }

    /**
     * Format a price using the Modern Commerce site currency helper.
     *
     * Always formats in the site's configured currency (symbol, position,
     * decimals) — never a per-record stored currency, which can go stale when
     * the admin changes the site currency.
     *
     * @param float $amount Amount.
     * @return string
     */
    private static function format_price(float $amount): string {
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            return \local_moderncommerce\services\pricing_service::format_price($amount);
        }
        $currency = get_config('local_moderncommerce', 'primary_currency') ?: 'USD';
        return $currency . ' ' . number_format($amount, 2);
    }

    /**
     * Require a system capability.
     *
     * @param string $capability Capability.
     * @return context_system
     */
    private static function require_system_capability(string $capability): context_system {
        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability($capability, $context);
        return $context;
    }

    /**
     * Shared add/remove result structure.
     *
     * @return external_single_structure
     */
    private static function access_result_structure(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the change succeeded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'rules' => new external_multiple_structure(self::rule_structure()),
            'totalcourses' => new external_value(PARAM_INT, 'Unique courses granted by the plan.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Access rule structure.
     *
     * @return external_single_structure
     */
    private static function rule_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Rule ID.'),
            'accesstype' => new external_value(PARAM_ALPHA, 'Access type.'),
            'targetid' => new external_value(PARAM_INT, 'Target ID.'),
            'targetname' => new external_value(PARAM_TEXT, 'Target display name.'),
            'coursecount' => new external_value(PARAM_INT, 'Courses granted by this rule.'),
        ]);
    }

    /**
     * Plan feature structure.
     *
     * @return external_single_structure
     */
    private static function feature_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Feature ID.'),
            'name' => new external_value(PARAM_TEXT, 'Feature name.'),
            'icon' => new external_value(PARAM_TEXT, 'Bootstrap icon name.'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether enabled for this plan.'),
        ]);
    }

    /**
     * Picker option structure.
     *
     * @return external_single_structure
     */
    private static function option_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Target ID.'),
            'name' => new external_value(PARAM_TEXT, 'Target name.'),
            'coursecount' => new external_value(PARAM_INT, 'Courses contained.'),
        ]);
    }

    /**
     * Plan header structure.
     *
     * @return external_single_structure
     */
    private static function plan_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Plan ID.'),
            'name' => new external_value(PARAM_TEXT, 'Plan name.'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Plan code.'),
            'billingcycle' => new external_value(PARAM_ALPHANUMEXT, 'Billing cycle.'),
            'displayprice' => new external_value(PARAM_TEXT, 'Formatted price.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status.'),
        ]);
    }
}
