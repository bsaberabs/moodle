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

namespace local_moderncommerce\services;

/**
 * ModernCommerce enrolment helper.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_service {
    /**
     * Enrol a user into a course through the enrol_moderncommerce plugin.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param array $options Optional orderid, enrollkeyid, and method.
     * @return \stdClass Result data.
     */
    public static function enrol_user_in_course($userid, $courseid, array $options = []) {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $plugin = self::resolve_enrol_plugin();

        $instance = self::get_or_create_instance($plugin, $course);
        $times = self::calculate_enrolment_times($instance);
        $roleid = self::resolve_roleid($plugin, $instance);

        $ue = $DB->get_record('user_enrolments', [
            'enrolid' => $instance->id,
            'userid' => $userid,
        ]);

        $created = false;
        if (!$ue) {
            $plugin->enrol_user(
                $instance,
                $userid,
                $roleid ?: null,
                $times->timestart,
                $times->timeend,
                ENROL_USER_ACTIVE
            );
            $ue = $DB->get_record('user_enrolments', [
                'enrolid' => $instance->id,
                'userid' => $userid,
            ], '*', MUST_EXIST);
            $created = true;
        } else {
            $resolvedtimeend = self::resolve_existing_timeend($ue, $times->timeend);
            $needsupdate = (int)$ue->status !== ENROL_USER_ACTIVE ||
                (int)$ue->timestart !== (int)$times->timestart ||
                (int)$ue->timeend !== (int)$resolvedtimeend;

            if (!$needsupdate) {
                self::upsert_entitlement_record($userid, $courseid, $instance, $ue, $times, $options);

                return (object)[
                    'course' => $course,
                    'instance' => $instance,
                    'userenrolment' => $ue,
                    'created' => $created,
                ];
            }

            $ue->status = ENROL_USER_ACTIVE;
            $ue->timestart = $times->timestart;
            $ue->timeend = $resolvedtimeend;
            $ue->timemodified = time();
            $DB->update_record('user_enrolments', $ue);
        }

        self::upsert_entitlement_record($userid, $courseid, $instance, $ue, $times, $options);

        return (object)[
            'course' => $course,
            'instance' => $instance,
            'userenrolment' => $ue,
            'created' => $created,
        ];
    }

    /**
     * Unenrol a user from a moderncommerce enrolment instance.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $orderid Optional order ID for tracking.
     * @return bool True when an enrolment instance was found.
     */
    public static function unenrol_user_from_course($userid, $courseid, $orderid = 0) {
        global $DB;

        $entitlement = null;
        if ($orderid) {
            $entitlement = $DB->get_record('local_moderncommerce_entitlements', [
                'userid' => $userid,
                'courseid' => $courseid,
                'orderid' => $orderid,
                'status' => 'active',
            ], '*', IGNORE_MULTIPLE);
        }

        if (!$entitlement) {
            return false;
        }

        $instance = null;
        if (!empty($entitlement->enrolinstanceid)) {
            $instance = $DB->get_record('enrol', ['id' => $entitlement->enrolinstanceid]);
        }

        if (!$instance) {
            return false;
        }

        $plugin = enrol_get_plugin($instance->enrol);
        if (!$plugin) {
            return false;
        }

        if (!self::has_other_active_entitlements((int)$userid, (int)$courseid, (int)$entitlement->id)) {
            $plugin->unenrol_user($instance, $userid);
        }

        $oldstatus = $entitlement->status;
        $entitlement->status = 'revoked';
        $entitlement->timerevoked = time();
        $entitlement->revokereason = 'unenrolled';
        $entitlement->timemodified = time();
        $DB->update_record('local_moderncommerce_entitlements', $entitlement);
        self::record_entitlement_event($entitlement, 'revoked', $oldstatus, 'unenrolled');

        return true;
    }

    /**
     * Resolve the enrolment plugin Modern Commerce should use for access grants.
     *
     * @return \enrol_plugin Enrolment plugin.
     */
    private static function resolve_enrol_plugin(): \enrol_plugin {
        $preferred = enrol_get_plugin('moderncommerce');
        if ($preferred && enrol_is_enabled('moderncommerce')) {
            return $preferred;
        }

        $manual = enrol_get_plugin('manual');
        if ($manual && enrol_is_enabled('manual')) {
            return $manual;
        }

        throw new \moodle_exception('fulfillmentenrolpluginmissing', 'local_moderncommerce');
    }

    /**
     * Get or create the ModernCommerce enrolment instance for a course.
     *
     * @param \enrol_plugin $plugin Enrol plugin.
     * @param \stdClass $course Course record.
     * @return \stdClass Enrol instance.
     */
    private static function get_or_create_instance(\enrol_plugin $plugin, \stdClass $course) {
        global $DB;

        $name = $plugin->get_name();
        $instance = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol' => $name,
        ], '*', IGNORE_MULTIPLE);

        if ($instance) {
            if ((int)$instance->status !== ENROL_INSTANCE_ENABLED) {
                $instance->status = ENROL_INSTANCE_ENABLED;
                $instance->timemodified = time();
                $DB->update_record('enrol', $instance);
            }
            return $instance;
        }

        $instanceid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => self::resolve_default_roleid($plugin),
            'enrolperiod' => (int)$plugin->get_config('enrolperiod', 0),
            'expirynotify' => (int)$plugin->get_config('expirynotify', 0),
            'expirythreshold' => (int)$plugin->get_config('expirythreshold', 86400),
            'customint1' => (int)$plugin->get_config('sendcoursewelcomemessage', ENROL_SEND_EMAIL_FROM_COURSE_CONTACT),
            'customint2' => (int)$plugin->get_config('welcome_email_template', 0),
        ]);

        if (!$instanceid) {
            throw new \moodle_exception('invaliddata', 'error', '', 'Could not create commerce enrolment instance.');
        }

        return $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }

    /**
     * Check whether another active commerce entitlement still grants this course.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $ignoredentitlementid Current entitlement ID.
     * @return bool True when another active grant exists.
     */
    private static function has_other_active_entitlements(int $userid, int $courseid, int $ignoredentitlementid): bool {
        global $DB;

        $sql = "SELECT COUNT(1)
                  FROM {local_moderncommerce_entitlements}
                 WHERE userid = :userid
                   AND courseid = :courseid
                   AND status = :status
                   AND id <> :entitlementid";

        return $DB->count_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'status' => 'active',
            'entitlementid' => $ignoredentitlementid,
        ]) > 0;
    }

    /**
     * Resolve role ID from instance/plugin/student role.
     *
     * @param \enrol_plugin $plugin Enrol plugin.
     * @param \stdClass $instance Enrol instance.
     * @return int Role ID.
     */
    private static function resolve_roleid(\enrol_plugin $plugin, \stdClass $instance) {
        if (!empty($instance->roleid)) {
            return (int)$instance->roleid;
        }

        return self::resolve_default_roleid($plugin);
    }

    /**
     * Resolve default role ID.
     *
     * @param \enrol_plugin $plugin Enrol plugin.
     * @return int Role ID.
     */
    private static function resolve_default_roleid(\enrol_plugin $plugin) {
        global $DB;

        $roleid = (int)$plugin->get_config('roleid', 0);
        if ($roleid) {
            return $roleid;
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        return $studentrole ? (int)$studentrole->id : 0;
    }

    /**
     * Calculate enrolment start and end dates from an enrol instance.
     *
     * @param \stdClass $instance Enrol instance.
     * @return \stdClass Time data.
     */
    private static function calculate_enrolment_times(\stdClass $instance) {
        $timestart = time();
        if (!empty($instance->enrolstartdate) && (int)$instance->enrolstartdate > $timestart) {
            $timestart = (int)$instance->enrolstartdate;
        }

        $timeend = 0;
        if (!empty($instance->enrolperiod)) {
            $timeend = $timestart + (int)$instance->enrolperiod;
        }

        if (!empty($instance->enrolenddate)) {
            $instanceend = (int)$instance->enrolenddate;
            $timeend = $timeend ? min($timeend, $instanceend) : $instanceend;
        }

        return (object)[
            'timestart' => $timestart,
            'timeend' => $timeend,
        ];
    }

    /**
     * Avoid shortening an existing unlimited or longer enrolment.
     *
     * @param \stdClass $ue User enrolment record.
     * @param int $newtimeend New calculated end date.
     * @return int Time end.
     */
    private static function resolve_existing_timeend(\stdClass $ue, $newtimeend) {
        if (empty($newtimeend) || empty($ue->timeend)) {
            return 0;
        }

        return max((int)$ue->timeend, (int)$newtimeend);
    }

    /**
     * Insert or update the commerce entitlement backing a Moodle enrolment.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param \stdClass $instance Enrol instance.
     * @param \stdClass $ue User enrolment.
     * @param \stdClass $times Time data.
     * @param array $options Tracking options.
     */
    private static function upsert_entitlement_record(
        $userid,
        $courseid,
        \stdClass $instance,
        \stdClass $ue,
        \stdClass $times,
        array $options
    ) {
        global $DB;

        $orderid = (int)($options['orderid'] ?? 0);
        $enrollkeyid = !empty($options['enrollkeyid']) ? (int)$options['enrollkeyid'] : 0;
        if ($orderid <= 0 && $enrollkeyid <= 0) {
            return;
        }

        $lineage = self::resolve_entitlement_lineage($orderid, $courseid);
        $source = $options['method'] ?? ($enrollkeyid ? 'enrollkey' : 'purchase');
        $sourcekey = self::make_entitlement_sourcekey($userid, $courseid, $orderid, $enrollkeyid, $lineage, $source);

        $record = $DB->get_record('local_moderncommerce_entitlements', ['sourcekey' => $sourcekey]);

        if (!$record) {
            $record = new \stdClass();
            $record->sourcekey = $sourcekey;
            $record->userid = $userid;
            $record->courseid = $courseid;
            $record->orderid = $orderid ?: null;
            $record->timecreated = time();
        }

        $oldstatus = $record->status ?? null;
        $record->productid = $lineage->productid;
        $record->orderitemid = $lineage->orderitemid;
        $record->enrollkeyid = $enrollkeyid ?: null;
        $record->userenrolmentid = $ue->id;
        $record->enrolinstanceid = $instance->id;
        $record->entitlementtype = 'course_access';
        $record->source = $source;
        $record->status = 'active';
        $record->grantreason = $source;
        $record->timestart = $times->timestart;
        $record->timeend = $times->timeend ?: null;
        $record->timegranted = !empty($record->timegranted) ? (int)$record->timegranted : time();
        $record->timerevoked = null;
        $record->timeexpired = null;
        $record->revokereason = null;
        $record->metadata = self::encode_metadata([
            'method' => $source,
            'orderid' => $orderid ?: null,
            'orderitemid' => $lineage->orderitemid,
            'productid' => $lineage->productid,
            'enrollkeyid' => $enrollkeyid ?: null,
        ]);
        $record->timemodified = time();

        if (!empty($record->id)) {
            $DB->update_record('local_moderncommerce_entitlements', $record);
            if ($oldstatus !== 'active') {
                self::record_entitlement_event($record, 'reactivated', $oldstatus, $source);
            }
        } else {
            $record->id = $DB->insert_record('local_moderncommerce_entitlements', $record);
            self::record_entitlement_event($record, 'granted', null, $source);
        }
    }

    /**
     * Resolve product/order item lineage for a granted course entitlement.
     *
     * @param int $orderid Order ID.
     * @param int $courseid Course ID.
     * @return \stdClass Lineage data.
     */
    private static function resolve_entitlement_lineage(int $orderid, int $courseid): \stdClass {
        global $DB;

        $lineage = (object)[
            'productid' => null,
            'orderitemid' => null,
        ];

        if ($orderid <= 0) {
            return $lineage;
        }

        $item = $DB->get_record(
            'local_moderncommerce_order_items',
            ['orderid' => $orderid, 'courseid' => $courseid],
            '*',
            IGNORE_MULTIPLE
        );

        if ($item) {
            $lineage->productid = !empty($item->productid) ? (int)$item->productid : null;
            $lineage->orderitemid = (int)$item->id;
        }

        return $lineage;
    }

    /**
     * Build a stable idempotency key for one entitlement grant.
     *
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @param int $orderid Order ID.
     * @param int $enrollkeyid Enrolment key ID.
     * @param \stdClass $lineage Order line lineage.
     * @param string $source Grant source.
     * @return string Source key.
     */
    private static function make_entitlement_sourcekey(
        int $userid,
        int $courseid,
        int $orderid,
        int $enrollkeyid,
        \stdClass $lineage,
        string $source
    ): string {
        if (!empty($lineage->orderitemid)) {
            return 'orderitem:' . $lineage->orderitemid . ':course:' . $courseid;
        }

        if ($orderid > 0) {
            return 'order:' . $orderid . ':course:' . $courseid . ':source:' . $source;
        }

        if ($enrollkeyid > 0) {
            return 'enrollkey:' . $enrollkeyid . ':user:' . $userid . ':course:' . $courseid;
        }

        return 'manual:' . $userid . ':course:' . $courseid . ':source:' . $source;
    }

    /**
     * Insert an entitlement lifecycle event.
     *
     * @param \stdClass $entitlement Entitlement record.
     * @param string $eventtype Event type.
     * @param string|null $oldstatus Previous status.
     * @param string|null $reason Event reason.
     */
    private static function record_entitlement_event(
        \stdClass $entitlement,
        string $eventtype,
        ?string $oldstatus,
        ?string $reason
    ): void {
        global $DB, $USER;

        $event = new \stdClass();
        $event->entitlementid = $entitlement->id;
        $event->eventuuid = bin2hex(random_bytes(16));
        $event->eventtype = $eventtype;
        $event->oldstatus = $oldstatus;
        $event->newstatus = $entitlement->status ?? null;
        $event->actoruserid = !empty($USER->id) ? (int)$USER->id : null;
        $event->source = 'system';
        $event->reason = $reason;
        $event->correlationid = $entitlement->sourcekey ?? null;
        $event->eventdata = self::encode_metadata([
            'userid' => $entitlement->userid ?? null,
            'courseid' => $entitlement->courseid ?? null,
            'productid' => $entitlement->productid ?? null,
            'orderid' => $entitlement->orderid ?? null,
            'orderitemid' => $entitlement->orderitemid ?? null,
        ]);
        $event->timecreated = time();

        $DB->insert_record('local_moderncommerce_entitlement_events', $event);
    }

    /**
     * Encode metadata without throwing from the commerce transaction path.
     *
     * @param array $metadata Metadata.
     * @return string|null Encoded metadata.
     */
    private static function encode_metadata(array $metadata): ?string {
        $json = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}
