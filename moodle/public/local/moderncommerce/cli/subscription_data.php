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
 * Manage Modern Commerce subscription demo data from the command line.
 *
 * Covers the whole subscription surface in one place: plan definitions and the
 * feature matrix, plan access rules, subscriber lifecycle records, and
 * redeemable subscription keys. Each area can be seeded, reset, or inspected
 * independently so a demo environment can be rebuilt without touching the rest
 * of the store.
 *
 * Plans and the feature matrix are delegated to the existing seeder in
 * seed_subscription_features.php rather than duplicated here, so both entry
 * points always produce the same catalogue.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/ddllib.php');

// Pull in the plan/feature seeder without letting its own CLI entrypoint run.
if (!defined('LOCAL_MODERNCOMMERCE_DEMO_DATA_INCLUDE')) {
    define('LOCAL_MODERNCOMMERCE_DEMO_DATA_INCLUDE', true);
}
require_once(__DIR__ . '/seed_subscription_features.php');

/** @var string[] Data areas this script can act on, in dependency order. */
const LOCAL_MODERNCOMMERCE_SUBSCRIPTION_AREAS = ['plans', 'rules', 'subscribers', 'keys'];

/**
 * Tables owned by each area, in child-to-parent delete order.
 *
 * Subscription email templates are deliberately absent everywhere: they are
 * admin-authored configuration, not demo data, so no reset may discard them.
 *
 * @return array<string, string[]>
 */
function local_moderncommerce_subscription_area_tables(): array {
    return [
        'keys' => [
            'local_moderncommerce_subscription_key_usage',
            'local_moderncommerce_subscription_keys',
        ],
        'subscribers' => [
            'local_moderncommerce_subscription_reminders',
            'local_moderncommerce_subscription_history',
            'local_moderncommerce_subscription_log',
            'local_moderncommerce_subscription_access',
            'local_moderncommerce_user_subscriptions',
        ],
        'rules' => [
            'local_moderncommerce_subscription_access_rules',
        ],
        'plans' => [
            'local_moderncommerce_subscription_feature_map',
            'local_moderncommerce_subscription_plan_features',
            'local_moderncommerce_subscription_features',
            'local_moderncommerce_subscription_plans',
        ],
    ];
}

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'seed' => false,
        'reset' => false,
        'status' => false,
        'area' => 'all',
        'subscribers' => 8,
        'keys' => 10,
        'yes' => false,
    ],
    [
        'h' => 'help',
        's' => 'status',
        'y' => 'yes',
    ]
);

if (!empty($unrecognized)) {
    $unrecognized = implode(PHP_EOL . '  ', $unrecognized);
    cli_error("Unknown options:\n  {$unrecognized}");
}

if (!empty($options['help']) || (empty($options['seed']) && empty($options['reset']) && empty($options['status']))) {
    $areas = implode(', ', LOCAL_MODERNCOMMERCE_SUBSCRIPTION_AREAS);
    echo <<<EOT
Manage Modern Commerce subscription demo data.

Actions (choose at least one):
  -s, --status        Show current row counts for every subscription area.
      --seed          Create demo data for the selected area(s).
      --reset         Delete existing data for the selected area(s).
                      Combine with --seed to rebuild from scratch.

Options:
      --area=AREA     Which area to act on: all, {$areas}. Default: all.
                      Repeat as a comma separated list, e.g. --area=subscribers,keys
      --subscribers=N How many demo subscribers to create. Default: 8.
      --keys=N        How many demo subscription keys to create. Default: 10.
  -y, --yes           Skip the confirmation prompt for --reset.
  -h, --help          Show this help.

Areas:
  plans         Plan definitions, features, and the feature matrix.
  rules         Plan access rules (which courses/categories a plan unlocks).
  subscribers   User subscription records, access grants, and history.
  keys          Redeemable subscription keys and their usage records.

Notes:
  Admin-configured subscription email templates are never deleted.
  Resetting "plans" also resets rules, subscribers, and keys, because those
  records reference a plan and would otherwise be orphaned.

Examples:
  php public/local/moderncommerce/cli/subscription_data.php --status
  php public/local/moderncommerce/cli/subscription_data.php --reset --seed
  php public/local/moderncommerce/cli/subscription_data.php --seed --area=subscribers --subscribers=20
  php public/local/moderncommerce/cli/subscription_data.php --reset --area=keys -y

EOT;
    exit(0);
}

