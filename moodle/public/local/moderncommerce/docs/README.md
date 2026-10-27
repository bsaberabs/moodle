# Modern Commerce Documentation

Modern Commerce is a Moodle 5.2 local plugin for running an ecommerce storefront inside Moodle. It supports course products, bundles, programs, subscriptions, checkout, payment gateway callbacks and webhooks, coupons, enrolment keys, invoices, refunds, storefront widgets, notifications, and learner account workflows.

## Who This Documentation Is For

- Moodle administrators installing and configuring the plugin.
- Store managers creating products, bundles, coupons, subscriptions, and storefront pages.
- Developers maintaining the plugin, packaging releases, or extending integrations.
- Support teams diagnosing payments, cron, notifications, cached assets, or access issues.

## Start Here

1. [Installation](installation.md)
2. [Demo role logins](demo-role-logins.md)
3. [Role access guide](role-access.md)
4. [First run and demo data](first-run.md)
5. [Products and pricing](products-and-pricing.md)
6. [Programs and learning paths](programs-and-learning-paths.md)
7. [Payments](payments.md)
8. [Storefront](storefront.md)

## Core Workflows

- [Products and pricing](products-and-pricing.md)
- [Programs and learning paths](programs-and-learning-paths.md)
- [Orders, invoices, refunds, and reports](orders-and-reports.md)
- [Dashboard widgets](dashboard-widgets.md)
- [Coupons and enrolment keys](coupons-and-keys.md)
- [Storefront pages and widgets](storefront.md)
- [Subscriptions](subscriptions.md)
- [Notifications and email templates](notifications.md)
- [Operations and troubleshooting](operations.md)

## Release Workflows

- [Upgrade notes](upgrade-notes.md)
- [Release packaging](release-packaging.md)
- [Moodle Plugins Directory checklist](moodle-plugin-directory.md)
- [Troubleshooting](troubleshooting.md)

## Reference

- [CLI reference](reference/cli.md)
- [Settings reference](reference/settings.md)
- [Capabilities reference](reference/capabilities.md)
- [Scheduled tasks reference](reference/scheduled-tasks.md)
- [Web service reference](reference/web-services.md)
- [Storefront widget reference](reference/widgets.md)
- [Dashboard widget reference](dashboard-widgets.md)
- [Database reference](reference/database.md)

## Documentation Standards

Modern Commerce documentation follows these rules:

- Use `local_moderncommerce` consistently and avoid legacy component names.
- Prefer absolute plugin paths such as `/local/moderncommerce/admin/orders.php` when documenting Moodle routes.
- Keep user guides task-focused and short.
- Keep generated or source-derived material in `docs/reference/`.
- Document command-line examples from the Moodle root unless stated otherwise.
- Avoid publishing secrets, test gateway keys, webhook signing secrets, or personal data in examples.

## Version

This documentation targets Modern Commerce `2.1.8-r7` on Moodle `5.2`.
