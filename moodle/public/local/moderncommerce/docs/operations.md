# Operations and Troubleshooting

## Common Checks

Run from the Moodle root unless stated otherwise.

```bash
php admin/cli/cron.php
php local/moderncommerce/cli/demo_data.php --audit
node local/moderncommerce/styles/tools/build-design-system.mjs --check
```

From the plugin directory:

```bash
composer run mc:docs-check
composer run mc:check-fast
composer run mc:css-audit
composer run mc:icon-audit
composer run mc:perf-report
```

## Learner Dashboard Routing

Authenticated requests to Moodle's core `/my` dashboard are redirected to
`/local/moderncommerce/learner/index.php`. The redirect is registered through
the `before_http_headers` hook in `db/hooks.php`; guests continue through
Moodle's normal login flow.

## Payments

If payment status is not updating:

1. Confirm HTTPS and gateway callback/webhook URLs.
2. Check `/local/moderncommerce/admin/payment_events.php`.
3. Check `/local/moderncommerce/admin/webhook_events.php`.
4. Confirm Moodle cron is running.
5. Confirm gateway credentials and signing secrets.

## Storefront Styling

If design changes do not appear:

1. Rebuild generated CSS:

   ```bash
   node local/moderncommerce/styles/tools/build-design-system.mjs
   ```

2. Purge Moodle caches.
3. Confirm route CSS is loaded by the central hook in `classes/hook/callbacks.php`.
4. Do not add page-level `$PAGE->requires->css()` calls.

## Demo Data

For staging:

```bash
php local/moderncommerce/cli/demo_data.php --refresh --yes
```

For production:

- Do not run `--refresh` or `--reset-empty`.
- Use admin pages to create real products, plans, coupons, and settings.

## Documentation Checks

From `local/moderncommerce`:

```bash
composer run mc:docs-check
```

The docs check validates internal Markdown links and MkDocs navigation paths.
