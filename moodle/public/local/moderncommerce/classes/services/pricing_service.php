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
 * Pricing Service
 *
 * Service to check and retrieve course pricing information
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;


/**
 * Pricing service class
 */
class pricing_service {
    /**
     * Cached formatting settings loaded from config.
     *
     * @var \stdClass|null
     */
    private static $formatsettings = null;

    /**
     * Get and cache currency/number formatting settings.
     *
     * This behaves like a set of "constants" for the duration of the request,
     * but still honours admin configuration.
     *
     * @return \stdClass
     */
    private static function get_format_settings() {

        if (self::$formatsettings === null) {
            self::$formatsettings = commerce_settings_service::get_currency_config();
        }

        return self::$formatsettings;
    }

    /**
     * Get the configured site currency display settings.
     *
     * @return \stdClass Currency display configuration.
     */
    public static function get_currency_config() {

        $settings = self::get_format_settings();
        return (object)[
            'currency' => $settings->primarycurrency,
            'symbol' => $settings->symbol,
            'position' => $settings->position,
            'decimals' => $settings->decimals,
            'thousand' => $settings->thousand,
            'decimal' => $settings->decimal,
        ];
    }

    /**
     * Snapshot the current currency display settings on an order record.
     *
     * @param \stdClass $record Order record before insert/update.
     * @return \stdClass Mutated order record.
     */
    public static function apply_currency_snapshot(\stdClass $record) {

        $config = self::get_currency_config();
        if (empty($record->currency)) {
            $record->currency = $config->currency;
        }

        $record->currencysymbol = $config->symbol;
        $record->currencyposition = $config->position;
        $record->decimalplaces = $config->decimals;
        $record->thousandseparator = $config->thousand;
        $record->decimalseparator = $config->decimal;
        return $record;
    }
    /**
     * Check if a course has pricing set
     *
     * @param int $courseid The course ID
     * @return bool True if pricing exists, false otherwise
     */
    public static function has_pricing($courseid) {

        return self::get_course_pricing((int) $courseid) !== null;
    }

    /**
     * Get pricing information for a course
     *
     * @param int $courseid The course ID
     * @return object|null Pricing object or null if not found
     */
    public static function get_course_pricing($courseid) {

        $resolved = price_resolver::resolve_for_course((int) $courseid, 1, false);
        return $resolved ? self::build_pricing_from_resolved_price($resolved) : null;
    }
    /**
     * Get formatted price for a course
     *
     * @param int $courseid The course ID
     * @param bool $includecurrency Include currency symbol
     * @return string Formatted price string
     */
    public static function get_formatted_price($courseid, $includecurrency = true) {
        $pricing = self::get_course_pricing($courseid);

        if (!$pricing) {
            return '';
        }

        if ($pricing->is_free) {
            return get_string('free', 'local_moderncommerce');
        }
        return self::format_price($pricing->final_price, null, $includecurrency);
    }

    /**
     * Get formatted original price (for display when on sale)
     *
     * @param int $courseid The course ID
     * @param bool $includecurrency Include currency symbol
     * @return string Formatted original price string
     */
    public static function get_formatted_original_price($courseid, $includecurrency = true) {
        $pricing = self::get_course_pricing($courseid);

        if (!$pricing || !$pricing->has_sale) {
            return '';
        }

        return self::format_price($pricing->price, null, $includecurrency);
    }

    /**
     * Get currency symbol
     *
     * @param string $currency Currency code
     * @return string Currency symbol
     */
    public static function get_currency_symbol($currency) {

        // If this is the primary currency, prefer the custom symbol from settings.
        $settings = self::get_format_settings();
        if (!empty($settings->primarycurrency) && !empty($settings->symbol) && $currency === $settings->primarycurrency) {
            return $settings->symbol;
        }

        return self::get_known_currency_symbol($currency);
    }

