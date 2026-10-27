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
 * Modern Commerce demo data and reset manager.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\demo;

/**
 * Coordinates install defaults, full demo data, audits, and full plugin resets.
 */
class data_manager {
    /** @var string Demo Moodle category idnumber prefix. */
    private const DEMO_CATEGORY_PREFIX = 'MCDEMO-CAT-';

    /** @var string Demo Moodle course idnumber prefix. */
    private const DEMO_COURSE_PREFIX = 'MCDEMO-COURSE-';

    /** @var array Default full demo options. */
    private const DEFAULT_DEMO_OPTIONS = [
        'userid' => 0,
        'categories' => 12,
        'courses' => 25,
        'products' => 0,
        'orders' => 120,
        'coupons' => 12,
        'keys' => 24,
        'reviews' => 4,
    ];

    /** @var string[] Modern Commerce tables in child-first reset order. */
    private const RESET_TABLES = [
        'local_moderncommerce_notify_log',
        'local_moderncommerce_notify_queue',
        'local_moderncommerce_notify_digest',
        'local_moderncommerce_notify_identity',
        'local_moderncommerce_notify_suppression',
        'local_moderncommerce_contact_replies',
        'local_moderncommerce_contacts',
        'local_moderncommerce_subscription_key_usage',
        'local_moderncommerce_subscription_log',
        'local_moderncommerce_subscription_access',
        'local_moderncommerce_subscription_reminders',
        'local_moderncommerce_subscription_history',
        'local_moderncommerce_user_subscriptions',
        'local_moderncommerce_subscription_keys',
        'local_moderncommerce_subscription_access_rules',
        'local_moderncommerce_subscription_feature_map',
        'local_moderncommerce_subscription_plan_features',
        'local_moderncommerce_subscription_features',
        'local_moderncommerce_subscription_plans',
        'local_moderncommerce_subscription_emailtpl',
        'local_moderncommerce_review_rxn',
        'local_moderncommerce_reviews',
        'local_moderncommerce_dashpref',
        'local_moderncommerce_subscriber',
        'local_moderncommerce_widget_preset',
        'local_moderncommerce_widget_slide',
        'local_moderncommerce_widget',
        'local_moderncommerce_bundle_outline',
        'local_moderncommerce_bundle_mustpass',
        'local_moderncommerce_bundle_prereq',
        'local_moderncommerce_bundle_tags',
        'local_moderncommerce_bundle_meta',
        'local_moderncommerce_emailtpl',
        'local_moderncommerce_report_gateways',
        'local_moderncommerce_report_products',
        'local_moderncommerce_report_daily',
        'local_moderncommerce_audit_log',
        'local_moderncommerce_entitlement_events',
        'local_moderncommerce_entitlements',
        'local_moderncommerce_fulfillment_items',
        'local_moderncommerce_fulfillments',
        'local_moderncommerce_refund_items',
        'local_moderncommerce_refunds',
        'local_moderncommerce_payment_log',
        'local_moderncommerce_payment_events',
        'local_moderncommerce_payment_attempts',
        'local_moderncommerce_webhook_events',
        'local_moderncommerce_invoice_items',
        'local_moderncommerce_invoices',
        'local_moderncommerce_inventory_reservations',
        'local_moderncommerce_order_operational',
        'local_moderncommerce_order_adjustments',
        'local_moderncommerce_order_status_history',
        'local_moderncommerce_order_addresses',
        'local_moderncommerce_order_items',
        'local_moderncommerce_orders',
        'local_moderncommerce_cart_items',
        'local_moderncommerce_carts',
        'local_moderncommerce_billing_profiles',
        'local_moderncommerce_key_usage',
        'local_moderncommerce_enrollkey_targets',
        'local_moderncommerce_enrollkeys',
        'local_moderncommerce_coupon_usage',
        'local_moderncommerce_coupon_targets',
        'local_moderncommerce_coupons',
        'local_moderncommerce_tax_rates',
        'local_moderncommerce_product_attribute_values',
        'local_moderncommerce_product_relations',
        'local_moderncommerce_product_category_map',
        'local_moderncommerce_wishlist',
        'local_moderncommerce_product_tags',
        'local_moderncommerce_product_inventory',
        'local_moderncommerce_product_prices',
        'local_moderncommerce_product_courses',
        'local_moderncommerce_product_attributes',
        'local_moderncommerce_product_categories',
        'local_moderncommerce_course_objectives',
        'local_moderncommerce_course_outline',
        'local_moderncommerce_course_meta',
        'local_moderncommerce_products',
        'local_moderncommerce_gateways',
    ];

