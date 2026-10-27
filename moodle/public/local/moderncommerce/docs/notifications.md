# Notifications and Email Templates

Modern Commerce includes reusable email templates, outgoing email toggles, a notification queue, contact form email settings, newsletter records, and subscription lifecycle emails.

Use this document when configuring the content of emails, choosing which events send emails, checking placeholders, or troubleshooting delivery.

## Admin Routes

- `/local/moderncommerce/admin/email_templates.php` - shared email template library and outgoing email controls.
- `/local/moderncommerce/admin/notifications.php` - notification queue, operational notification settings, and delivery checks.
- `/local/moderncommerce/admin/contact_emails.php` - contact form autoreply and staff notification email settings.
- `/local/moderncommerce/admin/contacts.php` - submitted contact messages and replies.
- `/local/moderncommerce/admin/newsletter_subscribers.php` - newsletter subscriber list.
- `/local/moderncommerce/admin/subscription_emails.php` - subscription lifecycle email settings.

## Access

Email template management uses email-template capabilities:

| Area | Typical capability |
| --- | --- |
| View shared email templates | `local/moderncommerce:viewemailtemplates` |
| Create, edit, clone, or delete shared email templates | `local/moderncommerce:manageemailtemplates` |
| Configure outgoing commerce email toggles | `moodle/site:config` |
| Configure contact email settings | `moodle/site:config` |
| Configure subscription emails | `local/moderncommerce:managesubscriptionplans` |

Keep email-template editing limited to trusted administrators. Templates can contain HTML and links that buyers will receive.

## Email Template Library

Open `/local/moderncommerce/admin/email_templates.php`, then use the **Templates** view for reusable content.

Template records live in `local_moderncommerce_emailtpl`.

| Field | Meaning |
| --- | --- |
| Template name | Human-readable name shown in the admin library. |
| Template key | Stable unique key used by code and notification settings. Leave blank on new custom templates to auto-generate a key. Existing keys are not editable. |
| Component | Owning component, usually `local_moderncommerce`. Templates owned by unavailable add-ons are shown read-only. |
| Type | Notification category: `transactional`, `reminder`, `dunning`, `celebratory`, `marketing`, or `operational`. |
| Status | `active` templates can be selected and used. `inactive` templates remain stored but should not be used for sending. |
| Subject | Subject line. Placeholders are allowed. |
| Body | HTML or text body. Placeholders are allowed. |
| Placeholders | Metadata list of tokens expected by the template. This helps admins understand the template, but substitution is driven by the actual tokens in subject/body. |

Bundled templates are protected. Clone a bundled template before making a custom version. Delete is blocked for locked templates.

## Outgoing Email Controls

The **Emails** view inside `/local/moderncommerce/admin/email_templates.php` controls the actual commerce email events.

For each outgoing email:

- enable or disable the email event
- choose a reusable template
- edit the event-specific subject/body
- use that email's allowed placeholder list

The outgoing email settings are event controls. The template library is the reusable content library. A good production setup usually keeps reusable branded content in templates and uses event controls only for event-specific wording.

## Template Categories

Modern Commerce uses these canonical categories:

| Category | Use | Suppression |
| --- | --- | --- |
| `transactional` | Orders, receipts, enrolment, keys, invoices, and required buyer notices. | Not marketing-suppressed. |
| `reminder` | Renewal, expiry, access, or payment reminders. | Not marketing-suppressed. |
| `dunning` | Failed payment and recovery flows. | Not marketing-suppressed. |
| `celebratory` | Completion, certificate, or achievement messages. | Not marketing-suppressed. |
| `marketing` | Abandoned cart, promotions, price drops, win-back, and newsletters. | Suppressible and should include unsubscribe links where relevant. |
| `operational` | Admin/store operator alerts. | Not buyer marketing. |

Marketing emails should use `{unsubscribe_url}` when the email is suppressible.

## Placeholder Syntax

Use single-brace tokens:

```text
Hi {firstname}, your order {order_number} is confirmed.
```

The placeholder engine also normalizes legacy double-brace tokens such as `{{firstname}}` to `{firstname}`.

