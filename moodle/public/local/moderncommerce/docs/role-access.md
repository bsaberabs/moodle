# Role Access Guide

Modern Commerce uses Moodle roles and capabilities. The plugin does not maintain a separate permission system.

Assign Modern Commerce roles at system context from:

```text
Site administration > Users > Permissions > Assign system roles
```

## Role Access Matrix

| Role | Default access | Not included by default |
| --- | --- | --- |
| Site administrator | Everything in Moodle and Modern Commerce. | None. |
| Moodle Manager / Store Manager | Dashboard, orders, customers, invoices, products/pricing, bundles, subscribers, coupons, keys, storefront pages, widget gallery, contacts, newsletter, reviews, reports, wishlists, documentation. | Commerce settings, categories, payment gateways, webhooks, payment/webhook ledgers, audit log, subscription plans, feature matrix, email template settings, notification channel settings. |
| Modern Commerce Administrator | All Modern Commerce admin areas, including settings, categories, gateways, audit log, subscriptions, storefront, products, orders, reports, notifications, contacts, newsletter, reviews, refunds, and documentation. | Optional add-on pages still require the matching add-on capabilities when those add-ons are installed. |
| Finance | Dashboard, orders, customers, invoices, subscribers, reports, wishlists, refunds, audit log, documentation. | Settings, categories, products, storefront, coupons, keys, payment gateway setup, subscription plan setup, feature matrix. |
| Product Manager | Dashboard, products/pricing, bundles, coupons, course and bundle keys, reviews, reports, wishlists, documentation. | Settings, categories, payment gateways, audit log, subscription plans, feature matrix. |
| Reporting Manager | Dashboard, orders, customers, subscribers, reports, wishlists, audit log, notification delivery log, documentation. | Write access, settings, categories, products, gateways, subscriptions setup. |
| Storefront Manager | Storefront pages, widget gallery, products/pricing, bundles, reviews, documentation. | Settings, categories, payment gateways, orders, audit log, subscription setup. |
| Marketing Manager | Coupons, email templates, contacts, newsletter, documentation. | Settings, categories, products, orders, payment gateways, audit log, subscription setup. |
| Support | Orders, customers, contacts, newsletter, documentation. | Settings, categories, products, gateways, audit log, reports, subscription setup. |
| Subscription Manager | Subscription plans, feature matrix, subscribers, subscriptions, subscription reports, subscription keys, subscription emails, documentation. | Global settings, categories, products, payment gateways, audit log unless separately granted. |
| Payment Operations | Orders, customers, invoices, refunds, reports, wishlists, audit log, payment gateways, webhooks, payment/webhook ledgers, documentation. | Global settings, categories, products, subscription setup. |

Optional add-ons such as Enrolment Notifier and Course Reminders have their own Moodle capabilities. A user must have both the relevant Modern Commerce capability and the add-on capability before those add-on configuration pages appear in the Modern Commerce sidebar.

## Extend an Existing Modern Commerce Role

Use this when an existing preset is close to what you need.

1. Go to `Site administration > Users > Permissions > Define roles`.
2. Open the role, for example `moderncommerceproduct`.
3. Click `Edit`.
4. Search for `local/moderncommerce`.
5. Set the additional capability to `Allow`.
6. Save changes.
7. Ask affected users to log out and log back in.

Common additions:

| Need | Add capability |
| --- | --- |
| Let a role manage catalog categories | `local/moderncommerce:managecategories` |
| Let a role configure payment gateways and webhooks | `local/moderncommerce:configuregateways` |
| Let a role view audit log | `local/moderncommerce:viewauditlog` |
| Let a role manage subscription plans | `local/moderncommerce:managesubscriptionplans` |
| Let a role manage the feature matrix | `local/moderncommerce:managesubscriptionfeatures` |
| Let a role manage global settings and branding | `local/moderncommerce:managesettings` |
| Let a role configure notification channels | `local/moderncommerce:managenotifications` |
| Let a role manage email templates | `local/moderncommerce:manageemailtemplates` |

## Create a New Custom Role

Use this for teams such as regional store manager, compliance reviewer, fulfilment operator, or partner support.

1. Go to `Site administration > Users > Permissions > Define roles`.
2. Click `Add a new role`.
3. Use `No role` or an existing Modern Commerce role as the archetype.
4. Give the role a clear name and shortname, for example `regionalstoremanager`.
5. Set the role context type to `System`.
6. Allow only the required `local/moderncommerce:*` capabilities.
7. Save the role.
8. Go to `Site administration > Users > Permissions > Assign system roles`.
9. Select the new role and assign users.

## Recommended Custom Role Examples

| Role idea | Suggested capabilities |
| --- | --- |
| Regional Store Manager | `viewreports`, `viewallorders`, `manageorders`, `managecourses`, `managecoupons`, `generatekeys`, `viewcontacts`, `managecontacts`. |
| Compliance Reviewer | `viewreports`, `viewallorders`, `viewauditlog`, `viewnotificationlog`. |
| Fulfilment Operator | `viewallorders`, `manageorders`, `generatekeys`, `viewcontacts`. |
| Category Administrator | `managecategories`, `viewreports`. |
| Gateway Administrator | `configuregateways`, `viewreports`, `viewauditlog`, `viewnotificationlog`. |

Capability names in this table omit the `local/moderncommerce:` prefix.

## Check a Role Safely

Use the demo role accounts to preview what a role can see:

```bash
php public/local/moderncommerce/cli/demo_data.php --seed-role-users
```

Remove the preview users after testing:

```bash
php public/local/moderncommerce/cli/demo_data.php --remove-role-users --yes
```

Modern Commerce role presets can be refreshed at any time:

```bash
php public/local/moderncommerce/cli/seed_role_presets.php
```

The role preset seeder creates missing preset roles and adds missing preset capabilities. It does not assign users automatically.
