# Products and Pricing

Modern Commerce treats sellable items as products. Product types include courses, bundles, programs, subscription plans, and future digital products.

## Admin Routes

- `/local/moderncommerce/admin/pricing.php` - product and course pricing admin
- `/local/moderncommerce/admin/categories.php` - catalog categories
- `/local/moderncommerce/admin/bundles.php` - bundle and program admin
- `/local/moderncommerce/admin/course_advanced_features.php` - course merchandising metadata
- `/local/moderncommerce/admin/advanced_bundle_features.php` - bundle/program merchandising metadata

## Product Lifecycle

1. Create or select a Moodle course.
2. Create a Modern Commerce product record.
3. Set price, sale price, visibility, status, SKU, and inventory behavior.
4. Attach catalog categories and tags.
5. Configure course or bundle details for storefront display.
6. Preview the public catalog and details page.

## Pricing Fields

Modern Commerce stores prices in `local_moderncommerce_product_prices`.

Common fields:

- `amount` - active price.
- `compareamount` - crossed-out compare-at price.
- `pricetype` - regular, sale, tier, or subscription-related price type.
- `startdate` and `enddate` - optional active window.
- `enabled` - whether the price row can be used.

## Inventory

Inventory lives in `local_moderncommerce_product_inventory`.

Use stock management when access should be limited by seats, licences, or cohort capacity. Leave stock unmanaged for normal always-available course products.

## Bundles and Programs

Bundles and programs are both multi-course products, but they should not be used as synonyms.

Use a **bundle** when the offer is a grouped commercial package: several related courses sold together for convenience, value, a discount, a team package, or a themed collection. A bundle says "buy these courses together." Learners may treat the included courses as a flexible set unless the product copy or completion rules say otherwise.

Use a **program** when the offer is a guided learning path: a structured curriculum, certificate pathway, cohort-style journey, career track, compliance pathway, or any product where course order, outcomes, prerequisites, duration, and completion expectations matter. A program says "follow this path to achieve this outcome."

In implementation, both bundles and programs are stored in `local_moderncommerce_products`:

- `producttype = bundle` for bundles.
- `producttype = program` for programs.
- Included Moodle courses are stored in `local_moderncommerce_product_courses`.
- Both use the same pricing, checkout, cart, order, enrolment-key, and product ownership foundation.
- Both can use advanced merchandising metadata such as duration, assessments, outline, tags, and badges. Live prerequisites, pass policies, must-pass rules, progress, and automatic final certificates apply only to programs.

The distinction is still important because it controls how the product is positioned and understood across the admin, catalog, learner dashboard, badges, filters, and reporting. Choose the type based on the buyer promise:

| Question | Bundle | Program |
| --- | --- | --- |
| Buyer expectation | A convenient package or discount | A guided path to an outcome |
| Course order | Usually flexible | Usually intentional or sequential |
| Marketing emphasis | Value, collection, theme, package | Outcome, pathway, curriculum, certificate |
| Examples | "Excel Skills Bundle", "Manager Starter Pack" | "Data Analyst Career Program", "New Manager Certification" |
| Best admin label | Bundle | Program |

Changing a product from bundle to program changes its `producttype`, initializes progress for existing paid purchasers, and activates program completion and certificate tracking. Existing Moodle enrollments are retained, and future program purchases receive every included course.

For the complete runtime, policy, certificate, data-model, and troubleshooting reference, see [Programs and learning paths](programs-and-learning-paths.md).

Use:

- `/local/moderncommerce/admin/bundles.php` to create and manage the product.
- `/local/moderncommerce/admin/advanced_bundle_features.php?bundleid=ID` for shared merchandising, duration, assessments, outline, tags, and badges, plus program-only completion, prerequisite, must-pass, and certificate controls.

## Advanced Bundle Settings

Open `/local/moderncommerce/admin/advanced_bundle_features.php?bundleid=ID` after the bundle or program has been created. `ID` is the Modern Commerce product ID for a product whose `producttype` is `bundle` or `program`. The page requires `local/moderncommerce:managecourses`; invalid IDs redirect back to the bundle admin list.

