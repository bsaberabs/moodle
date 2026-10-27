# Demo Role Logins

Demo role logins let an administrator preview what each Modern Commerce role can see without manually creating test users.

These accounts are intended for development, staging, sales demos, onboarding, and permissions review. Do not use them as production staff accounts.

For the role-by-role access matrix and customization steps, see [Role access guide](role-access.md).

## Create or Refresh Demo Role Users

Run this from the repository root:

```bash
php public/local/moderncommerce/cli/demo_data.php --seed-role-users
```

If you are already inside the Moodle web root, remove the leading `public/`:

```bash
php local/moderncommerce/cli/demo_data.php --seed-role-users
```

The full demo seed also creates these accounts:

```bash
php public/local/moderncommerce/cli/demo_data.php --seed
```

The command is safe to rerun. It updates the marked demo users, resets the documented password, and keeps one system-context role assignment for each account.

## Login Credentials

All demo role accounts use this password:

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

## What Each Login Shows

- `mcdemo_manager` uses Moodle's core Manager role at system context. It is a store operations preview and does not include settings, category structure, payment gateways, audit logs, subscription plans, or the feature matrix.
- `mcdemo_commerceadmin` uses the Modern Commerce administrator preset and can access all Modern Commerce admin areas.
- The finance, product, reporting, storefront, marketing, support, subscription, and payment operations users use the matching Modern Commerce custom role presets.
- Course creator is not included because Course creator does not receive Modern Commerce admin access by default.

## Cleanup

Remove only the marked Modern Commerce demo role users:

```bash
php public/local/moderncommerce/cli/demo_data.php --remove-role-users --yes
```

If you are already inside the Moodle web root:

```bash
php local/moderncommerce/cli/demo_data.php --remove-role-users --yes
```

The cleanup command deletes only users with Modern Commerce demo role markers such as `MC-DEMO-ROLE-finance`. It skips any normal Moodle user that happens to share one of the demo usernames.

## Safety Notes

- Install and upgrade seed the role definitions only; they do not create demo users.
- `--seed-role-users` creates or updates only the marked preview users.
- `--remove-role-users --yes` deletes only the marked preview users.
- `--reset-empty` and `--refresh` do not delete Moodle role definitions.
