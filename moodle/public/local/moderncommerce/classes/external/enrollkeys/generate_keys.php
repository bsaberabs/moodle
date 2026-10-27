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
 * External API generating enrollment keys for a target.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\enrollkeys;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\services\pricing_service;

/**
 * Generate one or more enrollment keys targeting a course or product.
 */
class generate_keys extends external_api {
    /** @var int Maximum keys generated per request. */
    private const MAX_QUANTITY = 500;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'targettype' => new external_value(PARAM_ALPHA, 'Target type: course or product.'),
            'targetid' => new external_value(PARAM_INT, 'Target course or product ID.'),
            'quantity' => new external_value(PARAM_INT, 'Number of keys to generate.', VALUE_DEFAULT, 1),
            'keytype' => new external_value(PARAM_ALPHA, 'Key type: single or multi.', VALUE_DEFAULT, 'single'),
            'maxuses' => new external_value(PARAM_INT, 'Max uses per key (0 = unlimited).', VALUE_DEFAULT, 1),
            'validdays' => new external_value(PARAM_INT, 'Days until expiry (0 = no expiry).', VALUE_DEFAULT, 90),
        ]);
    }

    /**
     * Execute.
     *
     * @param string $targettype Target type.
     * @param int $targetid Target ID.
     * @param int $quantity Quantity.
     * @param string $keytype Key type.
     * @param int $maxuses Max uses.
     * @param int $validdays Valid days.
     * @return array
     */
    public static function execute(
        string $targettype,
        int $targetid,
        int $quantity = 1,
        string $keytype = 'single',
        int $maxuses = 1,
        int $validdays = 90
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'targettype' => $targettype,
            'targetid' => $targetid,
            'quantity' => $quantity,
            'keytype' => $keytype,
            'maxuses' => $maxuses,
            'validdays' => $validdays,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:generatekeys', $context);

        $targettype = $params['targettype'] === 'product' ? 'product' : 'course';
        $targetid = (int) $params['targetid'];
        if (!self::target_exists($targettype, $targetid)) {
            return self::failure(get_string('invalidkeytarget', 'local_moderncommerce'));
        }

        $quantity = max(1, min(self::MAX_QUANTITY, (int) $params['quantity']));
        $keytype = $params['keytype'] === 'multi' ? 'multi' : 'single';
        $maxuses = $keytype === 'single' ? 1 : max(0, (int) $params['maxuses']);
        $validdays = max(0, (int) $params['validdays']);
        $currency = pricing_service::get_currency_config()->currency;
        $now = time();
        $expirydate = $validdays > 0 ? $now + ($validdays * DAYSECS) : 0;

        $transaction = $DB->start_delegated_transaction();
        $generated = [];
        $keyids = [];

        try {
            for ($i = 0; $i < $quantity; $i++) {
                $keycode = 'KEY-' . strtoupper(substr(md5(uniqid((string) random_int(0, PHP_INT_MAX), true)), 0, 12));

                $keyid = (int) $DB->insert_record('local_moderncommerce_enrollkeys', (object) [
                    'keycode' => $keycode,
                    'keytype' => $keytype,
                    'value' => 0,
                    'currency' => $currency,
                    'remainingvalue' => 0,
                    'status' => 'active',
                    'maxuses' => $maxuses,
                    'usedcount' => 0,
                    'maxusesperuser' => 1,
                    'startdate' => $now,
                    'expirydate' => $expirydate,
                    'createdby' => $USER->id,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $keyids[] = $keyid;

                $DB->insert_record('local_moderncommerce_enrollkey_targets', (object) [
                    'enrollkeyid' => $keyid,
                    'targettype' => $targettype,
                    'targetid' => $targetid,
                    'targetvalue' => null,
                    'includemode' => 'include',
                    'timecreated' => $now,
                ]);

                $generated[] = $keycode;
            }

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return self::failure($e->getMessage());
        }

        \local_moderncommerce\audit\audit_service::record('enrollment_keys_generated', 'enrollment_key_batch', 0, [
            'newdata' => [
                'targettype' => $targettype,
                'targetid' => $targetid,
                'quantity' => count($generated),
                'keytype' => $keytype,
                'maxuses' => $maxuses,
                'validdays' => $validdays,
                'keyids' => $keyids,
            ],
            'severity' => 'warning',
        ]);

        self::notify_keys_generated($targettype, $targetid, count($generated));

        return [
            'success' => true,
            'generated' => count($generated),
            'keycodes' => $generated,
            'message' => get_string('keysgeneratedsuccess', 'local_moderncommerce', count($generated)),
            'warnings' => [],
        ];
    }

    /**
     * Notify the generating user that their key batch is ready, through the hub.
     *
     * @param string $targettype Target type (course|product).
     * @param int $targetid Target id.
     * @param int $count Number of keys generated.
     * @return void
     */
    private static function notify_keys_generated(string $targettype, int $targetid, int $count): void {
        global $USER, $CFG, $DB;

        if ($count <= 0) {
            return;
        }

        if ($targettype === 'course') {
            $targetname = (string) $DB->get_field('course', 'fullname', ['id' => $targetid]);
        } else {
            $targetname = (string) $DB->get_field('local_moderncommerce_products', 'name', ['id' => $targetid]);
        }
        $dashurl = $CFG->wwwroot . '/local/moderncommerce/index.php';

        $notification = (new \local_moderncommerce\notifications\notification('local_moderncommerce', 'keys_generated'))
            ->category('transactional')
            ->template('moderncommerce_keys_generated')
            ->to_user((int) $USER->id)
            ->placeholders([
                'key_count' => $count,
                'key_target_name' => $targetname,
                'keys_csv_url' => $dashurl,
                'manager_dashboard_url' => $dashurl,
            ])
            ->context_url($dashurl)
            ->related($targetid);

        \local_moderncommerce\notifications\api::notify($notification);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether keys were generated.'),
            'generated' => new external_value(PARAM_INT, 'Number of keys generated.'),
            'keycodes' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Generated key code.')),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Check the target exists.
     *
     * @param string $targettype Target type.
     * @param int $targetid Target ID.
     * @return bool
     */
    private static function target_exists(string $targettype, int $targetid): bool {
        global $DB;

        if ($targettype === 'course') {
            return $targetid > 1 && $DB->record_exists('course', ['id' => $targetid]);
        }

        return $DB->record_exists('local_moderncommerce_products', ['id' => $targetid]);
    }

    /**
     * Build a failure response.
     *
     * @param string $message Message.
     * @return array
     */
    private static function failure(string $message): array {
        return [
            'success' => false,
            'generated' => 0,
            'keycodes' => [],
            'message' => $message,
            'warnings' => [],
        ];
    }
}
