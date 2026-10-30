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
 * The trainer's CRM: one contact per person (merged by e-mail), whether prospect, learner or customer.
 *
 * Contacts are added on their own: learners of the trainer's courses, buyers, sign-ups of the capture pages and
 * order forms left unpaid. A contact may have no account (added by hand or imported).
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contact_service {

    /** Contacts. */
    const TABLE = 'local_oriskill_contacts';

    /** Tags of the contacts. */
    const TAGS = 'local_oriskill_contacttags';

    /** Quick views of the list. */
    const VIEWS = ['all', 'members', 'prospects', 'customers'];

    /** Columns the list can be sorted by => SQL. */
    const SORTS = ['name' => 'c.lastname, c.firstname', 'email' => 'c.email', 'status' => 'c.status',
        'created' => 'c.timecreated', 'ltv' => 'c.ltv'];

    /** Rows per page offered. */
    const PERPAGE = [10, 25, 50, 100];

    /** Where a contact came from. */
    const SOURCES = ['learner', 'order', 'capture', 'checkout', 'manual', 'import'];

    /** Fields the trainer can edit. */
    const FIELDS = ['firstname', 'lastname', 'phone', 'address', 'postcode', 'city', 'country'];

    // ----- Adding and merging -------------------------------------------------------------------------------------

    /**
     * Add a contact, or complete the one with the same e-mail address (only empty fields are filled).
     *
     * @param \stdClass $creator Trainer.
     * @param string $email E-mail address.
     * @param array $data Fields (FIELDS, userid, consentdirect, consentpartners).
     * @param string $source One of SOURCES, for a new contact.
     * @return \stdClass The contact.
     */
    public static function upsert(\stdClass $creator, string $email, array $data, string $source): \stdClass {
        global $DB;

        $email = \core_text::strtolower(trim($email));
        if (!validate_email($email)) {
            throw new \moodle_exception('invalidemail');
        }
        $now = time();
        $contact = $DB->get_record(self::TABLE, ['creatorid' => $creator->id, 'email' => $email]);
        if (!$contact) {
            $record = (object) ['creatorid' => $creator->id, 'email' => $email, 'userid' => (int) ($data['userid'] ?? 0),
                'status' => 'active', 'source' => in_array($source, self::SOURCES, true) ? $source : 'manual',
                'consentdirect' => (int) !empty($data['consentdirect']), 'consentpartners' => (int) !empty($data['consentpartners']),
                'consentdate' => !empty($data['consentdirect']) || !empty($data['consentpartners']) ? $now : 0,
                'ltv' => 0, 'timecreated' => $now, 'timemodified' => $now];
            foreach (self::FIELDS as $field) {
                $record->$field = \core_text::substr(trim((string) ($data[$field] ?? '')), 0, $field === 'address' ? 255 : 100);
            }
            $record->id = $DB->insert_record(self::TABLE, $record);
            $record->isnew = true;
            return $record;
        }
        $update = [];
        foreach (self::FIELDS as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value !== '' && trim((string) $contact->$field) === '') {
                $update[$field] = \core_text::substr($value, 0, $field === 'address' ? 255 : 100);
            }
        }
        if (!empty($data['userid']) && !$contact->userid) {
            $update['userid'] = (int) $data['userid'];
        }
        // A consent given is kept with its date; it is withdrawn only by the trainer, on the contact's page.
        foreach (['consentdirect', 'consentpartners'] as $consent) {
            if (!empty($data[$consent]) && !$contact->$consent) {
                $update[$consent] = 1;
                $update['consentdate'] = $now;
            }
        }
        if ($update) {
            $DB->update_record(self::TABLE, (object) (['id' => $contact->id, 'timemodified' => $now] + $update));
            $contact = $DB->get_record(self::TABLE, ['id' => $contact->id]);
        }
        return $contact;
    }

    /**
     * Add or complete the contact of a platform account, and refresh its lifetime value.
     *
     * @param \stdClass $creator Trainer.
     * @param int $userid Account.
     * @param string $source One of SOURCES, for a new contact.
     * @param array $extra More data (consentdirect...).
     * @return \stdClass|null The contact, null for an account that cannot be one (deleted, guest, trainer, team).
     */
    public static function from_user(\stdClass $creator, int $userid, string $source, array $extra = []): ?\stdClass {
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted || isguestuser($user) || $userid == $creator->userid || !validate_email($user->email)
                || in_array($userid, team_service::member_userids($creator), true)) {
            return null;
        }
        $contact = self::upsert($creator, $user->email, ['userid' => $userid, 'firstname' => $user->firstname,
            'lastname' => $user->lastname, 'phone' => $user->phone1 ?: $user->phone2, 'address' => $user->address,
            'city' => $user->city, 'country' => $user->country] + $extra, $source);
        self::refresh($contact);
        return $contact;
    }

    /**
     * Same, for the trainer the course belongs to (enrolment events).
     *
     * @param int $courseid Course.
     * @param int $userid Account.
     * @param string $source Source.
     */
    public static function from_course_user(int $courseid, int $userid, string $source): void {
        global $DB;

        $creator = $DB->get_record_sql("SELECT c.* FROM {local_oriskill_creators} c JOIN {course} co ON co.category = c.categoryid
                                         WHERE co.id = ?", [$courseid], IGNORE_MULTIPLE);
        if ($creator) {
            self::from_user($creator, $userid, $source);
        }
    }

    /**
     * Recompute the lifetime value of a contact: invoices in the trainer's name less credit notes.
     *
     * @param \stdClass $contact Contact.
     */
    public static function refresh(\stdClass $contact): void {
        global $DB;

        if (!$contact->userid) {
            return;
        }
        $ltv = (float) $DB->get_field_sql(
            "SELECT COALESCE(SUM(CASE WHEN type = 'credit' THEN -total ELSE total END), 0)
               FROM {local_oriskill_invoices} WHERE creatorid = ? AND userid = ?", [$contact->creatorid, $contact->userid]);
        if (round($ltv, 2) != round((float) $contact->ltv, 2)) {
            $DB->set_field(self::TABLE, 'ltv', round($ltv, 2), ['id' => $contact->id]);
            $contact->ltv = round($ltv, 2);
        }
    }

    /**
     * Fill the CRM of a trainer from what already exists: learners, buyers, sign-ups and order forms left unpaid.
     *
     * @param \stdClass $creator Trainer.
     * @return int Contacts now in the CRM.
     */
    public static function backfill(\stdClass $creator): int {
        global $DB;

        $sources = [
            'learner' => "SELECT DISTINCT ue.userid FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                           JOIN {course} co ON co.id = e.courseid WHERE co.category = ?",
            'order' => "SELECT DISTINCT userid FROM {local_oriskill_invoices} WHERE creatorid = ? AND userid > 0",
            'capture' => "SELECT DISTINCT userid FROM {local_oriskill_leads} WHERE creatorid = ? AND userid > 0",
            'checkout' => "SELECT DISTINCT userid FROM {local_oriskill_abandons} WHERE creatorid = ? AND userid > 0",
        ];
        foreach ($sources as $source => $sql) {
            $param = $source === 'learner' ? $creator->categoryid : $creator->id;
            foreach ($DB->get_fieldset_sql($sql, [$param]) as $userid) {
                self::from_user($creator, (int) $userid, $source);
            }
        }
        // Consents given on the capture pages.
        foreach ($DB->get_records_select('local_oriskill_leads', 'creatorid = ? AND consent = 1', [$creator->id]) as $lead) {
            $contact = $DB->get_record(self::TABLE, ['creatorid' => $creator->id, 'email' => \core_text::strtolower($lead->email)]);
            if ($contact && !$contact->consentdirect) {
                $DB->update_record(self::TABLE, (object) ['id' => $contact->id, 'consentdirect' => 1,
                    'consentdate' => $lead->timecreated]);
            }
        }
        return $DB->count_records(self::TABLE, ['creatorid' => $creator->id]);
    }

    // ----- Reading --------------------------------------------------------------------------------------------------

    /**
     * A contact of the trainer.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Contact.
     * @return \stdClass
     */
    public static function require_contact(\stdClass $creator, int $id): \stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
    }

    /**
     * SQL condition "the contact's account is enrolled in one of the trainer's courses (in this one)".
     *
     * @param \stdClass $creator Trainer.
     * @param int $courseid A course, 0 for any.
     * @param int $groupid A group of that course, 0 for any.
     * @return array [sql, params]
     */
    protected static function enrolled_sql(\stdClass $creator, int $courseid = 0, int $groupid = 0): array {
        $sql = "EXISTS (SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
                          JOIN {course} co ON co.id = e.courseid
                         WHERE ue.userid = c.userid AND c.userid > 0 AND co.category = :enrolcat"
            . ($courseid ? " AND co.id = :enrolcourse" : '') . ")";
        $params = ['enrolcat' => $creator->categoryid] + ($courseid ? ['enrolcourse' => $courseid] : []);
        if ($groupid) {
            $sql .= " AND EXISTS (SELECT 1 FROM {groups_members} gm WHERE gm.userid = c.userid AND gm.groupid = :enrolgroup)";
            $params['enrolgroup'] = $groupid;
        }
        return [$sql, $params];
    }

    /**
     * Contacts matching filters, a page of them.
     *
     * @param \stdClass $creator Trainer.
     * @param array $filters view, q, status, tag, courseid, groupid, ltvmin, ltvmax, from, to, consent, ids.
     * @param string $sort Key of SORTS.
     * @param string $dir ASC or DESC.
     * @param int $page Page, 0 first.
     * @param int $perpage Rows per page, 0 for all.
     * @return array [records, total]
     */
    public static function search(\stdClass $creator, array $filters, string $sort = 'created', string $dir = 'DESC',
            int $page = 0, int $perpage = 25): array {
        global $DB;

        $where = ['c.creatorid = :creatorid'];
        $params = ['creatorid' => $creator->id];
        $view = $filters['view'] ?? 'all';
        if ($view === 'members') {
            [$sql, $p] = self::enrolled_sql($creator);
            $where[] = $sql;
            $params += $p;
        } else if ($view === 'customers') {
            $where[] = 'c.ltv > 0';
        } else if ($view === 'prospects') {
            [$sql, $p] = self::enrolled_sql($creator);
            $where[] = "c.ltv <= 0 AND NOT $sql";
            $params += $p;
        }
        if (($q = trim((string) ($filters['q'] ?? ''))) !== '') {
            $like = [];
            foreach (['email', 'firstname', 'lastname', 'phone'] as $i => $field) {
                $like[] = $DB->sql_like("c.$field", ":q$i", false, false);
                $params["q$i"] = '%' . $DB->sql_like_escape($q) . '%';
            }
            $like[] = $DB->sql_like($DB->sql_concat('c.firstname', "' '", 'c.lastname'), ':qfull', false, false);
            $params['qfull'] = '%' . $DB->sql_like_escape($q) . '%';
            $where[] = '(' . implode(' OR ', $like) . ')';
        }
        if (in_array($filters['status'] ?? '', ['active', 'disabled'], true)) {
            $where[] = 'c.status = :status';
            $params['status'] = $filters['status'];
        }
        if (($tag = trim((string) ($filters['tag'] ?? ''))) !== '') {
            $where[] = "EXISTS (SELECT 1 FROM {" . self::TAGS . "} t WHERE t.contactid = c.id AND t.tag = :tag)";
            $params['tag'] = $tag;
        }
        if (!empty($filters['courseid'])) {
            [$sql, $p] = self::enrolled_sql($creator, (int) $filters['courseid'], (int) ($filters['groupid'] ?? 0));
            $where[] = $sql;
            $params += $p;
        }
        if (isset($filters['ltvmin']) && $filters['ltvmin'] !== '') {
            $where[] = 'c.ltv >= :ltvmin';
            $params['ltvmin'] = (float) $filters['ltvmin'];
        }
        if (isset($filters['ltvmax']) && $filters['ltvmax'] !== '') {
            $where[] = 'c.ltv <= :ltvmax';
            $params['ltvmax'] = (float) $filters['ltvmax'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'c.timecreated >= :from';
            $params['from'] = (int) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'c.timecreated <= :to';
            $params['to'] = (int) $filters['to'];
        }
        if (in_array($filters['consent'] ?? '', ['yes', 'no'], true)) {
            $where[] = 'c.consentdirect = :consent';
            $params['consent'] = $filters['consent'] === 'yes' ? 1 : 0;
        }
        if (!empty($filters['ids'])) {
            [$insql, $p] = $DB->get_in_or_equal(array_map('intval', $filters['ids']), SQL_PARAMS_NAMED, 'id');
            $where[] = "c.id $insql";
            $params += $p;
        }
        $wheresql = implode(' AND ', $where);
        $total = $DB->count_records_sql("SELECT COUNT(1) FROM {" . self::TABLE . "} c WHERE $wheresql", $params);
        $order = implode(', ', array_map(fn($col) => "$col " . ($dir === 'ASC' ? 'ASC' : 'DESC'),
            explode(', ', self::SORTS[$sort] ?? self::SORTS['created']))) . ', c.id DESC';
        $records = $DB->get_records_sql("SELECT c.* FROM {" . self::TABLE . "} c WHERE $wheresql ORDER BY $order", $params,
            $perpage ? $page * $perpage : 0, $perpage);
        return [$records, $total];
    }

    /**
     * Counts of the quick views.
     *
     * @param \stdClass $creator Trainer.
     * @return int[] view => count
     */
    public static function counts(\stdClass $creator): array {
        $counts = [];
        foreach (self::VIEWS as $view) {
            $counts[$view] = self::search($creator, ['view' => $view], 'created', 'DESC', 0, 1)[1];
        }
        return $counts;
    }

    // ----- Tags -----------------------------------------------------------------------------------------------------

    /**
     * Tags of contacts.
     *
     * @param int[] $contactids Contacts.
     * @return array contactid => [tag, ...]
     */
    public static function tags_of(array $contactids): array {
        global $DB;

        if (!$contactids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($contactids);
        $tags = [];
        foreach ($DB->get_records_select(self::TAGS, "contactid $insql", $params, 'tag') as $row) {
            $tags[$row->contactid][] = $row->tag;
        }
        return $tags;
    }

    /**
     * Tags used in the trainer's CRM, with how many contacts have each.
     *
     * @param \stdClass $creator Trainer.
     * @return int[] tag => count
     */
    public static function all_tags(\stdClass $creator): array {
        global $DB;
        return array_map('intval', $DB->get_records_sql_menu("SELECT tag, COUNT(1) FROM {" . self::TAGS . "}
                                                               WHERE creatorid = ? GROUP BY tag ORDER BY tag", [$creator->id]));
    }

    /**
     * Split typed tags ("vip, LC 2026") into clean ones.
     *
     * @param string $text Tags separated by commas.
     * @return string[]
     */
    public static function parse_tags(string $text): array {
        $tags = array_map(fn($t) => \core_text::substr(trim(clean_param($t, PARAM_NOTAGS)), 0, 100), explode(',', $text));
        return array_values(array_unique(array_filter($tags, fn($t) => $t !== '')));
    }

    /**
     * Add tags to contacts.
     *
     * @param \stdClass $creator Trainer.
     * @param int[] $contactids Contacts (others' are ignored).
     * @param string[] $tags Tags.
     * @param bool $checked The ids are known to be the trainer's (import): no need to check them.
     * @return int Tags added.
     */
    public static function add_tags(\stdClass $creator, array $contactids, array $tags, bool $checked = false): int {
        global $DB;

        $added = 0;
        foreach ($checked ? array_map('intval', $contactids) : self::own_ids($creator, $contactids) as $contactid) {
            foreach ($tags as $tag) {
                if (!$DB->record_exists(self::TAGS, ['contactid' => $contactid, 'tag' => $tag])) {
                    $DB->insert_record(self::TAGS, (object) ['contactid' => $contactid, 'creatorid' => $creator->id,
                        'tag' => $tag, 'timecreated' => time()]);
                    $added++;
                }
            }
        }
        return $added;
    }

    /**
     * Remove a tag from contacts.
     *
     * @param \stdClass $creator Trainer.
     * @param int[] $contactids Contacts.
     * @param string $tag Tag.
     */
    public static function remove_tag(\stdClass $creator, array $contactids, string $tag): void {
        global $DB;

        $ids = self::own_ids($creator, $contactids);
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids);
            $DB->delete_records_select(self::TAGS, "tag = ? AND contactid $insql", array_merge([$tag], $params));
        }
    }

    /**
     * Keep the ids of the trainer's own contacts.
     *
     * @param \stdClass $creator Trainer.
     * @param int[] $contactids Ids.
     * @return int[]
     */
    protected static function own_ids(\stdClass $creator, array $contactids): array {
        global $DB;

        $contactids = array_values(array_unique(array_filter(array_map('intval', $contactids))));
        if (!$contactids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($contactids);
        return array_map('intval', $DB->get_fieldset_select(self::TABLE, 'id', "creatorid = ? AND id $insql",
            array_merge([$creator->id], $params)));
    }

    // ----- Changing -------------------------------------------------------------------------------------------------

    /**
     * Save what the trainer typed on the contact's page.
     *
     * @param \stdClass $contact Contact.
     * @param array $data FIELDS, consentdirect, consentpartners.
     */
    public static function update(\stdClass $contact, array $data): void {
        global $DB;

        $update = ['id' => $contact->id, 'timemodified' => time()];
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = \core_text::substr(trim((string) $data[$field]), 0, $field === 'address' ? 255 : 100);
            }
        }
        foreach (['consentdirect', 'consentpartners'] as $consent) {
            if (array_key_exists($consent, $data) && (int) (bool) $data[$consent] !== (int) $contact->$consent) {
                $update[$consent] = (int) (bool) $data[$consent];
                $update['consentdate'] = time();
            }
        }
        $DB->update_record(self::TABLE, (object) $update);
    }

    /**
     * Turn a contact off (their access to the trainer's courses is suspended) or on again (only what was suspended
     * here comes back: an access suspended for an unpaid instalment stays suspended).
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $contact Contact.
     * @param bool $disabled Off.
     */
    public static function set_disabled(\stdClass $creator, \stdClass $contact, bool $disabled): void {
        global $DB;

        if (($contact->status === 'disabled') === $disabled) {
            return;
        }
        $suspended = [];
        if ($contact->userid) {
            $enrolments = $DB->get_records_sql(
                "SELECT ue.id, ue.status, e.id AS enrolid, e.enrol, e.courseid
                   FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid JOIN {course} co ON co.id = e.courseid
                  WHERE ue.userid = ? AND co.category = ?", [$contact->userid, $creator->categoryid]);
            $restore = $disabled ? [] : (json_decode((string) $contact->suspendedenrolments, true) ?: []);
            foreach ($enrolments as $ue) {
                $plugin = enrol_get_plugin($ue->enrol);
                $instance = $DB->get_record('enrol', ['id' => $ue->enrolid]);
                if ($disabled && (int) $ue->status === ENROL_USER_ACTIVE) {
                    $plugin->update_user_enrol($instance, $contact->userid, ENROL_USER_SUSPENDED);
                    $suspended[] = (int) $ue->id;
                } else if (!$disabled && in_array((int) $ue->id, $restore, true)) {
                    $plugin->update_user_enrol($instance, $contact->userid, ENROL_USER_ACTIVE);
                }
            }
        }
        $DB->update_record(self::TABLE, (object) ['id' => $contact->id, 'status' => $disabled ? 'disabled' : 'active',
            'suspendedenrolments' => $disabled ? json_encode($suspended) : null, 'timemodified' => time()]);
    }

    /**
     * Delete contacts from the CRM (their account and accesses stay).
     *
     * @param \stdClass $creator Trainer.
     * @param int[] $contactids Contacts.
     */
    public static function delete(\stdClass $creator, array $contactids): void {
        global $DB;

        $ids = self::own_ids($creator, $contactids);
        if ($ids) {
            [$insql, $params] = $DB->get_in_or_equal($ids);
            $DB->delete_records_select(self::TAGS, "contactid $insql", $params);
            $DB->delete_records_select(self::TABLE, "id $insql", $params);
        }
    }

    // ----- Contact's page -------------------------------------------------------------------------------------------

    /**
     * The contact's accesses to the trainer's courses.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $contact Contact.
     * @return array of objects {course, timestart, suspended, progress}
     */
    public static function courses(\stdClass $creator, \stdClass $contact): array {
        global $DB;

        if (!$contact->userid) {
            return [];
        }
        $rows = $DB->get_records_sql(
            "SELECT co.id, co.fullname, co.shortname, MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END)
                    AS timestart, MAX(CASE WHEN ue.status = :active AND e.status = :enabled THEN 1 ELSE 0 END) AS active
               FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid JOIN {course} co ON co.id = e.courseid
              WHERE ue.userid = :userid AND co.category = :category
           GROUP BY co.id, co.fullname, co.shortname ORDER BY co.fullname",
            ['active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED, 'userid' => $contact->userid,
                'category' => $creator->categoryid]);
        foreach ($rows as $row) {
            $row->progress = \core_completion\progress::get_course_progress_percentage($row, (int) $contact->userid);
        }
        return array_values($rows);
    }

    /**
     * The contact's invoices and credit notes in the trainer's name, newest first.
     *
     * @param \stdClass $creator Trainer.
     * @param \stdClass $contact Contact.
     * @return array
     */
    public static function invoices(\stdClass $creator, \stdClass $contact): array {
        global $DB;

        if (!$contact->userid) {
            return [];
        }
        return $DB->get_records('local_oriskill_invoices', ['creatorid' => $creator->id, 'userid' => $contact->userid],
            'timecreated DESC, id DESC');
    }
}
