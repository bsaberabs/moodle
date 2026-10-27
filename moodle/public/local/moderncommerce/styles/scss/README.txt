Modern Commerce SCSS source
==========================

This folder is the SOURCE OF TRUTH for the Modern Commerce (local_moderncommerce)
mc-* design system. The page-scoped runtime stylesheets are GENERATED from this
SCSS:

- public/local/moderncommerce/styles/design-system.css
- public/local/moderncommerce/styles/bundles/*.css

Do not edit generated CSS by hand (the next compile overwrites it). Edit the
partials here, then recompile.

Structure:
- _foundation.scss: emits the token layer for compiled CSS.
- _tokens.scss: --mc-* source tokens.
- _mixins.scss: shared helpers for focus, panels, buttons, tables and fields.
- _shared-components.scss: mc-* shared components (incl. the generic control
  primitives: btn-icon, action-group, search, select, textarea, checkbox, radio,
  switch, alert).
- thirdparty/: vendored third-party styles included in the generated core CSS.
- runtime/: surface partials (layout, components, learner, buyer, admin,
  responsive, compatibility, apps).
- surfaces/: page and feature-level styles that used to live in standalone
  runtime CSS files.
- global/: tightly scoped styles that Moodle may render outside Modern
  Commerce pages.
- moderncommerce-design-system.scss: Modern Commerce core page entrypoint.
- moderncommerce-*.scss: route/page bundle entrypoints.
- moderncommerce-global.scss: plugin root styles.css entrypoint.
- CSS_AUDIT.txt: current loading policy, grouping map, and migration notes.

BUILD (regenerate the runtime stylesheet) — run from the repository root after
any SCSS change, then purge caches:

    node public/local/moderncommerce/styles/tools/build-design-system.mjs
    php admin/cli/purge_caches.php

Validate generated CSS only (no output written):

    node public/local/moderncommerce/styles/tools/build-design-system.mjs --check

Migration status: COMPLETE (2026-06-12).
1. styles/design-system.css loads on Modern Commerce pages.             [done]
2. Runtime partials extracted from / aligned with styles.css.           [done]
3. Compiled output verified semantically equal to the old styles.css    [done]
   (postcss-normalized, order-independent diff: only cosmetic Sass
   formatting differences — quote style, rgba() whitespace, 0.10 vs 0.1,
   unquoted font names; selector coverage identical).
4. Runtime target switched to the compiled SCSS and browser-verified     [done]
   (Playwright, admin login: buyer catalog, learner dashboard, admin
   dashboard/pricing, and the 3 utility pages all HTTP 200 with zero
   console errors; before/after screenshots visually equivalent).
