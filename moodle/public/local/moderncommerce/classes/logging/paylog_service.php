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

namespace local_moderncommerce\logging;


/**
 * Writes redacted payment diagnostics.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class paylog_service {
    /** @var string Diagnostic payment log table. */
    private const TABLE_PAYMENT_LOG = 'local_moderncommerce_payment_log';

    /** @var array Sensitive payload keys that must not be stored. */
    private const SENSITIVE_KEYS = [
        'authorization',
        'authorization_code',
        'authorizationcode',
        'card',
        'card_number',
        'cardnumber',
        'client_secret',
        'cvv',
        'cvc',
        'password',
        'secret',
        'secret_hash',
        'secret_key',
        'secretkey',
        'signature',
        'token',
        'access_token',
        'accesstoken',
        'clientsecret',
        'secrethash',
    ];

    /**
     * Logs a payment gateway action without storing raw secrets.
     *
     * @param int|null $orderid Order ID.
     * @param string $gateway Gateway name.
     * @param string $action Action name.
     * @param string|null $reference Gateway reference.
     * @param mixed $response Gateway response payload.
     */
    public static function log(?int $orderid, string $gateway, string $action, ?string $reference, $response): void {
        global $DB;

        if (!self::table_exists(self::TABLE_PAYMENT_LOG)) {
            return;
        }

        $payload = self::encode_payload($response);
        $decoded = json_decode($payload, true);

        $record = new \stdClass();
        $record->orderid = $orderid;
        $record->gateway = self::normalise_gateway($gateway);
        $record->action = substr($action, 0, 50);
        $record->reference = $reference;
        $record->eventid = self::extract_first($decoded, ['id', 'event_id', 'eventid', 'gatewayeventid']);
        $record->correlationid = self::extract_first($decoded, [
            'correlation_id',
            'correlationid',
            'request_id',
            'requestid',
        ]);
        $record->result = self::resolve_result($decoded);
        $record->payloadhash = hash('sha256', (string)$payload);
        $record->redacted = 1;
        $record->response = self::redact_payload($payload);
        $record->timecreated = time();

        try {
            $DB->insert_record(self::TABLE_PAYMENT_LOG, self::filter_record_fields(self::TABLE_PAYMENT_LOG, $record));
        } catch (\dml_exception $e) {
            // Swallow logging errors to avoid breaking payment flow.
            debugging('Failed to write ModernCommerce payment log: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Redacts sensitive payment values before storing diagnostics.
     *
     * @param string $payload Raw payload.
     * @return string Redacted payload.
     */
    private static function redact_payload(string $payload): string {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return self::redact_string($payload);
        }

        $redacted = self::redact_array($decoded);
        return self::encode_payload($redacted);
    }

    /**
     * Redact common sensitive key/value pairs from non-JSON payloads.
     *
     * @param string $payload Payload string.
     * @return string Redacted payload.
     */
    private static function redact_string(string $payload): string {
        return preg_replace(
            '/((?:access_token|authorization|card_number|cardnumber|client_secret|clientsecret|password|secret|'
                . 'secret_hash|secret_key|token)=)[^&\\s]+/i',
            '$1[redacted]',
            $payload
        );
    }

    /**
     * Recursively redact sensitive keys from an array payload.
     *
     * @param array $payload Payload data.
     * @return array Redacted payload.
     */
    private static function redact_array(array $payload): array {
        foreach ($payload as $key => $value) {
            $normalisedkey = strtolower(str_replace(['-', ' '], '_', (string)$key));
            if (in_array($normalisedkey, self::SENSITIVE_KEYS, true)) {
                $payload[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::redact_array($value);
            }
        }

        return $payload;
    }

    /**
     * Encode arbitrary payload data for storage.
     *
     * @param mixed $payload Payload data.
     * @return string Encoded payload.
     */
    private static function encode_payload($payload): string {
        if (is_string($payload)) {
            return $payload;
        }

        $encoded = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            return '';
        }

        return $encoded;
    }

    /**
     * Extract the first matching scalar value from a decoded payload.
     *
     * @param mixed $payload Decoded payload.
     * @param array $keys Candidate keys.
     * @return string|null Matching value.
     */
    private static function extract_first($payload, array $keys): ?string {
        if (!is_array($payload)) {
            return null;
        }

        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key]) && $payload[$key] !== '') {
                return substr((string)$payload[$key], 0, 100);
            }
        }

        foreach ($payload as $value) {
            if (is_array($value)) {
                $nested = self::extract_first($value, $keys);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    /**
     * Resolve a compact diagnostic result from a gateway response.
     *
     * @param mixed $payload Decoded payload.
     * @return string
     */
    private static function resolve_result($payload): string {
        if (!is_array($payload)) {
            return 'received';
        }

        foreach (['status', 'result', 'payment_status'] as $key) {
            if (!isset($payload[$key]) || !is_scalar($payload[$key])) {
                continue;
            }

            $value = strtolower((string)$payload[$key]);
            if (in_array($value, ['success', 'successful', 'succeeded', 'paid', 'completed'], true)) {
                return 'success';
            }
            if (in_array($value, ['failed', 'failure', 'declined', 'denied', 'error'], true)) {
                return 'failed';
            }
            if (in_array($value, ['cancelled', 'canceled'], true)) {
                return 'cancelled';
            }
            if ($value !== '') {
                return substr($value, 0, 20);
            }
        }

        if (isset($payload['success'])) {
            return !empty($payload['success']) ? 'success' : 'failed';
        }

        return 'received';
    }

    /**
     * Normalise gateway identifier for logging.
     *
     * @param string $gateway Gateway name.
     * @return string Normalised gateway name.
     */
    private static function normalise_gateway(string $gateway): string {
        return substr(preg_replace('/[^a-z0-9_\\-]/', '', strtolower(trim($gateway))), 0, 50);
    }

    /**
     * Check whether a table exists.
     *
     * @param string $tablename Table name.
     * @return bool
     */
    private static function table_exists(string $tablename): bool {
        global $DB;

        return $DB->get_manager()->table_exists(new \xmldb_table($tablename));
    }

    /**
     * Remove object properties that are not columns on the target table.
     *
     * @param string $tablename Table name.
     * @param object $record Record object.
     * @return object
     */
    private static function filter_record_fields(string $tablename, object $record): object {
        global $DB;

        $columns = $DB->get_columns($tablename);
        $filtered = new \stdClass();

        foreach (get_object_vars($record) as $field => $value) {
            if (isset($columns[$field])) {
                $filtered->{$field} = $value;
            }
        }

        return $filtered;
    }
}
