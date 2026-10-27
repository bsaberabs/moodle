# Storefront Pages and Widgets

The storefront is the public-facing Modern Commerce experience. It includes landing pages, catalog pages, detail pages, public content pages, and configurable widgets.

## Public Routes

- `/local/moderncommerce/index.php` - storefront/catalog entry
- `/local/moderncommerce/course_details.php?id=ID` - course detail
- `/local/moderncommerce/bundle_details.php?id=ID` - bundle/program detail
- `/local/moderncommerce/pricing.php` - Modern Commerce value/pricing page
- `/local/moderncommerce/about.php`
- `/local/moderncommerce/support.php`
- `/local/moderncommerce/privacy.php`
- `/local/moderncommerce/terms.php`
- `/local/moderncommerce/refund-policy.php`
- `/local/moderncommerce/page.php?slug=SLUG` - any admin-created custom page

## Custom Store Pages

The six routes above ship with the plugin. Administrators can add their own public pages
(for example a Services or Promotions page) from **Store pages** in the admin console,
without a developer or a plugin release.

A custom page is a row in `local_moderncommerce_storepage` (slug, title, summary, icon,
layout template, published flag). Every one of them is served by the single dispatcher
`page.php?slug=SLUG`; no PHP file is generated. `public_page::page_catalog()` merges these
rows over the built-in six, so custom pages pick up the widget builder, breadcrumbs, the
publish toggle and the admin table with no extra wiring.

### One renderer for every public page

Built-in and custom pages share the same renderer, so there is one place to change page
chrome rather than seven:

- `public_page::render_page($key, $OUTPUT)` - publish check, `$PAGE` setup, then the widget
  render. Each built-in entry file (`about.php`, `terms.php`, `privacy.php`,
  `refund-policy.php`) is now a three-line delegate to this, and `page.php` calls it too
  after resolving the slug.
- `public_page::setup_page($key)` - the same publish check and `$PAGE` setup without the
  render, returning the resolved title. Used by `support.php`, which must handle its form
  POST (and possibly redirect) between setup and render.

Page titles come from `page_catalog()` and are overridden per page by the
`publicpage_<key>_title` config value, so no entry file hardcodes an English title.

Two layout templates decide which widget zones a page exposes:

| Template | Zones |
| --- | --- |
| `content` | `page_hero`, `page_main`, `page_aside`, `page_footer` |
| `landing` | `home_announce`, `home_hero`, `home_belowhero`, `home_featured`, `home_social`, `home_footer` |

The `landing` set deliberately omits the `catalog_*` zones: those drive the real catalog
grid and would render a duplicate product listing.

### Moodle blocks on storefront pages

Every public content page hosts standard Moodle blocks, alongside the widget system.
Turn on Moodle's edit mode as a user with `local/moderncommerce:managestorefront` and an
"Add a block" control appears in each region.

Four regions are available:

| Region | Where it renders |
| --- | --- |
| `side-pre` | The theme's own block drawer, from the `standard` page layout. |
| `mc-above` | Full width, above the page content. |
| `mc-content` | Inside the page container, after the widgets. |
| `mc-below` | Full width, below the page content. |

The three `mc-*` regions are declared by `public_page::setup_page()` and rendered by
`public_page::block_region()`. They are page-declared regions, so **nothing in the theme
renders them** - any new page entry point must echo them itself (see `support.php`, which
renders the above/below bands around its own widget call). They must also be declared
before `$OUTPUT->header()`: `block_manager` rejects new regions once blocks have loaded.

Two details worth knowing:

- Custom pages all share the pagetype `local-moderncommerce-page`, since they are all
  served by `page.php`. `setup_page()` therefore calls `set_subpage($slug)` for custom
  pages so each one keeps its own blocks; without it a block added to one custom page
  would appear on every one of them. Built-in pages have distinct pagetypes already.
- `add_region()` makes the first region it sees the page default. `setup_page()` captures
  the theme's default (`side-pre`) beforehand and restores it afterwards, so blocks added
  from the drawer still land in the drawer.

Blocks that declare `applicable_formats()` without `'all'` (Completion status, Starred
courses, Badges and other course/dashboard-only blocks) are correctly not offered here.

**Theme portability.** The three `mc-*` regions are declared by the page, not the theme,
so they render on any theme - verified against Boost, Classic and Moove, including Moove
which has no `standard` layout of its own. The one theme-dependent piece is the *add*
control: `core_renderer::addblockbutton()` returns an empty string unless the theme sets
`$THEME->addblockposition` (Boost and its children do; Classic does not). `block_region()`
therefore falls back to a plain link to the same `bui_addblock` URL when the core button
comes back empty, so the regions remain usable everywhere. On themes that do set it, the
native button is used and no fallback is emitted.

