# Subscriptions

Modern Commerce includes a subscription subsystem for recurring access plans, plan features, plan access rules, subscription keys, lifecycle emails, and subscription reports.

## Admin Routes

- `/local/moderncommerce/admin/subscriptions.php`
- `/local/moderncommerce/admin/subscription_features.php`
- `/local/moderncommerce/admin/subscription_plan_access.php?id=ID`
- `/local/moderncommerce/admin/subscription_keys.php`
- `/local/moderncommerce/admin/subscription_emails.php`
- `/local/moderncommerce/admin/subscription_subscribers.php`

## Buyer Routes

- `/local/moderncommerce/subscribe.php`
- `/local/moderncommerce/learner/index.php#/subscriptions`

## Main Data Tables

- `local_moderncommerce_subscription_plans`
- `local_moderncommerce_subscription_plan_features`
- `local_moderncommerce_subscription_features`
- `local_moderncommerce_subscription_feature_map`
- `local_moderncommerce_subscription_access_rules`
- `local_moderncommerce_user_subscriptions`
- `local_moderncommerce_subscription_history`
- `local_moderncommerce_subscription_access`
- `local_moderncommerce_subscription_keys`
- `local_moderncommerce_subscription_key_usage`
- `local_moderncommerce_subscription_log`

## Plan Setup

1. Create a plan.
2. Set billing cycle, price, trial days, grace period, and status.
3. Configure feature matrix entries.
4. Add access rules for courses, categories, or bundles.
5. Preview the public subscribe flow.
6. Test subscription activation and access sync.

## Scheduled Tasks

Subscription tasks handle expiring reminders, expired subscriptions, access sync, cleanup, pending plan changes, trials, and recurring payments. See [scheduled tasks](reference/scheduled-tasks.md).
