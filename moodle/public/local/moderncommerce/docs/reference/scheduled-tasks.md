# Scheduled Tasks Reference

Scheduled tasks are defined in `db/tasks.php`.

| Task | Schedule | Purpose |
| --- | --- | --- |
| `local_moderncommerce\task\cleanup_cart` | Daily 02:00 | Clean old carts. |
| `local_moderncommerce\task\expire_keys` | Every 6 hours at minute 30 | Expire enrolment keys. |
| `local_moderncommerce\task\cancel_abandoned_orders` | Daily 03:00 | Cancel abandoned orders. |
| `local_moderncommerce\task\send_payment_reminders` | Daily 09:00 and 15:00 | Send payment reminders. |
| `local_moderncommerce\task\generate_sales_report` | Daily 01:05 | Generate sales report snapshots. |
| `local_moderncommerce\task\notify_daily_scan` | Daily 07:30 | Dispatch daily invoice/admin notification scan. |
| `local_moderncommerce\task\abandoned_cart_recovery` | Hourly at minute 15 | Send abandoned-cart recovery notifications. |
| `local_moderncommerce\task\notify_send_queue` | Every minute | Drain notification delivery queue. |
| `local_moderncommerce\task\notify_reap_stale` | Every 10 minutes | Requeue stale processing notifications. |
| `local_moderncommerce\task\notify_process_digests` | Daily 07:00 | Process notification digests. |
| `local_moderncommerce\subscription\task\check_expiring` | Daily 08:00 | Send subscription expiry reminders. |
| `local_moderncommerce\subscription\task\process_expired` | Daily 00:30 | Move expired subscriptions to grace or suspended states. |
| `local_moderncommerce\subscription\task\sync_access` | Every 6 hours | Sync subscription-derived access. |
| `local_moderncommerce\subscription\task\cleanup_old` | Monthly day 1 at 03:00 | Clean old expired/cancelled subscription data. |
| `local_moderncommerce\subscription\task\process_pending_changes` | Daily 00:15 | Apply scheduled plan changes. |
| `local_moderncommerce\subscription\task\process_trials` | Daily 00:45 | Process trial expirations. |
| `local_moderncommerce\subscription\task\process_recurring_payments` | Daily 01:00 | Process recurring subscription payments. |

Production sites should run Moodle cron every minute.
