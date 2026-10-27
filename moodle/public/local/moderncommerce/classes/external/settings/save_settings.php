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
 * External API saving Modern Commerce admin settings.
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

/**
 * Validate and save the Modern Commerce commerce settings.
 */
class save_settings extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'primary_currency' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.', VALUE_DEFAULT, ''),
            'currency_position' => new external_value(PARAM_ALPHA, 'Symbol position.', VALUE_DEFAULT, 'before'),
            'decimal_places' => new external_value(PARAM_INT, 'Decimal places.', VALUE_DEFAULT, 2),
            'thousand_separator' => new external_value(PARAM_TEXT, 'Thousand separator.', VALUE_DEFAULT, ','),
            'decimal_separator' => new external_value(PARAM_TEXT, 'Decimal separator.', VALUE_DEFAULT, '.'),
            'business_name' => new external_value(PARAM_TEXT, 'Business name.', VALUE_DEFAULT, ''),
            'support_email' => new external_value(PARAM_TEXT, 'Support email.', VALUE_DEFAULT, ''),
            'support_url' => new external_value(PARAM_URL, 'Support URL.', VALUE_DEFAULT, ''),
            'invoice_prefix' => new external_value(PARAM_ALPHANUMEXT, 'Invoice prefix.', VALUE_DEFAULT, 'INV'),
            'receipt_prefix' => new external_value(PARAM_ALPHANUMEXT, 'Receipt prefix.', VALUE_DEFAULT, 'RCPT'),
            'tax_mode' => new external_value(PARAM_ALPHA, 'Tax mode.', VALUE_DEFAULT, 'disabled'),
            'default_tax_rate' => new external_value(PARAM_FLOAT, 'Default tax rate.', VALUE_DEFAULT, 0),
            'phone_field' => new external_value(PARAM_ALPHA, 'Phone field visibility.', VALUE_DEFAULT, 'required'),
            'address_field' => new external_value(PARAM_ALPHA, 'Address field visibility.', VALUE_DEFAULT, 'required'),
            'city_field' => new external_value(PARAM_ALPHA, 'City field visibility.', VALUE_DEFAULT, 'required'),
            'state_field' => new external_value(PARAM_ALPHA, 'State field visibility.', VALUE_DEFAULT, 'optional'),
            'country_field' => new external_value(PARAM_ALPHA, 'Country field visibility.', VALUE_DEFAULT, 'required'),
            'zipcode_field' => new external_value(PARAM_ALPHA, 'ZIP/postal field visibility.', VALUE_DEFAULT, 'optional'),
            'adminnavlabel' => new external_value(PARAM_TEXT, 'Admin nav label.', VALUE_DEFAULT, ''),
            'learnernavlabel' => new external_value(PARAM_TEXT, 'Learner nav label.', VALUE_DEFAULT, ''),
            'hideprimarynavitems' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'Hidden navigation node key.'),
                'Hidden primary navigation nodes.',
                VALUE_DEFAULT,
                []
            ),
            'navbar_cart_position' => new external_value(PARAM_ALPHA, 'Navbar cart position.', VALUE_DEFAULT, 'first'),
            'reviews_enabled' => new external_value(PARAM_INT, 'Course reviews enabled.', VALUE_DEFAULT, 1),
            'product_show_sku' => new external_value(
                PARAM_INT,
                'Show the SKU field in the product form.',
                VALUE_DEFAULT,
                0
            ),
            'product_show_slug' => new external_value(
                PARAM_INT,
                'Show the slug field in the product form.',
                VALUE_DEFAULT,
                0
            ),
            'course_detail_sidebar_position' => new external_value(
                PARAM_ALPHA,
                'Course detail sidebar position.',
                VALUE_DEFAULT,
                'right'
            ),
            'enable_webhook_ip_whitelist' => new external_value(PARAM_INT, 'Webhook IP whitelist enabled.', VALUE_DEFAULT, 1),
            'payment_max_retries' => new external_value(PARAM_INT, 'Payment max retries.', VALUE_DEFAULT, 3),
            'notification_position' => new external_value(
                PARAM_ALPHANUMEXT,
                'Floating toast position.',
                VALUE_DEFAULT,
                'top-right'
            ),
            'notification_autodismiss' => new external_value(
                PARAM_INT,
                'Toast auto-dismiss delay (ms, 0 = sticky).',
                VALUE_DEFAULT,
                4000
            ),
            'contact_info_enabled' => new external_value(
                PARAM_INT,
                'Whether contact information is collected at checkout.',
                VALUE_DEFAULT,
                0
            ),
            'default_program_certificate_template_id' => new external_value(
                PARAM_INT,
                'Default Course Certificate template for programs.',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $primarycurrency Currency code.
     * @param string $currencyposition Symbol position.
     * @param int $decimalplaces Decimal places.
     * @param string $thousandseparator Thousand separator.
     * @param string $decimalseparator Decimal separator.
     * @param string $businessname Business name.
     * @param string $supportemail Support email.
     * @param string $supporturl Support URL.
     * @param string $invoiceprefix Invoice prefix.
     * @param string $receiptprefix Receipt prefix.
     * @param string $taxmode Tax mode.
     * @param float $defaulttaxrate Default tax rate.
     * @param string $phonefield Phone field visibility.
     * @param string $addressfield Address field visibility.
     * @param string $cityfield City field visibility.
     * @param string $statefield State field visibility.
     * @param string $countryfield Country field visibility.
     * @param string $zipcodefield ZIP/postal field visibility.
     * @param string $adminnavlabel Admin nav label.
     * @param string $learnernavlabel Learner nav label.
     * @param array $hideprimarynavitems Hidden primary navigation nodes.
     * @param string $navbarcartposition Navbar cart position.
     * @param int $reviewsenabled Course reviews enabled.
     * @param int $productshowsku Show the SKU field in the product form.
     * @param int $productshowslug Show the slug field in the product form.
     * @param string $coursedetailsidebarposition Course detail sidebar position.
     * @param int $enablewebhookipwhitelist Webhook IP whitelist enabled.
     * @param int $paymentmaxretries Payment max retries.
     * @param string $notificationposition Floating toast position.
     * @param int $notificationautodismiss Toast auto-dismiss delay (ms).
     * @param int $contactinfoenabled Whether contact info is collected at checkout.
     * @param int $defaultprogramcertificatetemplateid Default Course Certificate template ID.
     * @return array
     */
    public static function execute(
        string $primarycurrency = '',
        string $currencyposition = 'before',
        int $decimalplaces = 2,
        string $thousandseparator = ',',
        string $decimalseparator = '.',
        string $businessname = '',
        string $supportemail = '',
        string $supporturl = '',
        string $invoiceprefix = 'INV',
        string $receiptprefix = 'RCPT',
        string $taxmode = 'disabled',
        float $defaulttaxrate = 0,
        string $phonefield = 'required',
        string $addressfield = 'required',
        string $cityfield = 'required',
        string $statefield = 'optional',
        string $countryfield = 'required',
        string $zipcodefield = 'optional',
        string $adminnavlabel = '',
        string $learnernavlabel = '',
        array $hideprimarynavitems = [],
        string $navbarcartposition = 'first',
        int $reviewsenabled = 1,
        int $productshowsku = 0,
        int $productshowslug = 0,
        string $coursedetailsidebarposition = 'right',
        int $enablewebhookipwhitelist = 1,
        int $paymentmaxretries = 3,
        string $notificationposition = 'top-right',
        int $notificationautodismiss = 4000,
        int $contactinfoenabled = 0,
        int $defaultprogramcertificatetemplateid = 0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'primary_currency' => $primarycurrency,
            'currency_position' => $currencyposition,
            'decimal_places' => $decimalplaces,
            'thousand_separator' => $thousandseparator,
            'decimal_separator' => $decimalseparator,
            'business_name' => $businessname,
            'support_email' => $supportemail,
            'support_url' => $supporturl,
            'invoice_prefix' => $invoiceprefix,
            'receipt_prefix' => $receiptprefix,
            'tax_mode' => $taxmode,
            'default_tax_rate' => $defaulttaxrate,
            'phone_field' => $phonefield,
            'address_field' => $addressfield,
            'city_field' => $cityfield,
            'state_field' => $statefield,
            'country_field' => $countryfield,
            'zipcode_field' => $zipcodefield,
            'adminnavlabel' => $adminnavlabel,
            'learnernavlabel' => $learnernavlabel,
            'hideprimarynavitems' => $hideprimarynavitems,
            'navbar_cart_position' => $navbarcartposition,
            'reviews_enabled' => $reviewsenabled,
            'product_show_sku' => $productshowsku,
            'product_show_slug' => $productshowslug,
            'course_detail_sidebar_position' => $coursedetailsidebarposition,
            'enable_webhook_ip_whitelist' => $enablewebhookipwhitelist,
            'payment_max_retries' => $paymentmaxretries,
            'notification_position' => $notificationposition,
            'notification_autodismiss' => $notificationautodismiss,
            'contact_info_enabled' => $contactinfoenabled,
            'default_program_certificate_template_id' => $defaultprogramcertificatetemplateid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managesettings', $context);

        [$normalized, $errors] = commerce_settings_service::validate_admin_settings($params);

        if (!empty($errors)) {
            $fielderrors = [];
            foreach ($errors as $field => $message) {
                $fielderrors[] = ['field' => (string) $field, 'message' => (string) $message];
            }
            return ['success' => false, 'message' => '', 'errors' => $fielderrors, 'warnings' => []];
        }

        commerce_settings_service::save_admin_settings($normalized);

        return [
            'success' => true,
            'message' => get_string('settingssaved', 'local_moderncommerce'),
            'errors' => [],
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the settings were saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'errors' => new external_multiple_structure(new external_single_structure([
                'field' => new external_value(PARAM_ALPHANUMEXT, 'Field name.'),
                'message' => new external_value(PARAM_TEXT, 'Validation message.'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }
}
