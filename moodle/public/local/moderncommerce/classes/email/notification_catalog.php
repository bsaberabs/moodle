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
 * Catalog of editable Modern Commerce ecommerce emails.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\email;

/**
 * Builds the editable email-notification registry used by admin UI and delivery.
 */
class notification_catalog {
    /** @var string Plugin component. */
    private const COMPONENT = 'local_moderncommerce';

    /**
     * Legacy admin type keys used by the original checkout essentials editor.
     */
    private const LEGACY_TEMPLATE_KEYS = [
        'orderconfirmation' => 'moderncommerce_order_placed',
        'paymentreceipt' => 'moderncommerce_payment_receipt',
        'enrollmentconfirmation' => 'moderncommerce_enrollment_confirmation',
        'keyredemption' => 'moderncommerce_key_redeemed',
        'refundconfirmation' => 'moderncommerce_refund_confirmation',
    ];

    /**
     * Return all editable email notification types.
     *
     * @return array[]
     */
    public static function types(): array {
        $types = self::legacy_types();
        $legacytemplatekeys = array_values(self::LEGACY_TEMPLATE_KEYS);

        foreach (commerce_seed::definitions() as $definition) {
            $templatekey = (string) ($definition['template_key'] ?? '');
            if ($templatekey === '' || in_array($templatekey, $legacytemplatekeys, true)) {
                continue;
            }

            $category = (string) ($definition['template_type'] ?? 'transactional');
            $groupkey = self::group_key($templatekey);
            $types[$templatekey] = [
                'configkey' => $templatekey,
                'templatekey' => $templatekey,
                'name' => (string) ($definition['name'] ?? $templatekey),
                'description' => self::description(
                    (string) ($definition['name'] ?? $templatekey),
                    $category
                ),
                'icon' => self::icon($templatekey, $category),
                'color' => self::color($templatekey, $category),
                'category' => $category,
                'groupkey' => $groupkey,
                'grouplabel' => self::group_label($groupkey),
                'placeholders' => self::placeholder_list($definition['placeholders'] ?? '[]'),
                'subjectdefault' => (string) ($definition['subject'] ?? ''),
                'bodydefault' => (string) ($definition['body'] ?? ''),
                'enableddefault' => self::enabled_default($category),
            ];
        }

        return $types;
    }