    /**
     * Seed the data that a new install needs before any merchant data exists.
     *
     * This is intentionally conservative: it creates default role presets,
     * gateways, email templates, subscription email preferences, and storefront
     * widgets, but it does not create fake Moodle courses or fake orders.
     *
     * @param bool $resetstorefront Whether to replace existing storefront widgets.
     * @return array Seed summary.
     */
    public static function seed_install_defaults(bool $resetstorefront = false): array {
        \local_moderncommerce\payment\gateway_manager::sync_builtin_gateways();
        $rolepresets = \local_moderncommerce\services\role_preset_service::seed_presets();
        \local_moderncommerce\email\renderer::reset_shell_html();

        $emails = 0;
        if (self::table_exists('local_moderncommerce_emailtpl')) {
            $emails = \local_moderncommerce\email\demo_seed::seed();
        }

        if (self::table_exists('local_moderncommerce_widget')) {
            \local_moderncommerce\storefront\seed::run($resetstorefront);
        }

        if (self::table_exists('local_moderncommerce_subscription_emailtpl')) {
            \local_moderncommerce\subscription\services\notification_service::ensure_local_email_records();
        }

        return [
            'gateways' => self::count_records('local_moderncommerce_gateways'),
            'rolepresets' => [
                'created' => (int) $rolepresets['created'],
                'updated' => (int) $rolepresets['updated'],
                'unchanged' => (int) $rolepresets['unchanged'],
                'skipped' => (int) $rolepresets['skipped'],
                'capabilitiesadded' => (int) $rolepresets['capabilitiesadded'],
            ],
            'emailtemplates' => $emails,
            'storefrontwidgets' => self::count_records('local_moderncommerce_widget'),
            'subscriptionemailtemplates' => self::count_records('local_moderncommerce_subscription_emailtpl'),
        ];
    }

    /**
     * Seed the full review/demo data set.
     *
     * @param array $options Seeder options.
     * @return array Seed summary.
     */
    public static function seed_full_demo(array $options = []): array {
        global $CFG;

        $options = array_merge(self::DEFAULT_DEMO_OPTIONS, $options);
        $install = self::seed_install_defaults();

        if (!defined('LOCAL_MODERNCOMMERCE_DEMO_DATA_INCLUDE')) {
            define('LOCAL_MODERNCOMMERCE_DEMO_DATA_INCLUDE', true);
        }

        require_once($CFG->dirroot . '/local/moderncommerce/cli/seed_sample_data.php');
        require_once($CFG->dirroot . '/local/moderncommerce/cli/seed_subscription_features.php');

        $sample = local_moderncommerce_seed_sample_data(
            true,
            (int) $options['userid'],
            max(1, (int) $options['categories']),
            max(1, (int) $options['courses']),
            max(0, (int) $options['products']),
            max(1, (int) $options['orders']),
            max(1, (int) $options['coupons']),
            max(1, (int) $options['keys']),
            max(0, (int) $options['reviews'])
        );

        $subscriptions = local_moderncommerce_seed_subscription_feature_matrix();
        $supplemental = self::seed_supplemental_demo_records((int) $options['userid']);
        $roleaccounts = role_account_manager::seed_accounts();

        return [
            'install' => $install,
            'sample' => $sample,
            'subscriptions' => $subscriptions,
            'supplemental' => $supplemental,
            'roleaccounts' => $roleaccounts,
            'coverage' => self::audit_table_counts(),
        ];
    }

