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
 * External API for learner subscription summary.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\learner;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\localisation;
use local_moderncommerce\services\pricing_service;
use moodle_url;
use xmldb_table;

/**
 * Returns a learner's subscription summary.
 */
class get_subscription extends external_api {
    /** @var array Active subscription statuses. */
    private const ACTIVE_STATUSES = ['active', 'trial', 'grace'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Subscription ID.', VALUE_DEFAULT, 0),
            'planid' => new external_value(PARAM_INT, 'Plan ID.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $id Subscription ID.
     * @param int $planid Plan ID.
     * @return array
     */
    public static function execute(int $id = 0, int $planid = 0): array {
        global $DB, $USER;

        ['id' => $id, 'planid' => $planid] = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'planid' => $planid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:viewcatalog', $context);

        if (!self::subscription_available()) {
            return self::empty_response(false, get_string('subscriptionsunavailable_desc', 'local_moderncommerce'));
        }

        $subscription = self::find_subscription((int)$USER->id, $id, $planid, $context);
        if (!$subscription) {
            return self::empty_response(true, get_string('nosubscriptiondesc', 'local_moderncommerce'));
        }

        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid]);
        if (!$plan) {
            return self::empty_response(true, get_string('unknownplan', 'local_moderncommerce'));
        }

        $counts = self::access_counts((int)$plan->id);

        return [
            'success' => true,
            'available' => true,
            'hassubscription' => true,
            'message' => '',
            'subscription' => self::subscription_data($subscription),
            'plan' => self::plan_data($plan),
            'features' => self::features((int)$plan->id),
            'counts' => $counts,
            'actions' => self::actions($subscription, $plan),
            'urls' => self::urls($subscription),
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether request succeeded.'),
            'available' => new external_value(PARAM_BOOL, 'Whether subscription add-on is available.'),
            'hassubscription' => new external_value(PARAM_BOOL, 'Whether a subscription exists.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'subscription' => self::subscription_structure(),
            'plan' => self::plan_structure(),
            'features' => new external_multiple_structure(new external_single_structure([
                'text' => new external_value(PARAM_TEXT, 'Feature text.'),
                'icon' => new external_value(PARAM_TEXT, 'Feature icon.'),
            ])),
            'counts' => self::counts_structure(),
            'actions' => self::actions_structure(),
            'urls' => self::url_structure(),
        ]);
    }

    /**
     * Empty/unavailable response.
     *
     * @param bool $available Whether subscription add-on exists.
     * @param string $message Message.
     * @return array
     */
    private static function empty_response(bool $available, string $message): array {
        return [
            'success' => true,
            'available' => $available,
            'hassubscription' => false,
            'message' => $message,
            'subscription' => self::empty_subscription(),
            'plan' => self::empty_plan(),
            'features' => [],
            'counts' => [
                'courses' => 0,
                'bundles' => 0,
                'categories' => 0,
            ],
            'actions' => self::empty_actions(),
            'urls' => self::urls(null),
        ];
    }

    /**
     * Find a subscription with ownership checks.
     *
     * @param int $userid User ID.
     * @param int $subscriptionid Subscription ID.
     * @param int $planid Plan ID.
     * @param context_system $context Context.
     * @return \stdClass|false
     */
    private static function find_subscription(int $userid, int $subscriptionid, int $planid, context_system $context) {
        global $DB;

        if ($subscriptionid > 0) {
            $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
            if ($subscription && (int)$subscription->userid !== $userid) {
                require_capability('local/moderncommerce:viewsubscribers', $context);
            }
            return $subscription;
        }

        if ($planid > 0) {
            return $DB->get_record('local_moderncommerce_user_subscriptions', [
                'userid' => $userid,
                'planid' => $planid,
            ]);
        }

        [$statussql, $params] = $DB->get_in_or_equal(self::ACTIVE_STATUSES, SQL_PARAMS_NAMED, 'substatus');
        $params['userid'] = $userid;

        return $DB->get_record_sql(
            "SELECT *
               FROM {local_moderncommerce_user_subscriptions}
              WHERE userid = :userid
                AND status {$statussql}
           ORDER BY timecreated DESC",
            $params,
            IGNORE_MULTIPLE
        );
    }

