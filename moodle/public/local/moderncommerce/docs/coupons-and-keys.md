# Coupons and Enrolment Keys

Coupons and keys support promotional, manual, and prepaid access workflows.

## Admin Routes

- `/local/moderncommerce/admin/coupons.php`
- `/local/moderncommerce/admin/keys.php`
- `/local/moderncommerce/admin/course_keys.php`
- `/local/moderncommerce/admin/bundle_keys.php`
- `/local/moderncommerce/admin/subscription_keys.php`

## Buyer Routes

- `/local/moderncommerce/redeem.php`
- `/local/moderncommerce/redeem_bundle.php`
- `/local/moderncommerce/redeem_multiple.php`

## Coupons

Coupon records live in:

- `local_moderncommerce_coupons`
- `local_moderncommerce_coupon_targets`
- `local_moderncommerce_coupon_usage`

Use targets to limit coupons by product, bundle, category, or supported target type. Use usage records to audit redemption.

## Enrolment Keys

Enrolment key records live in:

- `local_moderncommerce_enrollkeys`
- `local_moderncommerce_enrollkey_targets`
- `local_moderncommerce_key_usage`

Keys are useful for prepaid course access, bulk corporate sales, offline payment workflows, and support-assisted enrolment.

## Subscription Keys

Subscription key records live in:

- `local_moderncommerce_subscription_keys`
- `local_moderncommerce_subscription_key_usage`

Use subscription keys when a learner should activate a plan without going through online checkout.

## Scheduled Maintenance

`local_moderncommerce\task\expire_keys` runs every six hours at minute 30.
