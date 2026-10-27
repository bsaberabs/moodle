# Settings Reference

Modern Commerce settings are split across two admin surfaces:

- `Modern Commerce > Settings` opens `/local/moderncommerce/admin/settings.php`. This is the day-to-day store configuration app for store identity, currency, tax, checkout fields, navigation, notifications, reviews, product form fields, and contact form emails.
- `Site administration > Plugins > Local plugins > Modern Commerce` opens Moodle's native settings pages. Use this area for deeper site configuration such as branding seeds, webhook security, notification delivery channels, navigation, and subscription defaults.

The admin settings app and the Moodle native settings pages save to the same `local_moderncommerce` configuration store. If a setting appears in both places, changing it in either place updates the same value.

## Access

Settings require Moodle site configuration access:

- Admin settings app: `/local/moderncommerce/admin/settings.php`
- Native plugin settings: `Site administration > Plugins > Local plugins > Modern Commerce`

Managers who can operate the store but do not have `moodle/site:config` should use the operational pages for orders, reports, products, coupons, subscriptions, and storefront design instead.

## Commerce Settings App

Open `Modern Commerce > Settings` from the Modern Commerce admin sidebar.

### Store Identity

Use this tab to define the public business details used by receipts, support messaging, and admin summaries.

| Setting | What it controls |
| --- | --- |
| Business name | Store/business name shown in Modern Commerce contexts. If empty, the site name is used where a fallback is needed. |
| Support email | Support mailbox shown in commerce messages. It must be a valid email address when set. If empty, Moodle's support email can be used as a fallback. |
| Support URL | Help or support URL for buyer-facing communication. Leave empty if support should be email-only. |

### Currency

Use this tab before creating production prices. Currency formatting affects product cards, checkout, invoices, receipts, reports, and order summaries.

| Setting | What it controls |
| --- | --- |
| Primary currency | The store currency used for prices and payments. Default is `USD`. Supported codes include `NGN`, `USD`, `EUR`, `GBP`, `ZAR`, `GHS`, `KES`, `UGX`, `TZS`, `XOF`, `XAF`, `EGP`, `MAD`, `CAD`, `AUD`, `INR`, `CNY`, `JPY`, `BRL`, `CHF`, and `SGD`. |
| Currency position | Whether the currency symbol/code appears before or after the amount. Default is `before`. |
| Decimal places | Number of decimal digits to show. Valid range is `0` to `6`; default is `2`. |
| Thousand separator | Character used between thousands. Default is comma. Only the first character is used. |
| Decimal separator | Character used before decimals. Default is period. It cannot be the same as the thousand separator. |

Changing currency after orders exist can make old and new reports harder to compare. For production sites, set this before launch and avoid changing it unless you have an accounting migration plan.

### Tax

Use this tab for simple site-wide tax handling.

| Setting | What it controls |
| --- | --- |
| Tax mode | `disabled` means Modern Commerce does not add site tax. `exclusive` means tax is calculated on top of the configured price. |
| Default tax rate | Site-wide percentage rate used when tax is enabled. Valid range is `0` to `100`. |

Modern Commerce currently uses a flat site tax rate from settings. Region-specific tax rules should be handled as a later tax module, not by overloading this field.

### Documents

Use this tab to control generated document number prefixes.

| Setting | What it controls |
| --- | --- |
| Invoice prefix | Prefix used for invoice numbers. Default is `INV`. Values are normalized to uppercase and may contain letters, numbers, `_`, and `-`. Maximum length is 20 characters. |
| Receipt prefix | Prefix used for receipt numbers. Default is `RCPT`. Values follow the same rules as invoice prefixes. |

Change prefixes before production transactions if you need a specific accounting format.

### Checkout

Use this tab when the business needs extra buyer details during checkout.

| Setting | What it controls |
| --- | --- |
| Collect contact information | Master toggle for extra checkout contact fields. Default is off. When off, checkout only needs the Moodle account details and gateway requirements. |
| Phone | Buyer phone field. Default is `optional` when contact fields are enabled. |
| Address | Buyer address field. Default is `hidden`. |
| City | Buyer city field. Default is `hidden`. |
| State/Province | Buyer state or province field. Default is `hidden`. |
| Country | Buyer country field. Default is `hidden`. |
| ZIP/Postal code | Buyer postal code field. Default is `hidden`. |

Each checkout field can be `hidden`, `optional`, or `required`. Keep fields hidden unless the store has a real fulfilment, invoice, tax, or support reason to collect them.

### Navigation

Use this tab to tune how Modern Commerce appears in Moodle navigation.

| Setting | What it controls |
| --- | --- |
| Admin navigation label | Label used for the Modern Commerce manager/admin entry. |
| Learner navigation label | Label used for learner-facing commerce navigation. |
| Hidden primary navigation items | Optional Moodle primary navigation nodes to hide globally: Home, Dashboard, My courses, and Site administration. |
| Cart position | Where the cart icon appears in Moodle's top-right user navigation. Options are `first` and `last`; default is `first`. |

Navigation changes can be affected by Moodle theme caching. If a label or hidden item does not change immediately, purge Moodle caches before debugging the code.