    /**
     * Subscription data.
     *
     * @param \stdClass $subscription Subscription record.
     * @return array
     */
    private static function subscription_data(\stdClass $subscription): array {
        $status = (string)$subscription->status;
        $daysremaining = !empty($subscription->end_date) && (int)$subscription->end_date > time()
            ? (int)ceil(((int)$subscription->end_date - time()) / DAYSECS)
            : 0;

        return [
            'id' => (int)$subscription->id,
            'planid' => (int)$subscription->planid,
            'status' => $status,
            'statuslabel' => localisation::status_label($status),
            'statusclass' => self::status_class($status),
            'isactive' => in_array($status, self::ACTIVE_STATUSES, true),
            'startdate' => !empty($subscription->start_date)
                ? userdate((int)$subscription->start_date, get_string('strftimedateshort'))
                : '',
            'enddate' => !empty($subscription->end_date)
                ? userdate((int)$subscription->end_date, get_string('strftimedateshort'))
                : self::subscription_string('lifetime', get_string('never')),
            'daysremaining' => $daysremaining,
            'hasdaysremaining' => $daysremaining > 0 && $daysremaining <= 30,
            'autorenew' => !empty($subscription->auto_renew),
            'accountcredit' => pricing_service::format_price((float)($subscription->account_credit ?? 0)),
            'hasaccountcredit' => (float)($subscription->account_credit ?? 0) > 0,
            'cancelatperiodend' => !empty($subscription->cancel_at_period_end),
        ];
    }

    /**
     * Plan data.
     *
     * @param \stdClass $plan Plan record.
     * @return array
     */
    private static function plan_data(\stdClass $plan): array {
        $cycle = (string)($plan->billing_cycle ?? $plan->billingcycle ?? '');

        return [
            'id' => (int)$plan->id,
            'name' => format_string((string)$plan->name),
            'description' => trim(strip_tags((string)($plan->description ?? ''))),
            'billingcycle' => $cycle !== ''
                ? self::subscription_string('billingcycle_' . $cycle, ucfirst($cycle))
                : '',
            'price' => pricing_service::format_price(self::plan_price($plan)),
        ];
    }

    /**
     * Plan features.
     *
     * @param int $planid Plan ID.
     * @return array
     */
    private static function features(int $planid): array {
        global $DB;

        if (!self::table_exists('local_moderncommerce_subscription_plan_features')) {
            return [];
        }

        $records = $DB->get_records(
            'local_moderncommerce_subscription_plan_features',
            ['planid' => $planid],
            'sortorder ASC'
        );

        $features = [];
        foreach ($records as $record) {
            $features[] = [
                'text' => format_string((string)$record->feature),
                'icon' => (string)($record->icon ?: 'check-circle'),
            ];
        }

        return $features;
    }

    /**
     * Access counts.
     *
     * @param int $planid Plan ID.
     * @return array
     */
    private static function access_counts(int $planid): array {
        global $DB;

        $counts = [
            'courses' => 0,
            'bundles' => 0,
            'categories' => 0,
        ];
        if (!self::table_exists('local_moderncommerce_subscription_access_rules')) {
            return $counts;
        }

        $courseids = [];
        foreach ($DB->get_records('local_moderncommerce_subscription_access_rules', ['planid' => $planid]) as $rule) {
            if ($rule->access_type === 'course') {
                $courseids[(int)$rule->target_id] = (int)$rule->target_id;
            } else if ($rule->access_type === 'category') {
                $counts['categories']++;
                foreach ($DB->get_records('course', ['category' => $rule->target_id, 'visible' => 1]) as $course) {
                    $courseids[(int)$course->id] = (int)$course->id;
                }
            } else if (in_array($rule->access_type, ['bundle', 'program', 'product'], true)) {
                $counts['bundles']++;
                foreach (self::product_course_ids((int)$rule->target_id) as $courseid) {
                    $courseids[$courseid] = $courseid;
                }
            }
        }

        $counts['courses'] = count($courseids);

        return $counts;
    }

    /**
     * Actions.
     *
     * @param \stdClass $subscription Subscription record.
     * @param \stdClass $plan Plan record.
     * @return array
     */
    private static function actions(\stdClass $subscription, \stdClass $plan): array {
        global $DB;

        $status = (string)$subscription->status;
        $isexpired = in_array($status, ['expired', 'cancelled', 'suspended'], true);
        $isexpiring = !$isexpired && !empty($subscription->end_date)
            && ((int)$subscription->end_date - time()) < (14 * DAYSECS);
        $otherplansexist = self::table_exists('local_moderncommerce_subscription_plans')
            && $DB->count_records_select(
                'local_moderncommerce_subscription_plans',
                "status = 'active' AND id != :planid",
                ['planid' => (int)$plan->id]
            ) > 0;

        return [
            'canchangeplan' => $otherplansexist && in_array($status, ['active', 'trial'], true),
            'haspendingchange' => !empty($subscription->pending_planid),
            'cancancel' => in_array($status, self::ACTIVE_STATUSES, true) && empty($subscription->cancel_at_period_end),
            'cancelscheduled' => !empty($subscription->cancel_at_period_end),
            'canrenew' => $isexpired || $isexpiring || in_array($status, self::ACTIVE_STATUSES, true),
            'isexpired' => $isexpired,
            'isexpiring' => $isexpiring,
            'istrial' => $status === 'trial',
        ];
    }

