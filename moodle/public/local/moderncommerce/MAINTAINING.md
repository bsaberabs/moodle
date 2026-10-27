# Maintaining ModernCommerce

This guide defines the routine engineering and operational practices for maintaining the ModernCommerce core plugin. It complements [PUBLISHING.txt](PUBLISHING.txt), which contains the authoritative release, tag, ZIP, and publication runbook.

## Project authority

- Creator and copyright owner: Adebare Showemimo
- Maintainer: Agunfon Interactivity LLC, USA
- Canonical public source: <https://github.com/adebareshowemimo/moodle-local_moderncommerce>
- Project website and documentation: <https://moderncommerce.dev/>
- Commercial support and managed services: <https://moderncommerce.dev/support>
- Moodle component: `local_moderncommerce`
- Required installation directory: `local/moderncommerce`
- Licence: GPL-3.0-or-later

The public repository is the source of truth for ModernCommerce core. Do not maintain a different private edition of the core. Customer-specific configuration, credentials, deployment infrastructure, private contracts, and premium add-ons must remain outside this repository.

## Repository safety

Before changing any checkout, identify its repository, branch, remote, and working-tree state:

```bash
git status --short --branch
git branch --show-current
git remote -v
git log -1 --oneline
```

Do not assume that a directory named `moderncommerce` points to the canonical repository. Older development checkouts may still point to a legacy repository.

Never repoint, reset, clean, or delete a checkout containing uncommitted work until that work has been reviewed and preserved. In particular, do not use `git reset --hard` or `git clean -fd` as a synchronization shortcut.

## Safe migration from a legacy remote

The safest migration is a clean clone followed by intentional transfer of reviewed changes.

1. Leave the legacy checkout unchanged.
2. Clone the canonical repository into a separate directory:

   ```bash
   git clone https://github.com/adebareshowemimo/moodle-local_moderncommerce.git
   ```

3. In the legacy checkout, inventory changes:

   ```bash
   git status --short
   git diff --stat
   git diff --check
   git log --oneline --decorate --graph -20
   ```

4. Group the intended changes by feature or fix. Commit them on a preservation branch in the legacy repository, or produce reviewed patches. Do not mix generated assets, product changes, documentation, and unrelated cleanup in one transfer.
5. Apply or recreate each focused change in a branch created from the canonical public `main` branch.
6. Run the applicable validation described below.
7. Open a pull request to the public repository and merge after review and CI.
8. Retire the legacy checkout only after every required change is present in the public repository and a backup has been verified.

If an existing clean checkout only has the wrong remote and contains no unique commits, update it explicitly:

```bash
git remote rename origin legacy
git remote add origin https://github.com/adebareshowemimo/moodle-local_moderncommerce.git
git fetch origin --tags
git branch --set-upstream-to=origin/main main
```

Do not run this sequence on a dirty or divergent checkout without first comparing histories and preserving its work.

## Routine development workflow

Start each change from current public `main`:

```bash
git switch main
git pull --ff-only origin main
git switch -c feature/short-description
```

Use a focused prefix that describes the work:

- `feature/` for backward-compatible functionality
- `fix/` for defects
- `security/` for privately coordinated security corrections
- `docs/` for documentation only
- `release/` for release preparation

During implementation:

1. Preserve `local_moderncommerce`, the PHP namespace, language component, external-function names, and installation directory.
2. Keep business logic in autoloaded classes and services rather than page scripts.
3. Add or update tests for material behavior changes.
4. Update language strings, privacy declarations, capabilities, services, events, tasks, and documentation when the contract changes.
5. Rebuild generated frontend artifacts when their source changes.
6. Review the complete diff before staging.

Commit only intentional files:

```bash
git status --short
git diff --check
git diff --stat
git add path/to/intended-file
git commit -m "Describe the change"
git push -u origin feature/short-description
```

Use a pull request for code, database, security, payment, access, or release changes. Direct commits to `main` should be limited to explicitly approved, low-risk repository administration or documentation corrections.

## Product and source contracts

Use executable metadata as the authority:

| Source | Governs |
| --- | --- |
| `version.php` | Component, release, Moodle build requirement, supported versions, and maturity |
| `composer.json` and `composer.lock` | PHP and packaged dependencies, autoloading, and maintenance scripts |
| `LICENSE` | Distribution and modification rights |
| `db/install.xml` | Fresh-install database schema |
| `db/upgrade.php` | Upgrade path for installed sites |
| `db/access.php` | Capabilities and role archetypes |
| `db/services.php` | External-function declarations and access requirements |
| `db/events.php` | Event observers |
| `db/tasks.php` | Scheduled task definitions |
| `lang/en/local_moderncommerce.php` | User-facing terminology |

README files and screenshots are supporting explanations. They do not override the source contracts above.

## Database and metadata maintenance

When changing `db/install.xml`, add the equivalent idempotent upgrade step to `db/upgrade.php`. A schema change that works only for fresh installations is incomplete.

Increase the Moodle build number in `version.php` whenever installed sites must process new definitions, including changes to:

- database schema or seeded defaults;
- capabilities or role behavior;
- scheduled tasks;
- event observers or hooks;
- external functions;
- message providers;
- configuration that requires migration.

Never decrease or reuse the Moodle build number. Preserve orders, payments, invoices, entitlements, subscriptions, customer records, and audit evidence during upgrades.

## External functions and security

Every callable external method must:

1. validate parameters;
2. require the appropriate login state;
3. validate the correct Moodle context;
4. require the matching capability;
5. scope personal records to the authorized user or administrative context;
6. return only the declared response structure.

The functions declared in `db/services.php` primarily support ModernCommerce's shipped AJAX applications. Do not expose or describe them as an unauthenticated general-purpose REST API without a separately designed and reviewed public API contract.

