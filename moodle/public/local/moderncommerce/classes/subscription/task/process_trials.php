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

namespace local_moderncommerce\subscription\task;

use local_moderncommerce\subscription\services\subscription_service;
use local_moderncommerce\subscription\services\email_service;

/**
 * Task to process trial period expirations.
 *
 * This task handles:
 * - Converting trials to active subscriptions (if auto-convert enabled and payment method exists)
 * - Expiring trials that have ended
 * - Sending trial ending notifications
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemmo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_trials extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:processtrials', 'local_moderncommerce');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        // Check if trials are enabled.
        if (!get_config('local_moderncommerce', 'enable_trials')) {
            mtrace('Trial periods are disabled. Skipping.');
            return;
        }

        mtrace('Processing trial subscriptions...');

        // Send warnings for trials ending soon.
        $this->send_trial_ending_warnings();

        // Process expired trials.
        $this->process_expired_trials();

        mtrace('Trial processing complete.');
    }

    /**
     * Send warnings for trials ending soon (1 day before).
     */
    private function send_trial_ending_warnings(): void {
        global $DB;

        $now = time();
        $warningtime = $now + DAYSECS; // 1 day from now.

        // Get trial subscriptions ending within 24 hours that haven't been warned.
        $sql = "SELECT s.*, u.id as userid, u.email, u.firstname, u.lastname,
                       p.name AS plan_name, p.price, p.billing_cycle
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {user} u ON u.id = s.userid
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE s.status = 'trial'
                  AND s.trial_end_date > :now
                  AND s.trial_end_date <= :warningtime
                  AND (s.trial_warning_sent IS NULL OR s.trial_warning_sent = 0)";

        $trials = $DB->get_records_sql($sql, [
            'now' => $now,
            'warningtime' => $warningtime,
        ]);

        mtrace("Found " . count($trials) . " trials ending soon to warn.");

        foreach ($trials as $trial) {
            try {
                // Only flag the trial once the mail actually went out. Setting it
                // regardless would permanently suppress the warning for anyone
                // processed while the mailer was unavailable.
                if ($this->send_trial_ending_email($trial)) {
                    $DB->set_field('local_moderncommerce_user_subscriptions', 'trial_warning_sent', 1, ['id' => $trial->id]);
                    mtrace("Sent trial ending warning for subscription {$trial->id}.");
                } else {
                    mtrace("Trial ending warning not sent for subscription {$trial->id}; will retry next run.");
                }
            } catch (\Exception $e) {
                mtrace("Error sending trial warning for subscription {$trial->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process trials that have expired.
     */
    private function process_expired_trials(): void {
        global $DB;

        $now = time();
        $autoconvert = get_config('local_moderncommerce', 'trial_auto_convert');

        // Get expired trials.
        $sql = "SELECT s.*, p.name AS plan_name, p.price, p.billing_cycle, p.grace_period_days
                FROM {local_moderncommerce_user_subscriptions} s
                JOIN {local_moderncommerce_subscription_plans} p ON p.id = s.planid
                WHERE s.status = 'trial'
                  AND s.trial_end_date <= :now";

        $trials = $DB->get_records_sql($sql, ['now' => $now]);

        mtrace("Found " . count($trials) . " expired trials to process.");

        foreach ($trials as $trial) {
            try {
                if ($autoconvert && $this->has_valid_payment_method($trial->userid)) {
                    // Auto-convert to paid subscription.
                    $this->convert_trial_to_paid($trial);
                    mtrace("Trial subscription {$trial->id} converted to paid.");
                } else {
                    // Trial expired without conversion.
                    $this->expire_trial($trial);
                    mtrace("Trial subscription {$trial->id} expired.");
                }
            } catch (\Exception $e) {
                mtrace("Error processing trial {$trial->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if user has a valid stored payment method.
     *
     * @param int $userid User ID.
     * @return bool Whether user has a valid payment method.
     */
    private function has_valid_payment_method(int $userid): bool {
        global $DB;

        // Check for stored Stripe customer/payment method.
        $stripepmid = $DB->get_field('local_moderncommerce_user_subscriptions', 'stripe_payment_method_id', [
            'userid' => $userid,
        ]);

        if (!empty($stripepmid)) {
            return true;
        }

        // Check for PayPal billing agreement.
        $paypalid = $DB->get_field('local_moderncommerce_user_subscriptions', 'paypal_subscription_id', [
            'userid' => $userid,
        ]);

        if (!empty($paypalid)) {
            return true;
        }

        return false;
    }

    /**
     * Convert a trial subscription to paid.
     *
     * @param object $trial Trial subscription record.
     */
    private function convert_trial_to_paid(object $trial): void {
        global $DB;

        $now = time();
        $duration = subscription_service::get_cycle_duration($trial->billing_cycle);

        // Update subscription to active.
        $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
            'id' => $trial->id,
            'status' => 'active',
            'start_date' => $now,
            'end_date' => $now + $duration,
            'trial_end_date' => null,
            'timemodified' => $now,
        ]);

        // Log the conversion.
        $logdata = [
            'subscriptionid' => $trial->id,
            'userid' => $trial->userid,
            'planid' => $trial->planid,
            'action' => 'trial_converted',
            'details' => json_encode([
                'from' => 'trial',
                'to' => 'active',
            ]),
            'timecreated' => $now,
        ];
        $DB->insert_record('local_moderncommerce_subscription_log', $logdata);

        // Send conversion notification.
        $this->send_trial_converted_email($trial);

        // Recurring payments are handled by the dedicated recurring-payment task.
    }

    /**
     * Expire a trial subscription.
     *
     * @param object $trial Trial subscription record.
     */
    private function expire_trial(object $trial): void {
        global $DB;

        $now = time();

        if ($trial->grace_period_days > 0) {
            // Enter grace period.
            subscription_service::enter_grace_period($trial->id);
        } else {
            // Expire immediately.
            $DB->update_record('local_moderncommerce_user_subscriptions', (object)[
                'id' => $trial->id,
                'status' => 'expired',
                'timemodified' => $now,
            ]);

            // Revoke access.
            \local_moderncommerce\subscription\services\access_service::revoke_plan_access(
                $trial->userid,
                $trial->planid,
                $trial->id
            );

            // Trigger expired event.
            $event = \local_moderncommerce\event\subscription_expired::create([
                'context' => \context_system::instance(),
                'objectid' => $trial->id,
                'relateduserid' => $trial->userid,
                'other' => ['planid' => $trial->planid, 'reason' => 'trial_expired'],
            ]);
            $event->trigger();
        }

        // Log the expiration.
        $logdata = [
            'subscriptionid' => $trial->id,
            'userid' => $trial->userid,
            'planid' => $trial->planid,
            'action' => 'trial_expired',
            'details' => json_encode([
                'had_grace_period' => $trial->grace_period_days > 0,
            ]),
            'timecreated' => $now,
        ];
        $DB->insert_record('local_moderncommerce_subscription_log', $logdata);

        // Send trial expired notification.
        $this->send_trial_expired_email($trial);
    }

    /**
     * Send trial ending warning email.
     *
     * @param object $trial Trial subscription record.
     */
    private function send_trial_ending_email(object $trial): bool {
        global $DB;

        $user = $DB->get_record('user', ['id' => $trial->userid]);
        if (!$user) {
            return false;
        }

        try {
            return email_service::send('trial_ending', $user, [
                'plan_name' => $trial->plan_name,
                'trial_end_date' => userdate($trial->trial_end_date, get_string('strftimedatetime', 'core_langconfig')),
                'plan_price' => number_format($trial->price, 2),
                'billing_cycle' => $trial->billing_cycle,
                'convert_url' => (new \moodle_url('/local/moderncommerce/learner/subscription.php'))->out(false),
            ]);
        } catch (\Exception $e) {
            mtrace("Failed to send trial ending email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send trial converted email.
     *
     * @param object $trial Trial subscription record.
     */
    private function send_trial_converted_email(object $trial): void {
        global $DB;

        $user = $DB->get_record('user', ['id' => $trial->userid]);
        if (!$user) {
            return;
        }

        try {
            if (class_exists('\\local_moderncommerce\subscription\\services\\email_service')) {
                email_service::send('trial_converted', $user, [
                    'plan_name' => $trial->plan_name,
                    'plan_price' => number_format($trial->price, 2),
                    'billing_cycle' => $trial->billing_cycle,
                    'subscription_url' => (new \moodle_url('/local/moderncommerce/learner/subscription.php'))->out(false),
                ]);
            }
        } catch (\Exception $e) {
            mtrace("Failed to send trial converted email: " . $e->getMessage());
        }
    }

    /**
     * Send trial expired email.
     *
     * @param object $trial Trial subscription record.
     */
    private function send_trial_expired_email(object $trial): void {
        global $DB;

        $user = $DB->get_record('user', ['id' => $trial->userid]);
        if (!$user) {
            return;
        }

        try {
            if (class_exists('\\local_moderncommerce\subscription\\services\\email_service')) {
                email_service::send('trial_expired', $user, [
                    'plan_name' => $trial->plan_name,
                    'plan_price' => number_format($trial->price, 2),
                    'billing_cycle' => $trial->billing_cycle,
                    'subscribe_url' => (new \moodle_url('/local/moderncommerce/learner/index.php'))->out(false) . '#/library',
                ]);
            }
        } catch (\Exception $e) {
            mtrace("Failed to send trial expired email: " . $e->getMessage());
        }
    }
}
