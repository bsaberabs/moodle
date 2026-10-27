# Programs and Learning Paths

This guide is the source of truth for Modern Commerce programs: how administrators configure them, how learners move through them, how Moodle completion and grades determine the outcome, and how Course Certificate awards are issued.

## What a Program Is

A program is a structured multi-course product built on the Modern Commerce product catalog. It is intended for a learning pathway where curriculum order, prerequisites, completion rules, and an optional final certificate matter.

A bundle is a flexible commercial collection. A program adds live completion, prerequisite, grade, and certificate rules. Purchasing either product grants access to every included course; a program's configured order guides presentation and reporting but does not lock course access.

| Behavior | Bundle | Program |
| --- | --- | --- |
| Product record | `producttype = bundle` | `producttype = program` |
| Included courses | `local_moderncommerce_product_courses` | `local_moderncommerce_product_courses` |
| Course order | Display preference | Structured display preference |
| Initial enrollment | All included courses | All included courses |
| External prerequisites | Metadata | Certificate/completion requirement |
| Completion policy | Stored merchandising setting | Evaluated against live Moodle data |
| Progress record | None | `local_moderncommerce_program_progress` |
| Automatic final certificate | No | Yes, when enabled and earned |

## Components and Requirements

The feature connects these Moodle and Modern Commerce components:

- `local_moderncommerce` owns products, orders, program configuration, enrollment, progress, learner presentation, and issuance orchestration.
- Moodle course completion supplies the authoritative completed/not-completed state for every course.
- Moodle gradebook supplies normalized final course grades when a final grade exists.
- The Modern Commerce enrollment service creates Moodle course enrollments and course-access entitlements.
- `mod_coursecertificate` makes the Course Certificate integration available.
- `tool_certificate` owns certificate templates, issues, PDF generation, and certificate notifications.

For automatic program certificates, both `mod_coursecertificate` and `tool_certificate` must be installed and upgraded. At least one visible certificate template must exist.

Create templates at:

`Site administration > General > Certificates > Manage certificate templates`

## Administrator Workflow

### 1. Prepare the Moodle courses

For every included course:

1. Enable Moodle course completion.
2. Configure meaningful completion criteria.
3. Configure the course total grade when the program policy depends on grades.
4. Optionally set the numeric `learninghours` custom course field.
5. Add Moodle quiz activities when assessments should be auto-counted.

The program engine reads Moodle's course-completion state. Activity completion without a resulting course completion does not satisfy program or certificate requirements.

### 2. Create the program

Open `/local/moderncommerce/admin/bundles.php` and create or edit the multi-course product.

- Set the product type to **Program**.
- Add all included Moodle courses.
- Arrange the courses in the intended learner order.
- Configure the normal product fields, including name, description, image, status, visibility, and price.

The `sortorder` values in `local_moderncommerce_product_courses` determine how the curriculum is presented. They do not restrict which included course a learner may open.

### 3. Configure advanced program features

Open:

`/local/moderncommerce/admin/advanced_bundle_features.php?bundleid=PROGRAM_ID`

The page requires `local/moderncommerce:managecourses`.

Configure:

- catalog visibility and availability dates;
- duration and assessment calculation;
- pass policy and pass grade;
- must-pass included courses;
- Course Certificate template and searchable prerequisite courses;
- outline, tags, and merchandising badges.

### 4. Test with a learner account

Use a non-administrator learner account and verify:

1. The purchase creates program access.
2. Every included course is enrolled and launchable.
3. Prerequisites do not restrict course access.
4. Incomplete prerequisites prevent final program completion and certificate issuance.
5. The selected completion policy produces the expected final result.
6. The configured certificate appears once in **My certificates**.

## Duration and Assessments

Duration and assessments can be calculated from Moodle or entered manually.

### Automatic duration

Automatic duration searches Moodle course custom fields for the exact shortname `learninghours` and sums the numeric value across all included courses. Values are hours and may include decimals; the result is converted to minutes internally for display.

The admin page distinguishes these states:

| State | Meaning |
| --- | --- |
| No included courses | There is nothing to calculate. |
| Field missing | Moodle has no course custom field with shortname `learninghours`. |
| Field exists, no values | The field exists but none of the included courses has a value. |
| Partial values | Configured values are summed and courses without a value are listed. |
| Complete values | Every included course contributes to the displayed total. |

A missing value is not treated as zero. This makes incomplete course configuration visible to the administrator.

When automatic duration is disabled, the administrator enters hours and minutes manually. Minutes are constrained to `0` through `59`.

### Automatic assessments

Automatic assessments count non-deleted Moodle `quiz` course modules across every included course. It is a live count and is not manually editable while automatic mode is enabled.

When automatic assessments are disabled, an administrator may enter a non-negative whole number. Use manual mode for offline, external, or non-quiz assessments.

