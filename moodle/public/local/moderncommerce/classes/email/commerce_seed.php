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
 * Commerce notification template seed (the 62 transactional/lifecycle email bodies).
 *
 * Authored content lives in
 * local/moderncommerce/docs/NOTIFICATION_CONTENT_LIBRARY.md. This class returns
 * one definition per `template_key`; in-app and Slack/Teams variants are owned by
 * the notification subsystem, not by the email engine.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\email;

// phpcs:disable moodle.Files.LineLength -- Seed copy keeps bundled email content auditable.

/**
 * Bundled Modern Commerce notification email templates.
 */
class commerce_seed {
    /** @var string Commerce component. */
    private const MC = 'local_moderncommerce';

    /** @var string Subscription component. */
    private const MS = 'local_moderncommerce';

    /**
     * Build one template definition row with inner body content.
     *
     * @param string $key Template key.
     * @param string $component Owning component.
     * @param string $name Source English name, mirrored in the component language strings.
     * @param string $type Template type (= notification category).
     * @param string $subject Source English subject, mirrored in the component language strings.
     * @param string $body Source English body, mirrored in the component language strings.
     * @param array $ph Placeholder tokens used (without braces).
     * @param bool $marketing Append the one-click unsubscribe footer.
     * @return array
     */
    private static function row(
        string $key,
        string $component,
        string $name,
        string $type,
        string $subject,
        string $body,
        array $ph,
        bool $marketing = false
    ): array {
        return [
            'template_key' => $key,
            'component' => $component,
            'name' => get_string(self::string_key($key, 'name'), self::MC),
            'template_type' => $type,
            'subject' => get_string(self::string_key($key, 'subject'), self::MC),
            'body' => get_string(self::string_key($key, 'body'), self::MC),
            'placeholders' => json_encode($ph),
            'locked' => 1,
        ];
    }

    /**
     * Build a deterministic language-string key for a bundled email template field.
     *
     * @param string $templatekey Template key.
     * @param string $field Template field.
     * @return string
     */
    private static function string_key(string $templatekey, string $field): string {
        return 'emailtpl_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($templatekey)) . '_' . $field;
    }

    /**
     * All bundled commerce notification template definitions.
     *
     * @return array
     */
    public static function definitions(): array {
        return array_merge(
            self::cart_checkout(),
            self::orders_payments(),
            self::access_certificates(),
            self::subscriptions_billing(),
            self::subscriptions_lifecycle(),
            self::invoices_keys(),
            self::marketing(),
            self::admin_ops()
        );
    }

    /**
     * Section 1 — Cart & Checkout.
     *
     * @return array
     */
    private static function cart_checkout(): array {
        return [
            self::row(
                'moderncommerce_cart_abandoned_1h',
                self::MC,
                'Abandoned Cart — 1 hour',
                'marketing',
                "You left something behind, {firstname}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Looks like you got pulled away before finishing up — no worries, we saved your cart for you.</p>
<p>Here's what's still waiting:</p>
<ul>{cart_items}</ul>
<p><strong>Cart total: {cart_total}</strong></p>
<p><a class="mc-button" href="{cart_url}">Return to my cart</a></p>
<p>It only takes a minute to check out and start learning today.</p>
HTML
                ,
                ['firstname', 'cart_items', 'cart_total', 'cart_url', 'cart_items_count'],
                true
            ),

            self::row(
                'moderncommerce_cart_abandoned_24h',
                self::MC,
                'Abandoned Cart — 24 hours',
                'marketing',
                "Your next skill is one click away",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Yesterday you took the first step toward a real goal — picking the course that gets you there. That instinct was right.</p>
<p><strong>{course_name}</strong> is built to move you from "I want to" to "I can," one focused lesson at a time. Your spot and your cart are still saved:</p>
<ul>{cart_items}</ul>
<p>Secure checkout, instant access, and you can learn at your own pace — no pressure, no rush.</p>
<p><a class="mc-button" href="{cart_url}">Complete my enrollment</a></p>
HTML
                ,
                ['firstname', 'course_name', 'cart_items', 'cart_url'],
                true
            ),

            self::row(
                'moderncommerce_cart_abandoned_72h',
                self::MC,
                'Abandoned Cart — 72 hours',
                'marketing',
                "A little something to help you start 🎟️",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your cart's still here — but we won't hold it forever, so this is a friendly last nudge.</p>
<p>To help you commit to the goal you set, here's a thank-you on us:</p>
<p style="text-align:center;"><strong>Use code {coupon_code} at checkout</strong></p>
<ul>{cart_items}</ul>
<p><strong>Cart total: {cart_total}</strong></p>
<p><a class="mc-button" href="{cart_url}">Apply my code &amp; enroll</a></p>
<p>The hardest part of any goal is starting. Today's a good day to.</p>
HTML
                ,
                ['firstname', 'coupon_code', 'cart_items', 'cart_total', 'cart_url'],
                true
            ),

            self::row(
                'moderncommerce_checkout_abandoned',
                self::MC,
                'Checkout Abandoned',
                'marketing',
                "You were so close, {firstname} — finish up",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>You'd already reached checkout — the finish line — when something interrupted you. Good news: nothing was lost.</p>
<p>Everything you entered is saved, so you can pick up exactly where you stopped and have instant access to <strong>{course_name}</strong> moments later.</p>
<p><a class="mc-button" href="{checkout_url}">Return to checkout</a></p>
<p>If anything went wrong at payment, just reply or reach us at {supportemail} — we're happy to help you across the line.</p>
HTML
                ,
                ['firstname', 'course_name', 'checkout_url', 'supportemail'],
                true
            ),
        ];
    }

