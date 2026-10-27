# Upgrade Notes

These notes apply to Modern Commerce `2.1.1` for Moodle `5.2`.

## Compatibility

- Component: `local_moderncommerce`
- Moodle requirement: `2026042000` and supported branch `5.2`
- PHP requirement: `8.3` or later
- Release declared in `version.php`: `2.1.1`

## Before Upgrading

1. Back up the Moodle database.
2. Back up `moodledata`.
3. Back up the current `local/moderncommerce` directory.
4. Confirm cron is healthy before changing files.
5. Confirm payment gateway credentials and webhook secrets are available outside the codebase.
6. On production, do not run demo-data reset commands.

## Upgrade Steps

From the Moodle root:

```bash
php admin/cli/maintenance.php --enable
```

Replace the plugin files in `local/moderncommerce`, then install production Composer dependencies from the plugin directory:

```bash
cd local/moderncommerce
composer install --no-dev --optimize-autoloader
```

Run the Moodle upgrade:

```bash
cd ../..
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
php admin/cli/maintenance.php --disable
```

Run cron after the upgrade:

```bash
php admin/cli/cron.php
```

## After Upgrading

Verify these areas:

- `/local/moderncommerce/admin/index.php`
- `/local/moderncommerce/index.php`
- cart and checkout
- payment callbacks and webhooks
- order detail, invoice, refund, and enrolment flows
- scheduled tasks under `Site administration > Server > Tasks > Scheduled tasks`
- notification queue and logs
- storefront widgets and generated CSS

Run:

```bash
php local/moderncommerce/cli/demo_data.php --audit
composer run mc:docs-check
node local/moderncommerce/styles/tools/build-design-system.mjs --check
```

## Data Notes

Modern Commerce creates and updates tables through `db/install.xml` and `db/upgrade.php`. Upgrade routines must preserve existing products, orders, payments, subscriptions, coupons, keys, notifications, and widget configuration.

Demo reset commands delete Modern Commerce data and seeded demo courses. Use them only on local or staging systems:

```bash
php local/moderncommerce/cli/demo_data.php --reset-empty --yes
php local/moderncommerce/cli/demo_data.php --refresh --yes
```

## Rollback

Rollback requires restoring both code and database state from the same backup point. Do not roll back code only after Moodle has run database upgrades.
