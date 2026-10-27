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
 * External API for listing product price rows.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\pricing;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_moderncommerce\services\pricing_service;

/**
 * List normalized product prices for the React admin pricing app.
 */
class list_prices extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'productid' => new external_value(PARAM_INT, 'Product ID.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the price listing.
     *
     * @param int $productid Product ID.
     * @return array
     */
    public static function execute(int $productid): array {
        global $DB;

        ['productid' => $productid] = self::validate_parameters(self::execute_parameters(), [
            'productid' => $productid,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $currency = self::get_currency_data();
        if ($productid <= 0) {
            return self::empty_response($currency, get_string('productnotfound', 'local_moderncommerce'));
        }

        $product = $DB->get_record('local_moderncommerce_products', ['id' => $productid], 'id, name');
        if (!$product) {
            return self::empty_response($currency, get_string('productnotfound', 'local_moderncommerce'));
        }

        $records = $DB->get_records_sql(
            "SELECT id,
                    productid,
                    pricetype,
                    amount,
                    compareamount,
                    minquantity,
                    maxquantity,
                    startdate,
                    enddate,
                    enabled,
                    timecreated,
                    timemodified
               FROM {local_moderncommerce_product_prices}
              WHERE productid = :productid
           ORDER BY enabled DESC,
                    pricetype ASC,
                    COALESCE(minquantity, 1) ASC,
                    COALESCE(startdate, 0) ASC,
                    id ASC",
            ['productid' => $productid]
        );

        $items = [];
        foreach ($records as $record) {
            $items[] = self::format_price_record($record);
        }

        return [
            'success' => true,
            'message' => '',
            'productid' => (int) $product->id,
            'productname' => format_string($product->name, true, ['context' => $context]),
            'currency' => $currency,
            'items' => $items,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether prices were loaded.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'productid' => new external_value(PARAM_INT, 'Product ID.'),
            'productname' => new external_value(PARAM_TEXT, 'Product name.'),
            'currency' => self::currency_structure(),
            'items' => new external_multiple_structure(self::price_structure()),
        ]);
    }

    /**
     * Price return structure.
     *
     * @return external_single_structure
     */
    private static function price_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Price row ID.'),
            'productid' => new external_value(PARAM_INT, 'Product ID.'),
            'pricetype' => new external_value(PARAM_ALPHANUMEXT, 'Price type.'),
            'pricetypelabel' => new external_value(PARAM_TEXT, 'Display price type.'),
            'amount' => new external_value(PARAM_FLOAT, 'Price amount.'),
            'compareamount' => new external_value(PARAM_FLOAT, 'Compare-at amount.'),
            'displayamount' => new external_value(PARAM_TEXT, 'Formatted amount.'),
            'displaycompareamount' => new external_value(PARAM_TEXT, 'Formatted compare-at amount.'),
            'minquantity' => new external_value(PARAM_INT, 'Minimum quantity.'),
            'maxquantity' => new external_value(PARAM_INT, 'Maximum quantity, or 0 for no limit.'),
            'startdate' => new external_value(PARAM_INT, 'Start timestamp, or 0.'),
            'enddate' => new external_value(PARAM_INT, 'End timestamp, or 0.'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether price is enabled.'),
            'active' => new external_value(PARAM_BOOL, 'Whether price is currently active.'),
            'timecreated' => new external_value(PARAM_INT, 'Created timestamp.'),
            'timemodified' => new external_value(PARAM_INT, 'Modified timestamp.'),
        ]);
    }

    /**
     * Currency return structure.
     *
     * @return external_single_structure
     */
    private static function currency_structure(): external_single_structure {
        return new external_single_structure([
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Currency code.'),
            'symbol' => new external_value(PARAM_TEXT, 'Currency symbol.'),
            'position' => new external_value(PARAM_ALPHA, 'Symbol position.'),
            'decimals' => new external_value(PARAM_INT, 'Decimal places.'),
        ]);
    }

    /**
     * Format one price row.
     *
     * @param \stdClass $record Price record.
     * @return array
     */
    private static function format_price_record(\stdClass $record): array {
        $now = time();
        $startdate = empty($record->startdate) ? 0 : (int) $record->startdate;
        $enddate = empty($record->enddate) ? 0 : (int) $record->enddate;
        $amount = (float) $record->amount;
        $compareamount = $record->compareamount === null ? 0.0 : (float) $record->compareamount;
        $active = !empty($record->enabled)
            && ($startdate === 0 || $startdate <= $now)
            && ($enddate === 0 || $enddate >= $now);

        return [
            'id' => (int) $record->id,
            'productid' => (int) $record->productid,
            'pricetype' => (string) $record->pricetype,
            'pricetypelabel' => self::get_price_type_label((string) $record->pricetype),
            'amount' => $amount,
            'compareamount' => $compareamount,
            'displayamount' => pricing_service::format_price($amount),
            'displaycompareamount' => $compareamount > 0 ? pricing_service::format_price($compareamount) : '',
            'minquantity' => empty($record->minquantity) ? 1 : (int) $record->minquantity,
            'maxquantity' => empty($record->maxquantity) ? 0 : (int) $record->maxquantity,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'enabled' => !empty($record->enabled),
            'active' => $active,
            'timecreated' => (int) $record->timecreated,
            'timemodified' => (int) $record->timemodified,
        ];
    }

    /**
     * Get configured currency data.
     *
     * @return array
     */
    private static function get_currency_data(): array {
        $config = pricing_service::get_currency_config();

        return [
            'code' => (string) $config->currency,
            'symbol' => (string) $config->symbol,
            'position' => (string) $config->position,
            'decimals' => (int) $config->decimals,
        ];
    }

    /**
     * Build a response for invalid product requests.
     *
     * @param array $currency Currency data.
     * @param string $message Result message.
     * @return array
     */
    private static function empty_response(array $currency, string $message): array {
        return [
            'success' => false,
            'message' => $message,
            'productid' => 0,
            'productname' => '',
            'currency' => $currency,
            'items' => [],
        ];
    }

    /**
     * Convert an internal price type to a display label.
     *
     * @param string $pricetype Price type.
     * @return string
     */
    private static function get_price_type_label(string $pricetype): string {
        $stringid = 'pricetype_' . $pricetype;
        if (get_string_manager()->string_exists($stringid, 'local_moderncommerce')) {
            return get_string($stringid, 'local_moderncommerce');
        }

        return ucfirst($pricetype);
    }
}