    /**
     * Get the built-in symbol for a known ISO currency code.
     *
     * @param string $currency Currency code
     * @return string Currency symbol or currency code
     */
    private static function get_known_currency_symbol($currency) {

        return commerce_settings_service::known_currency_symbol((string)$currency);
    }
    /**
     * Check if course is purchasable (has pricing and stock available)
     *
     * @param int $courseid The course ID
     * @return bool True if purchasable
     */
    public static function is_purchasable($courseid) {

        $pricing = self::get_course_pricing($courseid);
        if (!$pricing || empty($pricing->enabled)) {
            return false;
        }

        // Check if user is already enrolled.
        global $USER;
        if (is_enrolled(\context_course::instance($courseid), $USER)) {
            return false;
        }

        // Check stock availability.
        if (!empty($pricing->stockmanaged) && empty($pricing->allowbackorder)) {
            $stockinfo = self::get_stock_info($courseid);
            if (!$stockinfo->is_in_stock) {
                return false;
                // Out of stock.
            }
        }

        return true;
    }
    /**
     * Get stock information for a course
     *
     * @param int $courseid The course ID
     * @return object Stock information (available, total, sold, is_unlimited)
     */
    public static function get_stock_info($courseid) {

        $pricing = self::get_course_pricing($courseid);
        $info = new \stdClass();
        $info->total = $pricing && !empty($pricing->stockmanaged) ? (int) $pricing->stock : 0;
        $info->reserved = $pricing ? (int) $pricing->reservedstock : 0;
        $info->is_unlimited = !$pricing || empty($pricing->stockmanaged);
        $info->sold = $pricing ? self::get_sold_count_for_product((int) $pricing->productid) : 0;
        $info->available = $info->is_unlimited ? PHP_INT_MAX : max(0, $info->total - $info->reserved - $info->sold);
        $info->is_in_stock = $info->is_unlimited || !empty($pricing->allowbackorder) || $info->available > 0;
        return $info;
    }
    /**
     * Get pricing data for multiple courses
     *
     * @param array $courseids Array of course IDs
     * @return array Array of pricing objects keyed by course ID
     */
    public static function get_bulk_pricing($courseids) {

        global $DB;
        if (empty($courseids)) {
            return [];
        }

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $params['relationtype'] = 'included';
        $params['producttype'] = 'course';
        $params['pricetype'] = 'regular';
        $params['now'] = time();
        $sql = "SELECT pc.id AS mapid,
                       pc.courseid,
                       p.id AS productid,
                       p.producttype,
                       p.name,
                       p.slug,
                       p.sku,
                       p.status,
                       p.visible,
                       p.featured,
                       p.taxable,
                       p.taxcategory,
                       p.enrolduration,
                       pr.id AS priceid,
                       pr.amount,
                       pr.compareamount,
                       pr.startdate,
                       pr.enddate,
                       pr.enabled AS priceenabled,
                       COALESCE(inv.stockmanaged, 0) AS stockmanaged,
                       inv.stock,
                       COALESCE(inv.reservedstock, 0) AS reservedstock,
                       COALESCE(inv.allowbackorder, 0) AS allowbackorder
                  FROM {local_moderncommerce_product_courses} pc
                  JOIN {local_moderncommerce_products} p ON p.id = pc.productid
             LEFT JOIN {local_moderncommerce_product_prices} pr ON pr.id = (
                           SELECT MIN(prmin.id)
                             FROM {local_moderncommerce_product_prices} prmin
                            WHERE prmin.productid = p.id
                              AND prmin.pricetype = :pricetype
                              AND prmin.enabled = 1
                              AND (prmin.startdate IS NULL OR prmin.startdate = 0 OR prmin.startdate <= :now)
                              AND (prmin.enddate IS NULL OR prmin.enddate = 0 OR prmin.enddate >= :now)
                       )
             LEFT JOIN {local_moderncommerce_product_inventory} inv ON inv.productid = p.id
                 WHERE pc.courseid {$insql}
                   AND pc.relationtype = :relationtype
                   AND p.producttype = :producttype
              ORDER BY pc.courseid ASC,
                       CASE WHEN p.status = 'active' THEN 0 ELSE 1 END ASC,
                       p.visible DESC,
                       p.id ASC";
        $records = $DB->get_records_sql($sql, $params);
        $result = [];
        foreach ($records as $record) {
            $courseid = (int) $record->courseid;
            if (isset($result[$courseid])) {
                continue;
            }

            $pricing = self::build_pricing_from_product_record($record);
            if ($pricing) {
                $result[$courseid] = $pricing;
            }
        }

        return $result;
    }