$areas = local_moderncommerce_subscription_resolve_areas((string) $options['area']);

if (!empty($options['status'])) {
    local_moderncommerce_subscription_print_status();
    if (empty($options['seed']) && empty($options['reset'])) {
        exit(0);
    }
}

if (!empty($options['reset'])) {
    // Anything referencing a plan must go when plans go, or it is left orphaned.
    if (in_array('plans', $areas, true)) {
        $areas = LOCAL_MODERNCOMMERCE_SUBSCRIPTION_AREAS;
    }

    $counts = local_moderncommerce_subscription_area_counts($areas);
    $total = array_sum($counts);

    if (empty($options['yes'])) {
        cli_heading('Subscription data reset');
        echo 'Areas: ' . implode(', ', $areas) . PHP_EOL . PHP_EOL;
        echo "This will permanently DELETE:\n";
        foreach ($counts as $table => $count) {
            echo '  ' . str_pad($table, 52) . $count . PHP_EOL;
        }
        echo PHP_EOL . 'Total rows: ' . $total . PHP_EOL;
        echo 'Subscription email templates are preserved.' . PHP_EOL . PHP_EOL;

        if ($total === 0) {
            echo 'Nothing to delete.' . PHP_EOL;
        } else {
            $input = cli_input('Type "yes" to continue, anything else to abort: ');
            if (strtolower(trim($input)) !== 'yes') {
                cli_error('Aborted. No changes made.', 0);
            }
        }
    }

    $removed = local_moderncommerce_subscription_reset_areas($areas);
    cli_heading('Removed');
    foreach ($removed as $table => $count) {
        echo '  ' . str_pad($table, 52) . $count . PHP_EOL;
    }
}

if (!empty($options['seed'])) {
    $summary = [];

    // Plans first: every other area references a plan id.
    if (in_array('plans', $areas, true)) {
        $result = local_moderncommerce_seed_subscription_feature_matrix();
        $summary['Plans'] = $result['plans'];
        $summary['Features'] = $result['features'];
        $summary['Feature mappings'] = $result['mappings'];
    }

    $plans = local_moderncommerce_subscription_require_plans();

    if (in_array('rules', $areas, true)) {
        $summary['Access rules'] = local_moderncommerce_subscription_seed_rules($plans);
    }

    if (in_array('subscribers', $areas, true)) {
        $created = local_moderncommerce_subscription_seed_subscribers($plans, (int) $options['subscribers']);
        $summary['Subscribers'] = $created['subscriptions'];
        $summary['Access grants'] = $created['access'];
        $summary['History entries'] = $created['history'];
    }

    if (in_array('keys', $areas, true)) {
        $created = local_moderncommerce_subscription_seed_keys($plans, (int) $options['keys']);
        $summary['Subscription keys'] = $created['keys'];
        $summary['Key usage records'] = $created['usage'];
    }

    cli_heading('Seeded');
    foreach ($summary as $label => $count) {
        echo '  ' . str_pad($label, 24) . $count . PHP_EOL;
    }
}

echo PHP_EOL . 'Done.' . PHP_EOL;

/**
 * Expand and validate the --area option.
 *
 * @param string $area Raw option value.
 * @return string[] Area keys in dependency order.
 */