Rules:

- Placeholders can be used in both subject and body.
- Unknown placeholders are left unchanged, which makes missing data visible during preview/testing.
- Placeholder values are HTML-escaped before substitution.
- Global placeholders are merged with event-specific data.
- Template preview uses sample data, not a real order or subscription.
- Some placeholders only exist for specific events. Do not use order placeholders in a subscription email unless that event supplies order data.

## Global Placeholders

These are available in the shared placeholder engine and can be used broadly.

| Token | Meaning |
| --- | --- |
| `{sitename}` | Modern Commerce business name when configured, otherwise Moodle site full name. |
| `{siteurl}` | Moodle site URL. |
| `{supportemail}` | Modern Commerce support email when configured, otherwise Moodle support email. |
| `{logo}` | Site logo URL from Moodle core admin logo settings. |
| `{logo_compact}` | Compact site logo URL from Moodle core admin logo settings. |

Modern Commerce does not expose dark or white logo variants as public template placeholders. Theme-specific logo treatment should be handled in the email shell/design using the Moodle-backed `{logo}` or `{logo_compact}` values.

When a user or course context is available, global rendering can also populate `{firstname}`, `{lastname}`, `{fullname}`, `{email}`, `{course_name}`, `{course_code}`, `{course_link}`, and `{course_summary}`.

## Shared Placeholder Palette

The shared email template editor shows this palette from the core placeholder engine.

| Category | Tokens |
| --- | --- |
| User | `{firstname}`, `{lastname}`, `{fullname}`, `{email}`, `{phone}`, `{city}`, `{country}` |
| Course | `{course_name}`, `{course_code}`, `{course_summary}`, `{course_link}`, `{course_startdate}`, `{course_enddate}`, `{instructor_name}`, `{instructor_email}` |
| Order | `{order_number}`, `{order_date}`, `{order_status}`, `{order_total}`, `{subtotal}`, `{discount}`, `{tax}`, `{currency}`, `{payment_method}` |
| Order extra | `{courses_list}`, `{my_courses_url}`, `{order_view_link}`, `{retry_payment_url}`, `{cart_items}`, `{cart_items_count}`, `{cart_total}`, `{cart_url}`, `{checkout_url}` |
| Coupon | `{coupon_code}`, `{coupon_expiry}`, `{coupon_reject_reason}`, `{coupon_min_spend}`, `{discount_percent}` |
| Refund | `{refund_amount}`, `{refund_reference}`, `{refund_reason}` |
| Access | `{access_enddate}`, `{renew_url}`, `{catalog_url}`, `{certificate_name}`, `{certificate_url}`, `{certificate_expiry}` |
| Program | `{program_name}`, `{completed_courses}`, `{average_grade}`, `{completion_date}`, `{program_url}` |
| Subscription | `{plan_name}`, `{old_plan_name}`, `{new_plan_name}`, `{plan_price}`, `{billing_cycle}`, `{trial_days}`, `{trial_end_date}`, `{subscription_startdate}`, `{subscription_enddate}`, `{next_billing_date}`, `{effective_date}`, `{days_remaining}`, `{days_extended}`, `{courses_list}`, `{my_subscription_url}`, `{renewal_url}`, `{reactivate_url}`, `{update_payment_url}`, `{invoice_url}`, `{winback_coupon}`, `{winback_discount}` |
| Invoice | `{invoice_number}`, `{invoice_total}`, `{invoice_duedate}`, `{invoice_url}`, `{invoice_pdf_url}`, `{pay_invoice_url}`, `{organisation_name}` |
| Keys | `{key_code}`, `{key_count}`, `{key_target_name}`, `{key_expiry}`, `{keys_csv_url}`, `{redeem_url}`, `{seats_total}`, `{seats_used}`, `{seats_remaining}`, `{manager_dashboard_url}` |
| Marketing | `{product_name}`, `{product_url}`, `{old_price}`, `{new_price}`, `{promo_end_date}`, `{unsubscribe_url}` |
| Operations | `{customer_name}`, `{admin_order_url}`, `{admin_dashboard_url}`, `{ops_report_url}`, `{gateway_name}`, `{error_detail}`, `{failed_count}`, `{period_label}`, `{revenue_total}`, `{orders_count}`, `{refunds_count}`, `{new_subs_count}`, `{churn_count}`, `{churned_plan}`, `{mrr_total}`, `{upcoming_renewals_count}`, `{upcoming_renewals_value}` |
| Global | `{sitename}`, `{siteurl}`, `{supportemail}`, `{logo}`, `{logo_compact}` |