Use this page to describe how the bundle/program should appear and how its learner path should be understood. Use `/local/moderncommerce/admin/bundles.php` for the core bundle record, included courses, image, status, base price, sale price, and basic product details.

### Overview Panel

The left overview panel is a live summary, not the main editing area.

| Item | Meaning |
| --- | --- |
| Image | The current bundle/program image. Manage the image from the bundle editor, not from this page. |
| Name and type | Confirms whether the product is a Bundle or a Program. Change the type from the bundle editor when the buyer promise changes. |
| Price and sale price | Shows the active pricing context for the bundle/program. Pricing is owned by the bundle editor and pricing services. |
| Courses in bundle | Count of included Moodle courses. Add or remove included courses from the bundle editor. |
| Savings | Calculated comparison between the bundle/program price and included course value where pricing data is available. |
| Total duration | Uses auto-calculated course metadata when auto duration is enabled, or the manual duration entered on this page. |
| Assessments | Uses auto-detected quiz count when auto assessments are enabled, or the manual assessment count entered on this page. |
| Certificate state | Shows whether the bundle/program certificate flag is enabled. |

### Catalog Visibility

This section controls the metadata used by catalog and detail surfaces that read advanced bundle metadata.

| Setting | What it controls |
| --- | --- |
| Skill level | Learner-facing level label. Current options are `Beginner`, `Intermediate`, `Advanced`, and `All Levels`. Use this for filtering, buyer expectation, and detail page clarity. |
| Language | Learner-facing language label. Values come from Moodle's installed language list. Use it to show the primary delivery language for the bundle/program. |
| Visibility | Merchandising visibility mode. Current options are `Public`, `Hidden`, and `Scheduled`. Base product status, product visibility, stock, price availability, and storefront widget filters can still affect whether the product is visible or purchasable. |
| Start date | Optional availability start date for scheduled or campaign-based merchandising. |
| End date | Optional availability end date. If the end date is earlier than the start date, the service normalizes it to the start date. |

Use `Public` for normal storefront listing, `Hidden` for offers that should not appear in normal merchandising surfaces, and `Scheduled` when start/end dates define the intended campaign window. After changing visibility or dates, verify the public catalog, bundle details page, cart, and checkout.

### Duration and Assessments

Use this section to control the learning-effort signals buyers see before purchase.

| Setting | What it controls |
| --- | --- |
| Duration: auto-detect | When enabled, Modern Commerce sums the numeric `learninghours` Moodle custom course field across included courses. The page reports when the field is missing, when no course has a value, and which courses are missing values in a partially configured bundle. |
| Manual duration | When auto-detect is off, enter hours and minutes manually. Hours cannot be negative; minutes are stored from `0` to `59`. |
| Assessments: auto-detect | When enabled, shows a live count of non-deleted Moodle quiz modules across all included courses. |
| Manual assessments | When auto-detect is off, enter the number of assessments learners should expect. |

Create a Moodle custom course field with the shortname `learninghours`, then set its numeric hour value on each included course. Use manual duration when course-level learning hours are intentionally unavailable. Keep assessment auto-detection enabled for Moodle quiz-based bundles, or use manual assessments when the bundle includes offline, external, or non-quiz assessments.

### Completion Settings

This section appears for programs and drives the live completion and certificate engine. Bundles do not have a program-level completion outcome, prerequisite requirement, must-pass rule, or automatic final award.

| Setting | What it controls |
| --- | --- |
| Pass policy | Live program completion rule. Options are `all_must_pass`, `weighted_avg`, and `any_pass`. |
| Pass grade | Percentage threshold for policies that need a grade cutoff. Valid range is `0` to `100`; default is `70`. |
| Enable certificate | Enables certificate issuance. Programs require Moodle Course Certificate (`mod_coursecertificate`) and a selected template. |
| Certificate template | For programs, selects the native Moodle `tool_certificate` template used by the Course Certificate integration when completion requirements are met. The selector is shown only when certificates are enabled. |
| Program prerequisites | Search-ahead multi-select of external Moodle courses that must be complete before the final certificate is issued. Prerequisites never lock included program courses. |
| Must-pass courses | Program-only requirement shown directly beneath `weighted_avg` and `any_pass`. Each selected included course must pass individually in addition to the base policy. It is hidden and cleared for `all_must_pass`. |

