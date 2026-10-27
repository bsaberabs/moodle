# Capabilities Reference

Modern Commerce uses Moodle's native role and capability system. There is no separate Modern Commerce permission layer.

Site administrators can access every Modern Commerce page and setting because Moodle treats site administrators as site-wide superusers. The seeded custom roles below are normal Moodle roles that a site administrator assigns at system context.

For a page-level access matrix and custom-role instructions, see [Role access guide](../role-access.md).

## Moodle Core Role Defaults

| Moodle role | Modern Commerce access by default |
| --- | --- |
| Site administrator | All Modern Commerce pages, settings, services, and Moodle platform controls. |
| Manager | Store operations access only. Managers can work with orders, customers, invoices, products/pricing, bundles, coupons, keys, storefront pages, contacts, newsletter, reviews, and reports. Managers do not receive commerce settings, category structure, payment gateways, audit logs, subscription plan setup, or feature matrix access by default. |
| Course creator | No Modern Commerce admin access by default. Course creators can still use normal Moodle course-creation workflows. |
| Authenticated user | Buyer and learner actions such as catalog viewing, purchasing, own orders, key redemption, reviews, and own subscription access. |
| Guest | Public catalog, details, and review viewing only. |

## Buyer and Public Capabilities

| Capability | Purpose |
| --- | --- |
| `local/moderncommerce:viewcatalog` | View catalog and prices. |
| `local/moderncommerce:viewdetails` | View course detail pages. |
| `local/moderncommerce:purchase` | Purchase products. |
| `local/moderncommerce:viewownorders` | View own orders. |
| `local/moderncommerce:redeemkey` | Redeem enrolment keys. |
| `local/moderncommerce:viewreviews` | View public reviews. |
| `local/moderncommerce:submitreview` | Submit a review after verified access. |
| `local/moderncommerce:viewownsubscription` | View own subscription. |
| `local/moderncommerce:subscribetoplan` | Purchase or renew subscriptions. |

## Store and Admin Capabilities

| Capability | Purpose |
| --- | --- |
| `local/moderncommerce:managestorefront` | Arrange and configure storefront widgets and public store pages. |
| `local/moderncommerce:managesettings` | Manage Modern Commerce store settings, branding, navigation, and commerce-owned defaults. |
| `local/moderncommerce:viewallorders` | View all orders. |
| `local/moderncommerce:manageorders` | Manage orders, invoices, and order status. |
| `local/moderncommerce:managecourses` | Manage products, bundles, course pricing, and product metadata. |
| `local/moderncommerce:managecategories` | Manage global Modern Commerce product category structure. |
| `local/moderncommerce:managecoupons` | Manage coupons. |
| `local/moderncommerce:generatekeys` | Generate course and bundle enrolment keys. |
| `local/moderncommerce:viewreports` | View commerce reports, dashboard charts, payment events, webhook events, and wishlists. |
| `local/moderncommerce:viewauditlog` | View immutable audit logs. |
| `local/moderncommerce:configuregateways` | Configure payment gateways, webhooks, and payment/webhook event ledgers. |
| `local/moderncommerce:processrefunds` | Process refunds. |

## Reviews, Notifications, Contacts, and Newsletter

| Capability | Purpose |
| --- | --- |
| `local/moderncommerce:managereviews` | Moderate course reviews. |
| `local/moderncommerce:viewemailtemplates` | View email templates, outgoing email settings, and the global email shell. |
| `local/moderncommerce:manageemailtemplates` | Manage email templates, outgoing email settings, and the global email shell. |
| `local/moderncommerce:receivenotificationops` | Receive operational store alerts. |
| `local/moderncommerce:managenotifications` | Configure notification channels and endpoints. |
| `local/moderncommerce:viewnotificationlog` | View notification delivery logs. |
| `local/moderncommerce:viewcontacts` | View contact submissions. |
| `local/moderncommerce:managecontacts` | Reply to and manage contact submissions. |
| `local/moderncommerce:viewnewsletter` | View newsletter subscribers. |
| `local/moderncommerce:managenewsletter` | Manage newsletter subscribers. |

## Subscription Capabilities