For payment and webhook changes:

- use sandbox accounts during development;
- verify provider signatures before state changes;
- make callback processing idempotent;
- record provider events in the appropriate ledger;
- confirm payment, order, entitlement, enrolment, invoice, and subscription state agree;
- redact secrets and personal data from logs and issue reports.

Report suspected vulnerabilities privately to `support@agunfoninteractivity.com` with the subject `ModernCommerce security report`. Do not open a public issue until disclosure is coordinated and a fix is available.

## Frontend maintenance

Source and generated assets must remain synchronized.

- Edit AMD source under `amd/src` and rebuild Moodle AMD artifacts.
- Edit React/TypeScript source under `js/esm/src` and run the repository's supported React build from the Moodle checkout root.
- Edit SCSS sources under `styles/scss` and run the design-system build.
- Commit required generated assets with their source changes.

From the Moodle checkout root, use the applicable build commands:

```bash
grunt amd --root=public/local/moderncommerce
grunt react --root=public/local/moderncommerce
```

From the plugin root, verify the design system:

```bash
node styles/tools/build-design-system.mjs --check
```

## Validation levels

### Every change

```bash
git diff --check
composer validate --strict --no-check-publish
composer run mc:check-fast
node styles/tools/build-design-system.mjs --check
```

Review the output; a command completing does not make report-only violations acceptable.

### Functional or integration change

Also test in a supported Moodle checkout:

```bash
php public/admin/tool/phpunit/cli/util.php --diag
vendor/bin/phpunit -c phpunit.xml --testsuite local_moderncommerce_testsuite --no-coverage
php public/admin/cli/upgrade.php --non-interactive --allow-unstable
php public/admin/cli/cron.php
php public/admin/cli/purge_caches.php
```

Exercise the affected admin and learner paths with appropriate roles and capabilities.

### Payment, subscription, fulfilment, or access change

Test at least:

- a successful sandbox payment;
- failed, cancelled, duplicate, and delayed callback behavior;
- refund behavior where supported;
- order status transitions;
- entitlement creation and Moodle enrolment;
- subscription renewal, expiry, and access synchronization;
- cron retry behavior;
- invoices, notifications, ledgers, and audit records.

### Release candidate

Run the complete checks in [PUBLISHING.txt](PUBLISHING.txt), then test both a fresh installation and an upgrade from the latest public release.

## Dependency maintenance

Review dependencies deliberately rather than accepting broad automated upgrades.

1. Read upstream release and security notes.
2. Confirm compatibility with the supported PHP and Moodle versions.
3. Update `composer.json` only when constraints should change.
4. Regenerate and review `composer.lock`.
5. Run `composer audit --no-interaction` and the full relevant test level.
6. Include bundled library licences in `thirdpartylibs.xml` where required.

Do not commit merchant credentials, webhook secrets, OAuth tokens, `.env` files, database dumps, customer data, production logs, or private support attachments.

## Documentation and website synchronization

When behavior changes, update all applicable surfaces in the same release cycle:

- repository README and maintainer documents;
- versioned documentation on `moderncommerce.dev`;
- release notes and compatibility statements;
- admin help and language strings;
- screenshots or examples that would otherwise become misleading.

Use <https://moderncommerce.dev/> as the product and documentation destination. Use <https://moderncommerce.dev/support> for implementation, managed services, and commercial support. Agunfon's corporate website is <https://agunfoninteractivity.com/> and its company contact form is <https://agunfoninteractivity.com/contact>.

## Issue and support triage

For every reproducible issue, record:

- ModernCommerce release and Moodle build number;
- Moodle, PHP, database, and browser versions as relevant;
- installation or upgrade history;
- exact reproduction steps;
- expected and actual behavior;
- affected role and capability context;
- relevant sanitized logs;
- payment gateway and sandbox/live mode when applicable.

Classify issues as security, data integrity, payment/access, regression, compatibility, enhancement, documentation, or support request. Handle security privately. Route implementation and managed-service requests to <https://moderncommerce.dev/support>.

## Operational cadence

### Weekly

- Triage new issues and security email.
- Review CI failures and dependency advisories.
- Confirm documentation matches recently merged behavior.

### Monthly

- Run dependency and Composer audits.
- Review supported Moodle and PHP release status.
- Exercise a sandbox purchase and cron-driven subscription/access flow.
- Review stale issues, roadmap status, and support patterns.

### Before every release

- Follow [PUBLISHING.txt](PUBLISHING.txt) completely.
- Verify clean source, version metadata, database upgrades, CI, ZIP contents, and checksum.
- Install the final ZIP on a clean site and upgrade a copy of an existing site.
- Publish one immutable tag and one identical release artifact.

### After every release

- Verify the public release, checksum, and download.
- Confirm the website compatibility and release information.
- Monitor installation, upgrade, checkout, webhook, cron, and access reports.
- Publish a new patch release for corrections; never replace an existing tag or ZIP silently.

## Recovery rules

- Keep database, dataroot, configuration, and source backups aligned before production upgrades.
- Test restoration, not only backup creation.
- Do not fix production data with unreviewed direct SQL when a service or upgrade step can preserve invariants.
- If a release is defective, document it and publish a new patch. Do not move its tag or replace its artifact.
- If a secret reaches Git history, rotate it immediately. Deleting it in a later commit is not sufficient containment.

## Definition of maintained

ModernCommerce is being maintained responsibly when:

- public `main` is the canonical core source;
- changes are reviewed, tested, and documented;
- installed sites have safe upgrade paths;
- payment and learner-access invariants are protected;
- security reports have a private route;
- dependencies and supported platform versions are monitored;
- releases are immutable, reproducible, and install-tested;
- product, documentation, support, and commercial-service links remain consistent.
