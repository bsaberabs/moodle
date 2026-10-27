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
 * Sends subscription lifecycle emails for the trial and billing tasks.
 *
 * The trial and recurring-payment tasks call this with a template type, a user,
 * and a placeholder bag. It resolves the admin's configured template for that
 * type from local_moderncommerce_subscription_emailtpl, honours the enable
 * switch and any custom subject/body override, then hands off to the shared
 * email API. Anything not overridden falls back to the seeded template key.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\subscription\services;

use local_moderncommerce\api\email_api;
use local_moderncommerce\email\placeholder_engine;

/**
 * Template-driven mailer for subscription lifecycle events.
 */
class email_service {
    /**
     * Template type to the seeded email template key it falls back to.
     *
     * Keys match the templates seeded by commerce_seed and the types stored in
     * local_moderncommerce_subscription_emailtpl.
     */
    private const TEMPLATE_MAP = [
        'trial_ending' => 'modernsubscription_trial_ending',
        'trial_converted' => 'modernsubscription_trial_converted',
        'trial_expired' => 'modernsubscription_trial_expired',
        'recurring_renewal_success' => 'modernsubscription_renewal_success',
        'payment_failed' => 'modernsubscription_payment_failed',
        'subscription_suspended_payment' => 'modernsubscription_suspended_payment',
        'upcoming_billing' => 'modernsubscription_upcoming_billing',
        'grace_ending' => 'modernsubscription_grace_ending',
    ];

    /**
     * Send one subscription lifecycle email.
     *
     * @param string $type Template type, e.g. 'trial_ending'.
     * @param object $user Recipient user record.
     * @param array $data Placeholder values for the template.
     * @return bool Whether the message was handed off for delivery.
     */
    public static function send(string $type, $user, array $data = []): bool {
        global $DB;

        if (empty($user) || empty($user->id)) {
            return false;
        }

        $template = $DB->get_record('local_moderncommerce_subscription_emailtpl', ['templatetype' => $type]);

        // An admin can switch any of these off without disabling the task.
        if ($template && isset($template->enabled) && (int) $template->enabled === 0) {
            return false;
        }

        // Global placeholders (site name, support address, logo) always apply.
        $data = array_merge(placeholder_engine::get_global_placeholder_values(), $data);
        if (empty($data['student_fullname'])) {
            $data['student_fullname'] = fullname($user);
        }

        $support = \core_user::get_support_user();

        // Custom subject/body written by the admin wins over the seeded template.
        if ($template && (int) ($template->use_custom_message ?? 0) === 1 && !empty($template->custom_message)) {
            $engine = new placeholder_engine();
            $subject = $engine->substitute_placeholders((string) ($template->custom_subject ?? ''), $data);
            $body = $engine->substitute_placeholders((string) $template->custom_message, $data);

            if (trim($subject) === '') {
                $subject = get_string('subscriptions', 'local_moderncommerce');
            }

            return email_api::send_subject_body($user, $subject, $body, $data, $support);
        }

        $templatekey = !empty($template->template_key)
            ? (string) $template->template_key
            : (self::TEMPLATE_MAP[$type] ?? '');

        if ($templatekey === '') {
            debugging('No subscription email template configured for type: ' . $type, DEBUG_DEVELOPER);
            return false;
        }

        return email_api::send($templatekey, (int) $user->id, $data, $support);
    }
}
