# First Run and Demo Data

Modern Commerce has one primary CLI for installing defaults, seeding demo data, auditing table coverage, refreshing demo data, and resetting to empty.

Run commands from the Moodle web root—the directory that contains `admin/`, `course/`, and `local/`. In a split checkout where the web root is named `public`, either change into `public` first or include `public/` in the script path.

```bash
# From the Moodle web root.
php local/moderncommerce/cli/demo_data.php --help

# From the checkout root when Moodle is under public/.
php public/local/moderncommerce/cli/demo_data.php --help
```

## Install Defaults

Use this on a new site before real configuration:

```bash
php local/moderncommerce/cli/demo_data.php --install-defaults
```

This creates or synchronizes built-in gateway records, Modern Commerce role presets, email templates, subscription email templates, the email shell, and storefront widgets. It does not create fake Moodle courses, products, customers, or orders, so it is the appropriate mode for a production installation.

## Open the Site on the Storefront

Installing the plugin does not change where Moodle sends visitors. To make the storefront the page people land on at the site root, set both of these under **Site administration > Appearance > Navigation** (`/admin/settings.php?section=navigation`):

1. Tick **Enable Home** (`enablemyhome`). Required, and **off by default** on a fresh Moodle 5.x site.
2. Set **Start page for users** (`defaulthomepage`) to **Modern Commerce storefront**.

```bash
php admin/cli/cfg.php --name=enablemyhome --set=1
php admin/cli/cfg.php --name=defaulthomepage --set=/local/moderncommerce/index.php
php admin/cli/purge_caches.php
```

Skipping **Enable Home** is the usual cause of a site that opens the storefront for logged-in users but shows the login page to everyone else: Moodle core redirects anonymous visitors away from the site root before the storefront redirect is reached. Verify the site root in both a logged-out and a logged-in session after saving.

See [Storefront Pages and Widgets](storefront.md) for the anonymous-access requirements that go with this (`forcelogin`, the visitor role capability, and per-widget audience).

## Full Demo Seed

Use this only on development, staging, sales-demo, or disposable test sites:

```bash
php local/moderncommerce/cli/demo_data.php --seed
```

With no count options, the command requests 12 Moodle categories, 25 Moodle courses, one product per course, 120 orders, 12 coupons, 24 enrolment keys, and four reviews per course. It also creates bundles, prices, product metadata, subscription plans and features, lifecycle records, storefront content, reports, and demo role accounts.

### Bash, Linux, macOS, and Git Bash

Use a backslash (`\`) as the final character on each continued line:

```bash
php local/moderncommerce/cli/demo_data.php --seed \
  --categories=12 \
  --courses=25 \
  --orders=120 \
  --coupons=12 \
  --keys=24 \
  --reviews=4
```

Do not use PowerShell backticks in Bash. If Bash prints `--categories=12: command not found`, the first command may still have completed with default values, but the option lines were executed as separate commands.

### Windows PowerShell

Use a backtick (`` ` ``) as the final character on each continued line:

```powershell
php local/moderncommerce/cli/demo_data.php --seed `
  --categories=12 `
  --courses=25 `
  --orders=120 `
  --coupons=12 `
  --keys=24 `
  --reviews=4
```

Do not place spaces after a Bash backslash or PowerShell backtick. Alternatively, put the entire command on one line.

### Seed options

| Option | Default | Purpose |
| --- | ---: | --- |
| `--userid=N` | First site administrator | Owner for user-scoped sample records. |
| `--categories=N` | `12` | Number of `MCDEMO-CAT-*` Moodle course categories. |
| `--courses=N` | `25` | Number of `MCDEMO-COURSE-*` Moodle courses. |
| `--products=N` | `0` | Product count; `0` creates one product per demo course. |
| `--orders=N` | `120` | Number of orders with varied lifecycle states. |
| `--coupons=N` | `12` | Number of sample coupon definitions. |
| `--keys=N` | `24` | Number of sample enrolment keys. |
| `--reviews=N` | `4` | Requested reviews per course; `0` disables reviews. Actual reviews are limited by the number of usable Moodle users. |

The full seed also creates ten marked role-preview users. Their usernames and shared demo password are documented in [Demo role logins](demo-role-logins.md).

### Reading the result

A successful run prints these groups:

- **Install defaults**: gateways, role presets, email templates, and storefront widgets.
- **Catalog/order sample**: Moodle categories and courses, products, bundles, coupons, keys, reviews, and orders.
- **Subscription matrix**: plans, features, and enabled mappings.
- **Supplemental lifecycle groups**: checkout, marketing, contact, notification, subscription, and report records.
- **Demo role accounts**: created or updated role-preview users.
- **Table coverage audit**: row counts for every Modern Commerce table and a list of empty tables.

An empty optional table does not by itself mean the seed failed. For example, the review-reaction table can remain empty when there are not enough distinct reviewers to generate reactions. Treat a non-zero process exit code, a PHP exception, or an explicit `cli_error` message as a failure.

## Audit Data Coverage

```bash
php local/moderncommerce/cli/demo_data.php --audit
```

Use this after install or after demo seeding to see which Modern Commerce tables have data.

## Refresh Demo Data

This deletes existing Modern Commerce table data and `MCDEMO-*` Moodle courses/categories, then seeds again:

```bash
php local/moderncommerce/cli/demo_data.php --refresh --yes
```

You can pass the same count options used by `--seed`:

```bash
php local/moderncommerce/cli/demo_data.php --refresh --yes \
  --categories=12 \
  --courses=25 \
  --orders=120 \
  --coupons=12 \
  --keys=24 \
  --reviews=4
```

Use `--refresh` when you need the requested counts applied to a clean demo dataset. Use it only on development or staging sites.

## Reset to Empty

This clears Modern Commerce table data and seeded Moodle demo courses:

```bash
php local/moderncommerce/cli/demo_data.php --reset-empty --yes
```

This is destructive. Do not run it against production unless you deliberately intend to remove all Modern Commerce table data. It does not delete the Moodle role definitions.

## Demo Role Accounts Only

Create or update the marked role-preview users without seeding the full catalog:

```bash
php local/moderncommerce/cli/demo_data.php --seed-role-users
```

Remove only those marked users:

```bash
php local/moderncommerce/cli/demo_data.php --remove-role-users --yes
```

See [Demo role logins](demo-role-logins.md) for usernames, credentials, and the role-access summary.

## Related Seed Commands

The unified command above is preferred. These scripts remain useful for targeted development work:

- `php local/moderncommerce/cli/seed_storefront.php`
- `php local/moderncommerce/cli/seed_storefront.php --reset`
- `php local/moderncommerce/cli/seed_subscription_features.php`
- `php local/moderncommerce/cli/test_emails.php --userid=ID`
