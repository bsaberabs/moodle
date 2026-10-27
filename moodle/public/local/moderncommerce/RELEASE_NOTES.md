# ModernCommerce release notes

## ModernCommerce 2.3.0

ModernCommerce 2.3.0 is a major feature release focused on multi-page
storefronts, richer storefront design tools, and more reliable recurring
subscription processing.

### Multi-page storefront management

Administrators can now create and manage multiple public storefront pages
instead of relying on a single fixed storefront.

This release adds:

- Store page creation, editing, listing, and deletion.
- Unique, validated page slugs for clean public URLs.
- Page status and visibility controls.
- Public page routing through the new `page.php` endpoint.
- Store page lifecycle events for creation, updates, and deletion.
- New external services for page-management operations.
- Persistent database storage for storefront page definitions.
- Upgrade support for existing ModernCommerce installations.

These changes make it possible to build dedicated landing pages, campaign
pages, product collections, subscription pages, and other targeted storefront
experiences.

### New storefront widgets

Three new storefront widget types are included.

#### Feature Grid

Displays product benefits, platform capabilities, or service features in a
structured grid. Each item can include an icon, heading, and supporting
description.

#### Leader Message

Provides a prominent editorial section for a founder, instructor, executive,
or community leader. It supports portrait imagery, attribution details, calls
to action, and configurable presentation styles.

#### Subscription Plans

Displays available subscription plans directly within the storefront. The
widget resolves live plan information and supports pricing, billing-cycle,
feature, badge, and call-to-action presentation options.

The PHP rendering layer, React storefront renderer, widget editor, gallery,
schema definitions, presets, and documentation were updated together for all
three widgets.

### Improved widget design system

The storefront editor now provides a broader and more consistent set of visual
controls.

Enhancements include:

- Expanded widget field schemas.
- Additional typography, color, spacing, radius, border, and alignment
  controls.
- Improved preset sanitization and preservation.
- A reusable icon picker.
- Better preset compatibility between PHP and React rendering.
- Updated storefront and administrative gallery styling.
- Improved responsive presentation across storefront surfaces.

Automated contract tests verify that supported widgets remain synchronized
across the widget registry, schema, gallery, presets, and rendering layers.

### Recurring subscription improvements

ModernCommerce 2.3.0 significantly strengthens recurring payment and
subscription lifecycle handling.

Changes include:

- Reusable payment-mandate support.
- Improved recurring-payment processing for Stripe and Paystack.
- Better handling of trial expiration and subscription renewal states.
- More reliable processing of expired subscriptions.
- Improved payment-observer behavior.
- Subscription lifecycle email notifications.
- Additional subscription API operations.
- A public subscription-plan entry point.
- New CLI tooling for inspecting and managing subscription data.

The implementation keeps financial reconciliation tasks controllable through
Moodle scheduled-task configuration.

### Storefront performance and caching

A new storefront cache definition reduces repeated work when resolving public
storefront content. Cache invalidation is connected to page-management
operations so updated pages can be presented without retaining stale
definitions.

### Database and service changes

This release includes database schema and Moodle service-definition changes.

The upgrade introduces storage and metadata required for:

- Multiple storefront pages.
- Store page slugs and page configuration.
- New page-management web services.
- New storefront widgets.
- Subscription processing improvements.
- Storefront cache definitions.

Existing installations must complete Moodle's normal plugin upgrade process
after replacing the plugin files.

### Developer and administrative tooling

The release also includes:

- Store page lifecycle events for integrations and observers.
- Expanded storefront documentation.
- Updated widget reference documentation.
- Subscription data CLI utilities.
- Widget contract PHPUnit tests.
- A React/PHP editor parity audit.
- Regenerated production JavaScript and CSS assets.
- Improved Moodle coding-standard compliance.

### Compatibility

- Moodle component: `local_moderncommerce`
- Release: `2.3.0`
- Moodle build number: `2026081806`
- Requires Moodle 5.2 or later.
- Tested against Moodle 5.2.
- Maturity: Stable.

### Upgrade instructions

1. Back up the Moodle database and dataroot.
2. Replace `local/moderncommerce` with the contents of the release ZIP.
3. Visit **Site administration > Notifications**, or run:

   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```

4. Purge Moodle caches:

   ```bash
   php admin/cli/purge_caches.php
   ```

5. Review scheduled tasks and payment-gateway settings.
6. Verify existing storefront pages, subscriptions, checkout, and recurring
   payment behavior in a test environment.

### Validation

The release passed:

- Git whitespace validation.
- Moodle PHP coding-standard checks.
- PHP syntax validation.
- Composer metadata validation.
- Composer security auditing.
- Optimized autoloader generation.
- Generated design-system CSS verification.
- Widget editor parity auditing.
- Focused PHPUnit testing: 6 tests and 541 assertions.
- GitHub Actions checks and release packaging.
- Release ZIP structure and metadata validation.
- Post-upload asset checksum verification.

### Release artifact

- Commit: `a479edc70aa22fea89a746774240480702e1b9b1`
- Tag: `v2.3.0`
- ZIP: `moderncommerce-v2.3.0.zip`
- SHA-256: `7B5ABA9CC5E6C9FB01D315C15B22F024D3371D24ACB4FEF8DD7AAC6BD7DC6AC4`
- [GitHub release](https://github.com/adebareshowemimo/moodle-local_moderncommerce/releases/tag/v2.3.0)
- [Successful GitHub Actions run](https://github.com/adebareshowemimo/moodle-local_moderncommerce/actions/runs/32141933655)

No known release-blocking issues were identified.
