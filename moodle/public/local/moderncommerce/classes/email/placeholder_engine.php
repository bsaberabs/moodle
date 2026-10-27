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
 * Placeholder substitution engine for Modern Commerce Core Email Templates.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\email;

/**
 * Resolves {placeholder} tokens inside subject/body strings.
 */
class placeholder_engine {
    /**
     * Available placeholder categories with descriptions (UI palette + reference).
     *
     * @return array Placeholder definitions keyed by category.
     */
    public static function get_available_placeholders() {
        return [
            'user' => [
                '{firstname}' => 'User/Recipient first name',
                '{lastname}' => 'User/Recipient last name',
                '{fullname}' => 'User/Recipient full name',
                '{email}' => 'User/Recipient email address',
                '{phone}' => 'User/Recipient phone number',
                '{city}' => 'User/Recipient city',
                '{country}' => 'User/Recipient country',
            ],
            'course' => [
                '{course_name}' => 'Course full name',
                '{course_code}' => 'Course short name / code',
                '{course_summary}' => 'Course description/summary',
                '{course_link}' => 'Link to course page',
                '{course_startdate}' => 'Course start date (formatted)',
                '{course_enddate}' => 'Course end date (formatted)',
                '{instructor_name}' => 'Primary instructor name',
                '{instructor_email}' => 'Primary instructor email',
            ],
            'order' => [
                '{order_number}' => 'Order/Transaction number',
                '{order_date}' => 'Order creation date (formatted)',
                '{order_status}' => 'Current order status (paid, pending, etc.)',
                '{order_total}' => 'Order total amount',
                '{subtotal}' => 'Subtotal before tax/discount',
                '{discount}' => 'Discount amount',
                '{tax}' => 'Tax amount',
                '{currency}' => 'Currency code (USD, EUR, etc.)',
                '{payment_method}' => 'Payment method used (Stripe, PayPal, etc.)',
            ],
            'order_extra' => [
                '{courses_list}' => 'Preformatted list of purchased courses',
                '{my_courses_url}' => 'Link to the learner\'s courses',
                '{order_view_link}' => 'Link to view the order/receipt',
                '{retry_payment_url}' => 'Link to complete/retry payment',
                '{cart_items}' => 'Preformatted list of cart items',
                '{cart_items_count}' => 'Number of items in the cart',
                '{cart_total}' => 'Cart total amount',
                '{cart_url}' => 'Link to the shopping cart',
                '{checkout_url}' => 'Link to resume checkout',
            ],
            'coupon' => [
                '{coupon_code}' => 'Coupon / discount code',
                '{coupon_expiry}' => 'Coupon expiry date (formatted)',
                '{coupon_reject_reason}' => 'Why a coupon could not be applied',
                '{coupon_min_spend}' => 'Minimum spend for a coupon',
                '{discount_percent}' => 'Discount percentage',
            ],
            'refund' => [
                '{refund_amount}' => 'Refund amount',
                '{refund_reference}' => 'Refund reference number',
                '{refund_reason}' => 'Reason given for the refund',
            ],
            'access' => [
                '{access_enddate}' => 'Course access end date (formatted)',
                '{renew_url}' => 'Link to renew/extend access',
                '{catalog_url}' => 'Link to the course catalogue',
                '{certificate_name}' => 'Certificate name',
                '{certificate_url}' => 'Link to download the certificate',
                '{certificate_expiry}' => 'Certificate expiry date (formatted)',
            ],
            'program' => [
                '{program_name}' => 'Program name',
                '{completed_courses}' => 'Number of completed program courses',
                '{average_grade}' => 'Final program average grade',
                '{completion_date}' => 'Program completion date (formatted)',
                '{program_url}' => 'Link to the completed program',
            ],
            'subscription' => [
                '{plan_name}' => 'Subscription plan name',
                '{old_plan_name}' => 'Previous plan name (upgrade/downgrade)',
                '{new_plan_name}' => 'New plan name (upgrade/downgrade)',
                '{plan_price}' => 'Plan price (formatted)',
                '{billing_cycle}' => 'Billing cycle (monthly/yearly)',
                '{trial_days}' => 'Trial period in days',
                '{trial_end_date}' => 'Trial end date (formatted)',
                '{subscription_startdate}' => 'Subscription start date (formatted)',
                '{subscription_enddate}' => 'Subscription end date (formatted)',
                '{next_billing_date}' => 'Next billing/charge date (formatted)',
                '{effective_date}' => 'Date a scheduled plan change takes effect',
                '{days_remaining}' => 'Days remaining (renewal/expiry countdown)',
                '{days_extended}' => 'Number of days an admin extended access',
                '{courses_list}' => 'Preformatted list of courses accessible',
                '{my_subscription_url}' => 'Link to manage the subscription',
                '{renewal_url}' => 'Link to renew the subscription',
                '{reactivate_url}' => 'Link to reactivate the subscription',
                '{update_payment_url}' => 'Link to update the payment method',
                '{invoice_url}' => 'Link to a renewal receipt/invoice',
                '{winback_coupon}' => 'Win-back coupon code',
                '{winback_discount}' => 'Win-back discount (e.g. 30%)',
            ],
            'invoice' => [
                '{invoice_number}' => 'Invoice number',
                '{invoice_total}' => 'Invoice total amount',
                '{invoice_duedate}' => 'Invoice due date (formatted)',
                '{invoice_url}' => 'Link to view the invoice',
                '{invoice_pdf_url}' => 'Link to download the invoice/receipt PDF',
                '{pay_invoice_url}' => 'Link to pay the invoice',
                '{organisation_name}' => 'Buyer organisation name',
            ],
            'keys' => [
                '{key_code}' => 'Access/enrolment key code',
                '{key_count}' => 'Number of keys generated',
                '{key_target_name}' => 'Course/bundle a key unlocks',
                '{key_expiry}' => 'Key expiry date (formatted)',
                '{keys_csv_url}' => 'Link to download keys as CSV',
                '{redeem_url}' => 'Link to redeem a key',
                '{seats_total}' => 'Total seats in a key pool',
                '{seats_used}' => 'Seats used in a key pool',
                '{seats_remaining}' => 'Seats remaining in a key pool',
                '{manager_dashboard_url}' => 'Link to the seat-manager dashboard',
            ],
            'marketing' => [
                '{product_name}' => 'Product/course name',
                '{product_url}' => 'Link to the product/course page',
                '{old_price}' => 'Previous price (formatted)',
                '{new_price}' => 'New/sale price (formatted)',
                '{promo_end_date}' => 'Promotion end date (formatted)',
                '{unsubscribe_url}' => 'One-click marketing unsubscribe link (hub-signed)',
            ],
            'ops' => [
                '{customer_name}' => 'Customer name (admin alerts)',
                '{admin_order_url}' => 'Admin link to the order',
                '{admin_dashboard_url}' => 'Admin dashboard link',
                '{ops_report_url}' => 'Link to the ops/sales report',
                '{gateway_name}' => 'Payment gateway name',
                '{error_detail}' => 'Error detail/top reason',
                '{failed_count}' => 'Count of failed payments/events',
                '{period_label}' => 'Reporting period label',
                '{revenue_total}' => 'Total revenue for the period',
                '{orders_count}' => 'Order count for the period',
                '{refunds_count}' => 'Refund count for the period',
                '{new_subs_count}' => 'New subscriptions for the period',
                '{churn_count}' => 'Cancellations for the period',
                '{churned_plan}' => 'Plan a churned customer left',
                '{mrr_total}' => 'Current monthly recurring revenue',
                '{upcoming_renewals_count}' => 'Renewals due in the next 7 days',
                '{upcoming_renewals_value}' => 'Value of upcoming renewals',
            ],
            'global' => [
                '{sitename}' => 'Site full name',
                '{siteurl}' => 'Site URL',
                '{supportemail}' => 'Support email address',
                '{logo}' => 'Site logo URL (core admin logo)',
                '{logo_compact}' => 'Compact site logo URL',
            ],
        ];
    }