## Prerequisites and Program Access

### External program prerequisites

The certificate section contains a search-ahead multi-select of visible Moodle courses that are not included in the program. Administrators can search by full name or short name and select multiple prerequisites without loading the entire course catalog. After a course is selected, the results close, the query and stale results are cleared, and the search input is ready to be reopened for the next selection. Selected courses remain in a removable list beneath the field.

Every selected prerequisite must be complete before the program can become complete or issue its final certificate. An incomplete prerequisite never prevents access to an included program course.

Completing a prerequisite triggers the same program synchronization path as completing an included course.

### Included-course access

Receiving a program enrolls the learner into every included course. The learner may work through them in any order, while the configured sort order continues to present the intended curriculum path. Completion and grades are evaluated separately when determining whether the whole program and its certificate requirements are satisfied.

## Completion and Grade Rules

The program engine calculates these values whenever it synchronizes:

- total included courses;
- completed courses;
- passed courses;
- normalized average final grade;
- percentage of courses completed;
- first incomplete course;
- prerequisite state;
- final program status;
- certificate issue state.

### What counts as a passed course

A course passes when:

1. Moodle reports the course complete; and
2. its normalized final course grade is at least the configured pass grade.

If Moodle reports the course complete but the course has no final grade, completion is treated as passing for that course. This supports completion-only courses. If a program must always use grades, configure a course total grade for every included course before enrolling learners.

### Pass grade

The pass grade is a normalized percentage from `0` to `100`. Course final grades are normalized using the grade item's minimum and maximum values before comparison.

### Policy: All courses must pass

`all_must_pass` completes the program only when every included course is complete and passed.

Recommended for:

- compliance programs;
- professional certification paths;
- required onboarding;
- programs where every course represents a required competency.

### Policy: Weighted average

`weighted_avg` requires every included course to be complete and requires the calculated average normalized final grade to meet the pass grade. The stored policy key is historical; because the current data model has no per-course weight field, each available course final grade contributes equally to the average.

Courses without a final grade do not add grade points to the average. If no included course has a final grade, the calculated average is zero and the policy is not satisfied unless the configured pass grade is zero.

Recommended when the overall result matters more than passing every individual course.

### Policy: Any course passes

`any_pass` satisfies the base policy when at least one included course is complete and passed.

This does not bypass explicitly selected must-pass courses. It is intended for flexible pathways where learners may satisfy the outcome through one of several options.

### Must-pass courses

Must-pass courses are an additional requirement applied after the selected policy. Every selected must-pass course must be complete and passed.

The selector is shown directly beneath **Pass Policy** for `weighted_avg` and `any_pass`. It is hidden for `all_must_pass`, because that policy already requires every included course to pass. Changing a program to `all_must_pass` clears its individual must-pass selection. The server also rejects unknown policy keys and discards course IDs that are not included in the program.

Each included course appears on its own full-width row with a styled checkbox. Long names wrap within their row, and large course lists scroll inside a bounded panel rather than extending the whole page. The checked, hover, and keyboard-focus states are visually distinct.

For example, a program may use `weighted_avg` while marking a capstone course as must-pass. A qualifying average without a passing capstone does not complete the program.

### Policy summary

| Policy | Base completion condition | Must-pass override |
| --- | --- | --- |
| `all_must_pass` | Every course complete and passed | Not applicable; every course is already required |
| `weighted_avg` | Every course complete and average meets threshold | Every selected course must pass |
| `any_pass` | Every course complete and at least one course passed | Every selected course must pass |

External prerequisites must also be complete for every policy.

## Learner Experience

The learner enters a purchased program through the Modern Commerce learner application route:

`#/access/program/PROGRAM_ID`

The page displays:

- program name and product type;
- overall completion percentage;
- completed, in-progress, and not-started counts;
- included courses in program order;
- status and progress for each course;
- prerequisite-pending information;
- a launch action for every included course.

The general learner course list includes every included program course because the program enrollment grants active access to the full curriculum.

## Purchase and Enrollment Flow

The end-to-end flow is:

1. Checkout creates an order and order item for the program product.
2. Successful payment triggers `\local_moderncommerce\event\order_paid`.
3. `enrollment_observer` recognizes the product as a program.
4. `bundle_enrollment_api::enroll_user()` delegates to `program_progress_service::enrol()`.
5. The service reads prerequisites, ordered courses, completion, grades, and the selected policy.
6. It enrolls every included course through `enrolment_service`.
7. It inserts or updates `local_moderncommerce_program_progress`.
8. The order item is marked enrolled after the program is initialized.

Bundle and program purchases both enroll every included course. Programs additionally maintain live progress, prerequisite, grade, and certificate state.

## Course Completion Event Flow

Moodle emits `\core\event\course_completed` when a course becomes complete. Modern Commerce registers `program_observer::course_completed()` for that event.