| Capability | Purpose |
| --- | --- |
| `local/moderncommerce:managesubscriptionplans` | Create, edit, and delete plans. |
| `local/moderncommerce:viewsubscribers` | View all subscribers. |
| `local/moderncommerce:managesubscriptions` | Activate, suspend, cancel, or manage subscriptions. |
| `local/moderncommerce:viewsubscriptionreports` | View subscription reports. |
| `local/moderncommerce:managesubscriptionfeatures` | Manage plan feature matrix. |

## Seeded Role Presets

Modern Commerce seeds these Moodle role definitions automatically during install and upgrade. It never assigns users automatically.

| Shortname | Intended team | Capabilities |
| --- | --- | --- |
| `moderncommerceadmin` | Commerce administrator | All Modern Commerce admin capabilities: settings, storefront, products, categories, orders, reports, gateways, notifications, contacts, newsletter, reviews, subscriptions, refunds, and audit logs. |
| `moderncommercefinance` | Finance | `viewreports`, `viewsubscriptionreports`, `viewallorders`, `manageorders`, `processrefunds`, `viewauditlog`, `viewsubscribers`. |
| `moderncommerceproduct` | Product manager | `managecourses`, `managecoupons`, `generatekeys`, `managereviews`, `viewreports`. |
| `moderncommercereporting` | Reporting manager | `viewreports`, `viewsubscriptionreports`, `viewallorders`, `viewauditlog`, `viewsubscribers`, `viewnotificationlog`. |
| `moderncommercestorefront` | Storefront manager | `managestorefront`, `managecourses`, `managereviews`. |
| `moderncommercemarketing` | Marketing | `managecoupons`, `viewemailtemplates`, `manageemailtemplates`, `viewcontacts`, `managecontacts`, `viewnewsletter`, `managenewsletter`. |
| `moderncommercesupport` | Support | `viewallorders`, `viewcontacts`, `managecontacts`, `viewnewsletter`. |
| `moderncommercesubscription` | Subscription manager | `managesubscriptionplans`, `managesubscriptionfeatures`, `viewsubscribers`, `managesubscriptions`, `viewsubscriptionreports`. |
| `moderncommercepaymentops` | Payment operations | `configuregateways`, `viewallorders`, `manageorders`, `processrefunds`, `viewreports`, `viewauditlog`, `viewnotificationlog`. |

Capability names in the matrix omit the `local/moderncommerce:` prefix for readability.

## Assignment Instructions

1. Go to `Site administration > Users > Permissions > Assign system roles`.
2. Select the Modern Commerce role, for example `moderncommercefinance`.
3. Assign users at system context.
4. Use `Site administration > Users > Permissions > Define roles` to review or customize the role.

## Demo Role Accounts

Full demo seeding creates preview users for each Modern Commerce role and the Moodle Manager role, so administrators can log in and confirm what each role can see. These accounts are not created by install or upgrade role preset seeding.

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

Create or refresh only the demo role accounts:

```bash
php public/local/moderncommerce/cli/demo_data.php --seed-role-users
```

Remove only the marked demo role accounts:

```bash
php public/local/moderncommerce/cli/demo_data.php --remove-role-users --yes
```

The seeder marks these accounts with `MC-DEMO-ROLE-*` idnumbers. Removal skips any existing user with the same username unless it has the matching Modern Commerce demo marker.

The seeder is conservative:

| Rule | Behaviour |
| --- | --- |
| Existing seeded role | Missing preset capabilities are added. Admin-added capabilities are preserved. |
| Existing unmarked shortname collision | The role is skipped and not modified. |
| Role preset assignments | Install, upgrade, and role preset CLI seeding do not assign users. |
| Demo role account assignments | Full demo seeding and `--seed-role-users` assign only the marked preview users above. |
| Reset/demo commands | Modern Commerce data reset commands do not delete Moodle roles. `--remove-role-users --yes` deletes only marked demo role users. |

## CLI Seeder

Run a dry run:

```bash
php public/local/moderncommerce/cli/seed_role_presets.php --dry-run
```

Seed one role:

```bash
php public/local/moderncommerce/cli/seed_role_presets.php --role=moderncommercefinance
```

Return JSON for automation:

```bash
php public/local/moderncommerce/cli/seed_role_presets.php --json
```

The existing install-defaults command also runs the role seeder:

```bash
php public/local/moderncommerce/cli/demo_data.php --install-defaults
```