Use `all_must_pass` for compliance and certificate pathways, `weighted_avg` when the overall average matters more than every individual grade, and `any_pass` for elective programs where one successful option can satisfy the base policy. After setting program completion rules, test with a learner account that has realistic course completion and grade data.

### Course Outline

The outline is a learner-facing curriculum summary for the bundle/program. Each row is a short section title or milestone. The page saves non-empty outline items in the order shown.

Use outlines for:

- program stages
- bundle themes
- curriculum milestones
- onboarding or capstone phases
- buyer-facing value summaries

Keep each item short. The outline is not a replacement for Moodle course sections; it is a commerce-level summary that helps a buyer understand the path before purchase.

### Tags

Tags are bundle/program metadata used for organization, search/filter surfaces, and merchandising labels where supported.

Rules:

- Empty tags are ignored.
- Duplicate tags are removed case-insensitively during save.
- Tags longer than 100 characters are truncated.
- Tags are stored per bundle/program, not globally.

Use short business terms such as `leadership`, `compliance`, `career-track`, `beginner`, or `team-training`.

### Badges

Badges are merchandising signals shown by catalog/detail surfaces that support them.

| Badge | Best use |
| --- | --- |
| Featured | Use for strategic offers the store wants to promote. |
| Bestseller | Use only when the product has real sales or demand evidence. |
| Trending | Use for seasonal, campaign, or high-interest offers. |

Avoid turning on every badge for every product. Badges lose value if every bundle/program looks equally promoted.

### Prerequisites

Programs expose a search-ahead prerequisite multi-select in the certificate section. Every selected Moodle course and every included program course must be complete before the program certificate is issued. Prerequisites never restrict course access, and receiving the program grants every included course immediately.

Program progress is stored in `local_moderncommerce_program_progress` and refreshed by Moodle course-completion events. Completion policies use Moodle course completion and normalized final course grades, plus the configured pass grade and must-pass course list. A configured Course Certificate template is issued once when the policy is met; the issue ID is retained to prevent duplicate awards.

Converting a purchased bundle to a program initializes progress for existing purchasers from their current Moodle completion records. Existing course enrollments are retained, and new purchasers receive all included courses.

### Setup Checklist

For a production-ready bundle or program:

1. Create the bundle/program and attach included courses in `/local/moderncommerce/admin/bundles.php`.
2. Confirm base price, sale price, image, status, and included courses.
3. Open `/local/moderncommerce/admin/advanced_bundle_features.php?bundleid=ID`.
4. Set skill level, language, visibility, and availability dates.
5. Decide whether duration and assessment counts should be automatic or manual.
6. For programs, set the pass policy and pass grade. For `weighted_avg` or `any_pass`, select any individually required must-pass courses from the vertical list beneath the policy.
7. Enable the certificate, choose its template, and search for any external prerequisites.
8. Write a short course outline.
9. Add useful tags.
10. Add only meaningful merchandising badges.
11. Save and test the catalog card, bundle/program details page, cart, checkout, and learner access.

Program certificate templates come from Moodle's Course Certificate add-on. Set the optional store-wide fallback under **Modern Commerce > Settings > Certificates**. The global setting and bundle/program advanced pages provide **Manage Certificate Templates** for authorised administrators; it opens Moodle's native template manager in a new tab. Modern Commerce stores only the selected global fallback or program override and does not maintain a separate certificate-template system. If Course Certificate is absent, these controls degrade safely and the rest of Modern Commerce continues working.

## Course Metadata

Course details can include:

- level
- duration
- language
- outcomes
- outline
- tags
- visibility and availability metadata
- review and trust signals

Use the advanced course features page to enrich public course detail pages without editing Moodle course internals.