    /**
     * URLs.
     *
     * @param \stdClass|null $subscription Subscription record.
     * @return array
     */
    private static function urls(?\stdClass $subscription): array {
        $id = $subscription ? (int)$subscription->id : 0;
        $planid = $subscription ? (int)$subscription->planid : 0;

        return [
            'catalog' => (new moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
            'orders' => (new moodle_url('/local/moderncommerce/learner/orders.php'))->out(false),
            'courses' => (new moodle_url('/local/moderncommerce/learner/courses.php'))->out(false),
            'access' => (new moodle_url('/local/moderncommerce/learner/subscription_access.php', ['id' => $id]))->out(false),
            'plans' => (new moodle_url('/local/moderncommerce/subscribe.php'))->out(false),
            'billinghistory' => (new moodle_url('/local/moderncommerce/learner/orders.php'))->out(false),
            'changeplan' => (new moodle_url('/local/moderncommerce/learner/subscription.php', ['id' => $id]))->out(false),
            'cancel' => (new moodle_url('/local/moderncommerce/learner/subscription.php', ['id' => $id]))->out(false),
            'renew' => (new moodle_url('/local/moderncommerce/learner/subscription.php', ['id' => $id]))->out(false),
            'converttrial' => (new moodle_url('/local/moderncommerce/learner/subscription.php', [
                'planid' => $planid,
                'convert' => 1,
            ]))->out(false),
        ];
    }

    /**
     * Product course IDs.
     *
     * @param int $productid Product ID.
     * @return array Course IDs.
     */
    private static function product_course_ids(int $productid): array {
        global $DB;

        if (!self::table_exists('local_moderncommerce_product_courses')) {
            return [];
        }

        return array_map('intval', $DB->get_fieldset_select(
            'local_moderncommerce_product_courses',
            'courseid',
            'productid = :productid AND relationtype = :relationtype',
            [
                'productid' => $productid,
                'relationtype' => 'included',
            ]
        ));
    }

    /**
     * Plan price.
     *
     * @param \stdClass $plan Plan record.
     * @return float
     */
    private static function plan_price(\stdClass $plan): float {
        foreach (['price', 'amount', 'monthlyprice', 'regularprice'] as $field) {
            if (isset($plan->{$field})) {
                return (float)$plan->{$field};
            }
        }

        return 0.0;
    }

    /**
     * Status class.
     *
     * @param string $status Status.
     * @return string
     */
    private static function status_class(string $status): string {
        if (in_array($status, ['active'], true)) {
            return 'success';
        }
        if (in_array($status, ['trial', 'grace', 'pending'], true)) {
            return 'warning';
        }
        if (in_array($status, ['cancelled', 'expired', 'suspended'], true)) {
            return 'danger';
        }

        return 'neutral';
    }

    /**
     * Empty subscription.
     *
     * @return array
     */
    private static function empty_subscription(): array {
        return [
            'id' => 0,
            'planid' => 0,
            'status' => '',
            'statuslabel' => '',
            'statusclass' => 'neutral',
            'isactive' => false,
            'startdate' => '',
            'enddate' => '',
            'daysremaining' => 0,
            'hasdaysremaining' => false,
            'autorenew' => false,
            'accountcredit' => '',
            'hasaccountcredit' => false,
            'cancelatperiodend' => false,
        ];
    }

    /**
     * Empty plan.
     *
     * @return array
     */
    private static function empty_plan(): array {
        return [
            'id' => 0,
            'name' => '',
            'description' => '',
            'billingcycle' => '',
            'price' => '',
        ];
    }

    /**
     * Empty actions.
     *
     * @return array
     */
    private static function empty_actions(): array {
        return [
            'canchangeplan' => false,
            'haspendingchange' => false,
            'cancancel' => false,
            'cancelscheduled' => false,
            'canrenew' => false,
            'isexpired' => false,
            'isexpiring' => false,
            'istrial' => false,
        ];
    }

    /**
     * Optional subscription string with fallback.
     *
     * @param string $key String key.
     * @param string $fallback Fallback text.
     * @return string
     */
    private static function subscription_string(string $key, string $fallback): string {
        return get_string_manager()->string_exists($key, 'local_moderncommerce')
            ? get_string($key, 'local_moderncommerce')
            : $fallback;
    }

    /**
     * Check whether subscription integration is available.
     *
     * @return bool
     */
    private static function subscription_available(): bool {
        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('local_moderncommerce');

        return $plugininfo !== null
            && $plugininfo->is_installed_and_upgraded()
            && self::table_exists('local_moderncommerce_user_subscriptions')
            && self::table_exists('local_moderncommerce_subscription_plans');
    }

    /**
     * Check whether table exists.
     *
     * @param string $table Table name without prefix.
     * @return bool
     */
    private static function table_exists(string $table): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new xmldb_table($table));
    }

    /**
     * Subscription structure.
     *
     * @return external_single_structure
     */
    private static function subscription_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Subscription ID.'),
            'planid' => new external_value(PARAM_INT, 'Plan ID.'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status.'),
            'statuslabel' => new external_value(PARAM_TEXT, 'Status label.'),
            'statusclass' => new external_value(PARAM_ALPHANUMEXT, 'Status class.'),
            'isactive' => new external_value(PARAM_BOOL, 'Whether active.'),
            'startdate' => new external_value(PARAM_TEXT, 'Start date.'),
            'enddate' => new external_value(PARAM_TEXT, 'End date.'),
            'daysremaining' => new external_value(PARAM_INT, 'Days remaining.'),
            'hasdaysremaining' => new external_value(PARAM_BOOL, 'Whether days remaining should show.'),
            'autorenew' => new external_value(PARAM_BOOL, 'Whether auto-renew is enabled.'),
            'accountcredit' => new external_value(PARAM_TEXT, 'Account credit.'),
            'hasaccountcredit' => new external_value(PARAM_BOOL, 'Whether account credit exists.'),
            'cancelatperiodend' => new external_value(PARAM_BOOL, 'Whether cancel is scheduled.'),
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
            'description' => new external_value(PARAM_RAW, 'Plan description.'),
            'billingcycle' => new external_value(PARAM_TEXT, 'Billing cycle.'),
            'price' => new external_value(PARAM_TEXT, 'Formatted price.'),
        ]);
    }

    /**
     * Counts structure.
     *
     * @return external_single_structure
     */
    private static function counts_structure(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_value(PARAM_INT, 'Course count.'),
            'bundles' => new external_value(PARAM_INT, 'Bundle count.'),
            'categories' => new external_value(PARAM_INT, 'Category count.'),
        ]);
    }

    /**
     * Actions structure.
     *
     * @return external_single_structure
     */
    private static function actions_structure(): external_single_structure {
        return new external_single_structure([
            'canchangeplan' => new external_value(PARAM_BOOL, 'Whether plan can be changed.'),
            'haspendingchange' => new external_value(PARAM_BOOL, 'Whether pending change exists.'),
            'cancancel' => new external_value(PARAM_BOOL, 'Whether subscription can be cancelled.'),
            'cancelscheduled' => new external_value(PARAM_BOOL, 'Whether cancel is scheduled.'),
            'canrenew' => new external_value(PARAM_BOOL, 'Whether subscription can be renewed.'),
            'isexpired' => new external_value(PARAM_BOOL, 'Whether expired.'),
            'isexpiring' => new external_value(PARAM_BOOL, 'Whether expiring soon.'),
            'istrial' => new external_value(PARAM_BOOL, 'Whether trial.'),
        ]);
    }

    /**
     * URL structure.
     *
     * @return external_single_structure
     */
    private static function url_structure(): external_single_structure {
        return new external_single_structure([
            'catalog' => new external_value(PARAM_RAW, 'Catalog URL.'),
            'orders' => new external_value(PARAM_RAW, 'Orders URL.'),
            'courses' => new external_value(PARAM_RAW, 'Courses URL.'),
            'access' => new external_value(PARAM_RAW, 'Subscription access URL.'),
            'plans' => new external_value(PARAM_RAW, 'Plans URL.'),
            'billinghistory' => new external_value(PARAM_RAW, 'Billing history URL.'),
            'changeplan' => new external_value(PARAM_RAW, 'Change plan URL.'),
            'cancel' => new external_value(PARAM_RAW, 'Cancel URL.'),
            'renew' => new external_value(PARAM_RAW, 'Renew URL.'),
            'converttrial' => new external_value(PARAM_RAW, 'Convert trial URL.'),
        ]);
    }
}
