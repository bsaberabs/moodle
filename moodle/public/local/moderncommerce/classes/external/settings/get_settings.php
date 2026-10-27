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
 * External API returning Modern Commerce admin settings.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\settings;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\commerce_settings_service;
use local_moderncommerce\services\certificate_template_service;

/**
 * Get the Modern Commerce commerce settings and option lists.
 */
class get_settings extends external_api {
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
        self::validate_parameters(self::execute_parameters(), []);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managesettings', $context);

        $settings = commerce_settings_service::get_admin_settings();
        $currency = $settings->currency;

        $values = [
            'primary_currency' => (string) $currency->currency,
            'currency_position' => (string) $currency->position,
            'decimal_places' => (int) $currency->decimals,
            'thousand_separator' => (string) $currency->thousand,
            'decimal_separator' => (string) $currency->decimal,
            'business_name' => (string) $settings->businessname,
            'support_email' => (string) $settings->supportemail,
            'support_url' => (string) $settings->supporturl,
            'invoice_prefix' => (string) $settings->invoiceprefix,
            'receipt_prefix' => (string) $settings->receiptprefix,
            'tax_mode' => (string) $settings->taxmode,
            'default_tax_rate' => (float) $settings->defaulttaxrate,
            'contact_info_enabled' => (int) $settings->contact_info_enabled,
            'phone_field' => (string) $settings->phone_field,
            'address_field' => (string) $settings->address_field,
            'city_field' => (string) $settings->city_field,
            'state_field' => (string) $settings->state_field,
            'country_field' => (string) $settings->country_field,
            'zipcode_field' => (string) $settings->zipcode_field,
            'adminnavlabel' => (string) $settings->adminnavlabel,
            'learnernavlabel' => (string) $settings->learnernavlabel,
            'hideprimarynavitems' => array_values($settings->hideprimarynavitems),
            'navbar_cart_position' => (string) $settings->navbar_cart_position,
            'notification_position' => (string) $settings->notification_position,
            'notification_autodismiss' => (int) $settings->notification_autodismiss,
            'reviews_enabled' => (int) $settings->reviews_enabled,
            'product_show_sku' => (int) $settings->product_show_sku,
            'product_show_slug' => (int) $settings->product_show_slug,
            'course_detail_sidebar_position' => (string) $settings->course_detail_sidebar_position,
            'enable_webhook_ip_whitelist' => (int) $settings->enable_webhook_ip_whitelist,
            'payment_max_retries' => (int) $settings->payment_max_retries,
            'default_program_certificate_template_id' =>
                (int) $settings->default_program_certificate_template_id,
        ];

        $currencyoptions = [];
        foreach (commerce_settings_service::currency_options() as $code => $label) {
            $currencyoptions[] = ['value' => (string) $code, 'label' => (string) $label];
        }