    /**
     * Build the legacy pricing object shape from a resolved canonical price.
     *
     * @param \stdClass $resolved Resolved price snapshot.
     * @return \stdClass Pricing object.
     */
    private static function build_pricing_from_resolved_price(\stdClass $resolved): \stdClass {

        $regularprice = (float) $resolved->regularprice;
        $finalprice = (float) $resolved->final_price;
        $saleactive = !empty($resolved->has_sale);
        $pricing = new \stdClass();
        $pricing->id = (int) $resolved->priceid;
        $pricing->priceid = (int) $resolved->priceid;
        $pricing->productid = (int) $resolved->productid;
        $pricing->courseid = empty($resolved->courseid) ? 0 : (int) $resolved->courseid;
        $pricing->price = $regularprice;
        $pricing->saleprice = $saleactive ? $finalprice : null;
        $pricing->currency = (string) $resolved->currency;
        $pricing->salestartdate = $resolved->startdate;
        $pricing->saleenddate = $resolved->enddate;
        $pricing->enabled = !empty($resolved->enabled);
        $pricing->productstatus = (string) $resolved->productstatus;
        $pricing->visible = !empty($resolved->visible);
        $pricing->featured = !empty($resolved->featured) ? 1 : 0;
        $pricing->taxable = !empty($resolved->taxable) ? 1 : 0;
        $pricing->taxcategory = $resolved->taxcategory ?? null;
        $pricing->enrolduration = empty($resolved->enrolduration) ? null : (int) $resolved->enrolduration;
        $pricing->stockmanaged = !empty($resolved->stockmanaged) ? 1 : 0;
        $pricing->stock = (int) $resolved->stock;
        $pricing->reservedstock = (int) $resolved->reservedstock;
        $pricing->allowbackorder = !empty($resolved->allowbackorder) ? 1 : 0;
        $pricing->has_sale = $saleactive;
        $pricing->final_price = $finalprice;
        $pricing->discount_amount = (float) $resolved->discount_amount;
        $pricing->discount_percentage = (int) $resolved->discount_percentage;
        $pricing->is_free = !empty($resolved->is_free);
        $pricing->has_stock = !empty($resolved->has_stock);
        $pricing->is_unlimited = !empty($resolved->is_unlimited);
        return $pricing;
    }

