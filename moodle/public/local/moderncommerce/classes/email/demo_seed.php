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
 * Default email template seed spec for Modern Commerce Core Email Templates.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\email;

// phpcs:disable moodle.Files.LineLength -- Seed copy keeps bundled email content auditable.

/**
 * Builds and installs the bundled default templates.
 */
class demo_seed {
    /** @var string Database table. */
    private const TABLE = 'local_moderncommerce_emailtpl';

    /**
     * Seed the bundled templates.
     *
     * Without $force, only missing templates (matched by key) are inserted, so
     * admin edits are preserved. With $force the bundled keys are deleted first
     * and recreated from the spec.
     *
     * @param bool $force Delete the bundled templates first, then recreate.
     * @return int Number of templates inserted.
     */
    public static function seed(bool $force = false): int {
        global $DB;

        $admin = get_admin();
        $creatorid = $admin ? $admin->id : 0;
        $now = time();

        $templates = self::definitions();

        if ($force) {
            $keys = array_column($templates, 'template_key');
            if (!empty($keys)) {
                [$insql, $inparams] = $DB->get_in_or_equal($keys);
                $DB->delete_records_select(self::TABLE, "template_key $insql", $inparams);
            }
        }

        $count = 0;
        foreach ($templates as $t) {
            if ($DB->record_exists(self::TABLE, ['template_key' => $t['template_key']])) {
                continue;
            }
            $record = (object) array_merge($t, [
                'description' => $t['description'] ?? null,
                'locked' => $t['locked'] ?? 1,
                'created_by' => $creatorid,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $DB->insert_record(self::TABLE, $record);
            $count++;
        }

        return $count;
    }

    /**
     * The bundled template definitions (re-tagged to Modern Commerce components).
     *
     * @return array
     */
    private static function definitions(): array {
        $templates = [
            // Purchase / order.
            [
                'template_key' => 'moderncommerce_purchase_student_confirmation',
                'component' => 'local_moderncommerce',
                'name' => 'Purchase Confirmation (Student)',
                'template_type' => 'purchase',
                'subject' => 'Your order #{order_number} is confirmed',
                'body' => self::html_wrap('<h2>Thanks for your purchase, {firstname}!</h2>' .
                    '<p>Your order <strong>#{order_number}</strong> on {order_date} for <strong>{course_name}</strong> is confirmed.</p>' .
                    '<p>Amount: <strong>{order_total}</strong></p>' .
                    '<p>You can access your receipt <a href="{order_view_link}">here</a>.</p>' .
                    '<p>Happy learning,<br>{sitename}</p>'),
                'placeholders' => json_encode(['order_number', 'order_date', 'course_name', 'order_total', 'firstname', 'fullname', 'sitename', 'siteurl']),
            ],
            [
                'template_key' => 'moderncommerce_purchase_instructor_notification',
                'component' => 'local_moderncommerce',
                'name' => 'New Enrollment (Instructor)',
                'template_type' => 'purchase',
                'subject' => 'New enrollment: {fullname} in {course_name}',
                'body' => self::html_wrap('<h2>New enrollment</h2>' .
                    '<p>{fullname} has enrolled in <strong>{course_name}</strong>.</p>' .
                    '<p>Order <strong>#{order_number}</strong> on {order_date}.</p>' .
                    '<p><a href="{course_link}">View course</a></p>'),
                'placeholders' => json_encode(['firstname', 'fullname', 'course_name', 'order_number', 'order_date', 'sitename', 'course_link']),
            ],
            [
                'template_key' => 'moderncommerce_purchase_admin_high_value',
                'component' => 'local_moderncommerce',
                'name' => 'High-Value Order (Admin)',
                'template_type' => 'purchase',
                'subject' => 'High-value order #{order_number} — {order_total}',
                'body' => self::html_wrap('<h2>High-value order detected</h2>' .
                    '<p>Order <strong>#{order_number}</strong> amount: <strong>{order_total}</strong>.</p>' .
                    '<p>Student: {fullname} — Course: {course_name} — Date: {order_date}</p>' .
                    '<p><a href="{order_view_link}">Review order</a></p>'),
                'placeholders' => json_encode(['order_number', 'order_total', 'firstname', 'fullname', 'course_name', 'order_date', 'sitename', 'order_view_link']),
            ],

            // Enrollment.
            [
                'template_key' => 'moderncommerce_enrollment_student_confirmation',
                'component' => 'local_moderncommerce',
                'name' => 'Enrollment Confirmation (Student)',
                'template_type' => 'enrollment',
                'subject' => 'You\'re enrolled in {course_name}',
                'body' => self::html_wrap('<h2>Welcome to {course_name}</h2>' .
                    '<p>Hi {firstname}, you are now enrolled.</p>' .
                    '<p>Enrollment date: {enrollment_date}</p>' .
                    '<p><a href="{course_link}">Go to course</a></p>'),
                'placeholders' => json_encode(['firstname', 'fullname', 'course_name', 'enrollment_date', 'course_link', 'sitename']),
            ],
            [
                'template_key' => 'moderncommerce_enrollment_welcome',
                'component' => 'local_moderncommerce',
                'name' => 'Course Welcome Email',
                'template_type' => 'enrollment',
                'subject' => 'Welcome to {course_name}',
                'body' => self::welcome_body(),
                'placeholders' => json_encode(['firstname', 'fullname', 'course_name', 'course_link', 'sitename', 'siteurl', 'logo', 'supportemail']),
            ],

            // Refund.
            [
                'template_key' => 'moderncommerce_refund_student_processed',
                'component' => 'local_moderncommerce',
                'name' => 'Refund Processed (Student)',
                'template_type' => 'refund',
                'subject' => 'Refund processed for {course_name}',
                'body' => self::html_wrap('<h2>Your refund is processed</h2>' .
                    '<p>Course: <strong>{course_name}</strong></p>' .
                    '<p>Amount: <strong>{refund_amount}</strong></p>' .
                    '<p>Date: {refund_date} — Order #{order_number}</p>' .
                    '<p>Funds may take a few days to appear.</p>'),
                'placeholders' => json_encode(['firstname', 'fullname', 'course_name', 'refund_amount', 'refund_date', 'order_number', 'sitename']),
            ],

            // Subscription (modernsubscription also auto-creates its own on demand).
            [
                'template_key' => 'subscription_activation',
                'component' => 'local_moderncommerce',
                'name' => 'Subscription Activated',
                'template_type' => 'subscription',
                'subject' => '{sitename}: Subscription Activated',
                'body' => self::html_wrap('<h2>Your subscription is active</h2>' .
                    '<p>Hi {firstname}, your <strong>{plan_name}</strong> plan ({billing_cycle}) is now active.</p>' .
                    '<p>Access ends: {subscription_enddate}</p>' .
                    '<pre>{courses_list}</pre>'),
                'placeholders' => json_encode(['firstname', 'fullname', 'plan_name', 'billing_cycle', 'subscription_enddate', 'courses_list', 'sitename', 'logo']),
            ],

            // Contact (Modern Commerce contact core looks these up by key).
            [
                'template_key' => 'contact_autoreply',
                'component' => 'local_moderncommerce',
                'name' => 'Contact Auto-Reply',
                'template_type' => 'contact',
                'subject' => 'We received your message — {sitename}',
                'body' => self::html_wrap('<h2>Thanks for contacting us, {fullname}!</h2>' .
                    '<p>We have received your message and will reply as soon as possible.</p>' .
                    '<p><strong>Your message:</strong></p><blockquote>{message}</blockquote>' .
                    '<p>Kind regards,<br>{sitename}</p>'),
                'placeholders' => json_encode(['fullname', 'email', 'subject', 'message', 'sitename']),
            ],
            [
                'template_key' => 'contact_admin_notify',
                'component' => 'local_moderncommerce',
                'name' => 'Contact Admin Notification',
                'template_type' => 'contact',
                'subject' => 'New contact message from {fullname}',
                'body' => self::html_wrap('<h2>New contact submission</h2>' .
                    '<p><strong>From:</strong> {fullname} ({email})</p>' .
                    '<p><strong>Subject:</strong> {subject}</p>' .
                    '<p><strong>Message:</strong></p><blockquote>{message}</blockquote>'),
                'placeholders' => json_encode(['fullname', 'email', 'subject', 'message', 'sitename']),
            ],
        ];

        // Ten ready-made course-reminder designs (loaded from bundled HTML files).
        $reminderfiles = [
            'coursereminder_compliance_reminder' => [
                'template1_compliance_reminder.html',
                'Compliance Training Reminder',
                'Action Required: Complete Your Compliance Training',
            ],
            'coursereminder_compliance_final' => [
                'template2_compliance_final_reminder.html',
                'Compliance Deadline Approaching (Final Reminder)',
                'URGENT: Final Notice - Compliance Training Deadline',
            ],
            'coursereminder_leadership_invite' => [
                'template3_leadership_invite.html',
                'Leadership Program Invitation',
                'Unlock Your Leadership Potential',
            ],
            'coursereminder_marketing_reengagement' => [
                'template4_marketing_reengagement.html',
                'Marketing Skills Course - Re-engagement',
                'Don\'t Miss Out on Marketing Skills That Drive Results!',
            ],
            'coursereminder_new_manager_onboarding' => [
                'template5_new_manager_onboarding.html',
                'New Manager Onboarding Pathway',
                'Welcome to Leadership - Your Management Journey Starts Here',
            ],
            'coursereminder_communication_skills' => [
                'template6_communication_skills.html',
                'Soft Skills / Communication Program',
                'Master the Art of Communication',
            ],
            'coursereminder_annual_refresher' => [
                'template7_annual_refresher.html',
                'Mandatory Annual Refresher',
                'Time for Your Annual Refresher Training',
            ],
            'coursereminder_milestone_celebration' => [
                'template8_milestone_celebration.html',
                'Learning Milestone Celebration (Progress Nudge)',
                'You\'re Halfway There!',
            ],
            'coursereminder_personalized_recommendation' => [
                'template9_personalized_recommendation.html',
                'Personalized Course Recommendation',
                'A Course Perfectly Matched to Your Goals',
            ],
            'coursereminder_inactive_reactivation' => [
                'template10_inactive_reactivation.html',
                'Re-activation for Inactive Learners',
                'We Miss You! Come Back to Learning',
            ],
        ];

        foreach ($reminderfiles as $key => [$filename, $name, $subject]) {
            $templates[] = [
                'template_key' => $key,
                'component' => 'local_moderncoursereminder',
                'name' => $name,
                'template_type' => 'reminder',
                'subject' => $subject,
                'body' => self::file_template($filename),
                'placeholders' => json_encode(['firstname', 'lastname', 'fullname', 'email', 'course_name', 'course_link']),
            ];
        }

        // Commerce notification templates (the 62 transactional/lifecycle/marketing bodies).
        $templates = array_merge($templates, commerce_seed::definitions());

        // Normalise: every row needs format + status.
        foreach ($templates as &$t) {
            $t['format'] = $t['format'] ?? 'html';
            $t['status'] = $t['status'] ?? 'active';
        }
        unset($t);

        return $templates;
    }

    /**
     * Load a bundled HTML template file.
     *
     * @param string $filename File name within the plugin email_templates dir.
     * @return string HTML content (or a simple fallback).
     */
    private static function file_template(string $filename): string {
        global $CFG;

        $filepath = $CFG->dirroot . '/local/moderncommerce/email_templates/' . $filename;
        if (is_readable($filepath)) {
            return file_get_contents($filepath);
        }

        return self::html_wrap('<h2>Course Reminder</h2>' .
            '<p>Dear {firstname},</p>' .
            '<p>This is a reminder about your course <strong>{course_name}</strong>.</p>' .
            '<p><a href="{course_link}">Continue learning</a></p>');
    }

    /**
     * The richer welcome email body.
     *
     * @return string
     */
    private static function welcome_body(): string {
        return self::html_wrap(
            '<div style="text-align:center;margin-bottom:20px;"><img src="{logo}" alt="{sitename}" style="max-width:200px;height:auto;" /></div>' .
            '<h2 style="color:#333;">Welcome to {course_name}!</h2>' .
            '<p>Dear {firstname},</p>' .
            '<p>Congratulations! You have successfully enrolled in <strong>{course_name}</strong>. ' .
            'We are excited to have you as part of our learning community.</p>' .
            '<div style="text-align:center;margin:30px 0;">' .
            '<a href="{course_link}" style="display:inline-block;padding:15px 40px;background:#667eea;' .
            'color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;">Access Your Course</a>' .
            '</div>' .
            '<p>If you have any questions, contact us at <a href="mailto:{supportemail}">{supportemail}</a>.</p>' .
            '<p>Best regards,<br><strong>The {sitename} Team</strong></p>'
        );
    }

    /**
     * Return body content for the shared email shell.
     *
     * @param string $content Inner HTML.
     * @return string
     */
    private static function html_wrap(string $content): string {
        return $content;
    }

    /**
     * Return an inner content block.
     *
     * The global shell is applied by renderer at send/preview time.
     *
     * @param string $content Inner HTML content block.
     * @param bool $marketing Append the unsubscribe footer (marketing category only).
     * @return string
     */
    public static function wrap(string $content, bool $marketing = false): string {
        return $content;
    }
}