function local_moderncommerce_subscription_resolve_areas(string $area): array {
    $area = trim($area);
    if ($area === '' || $area === 'all') {
        return LOCAL_MODERNCOMMERCE_SUBSCRIPTION_AREAS;
    }

    $requested = array_filter(array_map('trim', explode(',', $area)));
    $invalid = array_diff($requested, LOCAL_MODERNCOMMERCE_SUBSCRIPTION_AREAS);
    if (!empty($invalid)) {
        cli_error('Unknown area(s): ' . implode(', ', $invalid)
            . '. Valid areas: all, ' . implode(', ', LOCAL_MODERNCOMMERCE_SUBSCRIPTION_AREAS));
    }

    // Keep the canonical dependency order regardless of how they were typed.
    return array_values(array_filter(
        LOCAL_MODERNCOMMERCE_SUBSCRIPTION_AREAS,
        static fn(string $key): bool => in_array($key, $requested, true)
    ));
}

/**
 * Row counts for the tables backing the given areas.
 *
 * @param string[] $areas Area keys.
 * @return array<string, int>
 */
function local_moderncommerce_subscription_area_counts(array $areas): array {
    global $DB;

    $manager = $DB->get_manager();
    $map = local_moderncommerce_subscription_area_tables();
    $counts = [];

    foreach ($areas as $areakey) {
        foreach ($map[$areakey] ?? [] as $table) {
            if (!$manager->table_exists(new xmldb_table($table))) {
                continue;
            }
            $counts[$table] = $DB->count_records($table);
        }
    }

    return $counts;
}

/**
 * Delete every row in the tables backing the given areas.
 *
 * @param string[] $areas Area keys.
 * @return array<string, int> Table name to rows removed.
 */
function local_moderncommerce_subscription_reset_areas(array $areas): array {
    global $DB;

    $manager = $DB->get_manager();
    $map = local_moderncommerce_subscription_area_tables();
    $removed = [];

    // Child tables first so foreign keys stay satisfied throughout.
    foreach (['keys', 'subscribers', 'rules', 'plans'] as $areakey) {
        if (!in_array($areakey, $areas, true)) {
            continue;
        }
        foreach ($map[$areakey] as $table) {
            if (!$manager->table_exists(new xmldb_table($table))) {
                continue;
            }
            $removed[$table] = $DB->count_records($table);
            $DB->delete_records($table);
        }
    }

    return $removed;
}

/**
 * Print row counts for every subscription table, grouped by area.
 */
function local_moderncommerce_subscription_print_status(): void {
    global $DB;

    $manager = $DB->get_manager();
    cli_heading('Modern Commerce subscription data');

    foreach (local_moderncommerce_subscription_area_tables() as $areakey => $tables) {
        echo strtoupper($areakey) . PHP_EOL;
        foreach ($tables as $table) {
            if (!$manager->table_exists(new xmldb_table($table))) {
                echo '  ' . str_pad($table, 52) . '(table missing)' . PHP_EOL;
                continue;
            }
            echo '  ' . str_pad($table, 52) . $DB->count_records($table) . PHP_EOL;
        }
        echo PHP_EOL;
    }

    $tpl = 'local_moderncommerce_subscription_emailtpl';
    if ($manager->table_exists(new xmldb_table($tpl))) {
        echo 'PRESERVED (never reset)' . PHP_EOL;
        echo '  ' . str_pad($tpl, 52) . $DB->count_records($tpl) . PHP_EOL . PHP_EOL;
    }
}

/**
 * Load the seeded demo plans, keyed by code.
 *
 * @return array<string, \stdClass>
 */
function local_moderncommerce_subscription_require_plans(): array {
    global $DB;

    $plans = [];
    foreach ($DB->get_records('local_moderncommerce_subscription_plans', null, 'sortorder ASC') as $plan) {
        $plans[$plan->code] = $plan;
    }

    if (empty($plans)) {
        cli_error('No subscription plans exist. Run with --seed --area=plans first.');
    }

    return $plans;
}

/**
 * Seed plan access rules so each tier unlocks progressively more content.
 *
 * Targets are resolved from real courses and categories on this site; a rule
 * pointing at a missing target is skipped rather than written, so the demo data
 * never references content that does not exist.
 *
 * @param array<string, \stdClass> $plans Plans keyed by code.
 * @return int Number of rules created.
 */
