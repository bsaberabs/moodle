# ModernCommerce for Moodle

ModernCommerce is an open-source commerce platform built natively for Moodle. It lets training providers sell courses, bundles, programs, and subscriptions without requiring a separate WordPress or ecommerce installation.

ModernCommerce was created by Adebare Showemimo and is maintained by [Agunfon Interactivity LLC, USA](https://agunfoninteractivity.com). Official implementation, managed services, and commercial support are available through [ModernCommerce support](https://moderncommerce.dev/support).

## Features

- Product catalog for courses, bundles, programs, and subscription plans
- Configurable prices, sale pricing, inventory, categories, tags, and attributes
- Cart, checkout, orders, invoices, refunds, coupons, and tax handling
- Stripe, PayPal, Paystack, and Flutterwave payment gateways
- Signed payment webhooks, payment-event tracking, and diagnostic logs
- Automatic Moodle enrolment, access entitlements, and prepaid enrolment keys
- Subscription trials, recurring billing, retries, plan changes, and access synchronization
- Configurable storefront pages, widgets, branding, and email templates
- Learner dashboards for courses, certificates, grades, orders, wishlists, and subscriptions
- Reviews, notifications, abandoned-cart recovery, reports, and audit logs
- [22 configurable admin-dashboard widgets](https://github.com/adebareshowemimo/moodle-local_moderncommerce/blob/main/docs/dashboard-widgets.md): 4 KPI tiles and 18 analytics or table widgets
- Optional integrations for Course Reminder and Enrolment Notifier add-ons

## Requirements

- Moodle 5.2
- PHP 8.3 or later
- Composer 2
- Moodle cron configured
- HTTPS for production payment gateways and webhooks

The Moodle component name is `local_moderncommerce`. The current plugin release is `2.3.0`.

## Installation

1. Place the plugin in `local/moderncommerce` within the Moodle installation.
2. Install production dependencies from the plugin directory:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Visit Moodle site administration to complete the installation, or run:

   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

4. Configure the plugin under `Site administration > Plugins > Local plugins > Modern Commerce`.
5. Ensure Moodle cron runs regularly before enabling payments or subscriptions.

For a new installation, seed safe default configuration with:

```bash
php local/moderncommerce/cli/demo_data.php --install-defaults
```

Use `--seed` only on demo or staging systems. Never reset or seed sample orders on a production site.

## Documentation

- [Documentation index](docs/README.md)
- [Dashboard widgets: count, purpose, calculations, and customization](docs/dashboard-widgets.md)
- [Orders, invoices, refunds, and reports](docs/orders-and-reports.md)
- [Storefront widget reference](docs/reference/widgets.md)

## Development

From the plugin directory:

```bash
composer install
composer run mc:docs-check
composer run mc:check-fast
node styles/tools/build-design-system.mjs --check
```

React and AMD assets are built from the Moodle checkout root with Moodle's Grunt tasks.

The complete maintainer procedure for versioning, validating, packaging, tagging, and publishing releases is in [PUBLISHING.txt](PUBLISHING.txt).

Routine repository, dependency, security, testing, documentation, and operational maintenance is documented in [MAINTAINING.md](MAINTAINING.md).

## Community and support

- Project website and documentation: [moderncommerce.dev](https://moderncommerce.dev/)
- Implementation, managed services, and commercial support: [moderncommerce.dev/support](https://moderncommerce.dev/support)
- Voluntary open-source project support: [moderncommerce.dev/support-development](https://moderncommerce.dev/support-development) or [support ModernCommerce on Ko-fi](https://ko-fi.com/moderncommerce)
- Maintainer website: [agunfoninteractivity.com](https://agunfoninteractivity.com/)
- Contact Agunfon Interactivity: [agunfoninteractivity.com/contact](https://agunfoninteractivity.com/contact)
- General support: `support@agunfoninteractivity.com`
- Security reports: `support@agunfoninteractivity.com` with the subject `ModernCommerce security report`

Please do not disclose suspected security vulnerabilities in a public issue. Include the affected version, reproduction steps, potential impact, and whether the issue is already public.

Community support is provided on a best-effort basis. Paid response times, installation assistance, migrations, custom integrations, and operational support are available separately through [ModernCommerce support](https://moderncommerce.dev/support).

Ko-fi support is voluntary. It does not purchase technical support, professional services, roadmap priority, governance rights, or a tax-deductible charitable contribution.

## Contributing

Bug reports and focused pull requests are welcome. Before proposing a large feature, open a discussion describing the use case and intended architecture. Contributions must:

- Follow Moodle coding and security standards
- Preserve the `local_moderncommerce` component and namespace
- Keep optional add-ons safely gated when they are not installed
- Include tests for material behavior changes
- Be submitted under GPL v3 or later

By contributing, you certify that you have the right to submit the work and agree that it may be distributed under this project's licence.

## Project stewardship

ModernCommerce is an open-source project maintained by Agunfon Interactivity LLC, USA. **ModernCommerce** identifies the official project and product. Commercial services are provided by Agunfon Interactivity LLC, USA through [moderncommerce.dev](https://moderncommerce.dev/). The GPL permits forks and redistribution of the code, but it does not grant permission to imply endorsement by Agunfon Interactivity LLC, USA or to present a fork as an official ModernCommerce release.

## Licence

ModernCommerce is free software licensed under the [GNU General Public License v3 or later](LICENSE).

Copyright © 2025–2026 Adebare Showemimo. Maintained by Agunfon Interactivity LLC, USA.
