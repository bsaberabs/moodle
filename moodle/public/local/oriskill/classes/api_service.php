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
 * REST API of a trainer's space, for Make, Zapier or their own tools: one key per application, limited to some rights,
 * shown once (only a hash is kept), revocable on its own, with a log of its calls.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_service {

    /** Keys and their calls. */
    const KEYS = 'local_oriskill_apikeys';
    const LOG = 'local_oriskill_apilog';

    /** Rights a key can have. */
    const SCOPES = ['contacts:read', 'contacts:write', 'sales:read', 'learners:read', 'courses:read'];

    /** Endpoints: method and path => right needed. */
    const ENDPOINTS = [
        'GET /contacts' => 'contacts:read',
        'GET /contacts/{id}' => 'contacts:read',
        'POST /contacts' => 'contacts:write',
        'GET /transactions' => 'sales:read',
        'GET /courses' => 'courses:read',
        'GET /courses/{id}/learners' => 'learners:read',
    ];

    /** Calls a key may make per hour. */
    const PER_HOUR = 600;

    /** Days the log of calls is kept. */
    const LOG_DAYS = 30;

    /** Most rows per page of a list. */
    const MAXPERPAGE = 200;

    /**
     * Keys of a trainer.
     *
     * @param \stdClass $creator Trainer.
     * @return array
     */
    public static function keys(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::KEYS, ['creatorid' => $creator->id], 'timecreated DESC');
    }

    /**
     * Create a key. Its value is returned once and never stored.
     *
     * @param \stdClass $creator Trainer.
     * @param string $name Application it is for.
     * @param string[] $scopes Rights.
     * @return array [record, key]
     */
    public static function create(\stdClass $creator, string $name, array $scopes): array {
        global $DB;

        $name = \core_text::substr(trim(clean_param($name, PARAM_TEXT)), 0, 100);
        $scopes = array_values(array_intersect(self::SCOPES, $scopes));
        if ($name === '' || !$scopes) {
            throw new \moodle_exception('api:incomplete', 'local_oriskill');
        }
        $key = 'osk_' . random_string(40);
        $record = (object) ['creatorid' => $creator->id, 'name' => $name, 'scopes' => implode(',', $scopes),
            'keyhash' => hash('sha256', $key), 'lastfour' => substr($key, -4), 'enabled' => 1, 'calls' => 0, 'lastused' => 0,
            'timecreated' => time()];
        $record->id = $DB->insert_record(self::KEYS, $record);
        log_service::add($creator, 'apikey_created', ['name' => $name]);
        return [$record, $key];
    }

    /**
     * Revoke a key: it stops working at once.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Key.
     */
    public static function revoke(\stdClass $creator, int $id): void {
        global $DB;

        $record = $DB->get_record(self::KEYS, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
        $DB->delete_records(self::KEYS, ['id' => $record->id]);
        log_service::add($creator, 'apikey_revoked', ['name' => $record->name]);
    }

    /**
     * Latest calls of a trainer's keys.
     *
     * @param \stdClass $creator Trainer.
     * @param int $limit Most calls.
     * @return array
     */
    public static function calls(\stdClass $creator, int $limit = 50): array {
        global $DB;

        return $DB->get_records_sql("SELECT l.*, k.name FROM {" . self::LOG . "} l JOIN {" . self::KEYS . "} k ON k.id = l.keyid
                                      WHERE k.creatorid = ? ORDER BY l.timecreated DESC, l.id DESC", [$creator->id], 0, $limit);
    }

    /**
     * Answer a call: check the key and its right, run the endpoint, log the call.
     *
     * @param string $method HTTP method.
     * @param string $path Path after api.php, such as /contacts/12.
     * @param string $authorization Authorization header.
     * @param array $query Query parameters.
     * @param array $body Decoded JSON body.
     * @return array [HTTP status, data]
     */
    public static function handle(string $method, string $path, string $authorization, array $query, array $body): array {
        global $DB;

        $key = preg_match('/^Bearer\s+(\S+)$/i', trim($authorization), $m) ? $m[1] : '';
        $record = $key !== '' ? $DB->get_record(self::KEYS, ['keyhash' => hash('sha256', $key), 'enabled' => 1]) : null;
        $creator = $record ? $DB->get_record('local_oriskill_creators', ['id' => $record->creatorid, 'status' => 'active']) : null;
        if (!$record || !$creator) {
            return [401, ['error' => 'invalid_key']];
        }
        $path = '/' . trim($path, '/');
        $route = null;
        $id = 0;
        foreach (self::ENDPOINTS as $endpoint => $scope) {
            [$verb, $pattern] = explode(' ', $endpoint);
            $regex = '~^' . str_replace('\{id\}', '(\d+)', preg_quote($pattern, '~')) . '$~';
            if ($verb === $method && preg_match($regex, $path, $mm)) {
                $route = [$endpoint, $scope];
                $id = (int) ($mm[1] ?? 0);
                break;
            }
        }
        if (!$route) {
            $status = [404, ['error' => 'unknown_endpoint']];
        } else if (!in_array($route[1], explode(',', $record->scopes), true)) {
            $status = [403, ['error' => 'missing_right', 'right' => $route[1]]];
        } else if ($DB->count_records_select(self::LOG, 'keyid = ? AND timecreated > ?', [$record->id, time() - HOURSECS])
                >= self::PER_HOUR) {
            $status = [429, ['error' => 'too_many_calls', 'limit_per_hour' => self::PER_HOUR]];
        } else {
            try {
                $status = [200, self::run($route[0], $creator, $id, $query, $body)];
            } catch (\moodle_exception $e) {
                $status = [$e->errorcode === 'notfound' ? 404 : 400, ['error' => $e->errorcode, 'message' => $e->getMessage()]];
            }
        }
        $DB->insert_record(self::LOG, (object) ['keyid' => $record->id, 'method' => $method,
            'endpoint' => \core_text::substr($path, 0, 255), 'status' => $status[0], 'ip' => getremoteaddr(),
            'timecreated' => time()]);
        $DB->update_record(self::KEYS, (object) ['id' => $record->id, 'calls' => $record->calls + 1, 'lastused' => time()]);
        return $status;
    }

    /**
     * Run an endpoint.
     *
     * @param string $endpoint One of ENDPOINTS.
     * @param \stdClass $creator Trainer of the key.
     * @param int $id Id in the path.
     * @param array $query Query parameters.
     * @param array $body JSON body.
     * @return array
     */
    protected static function run(string $endpoint, \stdClass $creator, int $id, array $query, array $body): array {
        global $DB;

        $page = max(0, (int) ($query['page'] ?? 0));
        $perpage = max(1, min(self::MAXPERPAGE, (int) ($query['perpage'] ?? 50)));
        switch ($endpoint) {
            case 'GET /contacts':
                [$contacts, $total] = contact_service::search($creator, ['q' => (string) ($query['q'] ?? '')], 'created', 'DESC',
                    $page, $perpage);
                $tags = contact_service::tags_of(array_keys($contacts));
                return ['total' => $total, 'page' => $page, 'perpage' => $perpage,
                    'data' => array_values(array_map(fn($c) => self::contact($c, $tags[$c->id] ?? []), $contacts))];
            case 'GET /contacts/{id}':
                $contact = $DB->get_record(contact_service::TABLE, ['id' => $id, 'creatorid' => $creator->id]);
                if (!$contact) {
                    throw new \moodle_exception('notfound');
                }
                return self::contact($contact, contact_service::tags_of([$contact->id])[$contact->id] ?? []);
            case 'POST /contacts':
                $email = \core_text::strtolower(trim((string) ($body['email'] ?? '')));
                if (!validate_email($email)) {
                    throw new \moodle_exception('invalidemail');
                }
                $fields = array_intersect_key(array_map(fn($v) => clean_param((string) $v, PARAM_TEXT), $body),
                    array_flip(contact_service::FIELDS));
                $contact = contact_service::upsert($creator, $email, $fields, 'manual');
                if (!empty($body['tags'])) {
                    contact_service::add_tags($creator, [(int) $contact->id], array_map(fn($t) => clean_param((string) $t,
                        PARAM_TEXT), (array) $body['tags']));
                }
                return self::contact($contact, contact_service::tags_of([$contact->id])[$contact->id] ?? []);
            case 'GET /transactions':
                $from = isset($query['from']) ? (int) $query['from'] : time() - 30 * DAYSECS;
                $to = isset($query['to']) ? (int) $query['to'] : time();
                $rows = array_reverse(transaction_service::rows($creator, $from, $to));
                return ['total' => count($rows), 'page' => $page, 'perpage' => $perpage,
                    'data' => array_map(fn($r) => ['time' => $r->time, 'type' => $r->type, 'amount' => $r->amount,
                        'currency' => $r->currency, 'buyer' => $r->buyer, 'email' => $r->email,
                        'products' => array_keys($r->lines), 'method' => $r->method, 'instalment' => $r->instalment,
                        'reason' => $r->reason, 'invoice' => $r->number, 'orderid' => $r->orderid],
                        array_slice($rows, $page * $perpage, $perpage))];
            case 'GET /courses':
                return ['data' => array_values(array_map(fn($c) => ['id' => (int) $c->id, 'name' => format_string($c->fullname),
                    'shortname' => $c->shortname, 'visible' => (bool) $c->visible],
                    $DB->get_records('course', ['category' => $creator->categoryid], 'fullname', 'id, fullname, shortname, visible')))];
            case 'GET /courses/{id}/learners':
                $course = $DB->get_record('course', ['id' => $id, 'category' => $creator->categoryid]);
                if (!$course) {
                    throw new \moodle_exception('notfound');
                }
                $learners = array_values(learning_service::learners($creator, $course));
                return ['total' => count($learners), 'page' => $page, 'perpage' => $perpage,
                    'data' => array_map(fn($l) => ['userid' => (int) $l->id, 'email' => $l->email, 'firstname' => $l->firstname,
                        'lastname' => $l->lastname, 'start' => (int) $l->timestart, 'end' => (int) $l->timeend,
                        'suspended' => (bool) $l->suspended, 'progress' => $l->progress === null ? null : round($l->progress),
                        'completed' => (int) $l->completed, 'groups' => array_map(fn($g) => $g->name, $l->groups)],
                        array_slice($learners, $page * $perpage, $perpage))];
        }
        throw new \moodle_exception('notfound');
    }

    /**
     * A contact as the API gives it.
     *
     * @param \stdClass $c Contact.
     * @param array $tags Its tags.
     * @return array
     */
    protected static function contact(\stdClass $c, array $tags): array {
        return ['id' => (int) $c->id, 'email' => $c->email, 'firstname' => $c->firstname, 'lastname' => $c->lastname,
            'phone' => $c->phone, 'country' => $c->country, 'status' => $c->status, 'ltv' => (float) $c->ltv,
            'consentdirect' => (bool) $c->consentdirect, 'consentpartners' => (bool) $c->consentpartners, 'tags' => array_values($tags),
            'created' => (int) $c->timecreated];
    }

    /**
     * Calls older than LOG_DAYS go. Run with the daily housekeeping.
     *
     * @return int Calls removed.
     */
    public static function cleanup(): int {
        global $DB;

        $before = time() - self::LOG_DAYS * DAYSECS;
        $count = $DB->count_records_select(self::LOG, 'timecreated < ?', [$before]);
        $DB->delete_records_select(self::LOG, 'timecreated < ?', [$before]);
        return $count;
    }
}
