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

namespace local_secureandpay;

use moodle_exception;

/**
 * Refunds asked of SecureAndPay from the site (web service refundWs.php).
 *
 * SecureAndPay refunds one charge at a time, named by its bank transaction (banqueId): a refund larger than one
 * charge (purchase in instalments) is split over the charges, newest first. SecureAndPay's accounting validates
 * each request, then notifies TransStatus 03 with the amount refunded: only then is the order updated and the
 * payment_refunded event raised (see notification_handler).
 *
 * @package    local_secureandpay
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refund_service {

    /** Refund request URLs. */
    private const URL_LIVE = 'https://www.secureandpay.com/refundWs.php';
    private const URL_TEST = 'https://www.secureandpay.com:4443/refundWs.php';

    /** refundWs.php answer code of an accepted request. */
    private const ACCEPTED = 220;

    /**
     * Charges received for an order, newest first, with what is left to refund on each.
     *
     * @param object $order Order.
     * @return array of {bankid, amount, left}
     */
    public static function charges(object $order): array {
        global $DB;

        $events = $DB->get_records_select('local_moderncommerce_payment_events',
            "orderid = :orderid AND gateway = :gateway AND eventtype IN ('secureandpay.00', 'secureandpay.02')
             AND amount > 0 AND transactionid IS NOT NULL", ['orderid' => $order->id, 'gateway' => gateway::GATEWAY],
            'timecreated DESC, id DESC', 'id, transactionid, amount');
        $asked = $DB->get_records_sql_menu(
            "SELECT bankid, SUM(amount) FROM {local_secureandpay_refunds}
              WHERE orderid = ? AND status <> 'refused' GROUP BY bankid", [$order->id]);
        $charges = [];
        foreach ($events as $event) {
            $charges[] = (object) ['bankid' => $event->transactionid, 'amount' => round((float) $event->amount, 2),
                'left' => round((float) $event->amount - (float) ($asked[$event->transactionid] ?? 0), 2)];
        }
        return $charges;
    }

    /**
     * Amount of an order that can still be refunded (charges received, minus refunds asked or done).
     *
     * @param object $order Order.
     * @return float
     */
    public static function refundable(object $order): float {
        return round(array_sum(array_map(fn($charge) => max(0, $charge->left), self::charges($order))), 2);
    }

    /**
     * Refunds asked for an order, newest first.
     *
     * @param object $order Order.
     * @return array
     */
    public static function requests(object $order): array {
        global $DB;
        return $DB->get_records('local_secureandpay_refunds', ['orderid' => $order->id], 'timecreated DESC, id DESC');
    }

    /**
     * Ask SecureAndPay to refund an amount of an order.
     *
     * @param object $order Order paid through SecureAndPay.
     * @param float $amount Amount to refund.
     * @param string $reason Reason, kept with the request.
     * @param callable|null $send Sends one request: fn(string $url, array $fields): array [HTTP code, body]. Tests
     *                            pass their own; by default it is posted to SecureAndPay.
     * @return array Requests recorded (status requested).
     */
    public static function request(object $order, float $amount, string $reason, ?callable $send = null): array {
        global $DB, $USER;

        $amount = round($amount, 2);
        if ($amount <= 0 || $amount > self::refundable($order) + 0.001) {
            throw new moodle_exception('error:refundamount', 'local_secureandpay');
        }
        $merchant = gateway::merchant_for_order($order, false);
        if (!$merchant->is_configured()) {
            throw new moodle_exception('notconfigured', 'local_secureandpay');
        }
        $send = $send ?? [self::class, 'post'];
        $url = $merchant->testmode ? self::URL_TEST : self::URL_LIVE;

        $requests = [];
        $left = $amount;
        foreach (self::charges($order) as $charge) {
            if ($left <= 0) {
                break;
            }
            $part = round(min($left, $charge->left), 2);
            if ($part <= 0) {
                continue;
            }
            $data = base64_encode(json_encode(['IdSite' => $merchant->siteid, 'bankID' => $charge->bankid,
                'amount' => (int) round($part * 100)]));
            [$httpcode, $body] = $send($url, ['data' => $data, 'sig' => $merchant->sign($data)]);
            [$accepted, $answer] = self::read_answer((int) $httpcode, (string) $body);
            $now = time();
            $record = (object) [
                'orderid' => $order->id,
                'bankid' => $charge->bankid,
                'amount' => $part,
                'currency' => $order->currency,
                'reason' => $reason,
                'status' => $accepted ? 'requested' : 'refused',
                'answer' => $answer,
                'requestedby' => $USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('local_secureandpay_refunds', $record);
            if (!$accepted) {
                throw new moodle_exception('error:refundrefused', 'local_secureandpay', '', $answer);
            }
            $requests[] = $record;
            $left = round($left - $part, 2);
        }
        return $requests;
    }

    /**
     * Read the answer of refundWs.php: {"code": 220, "msg": "..."} when the request is recorded; 221 missing data,
     * 222 unknown transaction, 223 wrong IdSite, 224 bad signature.
     *
     * @param int $httpcode HTTP status.
     * @param string $body Response body.
     * @return array [bool accepted, string code and message]
     */
    public static function read_answer(int $httpcode, string $body): array {
        if ($httpcode !== 200) {
            return [false, 'HTTP ' . $httpcode];
        }
        $decoded = json_decode($body, true);
        $code = is_array($decoded) ? (int) ($decoded['code'] ?? 0) : 0;
        $message = is_array($decoded) ? trim((string) ($decoded['msg'] ?? '')) : trim(strip_tags($body));
        return [$code === self::ACCEPTED, trim($code . ' ' . \core_text::substr($message, 0, 200))];
    }

    /**
     * SecureAndPay notified a refund (TransStatus 03): mark the matching request refunded.
     *
     * @param object $order Order.
     * @param float $amount Amount notified.
     */
    public static function refund_notified(object $order, float $amount): void {
        global $DB;

        // The oldest request of that amount still waiting; a refund made in SecureAndPay's back-office has none.
        $requests = $DB->get_records('local_secureandpay_refunds', ['orderid' => $order->id, 'status' => 'requested'],
            'timecreated ASC, id ASC');
        foreach ($requests as $request) {
            if (abs((float) $request->amount - $amount) < 0.005) {
                $DB->update_record('local_secureandpay_refunds', (object) ['id' => $request->id, 'status' => 'refunded',
                    'timemodified' => time()]);
                return;
            }
        }
    }

    /**
     * Post one request to SecureAndPay.
     *
     * @param string $url refundWs.php address.
     * @param array $fields data and sig.
     * @return array [HTTP code, body]
     */
    public static function post(string $url, array $fields): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $body = (string) $curl->post($url, $fields, ['CURLOPT_TIMEOUT' => 20]);
        if ($curl->get_errno()) {
            return [0, $curl->error];
        }
        return [(int) ($curl->get_info()['http_code'] ?? 0), $body];
    }
}
