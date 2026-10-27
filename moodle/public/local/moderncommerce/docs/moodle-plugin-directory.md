# Moodle Plugins Directory Checklist

Use this checklist before submitting Modern Commerce to the Moodle Plugins Directory.

## Metadata

- Component remains `local_moderncommerce`.
- Plugin directory is `local/moderncommerce`.
- `$plugin->requires`, `$plugin->supported`, `$plugin->maturity`, and `$plugin->release` are correct in `version.php`.
- `composer.json` declares the GPL license and production dependencies.
- Root `README.md` explains requirements, install steps, first run, and docs entry point.
- `thirdpartylibs.xml` lists bundled third-party libraries, including Bootstrap Icons and Composer dependencies.

## Packaging

- ZIP contains exactly one top-level `moderncommerce/` directory.
- ZIP does not contain `.git/`, `.github/`, `releases/`, `node_modules/`, local environment files, or cache files.
- Runtime Composer dependencies are installed with `--no-dev` before packaging.
- Generated AMD/React/CSS assets are current.
- Documentation check passes before packaging.

## Moodle Code Quality

Run from `local/moderncommerce`:

```bash
git diff --check
composer run mc:docs-check
composer run mc:check-fast
```

For final submission:

```bash
composer run mc:check
```

Reviewers commonly check:

- Moodle GPL boilerplate headers on PHP, Mustache, JavaScript, and CSS source files
- Moodle PHPCS compliance
- no global functions without the `local_moderncommerce_` prefix
- no redundant manual loading of Moodle-aggregated `styles.css`
- no direct access to scripts without the correct Moodle guards
- no missing language strings
- no development debug output

## Security

- All external services validate parameters.
- All external services validate context and require login where needed.
- Admin services require appropriate capabilities from `db/access.php`.
- Public services expose only intentional public data.
- Payment callbacks and webhooks validate provider payloads and do not expose secrets.
- User input uses specific Moodle parameter types instead of raw input unless raw payloads are unavoidable.
- File uploads validate context, component, filearea, and capability.
- Privacy provider coverage is reviewed for stored user data.

## Database and Tasks

- New installs work from `db/install.xml`.
- Upgrades work from `db/upgrade.php`.
- Scheduled tasks in `db/tasks.php` are documented and have clear failure behavior.
- Capabilities in `db/access.php` are documented.
- Web services in `db/services.php` are documented and treated as API surface.

## User Experience

- Public catalog, product detail, cart, checkout, learner pages, and admin pages are mobile-first.
- Storefront widgets are previewable in the gallery.
- Empty states do not require demo data.
- Demo data commands are clearly marked for local/staging use.
- Errors explain the next action without exposing internals.

## Submission Notes

Prepare:

- release ZIP
- release notes
- screenshots of catalog, checkout, admin dashboard, storefront gallery, learner account, and subscriptions
- installation and configuration documentation links
- test notes for payments, cron, notifications, and enrolment
