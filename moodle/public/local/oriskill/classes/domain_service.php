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
 * A trainer's own domains: one primary (their showcase, and the default address of their pages) and secondary ones.
 *
 * Every domain of a trainer opens their showcase, order forms, capture pages and course pages; each order form,
 * capture page and course can be given its own domain (e.g. courses on wsmacademy.com, order forms on
 * lionessclosing.com). The primary domain is also kept in creators.customdomain, read by older code.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class domain_service {

    /** Domains. */
    const TABLE = 'local_oriskill_domains';

    /** Domain chosen for a course page. */
    const COURSES = 'local_oriskill_coursedomains';

    /**
     * Domains of a trainer, the primary first.
     *
     * @param \stdClass $creator Trainer.
     * @return array id => record
     */
    public static function domains(\stdClass $creator): array {
        global $DB;
        return $DB->get_records(self::TABLE, ['creatorid' => $creator->id], 'isprimary DESC, domain ASC');
    }

    /**
     * Add a domain; the first one is the primary.
     *
     * @param \stdClass $creator Trainer.
     * @param string $domain Domain as typed.
     * @return \stdClass
     */
    public static function add(\stdClass $creator, string $domain): \stdClass {
        global $DB;

        $normalised = creator_service::normalise_domain($domain);
        $error = $normalised ? self::error($normalised) : 'error:domainshape';
        if ($error) {
            throw new \moodle_exception($error, 'local_oriskill');
        }
        $record = (object) ['creatorid' => $creator->id, 'domain' => $normalised,
            'isprimary' => $DB->record_exists(self::TABLE, ['creatorid' => $creator->id]) ? 0 : 1,
            'status' => 'pending', 'lastcheck' => 0, 'timeactive' => 0, 'timecreated' => time()];
        $record->id = $DB->insert_record(self::TABLE, $record);
        self::check($record);
        self::sync_primary($creator);
        return $DB->get_record(self::TABLE, ['id' => $record->id]);
    }

    /**
     * Why a domain cannot be added, or null if it can.
     *
     * @param string $domain Normalised domain.
     * @return string|null Language string key.
     */
    public static function error(string $domain): ?string {
        global $CFG, $DB;

        $site = preg_replace('/^www\./', '', strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST)));
        if ($domain === $site || str_ends_with($domain, '.' . $site)) {
            return 'error:domainsite';
        }
        if ($DB->record_exists(self::TABLE, ['domain' => $domain])) {
            return 'error:domaintaken';
        }
        return null;
    }

    /**
     * One of the trainer's domains.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Domain.
     * @return \stdClass
     */
    public static function require_own(\stdClass $creator, int $id): \stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $id, 'creatorid' => $creator->id], '*', MUST_EXIST);
    }

    /**
     * Make a domain the primary one.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Domain.
     */
    public static function set_primary(\stdClass $creator, int $id): void {
        global $DB;

        $domain = self::require_own($creator, $id);
        $DB->set_field(self::TABLE, 'isprimary', 0, ['creatorid' => $creator->id]);
        $DB->set_field(self::TABLE, 'isprimary', 1, ['id' => $domain->id]);
        self::sync_primary($creator);
    }

    /**
     * What uses a domain: order forms, capture pages and course pages given it.
     *
     * @param \stdClass $domain Domain.
     * @return array ['orderforms' => titles, 'capturepages' => titles, 'courses' => names]
     */
    public static function usage(\stdClass $domain): array {
        global $DB;

        return [
            'orderforms' => array_values($DB->get_fieldset_select('local_oriskill_orderforms', 'title', 'domainid = ?', [$domain->id])),
            'capturepages' => array_values($DB->get_fieldset_select('local_oriskill_capturepages', 'title', 'domainid = ?',
                [$domain->id])),
            'courses' => array_values($DB->get_fieldset_sql("SELECT c.fullname FROM {" . self::COURSES . "} cd
                                                               JOIN {course} c ON c.id = cd.courseid WHERE cd.domainid = ?",
                [$domain->id])),
        ];
    }

    /**
     * Remove a domain; what used it goes back to the primary domain. The primary goes last.
     *
     * @param \stdClass $creator Trainer.
     * @param int $id Domain.
     */
    public static function delete(\stdClass $creator, int $id): void {
        global $DB;

        $domain = self::require_own($creator, $id);
        if ($domain->isprimary && $DB->count_records(self::TABLE, ['creatorid' => $creator->id]) > 1) {
            throw new \moodle_exception('domains:primaryfirst', 'local_oriskill');
        }
        $DB->set_field('local_oriskill_orderforms', 'domainid', 0, ['domainid' => $domain->id]);
        $DB->set_field('local_oriskill_capturepages', 'domainid', 0, ['domainid' => $domain->id]);
        $DB->delete_records(self::COURSES, ['domainid' => $domain->id]);
        $DB->delete_records(self::TABLE, ['id' => $domain->id]);
        self::sync_primary($creator);
    }

    /**
     * Check whether a domain points to the server; its state follows.
     *
     * @param \stdClass $domain Domain.
     * @return \stdClass Updated record (with www: whether "www." points here too).
     */
    public static function check(\stdClass $domain): \stdClass {
        global $DB;

        $ip = creator_service::SERVER_IPV4;
        $here = in_array($ip, gethostbynamel($domain->domain) ?: [], true);
        $domain->www = in_array($ip, gethostbynamel('www.' . $domain->domain) ?: [], true);
        $update = (object) ['id' => $domain->id, 'status' => $here ? 'active' : 'pending', 'lastcheck' => time()];
        if ($here && !$domain->timeactive) {
            $update->timeactive = time();
        }
        $DB->update_record(self::TABLE, $update);
        foreach ((array) $update as $field => $value) {
            $domain->$field = $value;
        }
        return $domain;
    }

    /**
     * Keep the primary domain in creators.customdomain (read by the older pages).
     *
     * @param \stdClass $creator Trainer.
     */
    public static function sync_primary(\stdClass $creator): void {
        global $DB;

        $primary = $DB->get_field(self::TABLE, 'domain', ['creatorid' => $creator->id, 'isprimary' => 1]) ?: null;
        $DB->set_field('local_oriskill_creators', 'customdomain', $primary, ['id' => $creator->id]);
        $creator->customdomain = $primary;
    }

    /**
     * Trainer one of whose domains a request came through.
     *
     * @param string $host Host of the request.
     * @return \stdClass|null Active trainer.
     */
    public static function creator_by_domain(string $host): ?\stdClass {
        global $DB;

        $domain = creator_service::normalise_domain($host);
        if (!$domain) {
            return null;
        }
        return $DB->get_record_sql("SELECT c.* FROM {local_oriskill_creators} c JOIN {" . self::TABLE . "} d ON d.creatorid = c.id
                                     WHERE d.domain = ? AND c.status = 'active'", [$domain]) ?: null;
    }

    /**
     * Whether a host is one of a trainer's domains.
     *
     * @param \stdClass $creator Trainer.
     * @param string $host Host.
     * @return bool
     */
    public static function belongs(\stdClass $creator, string $host): bool {
        $found = self::creator_by_domain($host);
        return $found && (int) $found->id === (int) $creator->id;
    }

    /**
     * Host to build an address on: the domain given (if still the trainer's), else the primary one.
     *
     * @param \stdClass $creator Trainer.
     * @param int $domainid Domain given to the page (0 for none).
     * @return string|null Host, null when the trainer has no domain.
     */
    public static function host(\stdClass $creator, int $domainid = 0): ?string {
        global $DB;

        if ($domainid && ($domain = $DB->get_field(self::TABLE, 'domain', ['id' => $domainid, 'creatorid' => $creator->id]))) {
            return $domain;
        }
        return $creator->customdomain ?: null;
    }

    /**
     * Domain given to a course page (0 for the primary one).
     *
     * @param int $courseid Course.
     * @return int
     */
    public static function course_domainid(int $courseid): int {
        global $DB;
        return (int) $DB->get_field(self::COURSES, 'domainid', ['courseid' => $courseid]);
    }

    /**
     * Give a course page a domain (0 for the primary one).
     *
     * @param \stdClass $creator Trainer.
     * @param int $courseid Course of the trainer.
     * @param int $domainid Domain of the trainer, 0 for the primary.
     */
    public static function set_course_domain(\stdClass $creator, int $courseid, int $domainid): void {
        global $DB;

        learning_service::require_own_course($creator, $courseid);
        if ($domainid) {
            self::require_own($creator, $domainid);
        }
        $DB->delete_records(self::COURSES, ['courseid' => $courseid]);
        if ($domainid) {
            $DB->insert_record(self::COURSES, (object) ['courseid' => $courseid, 'domainid' => $domainid]);
        }
    }

    /**
     * Domains to choose from in a form: 0 for the primary, then each domain.
     *
     * @param \stdClass $creator Trainer.
     * @return array id => label
     */
    public static function options(\stdClass $creator): array {
        $options = [0 => get_string('domains:useprimary', 'local_oriskill', $creator->customdomain ?: 'oriskill.com')];
        foreach (self::domains($creator) as $domain) {
            if (!$domain->isprimary) {
                $options[$domain->id] = $domain->domain;
            }
        }
        return $options;
    }
}
