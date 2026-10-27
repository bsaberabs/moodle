# Web Service Reference

Modern Commerce AJAX/web service functions are defined in `db/services.php`.

There are 161 function definitions in this build. They are grouped by workflow.

## Public Catalog and Detail

- catalog dataset
- course detail dataset
- bundle/program detail dataset
- public reviews
- newsletter subscribe
- contact submit

Newsletter and contact submissions verify Moodle core reCAPTCHA when Moodle's global public and private keys are configured. Browser forms submit the standard `g-recaptcha-response` token; custom callers can pass the same token as `recaptcharesponse` where the legacy service parameter is used.

## Buyer and Learner

- cart read/update
- checkout start/place order
- learner dashboard
- learner courses, certificates, grades, orders, wishlist, profile
- learner subscriptions and product access

Program product access returns the persisted completion summary, prerequisite state, curriculum-ordered course states, and learner-safe URLs for every included course. It synchronizes live progress and ensures all program course enrollments before responding. Prerequisites affect final completion and certificate eligibility, not course access.

## Admin Commerce

- products and prices
- bundle/program advanced features, including server-side prerequisite search-ahead, duration and assessments; program saves additionally validate the pass-policy allowlist, discard must-pass IDs outside the included curriculum, clear must-pass rules for `all_must_pass`, and validate the optional Course Certificate template
- categories
- coupons and targets
- orders, customers, refunds
- gateways, payment events, webhooks
- reports and dashboard charts
- audit logs

## Storefront

- storefront page data
- gallery data
- widget presets
- storefront layout read/write
- widget add/update/delete
- slide image uploads

## Email and Notifications

- email template metadata
- email template read/write/delete/clone/preview
- email shell read/write/preview/reset
- notification settings
- contact inbox and replies

## Subscriptions

- plan overview
- plan save/delete
- feature matrix
- subscription list/details/actions
- subscription keys
- subscription email templates
- plan access rules
- public plan list, subscribe, and key redeem

## Security Notes

- Public read functions explicitly set `loginrequired` to false.
- Buyer functions require login where access or personal data is involved.
- Admin functions require capabilities from `db/access.php`.
- Treat web service names as API surface. Changes can break compiled React or AMD clients.
