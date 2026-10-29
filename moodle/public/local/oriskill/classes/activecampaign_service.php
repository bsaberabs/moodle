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
 * A trainer's ActiveCampaign account (API v3): buyers of the trainer's courses are added as contacts, subscribed to
 * a list and tagged (general tags, the courses bought, the order form offer's tags), and tagged again when an
 * instalment is unpaid. Calls run in an ad hoc task, so a payment never waits for ActiveCampaign, and are retried
 * when it does not answer.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activecampaign_service {

    /** Table of the trainers' accounts. */
    const TABLE = 'local_oriskill_activecampaign';

    /**
     * Trainer's ActiveCampaign account, if set up.
     *
     * @param \stdClass $creator Trainer.
     * @return \stdClass|null
     */
    public static function account(\stdClass $creator): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['creatorid' => $creator->id]) ?: null;
    }

    /**
     * API address as ActiveCampaign shows it (Settings > Developer), without a trailing /api/3 or slash, or null.
     *
     * @param string $url Address as typed.
     * @return string|null
     */
    public static function normalise_url(string $url): ?string {
        $url = rtrim(trim($url), '/');
        $url = preg_replace('~/api/3$~', '', $url);
        if (!preg_match('~^https://[a-z0-9-]+(\.[a-z0-9-]+)+$~i', $url)) {
            return null;
        }
        return strtolower($url);
    }

    /**
     * Save the trainer's account. An empty key keeps the saved one.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $data enabled, apiurl, apikey, listid, tags, coursetags, unpaidtag.
     */
    public static function save(\stdClass $creator, \stdClass $data): void {
        global $DB;

        $existing = self::account($creator);
        $record = (object) [
            'creatorid' => $creator->id,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'apiurl' => (string) self::normalise_url((string) $data->apiurl),
            'listid' => (int) $data->listid ?: null,
            'tags' => self::clean_tags((string) $data->tags),
            'coursetags' => empty($data->coursetags) ? 0 : 1,
            'unpaidtag' => trim((string) $data->unpaidtag),
            'timemodified' => time(),
        ];
        if (trim((string) $data->apikey) !== '') {
            $record->apikey = \core\encryption::encrypt(trim($data->apikey));
        }
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record(self::TABLE, $record);
        } else {
            $DB->insert_record(self::TABLE, $record);
        }
    }

    /**
     * Lists of the account, to choose the one buyers join.
     *
     * @param \stdClass $account Account.
     * @param callable|null $send See request().
     * @return array id => name
     */
    public static function lists(\stdClass $account, ?callable $send = null): array {
        $lists = [];
        foreach (self::request($account, 'GET', '/api/3/lists?limit=100', null, $send)['lists'] ?? [] as $list) {
            $lists[(int) $list['id']] = (string) $list['name'];
        }
        return $lists;
    }

    /**
     * Tags for the buyer of an order: the account's general tags, the name of each course bought (if chosen), and
     * the tags of the order form offer.
     *
     * @param \stdClass $account Account.
     * @param int $orderid Paid order.
     * @return string[]
     */
    public static function purchase_tags(\stdClass $account, int $orderid): array {
        global $DB;

        $tags = $account->tags ? explode(',', $account->tags) : [];
        if ($account->coursetags) {
            foreach ($DB->get_records_select('local_moderncommerce_order_items', 'orderid = ? AND courseid IS NOT NULL',
                    [$orderid]) as $item) {
                $tags[] = format_string($item->itemname);
            }
        }
        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        $notes = $order ? (json_decode((string) $order->notes, true) ?: []) : [];
        if (!empty($notes['offer'])) {
            $offertags = (string) $DB->get_field('local_oriskill_formoffers', 'actags', ['id' => $notes['offer']]);
            $tags = array_merge($tags, $offertags !== '' ? explode(',', $offertags) : []);
        }
        return array_values(array_unique(array_filter(array_map('trim', $tags))));
    }

    /**
     * A trainer's course was paid: queue the buyer's addition to the trainer's account.
     *
     * @param int $orderid Paid order.
     */
    public static function order_paid(int $orderid): void {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $orderid]);
        $sellers = $order ? seller_service::purchase_sellers($order, (int) $order->userid) : [];
        $creator = count($sellers) === 1 ? reset($sellers) : null;
        $account = $creator ? self::account($creator) : null;
        if ($account && $account->enabled) {
            self::queue($creator, (int) $order->userid, self::purchase_tags($account, $orderid), true);
        }
    }

    /**
     * An instalment is unpaid: queue the "unpaid" tag, when the trainer chose one.
     *
     * @param \local_secureandpay\event\instalment_failed $event Event.
     */
    public static function instalment_failed(\local_secureandpay\event\instalment_failed $event): void {
        global $DB;

        $order = $DB->get_record('local_moderncommerce_orders', ['id' => $event->objectid]);
        $sellers = $order ? seller_service::purchase_sellers($order, (int) $order->userid) : [];
        $creator = count($sellers) === 1 ? reset($sellers) : null;
        $account = $creator ? self::account($creator) : null;
        if ($account && $account->enabled && $account->unpaidtag !== '') {
            self::queue($creator, (int) $order->userid, [$account->unpaidtag], false);
        }
    }

    /**
     * Queue a contact update.
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid Buyer.
     * @param string[] $tags Tags to add.
     * @param bool $subscribe Also subscribe to the account's list.
     */
    public static function queue(\stdClass $creator, int $userid, array $tags, bool $subscribe): void {
        $task = new task\activecampaign_sync();
        $task->set_custom_data(['creatorid' => (int) $creator->id, 'userid' => $userid, 'tags' => $tags,
            'subscribe' => $subscribe]);
        \core\task\manager::queue_adhoc_task($task);
    }

    /**
     * Add or update a contact, subscribe it to the list and tag it.
     *
     * @param \stdClass $account Account.
     * @param \stdClass $user Buyer.
     * @param string[] $tags Tags to add.
     * @param bool $subscribe Subscribe to the account's list.
     * @param callable|null $send See request().
     * @return int ActiveCampaign contact id.
     */
    public static function sync(\stdClass $account, \stdClass $user, array $tags, bool $subscribe,
            ?callable $send = null): int {
        global $DB;

        try {
            $contact = self::request($account, 'POST', '/api/3/contact/sync', ['contact' => [
                'email' => $user->email, 'firstName' => $user->firstname, 'lastName' => $user->lastname,
                'phone' => (string) ($user->phone1 ?: $user->phone2)]], $send);
            $contactid = (int) ($contact['contact']['id'] ?? 0);
            if (!$contactid) {
                throw new \moodle_exception('error:activecampaign', 'local_oriskill', '', 'contact');
            }
            if ($subscribe && $account->listid) {
                self::request($account, 'POST', '/api/3/contactLists', ['contactList' => [
                    'list' => (int) $account->listid, 'contact' => $contactid, 'status' => 1]], $send);
            }
            foreach ($tags as $tag) {
                self::request($account, 'POST', '/api/3/contactTags', ['contactTag' => [
                    'contact' => $contactid, 'tag' => self::tag_id($account, $tag, $send)]], $send);
            }
            $result = get_string('ac:lastok', 'local_oriskill', (object) ['email' => $user->email,
                'time' => userdate(time(), get_string('strftimedatetimeshort', 'langconfig'))]);
        } catch (\moodle_exception $e) {
            $result = get_string('ac:lasterror', 'local_oriskill', (object) ['email' => $user->email,
                'error' => $e->getMessage(), 'time' => userdate(time(), get_string('strftimedatetimeshort', 'langconfig'))]);
            $DB->set_field(self::TABLE, 'lastresult', $result, ['id' => $account->id]);
            throw $e;
        }
        $DB->set_field(self::TABLE, 'lastresult', $result, ['id' => $account->id]);
        return $contactid;
    }

    /**
     * Id of a tag of the account, created when it does not exist yet.
     *
     * @param \stdClass $account Account.
     * @param string $name Tag.
     * @param callable|null $send See request().
     * @return int
     */
    private static function tag_id(\stdClass $account, string $name, ?callable $send): int {
        $found = self::request($account, 'GET', '/api/3/tags?search=' . rawurlencode($name), null, $send);
        foreach ($found['tags'] ?? [] as $tag) {
            if (\core_text::strtolower((string) $tag['tag']) === \core_text::strtolower($name)) {
                return (int) $tag['id'];
            }
        }
        $created = self::request($account, 'POST', '/api/3/tags', ['tag' => ['tag' => $name, 'tagType' => 'contact',
            'description' => 'Oriskill']], $send);
        return (int) ($created['tag']['id'] ?? 0);
    }

    /**
     * One API call.
     *
     * @param \stdClass $account Account.
     * @param string $method GET or POST.
     * @param string $path Path from the API address, such as /api/3/lists.
     * @param array|null $body JSON body.
     * @param callable|null $send fn(string $method, string $url, array $headers, ?string $body): array [HTTP code, body].
     *                            Tests pass their own; by default the call goes to ActiveCampaign.
     * @return array Decoded answer.
     */
    public static function request(\stdClass $account, string $method, string $path, ?array $body = null,
            ?callable $send = null): array {
        if (!$account->apiurl || !$account->apikey) {
            throw new \moodle_exception('error:activecampaign', 'local_oriskill', '', get_string('ac:notset', 'local_oriskill'));
        }
        $send = $send ?? [self::class, 'http'];
        [$code, $answer] = $send($method, $account->apiurl . $path,
            ['Api-Token: ' . \core\encryption::decrypt($account->apikey), 'Content-Type: application/json',
             'Accept: application/json'], $body === null ? null : json_encode($body));
        $decoded = json_decode((string) $answer, true);
        if ($code < 200 || $code >= 300) {
            $message = is_array($decoded) ? ($decoded['message'] ?? ($decoded['errors'][0]['title'] ?? '')) : '';
            throw new \moodle_exception('error:activecampaign', 'local_oriskill', '', trim('HTTP ' . $code . ' ' . $message));
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Send one call to ActiveCampaign.
     *
     * @param string $method GET or POST.
     * @param string $url Address.
     * @param array $headers Headers.
     * @param string|null $body JSON body.
     * @return array [HTTP code, body]
     */
    public static function http(string $method, string $url, array $headers, ?string $body): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setHeader($headers);
        $options = ['CURLOPT_TIMEOUT' => 20];
        $answer = $method === 'POST' ? $curl->post($url, (string) $body, $options) : $curl->get($url, [], $options);
        if ($curl->get_errno()) {
            return [0, $curl->error];
        }
        return [(int) ($curl->get_info()['http_code'] ?? 0), (string) $answer];
    }

    /**
     * Tags typed by the trainer, trimmed and without duplicates.
     *
     * @param string $tags Comma-separated tags.
     * @return string
     */
    public static function clean_tags(string $tags): string {
        $list = array_unique(array_filter(array_map(fn($t) => \core_text::substr(trim($t), 0, 100), explode(',', $tags))));
        return \core_text::substr(implode(',', $list), 0, 255);
    }
}