    /**
     * Get the primary course product row with regular price and inventory state.
     *
     * @param int $courseid Course ID.
     * @return \stdClass|null
     */
    private static function get_course_product_record($courseid) {

        global $DB;
        $sql = "SELECT pc.id AS mapid,
                       pc.courseid,
                       p.id AS productid,
                       p.producttype,
                       p.name,
                       p.slug,
                       p.sku,
                       p.status,
                       p.visible,
                       p.featured,
                       p.taxable,
                       p.taxcategory,
                       p.enrolduration,
                       pr.id AS priceid,
                       pr.amount,
                       pr.compareamount,
                       pr.startdate,
                       pr.enddate,
                       pr.enabled AS priceenabled,
                       COALESCE(inv.stockmanaged, 0) AS stockmanaged,
                       inv.stock,
                       COALESCE(inv.reservedstock, 0) AS reservedstock,
                       COALESCE(inv.allowbackorder, 0) AS allowbackorder
                  FROM {local_moderncommerce_product_courses} pc
                  JOIN {local_moderncommerce_products} p ON p.id = pc.productid
             LEFT JOIN {local_moderncommerce_product_prices} pr ON pr.id = (
                           SELECT MIN(prmin.id)
                             FROM {local_moderncommerce_product_prices} prmin
                            WHERE prmin.productid = p.id
                              AND prmin.pricetype = :pricetype
                              AND prmin.enabled = 1
                              AND (prmin.startdate IS NULL OR prmin.startdate = 0 OR prmin.startdate <= :now)
                              AND (prmin.enddate IS NULL OR prmin.enddate = 0 OR prmin.enddate >= :now)
                       )
             LEFT JOIN {local_moderncommerce_product_inventory} inv ON inv.productid = p.id
                 WHERE pc.courseid = :courseid
                   AND pc.relationtype = :relationtype
                   AND p.producttype = :producttype
              ORDER BY CASE WHEN p.status = 'active' THEN 0 ELSE 1 END ASC,
                       p.visible DESC,
                       p.id ASC";
        $records = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'relationtype' => 'included',
            'producttype' => 'course',
            'pricetype' => 'regular',
            'now' => time(),
        ], 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Build the legacy pricing object shape from canonical product tables.
     *
     * @param \stdClass $record Product/price/inventory row.
     * @return \stdClass|null
     */
    private static function build_pricing_from_product_record(\stdClass $record) {

        if (empty($record->priceid)) {
            return null;
        }

        $now = time();
        $currency = self::get_currency_config()->currency;
        $amount = (float) $record->amount;
        $compareamount = $record->compareamount === null ? 0.0 : (float) $record->compareamount;
        $saleprice = ($compareamount > $amount) ? $amount : null;
        $regularprice = ($compareamount > $amount) ? $compareamount : $amount;
        $saleactive = $saleprice !== null
            && (empty($record->startdate) || (int) $record->startdate <= $now)
            && (empty($record->enddate) || (int) $record->enddate >= $now);
        $priceactive = !empty($record->priceenabled);
        $pricing = new \stdClass();
        $pricing->id = (int) $record->priceid;
        $pricing->priceid = (int) $record->priceid;
        $pricing->productid = (int) $record->productid;
        $pricing->courseid = (int) $record->courseid;
        $pricing->price = $regularprice;
        $pricing->saleprice = $saleprice;
        $pricing->currency = $currency;
        $pricing->salestartdate = empty($record->startdate) ? null : (int) $record->startdate;
        $pricing->saleenddate = empty($record->enddate) ? null : (int) $record->enddate;
        $pricing->enabled = $priceactive && (string) $record->status === 'active' && !empty($record->visible);
        $pricing->productstatus = (string) $record->status;
        $pricing->visible = !empty($record->visible);
        $pricing->featured = !empty($record->featured) ? 1 : 0;
        $pricing->taxable = !empty($record->taxable) ? 1 : 0;
        $pricing->taxcategory = $record->taxcategory ?? null;
        $pricing->enrolduration = empty($record->enrolduration) ? null : (int) $record->enrolduration;
        $pricing->stockmanaged = !empty($record->stockmanaged) ? 1 : 0;
        $pricing->stock = $record->stock === null ? 0 : (int) $record->stock;
        $pricing->reservedstock = (int) $record->reservedstock;
        $pricing->allowbackorder = !empty($record->allowbackorder) ? 1 : 0;
        $pricing->has_sale = $saleactive;
        $pricing->final_price = $saleactive ? $saleprice : $regularprice;
        $pricing->discount_amount = $saleactive ? ($regularprice - $saleprice) : 0;
        $pricing->discount_percentage = $saleactive && $regularprice > 0
            ? round((($regularprice - $saleprice) / $regularprice) * 100)
            : 0;
        $pricing->is_free = (float) $pricing->final_price == 0.0;
        $pricing->has_stock = empty($pricing->stockmanaged)
            || $pricing->allowbackorder
            || $pricing->stock > $pricing->reservedstock;
        $pricing->is_unlimited = empty($pricing->stockmanaged);
        return $pricing;
    }

