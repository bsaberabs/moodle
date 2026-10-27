<?php
// This file is part of Moodle and is licensed under the
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Database foundation tests for local_moderncommerce.
 *
 * @package    local_moderncommerce
 * @category   test
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Verifies the clean commerce foundation schema exists after install.
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class schema_test extends advanced_testcase {
    /**
     * Canonical tables required for a clean installation.
     *
     * @return array
     */
    private function canonical_tables(): array {
        return [
            'local_moderncommerce_products',
            'local_moderncommerce_product_courses',
            'local_moderncommerce_product_prices',
            'local_moderncommerce_product_inventory',
            'local_moderncommerce_inventory_reservations',
            'local_moderncommerce_product_tags',
            'local_moderncommerce_product_categories',
            'local_moderncommerce_product_category_map',
            'local_moderncommerce_product_attributes',
            'local_moderncommerce_product_attribute_values',
            'local_moderncommerce_product_relations',
            'local_moderncommerce_course_meta',
            'local_moderncommerce_course_objectives',
            'local_moderncommerce_course_outline',
            'local_moderncommerce_billing_profiles',
            'local_moderncommerce_carts',
            'local_moderncommerce_cart_items',
            'local_moderncommerce_orders',
            'local_moderncommerce_order_operational',
            'local_moderncommerce_order_items',
            'local_moderncommerce_order_addresses',
            'local_moderncommerce_order_adjustments',
            'local_moderncommerce_order_status_history',
            'local_moderncommerce_invoices',
            'local_moderncommerce_invoice_items',
            'local_moderncommerce_gateways',
            'local_moderncommerce_payment_attempts',
            'local_moderncommerce_payment_events',
            'local_moderncommerce_webhook_events',
            'local_moderncommerce_payment_log',
            'local_moderncommerce_refunds',
            'local_moderncommerce_refund_items',
            'local_moderncommerce_coupons',
            'local_moderncommerce_coupon_targets',
            'local_moderncommerce_coupon_usage',
            'local_moderncommerce_enrollkeys',
            'local_moderncommerce_enrollkey_targets',
            'local_moderncommerce_key_usage',
            'local_moderncommerce_fulfillments',
            'local_moderncommerce_fulfillment_items',
            'local_moderncommerce_entitlements',
            'local_moderncommerce_entitlement_events',
            'local_moderncommerce_tax_rates',
            'local_moderncommerce_wishlist',
            'local_moderncommerce_audit_log',
            'local_moderncommerce_report_daily',
            'local_moderncommerce_report_products',
            'local_moderncommerce_report_gateways',
        ];
    }

    /**
     * Tables intentionally removed from the clean schema reset.
     *
     * @return array
     */
    private function legacy_tables(): array {
        return [
            'local_moderncommerce_items',
            'local_moderncommerce_pricing',
            'local_moderncommerce_cart',
            'local_moderncommerce_cartbundle',
            'local_moderncommerce_billing',
            'local_moderncommerce_transactions',
            'local_moderncommerce_enrollments',
            'local_moderncommerce_audit',
            'local_moderncommerce_paylog',
            'local_moderncommerce_reports',
            'local_moderncommerce_webhook',
            'local_ccp_bundles',
            'local_ccp_bundle_courses',
            'local_ccp_bundle_enrollments',
            'ccp_course_meta',
            'ccp_course_objectives',
            'ccp_course_outline',
            'ccp_bundle_meta',
            'ccp_bundle_outline',
            'ccp_bundle_mustpass',
            'ccp_bundle_prereq',
            'ccp_bundle_tags',
        ];
    }

    /**
     * Canonical foundation tables exist.
     */
    public function test_canonical_tables_exist(): void {
        global $DB;

        $dbman = $DB->get_manager();
        foreach ($this->canonical_tables() as $tablename) {
            $this->assertTrue(
                $dbman->table_exists(new xmldb_table($tablename)),
                $tablename . ' table should exist.'
            );
        }
    }

    /**
     * Legacy tables are not installed by the clean schema.
     */
    public function test_legacy_tables_are_not_installed(): void {
        global $DB;

        $dbman = $DB->get_manager();
        foreach ($this->legacy_tables() as $tablename) {
            $this->assertFalse(
                $dbman->table_exists(new xmldb_table($tablename)),
                $tablename . ' table should not exist in a clean installation.'
            );
        }
    }

    /**
     * Product master data does not carry currency in single-currency mode.
     */
    public function test_currency_is_not_product_master_data(): void {
        global $DB;

        $dbman = $DB->get_manager();

        $this->assertFalse(
            $dbman->field_exists(
                new xmldb_table('local_moderncommerce_products'),
                new xmldb_field('currency')
            ),
            'Product records should use the admin-configured system currency.'
        );

        $this->assertFalse(
            $dbman->field_exists(
                new xmldb_table('local_moderncommerce_product_prices'),
                new xmldb_field('currency')
            ),
            'Product prices should use the admin-configured system currency.'
        );
    }

    /**
     * Cart and order items retain price lineage for auditability.
     */
    public function test_price_lineage_fields_exist(): void {
        global $DB;

        $dbman = $DB->get_manager();
        foreach (['local_moderncommerce_cart_items', 'local_moderncommerce_order_items'] as $tablename) {
            $this->assertTrue(
                $dbman->field_exists(new xmldb_table($tablename), new xmldb_field('priceid')),
                $tablename . '.priceid should exist.'
            );
        }
    }

    /**
     * Gateway registry contains provider metadata needed for scalable checkout.
     */
    public function test_gateway_registry_fields_exist(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moderncommerce_gateways');
        foreach (['component', 'classname', 'methodtype', 'supportsrefunds', 'supportswebhooks', 'supportsrecurring'] as $field) {
            $this->assertTrue(
                $dbman->field_exists($table, new xmldb_field($field)),
                'local_moderncommerce_gateways.' . $field . ' should exist.'
            );
        }
    }

    /**
     * Order workflow state is separated from business order facts.
     */
    public function test_order_operational_fields_exist(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moderncommerce_order_operational');
        foreach (['orderid', 'paymentstatus', 'fulfillmentstatus', 'inventoryreserved', 'receiptsent'] as $field) {
            $this->assertTrue(
                $dbman->field_exists($table, new xmldb_field($field)),
                'local_moderncommerce_order_operational.' . $field . ' should exist.'
            );
        }
    }

    /**
     * Product catalog can scale without creating more product-specific tables.
     */
    public function test_product_extension_tables_exist(): void {
        global $DB;

        $dbman = $DB->get_manager();
        foreach (
            [
                'local_moderncommerce_product_categories',
                'local_moderncommerce_product_category_map',
                'local_moderncommerce_product_attributes',
                'local_moderncommerce_product_attribute_values',
                'local_moderncommerce_product_relations',
            ] as $tablename
        ) {
            $this->assertTrue(
                $dbman->table_exists(new xmldb_table($tablename)),
                $tablename . ' table should exist.'
            );
        }
    }

    /**
     * Entitlements are the commerce source of truth for course access.
     */
    public function test_entitlement_ledger_fields_exist(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_moderncommerce_entitlements');
        foreach (['sourcekey', 'userid', 'productid', 'courseid', 'orderid', 'orderitemid', 'status'] as $field) {
            $this->assertTrue(
                $dbman->field_exists($table, new xmldb_field($field)),
                'local_moderncommerce_entitlements.' . $field . ' should exist.'
            );
        }

        $eventtable = new xmldb_table('local_moderncommerce_entitlement_events');
        foreach (['entitlementid', 'eventuuid', 'eventtype', 'oldstatus', 'newstatus'] as $field) {
            $this->assertTrue(
                $dbman->field_exists($eventtable, new xmldb_field($field)),
                'local_moderncommerce_entitlement_events.' . $field . ' should exist.'
            );
        }
    }

    /**
     * Financial columns use commerce-grade precision.
     */
    public function test_money_columns_use_large_precision(): void {
        global $DB;

        foreach (
            [
                'local_moderncommerce_product_prices' => 'amount',
                'local_moderncommerce_orders' => 'total',
                'local_moderncommerce_order_items' => 'unitprice',
                'local_moderncommerce_payment_attempts' => 'amount',
                'local_moderncommerce_refunds' => 'amount',
                'local_moderncommerce_report_daily' => 'net',
            ] as $tablename => $fieldname
        ) {
            $column = $DB->get_columns($tablename)[$fieldname];
            $this->assertSame('20', (string)$column->max_length, $tablename . '.' . $fieldname . ' length.');
            $this->assertSame('6', (string)$column->scale, $tablename . '.' . $fieldname . ' scale.');
        }
    }
}
