# Troubleshooting

Start with the audit command:

```bash
php local/moderncommerce/cli/demo_data.php --audit
```

Then check Moodle logs, web server logs, payment gateway dashboards, and scheduled task history.

## Payments

| Symptom | Check |
| --- | --- |
| Gateway is missing from checkout | Confirm the gateway is enabled in Modern Commerce settings and exists in `local_moderncommerce_gateways`. |
| Payment redirects fail | Confirm HTTPS, return URLs, callback URLs, and gateway credentials. |
| Payment succeeds but order stays pending | Check `local_moderncommerce_payment_events`, scheduled tasks, and gateway callback logs. |
| Refund fails | Confirm the payment attempt has a provider transaction ID and the gateway supports refunds for that payment. |

Do not place live gateway secrets in docs, commits, screenshots, or support tickets.

## Webhooks

Webhook problems usually come from URL, secret, signature, or environment mismatch.

Check:

- production gateway points to the production Moodle URL
- staging gateway points to the staging Moodle URL
- webhook secret matches the configured gateway
- Moodle can receive external HTTP requests
- web server logs show the request
- `local_moderncommerce_webhook_events` records the event

After changing webhook settings, purge Moodle caches and send a test webhook from the provider dashboard when available.

## Spam Protection

Modern Commerce public support and newsletter forms use Moodle core reCAPTCHA only when Moodle's global public and private keys are both configured.

| Symptom | Check |
| --- | --- |
| reCAPTCHA does not appear | Confirm Moodle core reCAPTCHA public and private keys are configured, then purge Moodle caches. Local development sites without keys intentionally omit the challenge. |
| Form says the challenge is required | Confirm the browser is submitting `g-recaptcha-response`. For custom AJAX clients, pass the same token as `recaptcharesponse` if the service expects that field. |
| Challenge fails on production | Confirm the Google reCAPTCHA key is valid for the site's domain and that the server can reach Google's verification endpoint. |
| Challenge loads in one browser but not another | Check content security policy, privacy extensions, ad blockers, and network access to Google's reCAPTCHA script. |

## Cron and Scheduled Tasks

Modern Commerce relies on Moodle cron for cleanup, notifications, reports, subscriptions, abandoned cart recovery, key expiry, and access sync.

Run:

```bash
php admin/cli/cron.php
```

Check:

- cron runs every minute in production
- task failures under `Site administration > Server > Tasks > Scheduled tasks`
- adhoc task queue
- PHP memory/time limits for long report or notification runs

## Entitlements and Access

If a learner paid but cannot access a course:

1. Confirm the order status.
2. Confirm the payment attempt status.
3. Confirm order items exist.
4. Confirm entitlement rows exist.
5. Confirm enrolment/key usage rows when keys are involved.
6. Confirm the Moodle course is visible and enrolment methods are configured.
7. Run cron to process delayed fulfilment or subscription access sync.

For subscription access, also check:

- user subscription status
- plan access rules
- subscription key usage
- expiry and renewal tasks

## Notifications

If emails are not sending:

- confirm Moodle outgoing mail settings
- confirm Modern Commerce notification settings
- confirm email templates are seeded and enabled
- inspect notification queue and notification log tables
- run cron
- run the email test command:

```bash
php local/moderncommerce/cli/test_emails.php --userid=2
```

## Site Root Does Not Open the Storefront

Two settings are required, both under **Site administration > Appearance > Navigation** (`/admin/settings.php?section=navigation`):

- **Enable Home** (`enablemyhome`) ticked. Off by default on a fresh Moodle 5.x site.
- **Start page for users** (`defaulthomepage`) set to **Modern Commerce storefront**.

```bash
php admin/cli/cfg.php --name=enablemyhome
php admin/cli/cfg.php --name=defaulthomepage
```

Expect `1` and `/local/moderncommerce/index.php`. While `enablemyhome` is empty, Moodle core redirects anonymous visitors away from the site root before it reaches the branch that forwards to a URL start page, so the store opens for logged-in users but shows the login page to everyone else. If the **Modern Commerce storefront** option is missing from the dropdown, purge caches: hook registrations are cached. See [Storefront Pages and Widgets](storefront.md).

## Styling and Cached Assets

If CSS or storefront widgets look stale:

```bash
node local/moderncommerce/styles/tools/build-design-system.mjs --check
php admin/cli/purge_caches.php
```

Check:

- generated CSS exists under `local/moderncommerce/styles/bundles/`
- public pages load the bundled Modern Commerce CSS through Moodle page requirements
- browser cache is cleared
- CDN/proxy cache is purged
- SCSS changes were rebuilt before packaging

## Program Progress and Certificates

For program enrollment, certificate prerequisites, grade policies, conversion, and final certificate diagnosis, use [Programs and learning paths](programs-and-learning-paths.md). Its troubleshooting section maps learner symptoms to Moodle completion, gradebook, progress-table, observer, enrollment, and `tool_certificate` checks.

## Demo Data

For local or staging systems:

```bash
php local/moderncommerce/cli/demo_data.php --seed
php local/moderncommerce/cli/demo_data.php --refresh --yes
php local/moderncommerce/cli/demo_data.php --reset-empty --yes
```

Never run reset or refresh commands on production unless the site is intentionally being cleared.

## Documentation Checks

Run:

```bash
composer run mc:docs-check
```

The check verifies MkDocs navigation targets, local Markdown links, and stale component naming in the published documentation set.