The observer finds purchased programs where the completed course is either:

- an included program course; or
- an external prerequisite.

It then synchronizes each matching learner/program pair. Synchronization is safe to repeat: existing enrollments are reused, the progress row is updated, and an already-issued certificate is not issued again.

Opening the learner program page also synchronizes progress. This provides a recovery path when grades or completion records were changed after the original completion event.

## Course Certificate Integration

### Configuration

Completion and certificate controls appear only for programs. Bundles grant flexible access and do not have a program-level completion outcome. Program prerequisites are configured in this certificate section because they gate completion and issuance, not course access.

Administrators can configure a store-wide default at **Modern Commerce > Settings > Certificates** (`/local/moderncommerce/admin/settings.php?tab=certificates`). On each program, **Enable certificate** turns issuance on and the template selector can either:

- use the global default; or
- select a program-specific Course Certificate template that overrides the default.

The global Certificates tab and every bundle or program advanced-features page include a **Manage Certificate Templates** link for authorised administrators. On a program it sits beside the template selector; on a bundle it appears with the certificate controls. It opens Moodle's Course Certificate template manager in a new tab, where templates can be created or edited without discarding unsaved Modern Commerce form changes. The link is hidden when the add-on is unavailable or the current administrator cannot access the template manager.

Saving is rejected when certificate issuance is enabled and:

- the Course Certificate add-on is unavailable;
- neither a global default nor a program override is selected; or
- the selected template is not visible to the administrator.

Course Certificate is optional. When `mod_coursecertificate` or its `tool_certificate` backend is not installed, upgraded, or available, the settings tab shows an unavailable notice, returns no template options, and the rest of Modern Commerce continues operating normally.

The optional override is stored in `local_moderncommerce_bundle_meta.certificate_template_id`. A null value means “use the current global default.”

### Issuance

When the program completion policy is satisfied, Modern Commerce calls the native `tool_certificate` template API. The issue is stored in `tool_certificate_issues` with:

- `component = local_moderncommerce`;
- the selected template ID;
- the learner user ID;
- program ID and program name in the issue data;
- no Moodle course ID, because the award represents the whole program.

`tool_certificate` generates the issue file and sends its normal certificate notification. The certificate then appears in Modern Commerce learner certificate listings because those listings read native certificate issues.

### Duplicate protection

Issuance uses a learner-and-program lock. Before issuing, the service reloads the progress row and checks `certificateissueid`. After successful issuance, the native issue ID is stored on that row.

Repeated completion events, learner-page refreshes, and manual synchronization therefore return the existing issue instead of creating another certificate.

### Completion communication

The first transition to completed emits `\local_moderncommerce\event\program_completed`. The registered communication observer queues the editable `moderncommerce_program_completed` celebratory template to the learner through the Modern Commerce email and in-app notification channels.

The template is managed under **Modern Commerce > Email templates** and supports `{program_name}`, `{completed_courses}`, `{average_grade}`, `{completion_date}`, `{program_url}`, and `{certificate_url}`. Queue deduplication and the one-time status transition prevent duplicate messages when progress is recalculated.

## Bundle-to-Program Conversion

Changing an existing bundle to a program does more than change the label.

The conversion process:

1. changes `local_moderncommerce_products.producttype` to `program`;
2. finds every learner with a paid or completed order for that product;
3. calculates progress from current Moodle completion and grade data;
4. creates or updates a program progress row;
5. identifies the current course;
6. issues an earned certificate when all requirements are already satisfied.

Existing Moodle enrollments are retained. Conversion does not revoke access that learners already received while the product was a bundle, and new purchasers receive every included course.

The conversion result reports the number of purchaser records migrated and how many were already complete.

## Data Model

### Product and configuration tables

| Table | Responsibility |
| --- | --- |
| `local_moderncommerce_products` | Program identity through `producttype = program`. |
| `local_moderncommerce_product_courses` | Included courses, order, and required flag. |
| `local_moderncommerce_bundle_meta` | Pass policy, pass grade, certificate flag/template, duration, assessments, and merchandising metadata. |
| `local_moderncommerce_bundle_prereq` | External prerequisite Moodle courses. |
| `local_moderncommerce_bundle_mustpass` | Included courses that must pass. |
| `local_moderncommerce_bundle_outline` | Learner-facing program outline. |

The historical `bundle_` table prefix is shared by bundle and program advanced metadata.

### Program progress table

`local_moderncommerce_program_progress` contains one row per program and learner.