function local_moderncommerce_subscription_seed_rules(array $plans): int {
    global $DB;

    $now = time();

    // Real ids only: a rule aimed at a deleted course would break access checks.
    $courseids = array_values($DB->get_fieldset_select('course', 'id', 'id > 1 ORDER BY id', []));
    $categoryids = array_values($DB->get_fieldset_select('course_categories', 'id', '1=1 ORDER BY id', []));

    if (empty($courseids) && empty($categoryids)) {
        cli_problem('No courses or categories found; skipping access rules.');
        return 0;
    }

    // Starter unlocks a couple of courses, Growth a category, top tier a wider set.
    $blueprint = [
        'demo_starter' => ['course' => array_slice($courseids, 0, 2)],
        'demo_starter_yearly' => ['course' => array_slice($courseids, 0, 2)],
        'demo_growth' => [
            'course' => array_slice($courseids, 0, 4),
            'category' => array_slice($categoryids, 0, 1),
        ],
        'demo_growth_yearly' => [
            'course' => array_slice($courseids, 0, 4),
            'category' => array_slice($categoryids, 0, 1),
        ],
        'demo_team' => ['category' => array_slice($categoryids, 0, 3)],
        'demo_enterprise' => ['category' => array_slice($categoryids, 0, 3)],
    ];

    $created = 0;
    foreach ($blueprint as $code => $rules) {
        if (!isset($plans[$code])) {
            continue;
        }
        $planid = (int) $plans[$code]->id;

        foreach ($rules as $type => $targets) {
            foreach ($targets as $targetid) {
                $params = ['planid' => $planid, 'access_type' => $type, 'target_id' => (int) $targetid];
                if ($DB->record_exists('local_moderncommerce_subscription_access_rules', $params)) {
                    continue;
                }
                $DB->insert_record(
                    'local_moderncommerce_subscription_access_rules',
                    (object) ($params + ['timecreated' => $now])
                );
                $created++;
            }
        }
    }

    return $created;
}

/**
 * Seed demo subscriber records spanning every lifecycle status.
 *
 * Statuses are cycled deliberately rather than randomised so the admin
 * Subscribers screen always has an active, trialling, grace, suspended,
 * cancelled, and expired example to filter on.
 *
 * @param array<string, \stdClass> $plans Plans keyed by code.
 * @param int $count How many subscribers to create.
 * @return array{subscriptions: int, access: int, history: int}
 */
