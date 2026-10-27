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
 * Structured commerce audit logging service.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\audit;

use context_system;

/**
 * Writes Modern Commerce audit records and mirrors them into Moodle's log API.
 */
class audit_service {
    /** @var string Audit table name. */
    private const TABLE = 'local_moderncommerce_audit_log';

    /** @var string[] Keys that must never be written in clear text. */
    private const SENSITIVE_KEYS = [
        'authorization',
        'bearer',
        'card',
        'clientsecret',
        'cvv',
        'password',
        'privatekey',
        'secret',
        'secret_hash',
        'secretkey',
        'secret_key',
        'slack_secret',
        'teams_secret',
        'token',
        'webhooksecret',
        'webhook_secret',
    ];

    /**
     * Write an audit record.
     *
     * Supported options:
     * - actoruserid: user performing the action, defaults to current user.
     * - subjectuserid: user affected by the action.
     * - olddata/newdata: before/after snapshots.
     * - result: success, failed, warning, etc.
     * - severity: info, warning, critical.
     * - source: web, cli, task, webhook, ajax.
     * - correlationid: request/payment/webhook/order correlation key.
     * - context: Moodle context for the mirrored event.
     * - trigger: false to skip the Moodle event mirror.
     *
     * @param string $action Action name.
     * @param string $entitytype Entity type.
     * @param int $entityid Entity ID.
     * @param array $options Audit options.
     * @return int Audit row ID, or 0 when the audit table does not exist.
     */
    public static function record(string $action, string $entitytype, int $entityid = 0, array $options = []): int {
        global $DB, $USER;

        if (!$DB->get_manager()->table_exists(self::TABLE)) {
            return 0;
        }

        $redacted = false;
        $oldjson = self::encode_payload($options['olddata'] ?? null, $redacted);
        $newjson = self::encode_payload($options['newdata'] ?? null, $redacted);
        $time = time();
        $actoruserid = array_key_exists('actoruserid', $options)
            ? self::nullable_int($options['actoruserid'])
            : self::nullable_int($USER->id ?? null);
        $subjectuserid = self::nullable_int($options['subjectuserid'] ?? null);
        $correlationid = self::clean_text($options['correlationid'] ?? null, 100);
        $source = self::clean_token($options['source'] ?? self::default_source(), 30, 'system');
        $result = self::clean_token($options['result'] ?? 'success', 20, 'success');
        $severity = self::clean_token($options['severity'] ?? 'info', 20, 'info');
        $previoushash = self::get_previous_audit_hash();
        $eventuuid = self::generate_uuid();

        $hashsource = implode('|', [
            $previoushash ?? '',
            $eventuuid,
            $correlationid ?? '',
            (string) ($actoruserid ?? ''),
            (string) ($subjectuserid ?? ''),
            $action,
            $entitytype,
            (string) $entityid,
            $source,
            $result,
            $severity,
            (string) $oldjson,
            (string) $newjson,
            (string) $time,
        ]);

        $audit = (object) [
            'eventuuid' => $eventuuid,
            'correlationid' => $correlationid,
            'actoruserid' => $actoruserid,
            'subjectuserid' => $subjectuserid,
            'action' => self::clean_token($action, 100, 'unknown'),
            'entitytype' => self::clean_token($entitytype, 50, 'unknown'),
            'entityid' => $entityid,
            'source' => $source,
            'result' => $result,
            'severity' => $severity,
            'ipaddress' => getremoteaddr(),
            'useragent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'olddata' => $oldjson,
            'newdata' => $newjson,
            'oldhash' => $oldjson === null ? null : hash('sha256', $oldjson),
            'newhash' => $newjson === null ? null : hash('sha256', $newjson),
            'previoushash' => $previoushash,
            'eventhash' => hash('sha256', $hashsource),
            'redacted' => $redacted ? 1 : 0,
            'timecreated' => $time,
        ];

        $auditid = (int) $DB->insert_record(self::TABLE, self::filter_record_fields(self::TABLE, $audit));

        if (($options['trigger'] ?? true) !== false) {
            try {
                self::trigger_moodle_event($auditid, $audit, $options['context'] ?? null);
            } catch (\Throwable $e) {
                debugging('Modern Commerce audit event trigger failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        return $auditid;
    }

    /**
     * Return a redacted value suitable for audit snapshots.
     *
     * @param mixed $value Raw value.
     * @return mixed Redacted value.
     */
    public static function snapshot($value) {
        $redacted = false;
        return self::redact_value($value, $redacted);
    }

    /**
     * Encode a payload after recursive redaction.
     *
     * @param mixed $payload Payload.
     * @param bool $redacted Whether any field was redacted.
     * @return string|null Encoded payload.
     */
    private static function encode_payload($payload, bool &$redacted): ?string {
        if ($payload === null) {
            return null;
        }

        $clean = self::redact_value($payload, $redacted);
        $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? null : $json;
    }

    /**
     * Recursively redact sensitive payload fields.
     *
     * @param mixed $value Value.
     * @param bool $redacted Whether any field was redacted.
     * @return mixed Redacted value.
     */
    private static function redact_value($value, bool &$redacted) {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            $stringkey = strtolower((string) $key);
            if (self::is_sensitive_key($stringkey)) {
                $clean[$key] = '[redacted]';
                $redacted = true;
                continue;
            }
            $clean[$key] = self::redact_value($item, $redacted);
        }

        return $clean;
    }

    /**
     * Check whether a key should be redacted.
     *
     * @param string $key Key.
     * @return bool
     */
    private static function is_sensitive_key(string $key): bool {
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($key === $sensitive || strpos($key, $sensitive) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trigger the Moodle log event mirror.
     *
     * @param int $auditid Audit ID.
     * @param object $audit Audit record.
     * @param mixed $context Context override.
     */
    private static function trigger_moodle_event(int $auditid, object $audit, $context = null): void {
        $eventdata = [
            'context' => $context ?: context_system::instance(),
            'objectid' => $auditid,
            'other' => [
                'action' => $audit->action,
                'entitytype' => $audit->entitytype,
                'entityid' => (int) $audit->entityid,
                'result' => $audit->result,
                'severity' => $audit->severity,
                'source' => $audit->source,
                'correlationid' => $audit->correlationid ?? '',
            ],
        ];
        if (!empty($audit->actoruserid)) {
            $eventdata['userid'] = (int) $audit->actoruserid;
        }
        if (!empty($audit->subjectuserid)) {
            $eventdata['relateduserid'] = (int) $audit->subjectuserid;
        }

        $event = \local_moderncommerce\event\audit_event::create($eventdata);
        $event->trigger();
    }

    /**
     * Get the previous audit row hash.
     *
     * @return string|null Hash.
     */
    private static function get_previous_audit_hash(): ?string {
        global $DB;

        $records = $DB->get_records(self::TABLE, null, 'id DESC', 'id, eventhash', 0, 1);
        $record = $records ? reset($records) : null;

        return $record && !empty($record->eventhash) ? (string) $record->eventhash : null;
    }

    /**
     * Generate a UUID v4-like identifier.
     *
     * @return string UUID.
     */
    private static function generate_uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Default source for the current execution mode.
     *
     * @return string Source.
     */
    private static function default_source(): string {
        return defined('CLI_SCRIPT') && CLI_SCRIPT ? 'cli' : 'web';
    }

    /**
     * Normalise a nullable int.
     *
     * @param mixed $value Value.
     * @return int|null Normalised value.
     */
    private static function nullable_int($value): ?int {
        $value = (int) ($value ?? 0);
        return $value > 0 ? $value : null;
    }

    /**
     * Clean a short token field.
     *
     * @param mixed $value Raw value.
     * @param int $maxlength Max length.
     * @param string $fallback Fallback.
     * @return string Clean token.
     */
    private static function clean_token($value, int $maxlength, string $fallback): string {
        $value = strtolower(trim((string) ($value ?? '')));
        $value = preg_replace('/[^a-z0-9_:-]+/', '_', $value) ?: '';
        $value = trim($value, '_');
        if ($value === '') {
            $value = $fallback;
        }

        return substr($value, 0, $maxlength);
    }

    /**
     * Clean a short text field.
     *
     * @param mixed $value Raw value.
     * @param int $maxlength Max length.
     * @return string|null Clean text.
     */
    private static function clean_text($value, int $maxlength): ?string {
        $value = trim(strip_tags((string) ($value ?? '')));
        return $value === '' ? null : substr($value, 0, $maxlength);
    }

    /**
     * Keep inserts compatible with older upgraded tables.
     *
     * @param string $table Table name.
     * @param object $record Record.
     * @return object Filtered record.
     */
    private static function filter_record_fields(string $table, object $record): object {
        global $DB;

        $columns = $DB->get_columns($table);
        $filtered = new \stdClass();
        foreach ((array) $record as $field => $value) {
            if (isset($columns[$field])) {
                $filtered->{$field} = $value;
            }
        }

        return $filtered;
    }
}
