<?php
// This file is part of Moodle - https://moodle.org/
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

namespace local_oriskill;

/**
 * Webhooks of a trainer: their sales events are posted as JSON to the addresses they choose (Zapier, Make, their
 * own tools), signed with HMAC-SHA256 so the receiver can check they come from Oriskill.
 *
 * Headers: X-Oriskill-Event (event name), X-Oriskill-Signature ("sha256=" + hex HMAC of the raw body with the
 * address's secret). Deliveries run in an ad hoc task and are retried when the receiver does not answer 2xx.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook_service {

    /** Table. */
    const TABLE = 'local_oriskill_webhooks';

    /** Events a webhook can receive. */
    const EVENTS = ['order.paid', 'instalment.paid', 'instalment.failed', 'instalments.stopped', 'refund.done',
        'access.suspended', 'lead.created'];

    /**
     * Webhooks of a trainer.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function hooks(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['creatorid' => $creator->id], 'timecreated ASC, id ASC');
    }

    /**
     * Add a webhook; its secret is generated here.
     *
     * @param \stdClass $creator Trainer.
     * @param string $url https address.
     * @param string[] $events Events it receives.
     * @return \stdClass
     */
    public static function add(\stdClass $creator, string $url, array $events): \stdClass {
        global $DB;

        $url = trim($url);
        $events = array_values(array_intersect(self::EVENTS, $events));
        if (!preg_match('~^https://[^\s/$.?#][^\s]*$~i', $url) || !$events) {
            throw new \moodle_exception('error:webhook', 'local_oriskill');
        }
        $record = (object) ['creatorid' => $creator->id, 'url' => $url, 'secret' => \core\encryption::encrypt(random_string(40)),
            'events' => implode(',', $events), 'enabled' => 1, 'timecreated' => time()];
        $record->id = $DB->insert_record(self::TABLE, $record);
        return $record;
    }

    /**
     * Remove a webhook of the trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Webhook.
     */
    public static function remove(\stdClass $creator, int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id, 'creatorid' => $creator->id]);
    }

    /**
     * Signing secret of a webhook, shown to the trainer to set up the receiver.
     *
     * @param \stdClass $hook Webhook.
     * @return string
     */
    public static function secret(\stdClass $hook): string {
        return \core\encryption::decrypt($hook->secret);
    }

    /**
     * Queue an event for every webhook of the trainer that receives it.
     *
     * @param \stdClass $creator Trainer.
     * @param string $event Event name.
     * @param array $data Event data.
     */
    public static function dispatch(\stdClass $creator, string $event, array $data): void {
        foreach (self::hooks($creator) as $hook) {
            if ($hook->enabled && in_array($event, explode(',', $hook->events), true)) {
                self::queue($hook, $event, $data);
            }
        }
    }

    /**
     * Queue one delivery.
     *
     * @param \stdClass $hook Webhook.
     * @param string $event Event name.
     * @param array $data Event data.
     */
    public static function queue(\stdClass $hook, string $event, array $data): void {
        $task = new task\webhook_deliver();
        $task->set_custom_data(['hookid' => (int) $hook->id, 'body' => json_encode(['event' => $event, 'time' => time(),
            'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'event' => $event]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Post a body to a webhook.
     *
     * @param \stdClass $hook Webhook.
     * @param string $event Event name.
     * @param string $body JSON body.
     * @param callable|null $send fn(string $url, array $headers, string $body): array [HTTP code, answer]. Tests pass
     *                            their own; by default the body is posted.
     */
    public static function deliver(\stdClass $hook, string $event, string $body, ?callable $send = null): void {
        global $DB;

        $send = $send ?? [self::class, 'http'];
        $signature = 'sha256=' . hash_hmac('sha256', $body, self::secret($hook));
        [$code, $answer] = $send($hook->url, ['Content-Type: application/json', 'X-Oriskill-Event: ' . $event,
            'X-Oriskill-Signature: ' . $signature], $body);
        $time = userdate(time(), get_string('strftimedatetimeshort', 'langconfig'));
        if ($code < 200 || $code >= 300) {
            $DB->set_field(self::TABLE, 'lastresult', get_string('webhook:lasterror', 'local_oriskill', (object) [
                'event' => $event, 'time' => $time, 'error' => trim('HTTP ' . $code . ' ' . \core_text::substr(
                strip_tags((string) $answer), 0, 120))]), ['id' => $hook->id]);
            throw new \moodle_exception('error:webhookdelivery', 'local_oriskill', '', 'HTTP ' . $code);
        }
        $DB->set_field(self::TABLE, 'lastresult', get_string('webhook:lastok', 'local_oriskill',
            (object) ['event' => $event, 'time' => $time]), ['id' => $hook->id]);
    }

    /**
     * Post to the receiver.
     *
     * @param string $url Address.
     * @param array $headers Headers.
     * @param string $body JSON body.
     * @return array [HTTP code, answer]
     */
    public static function http(string $url, array $headers, string $body): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setHeader($headers);
        $answer = (string) $curl->post($url, $body, ['CURLOPT_TIMEOUT' => 15]);
        if ($curl->get_errno()) {
            return [0, $curl->error];
        }
        return [(int) ($curl->get_info()['http_code'] ?? 0), $answer];
    }

    /**
     * What an order event says: buyer, order, courses, offer, instalments.
     *
     * @param int $orderid Order.
     * @return array|null [trainer, data], or null when the order is not a trainer's.
     */
    public static function order_data(int $orderid): ?array {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        $sellers = $order ? seller_service::purchase_sellers($order, (int) $order->userid) : [];
        $creator = count($sellers) === 1 ? reset($sellers) : null;
        if (!$creator) {
            return null;
        }
        $user = $DB->get_record('user', ['id' => $order->userid]);
        $notes = json_decode((string) $order->notes, true) ?: [];
        $courses = [];
        foreach ($DB->get_records_select('local_moderncommerce_order_items', 'orderid = ? AND courseid IS NOT NULL',
                [$orderid]) as $item) {
            $courses[] = ['id' => (int) $item->courseid, 'name' => format_string($item->itemname)];
        }
        $instalments = $DB->get_record('local_oriskill_instalments', ['orderid' => $orderid]);
        return [$creator, [
            'trainer' => ['id' => (int) $creator->id, 'name' => format_string($creator->displayname)],
            'buyer' => ['id' => (int) $user->id, 'email' => $user->email, 'firstname' => $user->firstname,
                'lastname' => $user->lastname],
            'order' => ['number' => $order->ordernumber, 'status' => $order->status, 'total' => (float) $order->total,
                'discount' => (float) $order->discount, 'refunded' => (float) $order->refundedtotal,
                'currency' => $order->currency, 'coupon' => $order->couponcode],
            'courses' => $courses,
            'offer' => !empty($notes['offer']) ? (string) $DB->get_field('local_oriskill_formoffers', 'label',
                ['id' => $notes['offer']]) : null,
            'instalments' => $instalments ? ['paid' => (int) $instalments->paid, 'count' => (int) $instalments->count,
                'amount' => (float) $instalments->amount, 'first' => (float) ($instalments->firstamount ?: $instalments->amount),
                'status' => $instalments->status] : null,
        ]];
    }

    /**
     * Queue an order event for the trainer's webhooks.
     *
     * @param int $orderid Order.
     * @param string $event Event name.
     * @param array $extra More data.
     */
    public static function order_event(int $orderid, string $event, array $extra = []): void {
        if ($found = self::order_data($orderid)) {
            [$creator, $data] = $found;
            self::dispatch($creator, $event, $data + $extra);
        }
    }

    /**
     * SecureAndPay events of purchases in instalments and refunds.
     *
     * @param \core\event\base $event instalment_paid, instalment_failed, instalments_stopped or payment_refunded.
     */
    public static function secureandpay_event(\core\event\base $event): void {
        $names = [
            '\local_secureandpay\event\instalment_paid' => 'instalment.paid',
            '\local_secureandpay\event\instalment_failed' => 'instalment.failed',
            '\local_secureandpay\event\instalments_stopped' => 'instalments.stopped',
            '\local_secureandpay\event\payment_refunded' => 'refund.done',
        ];
        $name = $names['\\' . get_class($event)] ?? null;
        if ($name) {
            self::order_event((int) $event->objectid, $name, $name === 'refund.done'
                ? ['refund' => ['amount' => (float) $event->other['amount']]] : []);
        }
    }
}