### Notifications

Use this tab for on-screen toast behavior.

| Setting | What it controls |
| --- | --- |
| Notification position | Screen corner/edge for Modern Commerce toast messages. Default is `top-right`. |
| Auto-dismiss delay | Number of milliseconds before a toast closes. Default is `4000`. Use `0` only when messages should stay visible until dismissed. |

This controls UI display behavior only. Delivery channels such as Slack and Teams are configured in Moodle's native plugin settings.

### Reviews

Use this tab to control built-in product/course review features.

| Setting | What it controls |
| --- | --- |
| Enable reviews | Allows Modern Commerce review display and submission features where the storefront and product views support them. Default is enabled. |

Disable reviews for stores that do not want public/social proof features or are not ready to moderate buyer feedback.

### Products

Use this tab to decide whether product editors can manually edit technical identifiers.

| Setting | What it controls |
| --- | --- |
| Show SKU field | Shows the SKU field on product forms. Default is off because Modern Commerce can generate and manage SKUs internally. |
| Show slug field | Shows the slug field on product forms. Default is off because Modern Commerce can generate slugs from product names. |
| Course detail sidebar position | Controls whether the public course detail purchase/product-information sidebar appears on the right or left side on desktop screens. Default is `right`. |

Leave both off for most stores. Enable them only when migrating from another commerce system or when the business has a fixed SKU/URL naming policy.

Use the course detail sidebar position to match the store's reading flow. The mobile layout always stacks the purchase/sidebar card before the main content.

### Certificates

Use this tab to configure the store-wide fallback for automatic program certificates.

| Setting | What it controls |
| --- | --- |
| Default program certificate template | Course Certificate template used by certificate-enabled programs that do not select their own override. `No global default` requires every certificate-enabled program to choose a template. |
| Manage Certificate Templates | Opens Moodle's native Course Certificate template manager in a new tab so templates can be created or edited without losing unsaved Modern Commerce settings. |

The selector and management link are available only when `mod_coursecertificate` and its `tool_certificate` backend are installed and upgraded and the current administrator has the required template permissions. A program-specific selection in Advanced Features always overrides the global default. When the optional add-on is unavailable, Modern Commerce shows an explanatory notice and continues operating without certificate controls.

### Contact Autoreply

The Contact Autoreply tab configures email behavior for Modern Commerce contact forms.

| Setting | What it controls |
| --- | --- |
| Recipient emails | Comma-separated admin/support recipients for contact notifications. |
| Autoreply enabled | Sends an automatic acknowledgement to the person who submitted the form. |
| Autoreply template | Optional Modern Commerce email template used for the buyer acknowledgement. |
| Autoreply subject/body | Optional custom subject and body override for the buyer acknowledgement. |
| Admin notification enabled | Sends an internal notification to the configured recipient emails. |
| Admin notification template | Optional Modern Commerce email template used for the internal notification. |
| Admin notification subject/body | Optional custom subject and body override for the internal notification. |
| Placeholders | Tokens that can be copied into custom subjects and bodies, including `{fullname}`, `{email}`, `{subject}`, `{phone}`, `{message}`, `{submitted_at}`, and `{sitename}`. |

Use templates when the message should follow the same design as other commerce emails. Use custom subject/body overrides for a store-specific contact workflow.

### Core reCAPTCHA Prerequisite

Spam protection for Modern Commerce public support and newsletter forms is controlled by Moodle core reCAPTCHA settings, not by a Modern Commerce-specific settings page.

Configure Moodle's global reCAPTCHA public and private keys before opening public forms on a production site. When both keys exist, Modern Commerce renders the challenge and verifies submissions server-side. When either key is missing, the challenge is omitted so development and private staging forms remain usable.

## Native Moodle Settings

Open `Site administration > Plugins > Local plugins > Modern Commerce`.

### Main Plugin Settings

The main native settings page includes several groups also available in the admin settings app:

- Currency formatting
- Reviews
- Product form fields
- Tax
- Checkout contact fields
- Notification display

It also includes operational settings that are not all shown in the React settings app.

### Payment Gateways

The native settings page contains a gateway registry link. Gateway credentials and gateway-specific webhook setup are managed from:

- `/local/moderncommerce/admin/gateways.php`
- `/local/moderncommerce/admin/webhooks.php`

Do not place live gateway secrets in documentation, screenshots, or support tickets. Use the gateway admin page and Moodle configuration storage.

### Webhook Security

Use these settings for payment callback hardening and retry behavior.

| Setting | What it controls |
| --- | --- |
| Enable webhook IP whitelist | Enables Modern Commerce webhook IP whitelist checks where supported by the gateway integration. Default is enabled. |
| Payment max retries | Maximum retry attempts for payment processing workflows. Default is `3`; valid range is `0` to `10`. |

Webhook signatures and gateway-specific verification still matter. IP allow-listing is an extra control, not the only security control.

### Notification Delivery

Use these settings for queued operational notifications.

