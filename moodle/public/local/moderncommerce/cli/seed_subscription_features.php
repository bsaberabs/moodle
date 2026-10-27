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
 * Seeds demo subscription plans and feature matrix data.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState -- Reusable CLI seed include.
if (!defined('CLI_SCRIPT')) {
    define('CLI_SCRIPT', true);
}
// phpcs:enable moodle.Files.MoodleInternal.MoodleInternalGlobalState

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/ddllib.php');

if (local_moderncommerce_seed_subscription_features_cli_should_run()) {
    local_moderncommerce_seed_subscription_features_cli_main();
}

/**
 * Whether this file is the active CLI entrypoint.
 *
 * @return bool
 */
function local_moderncommerce_seed_subscription_features_cli_should_run(): bool {
    return !defined('LOCAL_MODERNCOMMERCE_DEMO_DATA_INCLUDE');
}

/**
 * Seed subscription feature data from the CLI entrypoint.
 */
function local_moderncommerce_seed_subscription_features_cli_main(): void {
    [$options, $unrecognized] = cli_get_params(
        [
            'help' => false,
            'fresh' => false,
            'yes' => false,
        ],
        [
            'h' => 'help',
            'y' => 'yes',
        ]
    );

    if (!empty($unrecognized)) {
        $unrecognized = implode(PHP_EOL . '  ', $unrecognized);
        cli_error("Unknown options:\n  {$unrecognized}");
    }

    if (!empty($options['help'])) {
        echo "Seed Modern Commerce subscription feature matrix demo data.\n\n";
        echo "This creates or updates demo subscription plans, demo features, and their matrix mappings.\n";
        echo "Six plans are seeded: three tiers (Starter, Growth, Team/Enterprise) on both the monthly\n";
        echo "and yearly billing cycles, with a live promotional price on the yearly Growth plan.\n";
        echo "By default it is idempotent and does not delete any records.\n\n";
        echo "Options:\n";
        echo "  -h, --help   Show this help.\n";
        echo "      --fresh  Delete ALL existing subscription plans, features, matrix mappings,\n";
        echo "               access rules, and subscriber lifecycle rows before seeding. Admin-configured\n";
        echo "               subscription email templates are preserved.\n";
        echo "  -y, --yes    Skip the confirmation prompt for --fresh.\n\n";
        echo "Examples:\n";
        echo "  php public/local/moderncommerce/cli/seed_subscription_features.php\n";
        echo "  php public/local/moderncommerce/cli/seed_subscription_features.php --fresh\n";
        exit(0);
    }

    $purged = [];
    if (!empty($options['fresh'])) {
        $counts = local_moderncommerce_subscription_reset_counts();
        $total = array_sum($counts);

        if (empty($options['yes'])) {
            cli_heading('Subscription data reset');
            echo "This will permanently DELETE the following rows before reseeding:\n";
            foreach ($counts as $table => $count) {
                echo '  ' . str_pad($table, 52) . $count . PHP_EOL;
            }
            echo PHP_EOL . 'Total rows to delete: ' . $total . PHP_EOL;
            echo 'Subscription email templates are preserved.' . PHP_EOL . PHP_EOL;

            $input = cli_input('Type "yes" to continue, anything else to abort: ');
            if (strtolower(trim($input)) !== 'yes') {
                cli_error('Aborted. No changes made.', 0);
            }
        }

        $purged = local_moderncommerce_subscription_reset();
    }

    $result = local_moderncommerce_seed_subscription_feature_matrix();

    if (!empty($options['fresh'])) {
        cli_heading('Existing subscription data removed');
        foreach ($purged as $table => $count) {
            echo '  ' . str_pad($table, 52) . $count . PHP_EOL;
        }
    }

    cli_heading('Modern Commerce subscription feature matrix seeded');
    echo 'Plans: ' . $result['plans'] . PHP_EOL;
    echo 'Features: ' . $result['features'] . PHP_EOL;
    echo 'Mappings enabled: ' . $result['mappings'] . PHP_EOL;
}

/**
 * Subscription tables cleared by --fresh, in child-to-parent order.
 *
 * Ordered so rows referencing a plan or subscription are removed before the
 * records they point at, which keeps the delete valid under the foreign keys
 * declared in install.xml.
 *
 * The subscription email template table is deliberately absent: those rows are
 * admin-authored configuration rather than demo data, so a reseed must not
 * discard them.
 *
 * @return string[]
 */
function local_moderncommerce_subscription_reset_tables(): array {
    return [
        // Subscriber lifecycle, deepest first.
        'local_moderncommerce_subscription_key_usage',
        'local_moderncommerce_subscription_keys',
        'local_moderncommerce_subscription_reminders',
        'local_moderncommerce_subscription_history',
        'local_moderncommerce_subscription_log',
        'local_moderncommerce_subscription_access',
        'local_moderncommerce_user_subscriptions',
        // Plan-scoped configuration.
        'local_moderncommerce_subscription_access_rules',
        'local_moderncommerce_subscription_feature_map',
        'local_moderncommerce_subscription_plan_features',
        // Definitions last.
        'local_moderncommerce_subscription_features',
        'local_moderncommerce_subscription_plans',
    ];
}

