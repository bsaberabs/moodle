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

namespace local_moderncommerce\notifications;

use local_moderncommerce\notifications\local\category_registry;
use local_moderncommerce\notifications\local\channel\channel_manager;
use local_moderncommerce\notifications\local\queue_manager;
use local_moderncommerce\notifications\local\suppression;

/**
 * Resolves a notification into queued deliveries (recipient x channel).
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dispatcher {
    /**
     * Dispatch a notification: resolve recipients/channels, suppress, dedupe, enqueue.
     *
     * @param notification $n The notification.
     * @return array{queued:int, skipped:int}
     */
    public static function dispatch(notification $n): array {
        $settings = category_registry::settings($n->category);
        $priority = $n->priority ?: $settings['priority'];
        $channels = $n->channels ?: $settings['channels'];
        $now = time();

        // Operational events also fan out to enabled ops endpoint channels (Slack/Teams).
        if ($n->category === 'operational') {
            foreach (['slack', 'teams'] as $endpoint) {
                $channel = channel_manager::get($endpoint);
                if ($channel && $channel->is_enabled() && !in_array($endpoint, $channels, true)) {
                    $channels[] = $endpoint;
                }
            }
        }

        // Split person-addressed channels from endpoint (webhook) channels.
        $personchannels = [];
        $endpointchannels = [];
        foreach ($channels as $ch) {
            $channel = channel_manager::get($ch);
            if (!$channel) {
                continue;
            }
            if ($channel->is_endpoint()) {
                $endpointchannels[] = $ch;
            } else {
                $personchannels[] = $ch;
            }
        }

        $queued = 0;
        $skipped = 0;

        // Person-addressed deliveries (one row per recipient x channel).
        $recipients = self::resolve_recipients($n);
        foreach ($recipients as $rec) {
            $placeholders = self::placeholders_for($n, $rec);

            // Marketing emails must carry a working one-click unsubscribe (legal requirement).
            if ($n->category === 'marketing' && $rec) {
                $placeholders['unsubscribe_url'] = suppression::unsubscribe_url((int) $rec->id, (string) ($rec->email ?? ''));
            }

            foreach ($personchannels as $ch) {
                if ($settings['suppressible'] && $rec && suppression::is_suppressed($rec, $n->category, $n->eventkey)) {
                    self::log_suppressed($n, $rec, $ch);
                    $skipped++;
                    continue;
                }

                $id = queue_manager::enqueue([
                    'component' => $n->component,
                    'eventkey' => $n->eventkey,
                    'category' => $n->category,
                    'priority' => $priority,
                    'templatekey' => $n->templatekey,
                    'placeholders' => json_encode($placeholders),
                    'recipientuserid' => $rec ? (int) $rec->id : 0,
                    'recipientemail' => $rec->email ?? null,
                    'channel' => $ch,
                    'subject' => $n->subject,
                    'body' => $n->summary,
                    'status' => 'pending',
                    'dedupekey' => self::dedupe_key($n, $rec, $ch),
                    'scheduledtime' => $now,
                    'contexturl' => $n->contexturl,
                    'relatedid' => $n->relatedid,
                ]);

                $id ? $queued++ : $skipped++;
            }
        }

        // Endpoint deliveries (one post per channel, no recipient).
        foreach ($endpointchannels as $ch) {
            $id = queue_manager::enqueue([
                'component' => $n->component,
                'eventkey' => $n->eventkey,
                'category' => $n->category,
                'priority' => $priority,
                'templatekey' => $n->templatekey,
                'placeholders' => json_encode($n->placeholders),
                'recipientuserid' => 0,
                'recipientemail' => null,
                'channel' => $ch,
                'endpointref' => $ch,
                'subject' => $n->subject,
                'body' => $n->summary,
                'status' => 'pending',
                'dedupekey' => self::dedupe_key($n, null, $ch),
                'scheduledtime' => $now,
                'contexturl' => $n->contexturl,
                'relatedid' => $n->relatedid,
            ]);

            $id ? $queued++ : $skipped++;
        }

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    /**
     * Resolve the recipient user records.
     *
     * @param notification $n Notification.
     * @return \stdClass[] Recipient user records.
     */
    protected static function resolve_recipients(notification $n): array {
        $recipients = [];

        if ($n->toadmins) {
            $context = \context_system::instance();
            $admins = get_users_by_capability($context, 'local/moderncommerce:receivenotificationops', 'u.*');
            if (empty($admins)) {
                $admins = get_admins();
            }
            foreach ($admins as $a) {
                $recipients[$a->id] = $a;
            }
        }

        foreach ($n->touserids as $uid) {
            if (isset($recipients[$uid])) {
                continue;
            }
            $user = \core_user::get_user($uid);
            if ($user) {
                $recipients[$uid] = $user;
            }
        }

        return array_values($recipients);
    }

    /**
     * Merge recipient identity tokens into the placeholder set.
     *
     * Critical for cron: the email engine's globals read $USER (the cron user), so the
     * recipient's own name/email must be supplied per-recipient here.
     *
     * @param notification $n Notification.
     * @param \stdClass|null $rec Recipient.
     * @return array
     */
    protected static function placeholders_for(notification $n, ?\stdClass $rec): array {
        $ph = $n->placeholders;
        if ($rec) {
            $ph += [
                'firstname' => $rec->firstname ?? '',
                'lastname' => $rec->lastname ?? '',
                'fullname' => fullname($rec),
                'email' => $rec->email ?? '',
            ];
        }
        return $ph;
    }

    /**
     * Stable dedupe key. Includes a discriminator (caller tag or daily bucket) so
     * legitimate re-sends across days are not blocked, but same-day duplicates are.
     *
     * @param notification $n Notification.
     * @param \stdClass|null $rec Recipient.
     * @param string $channel Channel key.
     * @return string sha1 hash.
     */
    protected static function dedupe_key(notification $n, ?\stdClass $rec, string $channel): string {
        $tag = $n->deduptag ?: date('Ymd');
        return sha1(implode('|', [
            $n->component,
            $n->eventkey,
            (int) $n->relatedid,
            $rec ? (int) $rec->id : 0,
            $channel,
            $tag,
        ]));
    }

    /**
     * Record a suppressed (opted-out) marketing send.
     *
     * @param notification $n Notification.
     * @param \stdClass $rec Recipient.
     * @param string $channel Channel.
     * @return void
     */
    protected static function log_suppressed(notification $n, \stdClass $rec, string $channel): void {
        queue_manager::log((object) [
            'id' => null,
            'component' => $n->component,
            'eventkey' => $n->eventkey,
            'category' => $n->category,
            'recipientuserid' => (int) $rec->id,
            'recipientemail' => $rec->email ?? null,
            'channel' => $channel,
            'subject' => null,
            'body' => null,
        ], 'suppressed', 'Recipient opted out of marketing');
    }
}
