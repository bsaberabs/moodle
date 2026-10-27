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

use context;
use moodle_url;

/**
 * Resolves access to the Modern Commerce admin area using Moodle capabilities.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_access_service {
    /** @var string Default documentation URL for admin-capable users with no operational landing page. */
    private const DOCUMENTATION_URL = '/local/moderncommerce/admin/help/index.php';

    /**
     * Capability-to-route map ordered by preferred landing page.
     *
     * @var array<string,string>
     */
    private const LANDING_ROUTES = [
        'local/moderncommerce:viewreports' => '/local/moderncommerce/admin/index.php',
        'local/moderncommerce:viewallorders' => '/local/moderncommerce/admin/orders.php',
        'local/moderncommerce:manageorders' => '/local/moderncommerce/admin/invoices.php',
        'local/moderncommerce:managecourses' => '/local/moderncommerce/admin/pricing.php',
        'local/moderncommerce:managecategories' => '/local/moderncommerce/admin/categories.php',
        'local/moderncommerce:managestorefront' => '/local/moderncommerce/admin/pages.php',
        'local/moderncommerce:managecoupons' => '/local/moderncommerce/admin/coupons.php',
        'local/moderncommerce:generatekeys' => '/local/moderncommerce/admin/keys.php',
        'local/moderncommerce:managereviews' => '/local/moderncommerce/admin/course_reviews.php',
        'local/moderncommerce:viewcontacts' => '/local/moderncommerce/admin/contacts.php',
        'local/moderncommerce:viewnewsletter' => '/local/moderncommerce/admin/newsletter_subscribers.php',
        'local/moderncommerce:manageemailtemplates' => '/local/moderncommerce/admin/email_templates.php',
        'local/moderncommerce:managesubscriptionplans' => '/local/moderncommerce/admin/subscriptions.php',
        'local/moderncommerce:managesubscriptionfeatures' => '/local/moderncommerce/admin/subscription_features.php',
        'local/moderncommerce:viewsubscribers' => '/local/moderncommerce/admin/subscription_subscribers.php',
        'local/moderncommerce:configuregateways' => '/local/moderncommerce/admin/gateways.php',
        'local/moderncommerce:viewauditlog' => '/local/moderncommerce/admin/audit_log.php',
        'local/moderncommerce:managenotifications' => '/local/moderncommerce/admin/notifications.php',
        'local/moderncommerce:managesettings' => '/local/moderncommerce/admin/settings.php',
    ];

    /**
     * Admin capabilities that do not have a guaranteed standalone landing page.
     *
     * They still allow the user into the admin documentation area and make the
     * primary navigation entry visible for narrowly scoped custom roles.
     *
     * @var string[]
     */
    private const SUPPORTING_CAPABILITIES = [
        'local/moderncommerce:managecontacts',
        'local/moderncommerce:managenewsletter',
        'local/moderncommerce:managesubscriptions',
        'local/moderncommerce:processrefunds',
        'local/moderncommerce:viewemailtemplates',
        'local/moderncommerce:receivenotificationops',
        'local/moderncommerce:viewnotificationlog',
        'local/moderncommerce:viewsubscriptionreports',
    ];

    /**
     * Return all capabilities that count as Modern Commerce admin access.
     *
     * @return string[]
     */
    public static function admin_capabilities(): array {
        return array_values(array_unique(array_merge(
            array_keys(self::LANDING_ROUTES),
            self::SUPPORTING_CAPABILITIES
        )));
    }

    /**
     * Check whether the current user can enter any Modern Commerce admin surface.
     *
     * @param context $context Capability context.
     * @return bool
     */
    public static function can_access_admin(context $context): bool {
        return has_any_capability(self::admin_capabilities(), $context);
    }

    /**
     * Resolve the first admin page the current user can open.
     *
     * @param context $context Capability context.
     * @return moodle_url|null Admin URL, or null when the user has no admin capability.
     */
    public static function resolve_landing_url(context $context): ?moodle_url {
        foreach (self::LANDING_ROUTES as $capability => $route) {
            if (has_capability($capability, $context)) {
                return new moodle_url($route);
            }
        }

        if (self::can_access_admin($context)) {
            return new moodle_url(self::DOCUMENTATION_URL);
        }

        return null;
    }
}
