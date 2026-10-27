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

/**
 * Store-wide commerce settings service.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class commerce_settings_service {
    /** @var string Plugin component name. */
    private const COMPONENT = 'local_moderncommerce';

    /** @var string Default single active currency. */
    private const DEFAULT_CURRENCY = 'USD';

    /** @var string[] Allowed checkout field visibility values. */
    private const FIELD_VISIBILITY = ['hidden', 'optional', 'required'];

    /**
     * Checkout billing fields and their default visibility (mirrors checkout_helper::billing_fields()).
     *
     * @var array<string,string>
     */
    private const CHECKOUT_FIELDS = [
        'phone_field' => 'optional',
        'address_field' => 'hidden',
        'city_field' => 'hidden',
        'state_field' => 'hidden',
        'country_field' => 'hidden',
        'zipcode_field' => 'hidden',
    ];

    /** @var string[] Core primary-navigation node keys that may be hidden. */
    private const NAV_ITEMS = ['home', 'myhome', 'mycourses', 'siteadminnode'];

    /** @var string[] Old placeholder values that must not be shown in Boost navigation. */
    private const LEGACY_NAV_LABEL_PLACEHOLDERS = [
        'Admin Navigation Label Default',
        'Learner Navigation Label Default',
    ];

    /** @var string[] Allowed cart positions in Moodle's top-right user navigation. */
    public const NAVBAR_CART_POSITIONS = ['first', 'last'];

    /** @var string Default cart position in Moodle's top-right user navigation. */
    public const NAVBAR_CART_POSITION_DEFAULT = 'first';

    /** @var string[] Allowed sidebar positions on the public course detail page. */
    public const COURSE_DETAIL_SIDEBAR_POSITIONS = ['right', 'left'];

    /** @var string Default sidebar position on the public course detail page. */
    public const COURSE_DETAIL_SIDEBAR_POSITION_DEFAULT = 'right';

    /** @var string[] Allowed floating-toast notification positions. */
    public const NOTIFICATION_POSITIONS = [
        'top-left', 'top-center', 'top-right',
        'bottom-left', 'bottom-center', 'bottom-right',
    ];

    /** @var string Default notification position. */
    public const NOTIFICATION_POSITION_DEFAULT = 'top-right';

    /** @var int Default notification auto-dismiss delay (ms). */
    public const NOTIFICATION_AUTODISMISS_DEFAULT = 4000;

    /**
     * Get supported currency labels.
     *
     * @return array Currency code => display label.
     */
    public static function currency_options(): array {
        return [
            'NGN' => get_string('currency_ngn', self::COMPONENT),
            'USD' => get_string('currency_usd', self::COMPONENT),
            'EUR' => get_string('currency_eur', self::COMPONENT),
            'GBP' => get_string('currency_gbp', self::COMPONENT),
            'ZAR' => get_string('currency_zar', self::COMPONENT),
            'GHS' => get_string('currency_ghs', self::COMPONENT),
            'KES' => get_string('currency_kes', self::COMPONENT),
            'UGX' => get_string('currency_ugx', self::COMPONENT),
            'TZS' => get_string('currency_tzs', self::COMPONENT),
            'XOF' => get_string('currency_xof', self::COMPONENT),
            'XAF' => get_string('currency_xaf', self::COMPONENT),
            'EGP' => get_string('currency_egp', self::COMPONENT),
            'MAD' => get_string('currency_mad', self::COMPONENT),
            'CAD' => get_string('currency_cad', self::COMPONENT),
            'AUD' => get_string('currency_aud', self::COMPONENT),
            'INR' => get_string('currency_inr', self::COMPONENT),
            'CNY' => get_string('currency_cny', self::COMPONENT),
            'JPY' => get_string('currency_jpy', self::COMPONENT),
            'BRL' => get_string('currency_brl', self::COMPONENT),
            'CHF' => get_string('currency_chf', self::COMPONENT),
            'SGD' => get_string('currency_sgd', self::COMPONENT),
        ];
    }

    /**
     * Get a known currency symbol.
     *
     * @param string $currency Currency code.
     * @return string Currency symbol or code.
     */
    public static function known_currency_symbol(string $currency): string {
        $currency = strtoupper(trim($currency));
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => '¥',
            'INR' => '₹',
            'NGN' => '₦',
            'ZAR' => 'R',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'BRL' => 'R$',
            'KRW' => '₩',
            'RUB' => '₽',
            'MXN' => '$',
            'CHF' => 'Fr',
            'SEK' => 'kr',
            'NZD' => 'NZ$',
            'SGD' => 'S$',
            'HKD' => 'HK$',
            'NOK' => 'kr',
            'DKK' => 'kr',
            'PLN' => 'zł',
            'THB' => '฿',
            'IDR' => 'Rp',
            'MYR' => 'RM',
            'PHP' => '₱',
            'CZK' => 'Kč',
            'ILS' => '₪',
            'CLP' => '$',
            'TWD' => 'NT$',
            'TRY' => '₺',
            'AED' => 'د.إ',
            'SAR' => '﷼',
            'GHS' => '₵',
            'KES' => 'KSh',
            'UGX' => 'USh',
            'TZS' => 'TSh',
            'XOF' => 'CFA',
            'XAF' => 'FCFA',
            'EGP' => '£',
            'MAD' => 'DH',
        ];

        return $symbols[$currency] ?? ($currency ?: self::DEFAULT_CURRENCY);
    }

    /**
     * Get active currency and number-format settings.
     *
     * @return \stdClass Currency config.
     */
    public static function get_currency_config(): \stdClass {
        $currency = strtoupper((string)get_config(self::COMPONENT, 'primary_currency'));
        if ($currency === '' || !array_key_exists($currency, self::currency_options())) {
            $currency = self::DEFAULT_CURRENCY;
        }

        $symbol = self::known_currency_symbol($currency);

        $decimals = get_config(self::COMPONENT, 'decimal_places');
        $decimals = ($decimals === null || $decimals === '') ? 2 : (int)$decimals;
        $decimals = max(0, min(6, $decimals));

        $thousand = get_config(self::COMPONENT, 'thousand_separator');
        $thousand = ($thousand === null || $thousand === '') ? ',' : (string)$thousand;

        $decimal = get_config(self::COMPONENT, 'decimal_separator');
        $decimal = ($decimal === null || $decimal === '') ? '.' : (string)$decimal;

        $position = (string)get_config(self::COMPONENT, 'currency_position');
        if (!in_array($position, ['before', 'after'], true)) {
            $position = 'before';
        }

        return (object)[
            'currency' => $currency,
            'primarycurrency' => $currency,
            'symbol' => $symbol,
            'position' => $position,
            'decimals' => $decimals,
            'thousand' => substr($thousand, 0, 1),
            'decimal' => substr($decimal, 0, 1),
        ];
    }

    /**
     * Resolve a primary-navigation label, replacing empty or legacy placeholder
     * values with the current language-string default.
     *
     * @param string $name Config setting name.
     * @param string $defaultkey Language string key used as fallback.
     * @return string
     */
    public static function resolve_navigation_label(string $name, string $defaultkey): string {
        $label = get_config(self::COMPONENT, $name);
        $label = $label === false ? '' : trim((string)$label);

        if ($label === '' || in_array($label, self::LEGACY_NAV_LABEL_PLACEHOLDERS, true)) {
            return get_string($defaultkey, self::COMPONENT);
        }

        return $label;
    }

    /**
     * Normalize a navigation label before saving plugin settings.
     *
     * @param string $label User supplied label.
     * @return string
     */
    private static function normalize_navigation_label(string $label): string {
        $label = trim($label);
        return in_array($label, self::LEGACY_NAV_LABEL_PLACEHOLDERS, true) ? '' : $label;
    }

    /**
     * Get all admin-facing commerce settings.
     *
     * @return \stdClass Settings.
     */
    public static function get_admin_settings(): \stdClass {
        global $CFG, $SITE;

        $currency = self::get_currency_config();
        $taxenabled = !empty(get_config(self::COMPONENT, 'enable_tax'));
        $taxmode = (string)get_config(self::COMPONENT, 'tax_mode');
        if (!in_array($taxmode, ['disabled', 'exclusive'], true)) {
            $taxmode = $taxenabled ? 'exclusive' : 'disabled';
        }

        $businessname = (string)get_config(self::COMPONENT, 'business_name');
        if ($businessname === '' && !empty($SITE->fullname)) {
            $businessname = format_string($SITE->fullname);
        }

        $supportemail = (string)get_config(self::COMPONENT, 'support_email');
        if ($supportemail === '' && !empty($CFG->supportemail)) {
            $supportemail = (string)$CFG->supportemail;
        }

        // Checkout field visibility.
        $checkout = [];
        foreach (self::CHECKOUT_FIELDS as $key => $default) {
            $value = (string)get_config(self::COMPONENT, $key);
            $checkout[$key] = in_array($value, self::FIELD_VISIBILITY, true) ? $value : $default;
        }

        // Navigation.
        $adminnavlabel = self::resolve_navigation_label('adminnavlabel', 'adminnavlabel_default');
        $learnernavlabel = self::resolve_navigation_label('learnernavlabel', 'learnernavlabel_default');
        $hiddennav = (string)get_config(self::COMPONENT, 'hideprimarynavitems');
        $hiddennav = $hiddennav === '' ? [] : array_values(array_intersect(
            self::NAV_ITEMS,
            array_map('trim', explode(',', $hiddennav))
        ));
        $navbarcartposition = self::normalize_navbar_cart_position(
            get_config(self::COMPONENT, 'navbar_cart_position')
        );

        return (object)[
            'currency' => $currency,
            'businessname' => $businessname,
            'supportemail' => $supportemail,
            'supporturl' => (string)get_config(self::COMPONENT, 'support_url'),
            'invoiceprefix' => (string)(get_config(self::COMPONENT, 'invoice_prefix') ?: 'INV'),
            'receiptprefix' => (string)(get_config(self::COMPONENT, 'receipt_prefix') ?: 'RCPT'),
            'taxmode' => $taxmode,
            'enabletax' => $taxmode === 'exclusive' ? 1 : 0,
            'defaulttaxrate' => (float)(get_config(self::COMPONENT, 'default_tax_rate') ?: 0),
            // Checkout / contact fields.
            'contact_info_enabled' => self::config_bool('contact_info_enabled', false) ? 1 : 0,
            'phone_field' => $checkout['phone_field'],
            'address_field' => $checkout['address_field'],
            'city_field' => $checkout['city_field'],
            'state_field' => $checkout['state_field'],
            'country_field' => $checkout['country_field'],
            'zipcode_field' => $checkout['zipcode_field'],
            // Navigation.
            'adminnavlabel' => $adminnavlabel,
            'learnernavlabel' => $learnernavlabel,
            'hideprimarynavitems' => $hiddennav,
            'navbar_cart_position' => $navbarcartposition,
            // Notifications (floating toast).
            'notification_position' => self::normalize_notification_position(
                get_config(self::COMPONENT, 'notification_position')
            ),
            'notification_autodismiss' => self::normalize_notification_autodismiss(
                get_config(self::COMPONENT, 'notification_autodismiss')
            ),
            // Reviews.
            'reviews_enabled' => self::config_bool('reviews_enabled', true) ? 1 : 0,
            // Product form fields (SKU / slug). Hidden by default — generated internally.
            'product_show_sku' => self::config_bool('product_show_sku', false) ? 1 : 0,
            'product_show_slug' => self::config_bool('product_show_slug', false) ? 1 : 0,
            'course_detail_sidebar_position' => self::course_detail_sidebar_position(),
            // Payments & security.
            'enable_webhook_ip_whitelist' => self::config_bool('enable_webhook_ip_whitelist', true) ? 1 : 0,
            'payment_max_retries' => self::clamped_int('payment_max_retries', 3, 0, 10),
            'default_program_certificate_template_id' => certificate_template_service::default_template_id(),
        ];
    }

    /**
     * Coerce a stored value to a valid cart position in Moodle's top-right user navigation.
     *
     * @param mixed $value Raw config value.
     * @return string
     */
    private static function normalize_navbar_cart_position($value): string {
        $value = (string) $value;
        return in_array($value, self::NAVBAR_CART_POSITIONS, true)
            ? $value
            : self::NAVBAR_CART_POSITION_DEFAULT;
    }

    /**
     * Coerce a stored value to a valid public course-detail sidebar position.
     *
     * @param mixed $value Raw config value.
     * @return string
     */
    private static function normalize_course_detail_sidebar_position($value): string {
        $value = (string) $value;
        return in_array($value, self::COURSE_DETAIL_SIDEBAR_POSITIONS, true)
            ? $value
            : self::COURSE_DETAIL_SIDEBAR_POSITION_DEFAULT;
    }

    /**
     * Coerce a stored value to a valid notification position.
     *
     * @param mixed $value Raw config value.
     * @return string
     */
    private static function normalize_notification_position($value): string {
        $value = (string) $value;
        return in_array($value, self::NOTIFICATION_POSITIONS, true)
            ? $value
            : self::NOTIFICATION_POSITION_DEFAULT;
    }

    /**
     * Coerce a stored value to a valid notification auto-dismiss delay (ms, 0 = sticky).
     *
     * @param mixed $value Raw config value.
     * @return int
     */
    private static function normalize_notification_autodismiss($value): int {
        if ($value === false || $value === '' || $value === null) {
            return self::NOTIFICATION_AUTODISMISS_DEFAULT;
        }
        return max(0, (int) $value);
    }

    /**
     * Read a boolean config with a default when unset.
     *
     * @param string $name Config name.
     * @param bool $default Default when never configured.
     * @return bool
     */
    private static function config_bool(string $name, bool $default): bool {
        $value = get_config(self::COMPONENT, $name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (bool)$value;
    }

    /**
     * Read an int config clamped to a range, with a default when unset.
     *
     * @param string $name Config name.
     * @param int $default Default when never configured.
     * @param int $min Minimum.
     * @param int $max Maximum.
     * @return int
     */
    private static function clamped_int(string $name, int $default, int $min, int $max): int {
        $value = get_config(self::COMPONENT, $name);
        if ($value === false || $value === null || $value === '') {
            $value = $default;
        }
        return max($min, min($max, (int)$value));
    }

    /**
     * Whether the SKU field is shown (and editable) in the product form. Hidden by default;
     * when hidden the SKU is generated internally.
     *
     * @return bool
     */
    public static function show_product_sku(): bool {
        return self::config_bool('product_show_sku', false);
    }

    /**
     * Whether the slug field is shown (and editable) in the product form. Hidden by default;
     * when hidden the slug is generated internally.
     *
     * @return bool
     */
    public static function show_product_slug(): bool {
        return self::config_bool('product_show_slug', false);
    }

    /**
     * Sidebar position for public Modern Commerce course detail pages.
     *
     * @return string
     */
    public static function course_detail_sidebar_position(): string {
        return self::normalize_course_detail_sidebar_position(
            get_config(self::COMPONENT, 'course_detail_sidebar_position')
        );
    }

    /**
     * Get the checkout billing field keys and their default visibility.
     *
     * @return array<string,string>
     */
    public static function checkout_fields(): array {
        return self::CHECKOUT_FIELDS;
    }

    /**
     * Get the visibility option values for checkout fields.
     *
     * @return string[]
     */
    public static function field_visibility_options(): array {
        return self::FIELD_VISIBILITY;
    }

    /**
     * Get the hideable primary-navigation node keys.
     *
     * @return string[]
     */
    public static function nav_items(): array {
        return self::NAV_ITEMS;
    }

    /**
     * Validate and normalize submitted settings.
     *
     * @param array $data Submitted data.
     * @return array Tuple: [settings, errors].
     */
    public static function validate_admin_settings(array $data): array {
        $errors = [];

        $currency = strtoupper(trim((string)($data['primary_currency'] ?? self::DEFAULT_CURRENCY)));
        if (!array_key_exists($currency, self::currency_options())) {
            $errors['primary_currency'] = get_string('invalidcurrency', self::COMPONENT);
            $currency = self::DEFAULT_CURRENCY;
        }

        $position = (string)($data['currency_position'] ?? 'before');
        if (!in_array($position, ['before', 'after'], true)) {
            $position = 'before';
        }

        $decimals = (int)($data['decimal_places'] ?? 2);
        if ($decimals < 0 || $decimals > 6) {
            $errors['decimal_places'] = get_string('decimalplaces_invalid', self::COMPONENT);
            $decimals = max(0, min(6, $decimals));
        }

        $thousand = (string)($data['thousand_separator'] ?? ',');
        $decimal = (string)($data['decimal_separator'] ?? '.');
        $thousand = $thousand === '' ? ',' : \core_text::substr($thousand, 0, 1);
        $decimal = $decimal === '' ? '.' : \core_text::substr($decimal, 0, 1);
        if ($thousand === $decimal) {
            $errors['decimal_separator'] = get_string('separatorsmustdiffer', self::COMPONENT);
            $decimal = $decimal === '.' ? ',' : '.';
        }

        $businessname = trim((string)($data['business_name'] ?? ''));
        $supportemail = trim((string)($data['support_email'] ?? ''));
        if ($supportemail !== '' && !validate_email($supportemail)) {
            $errors['support_email'] = get_string('invalidemail');
        }

        $supporturl = trim((string)($data['support_url'] ?? ''));
        if ($supporturl !== '') {
            $supporturl = clean_param($supporturl, PARAM_URL);
        }

        $invoiceprefix = strtoupper(trim((string)($data['invoice_prefix'] ?? 'INV')));
        $invoiceprefix = preg_replace('/[^A-Z0-9_\-]/', '', $invoiceprefix) ?: 'INV';
        $invoiceprefix = substr($invoiceprefix, 0, 20);

        $receiptprefix = strtoupper(trim((string)($data['receipt_prefix'] ?? 'RCPT')));
        $receiptprefix = preg_replace('/[^A-Z0-9_\-]/', '', $receiptprefix) ?: 'RCPT';
        $receiptprefix = substr($receiptprefix, 0, 20);

        $taxmode = (string)($data['tax_mode'] ?? 'disabled');
        if (!in_array($taxmode, ['disabled', 'exclusive'], true)) {
            $taxmode = 'disabled';
        }

        $defaulttaxrate = (float)($data['default_tax_rate'] ?? 0);
        if ($defaulttaxrate < 0 || $defaulttaxrate > 100) {
            $errors['default_tax_rate'] = get_string('defaulttaxrate_invalid', self::COMPONENT);
            $defaulttaxrate = max(0, min(100, $defaulttaxrate));
        }

        // Checkout field visibility.
        $checkout = [];
        foreach (self::CHECKOUT_FIELDS as $key => $default) {
            $value = (string)($data[$key] ?? $default);
            $checkout[$key] = in_array($value, self::FIELD_VISIBILITY, true) ? $value : $default;
        }

        // Navigation.
        $adminnavlabel = self::normalize_navigation_label((string)($data['adminnavlabel'] ?? ''));
        $learnernavlabel = self::normalize_navigation_label((string)($data['learnernavlabel'] ?? ''));
        $hiddennav = (array)($data['hideprimarynavitems'] ?? []);
        $hiddennav = array_values(array_intersect(self::NAV_ITEMS, array_map('strval', $hiddennav)));
        $navbarcartposition = self::normalize_navbar_cart_position($data['navbar_cart_position'] ?? null);

        // Notifications (floating toast).
        $notifposition = self::normalize_notification_position($data['notification_position'] ?? null);
        $notifautodismiss = self::normalize_notification_autodismiss($data['notification_autodismiss'] ?? null);

        // Reviews.
        $reviewsenabled = !empty($data['reviews_enabled']) ? 1 : 0;

        // Product form fields (SKU / slug visibility).
        $showsku = !empty($data['product_show_sku']) ? 1 : 0;
        $showslug = !empty($data['product_show_slug']) ? 1 : 0;
        $coursedetailsidebarposition = self::normalize_course_detail_sidebar_position(
            $data['course_detail_sidebar_position'] ?? null
        );

        // Payments & security.
        $ipwhitelist = !empty($data['enable_webhook_ip_whitelist']) ? 1 : 0;
        $maxretries = max(0, min(10, (int)($data['payment_max_retries'] ?? 3)));
        $defaultcertificatetemplateid = max(0, (int)($data['default_program_certificate_template_id'] ?? 0));
        if (
            $defaultcertificatetemplateid > 0
            && !array_key_exists($defaultcertificatetemplateid, certificate_template_service::options())
        ) {
            $errors['default_program_certificate_template_id'] = get_string(
                'certificatetemplateinvalid',
                self::COMPONENT
            );
            $defaultcertificatetemplateid = certificate_template_service::default_template_id();
        }

        return [
            [
                'primary_currency' => $currency,
                'currency_symbol' => '',
                'currency_symbol_custom' => 0,
                'currency_position' => $position,
                'decimal_places' => $decimals,
                'thousand_separator' => $thousand,
                'decimal_separator' => $decimal,
                'business_name' => $businessname,
                'support_email' => $supportemail,
                'support_url' => $supporturl,
                'invoice_prefix' => $invoiceprefix,
                'receipt_prefix' => $receiptprefix,
                'tax_mode' => $taxmode,
                'enable_tax' => $taxmode === 'exclusive' ? 1 : 0,
                'default_tax_rate' => $defaulttaxrate,
                'contact_info_enabled' => !empty($data['contact_info_enabled']) ? 1 : 0,
                'phone_field' => $checkout['phone_field'],
                'address_field' => $checkout['address_field'],
                'city_field' => $checkout['city_field'],
                'state_field' => $checkout['state_field'],
                'country_field' => $checkout['country_field'],
                'zipcode_field' => $checkout['zipcode_field'],
                'adminnavlabel' => $adminnavlabel,
                'learnernavlabel' => $learnernavlabel,
                'hideprimarynavitems' => implode(',', $hiddennav),
                'navbar_cart_position' => $navbarcartposition,
                'notification_position' => $notifposition,
                'notification_autodismiss' => $notifautodismiss,
                'reviews_enabled' => $reviewsenabled,
                'product_show_sku' => $showsku,
                'product_show_slug' => $showslug,
                'course_detail_sidebar_position' => $coursedetailsidebarposition,
                'enable_webhook_ip_whitelist' => $ipwhitelist,
                'payment_max_retries' => $maxretries,
                'default_program_certificate_template_id' => $defaultcertificatetemplateid,
            ],
            $errors,
        ];
    }

    /**
     * Save admin-facing settings.
     *
     * @param array $settings Normalized settings.
     */
    public static function save_admin_settings(array $settings): void {
        foreach ($settings as $name => $value) {
            set_config($name, $value, self::COMPONENT);
        }
    }

    /**
     * Format an amount with the supplied or active currency config.
     *
     * @param float $amount Amount.
     * @param \stdClass|null $config Currency config.
     * @return string Formatted amount.
     */
    public static function format_amount(float $amount, ?\stdClass $config = null): string {
        $config = $config ?: self::get_currency_config();
        $formatted = number_format($amount, (int)$config->decimals, (string)$config->decimal, (string)$config->thousand);

        return $config->position === 'after'
            ? ($formatted . ' ' . $config->symbol)
            : ($config->symbol . $formatted);
    }
}