        return [
            'values' => $values,
            'currencyoptions' => $currencyoptions,
            'positionoptions' => [
                ['value' => 'before', 'label' => get_string('currencyposition_before', 'local_moderncommerce')],
                ['value' => 'after', 'label' => get_string('currencyposition_after', 'local_moderncommerce')],
            ],
            'taxmodes' => [
                ['value' => 'disabled', 'label' => get_string('taxmode_disabled', 'local_moderncommerce')],
                ['value' => 'exclusive', 'label' => get_string('taxmode_exclusive', 'local_moderncommerce')],
            ],
            'fieldvisibilityoptions' => [
                ['value' => 'hidden', 'label' => get_string('hidden', 'local_moderncommerce')],
                ['value' => 'optional', 'label' => get_string('optional', 'local_moderncommerce')],
                ['value' => 'required', 'label' => get_string('required', 'local_moderncommerce')],
            ],
            'notificationpositionoptions' => self::notification_position_options(),
            'navitemoptions' => self::nav_item_options(),
            'navbarcartpositionoptions' => self::navbar_cart_position_options(),
            'coursedetailsidebarpositionoptions' => self::course_detail_sidebar_position_options(),
            'coursecertificateavailable' => certificate_template_service::available(),
            'certificatetemplateoptions' => self::certificate_template_options(),
            'metrics' => self::metrics($settings, $currency),
            'warnings' => [],
        ];
    }

    /**
     * Floating-toast notification position options.
     *
     * @return array
     */
    private static function notification_position_options(): array {
        $labels = [
            'top-left' => 'notificationposition_topleft',
            'top-center' => 'notificationposition_topcenter',
            'top-right' => 'notificationposition_topright',
            'bottom-left' => 'notificationposition_bottomleft',
            'bottom-center' => 'notificationposition_bottomcenter',
            'bottom-right' => 'notificationposition_bottomright',
        ];
        $options = [];
        foreach ($labels as $value => $stringkey) {
            $options[] = ['value' => $value, 'label' => get_string($stringkey, 'local_moderncommerce')];
        }
        return $options;
    }

    /**
     * Cart position options for Moodle's top-right user navigation.
     *
     * @return array
     */
    private static function navbar_cart_position_options(): array {
        return [
            [
                'value' => 'first',
                'label' => get_string('navbarcartposition_first', 'local_moderncommerce'),
            ],
            [
                'value' => 'last',
                'label' => get_string('navbarcartposition_last', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Course detail sidebar position options.
     *
     * @return array
     */
    private static function course_detail_sidebar_position_options(): array {
        return [
            [
                'value' => 'right',
                'label' => get_string('sidebarposition_right', 'local_moderncommerce'),
            ],
            [
                'value' => 'left',
                'label' => get_string('sidebarposition_left', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Hideable primary-navigation node options.
     *
     * @return array
     */
    private static function nav_item_options(): array {
        $labels = [
            'home' => get_string('home'),
            'myhome' => get_string('myhome'),
            'mycourses' => get_string('mycourses'),
            'siteadminnode' => get_string('administrationsite'),
        ];
        $options = [];
        foreach (commerce_settings_service::nav_items() as $key) {
            $options[] = ['value' => $key, 'label' => (string) ($labels[$key] ?? $key)];
        }
        return $options;
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'values' => self::values_structure(),
            'currencyoptions' => self::options_structure(),
            'positionoptions' => self::options_structure(),
            'taxmodes' => self::options_structure(),
            'fieldvisibilityoptions' => self::options_structure(),
            'notificationpositionoptions' => self::options_structure(),
            'navitemoptions' => self::options_structure(),
            'navbarcartpositionoptions' => self::options_structure(),
            'coursedetailsidebarpositionoptions' => self::options_structure(),
            'coursecertificateavailable' => new external_value(
                PARAM_BOOL,
                'Whether Moodle Course Certificate is available.'
            ),
            'certificatetemplateoptions' => self::options_structure(),
            'metrics' => new external_multiple_structure(new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Metric label.'),
                'value' => new external_value(PARAM_TEXT, 'Metric value.'),
                'icon' => new external_value(PARAM_TEXT, 'Icon class.'),
                'variant' => new external_value(PARAM_ALPHA, 'Tile variant.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Settings values structure.
     *
     * @return external_single_structure
     */
    public static function values_structure(): external_single_structure {
        return new external_single_structure([
            'primary_currency' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.'),
            'currency_position' => new external_value(PARAM_ALPHA, 'Symbol position.'),
            'decimal_places' => new external_value(PARAM_INT, 'Decimal places.'),
            'thousand_separator' => new external_value(PARAM_RAW, 'Thousand separator.'),
            'decimal_separator' => new external_value(PARAM_RAW, 'Decimal separator.'),
            'business_name' => new external_value(PARAM_TEXT, 'Business name.'),
            'support_email' => new external_value(PARAM_RAW, 'Support email.'),
            'support_url' => new external_value(PARAM_RAW, 'Support URL.'),
            'invoice_prefix' => new external_value(PARAM_RAW, 'Invoice prefix.'),
            'receipt_prefix' => new external_value(PARAM_RAW, 'Receipt prefix.'),
            'tax_mode' => new external_value(PARAM_ALPHA, 'Tax mode.'),
            'default_tax_rate' => new external_value(PARAM_FLOAT, 'Default tax rate.'),
            'contact_info_enabled' => new external_value(PARAM_INT, 'Collect contact info at checkout.'),
            'phone_field' => new external_value(PARAM_ALPHA, 'Phone field visibility.'),
            'address_field' => new external_value(PARAM_ALPHA, 'Address field visibility.'),
            'city_field' => new external_value(PARAM_ALPHA, 'City field visibility.'),
            'state_field' => new external_value(PARAM_ALPHA, 'State field visibility.'),
            'country_field' => new external_value(PARAM_ALPHA, 'Country field visibility.'),
            'zipcode_field' => new external_value(PARAM_ALPHA, 'ZIP/postal code field visibility.'),
            'adminnavlabel' => new external_value(PARAM_TEXT, 'Admin nav label.'),
            'learnernavlabel' => new external_value(PARAM_TEXT, 'Learner nav label.'),
            'hideprimarynavitems' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Hidden navigation node key.')
            ),
            'navbar_cart_position' => new external_value(PARAM_ALPHA, 'Navbar cart position.'),
            'notification_position' => new external_value(PARAM_ALPHANUMEXT, 'Floating toast position.'),
            'notification_autodismiss' => new external_value(PARAM_INT, 'Toast auto-dismiss delay (ms, 0 = sticky).'),
            'reviews_enabled' => new external_value(PARAM_INT, 'Course reviews enabled.'),
            'product_show_sku' => new external_value(PARAM_INT, 'Show the SKU field in the product form.'),
            'product_show_slug' => new external_value(PARAM_INT, 'Show the slug field in the product form.'),
            'course_detail_sidebar_position' => new external_value(PARAM_ALPHA, 'Course detail sidebar position.'),
            'enable_webhook_ip_whitelist' => new external_value(PARAM_INT, 'Webhook IP whitelist enabled.'),
            'payment_max_retries' => new external_value(PARAM_INT, 'Payment max retries.'),
            'default_program_certificate_template_id' => new external_value(
                PARAM_INT,
                'Default Course Certificate template for programs.'
            ),
        ]);
    }

    /**
     * Build Course Certificate template options, including no global default.
     *
     * @return array
     */
    private static function certificate_template_options(): array {
        $options = [[
            'value' => '0',
            'label' => get_string('nodefaultcertificatetemplate', 'local_moderncommerce'),
        ]];
        foreach (certificate_template_service::options() as $id => $name) {
            $options[] = ['value' => (string)$id, 'label' => $name];
        }
        return $options;
    }

    /**
     * Options structure.
     *
     * @return external_multiple_structure
     */
    private static function options_structure(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'value' => new external_value(PARAM_RAW, 'Option value.'),
            'label' => new external_value(PARAM_TEXT, 'Option label.'),
        ]));
    }

    /**
     * Build the summary metric tiles.
     *
     * @param \stdClass $settings Settings.
     * @param \stdClass $currency Currency config.
     * @return array
     */
    private static function metrics(\stdClass $settings, \stdClass $currency): array {
        return [
            [
                'label' => get_string('activecurrency', 'local_moderncommerce'),
                'value' => (string) $currency->currency,
                'icon' => 'bi-currency-exchange',
                'variant' => 'primary',
            ],
            [
                'label' => get_string('pricepreview', 'local_moderncommerce'),
                'value' => commerce_settings_service::format_amount(1234.56, $currency),
                'icon' => 'bi-cash-stack',
                'variant' => 'success',
            ],
            [
                'label' => get_string('taxmode', 'local_moderncommerce'),
                'value' => get_string('taxmode_' . $settings->taxmode, 'local_moderncommerce'),
                'icon' => 'bi-receipt',
                'variant' => $settings->taxmode === 'disabled' ? 'neutral' : 'warning',
            ],
            [
                'label' => get_string('supportemail', 'local_moderncommerce'),
                'value' => $settings->supportemail ?: get_string('notconfigured', 'local_moderncommerce'),
                'icon' => 'bi-envelope',
                'variant' => $settings->supportemail ? 'info' : 'neutral',
            ],
        ];
    }
}
