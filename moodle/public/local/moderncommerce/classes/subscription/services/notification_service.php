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

namespace local_moderncommerce\subscription\services;

/**
 * Notification service for Modern Subscription.
 *
 * Sends consolidated emails to avoid per-course notifications.
 *
 * @package    local_moderncommerce
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_service {
    /** Activation template key. */
    const TEMPLATE_ACTIVATION = 'modernsubscription_activation_summary';

    /** Renewal template key. */
    const TEMPLATE_RENEWAL = 'modernsubscription_renewal_digest';

    /** Expiring subscription template key. */
    const TEMPLATE_EXPIRING = 'modernsubscription_expiring_reminder';

    /** Grace-period template key. */
    const TEMPLATE_GRACE_PERIOD = 'modernsubscription_grace_period';

    /** Expired subscription template key. */
    const TEMPLATE_EXPIRED = 'modernsubscription_expired';

    /** Cancelled subscription template key. */
    const TEMPLATE_CANCELLED = 'modernsubscription_cancelled';

    /** Failed payment template key. */
    const TEMPLATE_PAYMENT_FAILED = 'modernsubscription_payment_failed';

    /**
     * Get all template types with metadata.
     *
     * @return array
     */
    public static function get_all_template_types(): array {
        return [
            'activation' => [
                'key' => self::TEMPLATE_ACTIVATION,
                'name' => get_string('activationemail', 'local_moderncommerce'),
                'description' => get_string('activationemail_desc', 'local_moderncommerce'),
            ],
            'renewal' => [
                'key' => self::TEMPLATE_RENEWAL,
                'name' => get_string('renewalemail', 'local_moderncommerce'),
                'description' => get_string('renewalemail_desc', 'local_moderncommerce'),
            ],
            'expiring' => [
                'key' => self::TEMPLATE_EXPIRING,
                'name' => get_string('expiringemail', 'local_moderncommerce'),
                'description' => get_string('expiringemail_desc', 'local_moderncommerce'),
            ],
            'grace_period' => [
                'key' => self::TEMPLATE_GRACE_PERIOD,
                'name' => get_string('graceperiodemail', 'local_moderncommerce'),
                'description' => get_string('graceperiodemail_desc', 'local_moderncommerce'),
            ],
            'expired' => [
                'key' => self::TEMPLATE_EXPIRED,
                'name' => get_string('expiredemail', 'local_moderncommerce'),
                'description' => get_string('expiredemail_desc', 'local_moderncommerce'),
            ],
            'cancelled' => [
                'key' => self::TEMPLATE_CANCELLED,
                'name' => get_string('cancelledemail', 'local_moderncommerce'),
                'description' => get_string('cancelledemail_desc', 'local_moderncommerce'),
            ],
            'payment_failed' => [
                'key' => self::TEMPLATE_PAYMENT_FAILED,
                'name' => get_string('paymentfailedemail', 'local_moderncommerce'),
                'description' => get_string('paymentfailedemail_desc', 'local_moderncommerce'),
            ],
        ];
    }

    /**
     * Get available placeholders for subscription emails.
     *
     * @return array
     */
    public static function get_available_placeholders(): array {
        return [
            // User placeholders.
            '{firstname}' => get_string('placeholder_firstname', 'local_moderncommerce'),
            '{lastname}' => get_string('placeholder_lastname', 'local_moderncommerce'),
            '{fullname}' => get_string('placeholder_fullname', 'local_moderncommerce'),
            '{email}' => get_string('placeholder_email', 'local_moderncommerce'),
            '{username}' => get_string('placeholder_username', 'local_moderncommerce'),
            // Subscription placeholders.
            '{plan_name}' => get_string('placeholder_planname', 'local_moderncommerce'),
            '{billing_cycle}' => get_string('placeholder_billingcycle', 'local_moderncommerce'),
            '{subscription_startdate}' => get_string('placeholder_startdate', 'local_moderncommerce'),
            '{subscription_enddate}' => get_string('placeholder_enddate', 'local_moderncommerce'),
            '{days_remaining}' => get_string('placeholder_daysremaining', 'local_moderncommerce'),
            '{trial_days}' => get_string('placeholder_trialdays', 'local_moderncommerce'),
            '{price}' => get_string('placeholder_price', 'local_moderncommerce'),
            '{currency}' => get_string('placeholder_currency', 'local_moderncommerce'),
            '{courses_list}' => get_string('placeholder_courseslist', 'local_moderncommerce'),
            '{renewal_url}' => get_string('placeholder_renewalurl', 'local_moderncommerce'),
            '{subscription_url}' => get_string('placeholder_subscriptionurl', 'local_moderncommerce'),
            // Global placeholders.
            '{sitename}' => get_string('placeholder_sitename', 'local_moderncommerce'),
            '{siteurl}' => get_string('placeholder_siteurl', 'local_moderncommerce'),
            '{logo}' => get_string('placeholder_logo', 'local_moderncommerce'),
            '{logo_compact}' => get_string('placeholder_logocompact', 'local_moderncommerce'),
            '{supportemail}' => get_string('placeholder_supportemail', 'local_moderncommerce'),
        ];
    }

    /**
     * Ensure default templates exist so admins can customize later.
     */
    public static function ensure_default_templates(): void {
        $manager = new \local_moderncommerce\email\template_manager();

        // Activation summary.
        if (!$manager->template_exists(self::TEMPLATE_ACTIVATION)) {
            $subject = self::template_string(self::TEMPLATE_ACTIVATION, 'subject');
            $body = self::template_string(self::TEMPLATE_ACTIVATION, 'body');

            $template = (object) [
                'template_key' => self::TEMPLATE_ACTIVATION,
                'component' => 'local_moderncommerce',
                'name' => self::template_string(self::TEMPLATE_ACTIVATION, 'name'),
                'description' => self::template_string(self::TEMPLATE_ACTIVATION, 'description'),
                'subject' => $subject,
                'body' => $body,
                'format' => 'html',
                'status' => 'active',
                'placeholders' => [
                    '{sitename}', '{logo}', '{emailsummary_greeting}', '{emailsummary_intro}', '{courses_list}',
                    '{plan_name}', '{billing_cycle}', '{trial_days}', '{subscription_enddate}', '{emailsummary_footer}',
                ],
            ];
            try {
                $manager->create_template($template);
            } catch (\Exception $e) {
                debugging('Failed to create default activation template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Renewal digest.
        if (!$manager->template_exists(self::TEMPLATE_RENEWAL)) {
            $subject = self::template_string(self::TEMPLATE_RENEWAL, 'subject');
            $body = self::template_string(self::TEMPLATE_RENEWAL, 'body');

            $template = (object) [
                'template_key' => self::TEMPLATE_RENEWAL,
                'component' => 'local_moderncommerce',
                'name' => self::template_string(self::TEMPLATE_RENEWAL, 'name'),
                'description' => self::template_string(self::TEMPLATE_RENEWAL, 'description'),
                'subject' => $subject,
                'body' => $body,
                'format' => 'html',
                'status' => 'active',
                'placeholders' => [
                    '{sitename}', '{logo}', '{student_fullname}', '{plan_name}', '{billing_cycle}',
                    '{subscription_enddate}', '{courses_list}',
                ],
            ];
            try {
                $manager->create_template($template);
            } catch (\Exception $e) {
                debugging('Failed to create default renewal template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Expiring reminder.
        if (!$manager->template_exists(self::TEMPLATE_EXPIRING)) {
            $subject = self::template_string(self::TEMPLATE_EXPIRING, 'subject');
            $body = self::template_string(self::TEMPLATE_EXPIRING, 'body');

            $template = (object) [
                'template_key' => self::TEMPLATE_EXPIRING,
                'component' => 'local_moderncommerce',
                'name' => self::template_string(self::TEMPLATE_EXPIRING, 'name'),
                'description' => self::template_string(self::TEMPLATE_EXPIRING, 'description'),
                'subject' => $subject,
                'body' => $body,
                'format' => 'html',
                'status' => 'active',
                'placeholders' => [
                    '{sitename}', '{logo}', '{fullname}', '{plan_name}', '{days_remaining}',
                    '{subscription_enddate}', '{renewal_url}', '{courses_list}',
                ],
            ];
            try {
                $manager->create_template($template);
            } catch (\Exception $e) {
                debugging('Failed to create default expiring template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Grace period warning.
        if (!$manager->template_exists(self::TEMPLATE_GRACE_PERIOD)) {
            $subject = self::template_string(self::TEMPLATE_GRACE_PERIOD, 'subject');
            $body = self::template_string(self::TEMPLATE_GRACE_PERIOD, 'body');

            $template = (object) [
                'template_key' => self::TEMPLATE_GRACE_PERIOD,
                'component' => 'local_moderncommerce',
                'name' => self::template_string(self::TEMPLATE_GRACE_PERIOD, 'name'),
                'description' => self::template_string(self::TEMPLATE_GRACE_PERIOD, 'description'),
                'subject' => $subject,
                'body' => $body,
                'format' => 'html',
                'status' => 'active',
                'placeholders' => [
                    '{sitename}', '{logo}', '{fullname}', '{plan_name}', '{days_remaining}',
                    '{renewal_url}', '{courses_list}',
                ],
            ];
            try {
                $manager->create_template($template);
            } catch (\Exception $e) {
                debugging('Failed to create default grace period template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Subscription expired.
        if (!$manager->template_exists(self::TEMPLATE_EXPIRED)) {
            $subject = self::template_string(self::TEMPLATE_EXPIRED, 'subject');
            $body = self::template_string(self::TEMPLATE_EXPIRED, 'body');

            $template = (object) [
                'template_key' => self::TEMPLATE_EXPIRED,
                'component' => 'local_moderncommerce',
                'name' => self::template_string(self::TEMPLATE_EXPIRED, 'name'),
                'description' => self::template_string(self::TEMPLATE_EXPIRED, 'description'),
                'subject' => $subject,
                'body' => $body,
                'format' => 'html',
                'status' => 'active',
                'placeholders' => [
                    '{sitename}', '{logo}', '{fullname}', '{plan_name}', '{courses_list}', '{subscription_url}',
                ],
            ];
            try {
                $manager->create_template($template);
            } catch (\Exception $e) {
                debugging('Failed to create default expired template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Subscription cancelled.
        if (!$manager->template_exists(self::TEMPLATE_CANCELLED)) {
            $subject = self::template_string(self::TEMPLATE_CANCELLED, 'subject');
            $body = self::template_string(self::TEMPLATE_CANCELLED, 'body');

            $template = (object) [
                'template_key' => self::TEMPLATE_CANCELLED,
                'component' => 'local_moderncommerce',
                'name' => self::template_string(self::TEMPLATE_CANCELLED, 'name'),
                'description' => self::template_string(self::TEMPLATE_CANCELLED, 'description'),
                'subject' => $subject,
                'body' => $body,
                'format' => 'html',
                'status' => 'active',
                'placeholders' => [
                    '{sitename}', '{logo}', '{fullname}', '{plan_name}', '{subscription_enddate}',
                    '{courses_list}', '{subscription_url}',
                ],
            ];
            try {
                $manager->create_template($template);
            } catch (\Exception $e) {
                debugging('Failed to create default cancelled template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Payment failed.
        if (!$manager->template_exists(self::TEMPLATE_PAYMENT_FAILED)) {
            $subject = self::template_string(self::TEMPLATE_PAYMENT_FAILED, 'subject');
            $body = self::template_string(self::TEMPLATE_PAYMENT_FAILED, 'body');

            $template = (object) [
                'template_key' => self::TEMPLATE_PAYMENT_FAILED,
                'component' => 'local_moderncommerce',
                'name' => self::template_string(self::TEMPLATE_PAYMENT_FAILED, 'name'),
                'description' => self::template_string(self::TEMPLATE_PAYMENT_FAILED, 'description'),
                'subject' => $subject,
                'body' => $body,
                'format' => 'html',
                'status' => 'active',
                'placeholders' => [
                    '{sitename}', '{logo}', '{fullname}', '{plan_name}', '{currency}', '{price}',
                    '{subscription_url}', '{supportemail}',
                ],
            ];
            try {
                $manager->create_template($template);
            } catch (\Exception $e) {
                debugging('Failed to create default payment failed template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Ensure local email template records exist for all types.
        self::ensure_local_email_records();
    }

    /**
     * Ensure local email template records exist in local_moderncommerce_subscription_emailtpl.
     */
    public static function ensure_local_email_records(): void {
        global $DB;

        $types = self::get_all_template_types();
        $now = time();

        foreach ($types as $templatetype => $info) {
            if (!$DB->record_exists('local_moderncommerce_subscription_emailtpl', ['templatetype' => $templatetype])) {
                $record = new \stdClass();
                $record->templatetype = $templatetype;
                $record->enabled = 1;
                $record->template_key = $info['key'];
                $record->use_custom_message = 0;
                $record->custom_subject = null;
                $record->custom_message = null;
                $record->timecreated = $now;
                $record->timemodified = $now;
                $DB->insert_record('local_moderncommerce_subscription_emailtpl', $record);
            }
        }
    }

    /**
     * Send a consolidated activation summary email listing accessible courses.
     *
     * @param int $userid User ID.
     * @param int $subscriptionid Subscription ID.
     * @param int[] $courseids List of course IDs user just gained access to.
     * @return bool Success.
     */
    public static function send_activation_summary(int $userid, int $subscriptionid, array $courseids): bool {
        global $DB;

        // Load local template configuration.
        $localtpl = $DB->get_record('local_moderncommerce_subscription_emailtpl', ['templatetype' => 'activation']);
        if ($localtpl && !$localtpl->enabled) {
            return false;
        }
        // Global toggle still respected if set to 0.
        if ((int)(get_config('local_moderncommerce', 'send_activation_summary_email') ?? 1) === 0) {
            return false;
        }

        self::ensure_default_templates();

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);

        // Build courses list as a single placeholder string.
        $courselist = '';
        if (!empty($courseids)) {
            $courses = $DB->get_records_list('course', 'id', $courseids, 'fullname ASC', 'id, fullname');
            foreach ($courses as $c) {
                $courselist .= "- " . format_string($c->fullname) . "\n";
            }
        }

        $globals = \local_moderncommerce\email\placeholder_engine::get_global_placeholder_values();
        $data = array_merge($globals, [
            'emailsummary_greeting' => get_string('emailsummary_greeting', 'local_moderncommerce', fullname($user)),
            'emailsummary_intro' => $courselist
                ? get_string('emailsummary_intro', 'local_moderncommerce')
                : get_string('emailsummary_nocourses', 'local_moderncommerce'),
            'emailsummary_footer' => get_string('emailsummary_footer', 'local_moderncommerce'),
            'student_fullname' => fullname($user),
            'plan_name' => format_string($plan->name),
            'billing_cycle' => $plan->billing_cycle,
            'trial_days' => (int)($plan->trial_days ?? 0),
            'subscription_enddate' => userdate($subscription->end_date),
            'courses_list' => $courselist,
        ]);
        // If local record requests custom message, render placeholders directly.
        if ($localtpl && (int)$localtpl->use_custom_message === 1 && !empty($localtpl->custom_message)) {
            $engine = new \local_moderncommerce\email\placeholder_engine();
            $subject = $engine->substitute_placeholders(
                $localtpl->custom_subject ?? get_string('emailsummary_subject', 'local_moderncommerce'),
                $data
            );
            $body = $engine->substitute_placeholders($localtpl->custom_message, $data);
            return \local_moderncommerce\api\email_api::send_subject_body(
                $user,
                $subject,
                $body,
                $data,
                \core_user::get_support_user()
            );
        }

        // Route through the notification hub when installed (in-app + email + audit).
        if (
            self::dispatch_subscription(
                'subscription_activated',
                'transactional',
                'modernsubscription_activated',
                $user,
                $subscription,
                $plan,
                (int) $subscriptionid,
                $data
            )
        ) {
            return true;
        }

        // Otherwise, use selected template key.
        $selectedkey = $localtpl && !empty($localtpl->template_key) ? $localtpl->template_key : null;
        $templatekey = $selectedkey ?: (get_config('local_moderncommerce', 'activation_template_key') ?: self::TEMPLATE_ACTIVATION);
        return \local_moderncommerce\api\email_api::send($templatekey, (int) $user->id, $data, \core_user::get_support_user());
    }

    /**
     * Send a subscription renewal digest email using templates.
     *
     * @param int $userid User ID
     * @param int $subscriptionid Subscription ID
     * @return bool
     */
    public static function send_renewal_digest(int $userid, int $subscriptionid): bool {
        global $DB;

        // Load local template configuration.
        $localtpl = $DB->get_record('local_moderncommerce_subscription_emailtpl', ['templatetype' => 'renewal']);
        if ($localtpl && !$localtpl->enabled) {
            return false;
        }
        if ((int)(get_config('local_moderncommerce', 'send_renewal_digest_email') ?? 1) === 0) {
            return false;
        }

        self::ensure_default_templates();

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);

        // Get current accessible courses for this user.
        $courseids = access_service::get_user_accessible_courses($userid);
        $courselist = '';
        if (!empty($courseids)) {
            $courses = $DB->get_records_list('course', 'id', $courseids, 'fullname ASC', 'id, fullname');
            foreach ($courses as $c) {
                $courselist .= "- " . format_string($c->fullname) . "\n";
            }
        }

        $globals = \local_moderncommerce\email\placeholder_engine::get_global_placeholder_values();
        $data = array_merge($globals, [
            'student_fullname' => fullname($user),
            'plan_name' => format_string($plan->name),
            'billing_cycle' => $plan->billing_cycle,
            'subscription_enddate' => userdate($subscription->end_date),
            'courses_list' => $courselist,
        ]);
        if ($localtpl && (int)$localtpl->use_custom_message === 1 && !empty($localtpl->custom_message)) {
            $engine = new \local_moderncommerce\email\placeholder_engine();
            $subject = $engine->substitute_placeholders(
                $localtpl->custom_subject ?? get_string('emailsummary_subject', 'local_moderncommerce'),
                $data
            );
            $body = $engine->substitute_placeholders($localtpl->custom_message, $data);
            return \local_moderncommerce\api\email_api::send_subject_body(
                $user,
                $subject,
                $body,
                $data,
                \core_user::get_support_user()
            );
        }

        // Route through the notification hub when installed (in-app + email + audit).
        if (
            self::dispatch_subscription(
                'renewal_succeeded',
                'transactional',
                'modernsubscription_renewal_success',
                $user,
                $subscription,
                $plan,
                (int) $subscriptionid,
                $data
            )
        ) {
            return true;
        }

        $selectedkey = $localtpl && !empty($localtpl->template_key) ? $localtpl->template_key : null;
        $templatekey = $selectedkey ?: (get_config('local_moderncommerce', 'renewal_template_key') ?: self::TEMPLATE_RENEWAL);
        return \local_moderncommerce\api\email_api::send($templatekey, (int) $user->id, $data, \core_user::get_support_user());
    }

    /**
     * Send expiring subscription reminder email.
     *
     * @param int $userid User ID
     * @param int $subscriptionid Subscription ID
     * @param int $daysremaining Days until expiration
     * @return bool
     */
    public static function send_expiring_reminder(int $userid, int $subscriptionid, int $daysremaining): bool {
        global $DB;

        $localtpl = $DB->get_record('local_moderncommerce_subscription_emailtpl', ['templatetype' => 'expiring']);
        if ($localtpl && !$localtpl->enabled) {
            return false;
        }

        self::ensure_default_templates();

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);

        $courseids = access_service::get_user_accessible_courses($userid);
        $courselist = self::build_course_list($courseids);

        $data = self::build_placeholder_data($user, $subscription, $plan, $courselist);
        $data['days_remaining'] = $daysremaining;
        $data['renewal_url'] = (new \moodle_url('/local/moderncommerce/learner/subscription.php', [
            'id' => $subscriptionid,
        ]))->out(false);

        if (
            !($localtpl && (int) $localtpl->use_custom_message === 1)
                && self::dispatch_subscription(
                    'renewal_reminder',
                    'reminder',
                    'modernsubscription_renewal_reminder',
                    $user,
                    $subscription,
                    $plan,
                    (int) $subscriptionid,
                    $data
                )
        ) {
            return true;
        }

        return self::send_email('expiring', $user, $data, $localtpl);
    }

    /**
     * Send grace period warning email.
     *
     * @param int $userid User ID
     * @param int $subscriptionid Subscription ID
     * @param int $daysremaining Days left in grace period
     * @return bool
     */
    public static function send_grace_period_warning(int $userid, int $subscriptionid, int $daysremaining): bool {
        global $DB;

        $localtpl = $DB->get_record('local_moderncommerce_subscription_emailtpl', ['templatetype' => 'grace_period']);
        if ($localtpl && !$localtpl->enabled) {
            return false;
        }

        self::ensure_default_templates();

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);

        $courseids = access_service::get_user_accessible_courses($userid);
        $courselist = self::build_course_list($courseids);

        $data = self::build_placeholder_data($user, $subscription, $plan, $courselist);
        $data['days_remaining'] = $daysremaining;
        $data['renewal_url'] = (new \moodle_url('/local/moderncommerce/learner/subscription.php', [
            'id' => $subscriptionid,
        ]))->out(false);

        if (
            !($localtpl && (int) $localtpl->use_custom_message === 1)
                && self::dispatch_subscription(
                    'grace_warning',
                    'reminder',
                    'modernsubscription_grace_started',
                    $user,
                    $subscription,
                    $plan,
                    (int) $subscriptionid,
                    $data
                )
        ) {
            return true;
        }

        return self::send_email('grace_period', $user, $data, $localtpl);
    }

    /**
     * Send subscription expired email.
     *
     * @param int $userid User ID
     * @param int $subscriptionid Subscription ID
     * @return bool
     */
    public static function send_expired_notice(int $userid, int $subscriptionid): bool {
        global $DB;

        $localtpl = $DB->get_record('local_moderncommerce_subscription_emailtpl', ['templatetype' => 'expired']);
        if ($localtpl && !$localtpl->enabled) {
            return false;
        }

        self::ensure_default_templates();

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);

        // Get courses user had access to (from access records).
        $courseids = $DB->get_fieldset_select(
            'local_moderncommerce_subscription_access',
            'courseid',
            'subscriptionid = :subid',
            ['subid' => $subscriptionid]
        );
        $courselist = self::build_course_list($courseids);

        $data = self::build_placeholder_data($user, $subscription, $plan, $courselist);
        $data['subscription_url'] = (new \moodle_url('/local/moderncommerce/learner/index.php'))->out(false)
            . '#/library';

        if (
            !($localtpl && (int) $localtpl->use_custom_message === 1)
                && self::dispatch_subscription(
                    'subscription_expired',
                    'transactional',
                    'modernsubscription_expired',
                    $user,
                    $subscription,
                    $plan,
                    (int) $subscriptionid,
                    $data
                )
        ) {
            return true;
        }

        return self::send_email('expired', $user, $data, $localtpl);
    }

    /**
     * Send subscription cancelled email.
     *
     * @param int $userid User ID
     * @param int $subscriptionid Subscription ID
     * @return bool
     */
    public static function send_cancelled_notice(int $userid, int $subscriptionid): bool {
        global $DB;

        // Operational churn alert to admins — independent of the learner email setting.
        self::notify_admins_churn($userid, $subscriptionid);

        $localtpl = $DB->get_record('local_moderncommerce_subscription_emailtpl', ['templatetype' => 'cancelled']);
        if ($localtpl && !$localtpl->enabled) {
            return false;
        }

        self::ensure_default_templates();

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);

        $courseids = access_service::get_user_accessible_courses($userid);
        $courselist = self::build_course_list($courseids);

        $data = self::build_placeholder_data($user, $subscription, $plan, $courselist);
        $data['subscription_url'] = (new \moodle_url('/local/moderncommerce/learner/index.php'))->out(false)
            . '#/library';

        if (
            !($localtpl && (int) $localtpl->use_custom_message === 1)
                && self::dispatch_subscription(
                    'subscription_cancelled',
                    'transactional',
                    'modernsubscription_cancelled',
                    $user,
                    $subscription,
                    $plan,
                    (int) $subscriptionid,
                    $data
                )
        ) {
            return true;
        }

        return self::send_email('cancelled', $user, $data, $localtpl);
    }

    /**
     * Send payment failed email.
     *
     * @param int $userid User ID
     * @param int $subscriptionid Subscription ID
     * @return bool
     */
    public static function send_payment_failed_notice(int $userid, int $subscriptionid): bool {
        global $DB;

        $localtpl = $DB->get_record('local_moderncommerce_subscription_emailtpl', ['templatetype' => 'payment_failed']);
        if ($localtpl && !$localtpl->enabled) {
            return false;
        }

        self::ensure_default_templates();

        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid], '*', MUST_EXIST);

        $data = self::build_placeholder_data($user, $subscription, $plan, '');
        $data['subscription_url'] = (new \moodle_url('/local/moderncommerce/learner/subscription.php'))->out(false);

        if (
            !($localtpl && (int) $localtpl->use_custom_message === 1)
                && self::dispatch_subscription(
                    'payment_failed',
                    'dunning',
                    'modernsubscription_payment_failed',
                    $user,
                    $subscription,
                    $plan,
                    (int) $subscriptionid,
                    $data
                )
        ) {
            return true;
        }

        return self::send_email('payment_failed', $user, $data, $localtpl);
    }

    /**
     * Route a subscription notification through the Modern Commerce notification subsystem.
     *
     * Reuses the caller's already-built placeholder data and augments it with the
     * new-template token vocabulary (plan_price, *_url, dates). Returns true when the
     * hub accepted it (caller should stop); false when the hub is absent (caller
     * falls back to the legacy email path).
     *
     * @param string $eventkey Canonical event key.
     * @param string $category Notification category.
     * @param string $templatekey Email template key.
     * @param object $user Recipient user.
     * @param object $subscription Subscription record.
     * @param object $plan Plan record.
     * @param int $subscriptionid Subscription id.
     * @param array $data Caller's placeholder data.
     * @return bool
     */
    private static function dispatch_subscription(
        string $eventkey,
        string $category,
        string $templatekey,
        $user,
        $subscription,
        $plan,
        int $subscriptionid,
        array $data
    ): bool {
        $suburl = (new \moodle_url('/local/moderncommerce/learner/subscription.php', ['id' => $subscriptionid]))->out(false);
        $price = (float) ($plan->price ?? 0);
        if (class_exists('\local_moderncommerce\services\pricing_service')) {
            $planprice = \local_moderncommerce\services\pricing_service::format_price($price);
        } else {
            $planprice = trim(($plan->currency ?? '') . ' ' . number_format($price, 2));
        }

        $tokens = [
            'plan_name' => format_string($plan->name),
            'plan_price' => $planprice,
            'billing_cycle' => $plan->billing_cycle,
            'subscription_enddate' => userdate($subscription->end_date),
            'next_billing_date' => !empty($subscription->next_billing_date)
                ? userdate($subscription->next_billing_date) : userdate($subscription->end_date),
            'trial_end_date' => !empty($subscription->trial_end_date) ? userdate($subscription->trial_end_date) : '',
            'trial_days' => (int) ($plan->trial_days ?? 0),
            'my_subscription_url' => $suburl,
            'renewal_url' => $suburl,
            'reactivate_url' => $suburl,
            'update_payment_url' => $suburl,
        ];

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', $eventkey))
            ->category($category)
            ->template($templatekey)
            ->to_user((int) $user->id)
            ->placeholders(array_merge($data, $tokens))
            ->context_url($suburl)
            ->related($subscriptionid);

        \local_moderncommerce\notifications\api::notify($notification);
        return true;
    }

    /**
     * Send the churn (cancellation) operational alert to store admins via the hub.
     *
     * @param int $userid User id.
     * @param int $subscriptionid Subscription id.
     * @return void
     */
    private static function notify_admins_churn(int $userid, int $subscriptionid): void {
        global $DB, $CFG;

        $subscription = $DB->get_record('local_moderncommerce_user_subscriptions', ['id' => $subscriptionid]);
        $plan = $subscription ? $DB->get_record('local_moderncommerce_subscription_plans', ['id' => $subscription->planid]) : null;
        $user = $DB->get_record('user', ['id' => $userid]);
        $churncount = $DB->count_records('local_moderncommerce_user_subscriptions', ['status' => 'cancelled']);
        $dashurl = $CFG->wwwroot . '/local/moderncommerce/index.php';

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'churn'))
            ->category('operational')
            ->template('ops_churn_alert')
            ->placeholders([
                'customer_name' => $user ? fullname($user) : '',
                'churned_plan' => $plan ? format_string($plan->name) : '',
                'churn_count' => $churncount,
                'period_label' => 'to date',
                'admin_dashboard_url' => $dashurl,
            ])
            ->context_url($dashurl)
            ->related($subscriptionid);

        \local_moderncommerce\notifications\api::notify_admins($notification);
    }

    /**
     * Build a formatted course list string.
     *
     * @param array $courseids
     * @return string
     */
    private static function build_course_list(array $courseids): string {
        global $DB;

        if (empty($courseids)) {
            return '';
        }

        $courses = $DB->get_records_list('course', 'id', $courseids, 'fullname ASC', 'id, fullname');
        $list = '';
        foreach ($courses as $c) {
            $list .= "- " . format_string($c->fullname) . "\n";
        }
        return $list;
    }

    /**
     * Build common placeholder data for emails.
     *
     * @param object $user
     * @param object $subscription
     * @param object $plan
     * @param string $courselist
     * @return array
     */
    private static function build_placeholder_data($user, $subscription, $plan, string $courselist): array {
        $globals = \local_moderncommerce\email\placeholder_engine::get_global_placeholder_values();

        return array_merge($globals, [
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'fullname' => fullname($user),
            'email' => $user->email,
            'username' => $user->username,
            'student_fullname' => fullname($user),
            'plan_name' => format_string($plan->name),
            'billing_cycle' => $plan->billing_cycle,
            'subscription_startdate' => userdate($subscription->start_date),
            'subscription_enddate' => userdate($subscription->end_date),
            'trial_days' => (int)($plan->trial_days ?? 0),
            'price' => number_format((float)$plan->price, 2),
            'currency' => $plan->currency ?? 'USD',
            'courses_list' => $courselist,
        ]);
    }

    /**
     * Send an email using template or custom content.
     *
     * @param string $templatetype
     * @param object $user
     * @param array $data
     * @param object|null $localtpl
     * @return bool
     */
    private static function send_email(string $templatetype, $user, array $data, $localtpl = null): bool {
        $types = self::get_all_template_types();
        $defaultkey = $types[$templatetype]['key'] ?? null;

        if ($localtpl && (int)$localtpl->use_custom_message === 1 && !empty($localtpl->custom_message)) {
            $engine = new \local_moderncommerce\email\placeholder_engine();
            $subject = $engine->substitute_placeholders($localtpl->custom_subject ?? '', $data);
            $body = $engine->substitute_placeholders($localtpl->custom_message, $data);
            return \local_moderncommerce\api\email_api::send_subject_body(
                $user,
                $subject,
                $body,
                $data,
                \core_user::get_support_user()
            );
        }

        $selectedkey = $localtpl && !empty($localtpl->template_key) ? $localtpl->template_key : null;
        $templatekey = $selectedkey ?: $defaultkey;

        if (!$templatekey) {
            return false;
        }

        return \local_moderncommerce\api\email_api::send($templatekey, (int) $user->id, $data, \core_user::get_support_user());
    }

    /**
     * Restore the default content for core subscription templates.
     * Overwrites existing global templates for activation and renewal.
     */
    public static function restore_default_templates(): void {
        $manager = new \local_moderncommerce\email\template_manager();

        // Activation defaults.
        $activationsubject = self::template_string(self::TEMPLATE_ACTIVATION, 'subject');
        $activationbody = self::template_string(self::TEMPLATE_ACTIVATION, 'body');
        $activationplaceholders = [
            '{sitename}', '{logo}', '{emailsummary_greeting}', '{emailsummary_intro}', '{courses_list}',
            '{plan_name}', '{billing_cycle}', '{trial_days}', '{subscription_enddate}', '{emailsummary_footer}',
        ];
        $activation = $manager->get_template(self::TEMPLATE_ACTIVATION);
        if ($activation && !empty($activation->id)) {
            try {
                $manager->update_template((int)$activation->id, (object)[
                    'subject' => $activationsubject,
                    'body' => $activationbody,
                    'placeholders' => $activationplaceholders,
                ]);
            } catch (\Exception $e) {
                debugging('Failed to restore activation template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Renewal defaults.
        $renewalsubject = self::template_string(self::TEMPLATE_RENEWAL, 'subject');
        $renewalbody = self::template_string(self::TEMPLATE_RENEWAL, 'body');
        $renewalplaceholders = [
            '{sitename}', '{logo}', '{student_fullname}', '{plan_name}', '{billing_cycle}',
            '{subscription_enddate}', '{courses_list}',
        ];
        $renewal = $manager->get_template(self::TEMPLATE_RENEWAL);
        if ($renewal && !empty($renewal->id)) {
            try {
                $manager->update_template((int)$renewal->id, (object)[
                    'subject' => $renewalsubject,
                    'body' => $renewalbody,
                    'placeholders' => $renewalplaceholders,
                ]);
            } catch (\Exception $e) {
                debugging('Failed to restore renewal template: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Read a default subscription template field from the component language strings.
     *
     * @param string $templatekey Template key.
     * @param string $field Template field.
     * @return string
     */
    private static function template_string(string $templatekey, string $field): string {
        return get_string(
            'subscriptiontpl_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($templatekey)) . '_' . $field,
            'local_moderncommerce'
        );
    }
}