    /**
     * Convert legacy double-brace syntax to single-brace.
     *
     * @param string $template Template with placeholders.
     * @return string Normalised template.
     */
    private function normalize_placeholder_syntax($template) {
        return preg_replace('/\{\{(\w+)\}\}/', '{$1}', $template);
    }

    /**
     * Substitute placeholders in a template with data.
     *
     * @param string $template Template string with {placeholder} syntax.
     * @param array $data Associative array of data for substitution.
     * @param array $defaults Optional default values for missing placeholders.
     * @return string Template with placeholders substituted.
     */
    public function substitute_placeholders($template, $data = [], $defaults = []) {
        if (empty($template)) {
            return '';
        }

        $template = $this->normalize_placeholder_syntax($template);

        $mergeddata = array_merge($defaults, $data);

        $matches = [];
        preg_match_all('/\{(\w+)\}/', $template, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $placeholder) {
                $key = '{' . $placeholder . '}';
                $value = $this->get_value_from_data($placeholder, $mergeddata);
                if ($value !== null) {
                    $template = str_replace($key, $value, $template);
                }
                // Leave placeholder as-is if not found (allows partial substitution).
            }
        }

        return $template;
    }

    /**
     * Get a value from a (possibly nested) data array using underscore notation.
     *
     * @param string $key Key to retrieve (supports e.g. course_name -> data['course']['name']).
     * @param array $data Data array.
     * @return string|null Value or null if not found.
     */
    private function get_value_from_data($key, $data) {
        if (isset($data[$key])) {
            return $this->format_value($data[$key]);
        }

        $parts = explode('_', $key, 2);
        if (count($parts) === 2) {
            $section = $parts[0];
            $field = $parts[1];

            if (isset($data[$section]) && is_array($data[$section]) && isset($data[$section][$field])) {
                return $this->format_value($data[$section][$field]);
            } else if (isset($data[$section]) && is_object($data[$section]) && isset($data[$section]->$field)) {
                return $this->format_value($data[$section]->$field);
            }
        }

        return null;
    }

    /**
     * Format a value for display in an email template.
     *
     * @param mixed $value Value to format.
     * @return string Formatted value.
     */
    private function format_value($value) {
        if ($value === null || $value === false) {
            return '';
        }

        if (is_array($value)) {
            return implode(', ', array_map(function ($v) {
                return $this->format_value($v);
            }, $value));
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            if ($value instanceof \moodle_url) {
                return $value->out(false);
            }
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate template syntax.
     *
     * @param string $template Template to validate.
     * @return array ['valid' => bool, 'errors' => array].
     */
    public function validate_template($template) {
        $errors = [];

        if (empty($template)) {
            return ['valid' => true, 'errors' => []];
        }

        // Note: we deliberately do not flag unmatched braces because HTML/CSS
        // content may contain legitimate braces (e.g. CSS blocks).
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get the list of placeholder names used in a template.
     *
     * @param string $template Template string.
     * @return array Array of placeholder names found.
     */
    public function get_placeholders_used($template) {
        $matches = [];
        preg_match_all('/\{(\w+)\}/', $template, $matches);
        return array_unique($matches[1] ?? []);
    }

    /**
     * Global placeholder values available in all templates.
     *
     * @return array Associative array of global placeholder values.
     */
    public static function get_global_placeholder_values() {
        global $CFG, $USER, $COURSE;

        $data = [];

        // Site information (prefer Modern Commerce store settings when available).
        $sitename = $CFG->fullname ?? '';
        $supportemail = $CFG->supportemail ?? '';
        if (class_exists('\local_moderncommerce\services\commerce_settings_service')) {
            try {
                $settings = \local_moderncommerce\services\commerce_settings_service::get_admin_settings();
                if (!empty($settings->businessname)) {
                    $sitename = $settings->businessname;
                }
                if (!empty($settings->supportemail)) {
                    $supportemail = $settings->supportemail;
                }
            } catch (\Throwable $e) {
                debugging('Modern Commerce settings unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $data['sitename'] = $sitename;
        $data['siteurl'] = $CFG->wwwroot ?? '';
        $data['supportemail'] = $supportemail;

        // Logos (core admin site logo; cron- and email-safe).
        $logo = self::core_logo_url('logo', 0, 200);
        $logocompact = self::core_logo_url('logocompact', 300, 300);
        $data['logo'] = $logo;
        $data['logo_compact'] = $logocompact;
        // Back-compat aliases used by older templates.
        $data['logo_dark'] = $logo;
        $data['logo_white'] = $logo;

        // Current user information (if logged in).
        if (isloggedin() && !isguestuser()) {
            $data['firstname'] = $USER->firstname ?? '';
            $data['lastname'] = $USER->lastname ?? '';
            $data['fullname'] = fullname($USER);
            $data['email'] = $USER->email ?? '';
        }

        // Current course information (if in a course context).
        if (!empty($COURSE) && $COURSE->id != SITEID) {
            $data['course_name'] = $COURSE->fullname ?? '';
            $data['course_code'] = $COURSE->shortname ?? '';
            $data['course_link'] = (new \moodle_url('/course/view.php', ['id' => $COURSE->id]))->out(false);
            $data['course_summary'] = $COURSE->summary ?? '';
        }

        return $data;
    }

    /**
     * Build an absolute, cron-safe URL for a core admin site logo.
     *
     * Mirrors core_renderer::get_logo_url()/get_compact_logo_url(): the file is
     * served from the system context, component core_admin, with the requested
     * size in the itemid segment and the theme revision in the path so the URL
     * is cacheable and resolves without a login session (e.g. inside emails).
     *
     * @param string $area Logo file area ('logo' or 'logocompact').
     * @param int $maxwidth Maximum width (0 = unconstrained).
     * @param int $maxheight Maximum height.
     * @return string Absolute logo URL, or '' when no logo is configured.
     */
    private static function core_logo_url(string $area = 'logo', int $maxwidth = 0, int $maxheight = 200): string {
        $logo = get_config('core_admin', $area);
        if (empty($logo)) {
            return '';
        }

        $filepath = ((int) $maxwidth . 'x' . (int) $maxheight) . '/';

        $url = \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            'core_admin',
            $area,
            $filepath,
            theme_get_revision(),
            $logo
        );

        return $url->out(false);
    }
}
