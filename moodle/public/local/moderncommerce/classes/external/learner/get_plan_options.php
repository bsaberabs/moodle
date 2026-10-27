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
 * External API listing the plans a learner can switch to.
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
use core_external\external_warnings;
use local_moderncommerce\services\pricing_service;
use local_moderncommerce\subscription\api\subscription_api;
use local_moderncommerce\subscription\services\upgrade_service;

/**
 * Returns every active plan alongside the learner's current one, classified as an
 * upgrade, downgrade or lateral move with its cost and effective date.
 */
class get_plan_options extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Execute.
     *
     * @return array
     */
    public static function execute(): array {
        global $DB, $USER;

        self::validate_parameters(self::execute_parameters(), []);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:subscribetoplan', $context);

        $userid = (int)$USER->id;
        $current = subscription_api::get_active_for_user($userid);

        // Without a subscription there is nothing to change from; the learner subscribes
        // through the normal plan-selection flow instead.
        if (!$current) {
            return self::response(false, 0, '', [], true, '');
        }

        $currentplan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $current->planid]);
        $canchange = upgrade_service::can_change_plan((int)$current->id);
        $allowed = !empty($canchange['allowed']);

        return self::response(
            true,
            (int)$current->planid,
            $currentplan ? format_string((string)$currentplan->name) : '',
            self::plan_rows($userid, (int)$current->planid, (int)($current->pending_planid ?? 0)),
            $allowed,
            $allowed ? '' : self::blocked_message($canchange)
        );
    }

    /**
     * Build one row per selectable plan.
     *
     * @param int $userid User ID.
     * @param int $currentplanid Current plan ID.
     * @param int $pendingplanid Scheduled plan ID, when a change is already queued.
     * @return array
     */
    private static function plan_rows(int $userid, int $currentplanid, int $pendingplanid): array {
        global $DB;

        $rows = [];

        // The current plan is not in get_available_plans(), but the comparison is only
        // meaningful with it shown in place.
        $currentplan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $currentplanid]);
        if ($currentplan) {
            $rows[(int)$currentplan->sortorder * 1000 + (int)$currentplan->id] = self::plan_row($currentplan, [
                'iscurrent' => true,
            ]);
        }

        foreach (upgrade_service::get_available_plans($userid) as $option) {
            $plan = $option['plan'] ?? null;
            if (!$plan) {
                continue;
            }
            $rows[(int)$plan->sortorder * 1000 + (int)$plan->id] = self::plan_row($plan, [
                'isupgrade' => !empty($option['isupgrade']),
                'isdowngrade' => !empty($option['isdowngrade']),
                'islateral' => !empty($option['islateral']),
                'changecost' => (float)($option['upgradecost'] ?? 0),
                'requirespayment' => !empty($option['requires_payment']),
                'immediate' => !empty($option['immediate']),
                'effectivedate' => (int)($option['effective_date'] ?? 0),
                'ispending' => $pendingplanid > 0 && (int)$plan->id === $pendingplanid,
            ]);
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * Describe a single plan.
     *
     * @param \stdClass $plan Plan record.
     * @param array $flags Classification flags.
     * @return array
     */
    private static function plan_row(\stdClass $plan, array $flags): array {
        $cycle = (string)($plan->billing_cycle ?? '');
        $price = (float)$plan->price;
        $changecost = (float)($flags['changecost'] ?? 0);
        $effective = (int)($flags['effectivedate'] ?? 0);

        return [
            'id' => (int)$plan->id,
            'name' => format_string((string)$plan->name),
            'description' => trim(strip_tags((string)($plan->description ?? ''))),
            'price' => $price,
            'displayprice' => pricing_service::format_price($price),
            'billingcycle' => $cycle,
            'billingcyclelabel' => self::cycle_label($cycle),
            'features' => self::features((int)$plan->id),
            'iscurrent' => !empty($flags['iscurrent']),
            'isupgrade' => !empty($flags['isupgrade']),
            'isdowngrade' => !empty($flags['isdowngrade']),
            'islateral' => !empty($flags['islateral']),
            'ispending' => !empty($flags['ispending']),
            'changecost' => $changecost,
            'displaychangecost' => pricing_service::format_price($changecost),
            'requirespayment' => !empty($flags['requirespayment']),
            'immediate' => !empty($flags['immediate']),
            'effectivedate' => $effective,
            'effectivedatelabel' => $effective > 0
                ? userdate($effective, get_string('strftimedateshort'))
                : '',
        ];
    }

    /**
     * Plan feature bullets.
     *
     * @param int $planid Plan ID.
     * @return array
     */
    private static function features(int $planid): array {
        global $DB;

        if (!$DB->get_manager()->table_exists(new \xmldb_table('local_moderncommerce_subscription_plan_features'))) {
            return [];
        }

        $features = [];
        $records = $DB->get_records(
            'local_moderncommerce_subscription_plan_features',
            ['planid' => $planid],
            'sortorder ASC'
        );
        foreach ($records as $record) {
            $features[] = format_string((string)$record->feature);
        }

        return $features;
    }

    /**
     * Human label for a billing cycle.
     *
     * @param string $cycle Cycle key.
     * @return string
     */
    private static function cycle_label(string $cycle): string {
        if ($cycle === '') {
            return '';
        }

        return get_string_manager()->string_exists('billingcycle_' . $cycle, 'local_moderncommerce')
            ? get_string('billingcycle_' . $cycle, 'local_moderncommerce')
            : ucfirst($cycle);
    }

    /**
     * Explain why plan changes are currently blocked.
     *
     * @param array $canchange Result from upgrade_service::can_change_plan().
     * @return string
     */
    private static function blocked_message(array $canchange): string {
        if (($canchange['reason'] ?? '') === 'cooldown') {
            return get_string('planchangeblocked', 'local_moderncommerce', (int)($canchange['wait_days'] ?? 0));
        }

        return get_string('planchangeunavailable', 'local_moderncommerce');
    }

    /**
     * Build the response.
     *
     * @param bool $hassubscription Whether the learner has an active subscription.
     * @param int $currentplanid Current plan ID.
     * @param string $currentplanname Current plan name.
     * @param array $plans Plan rows.
     * @param bool $canchange Whether a change is currently allowed.
     * @param string $message Explanation when changes are blocked.
     * @return array
     */
    private static function response(
        bool $hassubscription,
        int $currentplanid,
        string $currentplanname,
        array $plans,
        bool $canchange,
        string $message
    ): array {
        return [
            'success' => true,
            'hassubscription' => $hassubscription,
            'currentplanid' => $currentplanid,
            'currentplanname' => $currentplanname,
            'canchange' => $canchange,
            'message' => $message,
            'plans' => $plans,
            'warnings' => [],
        ];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded.'),
            'hassubscription' => new external_value(PARAM_BOOL, 'Whether an active subscription exists.'),
            'currentplanid' => new external_value(PARAM_INT, 'Current plan ID.'),
            'currentplanname' => new external_value(PARAM_TEXT, 'Current plan name.'),
            'canchange' => new external_value(PARAM_BOOL, 'Whether a plan change is allowed now.'),
            'message' => new external_value(PARAM_TEXT, 'Why a change is blocked, when it is.'),
            'plans' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Plan ID.'),
                'name' => new external_value(PARAM_TEXT, 'Plan name.'),
                'description' => new external_value(PARAM_TEXT, 'Plan description.'),
                'price' => new external_value(PARAM_FLOAT, 'Plan price.'),
                'displayprice' => new external_value(PARAM_TEXT, 'Formatted plan price.'),
                'billingcycle' => new external_value(PARAM_ALPHANUMEXT, 'Billing cycle key.'),
                'billingcyclelabel' => new external_value(PARAM_TEXT, 'Billing cycle label.'),
                'features' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Feature.')),
                'iscurrent' => new external_value(PARAM_BOOL, 'Whether this is the current plan.'),
                'isupgrade' => new external_value(PARAM_BOOL, 'Whether switching is an upgrade.'),
                'isdowngrade' => new external_value(PARAM_BOOL, 'Whether switching is a downgrade.'),
                'islateral' => new external_value(PARAM_BOOL, 'Whether switching is a lateral move.'),
                'ispending' => new external_value(PARAM_BOOL, 'Whether a change to this plan is scheduled.'),
                'changecost' => new external_value(PARAM_FLOAT, 'Amount due to switch now.'),
                'displaychangecost' => new external_value(PARAM_TEXT, 'Formatted amount due.'),
                'requirespayment' => new external_value(PARAM_BOOL, 'Whether switching needs payment.'),
                'immediate' => new external_value(PARAM_BOOL, 'Whether the change applies immediately.'),
                'effectivedate' => new external_value(PARAM_INT, 'When the change takes effect.'),
                'effectivedatelabel' => new external_value(PARAM_TEXT, 'Formatted effective date.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }
}
