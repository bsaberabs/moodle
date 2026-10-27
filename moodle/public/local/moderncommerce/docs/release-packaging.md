# Release Packaging

Modern Commerce release packages are built from the plugin root and written to `releases/`.

## Preflight

From `local/moderncommerce`:

```bash
composer validate --no-check-publish
composer run mc:docs-check
composer run mc:check-fast
```

For a full release candidate, run the complete check suite:

```bash
composer run mc:check
```

The full check may run PHPCS, PHP linting, Composer audit, optimized autoload generation, CSS build, AMD build, React build, and PHPUnit diagnostics depending on local tooling.

## CSS and Minification

Design work should keep generated CSS readable. Do not minify committed working CSS during active design iteration.

If a minified production artifact is required, generate it only inside the packaging flow after the SCSS/design-system build and before ZIP creation. Keep the source SCSS and expanded generated CSS as the reviewable design source.

## Build the ZIP

From `local/moderncommerce`:

```bash
composer run mc:package
```

The package script:

- reads `$plugin->release` from `version.php`
- runs the documentation check
- installs Composer production dependencies in a clean temporary package folder with `--no-dev --optimize-autoloader`
- creates `releases/moderncommerce-v<release>.zip`
- verifies the ZIP has one top-level `moderncommerce/` directory
- verifies `moderncommerce/version.php` exists in the ZIP
- verifies `moderncommerce/vendor/autoload.php` exists in the ZIP

End users installing the release ZIP should not need to run Composer. Composer is only required when installing from source, cloning the repository, or building the release package.

To run directly:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/git-package.ps1 -Force
```

## Release Automation

From `local/moderncommerce`:

```bash
composer run mc:release
```

The release script can run checks, build the package, and optionally commit, tag, or push depending on supplied PowerShell flags.

## ZIP Inspection

Before publishing, inspect the archive and confirm:

- top-level folder is `moderncommerce/`
- `version.php`, `db/install.xml`, `db/upgrade.php`, `db/services.php`, and `db/access.php` are present
- `vendor/` is present when Composer dependencies are required at runtime
- `node_modules/`, local environment files, test caches, and release ZIPs are not present
- no gateway secrets, webhook secrets, API keys, access tokens, or personal data are present
- documentation files listed in `mkdocs.yml` are present

## Version Rules

Every submitted package that changes files should update:

- `$plugin->version`
- `$plugin->release`
- release notes
- Git tag and release asset

The package name should match:

```text
moderncommerce-v2.1.1.zip
```
