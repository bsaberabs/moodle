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
 * External API for saving product price rows.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\pricing;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Create or update a normalized product price row.
 */
class save_price extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Price row ID, or 0 for a new price.', VALUE_DEFAULT, 0),
            'productid' => new external_value(PARAM_INT, 'Product ID.', VALUE_REQUIRED),
            'pricetype' => new external_value(PARAM_ALPHANUMEXT, 'Price type.', VALUE_DEFAULT, 'regular'),
            'amount' => new external_value(PARAM_FLOAT, 'Price amount.', VALUE_REQUIRED),
            'compareamount' => new external_value(PARAM_FLOAT, 'Compare-at amount.', VALUE_DEFAULT, 0),
            'minquantity' => new external_value(PARAM_INT, 'Minimum quantity.', VALUE_DEFAULT, 1),
            'maxquantity' => new external_value(PARAM_INT, 'Maximum quantity, or 0 for no limit.', VALUE_DEFAULT, 0),
            'startdate' => new external_value(PARAM_INT, 'Start timestamp, or 0.', VALUE_DEFAULT, 0),
            'enddate' => new external_value(PARAM_INT, 'End timestamp, or 0.', VALUE_DEFAULT, 0),
            'enabled' => new external_value(PARAM_BOOL, 'Whether the price is enabled.', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Save a product price.
     *
     * @param int $id Price row ID.
     * @param int $productid Product ID.
     * @param string $pricetype Price type.
     * @param float $amount Amount.
     * @param float $compareamount Compare-at amount.
     * @param int $minquantity Minimum quantity.
     * @param int $maxquantity Maximum quantity.
     * @param int $startdate Start date timestamp.
     * @param int $enddate End date timestamp.
     * @param bool $enabled Enabled flag.
     * @return array
     */
    public static function execute(
        int $id = 0,
        int $productid = 0,
        string $pricetype = 'regular',
        float $amount = 0,
        float $compareamount = 0,
        int $minquantity = 1,
        int $maxquantity = 0,
        int $startdate = 0,
        int $enddate = 0,
        bool $enabled = true
    ): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'productid' => $productid,
            'pricetype' => $pricetype,
            'amount' => $amount,
            'compareamount' => $compareamount,
            'minquantity' => $minquantity,
            'maxquantity' => $maxquantity,
            'startdate' => $startdate,
            'enddate' => $enddate,
            'enabled' => $enabled,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $params = self::normalise($params);
        $existing = null;
        if ($params['id'] > 0) {
            $existing = $DB->get_record('local_moderncommerce_product_prices', ['id' => $params['id']]);
            if (!$existing) {
                return self::failure(0, get_string('pricenotfound', 'local_moderncommerce'));
            }
            $params['productid'] = (int) $existing->productid;
        }

        $validation = self::validate_business_rules($params);
        if ($validation !== '') {
            return self::failure(0, $validation);
        }

        $now = time();
        $record = (object) [
            'productid' => $params['productid'],
            'pricetype' => $params['pricetype'],
            'amount' => $params['amount'],
            'compareamount' => $params['compareamount'] > 0 ? $params['compareamount'] : null,
            'minquantity' => $params['minquantity'],
            'maxquantity' => $params['maxquantity'] > 0 ? $params['maxquantity'] : null,
            'startdate' => $params['startdate'] > 0 ? $params['startdate'] : null,
            'enddate' => $params['enddate'] > 0 ? $params['enddate'] : null,
            'enabled' => $params['enabled'] ? 1 : 0,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_moderncommerce_product_prices', $record);
            $priceid = (int) $existing->id;
            $message = get_string('priceupdated', 'local_moderncommerce');
        } else {
            $record->timecreated = $now;
            $priceid = (int) $DB->insert_record('local_moderncommerce_product_prices', $record);
            $message = get_string('pricecreated', 'local_moderncommerce');
        }

        \local_moderncommerce\audit\audit_service::record(
            $existing ? 'price_updated' : 'price_created',
            'price',
            $priceid,
            [
                'olddata' => $existing ?: null,
                'newdata' => $record,
                'severity' => 'warning',
            ]
        );

        return [
            'success' => true,
            'priceid' => $priceid,
            'productid' => $params['productid'],
            'message' => $message,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether save succeeded.'),
            'priceid' => new external_value(PARAM_INT, 'Price row ID.'),
            'productid' => new external_value(PARAM_INT, 'Product ID.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
        ]);
    }

    /**
     * Normalise submitted values.
     *
     * @param array $params Raw params.
     * @return array Normalised params.
     */
    private static function normalise(array $params): array {
        $params['id'] = max(0, (int) $params['id']);
        $params['productid'] = max(0, (int) $params['productid']);
        $params['pricetype'] = self::normalise_price_type((string) $params['pricetype']);
        $params['amount'] = max(0, (float) $params['amount']);
        $params['compareamount'] = max(0, (float) $params['compareamount']);
        $params['minquantity'] = max(1, (int) $params['minquantity']);
        $params['maxquantity'] = max(0, (int) $params['maxquantity']);
        $params['startdate'] = max(0, (int) $params['startdate']);
        $params['enddate'] = max(0, (int) $params['enddate']);

        if ($params['compareamount'] <= $params['amount']) {
            $params['compareamount'] = 0;
        }

        return $params;
    }

    /**
     * Validate rules that need database state.
     *
     * @param array $params Normalised params.
     * @return string Empty when valid, otherwise message.
     */
    private static function validate_business_rules(array $params): string {
        global $DB;

        if (!$DB->record_exists('local_moderncommerce_products', ['id' => $params['productid']])) {
            return get_string('productnotfound', 'local_moderncommerce');
        }

        if ($params['maxquantity'] > 0 && $params['maxquantity'] < $params['minquantity']) {
            return get_string('invalidquantityrange', 'local_moderncommerce');
        }

        if ($params['startdate'] > 0 && $params['enddate'] > 0 && $params['enddate'] < $params['startdate']) {
            return get_string('invaliddatewindow', 'local_moderncommerce');
        }

        return '';
    }

    /**
     * Return a failure payload.
     *
     * @param int $priceid Price row ID.
     * @param string $message Message.
     * @return array
     */
    private static function failure(int $priceid, string $message): array {
        return [
            'success' => false,
            'priceid' => $priceid,
            'productid' => 0,
            'message' => $message,
        ];
    }

    /**
     * Normalise price type.
     *
     * @param string $pricetype Submitted type.
     * @return string Safe type.
     */
    private static function normalise_price_type(string $pricetype): string {
        $allowed = ['regular', 'sale', 'tier', 'subscription'];

        return in_array($pricetype, $allowed, true) ? $pricetype : 'regular';
    }
}