## Event-Specific Placeholders

Some older checkout and access emails use event-specific aliases in addition to the shared palette.

| Event | Common additional tokens |
| --- | --- |
| Order confirmation | `{course_count}`, `{my_courses_link}`, `{business_name}`, `{support_email}`, `{support_url}` |
| Payment receipt | `{transaction_id}`, `{payment_date}`, `{amount_paid}`, `{payment_fee}`, `{net_amount}`, `{transaction_reference}`, `{invoice_number}`, `{invoice_link}`, `{course_count}`, `{my_courses_link}`, `{business_name}`, `{support_email}`, `{support_url}` |
| Enrolment confirmation | `{enrollment_date}`, `{enrollment_role}`, `{go_to_course_button}`, `{course_url}`, `{business_name}`, `{support_email}`, `{support_url}` |
| Key redemption | `{enrollment_key}`, `{key_type}`, `{redemption_date}`, `{go_to_course_button}`, `{course_url}`, `{my_courses_link}`, `{business_name}`, `{support_email}`, `{support_url}` |
| Refund confirmation | `{refund_date}`, `{refund_method}`, `{original_amount}`, `{processing_time}`, `{unenrollment_notice}`, `{contact_support_link}`, `{business_name}`, `{support_email}`, `{support_url}` |
| Program completed | `{program_name}`, `{completed_courses}`, `{average_grade}`, `{completion_date}`, `{program_url}`, `{certificate_url}` |

## Program Completion Communication

When a learner first satisfies every configured program prerequisite, completion policy, pass grade, and must-pass rule, Modern Commerce emits `\local_moderncommerce\event\program_completed`. Its observer queues the `moderncommerce_program_completed` celebratory email and in-app notification for that learner.

The notification uses the persisted program progress row as its related record and a stable deduplication key. Reopening the learner page or recalculating completion does not send the message again. Administrators can enable, disable, preview, or edit this event in the shared email-template administration screen.

Use the placeholder chips shown by the specific outgoing email editor as the safest source for that event.

## Contact Email Templates

Open `/local/moderncommerce/admin/contact_emails.php`.

Contact email settings control two emails:

| Email | Recipient | Purpose |
| --- | --- | --- |
| Autoreply | Person who submitted the contact form. | Acknowledge that the message was received. |
| Admin notification | Comma-separated staff recipients, falling back to Moodle support email when empty. | Alert store/support staff about a new contact message. |

Each block can use:

- enabled/disabled state
- selected reusable template
- custom subject override
- custom body override

If a custom subject or body override is present, it is rendered directly. Otherwise Modern Commerce renders the selected reusable template.

Contact placeholders:

| Token | Meaning |
| --- | --- |
| `{fullname}` | Name submitted in the contact form. |
| `{email}` | Email submitted in the contact form. |
| `{subject}` | Contact form subject. |
| `{phone}` | Phone submitted in the contact form, if present. |
| `{message}` | Contact form message. |
| `{submitted_at}` | Submission timestamp. |
| `{sitename}` | Site/store name. |

Global placeholders such as `{siteurl}`, `{supportemail}`, `{logo}`, and `{logo_compact}` can also be used because contact email rendering uses the shared placeholder engine.

## Subscription Email Templates

Open `/local/moderncommerce/admin/subscription_emails.php`.

Subscription email records live in `local_moderncommerce_subscription_emailtpl`.

Subscription email types:

| Type | Default template key |
| --- | --- |
| Activation | `modernsubscription_activation_summary` |
| Renewal | `modernsubscription_renewal_digest` |
| Expiring | `modernsubscription_expiring_reminder` |
| Grace period | `modernsubscription_grace_period` |
| Expired | `modernsubscription_expired` |
| Cancelled | `modernsubscription_cancelled` |
| Payment failed | `modernsubscription_payment_failed` |

Each subscription email can be enabled/disabled, assigned to a shared reusable template, or overridden with a custom subject/body.

Subscription placeholders shown by the subscription email UI:

| Category | Tokens |
| --- | --- |
| User | `{firstname}`, `{lastname}`, `{fullname}`, `{email}`, `{username}` |
| Subscription | `{plan_name}`, `{billing_cycle}`, `{subscription_startdate}`, `{subscription_enddate}`, `{days_remaining}`, `{trial_days}`, `{price}`, `{currency}`, `{courses_list}`, `{renewal_url}`, `{subscription_url}` |
| Global | `{sitename}`, `{siteurl}`, `{logo}`, `{logo_compact}`, `{supportemail}` |

Some subscription flows add extra tokens such as `{grace_end_date}`, `{renew_url}`, `{resubscribe_url}`, or `{my_subscription_url}` when that event needs them.

## Email Shell and Branding

Modern Commerce renders reusable templates through the shared email renderer and shell. Configure the store/business identity in Commerce Settings and Moodle site logos in core Moodle admin settings.

Important branding sources:

- Store/business name: Commerce Settings `business_name`
- Support email: Commerce Settings `support_email`
- Site URL: Moodle `$CFG->wwwroot`
- Logo and compact logo: Moodle core admin logo settings
- Email body: selected template or event-specific custom body

## Notification Queue

Notification records live in:

- `local_moderncommerce_notify_queue`
- `local_moderncommerce_notify_log`
- `local_moderncommerce_notify_digest`
- `local_moderncommerce_notify_identity`
- `local_moderncommerce_notify_suppression`

Cron tasks drain the queue, reap stale processing rows, process digests, and scan for daily reminders. Slack and Teams delivery settings are configured from Moodle's native Modern Commerce plugin settings.

## Contact and Newsletter Storage

Contact forms and newsletter signups are stored in:

- `local_moderncommerce_contacts`
- `local_moderncommerce_contact_replies`
- `local_moderncommerce_subscriber`

Newsletter signup storage is separate from marketing email suppression. Marketing templates should still include `{unsubscribe_url}` where the notification pipeline supplies it.

## Test Emails

Send test emails to the site admin:

```bash
php local/moderncommerce/cli/test_emails.php
```

Send to a specific user:

```bash
php local/moderncommerce/cli/test_emails.php --userid=ID
```

Use this CLI after changing templates, shell branding, SMTP settings, or support email settings. It is especially useful for checking global placeholders such as `{sitename}`, `{siteurl}`, `{supportemail}`, and logo URLs.

## Production Checklist

1. Confirm Moodle SMTP/email sending works.
2. Configure Commerce Settings business name and support email.
3. Configure Moodle site logos if templates use `{logo}` or `{logo_compact}`.
4. Review bundled templates in `/local/moderncommerce/admin/email_templates.php`.
5. Clone bundled templates that need local customization.
6. Keep transactional emails active unless the business has an intentional alternate workflow.
7. Keep marketing emails disabled until consent, suppression, and unsubscribe behavior are verified.
8. Configure contact autoreply and admin notifications.
9. Configure subscription lifecycle emails before selling subscription products.
10. Run `php local/moderncommerce/cli/test_emails.php`.
11. Complete a sandbox purchase and verify order, payment, enrolment, key, refund, and subscription messages as applicable.

## Developer Notes

When adding a new email or placeholder:

1. Add or update the template seed in `classes/email/commerce_seed.php` or the owning service.
2. Add the placeholder to `classes/email/placeholder_engine.php` if it belongs in the shared palette.
3. Ensure the sending code passes the placeholder data before rendering.
4. Add the template to the notification catalog if it should appear in outgoing email controls.
5. Add language strings for labels/descriptions.
6. Update this documentation and run the docs check.
