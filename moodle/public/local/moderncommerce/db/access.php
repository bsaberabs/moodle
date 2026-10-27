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
 * Capability definitions for Modern Commerce plugin
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // View course catalog and prices.
    'local/moderncommerce:viewcatalog' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
            'guest' => CAP_ALLOW,
        ],
    ],

    // Arrange and configure the storefront landing-page widgets (in-page edit mode).
    'local/moderncommerce:managestorefront' => [
        'riskbitmask' => RISK_XSS | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage store-wide Modern Commerce settings, branding, and navigation.
    'local/moderncommerce:managesettings' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],

    // View course details page (per-course context), allow guests.
    'local/moderncommerce:viewdetails' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'user' => CAP_ALLOW,
            'guest' => CAP_ALLOW,
        ],
    ],

    // Purchase courses.
    'local/moderncommerce:purchase' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // View own purchase history.
    'local/moderncommerce:viewownorders' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Redeem enrollment keys.
    'local/moderncommerce:redeemkey' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // View all orders (admin).
    'local/moderncommerce:viewallorders' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage orders (create manual invoices, edit, refund).
    'local/moderncommerce:manageorders' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage course pricing.
    'local/moderncommerce:managecourses' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage global Modern Commerce product category structure.
    'local/moderncommerce:managecategories' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],

    // Manage coupons.
    'local/moderncommerce:managecoupons' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Generate enrollment keys.
    'local/moderncommerce:generatekeys' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // View reports.
    'local/moderncommerce:viewreports' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // View immutable commerce audit logs.
    'local/moderncommerce:viewauditlog' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
    ],

    // Configure payment gateways.
    'local/moderncommerce:configuregateways' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],

    // View email template content and shell configuration.
    'local/moderncommerce:viewemailtemplates' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Create, edit, clone and delete custom email templates and shell configuration.
    'local/moderncommerce:manageemailtemplates' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],

    // View visible course reviews as public course trust signals.
    'local/moderncommerce:viewreviews' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'user' => CAP_ALLOW,
            'guest' => CAP_ALLOW,
        ],
    ],

    // Submit a review for a course after verified learner access is confirmed.
    'local/moderncommerce:submitreview' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Manage course review visibility and moderation.
    'local/moderncommerce:managereviews' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Process refunds.
    'local/moderncommerce:processrefunds' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Who receives operational store alerts (new sale, refund, churn, gateway errors).
    'local/moderncommerce:receivenotificationops' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Configure notification channels and endpoints (Slack/Teams webhooks, secrets).
    'local/moderncommerce:managenotifications' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],

    // View the notification delivery log.
    'local/moderncommerce:viewnotificationlog' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // View contact form submissions and conversations.
    'local/moderncommerce:viewcontacts' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Reply to and manage contact form submissions.
    'local/moderncommerce:managecontacts' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // View newsletter / lead-capture subscribers.
    'local/moderncommerce:viewnewsletter' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Delete and manage newsletter / lead-capture subscribers.
    'local/moderncommerce:managenewsletter' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Subscription subsystem absorbed from local_modernsubscription.

    // Manage subscription plans (create, edit, delete).
    'local/moderncommerce:managesubscriptionplans' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],

    // View all subscribers.
    'local/moderncommerce:viewsubscribers' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage user subscriptions (activate, suspend, cancel).
    'local/moderncommerce:managesubscriptions' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // View own subscription.
    'local/moderncommerce:viewownsubscription' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // Purchase / renew a subscription.
    'local/moderncommerce:subscribetoplan' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_ALLOW,
        ],
    ],

    // View subscription reports.
    'local/moderncommerce:viewsubscriptionreports' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage subscription features (feature matrix).
    'local/moderncommerce:managesubscriptionfeatures' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
    ],

];
