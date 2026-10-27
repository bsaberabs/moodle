# Payments

Modern Commerce supports gateway-driven checkout, manual workflows, payment attempts, payment events, webhook events, refunds, and payment logs.

## Gateway Admin

- `/local/moderncommerce/admin/gateways.php` - configure gateways
- `/local/moderncommerce/admin/webhooks.php` - webhook status
- `/local/moderncommerce/admin/webhook_events.php` - webhook event log
- `/local/moderncommerce/admin/payment_events.php` - gateway lifecycle events

## Supported Gateway Entry Points

Payment entry points exist for:

- PayPal
- Stripe
- Paystack
- Flutterwave

Relevant route patterns:

- `/local/moderncommerce/payment/{gateway}_init.php`
- `/local/moderncommerce/payment/{gateway}_callback.php`
- `/local/moderncommerce/payment/{gateway}_webhook.php`
- `/local/moderncommerce/payment/init.php`
- `/local/moderncommerce/payment/callback.php`
- `/local/moderncommerce/payment/webhook.php`

## Production Checklist

- Use HTTPS.
- Configure gateway credentials in Moodle admin, not in source files.
- Configure webhook URLs in the gateway dashboard.
- Use gateway signing secrets where supported.
- Test successful payment, failed payment, cancelled checkout, refund, and duplicate webhook delivery.
- Confirm Moodle cron is running so asynchronous follow-up tasks are processed.

## Refunds

Refund records live in:

- `local_moderncommerce_refunds`
- `local_moderncommerce_refund_items`

Refund management is available from the order/admin workflows. Payment gateway behavior depends on gateway integration and gateway configuration.

## Diagnostics

Use:

- `/local/moderncommerce/admin/payment_events.php`
- `/local/moderncommerce/admin/webhook_events.php`
- `/local/moderncommerce/admin/audit_log.php`

For sensitive logs, redact customer and gateway data before sharing outside the operations team.