function local_moderncommerce_subscription_seed_subscribers(array $plans, int $count): array {
    global $DB;

    $count = max(1, min($count, 200));
    $now = time();
    $planlist = array_values($plans);

    // Real, active accounts only; guests and the site admin are skipped so the
    // demo does not alter the operator's own subscription state.
    $users = $DB->get_records_select(
        'user',
        'deleted = 0 AND suspended = 0 AND id > 2 AND username <> :guest',
        ['guest' => 'guest'],
        'id ASC',
        'id',
        0,
        $count
    );

    if (empty($users)) {
        cli_problem('No eligible users found; skipping subscribers.');
        return ['subscriptions' => 0, 'access' => 0, 'history' => 0];
    }

    $statuses = ['active', 'active', 'trial', 'grace', 'suspended', 'cancelled', 'expired'];
    $manager = $DB->get_manager();
    $hasaccess = $manager->table_exists(new xmldb_table('local_moderncommerce_subscription_access'));
    $hashistory = $manager->table_exists(new xmldb_table('local_moderncommerce_subscription_history'));
    $haslog = $manager->table_exists(new xmldb_table('local_moderncommerce_subscription_log'));
    $hasreminders = $manager->table_exists(new xmldb_table('local_moderncommerce_subscription_reminders'));

    $admin = get_admin();
    $createdby = $admin ? (int) $admin->id : 0;

    $made = ['subscriptions' => 0, 'access' => 0, 'history' => 0];
    $index = 0;

    foreach ($users as $user) {
        $plan = $planlist[$index % count($planlist)];
        $status = $statuses[$index % count($statuses)];
        $index++;

        // One subscription per user keeps the demo consistent with the
        // "current plan" the learner screens expect.
        if ($DB->record_exists('local_moderncommerce_user_subscriptions', ['userid' => $user->id])) {
            continue;
        }

        $cycledays = ($plan->billing_cycle === 'yearly') ? 365 : 30;
        // Stagger how far through its term each row sits, but keep the period
        // straddling "now" so a live status never carries a past end date.
        $elapsed = (int) round($cycledays * DAYSECS * (($index % 3) + 1) / 4);
        $start = $now - $elapsed;
        $end = $start + ($cycledays * DAYSECS);

        $record = (object) [
            'userid' => (int) $user->id,
            'planid' => (int) $plan->id,
            'status' => $status,
            'start_date' => $start,
            'end_date' => $end,
            'auto_renew' => in_array($status, ['active', 'trial'], true) ? 1 : 0,
            'renewal_count' => $index % 4,
            'account_credit' => 0,
            'payment_failed_count' => 0,
            'timecreated' => $start,
            'timemodified' => $now,
        ];

        // Status-specific dates, so each row is internally coherent.
        if ($status === 'trial') {
            $record->trial_end_date = $now + (max(1, (int) $plan->trial_days) * DAYSECS);
            $record->end_date = $record->trial_end_date;
        } else if ($status === 'grace') {
            $record->end_date = $now - (2 * DAYSECS);
            $record->grace_end_date = $now + (max(1, (int) $plan->grace_period_days) * DAYSECS);
        } else if ($status === 'cancelled') {
            $record->cancelled_at = $now - (5 * DAYSECS);
            $record->cancellation_reason = 'Demo cancellation for testing the subscribers screen.';
            $record->cancel_at_period_end = 1;
        } else if ($status === 'expired') {
            $record->start_date = $now - (2 * $cycledays * DAYSECS);
            $record->end_date = $now - ($cycledays * DAYSECS);
        } else {
            $record->next_billing_date = $end;
        }

        $subscriptionid = (int) $DB->insert_record('local_moderncommerce_user_subscriptions', $record);
        $made['subscriptions']++;

        if ($hashistory) {
            $DB->insert_record('local_moderncommerce_subscription_history', (object) [
                'subscriptionid' => $subscriptionid,
                'userid' => (int) $user->id,
                'action' => 'created',
                'new_planid' => (int) $plan->id,
                'new_end_date' => $record->end_date,
                'amount_paid' => (float) $plan->price,
                'notes' => 'Demo subscription created by the Modern Commerce subscription CLI.',
                'timecreated' => $start,
                'createdby' => $createdby,
            ]);
            $made['history']++;
        }

        if ($haslog) {
            $DB->insert_record('local_moderncommerce_subscription_log', (object) [
                'subscriptionid' => $subscriptionid,
                'userid' => (int) $user->id,
                'planid' => (int) $plan->id,
                'action' => 'created',
                'details' => 'Seeded by the Modern Commerce subscription CLI.',
                'timecreated' => $start,
            ]);
        }

        // Trial and grace rows are the ones a reminder task would have touched.
        if ($hasreminders && in_array($status, ['trial', 'grace'], true)) {
            $DB->insert_record('local_moderncommerce_subscription_reminders', (object) [
                'subscriptionid' => $subscriptionid,
                'reminder_type' => $status === 'trial' ? 'trial_ending' : 'grace_ending',
                'sent_at' => $now - DAYSECS,
            ]);
        }

        // Access grants only make sense while the subscription entitles the user.
        if ($hasaccess && in_array($status, ['active', 'trial', 'grace'], true)) {
            $courseids = $DB->get_fieldset_select(
                'local_moderncommerce_subscription_access_rules',
                'target_id',
                'planid = :planid AND access_type = :type',
                ['planid' => (int) $plan->id, 'type' => 'course']
            );
            foreach (array_slice($courseids, 0, 3) as $courseid) {
                if (!$DB->record_exists('course', ['id' => $courseid])) {
                    continue;
                }
                $DB->insert_record('local_moderncommerce_subscription_access', (object) [
                    'userid' => (int) $user->id,
                    'subscriptionid' => $subscriptionid,
                    'courseid' => (int) $courseid,
                    'granted_at' => $start,
                    'expires_at' => $record->end_date,
                ]);
                $made['access']++;
            }
        }
    }

    return $made;
}

