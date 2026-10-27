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

namespace local_moderncommerce\notifications\local;

use local_moderncommerce\notifications\local\channel\channel_manager;
use local_moderncommerce\email\notification_catalog;
use local_moderncommerce\email\renderer;

/**
 * The delivery queue: enqueue, claim, send, retry-with-backoff, and audit.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_manager {
    /** @var string Queue table. */
    private const QUEUE = 'local_moderncommerce_notify_queue';

    /** @var string Log table. */
    private const LOG = 'local_moderncommerce_notify_log';

    /** @var int Return processing rows older than this (seconds) to pending. */
    private const STALE_AFTER = 1800;

    /**
     * Enqueue one delivery row. Duplicate dedupekeys are silently dropped.
     *
     * @param array $data Row fields.
     * @return int New row id, or 0 if a duplicate / insert failed.
     */
    public static function enqueue(array $data): int {
        global $DB;

        $now = time();
        $record = (object) array_merge([
            'priority' => 'normal',
            'recipientuserid' => 0,
            'bodyformat' => FORMAT_HTML,
            'status' => 'pending',
            'attempts' => 0,
            'maxattempts' => 5,
            'scheduledtime' => $now,
            'nextattempttime' => 0,
            'senttime' => 0,
            'relatedid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $data);

        try {
            return (int) $DB->insert_record(self::QUEUE, $record);
        } catch (\Throwable $e) {
            // Unique dedupekey violation (already queued) or write error — treat as not-queued.
            return 0;
        }
    }

    /**
     * Process due deliveries.
     *
     * @param int $limit Max rows this run (0 = use config batchsize).
     * @return array{sent:int, failed:int, skipped:int}
     */
    public static function process_pending(int $limit = 0): array {
        global $DB;

        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $now = time();
        if ($limit <= 0) {
            $limit = (int) (get_config('local_moderncommerce', 'notify_batchsize') ?: 100);
        }

        $sql = "SELECT *
                  FROM {" . self::QUEUE . "}
                 WHERE status = :pending
                   AND scheduledtime <= :now1
                   AND nextattempttime <= :now2
              ORDER BY CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END,
                       scheduledtime ASC, id ASC";
        $rows = $DB->get_records_sql($sql, ['pending' => 'pending', 'now1' => $now, 'now2' => $now], 0, $limit);

        foreach ($rows as $row) {
            $claimed = self::claim($row);
            if (!$claimed) {
                continue;
            }
            $result = self::send_row($claimed);
            $stats[$result] = ($stats[$result] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Atomically claim a pending row (pending -> processing) to prevent double-send.
     *
     * @param \stdClass $row Candidate row.
     * @return \stdClass|null The claimed row, or null if another worker took it.
     */
    protected static function claim(\stdClass $row): ?\stdClass {
        global $DB;

        $current = $DB->get_record(self::QUEUE, ['id' => $row->id]);
        if (!$current || $current->status !== 'pending') {
            return null;
        }
        $current->status = 'processing';
        $current->timemodified = time();
        $DB->update_record(self::QUEUE, $current);

        return $current;
    }

    /**
     * Render and deliver one claimed row.
     *
     * @param \stdClass $row Claimed row.
     * @return string One of 'sent', 'failed', 'skipped'.
     */
    protected static function send_row(\stdClass $row): string {
        global $DB;

        if (
            (string) ($row->channel ?? '') === 'email'
            && !empty($row->templatekey)
            && !notification_catalog::is_enabled((string) $row->templatekey)
        ) {
            return self::finish($row, 'cancelled', 'skipped', 'Email disabled: ' . $row->templatekey);
        }

        [$subject, $plain, $body] = self::render($row);
        $row->subject = $subject;
        $row->body = $body;

        $channel = channel_manager::get($row->channel);
        if (!$channel || !$channel->is_enabled()) {
            return self::finish($row, 'cancelled', 'skipped', 'Channel unavailable: ' . $row->channel);
        }

        $recipient = null;
        if (!empty($row->recipientuserid)) {
            $recipient = \core_user::get_user($row->recipientuserid);
        }
        if (!$channel->is_endpoint() && !self::deliverable($recipient)) {
            return self::finish($row, 'cancelled', 'skipped', 'Recipient not deliverable');
        }

        $from = \core_user::get_noreply_user();
        $context = [
            'provider' => category_registry::provider($row->category),
            'row' => $row,
        ];

        $error = null;
        try {
            $ok = $channel->send($recipient ?: $from, $from, $subject, $plain, $body, $context);
        } catch (\Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        }

        if (!empty($ok)) {
            return self::finish($row, 'sent', 'sent', null);
        }

        // Failure: back off and requeue, or give up after maxattempts.
        $attempts = (int) $row->attempts + 1;
        $error = $error ?? 'Delivery failed on channel ' . $row->channel;
        if ($attempts < (int) $row->maxattempts) {
            $backoff = min(DAYSECS, 60 * (2 ** max(0, $attempts - 1)));
            $update = (object) [
                'id' => $row->id,
                'status' => 'pending',
                'attempts' => $attempts,
                'nextattempttime' => time() + $backoff,
                'lasterror' => $error,
                'subject' => $subject,
                'body' => $body,
                'timemodified' => time(),
            ];
            $DB->update_record(self::QUEUE, $update);
            self::log($row, 'failed', $error);
            return 'failed';
        }

        return self::finish($row, 'failed', 'failed', $error);
    }

    /**
     * Persist a terminal outcome and write the audit log.
     *
     * @param \stdClass $row Row (with rendered subject/body).
     * @param string $status Queue status to set.
     * @param string $result Log result.
     * @param string|null $error Error message.
     * @return string The log result.
     */
    protected static function finish(\stdClass $row, string $status, string $result, ?string $error): string {
        global $DB;

        $update = (object) [
            'id' => $row->id,
            'status' => $status,
            'attempts' => (int) $row->attempts + 1,
            'lasterror' => $error,
            'subject' => $row->subject ?? null,
            'body' => $row->body ?? null,
            'timemodified' => time(),
        ];
        if ($status === 'sent') {
            $update->senttime = time();
        }
        $DB->update_record(self::QUEUE, $update);
        self::log($row, $result, $error);

        return $result;
    }

    /**
     * Render subject/plain/body for a row.
     *
     * Prefers the named email template rendered by Modern Commerce core;
     * falls back to the row's raw subject/body. Recipient-specific placeholders are
     * on the row, so name tokens resolve correctly even under cron.
     *
     * @param \stdClass $row Queue row.
     * @return array{0:string, 1:string, 2:string} [subject, plain, html]
     */
    protected static function render(\stdClass $row): array {
        $placeholders = !empty($row->placeholders) ? (json_decode($row->placeholders, true) ?: []) : [];
        $subject = (string) ($row->subject ?? '');
        $body = (string) ($row->body ?? '');
        $applyshell = (string) ($row->channel ?? '') === 'email';

        if (!empty($row->templatekey)) {
            try {
                $rendered = renderer::render_template((string) $row->templatekey, $placeholders, [
                    'applyshell' => $applyshell,
                ]);
                return [
                    $rendered['subject'],
                    $rendered['plain'],
                    $applyshell ? $rendered['html'] : $rendered['body'],
                ];
            } catch (\Throwable $e) {
                debugging('Modern Commerce notification template render failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $rendered = renderer::render_subject_body($subject, $body, $placeholders, [
            'applyshell' => $applyshell,
        ]);
        return [
            $rendered['subject'],
            $rendered['plain'],
            $applyshell ? $rendered['html'] : $rendered['body'],
        ];
    }

    /**
     * Whether a user can receive a person-addressed notification.
     *
     * @param \stdClass|false|null $user User record.
     * @return bool
     */
    protected static function deliverable($user): bool {
        return !empty($user)
            && empty($user->deleted)
            && empty($user->suspended)
            && !empty($user->email)
            && $user->id != \core_user::NOREPLY_USER;
    }

    /**
     * Write an audit-log row.
     *
     * @param \stdClass $row Queue row.
     * @param string $result sent|failed|skipped|suppressed.
     * @param string|null $error Error message.
     * @param int|null $httpcode Optional HTTP status (Slack/Teams).
     * @param string|null $externalid Optional external message id.
     * @return void
     */
    public static function log(
        \stdClass $row,
        string $result,
        ?string $error = null,
        ?int $httpcode = null,
        ?string $externalid = null
    ): void {
        global $DB;

        $DB->insert_record(self::LOG, (object) [
            'queueid' => $row->id ?? null,
            'component' => $row->component ?? '',
            'eventkey' => $row->eventkey ?? '',
            'category' => $row->category ?? '',
            'recipientuserid' => $row->recipientuserid ?? 0,
            'recipientemail' => $row->recipientemail ?? null,
            'channel' => $row->channel ?? '',
            'subject' => $row->subject ?? null,
            'body' => $row->body ?? null,
            'result' => $result,
            'error' => $error,
            'httpcode' => $httpcode,
            'externalid' => $externalid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Return rows stuck in 'processing' (crashed worker) back to 'pending'.
     *
     * @return int Number of rows recovered.
     */
    public static function reap_stale(): int {
        global $DB;

        $cutoff = time() - self::STALE_AFTER;
        $stuck = $DB->get_records_select(
            self::QUEUE,
            'status = :processing AND timemodified < :cutoff',
            ['processing' => 'processing', 'cutoff' => $cutoff],
            '',
            'id'
        );
        foreach ($stuck as $s) {
            $DB->update_record(self::QUEUE, (object) [
                'id' => $s->id,
                'status' => 'pending',
                'timemodified' => time(),
            ]);
        }

        return count($stuck);
    }
}