| Field | Meaning |
| --- | --- |
| `programid`, `userid` | Unique learner/program identity. |
| `orderid` | Purchase that initialized access, when available. |
| `status` | `active` or `completed`. Incomplete prerequisites leave the program active. |
| `totalcourses` | Number of included courses at last synchronization. |
| `completedcourses` | Courses Moodle reported complete. |
| `passedcourses` | Completed courses meeting the grade rule. |
| `averagegrade` | Average normalized final grade from graded courses. |
| `progresspercentage` | Completed courses divided by total courses. |
| `currentcourseid` | First program course that is not complete. |
| `certificateissueid` | Native `tool_certificate_issues.id`, when issued. |
| `timecompleted` | First recorded program-completion time. |
| `timecreated`, `timemodified` | Audit timestamps. |

The unique index on `(programid, userid)` prevents multiple progress records for the same enrollment journey.

## Service and Event Map

| Entry point | Responsibility |
| --- | --- |
| `bundle_api::convert_to_program()` | Changes type and initializes existing purchasers. |
| `bundle_enrollment_api::enroll_user()` | Grants every included course and initializes program progress. |
| `program_progress_service::enrol()` | Starts a purchased program. |
| `program_progress_service::sync()` | Canonical access, completion, persistence, and certificate orchestration. |
| `program_progress_service::sync_for_course()` | Finds programs affected by a course-completion event. |
| `program_progress_service::initialize_existing()` | Migrates purchasers during conversion. |
| `program_observer::course_completed()` | Connects Moodle completion events to synchronization. |
| `get_product_access` | Returns progress, curriculum order, and learner-safe course URLs. |
| `search_prerequisite_courses` | Provides server-side search-ahead options for certificate prerequisites. |
| `get_bundle_features` | Returns configuration, prerequisites, course metadata, and certificate templates. |
| `save_bundle_features` | Validates and saves policy, prerequisite, must-pass, and certificate settings. |

## Access and Security Rules

- Admin feature APIs require login, system context validation, and `local/moderncommerce:managecourses`.
- Learner product access requires login, `local/moderncommerce:viewcatalog`, and proof that the learner owns the product.
- Program enrollment grants every included course through the normal enrollment service.
- Prerequisites affect completion and certificate eligibility only; they never remove or delay course access.
- Dynamic Moodle names are formatted before returning them to the UI.
- State-changing admin calls use Moodle's authenticated AJAX service and session key.

## Operational Scenarios

### Compliance pathway

- Policy: `all_must_pass`
- Pass grade: `80`
- Must-pass: all compliance courses or a final assessment course
- Certificate: enabled

The learner must complete and pass the whole sequence before receiving the award.

### Academic pathway with a compensating average

- Policy: `weighted_avg`
- Pass grade: `70`
- Must-pass: capstone course
- Certificate: enabled

Every course must be complete, the average must be at least 70, and the capstone must pass.

### Elective outcome program

- Policy: `any_pass`
- Pass grade: `70`
- Must-pass: none, or a required orientation/capstone

Every course must be complete and at least one must pass. Select must-pass courses when particular courses must also meet the pass grade.

## Troubleshooting

### A program course is not available

Check:

- the learner has a paid or completed order for the program;
- the included course still exists;
- a supported enrollment plugin is enabled;
- the progress row status and `currentcourseid`;
- Moodle event processing and debugging logs.

### The program will not complete

Compare:

- selected pass policy;
- configured pass grade;
- Moodle final course grades;
- courses with no final grade;
- must-pass selection;
- external prerequisite completion;
- stored `completedcourses`, `passedcourses`, and `averagegrade`.

For `weighted_avg`, a program with no final grades has an average of zero.

### The certificate was not issued

Check:

- program status is `completed`;
- certificate is enabled;
- `certificate_template_id` references a visible existing template;
- `mod_coursecertificate` and `tool_certificate` are installed and upgraded;
- `tool_certificate_issues` for an existing issue;
- `certificateissueid` on the progress row;
- Moodle debugging output for issuance or PDF-generation errors.

Do not manually clear `certificateissueid` unless the corresponding native certificate issue has been reviewed. Clearing it can allow a replacement issue to be generated.

## Verification Checklist

Before releasing program changes, verify:

- new purchases enroll every included course;
- incomplete prerequisites leave access intact and prevent final completion;
- every included course has a learner-safe launch URL;
- `any_pass` still requires every included course to be complete;
- each completion policy succeeds and fails with representative grades;
- must-pass courses override `weighted_avg` and `any_pass` as intended;
- prerequisite search-ahead returns matching courses and preserves multiple selections;
- certificate issuance creates one native issue and remains idempotent;
- bundle enrollment still grants all bundle courses;
- conversion initializes existing purchasers without revoking enrollments;
- PHP lint, React production build, docs check, database upgrade, and targeted PHPUnit tests pass.

The focused automated coverage lives in `tests/program_progress_service_test.php`.

## Related Documentation

- [Products and pricing](products-and-pricing.md)
- [Orders and reports](orders-and-reports.md)
- [Web services reference](reference/web-services.md)
- [Database reference](reference/database.md)
- [Troubleshooting](troubleshooting.md)
