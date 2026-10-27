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

namespace local_moderncommerce\output;

/**
 * Admin shell helper for rendering the admin dashboard shell with navigation.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_shell {
    /** @var string The active navigation key */
    private string $activenav;

    /** @var string Page title */
    private string $title;

    /** @var string Page subtitle */
    private string $subtitle;

    /** @var string Pre-rendered content HTML */
    private string $contenthtml;

    /** @var string Pre-rendered actions HTML */
    private string $actionshtml;

    /** @var bool Whether to hide the topbar */
    private bool $hidetopbar;

    /**
     * Navigation items definition.
     * Centralized location for all admin sidebar menu items.
     *
     * Each item can have:
     * - label: Language string key in local_moderncommerce
     * - url: Relative URL from wwwroot
     * - icon: Bootstrap icon class
     * - plugin: (optional) Plugin component name - item only shows if plugin is installed
     * - pluginaliases: (optional) Alternative component names accepted for the same add-on
     * - pluginurls: (optional) URL overrides keyed by installed component name
     * - capability: (optional) Single capability required to show the item
     * - capabilitiesany: (optional) Any one capability in this list shows the item
     * - capabilitiesall: (optional) Every capability in this list is required to show the item
     * - children: (optional) One-level nested navigation items
     */
    private const NAV_ITEMS = [
        'commerce' => [
            'label' => 'nav_commerce',
            'items' => [
                'dashboard' => [
                    'label' => 'nav_dashboard',
                    'url' => '/local/moderncommerce/admin/index.php',
                    'icon' => 'bi-speedometer2',
                    'capability' => 'local/moderncommerce:viewreports',
                ],
                'orders' => [
                    'label' => 'nav_orders',
                    'url' => '/local/moderncommerce/admin/orders.php',
                    'icon' => 'bi-bag',
                    'capability' => 'local/moderncommerce:viewallorders',
                ],
                'customers' => [
                    'label' => 'nav_customers',
                    'url' => '/local/moderncommerce/admin/customers.php',
                    'icon' => 'bi-people',
                    'capability' => 'local/moderncommerce:viewallorders',
                ],
                'invoices' => [
                    'label' => 'nav_invoices',
                    'url' => '/local/moderncommerce/admin/invoices.php',
                    'icon' => 'bi-file-text',
                    'capability' => 'local/moderncommerce:manageorders',
                ],
                'courses' => [
                    'label' => 'nav_courses',
                    'url' => '/local/moderncommerce/admin/pricing.php',
                    'icon' => 'bi-tag',
                    'capability' => 'local/moderncommerce:managecourses',
                ],
                'categories' => [
                    'label' => 'nav_categories',
                    'url' => '/local/moderncommerce/admin/categories.php',
                    'icon' => 'bi-tags',
                    'capability' => 'local/moderncommerce:managecategories',
                ],
                'bundles' => [
                    'label' => 'nav_bundles',
                    'url' => '/local/moderncommerce/admin/bundles.php',
                    'icon' => 'bi-layers',
                    'capability' => 'local/moderncommerce:managecourses',
                ],
                'subscriptions' => [
                    'label' => 'nav_subscriptions',
                    'url' => '/local/moderncommerce/admin/subscriptions.php',
                    'icon' => 'bi-credit-card',
                    'capability' => 'local/moderncommerce:managesubscriptionplans',
                    'children' => [
                        'subscriptionplans' => [
                            'label' => 'plans',
                            'url' => '/local/moderncommerce/admin/subscriptions.php',
                            'icon' => 'bi-card-list',
                            'capability' => 'local/moderncommerce:managesubscriptionplans',
                        ],
                        'subscriptionfeatures' => [
                            'label' => 'featurematrix',
                            'url' => '/local/moderncommerce/admin/subscription_features.php',
                            'icon' => 'bi-grid-3x3-gap',
                            'capability' => 'local/moderncommerce:managesubscriptionfeatures',
                        ],
                        'subscriptionsubscribers' => [
                            'label' => 'subscribers',
                            'url' => '/local/moderncommerce/admin/subscription_subscribers.php',
                            'icon' => 'bi-people',
                            'capability' => 'local/moderncommerce:viewsubscribers',
                        ],
                        'subscriptionemails' => [
                            'label' => 'nav_subscriptionemails',
                            'url' => '/local/moderncommerce/admin/subscription_emails.php',
                            'icon' => 'bi-envelope-open',
                            'capability' => 'local/moderncommerce:managesubscriptionplans',
                        ],
                    ],
                ],
                'coupons' => [
                    'label' => 'nav_coupons',
                    'url' => '/local/moderncommerce/admin/coupons.php',
                    'icon' => 'bi-ticket',
                    'capability' => 'local/moderncommerce:managecoupons',
                ],
            ],
        ],
        'keymanagement' => [
            'label' => 'nav_keymanagement',
            'items' => [
                'keys' => [
                    'label' => 'nav_coursekeys',
                    'url' => '/local/moderncommerce/admin/keys.php',
                    'icon' => 'bi-key',
                    'capability' => 'local/moderncommerce:generatekeys',
                ],
                'bundlekeys' => [
                    'label' => 'nav_bundlekeys',
                    'url' => '/local/moderncommerce/admin/bundle_keys.php',
                    'icon' => 'bi-key-fill',
                    'capability' => 'local/moderncommerce:generatekeys',
                ],
                'subscriptionkeys' => [
                    'label' => 'nav_subscriptionkeys',
                    'url' => '/local/moderncommerce/admin/subscription_keys.php',
                    'icon' => 'bi-key',
                    'capability' => 'local/moderncommerce:managesubscriptionplans',
                ],
            ],
        ],
        'design' => [
            'label' => 'nav_design',
            'items' => [
                'components' => [
                    'label' => 'nav_components',
                    'url' => '/local/moderncommerce/admin/components.php',
                    'icon' => 'bi-ui-checks-grid',
                    'capability' => 'moodle/site:config',
                ],
                'storepages' => [
                    'label' => 'nav_storepages',
                    'url' => '/local/moderncommerce/admin/pages.php',
                    'icon' => 'bi-file-earmark-richtext',
                    'capability' => 'local/moderncommerce:managestorefront',
                ],
                'widgetgallery' => [
                    'label' => 'nav_widgetgallery',
                    'url' => '/local/moderncommerce/admin/gallery.php',
                    'icon' => 'bi-collection',
                    'newtab' => true,
                    'capability' => 'local/moderncommerce:managestorefront',
                ],
                'pagedesigner' => [
                    'label' => 'nav_pagedesigner',
                    'url' => '/local/ccp_pagedesigner/index.php',
                    'icon' => 'bi-layout-text-window-reverse',
                    'plugin' => 'local_ccp_pagedesigner',
                ],
                'blocks' => [
                    'label' => 'nav_blockcatalog',
                    'url' => '/local/ccp_pagedesigner/blocks.php',
                    'icon' => 'bi-grid-3x3-gap',
                    'plugin' => 'local_ccp_pagedesigner',
                ],
            ],
        ],
        'communication' => [
            'label' => 'nav_communication',
            'items' => [
                'contact' => [
                    'label' => 'nav_contactforms',
                    'url' => '/local/moderncommerce/admin/contacts.php',
                    'icon' => 'bi-person-lines-fill',
                    'capability' => 'local/moderncommerce:viewcontacts',
                ],
                'newsletter' => [
                    'label' => 'nav_newslettersubscribers',
                    'url' => '/local/moderncommerce/admin/newsletter_subscribers.php',
                    'icon' => 'bi-envelope-paper',
                    'capability' => 'local/moderncommerce:viewnewsletter',
                ],
                'email' => [
                    'label' => 'nav_emailtemplates',
                    'url' => '/local/moderncommerce/admin/email_templates.php',
                    'icon' => 'bi-envelope',
                    'capability' => 'local/moderncommerce:manageemailtemplates',
                ],
                'notifier' => [
                    'label' => 'nav_enrolmentnotifier',
                    'url' => '/local/ccp_enrolmentnotifier/admin/index.php',
                    'icon' => 'bi-bell',
                    'plugin' => 'local_ccp_enrolmentnotifier',
                    'capability' => 'local/modernenrolnotifier:viewlogs',
                    'pluginaliases' => [
                        'local_modernenrolnotifier',
                    ],
                    'pluginurls' => [
                        'local_modernenrolnotifier' => '/local/moderncommerce/admin/enrolment_notifier.php',
                    ],
                    'children' => [
                        'notifierrules' => [
                            'label' => 'enrolmentnotifierrules',
                            'url' => '/local/moderncommerce/admin/enrolment_notifier_rules.php',
                            'icon' => 'bi-diagram-3',
                            'plugin' => 'local_modernenrolnotifier',
                            'capabilitiesall' => [
                                'local/modernenrolnotifier:viewlogs',
                                'local/moderncommerce:managenotifications',
                            ],
                        ],
                        'notifiertemplates' => [
                            'label' => 'enrolmentnotifiertemplates',
                            'url' => '/local/moderncommerce/admin/enrolment_notifier_templates.php',
                            'icon' => 'bi-envelope-paper',
                            'plugin' => 'local_modernenrolnotifier',
                            'capabilitiesall' => [
                                'local/modernenrolnotifier:viewlogs',
                                'local/moderncommerce:managenotifications',
                            ],
                        ],
                        'notifiersettings' => [
                            'label' => 'enrolmentnotifiercoursesettings',
                            'url' => '/local/moderncommerce/admin/enrolment_notifier_course_settings.php',
                            'icon' => 'bi-sliders',
                            'plugin' => 'local_modernenrolnotifier',
                            'capabilitiesall' => [
                                'local/modernenrolnotifier:viewlogs',
                                'local/moderncommerce:managenotifications',
                            ],
                        ],
                        'notifierqueue' => [
                            'label' => 'enrolmentnotifierqueue',
                            'url' => '/local/moderncommerce/admin/enrolment_notifier_queue.php',
                            'icon' => 'bi-hourglass-split',
                            'plugin' => 'local_modernenrolnotifier',
                            'capability' => 'local/modernenrolnotifier:viewlogs',
                        ],
                        'notifierlogs' => [
                            'label' => 'enrolmentnotifierlogs',
                            'url' => '/local/moderncommerce/admin/enrolment_notifier_logs.php',
                            'icon' => 'bi-list-check',
                            'plugin' => 'local_modernenrolnotifier',
                            'capability' => 'local/modernenrolnotifier:viewlogs',
                        ],
                        'notifierdigest' => [
                            'label' => 'enrolmentnotifierdigest',
                            'url' => '/local/moderncommerce/admin/enrolment_notifier_digest.php',
                            'icon' => 'bi-collection',
                            'plugin' => 'local_modernenrolnotifier',
                            'capability' => 'local/modernenrolnotifier:viewlogs',
                        ],
                        'notifiermanagers' => [
                            'label' => 'enrolmentnotifiermanagers',
                            'url' => '/local/moderncommerce/admin/enrolment_notifier_managers.php',
                            'icon' => 'bi-people',
                            'plugin' => 'local_modernenrolnotifier',
                            'capabilitiesall' => [
                                'local/modernenrolnotifier:viewlogs',
                                'local/moderncommerce:managenotifications',
                            ],
                        ],
                    ],
                ],
                'reminders' => [
                    'label' => 'nav_coursereminders',
                    'url' => '/local/ccp_coursereminder/admin/index.php',
                    'icon' => 'bi-clock',
                    'plugin' => 'local_ccp_coursereminder',
                    'capability' => 'local/moderncoursereminder:viewdashboard',
                    'pluginaliases' => [
                        'local_moderncoursereminder',
                    ],
                    'pluginurls' => [
                        'local_moderncoursereminder' => '/local/moderncommerce/admin/course_reminders.php',
                    ],
                    'children' => [
                        'remindersrules' => [
                            'label' => 'courseremindersrules',
                            'url' => '/local/moderncommerce/admin/course_reminders_rules.php',
                            'icon' => 'bi-diagram-3',
                            'plugin' => 'local_moderncoursereminder',
                            'capabilitiesall' => [
                                'local/moderncoursereminder:viewdashboard',
                                'local/moderncommerce:managenotifications',
                            ],
                        ],
                        'reminderstemplates' => [
                            'label' => 'coursereminderstemplates',
                            'url' => '/local/moderncommerce/admin/course_reminders_templates.php',
                            'icon' => 'bi-envelope-paper',
                            'plugin' => 'local_moderncoursereminder',
                            'capabilitiesall' => [
                                'local/moderncoursereminder:viewdashboard',
                                'local/moderncommerce:managenotifications',
                            ],
                        ],
                        'remindersschedules' => [
                            'label' => 'courseremindersschedules',
                            'url' => '/local/moderncommerce/admin/course_reminders_schedules.php',
                            'icon' => 'bi-calendar-week',
                            'plugin' => 'local_moderncoursereminder',
                            'capabilitiesall' => [
                                'local/moderncoursereminder:viewdashboard',
                                'local/moderncommerce:managenotifications',
                            ],
                        ],
                        'remindersqueue' => [
                            'label' => 'courseremindersqueue',
                            'url' => '/local/moderncommerce/admin/course_reminders_queue.php',
                            'icon' => 'bi-hourglass-split',
                            'plugin' => 'local_moderncoursereminder',
                            'capability' => 'local/moderncoursereminder:viewdashboard',
                        ],
                        'reminderslogs' => [
                            'label' => 'coursereminderslogs',
                            'url' => '/local/moderncommerce/admin/course_reminders_logs.php',
                            'icon' => 'bi-list-check',
                            'plugin' => 'local_moderncoursereminder',
                            'capability' => 'local/moderncoursereminder:viewdashboard',
                        ],
                        'remindersmanagers' => [
                            'label' => 'courseremindersmanagers',
                            'url' => '/local/moderncommerce/admin/course_reminders_managers.php',
                            'icon' => 'bi-people',
                            'plugin' => 'local_moderncoursereminder',
                            'capabilitiesall' => [
                                'local/moderncoursereminder:viewdashboard',
                                'local/moderncommerce:managenotifications',
                            ],
                        ],
                    ],
                ],
                'reviews' => [
                    'label' => 'nav_coursereviews',
                    'url' => '/local/moderncommerce/admin/course_reviews.php',
                    'icon' => 'bi-star',
                    'capability' => 'local/moderncommerce:managereviews',
                    'children' => [
                        'reviewdashboard' => [
                            'label' => 'reviewsdashboard',
                            'url' => '/local/moderncommerce/admin/course_reviews.php',
                            'icon' => 'bi-speedometer2',
                            'capability' => 'local/moderncommerce:managereviews',
                        ],
                        'allreviews' => [
                            'label' => 'allcourseswithreviews',
                            'url' => '/local/moderncommerce/admin/course_review_courses.php',
                            'icon' => 'bi-journal-text',
                            'capability' => 'local/moderncommerce:managereviews',
                        ],
                        'reviewmoderation' => [
                            'label' => 'reviewsmoderation',
                            'url' => '/local/moderncommerce/admin/course_review_moderation.php',
                            'icon' => 'bi-shield-check',
                            'capability' => 'local/moderncommerce:managereviews',
                        ],
                    ],
                ],
            ],
        ],
        'analytics' => [
            'label' => 'nav_analytics',
            'items' => [
                'reports' => [
                    'label' => 'nav_reports',
                    'url' => '/local/moderncommerce/admin/reports.php',
                    'icon' => 'bi-graph-up',
                    'capability' => 'local/moderncommerce:viewreports',
                ],
                'wishlists' => [
                    'label' => 'nav_wishlists',
                    'url' => '/local/moderncommerce/admin/wishlists.php',
                    'icon' => 'bi-heart',
                    'capability' => 'local/moderncommerce:viewreports',
                ],
                'auditlog' => [
                    'label' => 'nav_auditlog',
                    'url' => '/local/moderncommerce/admin/audit_log.php',
                    'icon' => 'bi-shield-lock',
                    'capability' => 'local/moderncommerce:viewauditlog',
                ],
            ],
        ],
        'settingsgroup' => [
            'label' => 'nav_settings',
            'items' => [
                'gateways' => [
                    'label' => 'paymentgateways',
                    'url' => '/local/moderncommerce/admin/gateways.php',
                    'icon' => 'bi-credit-card-2-front',
                    'capability' => 'local/moderncommerce:configuregateways',
                    'children' => [
                        'gatewaysettings' => [
                            'label' => 'nav_gateways',
                            'url' => '/local/moderncommerce/admin/gateways.php',
                            'icon' => 'bi-credit-card-2-front',
                            'capability' => 'local/moderncommerce:configuregateways',
                        ],
                        'webhooks' => [
                            'label' => 'nav_webhooks',
                            'url' => '/local/moderncommerce/admin/webhooks.php',
                            'icon' => 'bi-link-45deg',
                            'capability' => 'local/moderncommerce:configuregateways',
                        ],
                        'paymentevents' => [
                            'label' => 'nav_paymentevents',
                            'url' => '/local/moderncommerce/admin/payment_events.php',
                            'icon' => 'bi-receipt',
                            'capability' => 'local/moderncommerce:configuregateways',
                        ],
                        'webhookevents' => [
                            'label' => 'nav_webhookevents',
                            'url' => '/local/moderncommerce/admin/webhook_events.php',
                            'icon' => 'bi-hdd-network',
                            'capability' => 'local/moderncommerce:configuregateways',
                        ],
                    ],
                ],
                'settings' => [
                    'label' => 'nav_settings',
                    'url' => '/local/moderncommerce/admin/settings.php',
                    'icon' => 'bi-gear',
                    'capability' => 'local/moderncommerce:managesettings',
                    'children' => [
                        'global' => [
                            'label' => 'nav_global',
                            'url' => '/local/moderncommerce/admin/settings.php',
                            'icon' => 'bi-globe2',
                            'capability' => 'local/moderncommerce:managesettings',
                        ],
                        'branding' => [
                            'label' => 'nav_branding',
                            'url' => '/local/moderncommerce/admin/branding.php',
                            'icon' => 'bi-palette',
                            'capability' => 'local/moderncommerce:managesettings',
                        ],
                        'notifications' => [
                            'label' => 'nav_communicationchannels',
                            'url' => '/local/moderncommerce/admin/notifications.php',
                            'icon' => 'bi-broadcast',
                            'capability' => 'local/moderncommerce:managenotifications',
                        ],
                        'addons' => [
                            'label' => 'nav_addons',
                            'url' => '/local/moderncommerce/admin/addons.php',
                            'icon' => 'bi-puzzle',
                            'capability' => 'moodle/site:config',
                        ],
                    ],
                ],
                'documentation' => [
                    'label' => 'nav_documentation',
                    'url' => '/local/moderncommerce/admin/help/index.php',
                    'icon' => 'bi-journal-text',
                    'capabilitiesany' => [
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
                    ],
                ],
            ],
        ],
    ];
    /**
     * Constructor.
     *
     * @param string $activenav The key of the active navigation item (e.g., 'dashboard', 'orders')
     */
    public function __construct(string $activenav = '') {
        $this->activenav = $activenav;
        $this->title = '';
        $this->subtitle = '';
        $this->contenthtml = '';
        $this->actionshtml = '';
        $this->hidetopbar = false;
    }

    /**
     * Set the page title.
     *
     * @param string $title
     * @return self
     */
    public function set_title(string $title): self {
        $this->title = $title;
        return $this;
    }

    /**
     * Set the page subtitle.
     *
     * @param string $subtitle
     * @return self
     */
    public function set_subtitle(string $subtitle): self {
        $this->subtitle = $subtitle;
        return $this;
    }

    /**
     * Set the main content HTML.
     *
     * @param string $contenthtml
     * @return self
     */
    public function set_content(string $contenthtml): self {
        $this->contenthtml = $contenthtml;
        return $this;
    }

    /**
     * Set the actions HTML (buttons in topbar).
     *
     * @param string $actionshtml
     * @return self
     */
    public function set_actions(string $actionshtml): self {
        $this->actionshtml = $actionshtml;
        return $this;
    }

    /**
     * Hide the topbar.
     *
     * @param bool $hide
     * @return self
     */
    public function hide_topbar(bool $hide = true): self {
        $this->hidetopbar = $hide;
        return $this;
    }

    /**
     * Check if a plugin is installed and enabled.
     *
     * @param string $pluginname The full component name (e.g., 'local_modernenrolnotifier')
     * @return bool
     */
    private static function is_plugin_available(string $pluginname): bool {
        static $cache = [];

        if (!isset($cache[$pluginname])) {
            $pluginman = \core_plugin_manager::instance();
            $plugininfo = $pluginman->get_plugin_info($pluginname);
            $cache[$pluginname] = ($plugininfo !== null && $plugininfo->is_installed_and_upgraded());
        }

        return $cache[$pluginname];
    }

    /**
     * Get all plugin component names that can satisfy a navigation item.
     *
     * @param array $item Navigation item definition.
     * @return string[] Plugin component names.
     */
    private static function get_item_plugins(array $item): array {

        $plugins = [];
        if (!empty($item['plugin'])) {
            $plugins[] = (string) $item['plugin'];
        }
        if (!empty($item['pluginaliases']) && is_array($item['pluginaliases'])) {
            foreach ($item['pluginaliases'] as $pluginalias) {
                $plugins[] = (string) $pluginalias;
            }
        }

        return array_values(array_unique(array_filter($plugins)));
    }

    /**
     * Resolve the installed plugin component for a navigation item.
     *
     * @param array $item Navigation item definition.
     * @return string|null Empty string for ungated items, installed component name, or null when unavailable.
     */
    private static function resolve_item_plugin(array $item): ?string {

        $plugins = self::get_item_plugins($item);
        if (empty($plugins)) {
            return '';
        }

        foreach ($plugins as $pluginname) {
            if (self::is_plugin_available($pluginname)) {
                return $pluginname;
            }
        }

        return null;
    }

    /**
     * Check whether a navigation item should be shown.
     *
     * @param array $item Navigation item definition.
     * @return bool True when the item is available.
     */
    private static function is_item_available(array $item): bool {

        return self::resolve_item_plugin($item) !== null;
    }

    /**
     * Check whether a navigation item's capability allows it to be shown.
     *
     * @param array $item Navigation item definition.
     * @param \context $context Capability context.
     * @return bool True when no capability is required, or the user has it.
     */
    private static function is_item_capability_allowed(array $item, \context $context): bool {

        if (!empty($item['capability'])) {
            return has_capability((string) $item['capability'], $context);
        }

        if (!empty($item['capabilitiesany']) && is_array($item['capabilitiesany'])) {
            return has_any_capability(array_values($item['capabilitiesany']), $context);
        }

        if (!empty($item['capabilitiesall']) && is_array($item['capabilitiesall'])) {
            foreach (array_values($item['capabilitiesall']) as $capability) {
                if (!has_capability((string) $capability, $context)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check whether a navigation item should be visible to the current user.
     *
     * @param array $item Navigation item definition.
     * @param \context $context Capability context.
     * @return bool True when plugin and capability checks pass.
     */
    private static function is_item_visible(array $item, \context $context): bool {

        return self::is_item_available($item) && self::is_item_capability_allowed($item, $context);
    }

    /**
     * Resolve the URL for a navigation item using component-specific overrides.
     *
     * @param array $item Navigation item definition.
     * @param string $installedplugin Installed plugin component, or empty string for ungated items.
     * @return string Relative URL from wwwroot.
     */
    private static function resolve_item_url(array $item, string $installedplugin): string {

        if (
            $installedplugin !== ''
                && !empty($item['pluginurls'])
                && is_array($item['pluginurls'])
                && !empty($item['pluginurls'][$installedplugin])
        ) {
            return (string) $item['pluginurls'][$installedplugin];
        }

        return (string) $item['url'];
    }

    /**
     * Check whether any plugin in a candidate list is installed and upgraded.
     *
     * @param string[] $pluginnames Plugin component names.
     * @return bool True when at least one plugin is available.
     */
    private static function any_plugin_available(array $pluginnames): bool {

        foreach ($pluginnames as $pluginname) {
            if (self::is_plugin_available($pluginname)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a stable DOM id for a nested sidebar list.
     *
     * @param string $sectionkey Navigation section key.
     * @param string $itemkey Navigation item key.
     * @return string Stable submenu id.
     */
    private static function get_submenu_id(string $sectionkey, string $itemkey): string {

        $id = strtolower($sectionkey . '-' . $itemkey);
        $id = preg_replace('/[^a-z0-9_-]+/', '-', $id);

        return 'local-moderncommerce-admin-subnav-' . trim((string) $id, '-');
    }

    /**
     * Resolve a Modern Commerce language string.
     *
     * @param string $identifier Language string identifier.
     * @return string Localized label.
     */
    private static function get_local_string(string $identifier): string {

        return get_string($identifier, 'local_moderncommerce');
    }

    /**
     * Build a single navigation item, including any available children.
     *
     * @param string $sectionkey Navigation section key.
     * @param string $itemkey Navigation item key.
     * @param array $item Navigation item definition.
     * @param \context $context Capability context.
     * @return array|null Template-ready item data, or null when hidden.
     */
    private function build_navigation_item(
        string $sectionkey,
        string $itemkey,
        array $item,
        \context $context
    ): ?array {
        global $CFG;

        $installedplugin = self::resolve_item_plugin($item);
        $isvisible = $installedplugin !== null && self::is_item_capability_allowed($item, $context);
        $children = [];
        if (!empty($item['children']) && is_array($item['children'])) {
            foreach ($item['children'] as $childkey => $childitem) {
                $child = $this->build_navigation_item($sectionkey, $childkey, $childitem, $context);
                if ($child !== null) {
                    $children[] = $child;
                }
            }
        }

        if (!$isvisible && empty($children)) {
            return null;
        }

        $descendantactive = false;
        foreach ($children as $child) {
            if (!empty($child['active']) || !empty($child['descendantactive'])) {
                $descendantactive = true;
                break;
            }
        }

        $label = self::get_local_string($item['label']);
        $selfactive = ($itemkey === $this->activenav);
        $active = $selfactive || $descendantactive;
        $haschildren = !empty($children);
        if (!$isvisible && $haschildren) {
            $url = (string) $children[0]['url'];
        } else {
            $url = !empty($item['url']) ? $CFG->wwwroot . self::resolve_item_url($item, $installedplugin ?? '') : '#';
        }

        return [
            'key' => $itemkey,
            'label' => $label,
            'url' => $url,
            'icon' => $item['icon'] ?? 'bi-circle',
            'active' => $active,
            'current' => $selfactive,
            'ancestoractive' => !$selfactive && $descendantactive,
            'descendantactive' => $descendantactive,
            'expanded' => $active,
            'newtab' => !empty($item['newtab']),
            'haschildren' => $haschildren,
            'children' => $children,
            'submenuid' => $haschildren ? self::get_submenu_id($sectionkey, $itemkey) : '',
            'togglelabel' => $haschildren ? self::get_local_string('togglesidebar') . ': ' . $label : '',
        ];
    }

    /**
     * Build the navigation data for the template.
     *
     * @return array Navigation sections with items
     */
    public function get_navigation(): array {

        $context = \context_system::instance();
        $sections = [];
        foreach (self::NAV_ITEMS as $sectionkey => $section) {
            $items = [];
            foreach ($section['items'] as $itemkey => $item) {
                $navitem = $this->build_navigation_item($sectionkey, $itemkey, $item, $context);
                if ($navitem !== null) {
                    $items[] = $navitem;
                }
            }

            // Only add section if it has items.
            if (!empty($items)) {
                $sections[] = [
                    'key' => $sectionkey,
                    'label' => self::get_local_string($section['label']),
                    'items' => $items,
                ];
            }
        }

        return $sections;
    }

    /**
     * Build the legacy nav.* active flags for backward compatibility.
     *
     * @return array
     */
    public function get_nav_active_flags(): array {
        $context = \context_system::instance();
        $flags = [];
        foreach (self::NAV_ITEMS as $section) {
            foreach ($section['items'] as $itemkey => $item) {
                if (self::is_item_visible($item, $context)) {
                    $flags[$itemkey . 'active'] = ($itemkey === $this->activenav);
                }

                if (empty($item['children']) || !is_array($item['children'])) {
                    continue; // Skip this item.
                }

                foreach ($item['children'] as $childkey => $childitem) {
                    if (self::is_item_visible($childitem, $context)) {
                        $flags[$childkey . 'active'] = ($childkey === $this->activenav);
                    }
                }
            }
        }
        return $flags;
    }

    /**
     * Get the template context for rendering.
     *
     * @return array
     */
    public function get_template_context(): array {

        global $CFG;
        $navigation = $this->get_navigation();
        return [
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'actionshtml' => $this->actionshtml,
            'contenthtml' => $this->contenthtml,
            'hidetopbar' => $this->hidetopbar,
            'nav' => $this->get_nav_active_flags(),
            'plugins' => $this->get_plugin_availability_flags(),
            'navigation' => $navigation,
            'hasnavigation' => !empty($navigation),
            'config' => [
                'wwwroot' => $CFG->wwwroot,
            ],
            'version' => self::get_release_version(),
        ];
    }

    /**
     * Get the installed plugin release string for the shell footer.
     *
     * @return string
     */
    private static function get_release_version(): string {

        $pluginman = \core_plugin_manager::instance();
        $plugininfo = $pluginman->get_plugin_info('local_moderncommerce');
        return $plugininfo && !empty($plugininfo->release) ? (string) $plugininfo->release : '';
    }
    /**
     * Get plugin availability flags for the template.
     *
     * Returns a map of plugin short names to their availability status.
     * This allows the template to conditionally show/hide menu items
     * for optional plugins.
     *
     * @return array
     */
    private function get_plugin_availability_flags(): array {
        return [
            'pagedesigner' => self::is_plugin_available('local_ccp_pagedesigner'),
            'emailtemplates' => true,
            'subscription' => true,
            'contact' => true,
            'enrolmentnotifier' => self::any_plugin_available([
                'local_ccp_enrolmentnotifier',
                'local_modernenrolnotifier',
            ]),
            'coursereminder' => self::any_plugin_available([
                'local_ccp_coursereminder',
                'local_moderncoursereminder',
            ]),
        ];
    }

    /**
     * Render the shell template.
     *
     * @param object $output Moodle renderer or bootstrap renderer proxy.
     * @return string The rendered HTML
     */
    public function render(object $output): string {

        self::require_shell_assets();
        return $output->render_from_template('local_moderncommerce/shell', $this->get_template_context());
    }

    /**
     * Retained for render flow compatibility.
     *
     * Shared Modern Commerce CSS is registered centrally by
     * \local_moderncommerce\hook\callbacks::inject_custom_css().
     *
     * @return void
     */
    private static function require_shell_assets(): void {
    }

    /**
     * Static factory method for quick usage.
     *
     * @param string $activenav The active navigation key
     * @return self
     */
    public static function create(string $activenav): self {

        return new self($activenav);
    }

    /**
     * Build a standard header action group.
     *
     * Actions support:
     * - type: link|button
     * - label: Visible button label
     * - icon: Bootstrap icon class without the leading "bi "
     * - url: Link target for link actions
     * - primary: Whether to use the primary button treatment
     * - attributes: Extra HTML attributes
     *
     * @param array $actions Action definitions.
     * @return string Rendered action group HTML.
     */
    public static function action_group(array $actions): string {

        $html = '';
        foreach ($actions as $action) {
            if (empty($action['label'])) {
                continue;
            }

            $type = $action['type'] ?? 'link';
            $label = (string) $action['label'];
            $icon = (string) ($action['icon'] ?? '');
            $primary = !empty($action['primary']);
            $attributes = $action['attributes'] ?? [];
            if ($type === 'button') {
                $html .= self::action_button($label, $icon, $primary, $attributes);
                continue;
            }

            if (!empty($action['url'])) {
                $html .= self::action_link($action['url'], $label, $icon, $primary, $attributes);
            }
        }

        return $html === '' ? '' : \html_writer::span($html, 'd-inline-flex gap-2');
    }

    /**
     * Build a standard header link action.
     *
     * @param \moodle_url|string $url Link target.
     * @param string $label Visible label.
     * @param string $icon Bootstrap icon class without the leading "bi ".
     * @param bool $primary Whether to use primary styling.
     * @param array $attributes Extra attributes.
     * @return string
     */
    public static function action_link(
        $url,
        string $label,
        string $icon = '',
        bool $primary = false,
        array $attributes = []
    ): string {

        $attributes = self::action_attributes($attributes, $primary);
        $attributes['href'] = $url instanceof \moodle_url ? $url->out(false) : (string) $url;
        return \html_writer::tag('a', self::action_content($label, $icon), $attributes);
    }

    /**
     * Build a standard header button action.
     *
     * @param string $label Visible label.
     * @param string $icon Bootstrap icon class without the leading "bi ".
     * @param bool $primary Whether to use primary styling.
     * @param array $attributes Extra attributes.
     * @return string
     */
    public static function action_button(string $label, string $icon = '', bool $primary = false, array $attributes = []): string {

        $attributes = self::action_attributes($attributes, $primary);
        $attributes['type'] = $attributes['type'] ?? 'button';
        return \html_writer::tag('button', self::action_content($label, $icon), $attributes);
    }

    /**
     * Build common action attributes.
     *
     * @param array $attributes Existing attributes.
     * @param bool $primary Whether to use primary styling.
     * @return array
     */
    private static function action_attributes(array $attributes, bool $primary): array {

        $baseclass = $primary ? 'mc-button btn-mc-primary' : 'mc-button mc-btn-soft';
        if (!empty($attributes['class'])) {
            $attributes['class'] = $baseclass . ' ' . $attributes['class'];
        } else {
            $attributes['class'] = $baseclass;
        }
        $attributes['data-mc-button'] = $attributes['data-mc-button'] ?? ($primary ? 'primary' : 'soft');

        return $attributes;
    }

    /**
     * Build the icon and label for a header action.
     *
     * @param string $label Visible label.
     * @param string $icon Bootstrap icon class without the leading "bi ".
     * @return string
     */
    private static function action_content(string $label, string $icon): string {

        $iconhtml = '';
        if ($icon !== '') {
            $iconhtml = \html_writer::tag(
                'i',
                '',
                ['class' => 'bi ' . $icon . ' mc-icon me-2', 'aria-hidden' => 'true']
            );
        }

        return $iconhtml . s($label);
    }

    /**
     * Render a page in the shared admin shell with a compact call site.
     *
     * @param object $output Moodle renderer or bootstrap renderer proxy.
     * @param string $activenav The active navigation key.
     * @param string $title Page title.
     * @param string $contenthtml Pre-rendered content HTML.
     * @param string $subtitle Optional page subtitle.
     * @param string $actionshtml Optional pre-rendered actions HTML.
     * @param bool $hidetopbar Whether to hide the topbar.
     * @return string
     */
    public static function render_page(
        object $output,
        string $activenav,
        string $title,
        string $contenthtml,
        string $subtitle = '',
        string $actionshtml = '',
        bool $hidetopbar = false
    ): string {

        return self::create($activenav)
            ->set_title($title)
            ->set_subtitle($subtitle)
            ->set_actions($actionshtml)
            ->set_content($contenthtml)
            ->hide_topbar($hidetopbar)
            ->render($output);
    }
}