/**
 * Seed redeemable subscription keys, plus usage rows for the redeemed ones.
 *
 * @param array<string, \stdClass> $plans Plans keyed by code.
 * @param int $count How many keys to create.
 * @return array{keys: int, usage: int}
 */
function local_moderncommerce_subscription_seed_keys(array $plans, int $count): array {
    global $DB;

    $count = max(1, min($count, 500));
    $now = time();
    $planlist = array_values($plans);
    $admin = get_admin();
    $createdby = $admin ? (int) $admin->id : 0;
    $batchid = 'demo-' . $now;

    $hasusage = $DB->get_manager()->table_exists(
        new xmldb_table('local_moderncommerce_subscription_key_usage')
    );
    $subscribers = $DB->get_records(
        'local_moderncommerce_user_subscriptions',
        null,
        'id ASC',
        'id, userid',
        0,
        $count
    );
    $subscribers = array_values($subscribers);

    $made = ['keys' => 0, 'usage' => 0];

    for ($i = 0; $i < $count; $i++) {
        $plan = $planlist[$i % count($planlist)];

        // Cycle through the states an admin needs to see on the keys screen.
        $state = $i % 5;
        $status = 'active';
        $usedcount = 0;
        $expirydate = $now + (180 * DAYSECS);

        if ($state === 1) {
            // Fully redeemed.
            $usedcount = 1;
            $status = 'used';
        } else if ($state === 2) {
            $status = 'disabled';
        } else if ($state === 3) {
            $status = 'active';
            $expirydate = $now - (7 * DAYSECS);
        }

        $keycode = local_moderncommerce_subscription_demo_keycode($i);
        if ($DB->record_exists('local_moderncommerce_subscription_keys', ['keycode' => $keycode])) {
            continue;
        }

        $keyid = (int) $DB->insert_record('local_moderncommerce_subscription_keys', (object) [
            'keycode' => $keycode,
            'planid' => (int) $plan->id,
            'currency' => 'USD',
            'duration_days' => ($plan->billing_cycle === 'yearly') ? 365 : 30,
            'maxuses' => 1,
            'usedcount' => $usedcount,
            'maxusesperuser' => 1,
            'batchid' => $batchid,
            'batchname' => 'Demo key batch',
            'status' => $status,
            'startdate' => $now - (30 * DAYSECS),
            'expirydate' => $expirydate,
            'notes' => 'Demo key created by the Modern Commerce subscription CLI.',
            'createdby' => $createdby,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $made['keys']++;

        // A redeemed key needs a matching usage row to be coherent.
        if ($hasusage && $usedcount > 0 && !empty($subscribers)) {
            $subscriber = $subscribers[$i % count($subscribers)];
            $DB->insert_record('local_moderncommerce_subscription_key_usage', (object) [
                'keyid' => $keyid,
                'userid' => (int) $subscriber->userid,
                'subscriptionid' => (int) $subscriber->id,
                'ipaddress' => '127.0.0.1',
                'timecreated' => $now - (3 * DAYSECS),
            ]);
            $made['usage']++;
        }
    }

    return $made;
}

/**
 * Build a readable, collision-resistant demo key code.
 *
 * @param int $index Sequence number within this run.
 * @return string
 */
function local_moderncommerce_subscription_demo_keycode(int $index): string {
    return sprintf('DEMO-%s-%04d', strtoupper(substr(md5((string) $index), 0, 4)), $index + 1);
}
