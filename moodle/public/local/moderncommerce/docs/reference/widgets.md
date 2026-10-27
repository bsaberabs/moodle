# Storefront Widget Reference

Storefront widget content is configured in the editor on each public storefront page. Reusable style presets are configured in `/local/moderncommerce/admin/gallery.php`. Both surfaces render through the same React storefront and `classes/storefront/page_builder.php` contract.

The canonical widget type list lives in `classes/storefront/widget_types.php`. The editable field definitions live in `classes/storefront/field_schema.php`. Runtime payloads are produced by resolver classes in `classes/storefront/resolver/`.

## Widget Lifecycle

1. Admin creates or edits a widget instance.
2. Settings are stored as JSON on `local_moderncommerce_widget`.
3. Slider slides are stored separately in `local_moderncommerce_widget_slide`.
4. `page_builder` loads enabled widgets for the current page and zone.
5. The type-specific resolver builds a sanitized payload.
6. The React storefront renders the widget from the common widget envelope.

Widgets can be scoped by page type, zone, audience, start time, end time, sort order, background, and vertical spacing.

## Addable Widgets

| Type | Purpose | Data source |
| --- | --- | --- |
| `slider` | Hero slider with slides, CTA labels, links, and images. | `local_moderncommerce_widget_slide` plus widget settings. |
| `videohero` | Split hero with copy, buttons, video panel, quote, and info items. | Widget settings and uploaded media. |
| `breadcrumb` | Page title/breadcrumb banner for public pages. | Widget settings and optional background media. |
| `featured` | Featured product carousel or grid. | Catalog web services plus widget filters. |
| `related` | Related product carousel or grid. | Catalog web services plus widget filters. |
| `categories` | Category tiles or carousel. | Moodle course categories plus widget-selected items. |
| `trustbadges` | Trust/security/value badge strip. | Widget settings list. |
| `partners` | Partner, client, and accreditation logos in grid, carousel, or marquee layouts. | Widget settings list and uploaded/direct logo media. |
| `countdown` | Campaign countdown bar with optional CTA. | Widget settings. |
| `testimonials` | Testimonial cards with rating, author, and role. | Widget settings list. |
| `instructors` | Instructor spotlight cards. | Widget settings list. |
| `newsletter` | Email capture block. | Widget settings and newsletter web service. |
| `content` | General content section for public pages. | Widget settings. |
| `mediastorycarousel` | Side-by-side media and copy carousel. | Widget settings and uploaded/direct media. |
| `leadermessage` | Portrait and signed message from a named leader. | Widget settings and uploaded/direct media. |
| `featuregrid` | Responsive grid of icon feature/benefit cards. | Widget settings list. |
| `subscriptionplans` | Public subscription pricing table with plans, prices, and features. Cards link to `subscribe_plan.php?plan=<code>`, which raises the pending subscription order and forwards to checkout. Subscriptions bypass the cart: a cart line models a one-off charge, while a plan carries a billing cycle and trial. | Subscription plan records and the feature matrix, resolved server-side; widget settings control presentation and filtering. |
| `learningpromise` | Centered learning promise statement. | Widget settings. |
| `belief` | About-page belief/mission statement band. | Widget settings. |
| `policy` | Structured policy sections for terms, privacy, and refunds. | Widget settings list. |
| `faq` | FAQ accordion/list. | Widget settings list. |
| `cta` | Call-to-action band. | Widget settings. |
| `supportform` | Public commerce support form. | Widget settings and contact/support services. |
| `contactcards` | Contact/help option cards. | Widget settings list. |
| `footer` | Multi-column storefront footer. | Widget settings, logo source, social/app links. |

## System Widget

| Type | Purpose | Data source |
| --- | --- | --- |
| `catalog` | Core catalog grid. It is resolver-backed and gallery-visible but not normally admin-addable. | Catalog web services and query/filter state. |

## Editing Notes

- Use the gallery page to preview every widget style before placing it on a public page.
- Gallery presets are style-only: they may store colours, typography, spacing, layout, and display toggles, but never copy, links, media, filters, or repeating content rows.
- A saved preset can be selected while adding a widget or applied from an existing widget's page editor. Applying it must merge visual settings without replacing the widget's content.
- Reopening a page editor must hydrate every control from the saved widget values and universal style configuration.
- Keep text concise. Long titles and CTA labels should wrap cleanly on mobile.
- Prefer uploaded media for production hero/video widgets so assets remain package-controlled and cacheable.
- Product widgets should filter by product type, category, featured state, or limit instead of hard-coding product IDs unless the campaign requires fixed products.
- Use `breadcrumb` and `footer` as global widgets when the same shell should apply across public pages.
- Use `policy`, `faq`, `supportform`, and `contactcards` for support and compliance pages instead of building long static HTML blocks.

## Developer Notes

- Add a widget type constant and label in `classes/storefront/widget_types.php`.
- Add editable fields in `classes/storefront/field_schema.php`.
- Add a resolver implementing `classes/storefront/resolver/widget_resolver.php`.
- Register the resolver in `classes/storefront/page_builder.php`.
- Add gallery variants in `classes/storefront/gallery_builder.php`.
- Add or update React rendering in `js/esm/src/storefront.tsx` or the relevant storefront component.
- Add SCSS in the storefront/global SCSS bundle, not inline CSS in templates.