| Setting | What it controls |
| --- | --- |
| Notification batch size | Number of queued notification records processed per batch. Default is `100`. |
| Slack enabled | Enables Slack delivery for configured operational notifications. |
| Slack webhook URL | Incoming Slack webhook endpoint. |
| Slack signing secret | Secret used for Slack verification where applicable. |
| Teams enabled | Enables Microsoft Teams delivery for configured operational notifications. |
| Teams webhook URL | Incoming Teams webhook endpoint. |
| Teams signing secret | Secret used for Teams verification where applicable. |

Keep webhook URLs and secrets private. Rotate them if they are exposed outside Moodle administration.

### Branding

Branding settings are seed values used to generate Modern Commerce runtime CSS variables across admin, storefront, learner, and public pages.

| Setting | What it controls |
| --- | --- |
| Primary | Main action and focus color. |
| Secondary | Sidebar and supporting brand color. |
| Accent | Highlight/accent color. |
| Surface | Base surface/background color. |
| Text | Primary text color and derived borders. |
| Link | Link color and link hover color. |
| Muted | Muted/subtle text color. |
| Radius | Base corner radius used by Modern Commerce UI. |
| Custom CSS | Advanced site-admin CSS appended to the generated brand CSS. |

Use the seed fields for normal branding. Use Custom CSS only for small site-specific overrides that cannot be expressed through the design system.

### Navigation Settings

The native Navigation settings page controls the same navigation values exposed in the admin settings app:

- Cart position in Moodle's top-right user navigation
- Admin navigation label
- Learner navigation label
- Hidden Moodle primary navigation items

Use one place as the operational source during rollout so changes are easier to audit.

### Subscription Settings

Open `Site administration > Plugins > Local plugins > Modern Commerce > Subscriptions`.

| Setting | What it controls |
| --- | --- |
| Send activation summary email | Sends a summary email when a subscription is activated. Default is enabled. |
| Send renewal digest email | Sends renewal digest emails. Default is enabled. |
| Reminder days | Comma-separated days before expiry/renewal when reminders are sent. Default is `7,3,1`. |
| History retention days | Number of days to keep subscription history. Default is `730`. |
| Cleanup old subscriptions | Enables cleanup of old subscription records according to retention settings. Default is off. |
| Keep deleted user history | Keeps subscription history when a Moodle user is deleted. Default is enabled. |
| Plan change cooldown | Number of days before another plan change is allowed. Default is `30`. |
| Allow lateral moves | Allows movement between plans at the same level. Default is enabled. |
| Store downgrade credit | Stores credit when a buyer downgrades. Default is enabled. |
| Cancel mode | `immediate` cancels as soon as the action is processed. `end_of_period` keeps access until the paid period ends. Default is `end_of_period`. |
| Allow user cancel | Allows learners/buyers to cancel their own subscriptions where the UI exposes cancellation. Default is enabled. |
| Enable trials | Enables trial-related subscription behavior. Default is enabled. |
| Trial auto convert | Automatically converts trial subscriptions according to the configured subscription workflow. Default is off. |

Recurring payment webhook and retry settings live in the main Modern Commerce settings because they are shared with payment processing.

## Storefront Design Settings

Storefront page layout and widget display settings are not global plugin settings. They are configured per widget from the storefront editor side panel at:

`/local/moderncommerce/index.php`

Use the storefront editor for catalog layout, widget content, homepage sections, featured products, and visual storefront composition.

## Recommended Setup Order

1. Set Store Identity.
2. Set Currency before creating production prices.
3. Set Tax before opening checkout.
4. Configure gateway credentials and webhooks.
5. Configure Checkout fields only if the business needs extra buyer details.
6. Configure Documents prefixes before the first production invoice/receipt.
7. Configure Navigation labels and cart position.
8. Configure Contact Autoreply and test both buyer and admin messages.
9. Configure Moodle core reCAPTCHA keys if public support or newsletter forms are exposed.
10. Choose the course detail sidebar position if the purchase panel should appear on the left instead of the default right side.
11. Configure Notification Delivery if Slack or Teams is part of store operations.
12. Configure Subscription Settings before selling subscription products.

## After Changing Settings

Run a practical smoke test after production settings change:

- Open the storefront and confirm prices format correctly.
- Add a product to cart and confirm checkout fields match the intended policy.
- Complete a test payment in sandbox mode.
- Submit public support and newsletter forms, including the reCAPTCHA challenge when Moodle core keys are configured.
- Confirm the order, receipt, invoice, and email messages use the expected business name, currency, tax, prefixes, and support details.
- Confirm admin notifications appear in the correct screen position and external channels if enabled.
- Purge Moodle caches if navigation labels, hidden navigation items, or branding changes do not appear immediately.

## Source of Truth for Developers

When adding or changing settings:

1. Update `settings.php` for Moodle native settings.
2. Update the relevant admin React page or external service when the setting must appear in the Modern Commerce admin app.
3. Add or update language strings in `lang/en/local_moderncommerce.php`.
4. Add validation/default handling in `classes/services/commerce_settings_service.php` or the owning service.
5. Add upgrade/default handling when existing sites need a migrated value.
6. Update this documentation and run the docs validation command.
