# Database Reference

The install schema is defined in `db/install.xml`.

This build defines 82 Modern Commerce tables. The table groups below are the practical ownership map used for support and development.

## Product Catalog

- `local_moderncommerce_products`
- `local_moderncommerce_product_courses`
- `local_moderncommerce_product_prices`
- `local_moderncommerce_product_inventory`
- `local_moderncommerce_product_tags`
- `local_moderncommerce_product_categories`
- `local_moderncommerce_product_category_map`
- `local_moderncommerce_product_attributes`
- `local_moderncommerce_product_attribute_values`
- `local_moderncommerce_product_relations`
- `local_moderncommerce_course_meta`
- `local_moderncommerce_course_objectives`
- `local_moderncommerce_course_outline`

## Cart, Orders, Invoices, and Fulfillment

- `local_moderncommerce_billing_profiles`
- `local_moderncommerce_carts`
- `local_moderncommerce_cart_items`
- `local_moderncommerce_orders`
- `local_moderncommerce_order_operational`
- `local_moderncommerce_order_items`
- `local_moderncommerce_inventory_reservations`
- `local_moderncommerce_order_addresses`
- `local_moderncommerce_order_adjustments`
- `local_moderncommerce_order_status_history`
- `local_moderncommerce_invoices`
- `local_moderncommerce_invoice_items`
- `local_moderncommerce_fulfillments`
- `local_moderncommerce_fulfillment_items`
- `local_moderncommerce_entitlements`
- `local_moderncommerce_entitlement_events`

## Payments and Refunds

- `local_moderncommerce_gateways`
- `local_moderncommerce_payment_attempts`
- `local_moderncommerce_payment_events`
- `local_moderncommerce_webhook_events`
- `local_moderncommerce_payment_log`
- `local_moderncommerce_refunds`
- `local_moderncommerce_refund_items`

## Discounts, Keys, and Tax

- `local_moderncommerce_coupons`
- `local_moderncommerce_coupon_targets`
- `local_moderncommerce_coupon_usage`
- `local_moderncommerce_enrollkeys`
- `local_moderncommerce_enrollkey_targets`
- `local_moderncommerce_key_usage`
- `local_moderncommerce_tax_rates`

## Reporting, Reviews, Wishlist, and Audit

- `local_moderncommerce_wishlist`
- `local_moderncommerce_audit_log`
- `local_moderncommerce_report_daily`
- `local_moderncommerce_report_products`
- `local_moderncommerce_report_gateways`
- `local_moderncommerce_reviews`
- `local_moderncommerce_review_rxn`

## Storefront and Contacts

- `local_moderncommerce_bundle_meta`
- `local_moderncommerce_bundle_outline`
- `local_moderncommerce_bundle_mustpass`
- `local_moderncommerce_bundle_prereq`
- `local_moderncommerce_bundle_tags`
- `local_moderncommerce_program_progress`
- `local_moderncommerce_emailtpl`
- `local_moderncommerce_widget`
- `local_moderncommerce_widget_slide`
- `local_moderncommerce_widget_preset`
- `local_moderncommerce_subscriber`
- `local_moderncommerce_dashpref`
- `local_moderncommerce_contacts`
- `local_moderncommerce_contact_replies`

## Notifications

- `local_moderncommerce_notify_queue`
- `local_moderncommerce_notify_log`
- `local_moderncommerce_notify_digest`
- `local_moderncommerce_notify_identity`
- `local_moderncommerce_notify_suppression`

## Subscriptions

- `local_moderncommerce_subscription_plans`
- `local_moderncommerce_subscription_plan_features`
- `local_moderncommerce_subscription_features`
- `local_moderncommerce_subscription_feature_map`
- `local_moderncommerce_subscription_access_rules`
- `local_moderncommerce_user_subscriptions`
- `local_moderncommerce_subscription_history`
- `local_moderncommerce_subscription_reminders`
- `local_moderncommerce_subscription_access`
- `local_moderncommerce_subscription_emailtpl`
- `local_moderncommerce_subscription_keys`
- `local_moderncommerce_subscription_key_usage`
- `local_moderncommerce_subscription_log`