### Creating a page

The **New page** form fills the slug in from the title as you type (until you edit the slug
yourself), then checks it against the server on a short debounce via
`local_moderncommerce_check_store_page_slug`, so a clash or a reserved name is reported
before you save rather than after. The icon field is a typeahead over
`local_moderncommerce_icon_options()` - search by name, keyword or domain - with a live
preview of the chosen glyph.

**Start from** offers a blank page of either layout, or a copy of any existing page that
has widgets. Copying clones that page's widgets (and any slider slides) as a starting
point and inherits its zone template; the two pages are independent afterwards. Widgets in
zones the destination template does not expose are skipped rather than copied into a zone
that could never render - so cloning the catalog onto a landing page drops its
`catalog_*` grid widgets.

Slug rules: lowercase letters, digits and single hyphens, 2-30 characters, and not one of
the reserved names (the built-in page keys plus the plugin's own route filenames).
Underscores are rejected because `public_page::config_name()` maps `-` to `_`, so
`my-page` and `my_page` would otherwise collide on the same config keys. **The slug and the
layout template are fixed once the page exists** - widgets reference a page by its bare
`pagetype` string with no foreign key, so changing either would orphan the page's content.

Deleting a page cascades to its widgets, any slider slides and their uploaded images, and
its `publicpage_<slug>_*` config overrides.

Note that `seed::reset_widgets()` is scoped to `zones::builtin_pages()`, not
`zones::pages()`: resetting the seeded storefront must never delete an administrator's
custom-page widgets.

## Course Detail Page

The course detail page uses a dedicated Modern Commerce layout:

- hero section with category/status badges, course title, summary, image, and quick course metrics
- main content column for overview, learning objectives, course outline, and review summary
- sticky purchase/sidebar column for price, add-to-cart/buy-now actions, product metadata, secure payment, and instant access indicators

The sidebar position is configurable for desktop screens from Modern Commerce settings. Set **Course detail sidebar position** to `Right` or `Left` depending on the storefront layout and reading flow. Mobile screens place the purchase/sidebar card before the main content so the purchase action is not pushed below long reviews or outlines.

## Spam Protection

Modern Commerce uses Moodle core reCAPTCHA v2 for public lead and support forms. The plugin does not store separate Google keys; it reads Moodle's global `$CFG->recaptchapublickey` and `$CFG->recaptchaprivatekey` values.

When both Moodle core keys are configured, these surfaces render and verify a Google reCAPTCHA challenge:

- `/local/moderncommerce/support.php`
- storefront `supportform` widgets
- storefront `newsletter` widgets
- contact and newsletter public web service submissions

When the keys are not configured, Modern Commerce leaves the challenge out and the forms continue to work. This keeps local development and private staging sites usable while allowing production sites to turn on spam protection centrally from Moodle administration.

Server-side validation is still required even when the widget renders in the browser. The public services accept the normal `g-recaptcha-response` field, and legacy AJAX callers may pass the same token as `recaptcharesponse`.

## Use the Storefront as the Moodle Home Page

Modern Commerce registers `/local/moderncommerce/index.php` as a selectable Moodle start page. Use this when the site should open directly into the commerce storefront instead of Moodle's standard front page.

Both required settings live on the same page: **Site administration > Appearance > Navigation** (`/admin/settings.php?section=navigation`).

### Required steps

1. Tick **Enable Home** (`enablemyhome`). This step is required and the setting is **off by default** on a fresh Moodle 5.x site.
2. Set **Start page for users** (`defaulthomepage`, labelled *Default home page for users* on earlier Moodle releases) to **Modern Commerce storefront**.
3. Save changes.
4. Purge caches if the storefront option is not listed in the dropdown.
5. Test the site root URL, for example `https://example.com/`, in both a logged-out and a logged-in session.

The same configuration from the command line:

```bash
php admin/cli/cfg.php --name=enablemyhome --set=1
php admin/cli/cfg.php --name=defaulthomepage --set=/local/moderncommerce/index.php
php admin/cli/purge_caches.php
```

### Why Enable Home is required

Moodle core `index.php` redirects anonymous visitors straight to the login page whenever `enablemyhome` is empty, and it does so before the branch that forwards visitors to a URL start page. Logged-in users are still routed to the storefront by core, so a missed step shows up as a site that opens the store for signed-in users and the login page for everybody else.

### What Modern Commerce does once the setting is applied

- Core resolves the start page to a URL rather than one of its built-in pages, and routes logged-in users to the storefront.
- Modern Commerce redirects anonymous front-page requests, which core leaves on the site home. The listener is `\local_moderncommerce\hook\callbacks::redirect_frontpage_to_catalog`, registered on `\core\hook\output\before_http_headers` in `db/hooks.php`.
- The dropdown option itself is contributed by `\local_moderncommerce\hook\callbacks::extend_default_homepage` on `\core_user\hook\extend_default_homepage`. Hook registrations are cached, so purge caches after installing or upgrading the plugin.

### Also required for anonymous visitors

The storefront is public by design, but three site-level conditions must hold or visitors are still sent to the login page or reach a storefront with missing sections.

| Requirement | Where | Notes |
| --- | --- | --- |
| **Force users to log in** off (`forcelogin`) | Site administration > General > Security > Site security settings | When enabled, every page requires a session and no public storefront is possible. |
| Visitor role holds `local/moderncommerce:viewcatalog` | **Role for visitors** in Site administration > Users > Permissions > User policies, then that role's definition | The `guest` archetype receives this capability at install. |
| Widget **audience** set to `all` or `guest` | Storefront edit mode, per widget | A widget restricted to logged-in users stays hidden from anonymous visitors even when the page loads. |

Optionally enable **Open to search engines** (`opentowebcrawlers`, in Site security settings) so the storefront can be indexed.

Do not edit Moodle core `index.php` and do not add a theme-level redirect for this. Use the registered start page option so Moodle navigation, login redirects, and continue links keep behaving predictably.

## Admin Routes

- `/local/moderncommerce/admin/pages.php` - storefront page records
- `/local/moderncommerce/admin/gallery.php` - widget gallery and presets
- `/local/moderncommerce/admin/branding.php` - design tokens and email shell
- `/local/moderncommerce/icons_bootstrap.php` - curated icon browser
- `/local/moderncommerce/styleguide.php` - design-system styleguide

## Store Pages Administration

Open **Modern Commerce > Store pages** or visit `/local/moderncommerce/admin/pages.php`. The route requires the system capability `local/moderncommerce:managestorefront`.

The page manages these buyer-facing routes:

| Page | Public route | Availability |
| --- | --- | --- |
| Catalog | `/local/moderncommerce/index.php` | Required; cannot be disabled |
| About | `/local/moderncommerce/about.php` | Optional |
| Support | `/local/moderncommerce/support.php` | Optional |
| Terms | `/local/moderncommerce/terms.php` | Optional |
| Privacy | `/local/moderncommerce/privacy.php` | Optional |
| Refund policy | `/local/moderncommerce/refund-policy.php` | Optional |

Optional pages are enabled by default until a manager explicitly disables them. A disabled optional page returns Moodle's page-not-found response to ordinary visitors. A user with `local/moderncommerce:managestorefront` can still open it for review.

The actions on each row are:

- **Visibility switch**: enable or disable an optional page. The catalog is always required.
- **Manage widgets**: open the layout drawer for that page.
- **Preview**: open the public page.

The screen also links to `/local/moderncommerce/admin/global.php`, where global widgets are managed for all storefront pages. The page table displays each configured title and summary, but it does not directly edit those text values.

### Layout Drawer

The **Manage widgets** drawer loads the page's assigned widgets and applicable global widgets. Widgets are grouped by render zone. A manager can move a widget up or down within its zone, show or hide it, and save the revised order and visibility.

Saving re-sequences widgets inside each zone. It does not create a widget, move it to another zone, or edit widget content. Use storefront edit mode or the widget gallery for those operations.

If no widgets are assigned, seed the standard defaults with:

```bash
php local/moderncommerce/cli/demo_data.php --install-defaults
```

Before enabling an optional page publicly, preview it on desktop and mobile, verify global elements, test links and forms, and confirm policy or contact content is complete.

## Widget System

Storefront widgets are stored in:

- `local_moderncommerce_widget`
- `local_moderncommerce_widget_slide`
- `local_moderncommerce_widget_preset`

Use presets for reusable styling. Use widgets for page placement and content. Keep generated CSS in SCSS bundles; do not add page-level CSS includes.

## Default Storefront Seed

Seed defaults:

```bash
php local/moderncommerce/cli/demo_data.php --install-defaults
```

Reset and seed storefront widgets only:

```bash
php local/moderncommerce/cli/seed_storefront.php --reset
```

## CSS Architecture

Modern Commerce styles are centralized:

- core design system: `styles/design-system.css`
- route bundles: `styles/bundles/*.css`
- Moodle auto-loaded root CSS: `styles.css`
- SCSS source: `styles/scss/`

Minification should happen during packaging, not during active design work.
