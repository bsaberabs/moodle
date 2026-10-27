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
 * External APIs consumed by Modern Commerce React subscription admin screens.
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
use local_moderncommerce\subscription\services\notification_service;
use local_moderncommerce\subscription\services\subscription_service;

/**
 * Admin webservice methods for the endpoint-only subscription add-on.
 */
class admin_external extends external_api {
    /** @var int Maximum rows returned by paged endpoints. */
    private const MAX_PER_PAGE = 100;

    /** @var int Maximum subscription keys generated in one request. */
    private const MAX_KEYS = 1000;

    /**
     * Parameters for get_overview.
     *
     * @return external_function_parameters
     */
    public static function get_overview_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Plan search text.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Plan status filter.', VALUE_DEFAULT, ''),
            'billingcycle' => new external_value(PARAM_ALPHANUMEXT, 'Billing cycle filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * Get plan overview data.
     *
     * @param string $search Search text.
     * @param string $status Status filter.
     * @param string $billingcycle Billing cycle filter.
     * @param int $page Page.
     * @param int $perpage Rows per page.
     * @return array
     */
    public static function get_overview(
        string $search = '',
        string $status = '',
        string $billingcycle = '',
        int $page = 0,
        int $perpage = 10
    ): array {
        global $DB;

        $params = self::validate_parameters(self::get_overview_parameters(), [
            'search' => $search,
            'status' => $status,
            'billingcycle' => $billingcycle,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $params['page'] = max(0, (int) $params['page']);
        $params['perpage'] = self::normalise_perpage((int) $params['perpage']);

        [$where, $sqlparams] = self::plan_filter_sql($params);
        $total = (int) $DB->count_records_select('local_moderncommerce_subscription_plans', $where, $sqlparams);
        $plans = $DB->get_records_select(
            'local_moderncommerce_subscription_plans',
            $where,
            $sqlparams,
            'sortorder ASC, name ASC',
            '*',
            $params['page'] * $params['perpage'],
            $params['perpage']
        );

        return [
            'plans' => array_values(array_map([self::class, 'format_plan'], $plans)),
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'stats' => self::plan_stats(),
            'currency' => self::currency_data(),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_overview.
     *
     * @return external_single_structure
     */
    public static function get_overview_returns(): external_single_structure {
        return new external_single_structure([
            'plans' => new external_multiple_structure(self::plan_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching plans.'),
            'page' => new external_value(PARAM_INT, 'Current page.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.'),
            'stats' => self::plan_stats_structure(),
            'currency' => self::currency_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for save_plan.
     *
     * @return external_function_parameters
     */
    public static function save_plan_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Plan ID, or 0 for a new plan.', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Plan name.', VALUE_REQUIRED),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Plan code.', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_RAW, 'Plan description.', VALUE_DEFAULT, ''),
            'billingcycle' => new external_value(PARAM_ALPHANUMEXT, 'monthly or yearly.', VALUE_DEFAULT, 'monthly'),
            'price' => new external_value(PARAM_FLOAT, 'Plan price.', VALUE_DEFAULT, 0),
            'promoprice' => new external_value(PARAM_FLOAT, 'Promotional price, or 0 for none.', VALUE_DEFAULT, 0),
            'promoenddate' => new external_value(PARAM_INT, 'Promo end timestamp, or 0.', VALUE_DEFAULT, 0),
            'trialdays' => new external_value(PARAM_INT, 'Trial days.', VALUE_DEFAULT, 0),
            'graceperioddays' => new external_value(PARAM_INT, 'Grace period days.', VALUE_DEFAULT, 7),
            'maxseats' => new external_value(PARAM_INT, 'Maximum seats.', VALUE_DEFAULT, 0),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'active or inactive.', VALUE_DEFAULT, 'active'),
            'sortorder' => new external_value(PARAM_INT, 'Sort order.', VALUE_DEFAULT, 0),
            'featured' => new external_value(PARAM_BOOL, 'Featured flag.', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Create or update a plan.
     *
     * @param int $id Plan ID.
     * @param string $name Name.
     * @param string $code Code.
     * @param string $description Description.
     * @param string $billingcycle Billing cycle.
     * @param float $price Price.
     * @param float $promoprice Promo price.
     * @param int $promoenddate Promo end timestamp.
     * @param int $trialdays Trial days.
     * @param int $graceperioddays Grace period days.
     * @param int $maxseats Max seats.
     * @param string $status Status.
     * @param int $sortorder Sort order.
     * @param bool $featured Featured flag.
     * @return array
     */
    public static function save_plan(
        int $id = 0,
        string $name = '',
        string $code = '',
        string $description = '',
        string $billingcycle = 'monthly',
        float $price = 0,
        float $promoprice = 0,
        int $promoenddate = 0,
        int $trialdays = 0,
        int $graceperioddays = 7,
        int $maxseats = 0,
        string $status = 'active',
        int $sortorder = 0,
        bool $featured = false
    ): array {
        $params = self::validate_parameters(self::save_plan_parameters(), [
            'id' => $id,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'billingcycle' => $billingcycle,
            'price' => $price,
            'promoprice' => $promoprice,
            'promoenddate' => $promoenddate,
            'trialdays' => $trialdays,
            'graceperioddays' => $graceperioddays,
            'maxseats' => $maxseats,
            'status' => $status,
            'sortorder' => $sortorder,
            'featured' => $featured,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $data = (object) [
            'name' => trim($params['name']),
            'code' => strtoupper(trim($params['code'])),
            'description' => $params['description'],
            'billing_cycle' => self::normalise_choice($params['billingcycle'], ['monthly', 'yearly'], 'monthly'),
            'price' => max(0, (float) $params['price']),
            'promo_price' => (float) $params['promoprice'] > 0 ? (float) $params['promoprice'] : null,
            'promo_end_date' => (int) $params['promoenddate'] > 0 ? (int) $params['promoenddate'] : null,
            'trial_days' => max(0, (int) $params['trialdays']),
            'grace_period_days' => max(0, (int) $params['graceperioddays']),
            'max_seats' => max(0, (int) $params['maxseats']),
            'status' => self::normalise_choice($params['status'], ['active', 'inactive'], 'active'),
            'sortorder' => max(0, (int) $params['sortorder']),
            'featured' => !empty($params['featured']) ? 1 : 0,
        ];

        if ((int) $params['id'] > 0) {
            plan_api::update((int) $params['id'], $data);
            $planid = (int) $params['id'];
            $message = 'Plan updated.';
        } else {
            $planid = plan_api::create($data);
            $message = 'Plan created.';
        }

        return [
            'success' => true,
            'planid' => $planid,
            'message' => $message,
            'plan' => self::format_plan(plan_api::get($planid, false)),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for save_plan.
     *
     * @return external_single_structure
     */
    public static function save_plan_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the plan was saved.'),
            'planid' => new external_value(PARAM_INT, 'Plan ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'plan' => self::plan_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for delete_plan.
     *
     * @return external_function_parameters
     */
    public static function delete_plan_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Plan ID.', VALUE_REQUIRED),
            'force' => new external_value(PARAM_BOOL, 'Force delete even with subscribers.', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Delete a plan.
     *
     * @param int $id Plan ID.
     * @param bool $force Force flag.
     * @return array
     */
    public static function delete_plan(int $id, bool $force = false): array {
        $params = self::validate_parameters(self::delete_plan_parameters(), [
            'id' => $id,
            'force' => $force,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');
        plan_api::delete((int) $params['id'], !empty($params['force']));

        return self::simple_result(true, 'Plan deleted.');
    }

    /**
     * Return structure for delete_plan.
     *
     * @return external_single_structure
     */
    public static function delete_plan_returns(): external_single_structure {
        return self::simple_result_structure();
    }

    /**
     * Parameters for get_feature_matrix.
     *
     * @return external_function_parameters
     */
    public static function get_feature_matrix_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get feature matrix.
     *
     * @return array
     */
    public static function get_feature_matrix(): array {
        self::require_system_capability('local/moderncommerce:managesubscriptionfeatures');
        $data = feature_api::get_matrix_data();
        $mappings = [];

        foreach ($data['matrix'] as $featureid => $planstates) {
            foreach ($planstates as $planid => $enabled) {
                $mappings[] = [
                    'featureid' => (int) $featureid,
                    'planid' => (int) $planid,
                    'enabled' => (bool) $enabled,
                ];
            }
        }

        return [
            'features' => array_values(array_map([self::class, 'format_feature'], $data['features'])),
            'plans' => array_values(array_map([self::class, 'format_plan'], $data['plans'])),
            'mappings' => $mappings,
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_feature_matrix.
     *
     * @return external_single_structure
     */
    public static function get_feature_matrix_returns(): external_single_structure {
        return new external_single_structure([
            'features' => new external_multiple_structure(self::feature_structure()),
            'plans' => new external_multiple_structure(self::plan_structure()),
            'mappings' => new external_multiple_structure(self::feature_mapping_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for save_feature.
     *
     * @return external_function_parameters
     */
    public static function save_feature_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Feature ID, or 0 for new.', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Feature name.', VALUE_REQUIRED),
            'description' => new external_value(PARAM_RAW, 'Feature description.', VALUE_DEFAULT, ''),
            'icon' => new external_value(PARAM_ALPHANUMEXT, 'Bootstrap icon name.', VALUE_DEFAULT, 'check-circle'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'active or inactive.', VALUE_DEFAULT, 'active'),
        ]);
    }

    /**
     * Save a master feature.
     *
     * @param int $id Feature ID.
     * @param string $name Name.
     * @param string $description Description.
     * @param string $icon Icon.
     * @param string $status Status.
     * @return array
     */
    public static function save_feature(
        int $id = 0,
        string $name = '',
        string $description = '',
        string $icon = 'check-circle',
        string $status = 'active'
    ): array {
        $params = self::validate_parameters(self::save_feature_parameters(), [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'icon' => $icon,
            'status' => $status,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionfeatures');

        $status = self::normalise_choice($params['status'], ['active', 'inactive'], 'active');
        if ((int) $params['id'] > 0) {
            feature_api::update((int) $params['id'], $params['name'], $params['description'], $params['icon']);
            feature_api::set_status((int) $params['id'], $status);
            $featureid = (int) $params['id'];
            $message = 'Feature updated.';
        } else {
            $featureid = feature_api::create($params['name'], $params['description'], $params['icon']);
            feature_api::set_status($featureid, $status);
            $message = 'Feature created.';
        }

        return [
            'success' => true,
            'featureid' => $featureid,
            'message' => $message,
            'feature' => self::format_feature(feature_api::get($featureid)),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for save_feature.
     *
     * @return external_single_structure
     */
    public static function save_feature_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the feature was saved.'),
            'featureid' => new external_value(PARAM_INT, 'Feature ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'feature' => self::feature_structure(),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for delete_feature.
     *
     * @return external_function_parameters
     */
    public static function delete_feature_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Feature ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Delete a feature.
     *
     * @param int $id Feature ID.
     * @return array
     */
    public static function delete_feature(int $id): array {
        $params = self::validate_parameters(self::delete_feature_parameters(), ['id' => $id]);
        self::require_system_capability('local/moderncommerce:managesubscriptionfeatures');
        feature_api::delete((int) $params['id']);

        return self::simple_result(true, 'Feature deleted.');
    }

    /**
     * Return structure for delete_feature.
     *
     * @return external_single_structure
     */
    public static function delete_feature_returns(): external_single_structure {
        return self::simple_result_structure();
    }

    /**
     * Parameters for save_feature_matrix.
     *
     * @return external_function_parameters
     */
    public static function save_feature_matrix_parameters(): external_function_parameters {
        return new external_function_parameters([
            'mappings' => new external_multiple_structure(self::feature_mapping_structure()),
        ]);
    }

    /**
     * Save feature matrix mappings.
     *
     * @param array $mappings Matrix mappings.
     * @return array
     */
    public static function save_feature_matrix(array $mappings): array {
        $params = self::validate_parameters(self::save_feature_matrix_parameters(), ['mappings' => $mappings]);
        self::require_system_capability('local/moderncommerce:managesubscriptionfeatures');

        $matrixdata = [];
        foreach ($params['mappings'] as $mapping) {
            $featureid = (int) $mapping['featureid'];
            $planid = (int) $mapping['planid'];
            if ($featureid <= 0 || $planid <= 0) {
                continue;
            }
            $matrixdata[$featureid][$planid] = !empty($mapping['enabled']);
        }
        feature_api::save_matrix($matrixdata);

        return self::simple_result(true, 'Feature matrix saved.');
    }

    /**
     * Return structure for save_feature_matrix.
     *
     * @return external_single_structure
     */
    public static function save_feature_matrix_returns(): external_single_structure {
        return self::simple_result_structure();
    }

    /**
     * Parameters for list_subscriptions.
     *
     * @return external_function_parameters
     */
    public static function list_subscriptions_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search name or email.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Subscription status.', VALUE_DEFAULT, ''),
            'planid' => new external_value(PARAM_INT, 'Plan ID filter.', VALUE_DEFAULT, 0),
            'billingcycle' => new external_value(PARAM_ALPHANUMEXT, 'Billing cycle filter.', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * List subscriptions.
     *
     * @param string $search Search.
     * @param string $status Status.
     * @param int $planid Plan ID.
     * @param string $billingcycle Billing cycle.
     * @param int $page Page.
     * @param int $perpage Per page.
     * @return array
     */
    public static function list_subscriptions(
        string $search = '',
        string $status = '',
        int $planid = 0,
        string $billingcycle = '',
        int $page = 0,
        int $perpage = 10
    ): array {
        global $DB;

        $params = self::validate_parameters(self::list_subscriptions_parameters(), [
            'search' => $search,
            'status' => $status,
            'planid' => $planid,
            'billingcycle' => $billingcycle,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        self::require_system_capability('local/moderncommerce:viewsubscribers');

        $params['page'] = max(0, (int) $params['page']);
        $params['perpage'] = self::normalise_perpage((int) $params['perpage']);
        [$where, $sqlparams] = self::subscription_filter_sql($params);

        $countsql = "SELECT COUNT(1)
                       FROM {local_moderncommerce_user_subscriptions} s
                       JOIN {user} u ON u.id = s.userid
                       JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                      WHERE {$where}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $sql = "SELECT s.*,
                       u.firstname,
                       u.lastname,
                       u.email,
                       p.name AS planname,
                       p.billing_cycle AS billingcycle,
                       p.price,
                       p.currency
                  FROM {local_moderncommerce_user_subscriptions} s
                  JOIN {user} u ON u.id = s.userid
                  JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                 WHERE {$where}
              ORDER BY s.timecreated DESC, s.id DESC";
        $records = $DB->get_records_sql(
            $sql,
            $sqlparams,
            $params['page'] * $params['perpage'],
            $params['perpage']
        );

        return [
            'items' => array_values(array_map([self::class, 'format_subscription'], $records)),
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'stats' => self::subscription_stats(),
            'plans' => array_values(array_map([self::class, 'format_plan'], plan_api::get_all_admin())),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for list_subscriptions.
     *
     * @return external_single_structure
     */
    public static function list_subscriptions_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::subscription_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching subscriptions.'),
            'page' => new external_value(PARAM_INT, 'Current page.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.'),
            'stats' => self::subscription_stats_structure(),
            'plans' => new external_multiple_structure(self::plan_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for get_subscription.
     *
     * @return external_function_parameters
     */
    public static function get_subscription_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Subscription ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get one subscription detail.
     *
     * @param int $id Subscription ID.
     * @return array
     */
    public static function get_subscription(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::get_subscription_parameters(), ['id' => $id]);
        self::require_system_capability('local/moderncommerce:viewsubscribers');

        $sql = "SELECT s.*,
                       u.firstname,
                       u.lastname,
                       u.email,
                       p.name AS planname,
                       p.billing_cycle AS billingcycle,
                       p.price,
                       p.currency
                  FROM {local_moderncommerce_user_subscriptions} s
                  JOIN {user} u ON u.id = s.userid
                  JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                 WHERE s.id = :id";
        $subscription = $DB->get_record_sql($sql, ['id' => (int) $params['id']], MUST_EXIST);
        $history = [];

        $historyrecords = $DB->get_records(
            'local_moderncommerce_subscription_history',
            ['subscriptionid' => (int) $params['id']],
            'timecreated DESC, id DESC'
        );
        foreach ($historyrecords as $record) {
            $history[] = [
                'id' => (int) $record->id,
                'action' => (string) $record->action,
                'oldplanid' => (int) ($record->old_planid ?? 0),
                'newplanid' => (int) ($record->new_planid ?? 0),
                'amountpaid' => (float) ($record->amount_paid ?? 0),
                'notes' => (string) ($record->notes ?? ''),
                'timecreated' => (int) $record->timecreated,
            ];
        }

        return [
            'subscription' => self::format_subscription($subscription),
            'history' => $history,
            'access' => self::subscription_access((int) $subscription->id),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for get_subscription.
     *
     * @return external_single_structure
     */
    public static function get_subscription_returns(): external_single_structure {
        return new external_single_structure([
            'subscription' => self::subscription_structure(),
            'history' => new external_multiple_structure(self::history_structure()),
            'access' => new external_multiple_structure(self::access_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for subscription_action.
     *
     * @return external_function_parameters
     */
    public static function subscription_action_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Subscription ID.', VALUE_REQUIRED),
            'action' => new external_value(
                PARAM_ALPHAEXT,
                'cancel, reactivate, suspend, renew, autorenew_on, autorenew_off.'
            ),
            'reason' => new external_value(PARAM_TEXT, 'Reason for cancellation.', VALUE_DEFAULT, ''),
            'planid' => new external_value(PARAM_INT, 'Optional new plan ID for renewal.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Run a subscription action.
     *
     * @param int $id Subscription ID.
     * @param string $action Action.
     * @param string $reason Reason.
     * @param int $planid Plan ID.
     * @return array
     */
    public static function subscription_action(
        int $id,
        string $action,
        string $reason = '',
        int $planid = 0
    ): array {
        $params = self::validate_parameters(self::subscription_action_parameters(), [
            'id' => $id,
            'action' => $action,
            'reason' => $reason,
            'planid' => $planid,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptions');

        $subscriptionid = (int) $params['id'];
        switch ($params['action']) {
            case 'cancel':
                subscription_service::cancel($subscriptionid, $params['reason']);
                $message = 'Subscription cancelled.';
                break;
            case 'reactivate':
                subscription_service::reactivate($subscriptionid);
                $message = 'Subscription reactivated.';
                break;
            case 'suspend':
                subscription_service::suspend($subscriptionid);
                $message = 'Subscription suspended.';
                break;
            case 'renew':
                subscription_service::renew($subscriptionid, (int) $params['planid'] ?: null);
                $message = 'Subscription renewed.';
                break;
            case 'autorenew_on':
                \local_moderncommerce\subscription\api\subscription_api::set_auto_renew($subscriptionid, true);
                $message = 'Auto renew enabled.';
                break;
            case 'autorenew_off':
                \local_moderncommerce\subscription\api\subscription_api::set_auto_renew($subscriptionid, false);
                $message = 'Auto renew disabled.';
                break;
            default:
                throw new \moodle_exception('invalidrequest', 'error');
        }

        return self::simple_result(true, $message);
    }

    /**
     * Return structure for subscription_action.
     *
     * @return external_single_structure
     */
    public static function subscription_action_returns(): external_single_structure {
        return self::simple_result_structure();
    }

    /**
     * Parameters for list_keys.
     *
     * @return external_function_parameters
     */
    public static function list_keys_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Key search.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Key status.', VALUE_DEFAULT, ''),
            'planid' => new external_value(PARAM_INT, 'Plan ID filter.', VALUE_DEFAULT, 0),
            'page' => new external_value(PARAM_INT, 'Zero-based page.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.', VALUE_DEFAULT, 10),
        ]);
    }

    /**
     * List subscription keys.
     *
     * @param string $search Search.
     * @param string $status Status.
     * @param int $planid Plan ID.
     * @param int $page Page.
     * @param int $perpage Per page.
     * @return array
     */
    public static function list_keys(
        string $search = '',
        string $status = '',
        int $planid = 0,
        int $page = 0,
        int $perpage = 10
    ): array {
        global $DB;

        $params = self::validate_parameters(self::list_keys_parameters(), [
            'search' => $search,
            'status' => $status,
            'planid' => $planid,
            'page' => $page,
            'perpage' => $perpage,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $params['page'] = max(0, (int) $params['page']);
        $params['perpage'] = self::normalise_perpage((int) $params['perpage']);
        [$where, $sqlparams] = self::key_filter_sql($params);

        $countsql = "SELECT COUNT(1)
                       FROM {local_moderncommerce_subscription_keys} k
                       JOIN {local_moderncommerce_subscription_plans} p ON p.id = k.planid
                      WHERE {$where}";
        $total = (int) $DB->count_records_sql($countsql, $sqlparams);

        $sql = "SELECT k.*,
                       p.name AS planname,
                       p.billing_cycle AS billingcycle,
                       (SELECT COUNT(1)
                          FROM {local_moderncommerce_subscription_key_usage} ku
                         WHERE ku.keyid = k.id) AS actualused
                  FROM {local_moderncommerce_subscription_keys} k
                  JOIN {local_moderncommerce_subscription_plans} p ON p.id = k.planid
                 WHERE {$where}
              ORDER BY k.timecreated DESC, k.id DESC";
        $records = $DB->get_records_sql(
            $sql,
            $sqlparams,
            $params['page'] * $params['perpage'],
            $params['perpage']
        );

        return [
            'items' => array_values(array_map([self::class, 'format_key'], $records)),
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'stats' => self::key_stats(),
            'plans' => array_values(array_map([self::class, 'format_plan'], plan_api::get_all_admin())),
            'warnings' => [],
        ];
    }

    /**
     * Return structure for list_keys.
     *
     * @return external_single_structure
     */
    public static function list_keys_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::key_structure()),
            'total' => new external_value(PARAM_INT, 'Total matching keys.'),
            'page' => new external_value(PARAM_INT, 'Current page.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page.'),
            'stats' => self::key_stats_structure(),
            'plans' => new external_multiple_structure(self::plan_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for generate_keys.
     *
     * @return external_function_parameters
     */
    public static function generate_keys_parameters(): external_function_parameters {
        return new external_function_parameters([
            'planid' => new external_value(PARAM_INT, 'Subscription plan ID.', VALUE_REQUIRED),
            'quantity' => new external_value(PARAM_INT, 'Number of keys to generate.', VALUE_DEFAULT, 1),
            'value' => new external_value(PARAM_FLOAT, 'Key value.', VALUE_DEFAULT, 0),
            'durationdays' => new external_value(PARAM_INT, 'Duration override in days, or 0.', VALUE_DEFAULT, 0),
            'maxuses' => new external_value(PARAM_INT, 'Maximum uses, or 0 for unlimited.', VALUE_DEFAULT, 1),
            'maxusesperuser' => new external_value(PARAM_INT, 'Max uses per user.', VALUE_DEFAULT, 1),
            'validfrom' => new external_value(PARAM_INT, 'Start timestamp, or 0.', VALUE_DEFAULT, 0),
            'validuntil' => new external_value(PARAM_INT, 'Expiry timestamp, or 0.', VALUE_DEFAULT, 0),
            'batchname' => new external_value(PARAM_TEXT, 'Batch name.', VALUE_DEFAULT, ''),
            'notes' => new external_value(PARAM_TEXT, 'Notes.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Generate subscription keys.
     *
     * @param int $planid Plan ID.
     * @param int $quantity Quantity.
     * @param float $value Value.
     * @param int $durationdays Duration days.
     * @param int $maxuses Max uses.
     * @param int $maxusesperuser Max uses per user.
     * @param int $validfrom Start timestamp.
     * @param int $validuntil Expiry timestamp.
     * @param string $batchname Batch name.
     * @param string $notes Notes.
     * @return array
     */
    public static function generate_keys(
        int $planid,
        int $quantity = 1,
        float $value = 0,
        int $durationdays = 0,
        int $maxuses = 1,
        int $maxusesperuser = 1,
        int $validfrom = 0,
        int $validuntil = 0,
        string $batchname = '',
        string $notes = ''
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::generate_keys_parameters(), [
            'planid' => $planid,
            'quantity' => $quantity,
            'value' => $value,
            'durationdays' => $durationdays,
            'maxuses' => $maxuses,
            'maxusesperuser' => $maxusesperuser,
            'validfrom' => $validfrom,
            'validuntil' => $validuntil,
            'batchname' => $batchname,
            'notes' => $notes,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $plan = plan_api::get((int) $params['planid'], false);
        if (!$plan) {
            throw new \moodle_exception('error:plannotfound', 'local_moderncommerce');
        }

        $quantity = max(1, min(self::MAX_KEYS, (int) $params['quantity']));
        $now = time();
        $batchid = 'SUB' . $now . strtoupper(substr(md5(random_string(20)), 0, 8));
        $generated = [];
        $keyids = [];

        $transaction = $DB->start_delegated_transaction();
        try {
            for ($i = 0; $i < $quantity; $i++) {
                $keycode = self::generate_key_code();
                $keyids[] = (int) $DB->insert_record('local_moderncommerce_subscription_keys', (object) [
                    'keycode' => $keycode,
                    'planid' => (int) $params['planid'],
                    'value' => max(0, (float) $params['value']),
                    'currency' => (string) ($plan->currency ?? self::currency_data()['code']),
                    'duration_days' => (int) $params['durationdays'] > 0 ? (int) $params['durationdays'] : null,
                    'maxuses' => (int) $params['maxuses'] > 0 ? (int) $params['maxuses'] : null,
                    'usedcount' => 0,
                    'maxusesperuser' => max(1, (int) $params['maxusesperuser']),
                    'batchid' => $batchid,
                    'batchname' => trim($params['batchname']),
                    'status' => 'active',
                    'startdate' => (int) $params['validfrom'] > 0 ? (int) $params['validfrom'] : null,
                    'expirydate' => (int) $params['validuntil'] > 0 ? (int) $params['validuntil'] : null,
                    'userids' => null,
                    'cohortids' => null,
                    'requiredemail' => null,
                    'notes' => trim($params['notes']),
                    'createdby' => (int) $USER->id,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $generated[] = $keycode;
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        \local_moderncommerce\audit\audit_service::record('subscription_keys_generated', 'subscription_key_batch', 0, [
            'newdata' => [
                'planid' => (int) $params['planid'],
                'quantity' => count($generated),
                'batchid' => $batchid,
                'keyids' => $keyids,
                'durationdays' => (int) $params['durationdays'],
                'maxuses' => (int) $params['maxuses'],
                'maxusesperuser' => (int) $params['maxusesperuser'],
            ],
            'severity' => 'warning',
        ]);

        return [
            'success' => true,
            'generated' => count($generated),
            'batchid' => $batchid,
            'keycodes' => $generated,
            'message' => 'Subscription keys generated.',
            'warnings' => [],
        ];
    }

    /**
     * Return structure for generate_keys.
     *
     * @return external_single_structure
     */
    public static function generate_keys_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether keys were generated.'),
            'generated' => new external_value(PARAM_INT, 'Number of generated keys.'),
            'batchid' => new external_value(PARAM_TEXT, 'Generated batch ID.'),
            'keycodes' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Generated key code.')),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for key_action.
     *
     * @return external_function_parameters
     */
    public static function key_action_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Key ID.', VALUE_REQUIRED),
            'action' => new external_value(PARAM_ALPHAEXT, 'activate, disable, delete.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Run a key action.
     *
     * @param int $id Key ID.
     * @param string $action Action.
     * @return array
     */
    public static function key_action(int $id, string $action): array {
        global $DB;

        $params = self::validate_parameters(self::key_action_parameters(), [
            'id' => $id,
            'action' => $action,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $keyid = (int) $params['id'];
        $key = $DB->get_record('local_moderncommerce_subscription_keys', ['id' => $keyid], '*', MUST_EXIST);

        if ($params['action'] === 'delete') {
            if ($DB->record_exists('local_moderncommerce_subscription_key_usage', ['keyid' => $keyid])) {
                throw new \moodle_exception('invalidrequest', 'error', '', 'Key has usage records.');
            }
            $DB->delete_records('local_moderncommerce_subscription_keys', ['id' => $keyid]);
            \local_moderncommerce\audit\audit_service::record('subscription_key_deleted', 'subscription_key', $keyid, [
                'olddata' => $key,
                'newdata' => null,
                'severity' => 'warning',
            ]);
            return self::simple_result(true, 'Key deleted.');
        }

        $status = $params['action'] === 'activate' ? 'active' : 'disabled';
        $DB->set_field('local_moderncommerce_subscription_keys', 'status', $status, ['id' => $keyid]);
        $DB->set_field('local_moderncommerce_subscription_keys', 'timemodified', time(), ['id' => $keyid]);

        \local_moderncommerce\audit\audit_service::record(
            $status === 'active' ? 'subscription_key_activated' : 'subscription_key_disabled',
            'subscription_key',
            $keyid,
            [
                'olddata' => ['status' => $key->status],
                'newdata' => ['status' => $status],
                'severity' => 'warning',
            ]
        );

        return self::simple_result(true, $status === 'active' ? 'Key activated.' : 'Key disabled.');
    }

    /**
     * Return structure for key_action.
     *
     * @return external_single_structure
     */
    public static function key_action_returns(): external_single_structure {
        return self::simple_result_structure();
    }

    /**
     * Parameters for list_email_templates.
     *
     * @return external_function_parameters
     */
    public static function list_email_templates_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * List subscription email template settings.
     *
     * @return array
     */
    public static function list_email_templates(): array {
        global $DB;

        self::require_system_capability('local/moderncommerce:managesubscriptionplans');
        notification_service::ensure_local_email_records();

        $records = $DB->get_records('local_moderncommerce_subscription_emailtpl', [], 'templatetype ASC');
        $types = notification_service::get_all_template_types();
        $templateoptions = self::shared_email_template_options();
        $items = [];

        foreach ($types as $type => $metadata) {
            $items[] = self::format_email_template(
                $type,
                $metadata,
                self::find_email_record($records, $type),
                $templateoptions
            );
        }

        $placeholders = [];
        foreach (notification_service::get_available_placeholders() as $token => $description) {
            $placeholders[] = [
                'token' => (string) $token,
                'description' => (string) $description,
            ];
        }

        return [
            'items' => $items,
            'placeholders' => $placeholders,
            'templateoptions' => $templateoptions,
            'warnings' => [],
        ];
    }

    /**
     * Return structure for list_email_templates.
     *
     * @return external_single_structure
     */
    public static function list_email_templates_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(self::email_template_structure()),
            'placeholders' => new external_multiple_structure(self::placeholder_structure()),
            'templateoptions' => new external_multiple_structure(self::email_template_option_structure()),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Parameters for save_email_template.
     *
     * @return external_function_parameters
     */
    public static function save_email_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Template type.', VALUE_REQUIRED),
            'enabled' => new external_value(PARAM_BOOL, 'Whether the template is enabled.', VALUE_DEFAULT, true),
            'templatekey' => new external_value(PARAM_TEXT, 'Selected shared template key.', VALUE_DEFAULT, ''),
            'usecustommessage' => new external_value(PARAM_BOOL, 'Use custom subject and message.', VALUE_DEFAULT, false),
            'customsubject' => new external_value(PARAM_TEXT, 'Custom subject.', VALUE_DEFAULT, ''),
            'custommessage' => new external_value(PARAM_RAW, 'Custom HTML message.', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Save subscription email template settings.
     *
     * @param string $type Template type.
     * @param bool $enabled Enabled.
     * @param string $templatekey Template key.
     * @param bool $usecustommessage Use custom.
     * @param string $customsubject Custom subject.
     * @param string $custommessage Custom message.
     * @return array
     */
    public static function save_email_template(
        string $type,
        bool $enabled = true,
        string $templatekey = '',
        bool $usecustommessage = false,
        string $customsubject = '',
        string $custommessage = ''
    ): array {
        global $DB;

        $params = self::validate_parameters(self::save_email_template_parameters(), [
            'type' => $type,
            'enabled' => $enabled,
            'templatekey' => $templatekey,
            'usecustommessage' => $usecustommessage,
            'customsubject' => $customsubject,
            'custommessage' => $custommessage,
        ]);
        self::require_system_capability('local/moderncommerce:managesubscriptionplans');

        $types = notification_service::get_all_template_types();
        if (!isset($types[$params['type']])) {
            throw new \moodle_exception('invalidrequest', 'error');
        }

        $now = time();
        $record = $DB->get_record('local_moderncommerce_subscription_emailtpl', ['templatetype' => $params['type']]);
        $data = (object) [
            'templatetype' => $params['type'],
            'enabled' => !empty($params['enabled']) ? 1 : 0,
            'template_key' => trim($params['templatekey']),
            'use_custom_message' => !empty($params['usecustommessage']) ? 1 : 0,
            'custom_subject' => trim($params['customsubject']),
            'custom_message' => $params['custommessage'],
            'timemodified' => $now,
        ];

        if ($record) {
            $data->id = (int) $record->id;
            $DB->update_record('local_moderncommerce_subscription_emailtpl', $data);
        } else {
            $data->timecreated = $now;
            $DB->insert_record('local_moderncommerce_subscription_emailtpl', $data);
        }

        return self::simple_result(true, 'Email template saved.');
    }

    /**
     * Return structure for save_email_template.
     *
     * @return external_single_structure
     */
    public static function save_email_template_returns(): external_single_structure {
        return self::simple_result_structure();
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
     * Build plan filter SQL.
     *
     * @param array $params Params.
     * @return array
     */
    private static function plan_filter_sql(array $params): array {
        global $DB;

        $where = ['1 = 1'];
        $sqlparams = [];
        if ($params['search'] !== '') {
            $where[] = '(' . $DB->sql_like('name', ':search', false)
                . ' OR ' . $DB->sql_like('code', ':searchcode', false) . ')';
            $sqlparams['search'] = '%' . $DB->sql_like_escape($params['search']) . '%';
            $sqlparams['searchcode'] = '%' . $DB->sql_like_escape($params['search']) . '%';
        }
        if ($params['status'] !== '') {
            $where[] = 'status = :status';
            $sqlparams['status'] = $params['status'];
        }
        if ($params['billingcycle'] !== '') {
            $where[] = 'billing_cycle = :billingcycle';
            $sqlparams['billingcycle'] = $params['billingcycle'];
        }

        return [implode(' AND ', $where), $sqlparams];
    }

    /**
     * Build subscription filter SQL.
     *
     * @param array $params Params.
     * @return array
     */
    private static function subscription_filter_sql(array $params): array {
        global $DB;

        $where = ['1 = 1'];
        $sqlparams = [];
        if ($params['search'] !== '') {
            $where[] = '(' . $DB->sql_like($DB->sql_concat('u.firstname', "' '", 'u.lastname'), ':search', false)
                . ' OR ' . $DB->sql_like('u.email', ':searchemail', false) . ')';
            $sqlparams['search'] = '%' . $DB->sql_like_escape($params['search']) . '%';
            $sqlparams['searchemail'] = '%' . $DB->sql_like_escape($params['search']) . '%';
        }
        if ($params['status'] !== '') {
            $where[] = 's.status = :status';
            $sqlparams['status'] = $params['status'];
        }
        if ((int) $params['planid'] > 0) {
            $where[] = 's.planid = :planid';
            $sqlparams['planid'] = (int) $params['planid'];
        }
        if ($params['billingcycle'] !== '') {
            $where[] = 'p.billing_cycle = :billingcycle';
            $sqlparams['billingcycle'] = $params['billingcycle'];
        }

        return [implode(' AND ', $where), $sqlparams];
    }

    /**
     * Build key filter SQL.
     *
     * @param array $params Params.
     * @return array
     */
    private static function key_filter_sql(array $params): array {
        global $DB;

        $where = ['1 = 1'];
        $sqlparams = [];
        if ($params['search'] !== '') {
            $where[] = $DB->sql_like('k.keycode', ':search', false);
            $sqlparams['search'] = '%' . $DB->sql_like_escape($params['search']) . '%';
        }
        if ($params['status'] !== '') {
            $where[] = 'k.status = :status';
            $sqlparams['status'] = $params['status'];
        }
        if ((int) $params['planid'] > 0) {
            $where[] = 'k.planid = :planid';
            $sqlparams['planid'] = (int) $params['planid'];
        }

        return [implode(' AND ', $where), $sqlparams];
    }

    /**
     * Format a plan for external return values.
     *
     * @param \stdClass|false $plan Plan record.
     * @return array
     */
    private static function format_plan($plan): array {
        if (!$plan) {
            $plan = (object) [];
        }
        $price = (float) ($plan->price ?? 0);
        $promoprice = (float) ($plan->promo_price ?? 0);
        // Always display in the site currency (helper), not the stale stored plan currency.
        $currency = self::currency_data()['code'];

        return [
            'id' => (int) ($plan->id ?? 0),
            'name' => (string) ($plan->name ?? ''),
            'code' => (string) ($plan->code ?? ''),
            'description' => (string) ($plan->description ?? ''),
            'billingcycle' => (string) ($plan->billing_cycle ?? 'monthly'),
            'price' => $price,
            'displayprice' => self::format_price($price),
            'promoprice' => $promoprice,
            'displaypromoprice' => $promoprice > 0 ? self::format_price($promoprice) : '',
            'promoenddate' => (int) ($plan->promo_end_date ?? 0),
            'currency' => $currency,
            'trialdays' => (int) ($plan->trial_days ?? 0),
            'graceperioddays' => (int) ($plan->grace_period_days ?? 0),
            'maxseats' => (int) ($plan->max_seats ?? 0),
            'sortorder' => (int) ($plan->sortorder ?? 0),
            'status' => (string) ($plan->status ?? ''),
            'featured' => !empty($plan->featured),
            'subscribercount' => !empty($plan->id) ? plan_api::get_subscriber_count((int) $plan->id) : 0,
            'timecreated' => (int) ($plan->timecreated ?? 0),
            'timemodified' => (int) ($plan->timemodified ?? 0),
        ];
    }

    /**
     * Format a feature.
     *
     * @param \stdClass|false $feature Feature record.
     * @return array
     */
    private static function format_feature($feature): array {
        if (!$feature) {
            $feature = (object) [];
        }

        return [
            'id' => (int) ($feature->id ?? 0),
            'name' => (string) ($feature->name ?? ''),
            'description' => (string) ($feature->description ?? ''),
            'icon' => (string) ($feature->icon ?? 'check-circle'),
            'sortorder' => (int) ($feature->sortorder ?? 0),
            'status' => (string) ($feature->status ?? 'active'),
            'timecreated' => (int) ($feature->timecreated ?? 0),
            'timemodified' => (int) ($feature->timemodified ?? 0),
        ];
    }

    /**
     * Format a subscription.
     *
     * @param \stdClass $subscription Subscription record.
     * @return array
     */
    private static function format_subscription(\stdClass $subscription): array {
        $fullname = fullname((object) [
            'firstname' => $subscription->firstname ?? '',
            'lastname' => $subscription->lastname ?? '',
        ]);

        return [
            'id' => (int) $subscription->id,
            'userid' => (int) $subscription->userid,
            'userfullname' => $fullname,
            'useremail' => (string) ($subscription->email ?? ''),
            'planid' => (int) $subscription->planid,
            'planname' => (string) ($subscription->planname ?? $subscription->plan_name ?? ''),
            'billingcycle' => (string) ($subscription->billingcycle ?? $subscription->billing_cycle ?? ''),
            'status' => (string) $subscription->status,
            'startdate' => (int) $subscription->start_date,
            'enddate' => (int) $subscription->end_date,
            'trialenddate' => (int) ($subscription->trial_end_date ?? 0),
            'graceenddate' => (int) ($subscription->grace_end_date ?? 0),
            'autorenew' => !empty($subscription->auto_renew),
            'renewalcount' => (int) ($subscription->renewal_count ?? 0),
            'cancelledat' => (int) ($subscription->cancelled_at ?? 0),
            'cancelatperiodend' => !empty($subscription->cancel_at_period_end),
            'pendingplanid' => (int) ($subscription->pending_planid ?? 0),
            'pendingchangedate' => (int) ($subscription->pending_change_date ?? 0),
            'accountcredit' => (float) ($subscription->account_credit ?? 0),
            'timecreated' => (int) $subscription->timecreated,
            'timemodified' => (int) $subscription->timemodified,
        ];
    }

    /**
     * Format a subscription key.
     *
     * @param \stdClass $key Key record.
     * @return array
     */
    private static function format_key(\stdClass $key): array {
        return [
            'id' => (int) $key->id,
            'keycode' => (string) $key->keycode,
            'planid' => (int) $key->planid,
            'planname' => (string) ($key->planname ?? ''),
            'value' => (float) ($key->value ?? 0),
            'displayvalue' => self::format_price((float) ($key->value ?? 0)),
            'currency' => self::currency_data()['code'],
            'durationdays' => (int) ($key->duration_days ?? 0),
            'maxuses' => (int) ($key->maxuses ?? 0),
            'usedcount' => (int) ($key->actualused ?? $key->usedcount ?? 0),
            'maxusesperuser' => (int) ($key->maxusesperuser ?? 1),
            'batchid' => (string) ($key->batchid ?? ''),
            'batchname' => (string) ($key->batchname ?? ''),
            'status' => (string) ($key->status ?? ''),
            'startdate' => (int) ($key->startdate ?? 0),
            'expirydate' => (int) ($key->expirydate ?? 0),
            'notes' => (string) ($key->notes ?? ''),
            'timecreated' => (int) ($key->timecreated ?? 0),
            'timemodified' => (int) ($key->timemodified ?? 0),
        ];
    }

    /**
     * Format email template.
     *
     * @param string $type Type.
     * @param array $metadata Metadata.
     * @param \stdClass|null $record Local record.
     * @return array
     */
    private static function format_email_template(
        string $type,
        array $metadata,
        ?\stdClass $record,
        array $templateoptions
    ): array {
        $templatekey = $record ? (string) ($record->template_key ?? '') : '';
        if ($templatekey === '') {
            $templatekey = (string) ($metadata['key'] ?? '');
        }

        $templatecontent = self::template_content_by_key($templateoptions, $templatekey);
        $usecustom = $record ? !empty($record->use_custom_message) : false;
        $customsubject = $record ? (string) ($record->custom_subject ?? '') : '';
        $custommessage = $record ? (string) ($record->custom_message ?? '') : '';

        if (!$usecustom) {
            $customsubject = $templatecontent['subject'];
            $custommessage = $templatecontent['body'];
        }

        return [
            'type' => $type,
            'key' => (string) ($metadata['key'] ?? ''),
            'name' => (string) ($metadata['name'] ?? $type),
            'description' => (string) ($metadata['description'] ?? ''),
            'enabled' => $record ? !empty($record->enabled) : true,
            'templatekey' => $templatekey,
            'usecustommessage' => $usecustom,
            'customsubject' => $customsubject,
            'custommessage' => $custommessage,
            'timecreated' => $record ? (int) ($record->timecreated ?? 0) : 0,
            'timemodified' => $record ? (int) ($record->timemodified ?? 0) : 0,
        ];
    }

    /**
     * Return active shared email templates for picker controls.
     *
     * @return array
     */
    private static function shared_email_template_options(): array {
        global $DB;

        if (!$DB->get_manager()->table_exists(new \xmldb_table('local_moderncommerce_emailtpl'))) {
            return [];
        }

        $records = $DB->get_records(
            'local_moderncommerce_emailtpl',
            ['status' => 'active'],
            'template_type ASC, name ASC',
            'id, template_key, name, template_type, subject, body'
        );
        $options = [];
        foreach ($records as $record) {
            $type = !empty($record->template_type) ? ucfirst((string) $record->template_type) : 'General';
            $options[] = [
                'key' => (string) $record->template_key,
                'name' => (string) $record->name . ' (' . $type . ')',
                'subject' => (string) ($record->subject ?? ''),
                'body' => (string) ($record->body ?? ''),
            ];
        }

        return $options;
    }

    /**
     * Find subject/body for a selected shared template key.
     *
     * @param array $templateoptions Template options.
     * @param string $templatekey Template key.
     * @return array{subject:string,body:string}
     */
    private static function template_content_by_key(array $templateoptions, string $templatekey): array {
        foreach ($templateoptions as $option) {
            if ((string) ($option['key'] ?? '') === $templatekey) {
                return [
                    'subject' => (string) ($option['subject'] ?? ''),
                    'body' => (string) ($option['body'] ?? ''),
                ];
            }
        }

        return ['subject' => '', 'body' => ''];
    }

    /**
     * Find email record by type.
     *
     * @param array $records Records.
     * @param string $type Type.
     * @return \stdClass|null
     */
    private static function find_email_record(array $records, string $type): ?\stdClass {
        foreach ($records as $record) {
            if ((string) $record->templatetype === $type) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Get subscription access rows.
     *
     * @param int $subscriptionid Subscription ID.
     * @return array
     */
    private static function subscription_access(int $subscriptionid): array {
        global $DB;

        $sql = "SELECT a.id,
                       a.userid,
                       a.subscriptionid,
                       a.courseid,
                       a.granted_at,
                       a.expires_at,
                       c.fullname,
                       c.shortname
                  FROM {local_moderncommerce_subscription_access} a
                  JOIN {course} c ON c.id = a.courseid
                 WHERE a.subscriptionid = :subscriptionid
              ORDER BY c.fullname ASC";
        $records = $DB->get_records_sql($sql, ['subscriptionid' => $subscriptionid]);
        $items = [];

        foreach ($records as $record) {
            $items[] = [
                'id' => (int) $record->id,
                'courseid' => (int) $record->courseid,
                'coursename' => format_string($record->fullname),
                'courseshortname' => format_string($record->shortname),
                'grantedat' => (int) $record->granted_at,
                'expiresat' => (int) ($record->expires_at ?? 0),
            ];
        }

        return $items;
    }

    /**
     * Plan stats.
     *
     * @return array
     */
    private static function plan_stats(): array {
        global $DB;

        $mrr = 0.0;
        $plans = $DB->get_records('local_moderncommerce_subscription_plans', ['status' => 'active']);
        foreach ($plans as $plan) {
            $subscribers = plan_api::get_subscriber_count((int) $plan->id);
            $monthly = (float) ($plan->price ?? 0);
            if ((string) ($plan->billing_cycle ?? '') === 'yearly') {
                $monthly = $monthly / 12;
            }
            $mrr += $monthly * $subscribers;
        }

        return [
            'totalplans' => (int) $DB->count_records('local_moderncommerce_subscription_plans'),
            'activeplans' => (int) $DB->count_records('local_moderncommerce_subscription_plans', ['status' => 'active']),
            'inactiveplans' => (int) $DB->count_records('local_moderncommerce_subscription_plans', ['status' => 'inactive']),
            'totalsubscribers' => \local_moderncommerce\subscription\api\subscription_api::get_total_active_count(),
            'mrr' => $mrr,
            'displaymrr' => self::format_price($mrr),
        ];
    }

    /**
     * Subscription stats.
     *
     * @return array
     */
    private static function subscription_stats(): array {
        global $DB;

        return [
            'total' => (int) $DB->count_records('local_moderncommerce_user_subscriptions'),
            'active' => (int) $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'active']),
            'trial' => (int) $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'trial']),
            'grace' => (int) $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'grace']),
            'cancelled' => (int) $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'cancelled']),
            'expired' => (int) $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'expired']),
        ];
    }

    /**
     * Key stats.
     *
     * @return array
     */
    private static function key_stats(): array {
        global $DB;

        return [
            'total' => (int) $DB->count_records('local_moderncommerce_subscription_keys'),
            'active' => (int) $DB->count_records('local_moderncommerce_subscription_keys', ['status' => 'active']),
            'disabled' => (int) $DB->count_records('local_moderncommerce_subscription_keys', ['status' => 'disabled']),
            'used' => (int) $DB->count_records_sql(
                'SELECT COUNT(DISTINCT keyid) FROM {local_moderncommerce_subscription_key_usage}'
            ),
        ];
    }

    /**
     * Generate a unique subscription key code.
     *
     * @return string
     */
    private static function generate_key_code(): string {
        global $DB;

        do {
            $code = 'SUB-' . strtoupper(substr(md5(random_string(40)), 0, 4))
                . '-' . strtoupper(substr(md5(random_string(40)), 0, 4))
                . '-' . strtoupper(substr(md5(random_string(40)), 0, 4));
        } while ($DB->record_exists('local_moderncommerce_subscription_keys', ['keycode' => $code]));

        return $code;
    }

    /**
     * Build a simple result.
     *
     * @param bool $success Success flag.
     * @param string $message Result message.
     * @return array
     */
    private static function simple_result(bool $success, string $message): array {
        return [
            'success' => $success,
            'message' => $message,
            'warnings' => [],
        ];
    }

    /**
     * Get normalised per page value.
     *
     * @param int $perpage Per page.
     * @return int
     */
    private static function normalise_perpage(int $perpage): int {
        return max(1, min(self::MAX_PER_PAGE, $perpage));
    }

    /**
     * Normalise a choice.
     *
     * @param string $value Raw value.
     * @param array $allowed Allowed values.
     * @param string $default Default.
     * @return string
     */
    private static function normalise_choice(string $value, array $allowed, string $default): string {
        return in_array($value, $allowed, true) ? $value : $default;
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
     * Currency data.
     *
     * @return array
     */
    private static function currency_data(): array {
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            $config = \local_moderncommerce\services\pricing_service::get_currency_config();
            return [
                'code' => (string) $config->currency,
                'symbol' => (string) $config->symbol,
                'position' => (string) $config->position,
                'decimals' => (int) $config->decimals,
            ];
        }

        return [
            'code' => get_config('local_moderncommerce', 'primary_currency') ?: 'USD',
            'symbol' => '$',
            'position' => 'before',
            'decimals' => 2,
        ];
    }

    /**
     * Generic success result structure.
     *
     * @return external_single_structure
     */
    private static function simple_result_structure(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success flag.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Plan structure.
     *
     * @return external_single_structure
     */
    private static function plan_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Plan ID.'),
            'name' => new external_value(PARAM_TEXT, 'Plan name.'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Plan code.'),
            'description' => new external_value(PARAM_RAW, 'Description.'),
            'billingcycle' => new external_value(PARAM_ALPHANUMEXT, 'Billing cycle.'),
            'price' => new external_value(PARAM_FLOAT, 'Price.'),
            'displayprice' => new external_value(PARAM_TEXT, 'Formatted price.'),
            'promoprice' => new external_value(PARAM_FLOAT, 'Promo price.'),
            'displaypromoprice' => new external_value(PARAM_TEXT, 'Formatted promo price.'),
            'promoenddate' => new external_value(PARAM_INT, 'Promo end timestamp.'),
            'currency' => new external_value(PARAM_TEXT, 'Currency.'),
            'trialdays' => new external_value(PARAM_INT, 'Trial days.'),
            'graceperioddays' => new external_value(PARAM_INT, 'Grace period days.'),
            'maxseats' => new external_value(PARAM_INT, 'Max seats.'),
            'sortorder' => new external_value(PARAM_INT, 'Sort order.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status.'),
            'featured' => new external_value(PARAM_BOOL, 'Featured flag.'),
            'subscribercount' => new external_value(PARAM_INT, 'Subscriber count.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
        ]);
    }

    /**
     * Feature structure.
     *
     * @return external_single_structure
     */
    private static function feature_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Feature ID.'),
            'name' => new external_value(PARAM_TEXT, 'Feature name.'),
            'description' => new external_value(PARAM_RAW, 'Description.'),
            'icon' => new external_value(PARAM_ALPHANUMEXT, 'Icon.'),
            'sortorder' => new external_value(PARAM_INT, 'Sort order.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
        ]);
    }

    /**
     * Feature mapping structure.
     *
     * @return external_single_structure
     */
    private static function feature_mapping_structure(): external_single_structure {
        return new external_single_structure([
            'featureid' => new external_value(PARAM_INT, 'Feature ID.'),
            'planid' => new external_value(PARAM_INT, 'Plan ID.'),
            'enabled' => new external_value(PARAM_BOOL, 'Enabled flag.'),
        ]);
    }

    /**
     * Subscription structure.
     *
     * @return external_single_structure
     */
    private static function subscription_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Subscription ID.'),
            'userid' => new external_value(PARAM_INT, 'User ID.'),
            'userfullname' => new external_value(PARAM_TEXT, 'User full name.'),
            'useremail' => new external_value(PARAM_TEXT, 'User email.'),
            'planid' => new external_value(PARAM_INT, 'Plan ID.'),
            'planname' => new external_value(PARAM_TEXT, 'Plan name.'),
            'billingcycle' => new external_value(PARAM_ALPHANUMEXT, 'Billing cycle.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status.'),
            'startdate' => new external_value(PARAM_INT, 'Start timestamp.'),
            'enddate' => new external_value(PARAM_INT, 'End timestamp.'),
            'trialenddate' => new external_value(PARAM_INT, 'Trial end timestamp.'),
            'graceenddate' => new external_value(PARAM_INT, 'Grace end timestamp.'),
            'autorenew' => new external_value(PARAM_BOOL, 'Auto renew flag.'),
            'renewalcount' => new external_value(PARAM_INT, 'Renewal count.'),
            'cancelledat' => new external_value(PARAM_INT, 'Cancelled timestamp.'),
            'cancelatperiodend' => new external_value(PARAM_BOOL, 'Cancel at period end flag.'),
            'pendingplanid' => new external_value(PARAM_INT, 'Pending plan ID.'),
            'pendingchangedate' => new external_value(PARAM_INT, 'Pending change timestamp.'),
            'accountcredit' => new external_value(PARAM_FLOAT, 'Account credit.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
        ]);
    }

    /**
     * Subscription history structure.
     *
     * @return external_single_structure
     */
    private static function history_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'History ID.'),
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Action.'),
            'oldplanid' => new external_value(PARAM_INT, 'Old plan ID.'),
            'newplanid' => new external_value(PARAM_INT, 'New plan ID.'),
            'amountpaid' => new external_value(PARAM_FLOAT, 'Amount paid.'),
            'notes' => new external_value(PARAM_RAW, 'Notes.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
        ]);
    }

    /**
     * Subscription access structure.
     *
     * @return external_single_structure
     */
    private static function access_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Access ID.'),
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'coursename' => new external_value(PARAM_TEXT, 'Course name.'),
            'courseshortname' => new external_value(PARAM_TEXT, 'Course short name.'),
            'grantedat' => new external_value(PARAM_INT, 'Granted timestamp.'),
            'expiresat' => new external_value(PARAM_INT, 'Expires timestamp.'),
        ]);
    }

    /**
     * Key structure.
     *
     * @return external_single_structure
     */
    private static function key_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Key ID.'),
            'keycode' => new external_value(PARAM_TEXT, 'Key code.'),
            'planid' => new external_value(PARAM_INT, 'Plan ID.'),
            'planname' => new external_value(PARAM_TEXT, 'Plan name.'),
            'value' => new external_value(PARAM_FLOAT, 'Value.'),
            'displayvalue' => new external_value(PARAM_TEXT, 'Formatted value.'),
            'currency' => new external_value(PARAM_TEXT, 'Currency.'),
            'durationdays' => new external_value(PARAM_INT, 'Duration days.'),
            'maxuses' => new external_value(PARAM_INT, 'Max uses.'),
            'usedcount' => new external_value(PARAM_INT, 'Used count.'),
            'maxusesperuser' => new external_value(PARAM_INT, 'Max uses per user.'),
            'batchid' => new external_value(PARAM_TEXT, 'Batch ID.'),
            'batchname' => new external_value(PARAM_TEXT, 'Batch name.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status.'),
            'startdate' => new external_value(PARAM_INT, 'Start timestamp.'),
            'expirydate' => new external_value(PARAM_INT, 'Expiry timestamp.'),
            'notes' => new external_value(PARAM_RAW, 'Notes.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
        ]);
    }

    /**
     * Email template structure.
     *
     * @return external_single_structure
     */
    private static function email_template_structure(): external_single_structure {
        return new external_single_structure([
            'type' => new external_value(PARAM_ALPHANUMEXT, 'Template type.'),
            'key' => new external_value(PARAM_TEXT, 'Default template key.'),
            'name' => new external_value(PARAM_TEXT, 'Template name.'),
            'description' => new external_value(PARAM_RAW, 'Description.'),
            'enabled' => new external_value(PARAM_BOOL, 'Enabled flag.'),
            'templatekey' => new external_value(PARAM_TEXT, 'Selected shared template key.'),
            'usecustommessage' => new external_value(PARAM_BOOL, 'Use custom message.'),
            'customsubject' => new external_value(PARAM_TEXT, 'Custom subject.'),
            'custommessage' => new external_value(PARAM_RAW, 'Custom message.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
        ]);
    }

    /**
     * Email template option structure.
     *
     * @return external_single_structure
     */
    private static function email_template_option_structure(): external_single_structure {
        return new external_single_structure([
            'key' => new external_value(PARAM_TEXT, 'Template key.'),
            'name' => new external_value(PARAM_TEXT, 'Template display name.'),
            'subject' => new external_value(PARAM_TEXT, 'Template subject.'),
            'body' => new external_value(PARAM_RAW, 'Template body.'),
        ]);
    }

    /**
     * Placeholder structure.
     *
     * @return external_single_structure
     */
    private static function placeholder_structure(): external_single_structure {
        return new external_single_structure([
            'token' => new external_value(PARAM_TEXT, 'Placeholder token.'),
            'description' => new external_value(PARAM_TEXT, 'Placeholder description.'),
        ]);
    }

    /**
     * Plan stats structure.
     *
     * @return external_single_structure
     */
    private static function plan_stats_structure(): external_single_structure {
        return new external_single_structure([
            'totalplans' => new external_value(PARAM_INT, 'Total plans.'),
            'activeplans' => new external_value(PARAM_INT, 'Active plans.'),
            'inactiveplans' => new external_value(PARAM_INT, 'Inactive plans.'),
            'totalsubscribers' => new external_value(PARAM_INT, 'Active subscribers.'),
            'mrr' => new external_value(PARAM_FLOAT, 'Monthly recurring revenue.'),
            'displaymrr' => new external_value(PARAM_TEXT, 'Formatted MRR.'),
        ]);
    }

    /**
     * Subscription stats structure.
     *
     * @return external_single_structure
     */
    private static function subscription_stats_structure(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total subscriptions.'),
            'active' => new external_value(PARAM_INT, 'Active subscriptions.'),
            'trial' => new external_value(PARAM_INT, 'Trial subscriptions.'),
            'grace' => new external_value(PARAM_INT, 'Grace subscriptions.'),
            'cancelled' => new external_value(PARAM_INT, 'Cancelled subscriptions.'),
            'expired' => new external_value(PARAM_INT, 'Expired subscriptions.'),
        ]);
    }

    /**
     * Key stats structure.
     *
     * @return external_single_structure
     */
    private static function key_stats_structure(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Total keys.'),
            'active' => new external_value(PARAM_INT, 'Active keys.'),
            'disabled' => new external_value(PARAM_INT, 'Disabled keys.'),
            'used' => new external_value(PARAM_INT, 'Used keys.'),
        ]);
    }

    /**
     * Currency structure.
     *
     * @return external_single_structure
     */
    private static function currency_structure(): external_single_structure {
        return new external_single_structure([
            'code' => new external_value(PARAM_TEXT, 'Currency code.'),
            'symbol' => new external_value(PARAM_TEXT, 'Currency symbol.'),
            'position' => new external_value(PARAM_ALPHANUMEXT, 'Currency position.'),
            'decimals' => new external_value(PARAM_INT, 'Decimal places.'),
        ]);
    }
}
