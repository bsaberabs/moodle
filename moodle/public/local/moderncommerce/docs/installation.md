# Installation

## Requirements

- Moodle 5.2.
- PHP 8.3 or later.
- Composer available during installation or packaging.
- HTTPS in production, especially for payment gateway redirects and webhooks.
- Moodle cron configured.

## Install the Plugin

1. Copy the plugin to:

   ```text
   local/moderncommerce
   ```
2. Install PHP dependencies from the plugin directory:

   ```bash
   cd local/moderncommerce
   composer install --no-dev --optimize-autoloader
   ```

3. Run the Moodle upgrade:

   ```bash
   php ../../admin/cli/upgrade.php
   ```

4. Open Moodle site administration and confirm the plugin appears under:

   `Site administration > Plugins > Local plugins > Modern Commerce`

## Configure Core Settings

Open:

`Site administration > Plugins > Local plugins > Modern Commerce > Settings`

Set at least:

- primary currency and currency formatting
- tax behavior
- enabled payment gateways
- checkout billing fields
- notification sender/support details
- storefront/navigation labels

## Make the Storefront the Site Start Page

Optional, but required if the site root should open the store instead of Moodle's front page. Both settings are on **Site administration > Appearance > Navigation** (`/admin/settings.php?section=navigation`):

1. Tick **Enable Home** (`enablemyhome`). Required, and **off by default** on a fresh Moodle 5.x site. Without it, Moodle core sends anonymous visitors from the site root to the login page before the storefront redirect runs.
2. Set **Start page for users** (`defaulthomepage`) to **Modern Commerce storefront**.

```bash
php admin/cli/cfg.php --name=enablemyhome --set=1
php admin/cli/cfg.php --name=defaulthomepage --set=/local/moderncommerce/index.php
php admin/cli/purge_caches.php
```

Also confirm the storefront is reachable without a session: **Force users to log in** (`forcelogin`) off, and the visitor role holding `local/moderncommerce:viewcatalog`. Full detail is in [Storefront Pages and Widgets](storefront.md).

## Configure Cron

Modern Commerce uses scheduled tasks for cart cleanup, key expiration, abandoned cart recovery, reports, notification queue processing, and subscriptions.

Run Moodle cron regularly:

```bash
php admin/cli/cron.php
```

For production, run cron every minute.

## Seed Install Defaults

For a new installation, seed safe defaults such as gateways, email templates, and storefront widgets:

```bash
php local/moderncommerce/cli/demo_data.php --install-defaults
```

This is different from full demo data. It is intended as a starting configuration for a real site.

## Verify

1. Visit `/local/moderncommerce/index.php`.
2. Visit `/local/moderncommerce/admin/index.php` as a manager.
3. Run:

   ```bash
   php local/moderncommerce/cli/demo_data.php --audit
   ```

4. Confirm generated CSS is current:

   ```bash
   node local/moderncommerce/styles/tools/build-design-system.mjs --check
   ```