    /**
     * Section 2 — Orders & Payments.
     *
     * @return array
     */
    private static function orders_payments(): array {
        return [
            self::row(
                'moderncommerce_order_placed',
                self::MC,
                'Order Placed (Pending Payment)',
                'transactional',
                "We've got your order {order_number}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Thanks for choosing {sitename} — we've reserved your order and it's waiting on payment.</p>
<p><strong>Order {order_number}</strong> · {order_date}<br>{courses_list}</p>
<p>Total due: <strong>{order_total}</strong> ({payment_method})</p>
<p>Complete your payment to unlock access right away.</p>
<p><a class="mc-button" href="{retry_payment_url}">Complete payment</a></p>
<p>Need a hand? Reply to this email or write to {supportemail}.</p>
HTML
                ,
                ['firstname', 'sitename', 'order_number', 'order_date', 'courses_list', 'order_total', 'payment_method', 'retry_payment_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_payment_pending_reminder',
                self::MC,
                'Payment Pending Reminder',
                'reminder',
                "{firstname}, your courses are still waiting",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Just a friendly nudge — your order is saved but not quite finished, so your access hasn't started yet.</p>
<p><strong>Order {order_number}</strong><br>{courses_list}</p>
<p>Total due: <strong>{order_total}</strong></p>
<p>Pick up right where you left off — it only takes a moment.</p>
<p><a class="mc-button" href="{retry_payment_url}">Finish my order</a></p>
HTML
                ,
                ['firstname', 'order_number', 'courses_list', 'order_total', 'retry_payment_url']
            ),

            self::row(
                'moderncommerce_payment_receipt',
                self::MC,
                'Payment Receipt (Learner)',
                'transactional',
                "You're in, {firstname} — order {order_number} confirmed",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Thanks for your purchase — your payment of <strong>{order_total}</strong> is in and your access is ready.</p>
<p><strong>Order {order_number}</strong> · {order_date} · {payment_method}<br>{courses_list}</p>
<p><a class="mc-button" href="{my_courses_url}">Start learning</a></p>
<p>Want the full breakdown? <a href="{order_view_link}">View your receipt</a>, or reach us at {supportemail}.</p>
HTML
                ,
                ['firstname', 'order_total', 'order_number', 'order_date', 'payment_method', 'courses_list', 'my_courses_url', 'order_view_link', 'supportemail']
            ),

            self::row(
                'moderncommerce_order_payment_failed',
                self::MC,
                'Order Payment Failed',
                'dunning',
                "Quick fix for order {order_number}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your payment for order {order_number} didn't go through, so <strong>nothing was charged</strong> — these things happen.</p>
<p>It's usually a quick fix. Just confirm your details and your <strong>{order_total}</strong> order will be ready in about 30 seconds.</p>
<p><strong>Order {order_number}</strong><br>{courses_list}</p>
<p><a class="mc-button" href="{retry_payment_url}">Retry payment</a></p>
<p>Still stuck? Reply here or email {supportemail} — we're happy to help.</p>
HTML
                ,
                ['firstname', 'order_number', 'order_total', 'courses_list', 'retry_payment_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_enrollment_confirmation',
                self::MC,
                'Order Completed / Enrolled',
                'transactional',
                "You're enrolled, {firstname} 🎉",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Great news — order {order_number} is all set and you're enrolled. Everything below is unlocked and waiting for you:</p>
<p>{courses_list}</p>
<p>Jump in whenever you're ready — your progress saves automatically as you go.</p>
<p><a class="mc-button" href="{my_courses_url}">Start learning</a></p>
<p>Questions about your courses? Just reply or reach us at {supportemail}.</p>
HTML
                ,
                ['firstname', 'order_number', 'courses_list', 'my_courses_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_order_cancelled',
                self::MC,
                'Order Cancelled',
                'transactional',
                "Your order {order_number} was cancelled",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>We're confirming that order {order_number} has been cancelled. <strong>You haven't been charged</strong>, and there's nothing more you need to do.</p>
<p>Cancelled order:<br>{courses_list}</p>
<p>Changed your mind? Your courses are still available whenever you'd like to come back.</p>
<p><a class="mc-button" href="{siteurl}">Browse courses</a></p>
<p>If this cancellation was unexpected, let us know at {supportemail}.</p>
HTML
                ,
                ['firstname', 'order_number', 'courses_list', 'siteurl', 'supportemail']
            ),

            self::row(
                'moderncommerce_refund_confirmation',
                self::MC,
                'Refund Issued',
                'transactional',
                "Your {refund_amount} refund is on its way",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your refund of <strong>{refund_amount}</strong> for order {order_number} has been approved and is on its way back to you.</p>
<ul>
  <li>Refund amount: <strong>{refund_amount}</strong></li>
  <li>Back to: {payment_method}</li>
  <li>Reference: {refund_reference}</li>
</ul>
<p>Refunds usually land in your account within 5–10 business days, depending on your provider.</p>
<p>Anything we can help with? Just reply or email {supportemail}.</p>
HTML
                ,
                ['firstname', 'refund_amount', 'order_number', 'payment_method', 'refund_reference', 'supportemail']
            ),

            self::row(
                'moderncommerce_refund_settled',
                self::MC,
                'Refund Settled',
                'transactional',
                "Your {refund_amount} refund has landed",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Good news — your refund of <strong>{refund_amount}</strong> for order {order_number} has now settled in your account. This one's fully wrapped up.</p>
<p>Reference: {refund_reference}</p>
<p>Thank you for giving {sitename} a try. We'd love to welcome you back whenever the timing's right, and you're always welcome to explore what's new.</p>
<p><a class="mc-button" href="{siteurl}">See what's new</a></p>
<p>Take care — and reach out at {supportemail} anytime.</p>
HTML
                ,
                ['firstname', 'refund_amount', 'order_number', 'refund_reference', 'sitename', 'siteurl', 'supportemail']
            ),
        ];
    }

    /**
     * Section 3 — Enrollment, Access & Certificates.
     *
     * @return array
     */
    private static function access_certificates(): array {
        return [
            self::row(
                'moderncommerce_access_welcome',
                self::MC,
                'Access Welcome / Enrolled',
                'transactional',
                "You're in, {firstname} — {course_name} awaits",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Welcome to <strong>{course_name}</strong> — your access is live and everything's ready for you.</p>
<p>To get off to a strong start:</p>
<ul>
  <li>Open the course and watch the short welcome lesson</li>
  <li>Complete the first activity while your momentum is fresh</li>
  <li>Bookmark <a href="{course_link}">{course_name}</a> so it's one tap away</li>
</ul>
<p><a class="mc-button" href="{course_link}">Start your first lesson</a></p>
<p>Questions along the way? Just reply or email {supportemail}.</p>
HTML
                ,
                ['firstname', 'course_name', 'course_link', 'supportemail']
            ),

            self::row(
                'moderncommerce_course_completed',
                self::MC,
                'Course Completed',
                'celebratory',
                "🎉 You did it, {firstname} — {course_name} done",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>You've completed <strong>{course_name}</strong> — congratulations. Finishing a full course takes focus and follow-through, and you saw it all the way through.</p>
<p>Don't let that momentum cool off. The best time to build on what you've learned is right now, while it's fresh.</p>
<ul>
  <li>Put one new skill into practice this week</li>
  <li>Pick your next course and keep the streak going</li>
</ul>
<p><a class="mc-button" href="{catalog_url}">Find your next course</a></p>
<p>Proud to have you learning with us.</p>
HTML
                ,
                ['firstname', 'course_name', 'catalog_url']
            ),

            self::row(
                'moderncommerce_program_completed',
                self::MC,
                'Program Completed',
                'celebratory',
                "Congratulations, {firstname} — you completed {program_name}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Congratulations — you have met every completion requirement for <strong>{program_name}</strong>.</p>
<ul>
  <li><strong>Courses completed:</strong> {completed_courses}</li>
  <li><strong>Average grade:</strong> {average_grade}</li>
  <li><strong>Completed:</strong> {completion_date}</li>
</ul>
<p><a class="mc-button" href="{program_url}">View your completed program</a></p>
<p>If this program awards a certificate, it is available in <a href="{certificate_url}">My certificates</a>.</p>
HTML
                ,
                [
                    'firstname', 'program_name', 'completed_courses', 'average_grade',
                    'completion_date', 'program_url', 'certificate_url',
                ]
            ),

            self::row(
                'moderncommerce_access_expiring',
                self::MC,
                'Access Expiring Soon',
                'reminder',
                "{days_remaining} days left in {course_name}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>A quick heads-up: your access to <strong>{course_name}</strong> ends in <strong>{days_remaining} days</strong>, on {access_enddate}.</p>
<p>There's still time to finish what you started and lock in your progress. If you'd like to keep going beyond that date, you can extend in one tap.</p>
<p><a class="mc-button" href="{course_link}">Pick up where you left off</a></p>
<p>Need more time? <a href="{renew_url}">Renew your access</a>.</p>
HTML
                ,
                ['firstname', 'course_name', 'days_remaining', 'access_enddate', 'course_link', 'renew_url']
            ),

            self::row(
                'moderncommerce_access_expired',
                self::MC,
                'Access Expired',
                'transactional',
                "Your {course_name} access has ended",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your access to <strong>{course_name}</strong> ended on {access_enddate}, so the course is paused for now.</p>
<p>If you weren't quite finished — or you'd like to revisit the material — you can pick up right where you left off. Your progress is saved and waiting.</p>
<p><a class="mc-button" href="{renew_url}">Renew your access</a></p>
<p>Any trouble getting back in? Email {supportemail} and we'll sort it out.</p>
HTML
                ,
                ['firstname', 'course_name', 'access_enddate', 'renew_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_access_revoked',
                self::MC,
                'Access Revoked',
                'transactional',
                "Update on your {course_name} access",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>We're writing to let you know that your access to <strong>{course_name}</strong> has been removed, effective {access_enddate}.</p>
<p>This can happen after a refund, a cancellation, or an account change. If you have questions about this — or you believe it was made in error — our team is happy to help.</p>
<p><a class="mc-button" href="mailto:{supportemail}">Contact support</a></p>
<p>Thank you for learning with {sitename}.</p>
HTML
                ,
                ['firstname', 'course_name', 'access_enddate', 'supportemail', 'sitename']
            ),

            self::row(
                'moderncommerce_certificate_issued',
                self::MC,
                'Certificate Issued',
                'celebratory',
                "🏆 Your {certificate_name} is ready",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Congratulations — your <strong>{certificate_name}</strong> is officially issued. This isn't just a file; it's proof of the work you put in to complete {course_name}.</p>
<p>Make it count:</p>
<ul>
  <li>Download it and keep a copy for your records</li>
  <li>Add it to your LinkedIn or CV to show off your new skills</li>
</ul>
<p><a class="mc-button" href="{certificate_url}">Download your certificate</a></p>
<p>Wear it proudly — you've earned it.</p>
HTML
                ,
                ['firstname', 'certificate_name', 'course_name', 'certificate_url']
            ),

            self::row(
                'moderncommerce_certificate_expiring',
                self::MC,
                'Certificate Expiring',
                'reminder',
                "Recertify before {certificate_expiry}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your <strong>{certificate_name}</strong> is due to expire on <strong>{certificate_expiry}</strong>. To keep it valid and current, you'll want to recertify before then.</p>
<p>Renewing keeps your credential active for employers, clients, and compliance records — no gap, no lapse.</p>
<p><a class="mc-button" href="{renew_url}">Renew your certification</a></p>
<p>Questions about recertifying? Email {supportemail}.</p>
HTML
                ,
                ['firstname', 'certificate_name', 'certificate_expiry', 'renew_url', 'supportemail']
            ),
        ];
    }

    /**
     * Section 4 — Subscriptions: onboarding, renewal, billing & dunning.
     *
     * @return array
     */
    private static function subscriptions_billing(): array {
        return [
            self::row(
                'modernsubscription_activated',
                self::MS,
                'Subscription Activated',
                'transactional',
                "You're in, {firstname} — {plan_name} is live 🎉",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Welcome aboard! Your <strong>{plan_name}</strong> subscription is now active and your full library is unlocked. Here's what's included:</p>
<ul>{courses_list}</ul>
<p>Pick a course and dive in — your progress saves automatically across every device.</p>
<p><a class="mc-button" href="{my_subscription_url}">Start learning</a></p>
<p>Need a hand? Reach us anytime at {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'courses_list', 'my_subscription_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_trial_started',
                self::MS,
                'Trial Started',
                'transactional',
                "Your free trial is on, {firstname} ✨",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your <strong>{plan_name}</strong> trial is live, with full access until <strong>{trial_end_date}</strong> — completely free. This is your chance to explore everything:</p>
<ul>{courses_list}</ul>
<p>Our tip: finish one course in your first few days. You'll feel the momentum and get the most from your <strong>{trial_days}</strong>-day trial.</p>
<p><a class="mc-button" href="{my_subscription_url}">Explore your trial</a></p>
<p>Questions? We're at {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'trial_end_date', 'courses_list', 'trial_days', 'my_subscription_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_trial_ending',
                self::MS,
                'Trial Ending Soon',
                'reminder',
                "1 day left, {firstname} — keep your access",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your <strong>{plan_name}</strong> trial ends <strong>tomorrow, {trial_end_date}</strong>. To keep your courses and progress, just add a payment method now.</p>
<p>After your trial, you'll pay <strong>{plan_price}</strong> ({billing_cycle}) — no hidden fees, cancel anytime. Your first charge is on {next_billing_date}.</p>
<p><a class="mc-button" href="{update_payment_url}">Add payment method</a></p>
<p>Not sure yet? We're happy to help at {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'trial_end_date', 'plan_price', 'billing_cycle', 'next_billing_date', 'update_payment_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_trial_converted',
                self::MS,
                'Trial Converted to Paid',
                'transactional',
                "You're all set, {firstname} — welcome to {plan_name}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Thanks for sticking with us! Your trial has converted to a full <strong>{plan_name}</strong> subscription and your first payment of <strong>{plan_price}</strong> ({billing_cycle}) is confirmed.</p>
<p>Nothing changes for you — every course and all your progress stay right where you left them. Your next renewal is <strong>{next_billing_date}</strong>.</p>
<p><a class="mc-button" href="{my_subscription_url}">View my subscription</a></p>
<p>Thank you for learning with {sitename}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'plan_price', 'billing_cycle', 'next_billing_date', 'my_subscription_url', 'sitename']
            ),

            self::row(
                'modernsubscription_trial_expired',
                self::MS,
                'Trial Expired',
                'transactional',
                "Your trial has ended, {firstname}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your <strong>{plan_name}</strong> trial wrapped up on {trial_end_date}, so course access is paused for now. No worries — your progress is safely saved.</p>
<p>Ready to keep going? Subscribe for <strong>{plan_price}</strong> ({billing_cycle}) and you'll be back in your courses instantly, exactly where you stopped.</p>
<p><a class="mc-button" href="{renewal_url}">Subscribe and continue</a></p>
<p>We'd love to have you back. Questions? {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'trial_end_date', 'plan_price', 'billing_cycle', 'renewal_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_renewal_reminder',
                self::MS,
                'Renewal Reminder (7/3/1 day)',
                'reminder',
                "Your {plan_name} renews in {days_remaining} days",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Just a heads-up: your <strong>{plan_name}</strong> renews in <strong>{days_remaining} days</strong> on <strong>{subscription_enddate}</strong> for <strong>{plan_price}</strong> ({billing_cycle}). Keep going — your courses stay open and your progress is saved.</p>
<p>Nothing to do unless you'd like to make a change.</p>
<p><a class="mc-button" href="{my_subscription_url}">Manage subscription</a></p>
<p>Questions? We're here at {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'days_remaining', 'subscription_enddate', 'plan_price', 'billing_cycle', 'my_subscription_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_billing_upcoming',
                self::MS,
                'Upcoming Billing Notice',
                'reminder',
                "Heads-up: {plan_price} charge in 3 days",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>A friendly reminder that your <strong>{plan_name}</strong> will renew in <strong>3 days</strong>. We'll charge your card on file <strong>{plan_price}</strong> ({billing_cycle}) on <strong>{next_billing_date}</strong>.</p>
<p>No action is needed — your courses keep rolling. Need to update your card or change plans first?</p>
<p><a class="mc-button" href="{update_payment_url}">Update payment details</a></p>
<p>Questions anytime at {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'plan_price', 'billing_cycle', 'next_billing_date', 'update_payment_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_renewal_success',
                self::MS,
                'Renewal Succeeded',
                'transactional',
                "Renewed! {plan_name} active to {subscription_enddate}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your <strong>{plan_name}</strong> renewed successfully. We received <strong>{plan_price}</strong> ({billing_cycle}), and your access continues through <strong>{subscription_enddate}</strong>.</p>
<p>Thanks for staying with us — keep your momentum going.</p>
<p><a class="mc-button" href="{invoice_url}">View receipt</a></p>
<p>Your next renewal is {next_billing_date}. Manage anytime at {my_subscription_url}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'plan_price', 'billing_cycle', 'subscription_enddate', 'invoice_url', 'next_billing_date', 'my_subscription_url']
            ),

            self::row(
                'modernsubscription_payment_failed',
                self::MS,
                'Payment Failed (Dunning)',
                'dunning',
                "{firstname}, we couldn't process your payment",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>We tried to renew your <strong>{plan_name}</strong> for <strong>{plan_price}</strong> ({billing_cycle}), but the payment didn't go through. It happens — usually it's just an expired card or a temporary hold.</p>
<p><strong>Good news:</strong> your access stays on for now, and we'll automatically retry on {next_billing_date}. Updating your card takes under a minute and sorts it instantly.</p>
<p><a class="mc-button" href="{update_payment_url}">Update payment method</a></p>
<p>Need help? We're glad to assist at {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'plan_price', 'billing_cycle', 'next_billing_date', 'update_payment_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_suspended_payment',
                self::MS,
                'Suspended — Final Payment Failure',
                'dunning',
                "{firstname}, your access is paused — fix in 1 min",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>After a few tries, we still couldn't process payment for your <strong>{plan_name}</strong>, so your access is paused for now. We're sorry for the interruption — and your progress is completely safe.</p>
<p>One quick card update reactivates everything right away, and you'll be back in your courses instantly.</p>
<p><a class="mc-button" href="{update_payment_url}">Reactivate my subscription</a></p>
<p>If something's wrong on our end, tell us at {supportemail} — we'll make it right.</p>
HTML
                ,
                ['firstname', 'plan_name', 'update_payment_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_grace_started',
                self::MS,
                'Grace Period Started',
                'reminder',
                "{firstname}, {days_remaining} days to keep your access",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>We weren't able to renew your <strong>{plan_name}</strong> yet, so you're in a short grace period. The good news: your courses stay fully open for <strong>{days_remaining} more days</strong>.</p>
<p>Renew now for <strong>{plan_price}</strong> ({billing_cycle}) to keep everything running without a break — it only takes a moment.</p>
<p><a class="mc-button" href="{renewal_url}">Renew now</a></p>
<p>Questions? We're here at {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'days_remaining', 'plan_price', 'billing_cycle', 'renewal_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_grace_ending',
                self::MS,
                'Grace Period Ending',
                'reminder',
                "Final notice, {firstname} — access ends soon",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>This is your <strong>final reminder</strong>: your grace period ends in <strong>{days_remaining} days</strong>, after which your <strong>{plan_name}</strong> access will close and your courses will lock.</p>
<p>Renew now for <strong>{plan_price}</strong> ({billing_cycle}) to stay in without losing a thing — your progress is saved, but don't cut it close.</p>
<p><a class="mc-button" href="{renewal_url}">Renew and keep access</a></p>
<p>We'd hate to see you go — reach us at {supportemail} if you need anything.</p>
HTML
                ,
                ['firstname', 'plan_name', 'days_remaining', 'plan_price', 'billing_cycle', 'renewal_url', 'supportemail']
            ),
        ];
    }

    /**
     * Section 5 — Subscriptions: lifecycle & win-back.
     *
     * @return array
     */
    private static function subscriptions_lifecycle(): array {
        return [
            self::row(
                'modernsubscription_expired',
                self::MS,
                'Subscription Expired / Suspended',
                'transactional',
                "Your {plan_name} access has paused",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your <strong>{plan_name}</strong> subscription has expired, so access to your courses is paused for now. Nothing is lost — your progress is saved and waiting for you.</p>
<p>Reactivate whenever you're ready and you'll be straight back in.</p>
<p><a class="mc-button" href="{renewal_url}">Renew my plan</a></p>
<p>Questions about your account? Just reply or email us at {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'renewal_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_cancelled',
                self::MS,
                'Subscription Cancelled',
                'transactional',
                "{plan_name} cancelled — access to {subscription_enddate}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your <strong>{plan_name}</strong> subscription is now cancelled. You'll keep full access until <strong>{subscription_enddate}</strong> — nothing changes before then, and you won't be charged again.</p>
<p>Change your mind? You can reactivate in one click.</p>
<p><a class="mc-button" href="{reactivate_url}">Reactivate</a></p>
<p>We'd love to know what we could do better — just reply to this email.</p>
HTML
                ,
                ['firstname', 'plan_name', 'subscription_enddate', 'reactivate_url']
            ),

            self::row(
                'modernsubscription_cancel_scheduled',
                self::MS,
                'Cancellation Scheduled',
                'transactional',
                "Your {plan_name} ends on {subscription_enddate}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>We've scheduled your <strong>{plan_name}</strong> subscription to cancel at the end of your billing period. You'll keep full access until <strong>{subscription_enddate}</strong>, and there'll be no further charges after that.</p>
<p>Didn't mean to do this, or want to stay? You can undo the cancellation any time before {subscription_enddate}.</p>
<p><a class="mc-button" href="{reactivate_url}">Keep my subscription</a></p>
<p>Need a hand? Reply to this email or contact {supportemail}.</p>
HTML
                ,
                ['firstname', 'plan_name', 'subscription_enddate', 'reactivate_url', 'supportemail']
            ),

            self::row(
                'modernsubscription_upgraded',
                self::MS,
                'Plan Upgraded',
                'transactional',
                "Welcome to {new_plan_name} 🎉",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>You've upgraded from <strong>{old_plan_name}</strong> to <strong>{new_plan_name}</strong>, and it's live right now. Here's what's new:</p>
<ul>
  <li>Everything in your previous plan, plus more courses and features</li>
  <li>Billing moves to <strong>{plan_price}</strong> per {billing_cycle}</li>
  <li>A prorated charge was applied for the rest of this cycle</li>
</ul>
<p>Ready to dive in?</p>
<p><a class="mc-button" href="{my_subscription_url}">Explore {new_plan_name}</a></p>
HTML
                ,
                ['firstname', 'old_plan_name', 'new_plan_name', 'plan_price', 'billing_cycle', 'my_subscription_url']
            ),

            self::row(
                'modernsubscription_downgrade_scheduled',
                self::MS,
                'Plan Downgrade Scheduled',
                'transactional',
                "Your plan changes to {new_plan_name} soon",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>We've scheduled your plan to move from <strong>{old_plan_name}</strong> to <strong>{new_plan_name}</strong> on <strong>{effective_date}</strong>. Until then, nothing changes and you keep everything you have now.</p>
<p>From {effective_date}, your subscription will bill at <strong>{plan_price}</strong> per {billing_cycle}, and access will match the {new_plan_name} plan.</p>
<p>Want to stay on {old_plan_name} instead? You can change this any time before {effective_date}.</p>
<p><a class="mc-button" href="{my_subscription_url}">Review my plan</a></p>
HTML
                ,
                ['firstname', 'old_plan_name', 'new_plan_name', 'effective_date', 'plan_price', 'billing_cycle', 'my_subscription_url']
            ),

            self::row(
                'modernsubscription_extended',
                self::MS,
                'Subscription Extended (Goodwill)',
                'transactional',
                "We've added {days_extended} days, {firstname} 🎁",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Good news — we've added <strong>{days_extended} days</strong> to your <strong>{plan_name}</strong> subscription, on the house. Your access now runs through <strong>{subscription_enddate}</strong>.</p>
<p>There's nothing you need to do. Just keep learning.</p>
<p><a class="mc-button" href="{my_subscription_url}">Back to my courses</a></p>
<p>Enjoy the extra time, and thanks for being with {sitename}.</p>
HTML
                ,
                ['firstname', 'days_extended', 'plan_name', 'subscription_enddate', 'my_subscription_url', 'sitename']
            ),

            self::row(
                'modernsubscription_winback',
                self::MS,
                'Win-back / Re-engagement',
                'marketing',
                "Your courses miss you — {winback_discount} off",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>You were making real progress before life got busy — and your courses are still right where you left them. Come back and you can keep building toward the skills and goals that brought you here.</p>
<p>To make it easy, here's <strong>{winback_discount} off</strong> when you restart with code <strong>{winback_coupon}</strong>.</p>
<p><a class="mc-button" href="{reactivate_url}">Restart and save {winback_discount}</a></p>
HTML
                ,
                ['firstname', 'winback_discount', 'winback_coupon', 'reactivate_url'],
                true
            ),
        ];
    }

    /**
     * Section 6 — Invoices & License Keys.
     *
     * @return array
     */
    private static function invoices_keys(): array {
        return [
            self::row(
                'moderncommerce_invoice_sent',
                self::MC,
                'Invoice Issued',
                'transactional',
                "Invoice {invoice_number} — due {invoice_duedate}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Please find invoice <strong>{invoice_number}</strong> for <strong>{invoice_total}</strong>, due <strong>{invoice_duedate}</strong>.</p>
<p>You can review the full breakdown and pay securely online, or download a PDF for your records.</p>
<p><a class="mc-button" href="{pay_invoice_url}">View &amp; pay invoice</a></p>
<p>Questions about this invoice? Contact us at {supportemail}.</p>
HTML
                ,
                ['firstname', 'invoice_number', 'invoice_total', 'invoice_duedate', 'pay_invoice_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_invoice_due_soon',
                self::MC,
                'Invoice Due Soon',
                'reminder',
                "Reminder: invoice {invoice_number} due {invoice_duedate}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>A friendly reminder that invoice <strong>{invoice_number}</strong> for <strong>{invoice_total}</strong> is due on <strong>{invoice_duedate}</strong>.</p>
<p>If payment is already on its way, please disregard this note. Otherwise, you can settle it online in just a moment.</p>
<p><a class="mc-button" href="{pay_invoice_url}">Pay invoice now</a></p>
<p>Need a copy for accounts? Download the <a href="{invoice_pdf_url}">PDF</a> or reach us at {supportemail}.</p>
HTML
                ,
                ['firstname', 'invoice_number', 'invoice_total', 'invoice_duedate', 'pay_invoice_url', 'invoice_pdf_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_invoice_overdue',
                self::MC,
                'Invoice Overdue',
                'reminder',
                "Action needed: invoice {invoice_number} is overdue",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Our records show invoice <strong>{invoice_number}</strong> for <strong>{invoice_total}</strong> was due on <strong>{invoice_duedate}</strong> and remains unpaid.</p>
<p>To keep <strong>{organisation_name}</strong>'s access uninterrupted, please arrange payment at your earliest convenience.</p>
<p><a class="mc-button" href="{pay_invoice_url}">Pay overdue invoice</a></p>
<p>Already paid, or need to discuss terms? Contact us at {supportemail} and we'll sort it out.</p>
HTML
                ,
                ['firstname', 'invoice_number', 'invoice_total', 'invoice_duedate', 'organisation_name', 'pay_invoice_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_invoice_paid',
                self::MC,
                'Invoice Paid',
                'transactional',
                "Payment received — invoice {invoice_number} ✅",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Thank you — we've received your payment of <strong>{invoice_total}</strong> for invoice <strong>{invoice_number}</strong>. This invoice is now marked paid in full.</p>
<p>A copy is on your account for <strong>{organisation_name}</strong>, and you can download a receipt PDF anytime for your records.</p>
<p><a class="mc-button" href="{invoice_pdf_url}">Download receipt</a></p>
<p>We appreciate your business. Any questions? Reach us at {supportemail}.</p>
HTML
                ,
                ['firstname', 'invoice_total', 'invoice_number', 'organisation_name', 'invoice_pdf_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_invoice_cancelled',
                self::MC,
                'Invoice Cancelled / Void',
                'transactional',
                "Invoice {invoice_number} has been cancelled",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>We're writing to confirm that invoice <strong>{invoice_number}</strong> for <strong>{invoice_total}</strong> has been cancelled and is no longer payable.</p>
<p>No action is required on your part. If a payment was already made against this invoice, any applicable credit or refund will be handled separately.</p>
<p>If a replacement invoice is needed, we'll issue it shortly. You can review the status online at any time.</p>
<p><a class="mc-button" href="{invoice_url}">View invoice status</a></p>
<p>Questions? We're happy to help at {supportemail}.</p>
HTML
                ,
                ['firstname', 'invoice_number', 'invoice_total', 'invoice_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_keys_generated',
                self::MC,
                'Keys Generated (Batch)',
                'transactional',
                "{key_count} access keys for {key_target_name} are ready",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your batch of <strong>{key_count}</strong> access keys for <strong>{key_target_name}</strong> has been generated and is ready to distribute.</p>
<p>To get your team started:</p>
<ul>
  <li>Download the full list as a CSV from the link below.</li>
  <li>Share one key per learner, or send them straight from your dashboard.</li>
  <li>Each recipient redeems their key to unlock access instantly.</li>
</ul>
<p><a class="mc-button" href="{keys_csv_url}">Download keys (CSV)</a></p>
<p>Manage seats anytime from your <a href="{manager_dashboard_url}">dashboard</a>, or reach us at {supportemail}.</p>
HTML
                ,
                ['firstname', 'key_count', 'key_target_name', 'keys_csv_url', 'manager_dashboard_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_key_delivered',
                self::MC,
                'Key Delivered / Gifted',
                'transactional',
                "You've been given access to {key_target_name} 🎁",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Good news — <strong>{organisation_name}</strong> has given you access to <strong>{key_target_name}</strong> on {sitename}.</p>
<p>Your access key is:</p>
<p><strong>{key_code}</strong></p>
<p>Redeem it using the button below to unlock your course right away. If you don't have an account yet, you'll be guided to create one first.</p>
<p><a class="mc-button" href="{redeem_url}">Redeem my key</a></p>
<p>This key is valid until <strong>{key_expiry}</strong>. Need help? Contact {supportemail}.</p>
HTML
                ,
                ['firstname', 'organisation_name', 'key_target_name', 'sitename', 'key_code', 'redeem_url', 'key_expiry', 'supportemail']
            ),

            self::row(
                'moderncommerce_key_redeemed',
                self::MC,
                'Key Redeemed',
                'transactional',
                "You're in — {key_target_name} is now active",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your key has been redeemed and access to <strong>{key_target_name}</strong> is now active on your {sitename} account.</p>
<p>There's nothing more to set up — you can jump straight in and begin whenever you're ready.</p>
<p><a class="mc-button" href="{redeem_url}">Start learning</a></p>
<p>If anything looks off, we're here to help at {supportemail}.</p>
HTML
                ,
                ['firstname', 'key_target_name', 'sitename', 'redeem_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_key_expiring',
                self::MC,
                'Key Expiring Soon',
                'reminder',
                "Redeem your key before {key_expiry}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Just a reminder that your access key for <strong>{key_target_name}</strong> is still waiting to be redeemed — and it expires on <strong>{key_expiry}</strong>.</p>
<p>Your key is:</p>
<p><strong>{key_code}</strong></p>
<p>Redeem it before the date below to claim your access. It only takes a moment.</p>
<p><a class="mc-button" href="{redeem_url}">Redeem before {key_expiry}</a></p>
<p>Trouble redeeming? We're happy to help at {supportemail}.</p>
HTML
                ,
                ['firstname', 'key_target_name', 'key_expiry', 'key_code', 'redeem_url', 'supportemail']
            ),

            self::row(
                'moderncommerce_key_expired',
                self::MC,
                'Key Expired',
                'transactional',
                "Your access key for {key_target_name} has expired",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Your access key <strong>{key_code}</strong> for <strong>{key_target_name}</strong> expired on <strong>{key_expiry}</strong> before it was redeemed, so it can no longer be used.</p>
<p>Don't worry — if you still need access, the person or team who sent your key can issue a fresh one, or you can request a new key from {organisation_name}.</p>
<p><a class="mc-button" href="{siteurl}">Visit {sitename}</a></p>
<p>If you think this is a mistake, reach out to us at {supportemail} and we'll take a look.</p>
HTML
                ,
                ['firstname', 'key_code', 'key_target_name', 'key_expiry', 'organisation_name', 'siteurl', 'sitename', 'supportemail']
            ),

            self::row(
                'moderncommerce_key_pool_low',
                self::MC,
                'Key Pool Low',
                'operational',
                "Only {seats_remaining} seats left for {key_target_name}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>A quick heads-up: your seat pool for <strong>{key_target_name}</strong> is running low. You have <strong>{seats_remaining}</strong> of <strong>{seats_total}</strong> seats remaining ({seats_used} in use).</p>
<p>To keep onboarding without interruption, we'd recommend topping up before the pool runs out.</p>
<p><a class="mc-button" href="{manager_dashboard_url}">Reorder seats</a></p>
<p>Want help forecasting how many seats {organisation_name} needs? Contact us at {supportemail}.</p>
HTML
                ,
                ['firstname', 'key_target_name', 'seats_remaining', 'seats_total', 'seats_used', 'manager_dashboard_url', 'organisation_name', 'supportemail']
            ),

            self::row(
                'moderncommerce_key_revoked',
                self::MC,
                'Key Revoked',
                'transactional',
                "Your access key for {key_target_name} was revoked",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>We're writing to let you know that access key <strong>{key_code}</strong> for <strong>{key_target_name}</strong> has been revoked by {organisation_name} and can no longer be used.</p>
<p>This sometimes happens when seats are reassigned or details change. If you believe access should continue, the best next step is to check in with your organisation.</p>
<p><a class="mc-button" href="{siteurl}">Visit {sitename}</a></p>
<p>Have questions about this change? Our team is available at {supportemail}.</p>
HTML
                ,
                ['firstname', 'key_code', 'key_target_name', 'organisation_name', 'siteurl', 'sitename', 'supportemail']
            ),
        ];
    }

    /**
     * Section 7 — Catalogue, Pricing & Promotions (marketing).
     *
     * @return array
     */
    private static function marketing(): array {
        return [
            self::row(
                'moderncommerce_back_in_stock',
                self::MC,
                'Back in Stock',
                'marketing',
                "{product_name} is open again, {firstname}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Good news — <strong>{product_name}</strong>, the course you were waiting for, has spots open again.</p>
<p>You asked us to let you know, so here it is: the skills you were after are back within reach. Places filled fast last time, so it's worth grabbing yours while it's open.</p>
<p><a class="mc-button" href="{product_url}">Enrol now</a></p>
HTML
                ,
                ['firstname', 'product_name', 'product_url'],
                true
            ),

            self::row(
                'moderncommerce_price_drop',
                self::MC,
                'Price Drop (Wishlist)',
                'marketing',
                "{course_name} just dropped to {new_price}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Good news — <strong>{course_name}</strong>, the course you saved, just dropped from {old_price} to <strong>{new_price}</strong> ({discount_percent} off).</p>
<p>Same skills, same outcome — now at a better price. There's no better moment to pick up where your goals left off.</p>
<p><a class="mc-button" href="{course_link}">Enrol now</a></p>
HTML
                ,
                ['firstname', 'course_name', 'old_price', 'new_price', 'discount_percent', 'course_link'],
                true
            ),

            self::row(
                'moderncommerce_promo_ending',
                self::MC,
                'Promotion Ending',
                'marketing',
                "Last chance — sale ends {promo_end_date}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Just a heads-up — the sale on <strong>{product_name}</strong> ends on <strong>{promo_end_date}</strong>, and the price goes back up after that.</p>
<p>If building this skill has been on your list, now's the moment to start while it's <strong>{new_price}</strong> ({discount_percent} off). Same outcome, lower cost — but only until the clock runs out.</p>
<p><a class="mc-button" href="{product_url}">Enrol before it ends</a></p>
HTML
                ,
                ['firstname', 'product_name', 'promo_end_date', 'new_price', 'discount_percent', 'product_url'],
                true
            ),

            self::row(
                'moderncommerce_new_product',
                self::MC,
                'New Product / Bundle',
                'marketing',
                "New: {product_name} is now open",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>We've just added something we think fits where you're headed: <strong>{product_name}</strong>.</p>
<p>It's built to take you from where you are now to real, usable skill — step by step, with practice that sticks. If you've been looking to level up, this is a clean place to start.</p>
<ul>
  <li>Practical, outcome-focused lessons</li>
  <li>Learn at your own pace, on any device</li>
  <li>Skills you can put to work right away</li>
</ul>
<p><a class="mc-button" href="{product_url}">Explore the course</a></p>
HTML
                ,
                ['firstname', 'product_name', 'product_url'],
                true
            ),

            self::row(
                'moderncommerce_coupon_expiring',
                self::MC,
                'Coupon Expiring',
                'marketing',
                "{firstname}, your code expires {coupon_expiry}",
                <<<'HTML'
<p>Hi {firstname},</p>
<p>Quick reminder — your coupon <strong>{coupon_code}</strong> ({discount_percent} off) expires on <strong>{coupon_expiry}</strong>.</p>
<p>It's already sitting in your account, ready to use. If there's a course you've been meaning to start, this is a low-cost moment to finally make the move toward your goal — before the code disappears.</p>
<p><a class="mc-button" href="{catalog_url}">Browse and use your code</a></p>
HTML
                ,
                ['firstname', 'coupon_code', 'discount_percent', 'coupon_expiry', 'catalog_url'],
                true
            ),
        ];
    }

    /**
     * Section 8 — Admin operational alerts (email digest bodies; Slack/Teams owned by the hub).
     *
     * @return array
     */
    private static function admin_ops(): array {
        return [
            self::row(
                'ops_new_sale',
                self::MC,
                'Ops — New Sale',
                'operational',
                "💰 New sale — {order_total} (order {order_number})",
                <<<'HTML'
<ul>
  <li><strong>Order:</strong> {order_number}</li>
  <li><strong>Amount:</strong> {order_total}</li>
  <li><strong>Customer:</strong> {customer_name}</li>
  <li><strong>Product:</strong> {product_name}</li>
</ul>
<p><a class="mc-button" href="{admin_order_url}">View order</a></p>
HTML
                ,
                ['order_number', 'order_total', 'customer_name', 'product_name', 'admin_order_url']
            ),

            self::row(
                'ops_sales_digest',
                self::MC,
                'Ops — Daily Sales Digest',
                'operational',
                "📊 {period_label}: {revenue_total} · {orders_count} orders",
                <<<'HTML'
<ul>
  <li><strong>Period:</strong> {period_label}</li>
  <li><strong>Revenue:</strong> {revenue_total}</li>
  <li><strong>Orders:</strong> {orders_count}</li>
  <li><strong>Refunds:</strong> {refunds_count}</li>
  <li><strong>New subs:</strong> {new_subs_count}</li>
  <li><strong>Churn:</strong> {churn_count}</li>
</ul>
<p><a class="mc-button" href="{ops_report_url}">Open report</a></p>
HTML
                ,
                ['period_label', 'revenue_total', 'orders_count', 'refunds_count', 'new_subs_count', 'churn_count', 'ops_report_url']
            ),

            self::row(
                'ops_payment_failures',
                self::MC,
                'Ops — Payment Failure Spike',
                'operational',
                "⚠️ {failed_count} failed payments today",
                <<<'HTML'
<ul>
  <li><strong>Failed payments:</strong> {failed_count}</li>
  <li><strong>Period:</strong> {period_label}</li>
  <li><strong>Gateway:</strong> {gateway_name}</li>
  <li><strong>Top reason:</strong> {error_detail}</li>
</ul>
<p><a class="mc-button" href="{admin_dashboard_url}">Investigate failures</a></p>
HTML
                ,
                ['failed_count', 'period_label', 'gateway_name', 'error_detail', 'admin_dashboard_url']
            ),

            self::row(
                'ops_refund_requested',
                self::MC,
                'Ops — Refund / Dispute Requested',
                'operational',
                "🔁 Refund requested — {refund_amount} (order {order_number})",
                <<<'HTML'
<ul>
  <li><strong>Refund:</strong> {refund_amount}</li>
  <li><strong>Order:</strong> {order_number}</li>
  <li><strong>Customer:</strong> {customer_name}</li>
  <li><strong>Reason:</strong> {refund_reason}</li>
</ul>
<p><a class="mc-button" href="{admin_order_url}">Review &amp; act</a></p>
HTML
                ,
                ['refund_amount', 'order_number', 'customer_name', 'refund_reason', 'admin_order_url']
            ),

            self::row(
                'ops_gateway_error',
                self::MC,
                'Ops — Gateway Error',
                'operational',
                "🚨 {gateway_name} failing — check config",
                <<<'HTML'
<ul>
  <li><strong>Gateway:</strong> {gateway_name}</li>
  <li><strong>Error:</strong> {error_detail}</li>
  <li><strong>Failed events:</strong> {failed_count}</li>
  <li><strong>Period:</strong> {period_label}</li>
</ul>
<p><a class="mc-button" href="{admin_dashboard_url}">Check gateway config</a></p>
HTML
                ,
                ['gateway_name', 'error_detail', 'failed_count', 'period_label', 'admin_dashboard_url']
            ),

            self::row(
                'ops_churn_alert',
                self::MC,
                'Ops — Churn Alert',
                'operational',
                "📉 Churn: {customer_name} left {churned_plan}",
                <<<'HTML'
<ul>
  <li><strong>Customer:</strong> {customer_name}</li>
  <li><strong>Plan cancelled:</strong> {churned_plan}</li>
  <li><strong>Churn this week:</strong> {churn_count}</li>
  <li><strong>Period:</strong> {period_label}</li>
</ul>
<p><a class="mc-button" href="{admin_dashboard_url}">View churn</a></p>
HTML
                ,
                ['customer_name', 'churned_plan', 'churn_count', 'period_label', 'admin_dashboard_url']
            ),

            self::row(
                'ops_renewal_forecast',
                self::MC,
                'Ops — Renewal Forecast',
                'operational',
                "🔮 {upcoming_renewals_count} renewals — {upcoming_renewals_value}",
                <<<'HTML'
<ul>
  <li><strong>Renewals (7 days):</strong> {upcoming_renewals_count}</li>
  <li><strong>Renewal value:</strong> {upcoming_renewals_value}</li>
  <li><strong>Current MRR:</strong> {mrr_total}</li>
  <li><strong>Period:</strong> {period_label}</li>
</ul>
<p><a class="mc-button" href="{ops_report_url}">View forecast</a></p>
HTML
                ,
                ['upcoming_renewals_count', 'upcoming_renewals_value', 'mrr_total', 'period_label', 'ops_report_url']
            ),
        ];
    }
}