    /**
     * Resolve one editable type by type key or template key.
     *
     * @param string $key Type key or template key.
     * @return array|null
     */
    public static function get(string $key): ?array {
        $types = self::types();
        if (isset($types[$key])) {
            return $types[$key];
        }

        foreach ($types as $type) {
            if (($type['templatekey'] ?? '') === $key) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Whether the email type is enabled.
     *
     * Unset config uses the catalog default. This keeps existing transactional
     * emails on while leaving marketing automations opt-in.
     *
     * @param string $key Type key or template key.
     * @return bool
     */
    public static function is_enabled(string $key): bool {
        $type = self::get($key);
        if ($type === null) {
            return true;
        }

        $value = get_config(self::COMPONENT, $type['configkey'] . '_enabled');
        if ($value === false) {
            return !empty($type['enableddefault']);
        }

        return (bool) $value;
    }

    /**
     * Resolve the config key that controls a template key.
     *
     * @param string $templatekey Template key.
     * @return string
     */
    public static function config_key_for_template(string $templatekey): string {
        $type = self::get($templatekey);
        return $type['configkey'] ?? '';
    }

    /**
     * Return a seeded template definition as a renderable record.
     *
     * @param string $templatekey Template key.
     * @return \stdClass|null
     */
    public static function seeded_record(string $templatekey): ?\stdClass {
        foreach (commerce_seed::definitions() as $definition) {
            if (($definition['template_key'] ?? '') !== $templatekey) {
                continue;
            }

            return (object) [
                'template_key' => (string) $definition['template_key'],
                'component' => (string) ($definition['component'] ?? self::COMPONENT),
                'name' => (string) ($definition['name'] ?? $templatekey),
                'template_type' => (string) ($definition['template_type'] ?? 'transactional'),
                'subject' => (string) ($definition['subject'] ?? ''),
                'body' => (string) ($definition['body'] ?? ''),
                'placeholders' => (string) ($definition['placeholders'] ?? '[]'),
                'status' => 'active',
                'locked' => 1,
            ];
        }

        return null;
    }

    /**
     * Original checkout essentials with legacy config keys.
     *
     * @return array[]
     */
    private static function legacy_types(): array {
        return [
            'orderconfirmation' => [
                'configkey' => 'orderconfirmation',
                'templatekey' => 'moderncommerce_order_placed',
                'name' => get_string('orderconfirmationemail', self::COMPONENT),
                'description' => get_string('orderconfirmationemail_desc', self::COMPONENT),
                'icon' => 'bi-cart-check',
                'color' => 'primary',
                'category' => 'transactional',
                'groupkey' => 'checkout_order',
                'grouplabel' => self::group_label('checkout_order'),
                'placeholders' => '{firstname}, {lastname}, {fullname}, {email}, {order_number}, {order_date}, '
                    . '{order_status}, {order_total}, {currency}, {courses_list}, {course_count}, '
                    . '{order_view_link}, {my_courses_link}, {business_name}, {support_email}, '
                    . '{support_url}, {sitename}, {siteurl}',
                'subjectdefault' => get_string('email_orderconfirmation_subject_default', self::COMPONENT),
                'bodydefault' => get_string('email_orderconfirmation_body_default', self::COMPONENT),
                'enableddefault' => true,
            ],
            'paymentreceipt' => [
                'configkey' => 'paymentreceipt',
                'templatekey' => 'moderncommerce_payment_receipt',
                'name' => get_string('paymentreceiptemail', self::COMPONENT),
                'description' => get_string('paymentreceiptemail_desc', self::COMPONENT),
                'icon' => 'bi-receipt',
                'color' => 'success',
                'category' => 'transactional',
                'groupkey' => 'checkout_order',
                'grouplabel' => self::group_label('checkout_order'),
                'placeholders' => '{firstname}, {fullname}, {email}, {transaction_id}, {payment_method}, '
                    . '{payment_date}, {amount_paid}, {order_number}, {order_total}, {currency}, '
                    . '{courses_list}, {course_count}, {my_courses_link}, {business_name}, '
                    . '{support_email}, {support_url}, {sitename}, {siteurl}',
                'subjectdefault' => get_string('email_paymentreceipt_subject_default', self::COMPONENT),
                'bodydefault' => get_string('email_paymentreceipt_body_default', self::COMPONENT),
                'enableddefault' => true,
            ],
            'enrollmentconfirmation' => [
                'configkey' => 'enrollmentconfirmation',
                'templatekey' => 'moderncommerce_enrollment_confirmation',
                'name' => get_string('enrollmentconfirmationemail', self::COMPONENT),
                'description' => get_string('enrollmentconfirmationemail_desc', self::COMPONENT),
                'icon' => 'bi-person-check',
                'color' => 'info',
                'category' => 'transactional',
                'groupkey' => 'access_learning',
                'grouplabel' => self::group_label('access_learning'),
                'placeholders' => '{firstname}, {fullname}, {email}, {course_name}, {course_code}, '
                    . '{course_summary}, {course_link}, {order_number}, {enrollment_date}, '
                    . '{go_to_course_button}, {instructor_name}, {instructor_email}, {business_name}, '
                    . '{support_email}, {support_url}, {sitename}, {siteurl}',
                'subjectdefault' => get_string('email_enrollment_subject_default', self::COMPONENT),
                'bodydefault' => get_string('email_enrollment_body_default', self::COMPONENT),
                'enableddefault' => true,
            ],
            'keyredemption' => [
                'configkey' => 'keyredemption',
                'templatekey' => 'moderncommerce_key_redeemed',
                'name' => get_string('keyredemptionemail', self::COMPONENT),
                'description' => get_string('keyredemptionemail_desc', self::COMPONENT),
                'icon' => 'bi-key',
                'color' => 'warning',
                'category' => 'transactional',
                'groupkey' => 'keys',
                'grouplabel' => self::group_label('keys'),
                'placeholders' => '{firstname}, {fullname}, {email}, {enrollment_key}, {key_type}, '
                    . '{redemption_date}, {course_name}, {course_code}, {course_summary}, {course_link}, '
                    . '{go_to_course_button}, {my_courses_link}, {business_name}, {support_email}, '
                    . '{support_url}, {sitename}, {siteurl}',
                'subjectdefault' => get_string('email_keyredemption_subject_default', self::COMPONENT),
                'bodydefault' => get_string('email_keyredemption_body_default', self::COMPONENT),
                'enableddefault' => true,
            ],
            'refundconfirmation' => [
                'configkey' => 'refundconfirmation',
                'templatekey' => 'moderncommerce_refund_confirmation',
                'name' => get_string('refundconfirmationemail', self::COMPONENT),
                'description' => get_string('refundconfirmationemail_desc', self::COMPONENT),
                'icon' => 'bi-arrow-counterclockwise',
                'color' => 'danger',
                'category' => 'transactional',
                'groupkey' => 'checkout_order',
                'grouplabel' => self::group_label('checkout_order'),
                'placeholders' => '{firstname}, {fullname}, {email}, {refund_amount}, {refund_reason}, '
                    . '{refund_date}, {order_number}, {order_date}, {original_amount}, {courses_list}, '
                    . '{contact_support_link}, {business_name}, {support_email}, {support_url}, '
                    . '{sitename}, {siteurl}',
                'subjectdefault' => get_string('email_refund_subject_default', self::COMPONENT),
                'bodydefault' => get_string('email_refund_body_default', self::COMPONENT),
                'enableddefault' => true,
            ],
        ];
    }

    /**
     * Convert seed placeholders to the editable comma-list format.
     *
     * @param mixed $raw Raw JSON/array placeholder value.
     * @return string
     */
    private static function placeholder_list($raw): string {
        $placeholders = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $tokens = [];
        foreach ($placeholders as $placeholder) {
            $token = trim((string) $placeholder);
            if ($token !== '') {
                $tokens[] = '{' . trim($token, '{}') . '}';
            }
        }

        return implode(', ', array_values(array_unique($tokens)));
    }

    /**
     * Build a concise purpose line for seeded templates.
     *
     * @param string $name Template name.
     * @param string $category Notification category.
     * @return string
     */
    private static function description(string $name, string $category): string {
        $purpose = strtolower(str_replace([' - ', ' -- ', '_'], ' ', $name));
        $prefix = match ($category) {
            'marketing' => 'Sends a suppressible commerce message for',
            'operational' => 'Alerts store operators about',
            'reminder' => 'Reminds learners or buyers about',
            'dunning' => 'Helps recover failed or pending payment for',
            'celebratory' => 'Recognises learner progress for',
            default => 'Sends the customer-facing ecommerce email for',
        };

        return $prefix . ' ' . $purpose . '.';
    }

    /**
     * Icon for a seeded template.
     *
     * @param string $key Template key.
     * @param string $category Notification category.
     * @return string
     */
    private static function icon(string $key, string $category): string {
        if (str_contains($key, 'invoice')) {
            return 'bi-receipt-cutoff';
        }
        if (str_contains($key, 'subscription') || str_contains($key, 'modernsubscription')) {
            return 'bi-arrow-repeat';
        }
        if (str_contains($key, 'key')) {
            return 'bi-key';
        }
        if (str_contains($key, 'cart') || str_contains($key, 'checkout')) {
            return 'bi-cart';
        }
        if (str_contains($key, 'payment')) {
            return 'bi-credit-card';
        }
        if (str_contains($key, 'refund')) {
            return 'bi-arrow-counterclockwise';
        }
        if (str_contains($key, 'certificate')) {
            return 'bi-award';
        }
        if (str_contains($key, 'access')) {
            return 'bi-unlock';
        }
        if (str_starts_with($key, 'ops_')) {
            return 'bi-speedometer2';
        }

        return match ($category) {
            'marketing' => 'bi-megaphone',
            'operational' => 'bi-activity',
            'reminder' => 'bi-clock-history',
            'dunning' => 'bi-credit-card-2-back',
            'celebratory' => 'bi-stars',
            default => 'bi-envelope',
        };
    }

    /**
     * Accent colour key.
     *
     * @param string $key Template key.
     * @param string $category Notification category.
     * @return string
     */
    private static function color(string $key, string $category): string {
        if (str_contains($key, 'failed') || str_contains($key, 'overdue') || str_contains($key, 'expired')) {
            return 'danger';
        }
        if (str_contains($key, 'paid') || str_contains($key, 'success') || str_contains($key, 'activated')) {
            return 'success';
        }
        if (str_contains($key, 'reminder') || str_contains($key, 'expiring') || str_contains($key, 'due')) {
            return 'warning';
        }

        return match ($category) {
            'marketing' => 'primary',
            'operational' => 'secondary',
            'reminder' => 'warning',
            'dunning' => 'danger',
            'celebratory' => 'success',
            default => 'info',
        };
    }

    /**
     * Business group for the admin filter.
     *
     * @param string $key Template key.
     * @return string
     */
    private static function group_key(string $key): string {
        if (str_starts_with($key, 'ops_')) {
            return 'operational_admin';
        }
        if (str_contains($key, 'invoice')) {
            return 'invoice';
        }
        if (str_contains($key, 'modernsubscription')) {
            return 'subscriptions';
        }
        if (str_contains($key, 'key')) {
            return 'keys';
        }
        if (
            str_contains($key, 'access')
            || str_contains($key, 'enrollment')
            || str_contains($key, 'course_completed')
            || str_contains($key, 'program_completed')
            || str_contains($key, 'certificate')
        ) {
            return 'access_learning';
        }
        if (
            str_contains($key, 'back_in_stock')
            || str_contains($key, 'price_drop')
            || str_contains($key, 'promo')
            || str_contains($key, 'new_product')
            || str_contains($key, 'coupon')
        ) {
            return 'marketing_commerce';
        }

        return 'checkout_order';
    }

    /**
     * Display label for a business group.
     *
     * @param string $key Group key.
     * @return string
     */
    private static function group_label(string $key): string {
        return match ($key) {
            'invoice' => 'Invoice',
            'access_learning' => 'Access / Learning',
            'keys' => 'Keys',
            'subscriptions' => 'Subscriptions',
            'operational_admin' => 'Operational/Admin',
            'marketing_commerce' => 'Marketing/Commerce',
            default => 'Core Checkout/Order',
        };
    }

    /**
     * Default enabled state for newly exposed email controls.
     *
     * @param string $category Notification category.
     * @return bool
     */
    private static function enabled_default(string $category): bool {
        return $category !== 'marketing';
    }
}
