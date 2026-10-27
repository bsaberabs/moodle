# CLI Reference

Run commands from the repository root unless noted. If you are already inside the Moodle web root, remove the leading `public/` from each command.

## Unified Demo Data Command

```bash
php public/local/moderncommerce/cli/demo_data.php [mode] [options]
```

Modes:

| Mode | Purpose |
| --- | --- |
| `--seed` | Seed the full demo set for a new development/staging installation. |
| `--install-defaults` | Seed safe install defaults: role presets, gateways, emails, storefront widgets. |
| `--seed-role-users` | Create or refresh demo users for each Modern Commerce role and the Moodle Manager role. |
| `--remove-role-users --yes` | Delete only the marked Modern Commerce demo role users. |
| `--reset-empty --yes` | Delete all Modern Commerce table data and seeded Moodle demo courses. |
| `--refresh --yes` | Reset to empty, then seed full demo data. |
| `--audit` | Print Modern Commerce table counts and empty tables. |

Seed options:

| Option | Default | Purpose |
| --- | ---: | --- |
| `--userid=N` | first site admin | User ID for user-scoped demo rows. |
| `--categories=N` | 12 | Demo Moodle course categories. |
| `--courses=N` | 25 | Demo Moodle courses. |
| `--products=N` | 0 | Demo products; `0` means one product per demo course. |
| `--orders=N` | 120 | Demo orders. |
| `--coupons=N` | 12 | Demo coupons. |
| `--keys=N` | 24 | Demo enrolment keys. |
| `--reviews=N` | 4 | Reviews per demo course. |

Examples:

```bash
php public/local/moderncommerce/cli/demo_data.php --install-defaults
php public/local/moderncommerce/cli/demo_data.php --seed
php public/local/moderncommerce/cli/demo_data.php --seed-role-users
php public/local/moderncommerce/cli/demo_data.php --remove-role-users --yes
php public/local/moderncommerce/cli/demo_data.php --refresh --yes
php public/local/moderncommerce/cli/demo_data.php --reset-empty --yes
php public/local/moderncommerce/cli/demo_data.php --audit
```

## Demo Role Accounts

`--seed` and `--seed-role-users` create role preview users with this shared password:

```text
ModernCommerceDemo#2026!
```

| Role preview | Username |
| --- | --- |
| Moodle Manager | `mcdemo_manager` |
| Modern Commerce Administrator | `mcdemo_commerceadmin` |
| Modern Commerce Finance | `mcdemo_finance` |
| Modern Commerce Product Manager | `mcdemo_product` |
| Modern Commerce Reporting Manager | `mcdemo_reporting` |
| Modern Commerce Storefront Manager | `mcdemo_storefront` |
| Modern Commerce Marketing Manager | `mcdemo_marketing` |
| Modern Commerce Support | `mcdemo_support` |
| Modern Commerce Subscription Manager | `mcdemo_subscription` |
| Modern Commerce Payment Operations | `mcdemo_paymentops` |

The cleanup command deletes only users marked with Modern Commerce demo role idnumbers:

```bash
php public/local/moderncommerce/cli/demo_data.php --remove-role-users --yes
```

## Targeted Seed Commands

```bash
php public/local/moderncommerce/cli/seed_storefront.php
php public/local/moderncommerce/cli/seed_storefront.php --reset
php public/local/moderncommerce/cli/seed_subscription_features.php
php public/local/moderncommerce/cli/seed_sample_data.php --reset --with-order
```

`demo_data.php` is preferred for normal setup. Use targeted seed scripts for development and debugging.

## Email Test Command

```bash
php public/local/moderncommerce/cli/test_emails.php
php public/local/moderncommerce/cli/test_emails.php --userid=ID
```

This sends test transactional emails using the configured email templates and branding shell.

## Inspection Helpers

The following scripts are developer diagnostics:

- `cli/inspect_bundles.php`
- `cli/inspect_bundle_files.php`
- `cli/set_bundle_template.php`