    /**
     * Delete all Modern Commerce table data and seeded Moodle demo courses/categories.
     *
     * @param bool $deletedemocourses Whether to remove the Moodle demo catalog.
     * @return array Reset summary.
     */
    public static function reset_to_empty(bool $deletedemocourses = true): array {
        global $DB, $CFG;

        $deleted = [];
        $disablefk = in_array($CFG->dbtype, ['mariadb', 'mysqli'], true);
        if ($disablefk) {
            $DB->execute('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach (self::RESET_TABLES as $table) {
                if (!self::table_exists($table)) {
                    continue;
                }
                $deleted[$table] = (int) $DB->count_records($table);
                $DB->delete_records($table);
            }
        } finally {
            if ($disablefk) {
                $DB->execute('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $moodle = ['courses' => 0, 'categories' => 0];
        if ($deletedemocourses) {
            $moodle = self::delete_demo_moodle_catalog();
        }

        return [
            'tables' => $deleted,
            'moodle' => $moodle,
        ];
    }

    /**
     * Return row counts and empty tables for Modern Commerce tables.
     *
     * @return array Audit summary.
     */
    public static function audit_table_counts(): array {
        global $DB;

        $counts = [];
        $empty = [];
        foreach (self::schema_tables() as $table) {
            if (!self::table_exists($table)) {
                continue;
            }
            $count = (int) $DB->count_records($table);
            $counts[$table] = $count;
            if ($count === 0) {
                $empty[] = $table;
            }
        }

        return [
            'tables' => $counts,
            'empty' => $empty,
            'total' => count($counts),
        ];
    }

    /**
     * Seed auxiliary tables not covered by the original catalog/order seeder.
     *
     * @param int $preferreduserid Preferred demo user ID.
     * @return array Counts by seeded group.
     */
    private static function seed_supplemental_demo_records(int $preferreduserid): array {
        $now = time();
        $userid = self::resolve_userid($preferreduserid);
        $context = self::demo_context();

        if (empty($context['product']) || empty($context['course'])) {
            return ['skipped' => 1];
        }

        $counts = [];
        $counts['coursefeatures'] = self::seed_course_features($context['course']->id, $now);
        $counts['bundlefeatures'] = self::seed_bundle_features($context, $userid, $now);
        $counts['checkout'] = self::seed_checkout_auxiliary($context, $userid, $now);
        $counts['marketing'] = self::seed_marketing_auxiliary($userid, $now);
        $counts['contacts'] = self::seed_contacts($userid, $now);
        $counts['notifications'] = self::seed_notifications($userid, $now);
        $counts['subscriptions'] = self::seed_subscription_auxiliary($context, $userid, $now);
        $counts['reports'] = self::seed_report_auxiliary($context, $now);

        return $counts;
    }

    /**
     * Seed course objectives and outline rows.
     *
     * @param int $courseid Course ID.
     * @param int $now Current time.
     * @return int Rows touched.
     */
    private static function seed_course_features(int $courseid, int $now): int {
        $rows = 0;
        $objectives = [
            10 => 'Understand the core workflow and commercial model.',
            20 => 'Apply the concepts in a realistic Moodle commerce scenario.',
            30 => 'Review operational reporting and learner access outcomes.',
        ];
        foreach ($objectives as $sortorder => $objective) {
            self::upsert('local_moderncommerce_course_objectives', [
                'courseid' => $courseid,
                'sortorder' => $sortorder,
            ], [
                'courseid' => $courseid,
                'sortorder' => $sortorder,
                'objective' => $objective,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $rows++;
        }

        $outline = [
            10 => ['Market fit and offer structure', '45 min'],
            20 => ['Checkout, access, and lifecycle automation', '60 min'],
            30 => ['Reporting and optimization review', '30 min'],
        ];
        foreach ($outline as $sortorder => $section) {
            self::upsert('local_moderncommerce_course_outline', [
                'courseid' => $courseid,
                'sortorder' => $sortorder,
            ], [
                'courseid' => $courseid,
                'sortorder' => $sortorder,
                'sectiontitle' => $section[0],
                'estimatedtime' => $section[1],
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $rows++;
        }

        return $rows;
    }

    /**
     * Seed bundle metadata rows.
     *
     * @param array $context Demo context.
     * @param int $userid User ID.
     * @param int $now Current time.
     * @return int Rows touched.
     */
    private static function seed_bundle_features(array $context, int $userid, int $now): int {
        $bundle = $context['bundle'] ?? null;
        if (!$bundle) {
            return 0;
        }

        $courseids = self::bundle_course_ids((int) $bundle->id);
        if (empty($courseids)) {
            $courseids = [(int) $context['course']->id];
        }

        $rows = 0;
        self::upsert('local_moderncommerce_bundle_meta', ['bundleid' => $bundle->id], [
            'bundleid' => $bundle->id,
            'visibility' => 'Public',
            'availstart' => $now - WEEKSECS,
            'availend' => $now + (90 * DAYSECS),
            'badge_featured' => 1,
            'badge_bestseller' => 1,
            'badge_trending' => 1,
            'bundle_price' => 299.000000,
            'compare_at' => 499.000000,
            'skill_level' => 'Intermediate',
            'language' => 'English',
            'has_prereq' => 1,
            'auto_duration' => 0,
            'dur_hours' => 18,
            'dur_mins' => 30,
            'auto_assessments' => 0,
            'assessments_count' => 4,
            'auto_outline' => 0,
            'pass_grade' => 70.00000,
            'pass_policy' => 'all_must_pass',
            'bundle_cert' => 1,
            'usermodified' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $rows++;

        $outline = [
            10 => 'Start with the commercial foundation and value proposition.',
            20 => 'Build the learner journey, checkout path, and access model.',
            30 => 'Finish with analytics, subscriptions, and retention actions.',
        ];
        foreach ($outline as $sortorder => $text) {
            self::upsert('local_moderncommerce_bundle_outline', [
                'bundleid' => $bundle->id,
                'sortorder' => $sortorder,
            ], [
                'bundleid' => $bundle->id,
                'sortorder' => $sortorder,
                'item_text' => $text,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => $userid,
            ]);
            $rows++;
        }

        foreach (array_slice($courseids, 0, 2) as $courseid) {
            self::upsert('local_moderncommerce_bundle_mustpass', [
                'bundleid' => $bundle->id,
                'courseid' => $courseid,
            ], [
                'bundleid' => $bundle->id,
                'courseid' => $courseid,
                'timecreated' => $now,
            ]);
            $rows++;
        }

        self::upsert('local_moderncommerce_bundle_prereq', [
            'bundleid' => $bundle->id,
            'courseid' => $courseids[0],
        ], [
            'bundleid' => $bundle->id,
            'courseid' => $courseids[0],
            'timecreated' => $now,
        ]);
        $rows++;

        foreach (['demo', 'career-track', 'certificate'] as $tag) {
            self::upsert('local_moderncommerce_bundle_tags', [
                'bundleid' => $bundle->id,
                'tag' => $tag,
            ], [
                'bundleid' => $bundle->id,
                'tag' => $tag,
                'timecreated' => $now,
                'timemodified' => $now,
                'usermodified' => $userid,
            ]);
            $rows++;
        }

        return $rows;
    }

    /**
     * Seed checkout, cart, tax, coupon, key usage, webhook, and billing rows.
     *
     * @param array $context Demo context.
     * @param int $userid User ID.
     * @param int $now Current time.
     * @return int Rows touched.
     */
    private static function seed_checkout_auxiliary(array $context, int $userid, int $now): int {
        $product = $context['product'];
        $price = $context['price'];
        $course = $context['course'];
        $order = $context['order'] ?? null;
        $orderitem = $context['orderitem'] ?? null;
        $rows = 0;

        self::upsert('local_moderncommerce_tax_rates', [
            'name' => 'Demo US Sales Tax',
            'country' => 'US',
            'taxcategory' => 'standard',
        ], [
            'name' => 'Demo US Sales Tax',
            'country' => 'US',
            'state' => 'NY',
            'postcode' => null,
            'taxcategory' => 'standard',
            'rate' => 7.50000,
            'priority' => 10,
            'compound' => 0,
            'enabled' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $rows++;

        self::upsert('local_moderncommerce_billing_profiles', [
            'userid' => $userid,
            'email' => self::user_email($userid),
        ], [
            'userid' => $userid,
            'firstname' => self::user_field($userid, 'firstname'),
            'lastname' => self::user_field($userid, 'lastname'),
            'company' => 'Modern Commerce Demo',
            'email' => self::user_email($userid),
            'phone' => '+1 555 0100',
            'address1' => '100 Demo Commerce Street',
            'address2' => 'Suite 10',
            'city' => 'Demo City',
            'state' => 'NY',
            'country' => 'US',
            'postcode' => '10001',
            'isdefault' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $rows++;

        $cartid = self::upsert('local_moderncommerce_carts', ['sessionid' => 'mc-demo-cart'], [
            'userid' => $userid,
            'sessionid' => 'mc-demo-cart',
            'status' => 'active',
            'currency' => 'USD',
            'couponcode' => 'MODERN20',
            'subtotal' => $product->amount,
            'discount' => round((float) $product->amount * 0.2, 6),
            'tax' => 0,
            'total' => round((float) $product->amount * 0.8, 6),
            'expiresat' => $now + DAYSECS,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $rows++;

        $cartitemid = self::upsert('local_moderncommerce_cart_items', [
            'cartid' => $cartid,
            'productid' => $product->id,
        ], [
            'cartid' => $cartid,
            'productid' => $product->id,
            'priceid' => $price ? $price->id : null,
            'quantity' => 1,
            'unitprice' => $product->amount,
            'currency' => 'USD',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $rows++;

        self::upsert('local_moderncommerce_inventory_reservations', [
            'productid' => $product->id,
            'cartid' => $cartid,
            'cartitemid' => $cartitemid,
        ], [
            'productid' => $product->id,
            'cartid' => $cartid,
            'cartitemid' => $cartitemid,
            'orderid' => $order ? $order->id : null,
            'orderitemid' => $orderitem ? $orderitem->id : null,
            'quantity' => 1,
            'status' => 'reserved',
            'expiresat' => $now + HOURSECS,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $rows++;

        $coupon = $context['coupon'] ?? null;
        if ($coupon) {
            self::upsert('local_moderncommerce_coupon_targets', [
                'couponid' => $coupon->id,
                'targettype' => 'product',
                'targetid' => $product->id,
            ], [
                'couponid' => $coupon->id,
                'targettype' => 'product',
                'targetid' => $product->id,
                'targetvalue' => null,
                'includemode' => 'include',
                'timecreated' => $now,
            ]);
            $rows++;

            if ($order) {
                self::upsert('local_moderncommerce_coupon_usage', [
                    'couponid' => $coupon->id,
                    'userid' => $userid,
                    'orderid' => $order->id,
                ], [
                    'couponid' => $coupon->id,
                    'userid' => $userid,
                    'orderid' => $order->id,
                    'discountamount' => max(1, (float) ($order->discount ?? 1)),
                    'timecreated' => $now,
                ]);
                $rows++;
            }
        }

        $enrollkey = $context['enrollkey'] ?? null;
        if ($enrollkey) {
            self::upsert('local_moderncommerce_key_usage', [
                'enrollkeyid' => $enrollkey->id,
                'userid' => $userid,
            ], [
                'enrollkeyid' => $enrollkey->id,
                'userid' => $userid,
                'orderid' => $order ? $order->id : null,
                'productid' => $product->id,
                'courseid' => $course->id,
                'valueused' => 0,
                'ipaddress' => '127.0.0.1',
                'useragent' => 'Modern Commerce demo seed',
                'timeredeemed' => $now,
            ]);
            $rows++;
        }

        self::upsert('local_moderncommerce_webhook_events', ['dedupekey' => 'MC-DEMO-WEBHOOK-001'], [
            'gateway' => 'stripe',
            'dedupekey' => 'MC-DEMO-WEBHOOK-001',
            'gatewayeventid' => 'evt_mc_demo_001',
            'eventtype' => 'checkout.session.completed',
            'reference' => $order ? $order->ordernumber : 'MC-DEMO-ORDER',
            'signatureverified' => 1,
            'payloadhash' => hash('sha256', 'mc-demo-webhook'),
            'payload' => json_encode(['demo' => true, 'source' => 'seed']),
            'status' => 'processed',
            'attemptcount' => 1,
            'lasterror' => null,
            'timecreated' => $now,
            'timeprocessed' => $now,
        ]);
        $rows++;

        return $rows;
    }

    /**
     * Seed subscriber, dashboard preference, and widget preset rows.
     *
     * @param int $userid User ID.
     * @param int $now Current time.
     * @return int Rows touched.
     */
    private static function seed_marketing_auxiliary(int $userid, int $now): int {
        $rows = 0;
        self::upsert('local_moderncommerce_subscriber', ['email' => 'learner.demo@example.com'], [
            'email' => 'learner.demo@example.com',
            'source' => 'demo_seed',
            'userid' => $userid,
            'timecreated' => $now,
        ]);
        $rows++;

        self::upsert('local_moderncommerce_dashpref', ['userid' => $userid], [
            'userid' => $userid,
            'chartslayout' => json_encode(['revenue', 'orders', 'conversion']),
            'panellayout' => json_encode(['summary', 'recent_orders', 'top_products']),
            'daterange' => 'last30days',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $rows++;

        // The 'videohero' value is the real widget type key, and the styleconfig keys must come from
        // preset_service::STYLE_KEYS -- anything else is dropped when the preset is applied.
        self::upsert('local_moderncommerce_widget_preset', [
            'type' => 'videohero',
            'name' => 'Demo editorial hero',
        ], [
            'type' => 'videohero',
            'name' => 'Demo editorial hero',
            'styleconfig' => json_encode([
                'bg' => 'var(--mc-secondary)',
                'headingcolor' => 'var(--mc-text-inverse)',
                'radius' => 8,
            ]),
            'settingspatch' => json_encode([
                'alignment' => 'left',
                'headingcolor' => 'var(--mc-text-inverse)',
            ]),
            'usermodified' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $rows++;

        return $rows;
    }

    /**
     * Seed contact conversation rows.
     *
     * @param int $userid User ID.
     * @param int $now Current time.
     * @return int Rows touched.
     */
    private static function seed_contacts(int $userid, int $now): int {
        $contactid = self::upsert('local_moderncommerce_contacts', ['replytoken' => 'mc-demo-contact-token'], [
            'fullname' => 'Demo Learner',
            'email' => 'learner.demo@example.com',
            'subject' => 'Question about a course bundle',
            'phone' => '+1 555 0123',
            'message' => 'I would like help choosing the right learning path for my team.',
            'status' => 'open',
            'source' => 'demo_seed',
            'replytoken' => 'mc-demo-contact-token',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        self::upsert('local_moderncommerce_contact_replies', [
            'contactid' => $contactid,
            'fromclient' => 0,
        ], [
            'contactid' => $contactid,
            'userid' => $userid,
            'fromclient' => 0,
            'message' => 'Thanks for reaching out. The demo bundle is a strong starting point for a team.',
            'timecreated' => $now + 300,
        ]);

        return 2;
    }

    /**
     * Seed notification queue, log, digest, identity, and suppression rows.
     *
     * @param int $userid User ID.
     * @param int $now Current time.
     * @return int Rows touched.
     */
    private static function seed_notifications(int $userid, int $now): int {
        $queueid = self::upsert('local_moderncommerce_notify_queue', ['dedupekey' => 'mc-demo-notify-001'], [
            'component' => 'local_moderncommerce',
            'eventkey' => 'demo_order_completed',
            'category' => 'orders',
            'priority' => 'normal',
            'templatekey' => 'moderncommerce_purchase_student_confirmation',
            'placeholders' => json_encode(['firstname' => self::user_field($userid, 'firstname')]),
            'recipientuserid' => $userid,
            'recipientemail' => self::user_email($userid),
            'channel' => 'email',
            'endpointref' => null,
            'subject' => 'Demo order completed',
            'body' => 'Your Modern Commerce demo order is complete.',
            'bodyformat' => FORMAT_HTML,
            'status' => 'sent',
            'attempts' => 1,
            'maxattempts' => 5,
            'lasterror' => null,
            'dedupekey' => 'mc-demo-notify-001',
            'scheduledtime' => $now,
            'nextattempttime' => 0,
            'senttime' => $now + 60,
            'contexturl' => '/local/moderncommerce/order.php',
            'relatedid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        self::upsert('local_moderncommerce_notify_log', [
            'queueid' => $queueid,
            'eventkey' => 'demo_order_completed',
        ], [
            'queueid' => $queueid,
            'component' => 'local_moderncommerce',
            'eventkey' => 'demo_order_completed',
            'category' => 'orders',
            'recipientuserid' => $userid,
            'recipientemail' => self::user_email($userid),
            'channel' => 'email',
            'subject' => 'Demo order completed',
            'body' => 'Your Modern Commerce demo order is complete.',
            'result' => 'sent',
            'error' => null,
            'httpcode' => 200,
            'externalid' => 'mc-demo-email-001',
            'timecreated' => $now + 60,
        ]);

        self::upsert('local_moderncommerce_notify_digest', [
            'recipientuserid' => $userid,
            'eventkey' => 'demo_daily_summary',
        ], [
            'recipientuserid' => $userid,
            'frequency' => 'daily',
            'category' => 'orders',
            'eventkey' => 'demo_daily_summary',
            'summary' => 'Demo digest with recent sales, access, and support activity.',
            'relatedid' => 0,
            'status' => 'pending',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        self::upsert('local_moderncommerce_notify_identity', [
            'userid' => $userid,
            'provider' => 'teams',
        ], [
            'userid' => $userid,
            'provider' => 'teams',
            'externalid' => 'mc-demo-teams-user',
            'workspace' => 'Modern Commerce Demo',
            'status' => 'verified',
            'verifiedat' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        self::upsert('local_moderncommerce_notify_suppression', [
            'userid' => $userid,
            'scope' => 'marketing',
        ], [
            'userid' => $userid,
            'email' => self::user_email($userid),
            'scope' => 'marketing',
            'reason' => 'demo preference',
            'token' => 'mc-demo-suppression-token',
            'timecreated' => $now,
        ]);

        return 5;
    }

    /**
     * Seed subscription lifecycle rows.
     *
     * @param array $context Demo context.
     * @param int $userid User ID.
     * @param int $now Current time.
     * @return int Rows touched.
     */
    private static function seed_subscription_auxiliary(array $context, int $userid, int $now): int {
        $plan = self::demo_subscription_plan();
        if (!$plan) {
            return 0;
        }

        $rows = self::seed_subscription_plan_features((int) $plan->id, $now);
        $courseid = (int) $context['course']->id;
        $order = $context['order'] ?? null;

        self::upsert('local_moderncommerce_subscription_access_rules', [
            'planid' => $plan->id,
            'access_type' => 'course',
            'target_id' => $courseid,
        ], [
            'planid' => $plan->id,
            'access_type' => 'course',
            'target_id' => $courseid,
            'timecreated' => $now,
        ]);
        $rows++;

        $subscriptionid = self::upsert('local_moderncommerce_user_subscriptions', [
            'userid' => $userid,
            'planid' => $plan->id,
            'stripe_subscription_id' => 'sub_mc_demo_001',
        ], [
            'userid' => $userid,
            'planid' => $plan->id,
            'orderid' => $order ? $order->id : null,
            'status' => 'active',
            'start_date' => $now - (10 * DAYSECS),
            'end_date' => $now + (20 * DAYSECS),
            'trial_end_date' => $now - (3 * DAYSECS),
            'trial_warning_sent' => 1,
            'grace_end_date' => null,
            'auto_renew' => 1,
            'renewal_count' => 1,
            'last_reminder_sent' => $now - DAYSECS,
            'cancellation_reason' => null,
            'cancelled_at' => null,
            'cancel_at_period_end' => 0,
            'pending_planid' => null,
            'pending_change_date' => null,
            'last_plan_change' => $now - (10 * DAYSECS),
            'account_credit' => 15.000000,
            'stripe_subscription_id' => 'sub_mc_demo_001',
            'stripe_customer_id' => 'cus_mc_demo_001',
            'stripe_payment_method_id' => 'pm_mc_demo_001',
            'paypal_subscription_id' => null,
            'paystack_subscription_code' => null,
            'flutterwave_subscription_id' => null,
            'next_billing_date' => $now + (20 * DAYSECS),
            'payment_failed_count' => 0,
            'last_payment_attempt' => $now - (10 * DAYSECS),
            'timecreated' => $now - (10 * DAYSECS),
            'timemodified' => $now,
        ]);
        $rows++;

        self::upsert('local_moderncommerce_subscription_history', [
            'subscriptionid' => $subscriptionid,
            'action' => 'created',
        ], [
            'subscriptionid' => $subscriptionid,
            'userid' => $userid,
            'action' => 'created',
            'old_planid' => null,
            'new_planid' => $plan->id,
            'old_end_date' => null,
            'new_end_date' => $now + (20 * DAYSECS),
            'amount_paid' => $plan->price,
            'orderid' => $order ? $order->id : null,
            'notes' => 'Demo subscription created by Modern Commerce seed data.',
            'timecreated' => $now - (10 * DAYSECS),
            'createdby' => $userid,
        ]);
        $rows++;

        self::upsert('local_moderncommerce_subscription_reminders', [
            'subscriptionid' => $subscriptionid,
            'reminder_type' => 'renewal_7day',
        ], [
            'subscriptionid' => $subscriptionid,
            'reminder_type' => 'renewal_7day',
            'sent_at' => $now - DAYSECS,
        ]);
        $rows++;

        self::upsert('local_moderncommerce_subscription_access', [
            'userid' => $userid,
            'subscriptionid' => $subscriptionid,
            'courseid' => $courseid,
        ], [
            'userid' => $userid,
            'subscriptionid' => $subscriptionid,
            'courseid' => $courseid,
            'granted_at' => $now - (10 * DAYSECS),
            'expires_at' => $now + (20 * DAYSECS),
        ]);
        $rows++;

        $subkeyid = self::upsert('local_moderncommerce_subscription_keys', ['keycode' => 'MC-SUB-DEMO-KEY'], [
            'keycode' => 'MC-SUB-DEMO-KEY',
            'planid' => $plan->id,
            'value' => 0,
            'currency' => 'USD',
            'duration_days' => 30,
            'maxuses' => 10,
            'usedcount' => 1,
            'maxusesperuser' => 1,
            'batchid' => 'MC-SUB-DEMO',
            'batchname' => 'Modern Commerce subscription demo keys',
            'status' => 'active',
            'startdate' => $now - DAYSECS,
            'expirydate' => $now + (60 * DAYSECS),
            'userids' => null,
            'cohortids' => null,
            'requiredemail' => null,
            'notes' => 'Demo subscription key.',
            'createdby' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $rows++;

        self::upsert('local_moderncommerce_subscription_key_usage', [
            'keyid' => $subkeyid,
            'userid' => $userid,
        ], [
            'keyid' => $subkeyid,
            'userid' => $userid,
            'subscriptionid' => $subscriptionid,
            'orderid' => $order ? $order->id : null,
            'ipaddress' => '127.0.0.1',
            'timecreated' => $now,
        ]);
        $rows++;

        self::upsert('local_moderncommerce_subscription_log', [
            'subscriptionid' => $subscriptionid,
            'action' => 'demo_seed',
        ], [
            'subscriptionid' => $subscriptionid,
            'userid' => $userid,
            'planid' => $plan->id,
            'action' => 'demo_seed',
            'details' => json_encode(['seed' => 'full_demo']),
            'timecreated' => $now,
        ]);
        $rows++;

        return $rows;
    }

    /**
     * Seed old-style plan feature bullets for pages that still read that table.
     *
     * @param int $planid Plan ID.
     * @param int $now Current time.
     * @return int Rows touched.
     */
    private static function seed_subscription_plan_features(int $planid, int $now): int {
        $features = [
            ['Unlimited demo course access', 'collection'],
            ['Completion certificates', 'award'],
            ['Priority learner support', 'headset'],
        ];

        $rows = 0;
        foreach ($features as $index => $feature) {
            self::upsert('local_moderncommerce_subscription_plan_features', [
                'planid' => $planid,
                'feature' => $feature[0],
            ], [
                'planid' => $planid,
                'feature' => $feature[0],
                'icon' => $feature[1],
                'sortorder' => ($index + 1) * 10,
                'timecreated' => $now,
            ]);
            $rows++;
        }

        return $rows;
    }

    /**
     * Ensure report tables have non-order demo coverage.
     *
     * @param array $context Demo context.
     * @param int $now Current time.
     * @return int Rows touched.
     */
    private static function seed_report_auxiliary(array $context, int $now): int {
        $product = $context['product'];
        $reportdate = strtotime(date('Y-m-d 00:00:00', $now - DAYSECS));

        self::upsert('local_moderncommerce_report_daily', [
            'reportdate' => $reportdate,
            'currency' => 'USD',
        ], [
            'reportdate' => $reportdate,
            'currency' => 'USD',
            'orders' => 8,
            'paidorders' => 7,
            'refunds' => 1,
            'gross' => 820.000000,
            'discount' => 75.000000,
            'tax' => 48.000000,
            'net' => 793.000000,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        self::upsert('local_moderncommerce_report_products', [
            'reportdate' => $reportdate,
            'productid' => $product->id,
            'currency' => 'USD',
        ], [
            'reportdate' => $reportdate,
            'productid' => $product->id,
            'currency' => 'USD',
            'quantity' => 8,
            'gross' => 820.000000,
            'discount' => 75.000000,
            'net' => 793.000000,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        self::upsert('local_moderncommerce_report_gateways', [
            'reportdate' => $reportdate,
            'gateway' => 'stripe',
            'currency' => 'USD',
        ], [
            'reportdate' => $reportdate,
            'gateway' => 'stripe',
            'currency' => 'USD',
            'attempts' => 9,
            'successful' => 7,
            'failed' => 2,
            'amount' => 793.000000,
            'fees' => 19.000000,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        return 3;
    }

    /**
     * Build commonly needed parent records for supplemental seeding.
     *
     * @return array Demo context.
     */
    private static function demo_context(): array {
        global $DB;

        $products = $DB->get_records_select(
            'local_moderncommerce_products',
            $DB->sql_like('sku', ':sku', false) . " AND producttype = :type",
            ['sku' => 'MC-DEMO-COURSE-%', 'type' => 'course'],
            'id ASC',
            '*',
            0,
            1
        );
        $product = $products ? reset($products) : null;
        $bundles = $DB->get_records_select(
            'local_moderncommerce_products',
            $DB->sql_like('sku', ':sku', false) . " AND producttype = :type",
            ['sku' => 'MC-DEMO-BUNDLE-%', 'type' => 'bundle'],
            'id ASC',
            '*',
            0,
            1
        );
        $bundle = $bundles ? reset($bundles) : null;
        $course = null;
        $price = null;
        if ($product) {
            $courseid = $DB->get_field(
                'local_moderncommerce_product_courses',
                'courseid',
                ['productid' => $product->id],
                IGNORE_MULTIPLE
            );
            if ($courseid) {
                $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, shortname', IGNORE_MISSING);
            }
            $price = self::first_record('local_moderncommerce_product_prices', ['productid' => $product->id]);
            $product->amount = $price ? (float) $price->amount : 0;
        }

        return [
            'product' => $product,
            'bundle' => $bundle,
            'course' => $course,
            'price' => $price,
            'order' => self::first_demo_order(),
            'orderitem' => self::first_demo_order_item(),
            'coupon' => self::first_record('local_moderncommerce_coupons', ['code' => 'MODERN20']),
            'enrollkey' => self::first_record('local_moderncommerce_enrollkeys', ['keycode' => 'MODERN-DEMO-KEY']),
        ];
    }

    /**
     * Get course IDs included in a bundle product.
     *
     * @param int $bundleid Bundle product ID.
     * @return int[]
     */
    private static function bundle_course_ids(int $bundleid): array {
        global $DB;

        return array_map('intval', $DB->get_fieldset_select(
            'local_moderncommerce_product_courses',
            'courseid',
            'productid = :productid',
            ['productid' => $bundleid]
        ));
    }

    /**
     * Get the primary demo subscription plan.
     *
     * @return \stdClass|null
     */
    private static function demo_subscription_plan(): ?\stdClass {
        return self::first_record('local_moderncommerce_subscription_plans', ['code' => 'demo_growth'])
            ?: self::first_record('local_moderncommerce_subscription_plans', ['code' => 'demo_starter']);
    }

    /**
     * Resolve a user ID for seeded user-scoped rows.
     *
     * @param int $preferreduserid Preferred user ID.
     * @return int
     */
    private static function resolve_userid(int $preferreduserid): int {
        global $DB, $CFG;

        if ($preferreduserid > 0 && $DB->record_exists('user', ['id' => $preferreduserid, 'deleted' => 0])) {
            return $preferreduserid;
        }

        $siteadmins = array_filter(array_map('intval', explode(',', $CFG->siteadmins ?? '')));
        foreach ($siteadmins as $adminid) {
            if ($DB->record_exists('user', ['id' => $adminid, 'deleted' => 0])) {
                return $adminid;
            }
        }

        return (int) $DB->get_field_select('user', 'id', 'deleted = 0 AND id <> :guestid', ['guestid' => 1]);
    }

    /**
     * Get a user email.
     *
     * @param int $userid User ID.
     * @return string
     */
    private static function user_email(int $userid): string {
        return (string) self::user_field($userid, 'email');
    }

    /**
     * Get one user field.
     *
     * @param int $userid User ID.
     * @param string $field Field name.
     * @return string
     */
    private static function user_field(int $userid, string $field): string {
        global $DB;

        return (string) $DB->get_field('user', $field, ['id' => $userid], IGNORE_MISSING);
    }

    /**
     * Get a first record for simple conditions.
     *
     * @param string $table Table name.
     * @param array $conditions Conditions.
     * @return \stdClass|null
     */
    private static function first_record(string $table, array $conditions): ?\stdClass {
        global $DB;

        if (!self::table_exists($table)) {
            return null;
        }

        $record = $DB->get_record($table, $conditions, '*', IGNORE_MULTIPLE);

        return $record ?: null;
    }

    /**
     * Get the first demo order.
     *
     * @return \stdClass|null
     */
    private static function first_demo_order(): ?\stdClass {
        global $DB;

        $records = $DB->get_records_select(
            'local_moderncommerce_orders',
            $DB->sql_like('ordernumber', ':ordernumber', false),
            ['ordernumber' => 'MC-DEMO-ORDER-%'],
            'id ASC',
            '*',
            0,
            1
        );
        $record = $records ? reset($records) : null;

        return $record ?: null;
    }

    /**
     * Get the first order item for the first demo order.
     *
     * @return \stdClass|null
     */
    private static function first_demo_order_item(): ?\stdClass {
        $order = self::first_demo_order();
        if (!$order) {
            return null;
        }

        return self::first_record('local_moderncommerce_order_items', ['orderid' => $order->id]);
    }

    /**
     * Insert or update a record.
     *
     * @param string $table Table name.
     * @param array $conditions Unique lookup conditions.
     * @param array $data Data.
     * @return int Record ID.
     */
    private static function upsert(string $table, array $conditions, array $data): int {
        global $DB;

        if (!self::table_exists($table)) {
            return 0;
        }

        $existing = $DB->get_record($table, $conditions, 'id', IGNORE_MULTIPLE);
        $record = (object) $data;
        if ($existing) {
            $record->id = (int) $existing->id;
            $DB->update_record($table, $record);
            return (int) $existing->id;
        }

        return (int) $DB->insert_record($table, $record);
    }

    /**
     * Count records in a table, returning 0 when the table is not installed.
     *
     * @param string $table Table name.
     * @return int
     */
    private static function count_records(string $table): int {
        global $DB;

        return self::table_exists($table) ? (int) $DB->count_records($table) : 0;
    }

    /**
     * Check whether a plugin table exists.
     *
     * @param string $table Table name.
     * @return bool
     */
    private static function table_exists(string $table): bool {
        global $DB, $CFG;

        require_once($CFG->libdir . '/ddllib.php');

        return $DB->get_manager()->table_exists(new \xmldb_table($table));
    }

    /**
     * Return all plugin table names from the install XML in schema order.
     *
     * @return string[]
     */
    private static function schema_tables(): array {
        global $CFG;

        $installxml = $CFG->dirroot . '/local/moderncommerce/db/install.xml';
        $xml = simplexml_load_file($installxml);
        if (!$xml || !isset($xml->TABLES->TABLE)) {
            return self::RESET_TABLES;
        }

        $tables = [];
        foreach ($xml->TABLES->TABLE as $table) {
            $tables[] = (string) $table['NAME'];
        }

        return $tables;
    }

    /**
     * Delete seeded Moodle demo courses and categories.
     *
     * @return array Deleted Moodle row counts.
     */
    private static function delete_demo_moodle_catalog(): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/lib.php');

        $courseids = $DB->get_fieldset_select(
            'course',
            'id',
            $DB->sql_like('idnumber', ':idnumber', false),
            ['idnumber' => self::DEMO_COURSE_PREFIX . '%']
        );
        foreach ($courseids as $courseid) {
            delete_course((int) $courseid, false);
        }

        $categories = $DB->get_records_select(
            'course_categories',
            $DB->sql_like('idnumber', ':idnumber', false),
            ['idnumber' => self::DEMO_CATEGORY_PREFIX . '%'],
            'depth DESC, id DESC',
            'id'
        );
        foreach ($categories as $category) {
            $coursecategory = \core_course_category::get((int) $category->id, IGNORE_MISSING);
            if ($coursecategory) {
                $coursecategory->delete_full(false);
            }
        }

        return [
            'courses' => count($courseids),
            'categories' => count($categories),
        ];
    }
}