/**
 * Row counts for each table {@see local_moderncommerce_subscription_reset()} would clear.
 *
 * @return array<string, int> Table name to row count, tables that do not exist omitted.
 */
function local_moderncommerce_subscription_reset_counts(): array {
    global $DB;

    $manager = $DB->get_manager();
    $counts = [];
    foreach (local_moderncommerce_subscription_reset_tables() as $table) {
        if (!$manager->table_exists(new xmldb_table($table))) {
            continue;
        }
        $counts[$table] = $DB->count_records($table);
    }

    return $counts;
}

/**
 * Delete every subscription plan, feature, mapping, and subscriber lifecycle row.
 *
 * @return array<string, int> Table name to the number of rows removed.
 */
function local_moderncommerce_subscription_reset(): array {
    global $DB;

    $manager = $DB->get_manager();
    $removed = [];

    foreach (local_moderncommerce_subscription_reset_tables() as $table) {
        if (!$manager->table_exists(new xmldb_table($table))) {
            continue;
        }
        $removed[$table] = $DB->count_records($table);
        $DB->delete_records($table);
    }

    return $removed;
}

/**
 * Seed demo subscription feature matrix rows.
 *
 * @return array{plans: int, features: int, mappings: int}
 */
function local_moderncommerce_seed_subscription_feature_matrix(): array {
    global $DB;

    $manager = $DB->get_manager();
    $requiredtables = [
        'local_moderncommerce_subscription_plans',
        'local_moderncommerce_subscription_features',
        'local_moderncommerce_subscription_feature_map',
    ];
    foreach ($requiredtables as $table) {
        if (!$manager->table_exists(new xmldb_table($table))) {
            cli_error("Missing required table: {$table}");
        }
    }

    $now = time();
    $admin = get_admin();
    $userid = $admin ? (int) $admin->id : 0;

    // Three tiers, each offered on both billing cycles, so a storefront pricing
    // table shows a complete like-for-like set whichever cycle it filters on.
    // Yearly prices are pitched at ten months of the monthly rate, which is the
    // usual "two months free" framing. The mid yearly tier carries a live promo
    // so the struck-through regular price has real data to render from.
    $plans = [
        'demo_starter' => [
            'name' => 'Demo Starter',
            'description' => 'A lightweight plan for individual learners.',
            'billing_cycle' => 'monthly',
            'price' => 29,
            'trial_days' => 7,
            'grace_period_days' => 7,
            'max_seats' => 1,
            'sortorder' => 10,
            'featured' => 0,
        ],
        'demo_growth' => [
            'name' => 'Demo Growth',
            'description' => 'A fuller plan with projects, downloads, and instructor support.',
            'billing_cycle' => 'monthly',
            'price' => 79,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'max_seats' => 5,
            'sortorder' => 20,
            'featured' => 1,
        ],
        'demo_team' => [
            'name' => 'Demo Team',
            'description' => 'A team plan with priority support and reporting.',
            'billing_cycle' => 'monthly',
            'price' => 149,
            'trial_days' => 14,
            'grace_period_days' => 14,
            'max_seats' => 25,
            'sortorder' => 30,
            'featured' => 0,
        ],
        'demo_starter_yearly' => [
            'name' => 'Demo Starter (yearly)',
            'description' => 'A lightweight plan for individual learners, billed once a year.',
            'billing_cycle' => 'yearly',
            'price' => 290,
            'trial_days' => 7,
            'grace_period_days' => 7,
            'max_seats' => 1,
            'sortorder' => 40,
            'featured' => 0,
        ],
        'demo_growth_yearly' => [
            'name' => 'Demo Growth (yearly)',
            'description' => 'A fuller plan with projects, downloads, and instructor support, billed once a year.',
            'billing_cycle' => 'yearly',
            'price' => 790,
            // Live promotional price; the end date is set below so it never expires
            // in a demo environment.
            'promo_price' => 632,
            'trial_days' => 14,
            'grace_period_days' => 7,
            'max_seats' => 5,
            'sortorder' => 50,
            'featured' => 1,
        ],
        'demo_enterprise' => [
            'name' => 'Demo Enterprise',
            'description' => 'A team plan with priority support and reporting, billed once a year.',
            'billing_cycle' => 'yearly',
            'price' => 1490,
            'trial_days' => 30,
            'grace_period_days' => 14,
            'max_seats' => 25,
            'sortorder' => 60,
            'featured' => 0,
        ],
    ];

    // Far enough out that the seeded promo stays live without needing a re-seed.
    $promoend = $now + (365 * DAYSECS);

    $planids = [];
    foreach ($plans as $code => $plan) {
        $record = $DB->get_record('local_moderncommerce_subscription_plans', ['code' => $code]);
        $promoprice = (float) ($plan['promo_price'] ?? 0);
        $data = (object) [
            'name' => $plan['name'],
            'code' => $code,
            'description' => $plan['description'],
            'billing_cycle' => $plan['billing_cycle'],
            'price' => $plan['price'],
            'promo_price' => $promoprice,
            'promo_end_date' => $promoprice > 0 ? $promoend : 0,
            'currency' => 'USD',
            'trial_days' => $plan['trial_days'],
            'grace_period_days' => $plan['grace_period_days'],
            'max_seats' => $plan['max_seats'],
            'sortorder' => $plan['sortorder'],
            'status' => 'active',
            'featured' => $plan['featured'],
            'timemodified' => $now,
            'createdby' => $userid,
        ];

        if ($record) {
            $data->id = (int) $record->id;
            $DB->update_record('local_moderncommerce_subscription_plans', $data);
            $planids[$code] = (int) $record->id;
            continue;
        }

        $data->timecreated = $now;
        $planids[$code] = (int) $DB->insert_record('local_moderncommerce_subscription_plans', $data);
    }

    $features = [
        'Course library' => [
            'description' => 'Access to the subscription course catalog.',
            'icon' => 'collection',
            'sortorder' => 10,
        ],
        'Certificates' => [
            'description' => 'Completion certificates for eligible courses.',
            'icon' => 'award',
            'sortorder' => 20,
        ],
        'Community access' => [
            'description' => 'Access to learner discussion spaces.',
            'icon' => 'people',
            'sortorder' => 30,
        ],
        'Downloadable resources' => [
            'description' => 'Templates, worksheets, and offline files.',
            'icon' => 'download',
            'sortorder' => 40,
        ],
        'Instructor Q&A' => [
            'description' => 'Ask questions and get instructor guidance.',
            'icon' => 'chat-dots',
            'sortorder' => 50,
        ],
        'Practice projects' => [
            'description' => 'Hands-on projects and graded practice work.',
            'icon' => 'clipboard-check',
            'sortorder' => 60,
        ],
        'Team seats' => [
            'description' => 'Seat management for teams and cohorts.',
            'icon' => 'people-fill',
            'sortorder' => 70,
        ],
        'Priority support' => [
            'description' => 'Faster support response for admins and learners.',
            'icon' => 'headset',
            'sortorder' => 80,
        ],
        'Analytics dashboard' => [
            'description' => 'Progress and usage reporting for team leads.',
            'icon' => 'graph-up-arrow',
            'sortorder' => 90,
        ],
    ];

    $featureids = [];
    foreach ($features as $name => $feature) {
        $record = $DB->get_record('local_moderncommerce_subscription_features', ['name' => $name]);
        $data = (object) [
            'name' => $name,
            'description' => $feature['description'],
            'icon' => $feature['icon'],
            'sortorder' => $feature['sortorder'],
            'status' => 'active',
            'timemodified' => $now,
        ];

        if ($record) {
            $data->id = (int) $record->id;
            $DB->update_record('local_moderncommerce_subscription_features', $data);
            $featureids[$name] = (int) $record->id;
            continue;
        }

        $data->timecreated = $now;
        $featureids[$name] = (int) $DB->insert_record('local_moderncommerce_subscription_features', $data);
    }

    // Cumulative tiers: each one includes everything below it, so the pricing
    // table reads as a clean "everything in X, plus..." ladder. A tier's monthly
    // and yearly plans always carry the identical feature set — only the price
    // and billing cycle differ.
    $tiers = [
        'starter' => [
            'Course library',
            'Certificates',
            'Community access',
        ],
        'growth' => [
            'Course library',
            'Certificates',
            'Community access',
            'Downloadable resources',
            'Instructor Q&A',
            'Practice projects',
        ],
        'team' => array_keys($features),
    ];

    $matrix = [
        'demo_starter' => $tiers['starter'],
        'demo_starter_yearly' => $tiers['starter'],
        'demo_growth' => $tiers['growth'],
        'demo_growth_yearly' => $tiers['growth'],
        'demo_team' => $tiers['team'],
        'demo_enterprise' => $tiers['team'],
    ];

    $enabled = 0;
    foreach ($planids as $code => $planid) {
        foreach ($featureids as $name => $featureid) {
            $shouldenable = in_array($name, $matrix[$code] ?? [], true);
            $params = ['planid' => $planid, 'featureid' => $featureid];

            if (!$shouldenable) {
                $DB->delete_records('local_moderncommerce_subscription_feature_map', $params);
                continue;
            }

            if (!$DB->record_exists('local_moderncommerce_subscription_feature_map', $params)) {
                $DB->insert_record('local_moderncommerce_subscription_feature_map', (object) [
                    'planid' => $planid,
                    'featureid' => $featureid,
                    'timecreated' => $now,
                ]);
            }
            $enabled++;
        }
    }

    return [
        'plans' => count($planids),
        'features' => count($featureids),
        'mappings' => $enabled,
    ];
}
