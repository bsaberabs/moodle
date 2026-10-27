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

use context_system;

/**
 * Seeds Modern Commerce role presets into Moodle's native role system.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_preset_service {
    /** @var string Component name. */
    private const COMPONENT = 'local_moderncommerce';

    /** @var string Marker used to prove the plugin owns a seeded role shortname. */
    private const MARKER_PREFIX = 'Seeded role preset: local_moderncommerce:';

    /**
     * All Modern Commerce system-level admin capabilities.
     *
     * Buyer and learner capabilities are intentionally excluded; users receive
     * those through Moodle's normal authenticated-user role defaults.
     *
     * @var string[]
     */
    private const ADMIN_CAPABILITIES = [
        'local/moderncommerce:managestorefront',
        'local/moderncommerce:managesettings',
        'local/moderncommerce:viewallorders',
        'local/moderncommerce:manageorders',
        'local/moderncommerce:managecourses',
        'local/moderncommerce:managecategories',
        'local/moderncommerce:managecoupons',
        'local/moderncommerce:generatekeys',
        'local/moderncommerce:viewreports',
        'local/moderncommerce:viewauditlog',
        'local/moderncommerce:configuregateways',
        'local/moderncommerce:viewemailtemplates',
        'local/moderncommerce:manageemailtemplates',
        'local/moderncommerce:managereviews',
        'local/moderncommerce:processrefunds',
        'local/moderncommerce:receivenotificationops',
        'local/moderncommerce:managenotifications',
        'local/moderncommerce:viewnotificationlog',
        'local/moderncommerce:viewcontacts',
        'local/moderncommerce:managecontacts',
        'local/moderncommerce:viewnewsletter',
        'local/moderncommerce:managenewsletter',
        'local/moderncommerce:managesubscriptionplans',
        'local/moderncommerce:viewsubscribers',
        'local/moderncommerce:managesubscriptions',
        'local/moderncommerce:viewsubscriptionreports',
        'local/moderncommerce:managesubscriptionfeatures',
    ];

    /**
     * Return all Modern Commerce system-level admin capabilities.
     *
     * @return string[]
     */
    public static function admin_capabilities(): array {
        return self::ADMIN_CAPABILITIES;
    }

    /**
     * Return the built-in Modern Commerce role presets.
     *
     * @return array<string,array{name:string,shortname:string,description:string,capabilities:string[]}>
     */
    public static function presets(): array {
        return [
            'admin' => [
                'name' => 'Modern Commerce Administrator',
                'shortname' => 'moderncommerceadmin',
                'description' => 'Can manage all Modern Commerce operational pages and settings.',
                'capabilities' => self::ADMIN_CAPABILITIES,
            ],
            'finance' => [
                'name' => 'Modern Commerce Finance',
                'shortname' => 'moderncommercefinance',
                'description' => 'Can review commerce revenue, orders, refunds, subscribers, and audit evidence.',
                'capabilities' => [
                    'local/moderncommerce:viewreports',
                    'local/moderncommerce:viewsubscriptionreports',
                    'local/moderncommerce:viewallorders',
                    'local/moderncommerce:manageorders',
                    'local/moderncommerce:processrefunds',
                    'local/moderncommerce:viewauditlog',
                    'local/moderncommerce:viewsubscribers',
                ],
            ],
            'product' => [
                'name' => 'Modern Commerce Product Manager',
                'shortname' => 'moderncommerceproduct',
                'description' => 'Can manage products, pricing, coupons, enrolment keys, reviews, and product reporting.',
                'capabilities' => [
                    'local/moderncommerce:managecourses',
                    'local/moderncommerce:managecoupons',
                    'local/moderncommerce:generatekeys',
                    'local/moderncommerce:managereviews',
                    'local/moderncommerce:viewreports',
                ],
            ],
            'reporting' => [
                'name' => 'Modern Commerce Reporting Manager',
                'shortname' => 'moderncommercereporting',
                'description' => 'Can inspect commerce reports, orders, subscribers, audit logs, and notification delivery logs.',
                'capabilities' => [
                    'local/moderncommerce:viewreports',
                    'local/moderncommerce:viewsubscriptionreports',
                    'local/moderncommerce:viewallorders',
                    'local/moderncommerce:viewauditlog',
                    'local/moderncommerce:viewsubscribers',
                    'local/moderncommerce:viewnotificationlog',
                ],
            ],
            'storefront' => [
                'name' => 'Modern Commerce Storefront Manager',
                'shortname' => 'moderncommercestorefront',
                'description' => 'Can manage store pages, catalog presentation, products, and public review signals.',
                'capabilities' => [
                    'local/moderncommerce:managestorefront',
                    'local/moderncommerce:managecourses',
                    'local/moderncommerce:managereviews',
                ],
            ],
            'marketing' => [
                'name' => 'Modern Commerce Marketing Manager',
                'shortname' => 'moderncommercemarketing',
                'description' => 'Can manage coupons, email templates, contact leads, and newsletter subscribers.',
                'capabilities' => [
                    'local/moderncommerce:managecoupons',
                    'local/moderncommerce:viewemailtemplates',
                    'local/moderncommerce:manageemailtemplates',
                    'local/moderncommerce:viewcontacts',
                    'local/moderncommerce:managecontacts',
                    'local/moderncommerce:viewnewsletter',
                    'local/moderncommerce:managenewsletter',
                ],
            ],
            'support' => [
                'name' => 'Modern Commerce Support',
                'shortname' => 'moderncommercesupport',
                'description' => 'Can view orders and manage customer contact conversations.',
                'capabilities' => [
                    'local/moderncommerce:viewallorders',
                    'local/moderncommerce:viewcontacts',
                    'local/moderncommerce:managecontacts',
                    'local/moderncommerce:viewnewsletter',
                ],
            ],
            'subscription' => [
                'name' => 'Modern Commerce Subscription Manager',
                'shortname' => 'moderncommercesubscription',
                'description' => 'Can manage subscription plans, features, subscribers, subscriptions, and subscription reporting.',
                'capabilities' => [
                    'local/moderncommerce:managesubscriptionplans',
                    'local/moderncommerce:managesubscriptionfeatures',
                    'local/moderncommerce:viewsubscribers',
                    'local/moderncommerce:managesubscriptions',
                    'local/moderncommerce:viewsubscriptionreports',
                ],
            ],
            'paymentops' => [
                'name' => 'Modern Commerce Payment Operations',
                'shortname' => 'moderncommercepaymentops',
                'description' => 'Can configure gateways, manage orders and refunds, and inspect payment operations evidence.',
                'capabilities' => [
                    'local/moderncommerce:configuregateways',
                    'local/moderncommerce:viewallorders',
                    'local/moderncommerce:manageorders',
                    'local/moderncommerce:processrefunds',
                    'local/moderncommerce:viewreports',
                    'local/moderncommerce:viewauditlog',
                    'local/moderncommerce:viewnotificationlog',
                ],
            ],
        ];
    }

    /**
     * Seed all or one Modern Commerce preset role.
     *
     * @param bool $dryrun Whether to report changes without writing them.
     * @param string|null $only Optional preset key or role shortname.
     * @return array Seed summary.
     */
    public static function seed_presets(bool $dryrun = false, ?string $only = null): array {
        global $DB;

        $unknown = '';
        $presets = self::select_presets($only, $unknown);
        $summary = [
            'dryrun' => $dryrun,
            'only' => $only,
            'unknown' => $unknown,
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'skipped' => 0,
            'wouldcreate' => 0,
            'wouldupdate' => 0,
            'wouldleaveunchanged' => 0,
            'capabilitiesadded' => 0,
            'roles' => [],
        ];

        if ($unknown !== '') {
            return $summary;
        }

        if (!$dryrun) {
            self::ensure_capabilities_registered();
        }

        $context = context_system::instance();
        foreach ($presets as $key => $preset) {
            $role = $DB->get_record('role', ['shortname' => $preset['shortname']], '*', IGNORE_MISSING);
            if (!$role) {
                $result = self::create_role_from_preset($key, $preset, $context, $dryrun);
            } else if (!self::is_seeded_role($role, $key)) {
                $result = self::collision_result($key, $preset, $role);
            } else {
                $result = self::update_role_from_preset($key, $preset, $role, $context, $dryrun);
            }

            self::add_result_to_summary($summary, $result);
            $summary['roles'][] = $result;
        }

        return $summary;
    }

    /**
     * Select presets by key or shortname.
     *
     * @param string|null $only Optional preset key or shortname.
     * @param string $unknown Filled when the requested preset is unknown.
     * @return array
     */
    private static function select_presets(?string $only, string &$unknown): array {
        $presets = self::presets();
        $only = trim((string) $only);
        if ($only === '') {
            return $presets;
        }

        $needle = strtolower($only);
        foreach ($presets as $key => $preset) {
            if ($needle === strtolower($key) || $needle === strtolower($preset['shortname'])) {
                return [$key => $preset];
            }
        }

        $unknown = $only;
        return [];
    }

    /**
     * Create a missing preset role.
     *
     * @param string $key Preset key.
     * @param array $preset Preset data.
     * @param context_system $context System context.
     * @param bool $dryrun Whether to report only.
     * @return array Role seed result.
     */
    private static function create_role_from_preset(
        string $key,
        array $preset,
        context_system $context,
        bool $dryrun
    ): array {
        global $CFG;

        $capabilities = array_values(array_unique($preset['capabilities']));
        if ($dryrun) {
            return self::role_result($key, $preset, 0, 'would_create', [
                'capabilities_to_add' => $capabilities,
            ]);
        }

        require_once($CFG->libdir . '/accesslib.php');

        $roleid = create_role(
            $preset['name'],
            $preset['shortname'],
            self::description_with_marker($key, $preset['description'])
        );
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);
        $capabilityresult = self::add_missing_capabilities($roleid, $capabilities, $context, false);

        return self::role_result($key, $preset, $roleid, 'created', $capabilityresult);
    }

    /**
     * Update an existing marked preset role by adding missing capabilities.
     *
     * @param string $key Preset key.
     * @param array $preset Preset data.
     * @param object $role Existing role record.
     * @param context_system $context System context.
     * @param bool $dryrun Whether to report only.
     * @return array Role seed result.
     */
    private static function update_role_from_preset(
        string $key,
        array $preset,
        object $role,
        context_system $context,
        bool $dryrun
    ): array {
        $capabilities = array_values(array_unique($preset['capabilities']));
        $capabilityresult = self::add_missing_capabilities((int) $role->id, $capabilities, $context, $dryrun);

        if ($dryrun) {
            $status = empty($capabilityresult['capabilities_to_add']) ? 'would_leave_unchanged' : 'would_update';
            return self::role_result($key, $preset, (int) $role->id, $status, $capabilityresult);
        }

        $status = empty($capabilityresult['capabilities_added']) ? 'unchanged' : 'updated';
        return self::role_result($key, $preset, (int) $role->id, $status, $capabilityresult);
    }

    /**
     * Return a skip result for an unmarked role with a reserved shortname.
     *
     * @param string $key Preset key.
     * @param array $preset Preset data.
     * @param object $role Existing role record.
     * @return array Role seed result.
     */
    private static function collision_result(string $key, array $preset, object $role): array {
        return self::role_result($key, $preset, (int) $role->id, 'skipped_shortname_collision', [
            'reason' => 'shortname exists without Modern Commerce marker',
            'capabilities_existing' => [],
            'capabilities_to_add' => [],
            'capabilities_added' => [],
            'capabilities_preserved' => [],
        ]);
    }

    /**
     * Add missing role capabilities without overwriting existing admin edits.
     *
     * @param int $roleid Role ID.
     * @param string[] $capabilities Capabilities to allow.
     * @param context_system $context System context.
     * @param bool $dryrun Whether to report only.
     * @return array Capability result.
     */
    private static function add_missing_capabilities(
        int $roleid,
        array $capabilities,
        context_system $context,
        bool $dryrun
    ): array {
        global $CFG, $DB;

        $existing = [];
        $preserved = [];
        $toadd = [];
        $added = [];

        foreach ($capabilities as $capability) {
            $record = $DB->get_record('role_capabilities', [
                'roleid' => $roleid,
                'contextid' => $context->id,
                'capability' => $capability,
            ], 'id, permission', IGNORE_MISSING);

            if ($record) {
                if ((int) $record->permission === CAP_ALLOW) {
                    $existing[] = $capability;
                } else {
                    $preserved[] = $capability;
                }
                continue;
            }

            $toadd[] = $capability;
        }

        if (!$dryrun && !empty($toadd)) {
            require_once($CFG->libdir . '/accesslib.php');
            foreach ($toadd as $capability) {
                assign_capability($capability, CAP_ALLOW, $roleid, $context, false);
                $added[] = $capability;
            }
        }

        return [
            'capabilities_existing' => $existing,
            'capabilities_preserved' => $preserved,
            'capabilities_to_add' => $toadd,
            'capabilities_added' => $added,
        ];
    }

    /**
     * Register current plugin capability definitions before assigning new caps.
     */
    private static function ensure_capabilities_registered(): void {
        global $CFG;

        require_once($CFG->libdir . '/accesslib.php');
        update_capabilities(self::COMPONENT);
    }

    /**
     * Check whether an existing role is owned by this preset.
     *
     * @param object $role Existing role record.
     * @param string $key Preset key.
     * @return bool
     */
    private static function is_seeded_role(object $role, string $key): bool {
        return strpos((string) $role->description, self::marker($key)) !== false;
    }

    /**
     * Build the ownership marker for a preset.
     *
     * @param string $key Preset key.
     * @return string Marker.
     */
    private static function marker(string $key): string {
        return self::MARKER_PREFIX . $key;
    }

    /**
     * Append the ownership marker to a role description.
     *
     * @param string $key Preset key.
     * @param string $description Human description.
     * @return string Marked description.
     */
    private static function description_with_marker(string $key, string $description): string {
        return trim($description) . "\n\n" . self::marker($key);
    }

    /**
     * Build a role result row.
     *
     * @param string $key Preset key.
     * @param array $preset Preset data.
     * @param int $roleid Role ID or 0 for dry-run creation.
     * @param string $status Result status.
     * @param array $details Detail fields.
     * @return array Role result.
     */
    private static function role_result(
        string $key,
        array $preset,
        int $roleid,
        string $status,
        array $details
    ): array {
        return array_merge([
            'key' => $key,
            'shortname' => $preset['shortname'],
            'name' => $preset['name'],
            'roleid' => $roleid,
            'status' => $status,
        ], $details);
    }

    /**
     * Add one role result into the aggregate summary.
     *
     * @param array $summary Aggregate summary.
     * @param array $result Role result.
     */
    private static function add_result_to_summary(array &$summary, array $result): void {
        switch ($result['status']) {
            case 'created':
                $summary['created']++;
                break;
            case 'updated':
                $summary['updated']++;
                break;
            case 'unchanged':
                $summary['unchanged']++;
                break;
            case 'skipped_shortname_collision':
                $summary['skipped']++;
                break;
            case 'would_create':
                $summary['wouldcreate']++;
                break;
            case 'would_update':
                $summary['wouldupdate']++;
                break;
            case 'would_leave_unchanged':
                $summary['wouldleaveunchanged']++;
                break;
        }

        $summary['capabilitiesadded'] += count($result['capabilities_added'] ?? []);
    }
}
