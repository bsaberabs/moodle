# Orders, Invoices, Refunds, and Reports

Modern Commerce separates cart, order, invoice, payment, refund, fulfillment, entitlement, and reporting records.

## Admin Routes

- `/local/moderncommerce/admin/orders.php`
- `/local/moderncommerce/admin/order_view.php?id=ID`
- `/local/moderncommerce/admin/invoices.php`
- `/local/moderncommerce/admin/reports.php`
- `/local/moderncommerce/admin/customers.php`
- `/local/moderncommerce/admin/customer.php?id=ID`
- `/local/moderncommerce/admin/audit_log.php`

## Buyer Routes

- `/local/moderncommerce/cart.php`
- `/local/moderncommerce/checkout.php`
- `/local/moderncommerce/order.php`
- `/local/moderncommerce/success.php`
- `/local/moderncommerce/learner/index.php`

## Order Flow

1. Buyer adds a product to cart.
2. Checkout creates or continues an order.
3. Payment attempt starts with the selected gateway.
4. Gateway callback or webhook updates the payment lifecycle.
5. Fulfillment grants course, bundle, program, key, or subscription access.
6. Entitlements and order history appear in the learner dashboard.

## Reporting

The admin dashboard contains **22 configurable widgets**: four KPI tiles and 18 analytics or table widgets. See [Dashboard widgets](dashboard-widgets.md) for the exact count, calculation, date-range behavior, and purpose of every widget.

Reporting tables include:

- `local_moderncommerce_report_daily`
- `local_moderncommerce_report_products`
- `local_moderncommerce_report_gateways`

Scheduled tasks can generate sales report snapshots. Use admin reports for operational review and raw tables for advanced diagnostics.

## Audit Logs

Use `/local/moderncommerce/admin/audit_log.php` to review immutable commerce events. Audit logs are for support, security review, and compliance-style traceability.