    /**
     * Count paid/completed quantity sold for a canonical product.
     *
     * @param int $productid Product ID.
     * @return int Quantity sold.
     */
    private static function get_sold_count_for_product($productid) {

        global $DB;
        if ($productid <= 0) {
            return 0;
        }

        return (int) $DB->get_field_sql("SELECT COALESCE(SUM(oi.quantity), 0)
               FROM {local_moderncommerce_order_items} oi
               JOIN {local_moderncommerce_orders} o ON o.id = oi.orderid
              WHERE oi.productid = :productid
                AND o.status IN ('paid', 'completed')", ['productid' => $productid]);
    }
    /**
     * Format an amount using supplied currency display settings.
     *
     * @param float $amount Amount to format
     * @param \stdClass $settings Currency settings
     * @param bool $includecurrency Include currency symbol
     * @return string Formatted amount
     */
    private static function format_with_settings($amount, \stdClass $settings, $includecurrency = true) {

        $formatted = number_format((float)$amount, $settings->decimals, $settings->decimal, $settings->thousand);
        if (!$includecurrency) {
            return $formatted;
        }

        $symbol = !empty($settings->symbol) ? $settings->symbol : self::get_known_currency_symbol($settings->primarycurrency);
        return ($settings->position === 'after') ? ($formatted . ' ' . $symbol) : ($symbol . $formatted);
    }

    /**
     * Build display settings from an order snapshot.
     *
     * @param \stdClass $order Order record
     * @return \stdClass Currency display settings
     */
    private static function get_order_format_settings(\stdClass $order) {

        $settings = clone self::get_format_settings();
        if (!empty($order->currency)) {
            $settings->primarycurrency = $order->currency;
        }

        if (!empty($order->currencysymbol)) {
            $settings->symbol = $order->currencysymbol;
        } else {
            $settings->symbol = self::get_known_currency_symbol($settings->primarycurrency);
        }

        if (!empty($order->currencyposition)) {
            $settings->position = $order->currencyposition;
        }

        if (isset($order->decimalplaces) && $order->decimalplaces !== '' && $order->decimalplaces !== null) {
            $settings->decimals = (int)$order->decimalplaces;
        }

        if (isset($order->thousandseparator) && $order->thousandseparator !== null) {
            $settings->thousand = $order->thousandseparator;
        }

        if (isset($order->decimalseparator) && $order->decimalseparator !== null && $order->decimalseparator !== '') {
            $settings->decimal = $order->decimalseparator;
        }

        return $settings;
    }

    /**
     * Format a price amount using the configured currency
     *
     * @param float $amount The price amount
     * @param string|null $currency Currency code (uses primary_currency config if not provided)
     * @param bool $includecurrency Include currency symbol
     * @return string Formatted price string
     */
    public static function format_price($amount, $currency = null, $includecurrency = true) {

        $settings = self::get_format_settings();
        $active = clone $settings;
        if ($currency !== null && $currency !== '' && $currency !== $settings->primarycurrency) {
            $active->primarycurrency = $currency;
            $active->symbol = self::get_known_currency_symbol($currency);
        }

        return self::format_with_settings($amount, $active, $includecurrency);
    }

    /**
     * Format an amount using the currency display snapshot stored on an order.
     *
     * @param float $amount Amount to format
     * @param \stdClass $order Order record
     * @param bool $includecurrency Include currency symbol
     * @return string Formatted amount
     */
    public static function format_order_price($amount, \stdClass $order, $includecurrency = true) {

        return self::format_with_settings($amount, self::get_order_format_settings($order), $includecurrency);
    }
}
