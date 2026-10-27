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
 * Plugin administration pages
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create the root-level category only if it doesn't already exist.
    if (!$ADMIN->locate('moderncommerce')) {
        $ADMIN->add('root', new admin_category('moderncommerce', get_string('pluginname', 'local_moderncommerce')), 'badges');
    }

    // Add an h4 heading page labeled "Modern Commerce" within the tab.
    $moderncommerceheading = new admin_settingpage(
        'local_moderncommerce_heading',
        get_string('pluginname', 'local_moderncommerce')
    );
    // Optional descriptive text under the heading.
    $moderncommerceheading->add(new admin_setting_heading(
        'local_moderncommerce/heading',
        get_string('pluginname', 'local_moderncommerce'),
        ''
    ));
    $ADMIN->add('moderncommerce', $moderncommerceheading);

    // Settings page.
    $settings = new admin_settingpage('local_moderncommerce_settings', get_string('settings', 'local_moderncommerce'));

    if ($ADMIN->fulltree) {
        // Currency Settings - Primary system configuration.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/currencyheading',
            get_string('currencysettings', 'local_moderncommerce'),
            get_string('currencysettings_desc', 'local_moderncommerce')
        ));

        $currencyoptions = [
            'NGN' => 'Nigerian Naira (₦)',
            'USD' => 'US Dollar ($)',
            'EUR' => 'Euro (€)',
            'GBP' => 'British Pound (£)',
            'ZAR' => 'South African Rand (R)',
            'GHS' => 'Ghanaian Cedi (₵)',
            'KES' => 'Kenyan Shilling (KSh)',
            'UGX' => 'Ugandan Shilling (USh)',
            'TZS' => 'Tanzanian Shilling (TSh)',
            'XOF' => 'West African CFA Franc (CFA)',
            'XAF' => 'Central African CFA Franc (FCFA)',
            'EGP' => 'Egyptian Pound (£)',
            'MAD' => 'Moroccan Dirham (DH)',
            'CAD' => 'Canadian Dollar ($)',
            'AUD' => 'Australian Dollar ($)',
            'INR' => 'Indian Rupee (₹)',
            'CNY' => 'Chinese Yuan (¥)',
            'JPY' => 'Japanese Yen (¥)',
        ];

        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/primary_currency',
            get_string('primarycurrency', 'local_moderncommerce'),
            get_string('primarycurrency_desc', 'local_moderncommerce'),
            'USD',
            $currencyoptions
        ));

        $currencypositionoptions = [
            'before' => get_string('currencyposition_before', 'local_moderncommerce'),
            'after' => get_string('currencyposition_after', 'local_moderncommerce'),
        ];

        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/currency_position',
            get_string('currencyposition', 'local_moderncommerce'),
            get_string('currencyposition_desc', 'local_moderncommerce'),
            'before',
            $currencypositionoptions
        ));

        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/decimal_places',
            get_string('decimalplaces', 'local_moderncommerce'),
            get_string('decimalplaces_desc', 'local_moderncommerce'),
            '2',
            PARAM_INT
        ));

        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/thousand_separator',
            get_string('thousandseparator', 'local_moderncommerce'),
            get_string('thousandseparator_desc', 'local_moderncommerce'),
            ',',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/decimal_separator',
            get_string('decimalseparator', 'local_moderncommerce'),
            get_string('decimalseparator_desc', 'local_moderncommerce'),
            '.',
            PARAM_TEXT
        ));

        // Review settings.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/reviewsettingsheading',
            get_string('reviewsettings', 'local_moderncommerce'),
            get_string('reviewsettings_desc', 'local_moderncommerce')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/reviews_enabled',
            get_string('reviewsenabled', 'local_moderncommerce'),
            get_string('reviewsenabled_desc', 'local_moderncommerce'),
            1
        ));

        // Product form fields. SKU and slug are generated internally; hidden from the product
        // form by default. Enable to let admins view and edit them.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/productformsettingsheading',
            get_string('productformsettings', 'local_moderncommerce'),
            get_string('productformsettings_desc', 'local_moderncommerce')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/product_show_sku',
            get_string('showskufield', 'local_moderncommerce'),
            get_string('showskufield_desc', 'local_moderncommerce'),
            0
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/product_show_slug',
            get_string('showslugfield', 'local_moderncommerce'),
            get_string('showslugfield_desc', 'local_moderncommerce'),
            0
        ));

        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/course_detail_sidebar_position',
            get_string('coursedetailsidebarposition', 'local_moderncommerce'),
            get_string('coursedetailsidebarposition_desc', 'local_moderncommerce'),
            'right',
            [
                'right' => get_string('sidebarposition_right', 'local_moderncommerce'),
                'left' => get_string('sidebarposition_left', 'local_moderncommerce'),
            ]
        ));

        // Gateway credentials are managed in the registry-backed gateway admin page.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/gatewayregistryheading',
            get_string('paymentgateways', 'local_moderncommerce'),
            html_writer::link(
                new moodle_url('/local/moderncommerce/admin/gateways.php'),
                get_string('managepaymentgateways', 'local_moderncommerce')
            )
        ));

        // Webhook Security Settings.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/webhooksecurityheading',
            get_string('webhooksecurity', 'local_moderncommerce'),
            get_string('webhooksecurity_desc', 'local_moderncommerce')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/enable_webhook_ip_whitelist',
            get_string('enablewebhookipwhitelist', 'local_moderncommerce'),
            get_string('enablewebhookipwhitelist_desc', 'local_moderncommerce'),
            1
        ));

        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/payment_max_retries',
            get_string('paymentmaxretries', 'local_moderncommerce'),
            get_string('paymentmaxretries_desc', 'local_moderncommerce'),
            '3',
            PARAM_INT
        ));

        // Tax Settings.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/taxheading',
            get_string('taxsettings', 'local_moderncommerce'),
            get_string('taxsettings_desc', 'local_moderncommerce')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/enable_tax',
            get_string('enabletax', 'local_moderncommerce'),
            get_string('enabletax_desc', 'local_moderncommerce'),
            0
        ));

        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/default_tax_rate',
            get_string('defaulttaxrate', 'local_moderncommerce'),
            get_string('defaulttaxrate_desc', 'local_moderncommerce'),
            '0',
            PARAM_FLOAT
        ));

        // Notification display settings.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/notificationheading',
            get_string('notificationsettings', 'local_moderncommerce'),
            get_string('notificationsettings_desc', 'local_moderncommerce')
        ));

        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/notification_position',
            get_string('notificationposition', 'local_moderncommerce'),
            get_string('notificationposition_desc', 'local_moderncommerce'),
            'top-right',
            [
                'top-left' => get_string('notificationposition_topleft', 'local_moderncommerce'),
                'top-center' => get_string('notificationposition_topcenter', 'local_moderncommerce'),
                'top-right' => get_string('notificationposition_topright', 'local_moderncommerce'),
                'bottom-left' => get_string('notificationposition_bottomleft', 'local_moderncommerce'),
                'bottom-center' => get_string('notificationposition_bottomcenter', 'local_moderncommerce'),
                'bottom-right' => get_string('notificationposition_bottomright', 'local_moderncommerce'),
            ]
        ));

        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/notification_autodismiss',
            get_string('notificationautodismiss', 'local_moderncommerce'),
            get_string('notificationautodismiss_desc', 'local_moderncommerce'),
            '4000',
            PARAM_INT
        ));

        // Notification delivery subsystem (queue + channels). Also editable from the
        // Notifications admin page; these settings persist the same notify_* config keys.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/notifydeliveryheading',
            get_string('notifydeliveryheading', 'local_moderncommerce'),
            get_string('notifydeliveryheading_desc', 'local_moderncommerce')
        ));

        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/notify_batchsize',
            get_string('notify_batchsize', 'local_moderncommerce'),
            get_string('notify_batchsize_desc', 'local_moderncommerce'),
            100,
            PARAM_INT
        ));

        // Slack ops channel.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/notify_slackheading',
            get_string('notify_slackheading', 'local_moderncommerce'),
            get_string('notify_slackheading_desc', 'local_moderncommerce')
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/notify_slack_enabled',
            get_string('notify_slackenabled', 'local_moderncommerce'),
            get_string('notify_slackenabled_desc', 'local_moderncommerce'),
            0
        ));
        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/notify_slack_url',
            get_string('notify_slackurl', 'local_moderncommerce'),
            get_string('notify_slackurl_desc', 'local_moderncommerce'),
            '',
            PARAM_URL
        ));
        $settings->add(new admin_setting_configpasswordunmask(
            'local_moderncommerce/notify_slack_secret',
            get_string('notify_slacksecret', 'local_moderncommerce'),
            get_string('notify_slacksecret_desc', 'local_moderncommerce'),
            ''
        ));

        // Teams ops channel.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/notify_teamsheading',
            get_string('notify_teamsheading', 'local_moderncommerce'),
            get_string('notify_teamsheading_desc', 'local_moderncommerce')
        ));
        $settings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/notify_teams_enabled',
            get_string('notify_teamsenabled', 'local_moderncommerce'),
            get_string('notify_teamsenabled_desc', 'local_moderncommerce'),
            0
        ));
        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/notify_teams_url',
            get_string('notify_teamsurl', 'local_moderncommerce'),
            get_string('notify_teamsurl_desc', 'local_moderncommerce'),
            '',
            PARAM_URL
        ));
        $settings->add(new admin_setting_configpasswordunmask(
            'local_moderncommerce/notify_teams_secret',
            get_string('notify_teamssecret', 'local_moderncommerce'),
            get_string('notify_teamssecret_desc', 'local_moderncommerce'),
            ''
        ));

        // Contact information (checkout) settings.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/checkoutfieldsheading',
            get_string('checkoutfields', 'local_moderncommerce'),
            get_string('checkoutfields_desc', 'local_moderncommerce')
        ));
        // Master toggle: collect contact information at checkout. Off by default —
        // none of the supported gateways require a billing address, and tax is a flat
        // site rate, so checkout only needs the account email/name unless enabled here.
        $settings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/contact_info_enabled',
            get_string('contactinfoenabled', 'local_moderncommerce'),
            get_string('contactinfoenabled_desc', 'local_moderncommerce'),
            0
        ));
        // Phone Number.
        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/phone_field',
            get_string('phonefield', 'local_moderncommerce'),
            get_string('phonefield_desc', 'local_moderncommerce'),
            'optional',
            [
                'hidden' => get_string('hidden', 'local_moderncommerce'),
                'optional' => get_string('optional', 'local_moderncommerce'),
                'required' => get_string('required', 'local_moderncommerce'),
            ]
        ));

        // Address.
        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/address_field',
            get_string('addressfield', 'local_moderncommerce'),
            get_string('addressfield_desc', 'local_moderncommerce'),
            'hidden',
            [
                'hidden' => get_string('hidden', 'local_moderncommerce'),
                'optional' => get_string('optional', 'local_moderncommerce'),
                'required' => get_string('required', 'local_moderncommerce'),
            ]
        ));

        // City.
        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/city_field',
            get_string('cityfield', 'local_moderncommerce'),
            get_string('cityfield_desc', 'local_moderncommerce'),
            'hidden',
            [
                'hidden' => get_string('hidden', 'local_moderncommerce'),
                'optional' => get_string('optional', 'local_moderncommerce'),
                'required' => get_string('required', 'local_moderncommerce'),
            ]
        ));

        // State/Province.
        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/state_field',
            get_string('statefield', 'local_moderncommerce'),
            get_string('statefield_desc', 'local_moderncommerce'),
            'hidden',
            [
                'hidden' => get_string('hidden', 'local_moderncommerce'),
                'optional' => get_string('optional', 'local_moderncommerce'),
                'required' => get_string('required', 'local_moderncommerce'),
            ]
        ));

        // Country.
        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/country_field',
            get_string('countryfield', 'local_moderncommerce'),
            get_string('countryfield_desc', 'local_moderncommerce'),
            'hidden',
            [
                'hidden' => get_string('hidden', 'local_moderncommerce'),
                'optional' => get_string('optional', 'local_moderncommerce'),
                'required' => get_string('required', 'local_moderncommerce'),
            ]
        ));

        // ZIP/Postal Code.
        $settings->add(new admin_setting_configselect(
            'local_moderncommerce/zipcode_field',
            get_string('zipcodefield', 'local_moderncommerce'),
            get_string('zipcodefield_desc', 'local_moderncommerce'),
            'hidden',
            [
                'hidden' => get_string('hidden', 'local_moderncommerce'),
                'optional' => get_string('optional', 'local_moderncommerce'),
                'required' => get_string('required', 'local_moderncommerce'),
            ]
        ));

        // Branding - applies to both the storefront and the admin console.
        // Empty values fall back to the design-system defaults. Every brandable
        // colour token is defined once in \local_moderncommerce\branding and
        // rendered here, so the controls and the injected CSS never drift.
        $settings->add(new admin_setting_heading(
            'local_moderncommerce/brandingheading',
            get_string('brandingheading', 'local_moderncommerce'),
            get_string('brandingheading_desc', 'local_moderncommerce')
        ));

        foreach (\local_moderncommerce\branding::get_groups() as $groupkey => $tokens) {
            $settings->add(new admin_setting_heading(
                'local_moderncommerce/brandgroup_' . $groupkey,
                get_string('brandgroup_' . $groupkey, 'local_moderncommerce'),
                ''
            ));

            foreach ($tokens as $setting => $token) {
                if ($token['type'] === 'text') {
                    // Free text for rgba()/alpha values a colour picker cannot express.
                    $settings->add(new admin_setting_configtext(
                        'local_moderncommerce/' . $setting,
                        get_string($setting, 'local_moderncommerce'),
                        '',
                        '',
                        PARAM_TEXT
                    ));
                } else {
                    $settings->add(new admin_setting_configcolourpicker(
                        'local_moderncommerce/' . $setting,
                        get_string($setting, 'local_moderncommerce'),
                        '',
                        ''
                    ));
                }
            }
        }

        // Corner radius (a length) and the advanced raw-CSS escape hatch.
        $settings->add(new admin_setting_configtext(
            'local_moderncommerce/brand_radius',
            get_string('brand_radius', 'local_moderncommerce'),
            '',
            '',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtextarea(
            'local_moderncommerce/customcss',
            get_string('customcss', 'local_moderncommerce'),
            get_string('customcss_desc', 'local_moderncommerce'),
            '',
            PARAM_RAW
        ));
    }

    $ADMIN->add('moderncommerce', $settings);

    // Catalog display settings are configured per-widget from the storefront side panel
    // (/local/moderncommerce/index.php in edit mode), not from a store-wide admin page.

    // Navigation Settings - control primary navigation items.
    $navsettings = new admin_settingpage(
        'local_moderncommerce_navigation',
        get_string('navigationsettings', 'local_moderncommerce')
    );

    $navsettings->add(new admin_setting_heading(
        'local_moderncommerce/navigationheading',
        get_string('navigationsettings', 'local_moderncommerce'),
        get_string('navigationsettings_desc', 'local_moderncommerce')
    ));
    // Position of the cart icon inside Moodle's top-right user navigation.
    $navsettings->add(new admin_setting_configselect(
        'local_moderncommerce/navbar_cart_position',
        get_string('navbarcartposition', 'local_moderncommerce'),
        get_string('navbarcartposition_desc', 'local_moderncommerce'),
        'first',
        [
            'first' => get_string('navbarcartposition_first', 'local_moderncommerce'),
            'last' => get_string('navbarcartposition_last', 'local_moderncommerce'),
        ]
    ));
    // Label for the admin (manager) primary navigation item.
    $navsettings->add(new admin_setting_configtext(
        'local_moderncommerce/adminnavlabel',
        get_string('adminnavlabel', 'local_moderncommerce'),
        get_string('adminnavlabel_desc', 'local_moderncommerce'),
        get_string('adminnavlabel_default', 'local_moderncommerce'),
        PARAM_TEXT
    ));

    // Label for the learner dashboard primary navigation item.
    $navsettings->add(new admin_setting_configtext(
        'local_moderncommerce/learnernavlabel',
        get_string('learnernavlabel', 'local_moderncommerce'),
        get_string('learnernavlabel_desc', 'local_moderncommerce'),
        get_string('learnernavlabel_default', 'local_moderncommerce'),
        PARAM_TEXT
    ));

    // Hide selected core primary navigation nodes for all users.
    $navsettings->add(new admin_setting_configmulticheckbox(
        'local_moderncommerce/hideprimarynavitems',
        get_string('hideprimarynavitems', 'local_moderncommerce'),
        get_string('hideprimarynavitems_desc', 'local_moderncommerce'),
        [],
        [
            'home' => get_string('home'),
            'myhome' => get_string('myhome'),
            'mycourses' => get_string('mycourses'),
            'siteadminnode' => get_string('administrationsite'),
        ]
    ));

    $ADMIN->add('moderncommerce', $navsettings);

    // Dashboard.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_dashboard',
        get_string('dashboard', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/index.php'),
        'local/moderncommerce:viewreports'
    ));

    // Add-on catalog.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_addons',
        get_string('addons', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/addons.php'),
        'moodle/site:config'
    ));

    // Commerce Settings.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_admin_settings',
        get_string('commercesettings', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/settings.php'),
        'moodle/site:config'
    ));

    // Manage orders.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_orders',
        get_string('manageorders', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/orders.php'),
        'local/moderncommerce:viewallorders'
    ));

    // Manage courses (was pricing).
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_pricing',
        get_string('managecourses', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/pricing.php'),
        'local/moderncommerce:managecourses'
    ));

    // Manage product categories.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_categories',
        get_string('managecategories', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/categories.php'),
        'local/moderncommerce:managecourses'
    ));

    // Manage coupons.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_coupons',
        get_string('managecoupons', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/coupons.php'),
        'local/moderncommerce:managecoupons'
    ));

    // Manage payment gateways.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_gateways',
        get_string('paymentgateways', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/gateways.php'),
        'local/moderncommerce:configuregateways'
    ));

    // Manage bundles.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_bundles',
        get_string('managebundles', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/bundles.php'),
        'local/moderncommerce:managecourses'
    ));

    // Manage course reviews.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_reviews',
        get_string('coursereviews', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/course_reviews.php'),
        'local/moderncommerce:managereviews'
    ));

    // Manage enrollment keys.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_keys',
        get_string('managekeys', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/keys.php'),
        'local/moderncommerce:generatekeys'
    ));

    // Manage bundle enrollment keys.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_bundlekeys',
        get_string('managebundlekeys', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/bundle_keys.php'),
        'local/moderncommerce:generatekeys'
    ));

    // Manage invoices.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_invoices',
        get_string('invoices', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/invoices.php'),
        'local/moderncommerce:manageorders'
    ));

    // Reports.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_reports',
        get_string('reports', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/reports.php'),
        'local/moderncommerce:viewreports'
    ));

    // Webhook configuration.
    $ADMIN->add('moderncommerce', new admin_externalpage(
        'local_moderncommerce_webhooks',
        get_string('webhooks', 'local_moderncommerce'),
        new moodle_url('/local/moderncommerce/admin/webhooks.php'),
        'local/moderncommerce:manageorders'
    ));

    // Subscription subsystem settings absorbed from local_modernsubscription.
    $subscriptionsettings = new admin_settingpage(
        'local_moderncommerce_subscription',
        get_string('subscriptions', 'local_moderncommerce')
    );

    if ($ADMIN->fulltree) {
        // General.
        $subscriptionsettings->add(new admin_setting_heading(
            'local_moderncommerce/sub_generalheading',
            get_string('settings:general', 'local_moderncommerce'),
            ''
        ));
        $subscriptionsettings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/send_activation_summary_email',
            get_string('emailsummary_enable', 'local_moderncommerce'),
            get_string('emailsummary_enable_desc', 'local_moderncommerce'),
            1
        ));
        $subscriptionsettings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/send_renewal_digest_email',
            get_string('renewaldigest_enable', 'local_moderncommerce'),
            get_string('renewaldigest_enable_desc', 'local_moderncommerce'),
            1
        ));

        // Reminders.
        $subscriptionsettings->add(new admin_setting_heading(
            'local_moderncommerce/sub_reminderheading',
            get_string('settings:reminders', 'local_moderncommerce'),
            ''
        ));
        $subscriptionsettings->add(new admin_setting_configtext(
            'local_moderncommerce/reminder_days',
            get_string('reminderdays', 'local_moderncommerce'),
            get_string('reminderdays_desc', 'local_moderncommerce'),
            '7,3,1',
            PARAM_TEXT
        ));

        // Data cleanup.
        $subscriptionsettings->add(new admin_setting_heading(
            'local_moderncommerce/sub_cleanupheading',
            get_string('settings:cleanup', 'local_moderncommerce'),
            ''
        ));
        $subscriptionsettings->add(new admin_setting_configtext(
            'local_moderncommerce/history_retention_days',
            get_string('historyretentiondays', 'local_moderncommerce'),
            get_string('historyretentiondays_desc', 'local_moderncommerce'),
            '730',
            PARAM_INT
        ));
        $subscriptionsettings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/cleanup_old_subscriptions',
            get_string('cleanupoldsubscriptions', 'local_moderncommerce'),
            get_string('cleanupoldsubscriptions_desc', 'local_moderncommerce'),
            0
        ));
        $subscriptionsettings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/keep_deleted_user_history',
            get_string('keepdeletedhistory', 'local_moderncommerce'),
            get_string('keepdeletedhistory_desc', 'local_moderncommerce'),
            1
        ));

        // Plan changes.
        $subscriptionsettings->add(new admin_setting_heading(
            'local_moderncommerce/sub_upgradeheading',
            get_string('settings:upgrade', 'local_moderncommerce'),
            ''
        ));
        $subscriptionsettings->add(new admin_setting_configtext(
            'local_moderncommerce/upgrade_cooldown_days',
            get_string('planchangecooldown', 'local_moderncommerce'),
            get_string('planchangecooldown_desc', 'local_moderncommerce'),
            '30',
            PARAM_INT
        ));
        $subscriptionsettings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/allow_lateral_moves',
            get_string('allowlateralmoves', 'local_moderncommerce'),
            get_string('allowlateralmoves_desc', 'local_moderncommerce'),
            1
        ));
        $subscriptionsettings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/store_downgrade_credit',
            get_string('storedowngradecredit', 'local_moderncommerce'),
            get_string('storedowngradecredit_desc', 'local_moderncommerce'),
            1
        ));

        // Cancellation.
        $subscriptionsettings->add(new admin_setting_heading(
            'local_moderncommerce/sub_cancelheading',
            get_string('settings:cancel', 'local_moderncommerce'),
            ''
        ));
        $subscriptionsettings->add(new admin_setting_configselect(
            'local_moderncommerce/cancel_mode',
            get_string('cancelmode', 'local_moderncommerce'),
            get_string('cancelmode_desc', 'local_moderncommerce'),
            'end_of_period',
            [
                'immediate' => get_string('cancelmode_immediate', 'local_moderncommerce'),
                'end_of_period' => get_string('cancelmode_endofperiod', 'local_moderncommerce'),
            ]
        ));
        $subscriptionsettings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/allow_user_cancel',
            get_string('allowusercancel', 'local_moderncommerce'),
            get_string('allowusercancel_desc', 'local_moderncommerce'),
            1
        ));

        // Trials.
        $subscriptionsettings->add(new admin_setting_heading(
            'local_moderncommerce/sub_trialheading',
            get_string('settings:trial', 'local_moderncommerce'),
            ''
        ));
        $subscriptionsettings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/enable_trials',
            get_string('enabletrials', 'local_moderncommerce'),
            get_string('enabletrials_desc', 'local_moderncommerce'),
            1
        ));
        $subscriptionsettings->add(new admin_setting_configcheckbox(
            'local_moderncommerce/trial_auto_convert',
            get_string('trialautoconvert', 'local_moderncommerce'),
            get_string('trialautoconvert_desc', 'local_moderncommerce'),
            0
        ));

        // Recurring payments (webhook/retry settings live in the main Modern Commerce settings).
        $subscriptionsettings->add(new admin_setting_heading(
            'local_moderncommerce/sub_recurringheading',
            get_string('settings:recurring', 'local_moderncommerce'),
            get_string('settings:recurring_desc', 'local_moderncommerce')
        ));
    }

    $ADMIN->add('moderncommerce', $subscriptionsettings);
}
