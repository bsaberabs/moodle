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
 * Notifications of a course, written by the trainer: a module opens (X days after enrolment or on a date), progress
 * reminder X days after enrolment, login reminder after X days without coming.
 *
 * @package    local_oriskill
 * @copyright  2026 Advanced TM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_notifications {

    /** Settings, one row per course and kind. */
    const TABLE = 'local_oriskill_coursenotifs';

    /** Notifications sent. */
    const SENT = 'local_oriskill_notifsent';

    /** Kinds of notification. */
    const KINDS = ['unlock', 'progress', 'inactive'];

    /** A notification due longer ago than this is no longer sent (the cron was down, or it was just turned on). */
    const LATE = 2 * DAYSECS;

    /** Variables of the subject and content. */
    const PLACEHOLDERS = ['[[PRENOM]]', '[[NOM]]', '[[EMAIL]]', '[[FORMATION]]', '[[URL_FORMATION]]', '[[MODULE]]', '[[URL_MODULE]]',
        '[[PROGRESSION]]', '[[JOURS]]', '[[SITE_NAME]]'];

    /**
     * Settings of a course, kind => record (defaults for the kinds not saved yet).
     *
     * @param \stdClass $course Course.
     * @return \stdClass[]
     */
    public static function settings(\stdClass $course): array {
        global $DB;

        $saved = [];
        foreach ($DB->get_records(self::TABLE, ['courseid' => $course->id]) as $record) {
            $saved[$record->kind] = $record;
        }
        $settings = [];
        foreach (self::KINDS as $kind) {
            $settings[$kind] = $saved[$kind] ?? (object) ['id' => 0, 'courseid' => $course->id, 'kind' => $kind, 'enabled' => 0,
                'days' => $kind === 'progress' ? 7 : ($kind === 'inactive' ? 14 : 0), 'hour' => 9,
                'subject' => get_string('notifs:defaultsubject_' . $kind, 'local_oriskill'),
                'body' => get_string('notifs:defaultbody_' . $kind, 'local_oriskill'), 'bodyformat' => FORMAT_HTML];
        }
        return $settings;
    }

    /**
     * Save the settings of a course from the form.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $data Form data (enabled_<kind>, days_<kind>, hour_<kind>, subject_<kind>, body_<kind>).
     */
    public static function save(\stdClass $course, \stdClass $data): void {
        global $DB;

        foreach (self::KINDS as $kind) {
            $record = (object) ['courseid' => $course->id, 'kind' => $kind,
                'enabled' => empty($data->{'enabled_' . $kind}) ? 0 : 1,
                'days' => max(1, min(365, (int) ($data->{'days_' . $kind} ?? 1))),
                'hour' => max(0, min(23, (int) ($data->{'hour_' . $kind} ?? 9))),
                'subject' => \core_text::substr(trim((string) $data->{'subject_' . $kind}), 0, 255),
                'body' => (string) $data->{'body_' . $kind}['text'], 'bodyformat' => (int) $data->{'body_' . $kind}['format'],
                'timemodified' => time()];
            if ($id = $DB->get_field(self::TABLE, 'id', ['courseid' => $course->id, 'kind' => $kind])) {
                $record->id = $id;
                $DB->update_record(self::TABLE, $record);
            } else {
                $DB->insert_record(self::TABLE, $record);
            }
        }
    }

    /**
     * Send the notifications due in every course (hourly task).
     *
     * @param int|null $now Time (tests).
     * @return int Notifications sent.
     */
    public static function run(?int $now = null): int {
        global $DB;

        $now = $now ?? \core\di::get(\core\clock::class)->time();
        $sent = 0;
        foreach ($DB->get_fieldset_sql('SELECT DISTINCT courseid FROM {' . self::TABLE . '} WHERE enabled = 1') as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            $creator = $course ? seller_service::owner_of_course((int) $courseid) : null;
            if ($course && $creator) {
                $sent += self::run_course($course, $creator, $now);
            }
        }
        return $sent;
    }

    /**
     * Send the notifications due in a course.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $creator Its trainer.
     * @param int $now Time.
     * @return int Notifications sent.
     */
    public static function run_course(\stdClass $course, \stdClass $creator, int $now): int {
        $settings = array_filter(self::settings($course), fn($s) => $s->enabled);
        if (!$settings) {
            return 0;
        }
        $rules = isset($settings['unlock']) ? learning_service::section_rules($course) : [];
        $owner = \core_user::get_user($creator->userid);
        $timezone = \core_date::get_user_timezone($owner ?: null);
        $due = fn(int $time) => $now >= $time && $now - $time <= self::LATE;
        $sent = 0;
        foreach (learning_service::learners($creator, $course) as $learner) {
            if ($learner->suspended || $learner->expired || $learner->id == $creator->userid) {
                continue;
            }
            $values = ['[[PROGRESSION]]' => $learner->progress === null ? '–' : (int) round($learner->progress)];
            $done = $learner->completed || ($learner->progress !== null && $learner->progress >= 100);

            // Modules that open on a date or X days after enrolment, at the hour chosen, that day.
            if (isset($settings['unlock'])) {
                $groupids = array_map(fn($g) => (int) $g->id, $learner->groups);
                foreach ($rules as $rule) {
                    if (!empty($rule->custom) || !in_array($rule->timing, ['date', 'days'], true)
                            || ($rule->groups && !array_intersect($rule->groups, $groupids))) {
                        continue;
                    }
                    $open = $rule->timing === 'date' ? (int) $rule->date : $learner->timestart + $rule->days * DAYSECS;
                    $sendat = max($open, usergetmidnight($open, $timezone) + $settings['unlock']->hour * HOURSECS);
                    if ($open > $learner->timestart && $due($sendat)) {
                        $sent += (int) self::send_once($course, $creator, $learner->id, $settings['unlock'], (int) $rule->section->id,
                            $values + ['[[MODULE]]' => $rule->name, '[[URL_MODULE]]' => (new \moodle_url('/course/section.php',
                                ['id' => $rule->section->id]))->out(false), '[[JOURS]]' => $rule->timing === 'days' ? $rule->days : '']);
                    }
                }
            }
            // X days after enrolment, if not finished.
            if (isset($settings['progress']) && !$done
                    && $due($learner->timestart + $settings['progress']->days * DAYSECS)) {
                $sent += (int) self::send_once($course, $creator, $learner->id, $settings['progress'], 0,
                    $values + ['[[JOURS]]' => $settings['progress']->days]);
            }
            // X days without coming to the course: once per absence (the reference is the last visit).
            $last = max($learner->lastaccess, $learner->timestart);
            if (isset($settings['inactive']) && !$done && $due($last + $settings['inactive']->days * DAYSECS)) {
                $sent += (int) self::send_once($course, $creator, $learner->id, $settings['inactive'], $last,
                    $values + ['[[JOURS]]' => $settings['inactive']->days]);
            }
        }
        return $sent;
    }

    /**
     * Send a notification unless it was already sent.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $creator Trainer.
     * @param int $userid Learner.
     * @param \stdClass $setting Setting of the kind.
     * @param int $ref Module (section id), start of the absence, or 0.
     * @param array $values Values of the variables.
     * @return bool Sent.
     */
    protected static function send_once(\stdClass $course, \stdClass $creator, int $userid, \stdClass $setting, int $ref,
            array $values): bool {
        global $DB;

        $key = ['courseid' => $course->id, 'userid' => $userid, 'kind' => $setting->kind, 'ref' => $ref];
        $user = \core_user::get_user($userid);
        if ($DB->record_exists(self::SENT, $key) || !$user || $user->deleted || $user->suspended) {
            return false;
        }
        $DB->insert_record(self::SENT, (object) ($key + ['timecreated' => time()]));
        try {
            return self::send($course, $creator, $user, $setting, $values);
        } catch (\Throwable $e) {
            debugging('Course notification: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Send a notification to someone.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $creator Trainer.
     * @param \stdClass $user Recipient.
     * @param \stdClass $setting Setting of the kind.
     * @param array $values Values of the variables of the kind (module, progress, days).
     * @return bool Sent.
     */
    public static function send(\stdClass $course, \stdClass $creator, \stdClass $user, \stdClass $setting, array $values): bool {
        global $DB;

        $values += [
            '[[PRENOM]]' => $user->firstname, '[[NOM]]' => $user->lastname, '[[EMAIL]]' => $user->email,
            '[[FORMATION]]' => format_string($course->fullname),
            '[[URL_FORMATION]]' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            '[[MODULE]]' => '', '[[URL_MODULE]]' => '', '[[PROGRESSION]]' => '', '[[JOURS]]' => '',
            '[[SITE_NAME]]' => format_string($creator->displayname),
        ];
        $values = array_map('strval', $values);
        $subject = strtr($setting->subject, $values);
        $html = format_text(strtr($setting->body, array_map('s', $values)), $setting->bodyformat,
            ['context' => \context_course::instance($course->id), 'noclean' => false, 'para' => false]);
        $from = \core_user::get_noreply_user();
        $from->firstname = format_string($creator->displayname);
        $from->lastname = '';
        $replyto = (string) $DB->get_field('user', 'email', ['id' => $creator->userid]);
        return (bool) email_log::for_creator((int) $creator->id, 'transactional', fn() => email_to_user($user, $from, $subject,
            html_to_text($html), $html, '', '', true, $replyto, $from->firstname));
    }

    /**
     * Send the trainer an example of each notification turned on (the first module of the course, their own progress).
     *
     * @param \stdClass $course Course.
     * @param \stdClass $creator Trainer.
     * @param \stdClass $user Recipient.
     * @return int Sent.
     */
    public static function send_tests(\stdClass $course, \stdClass $creator, \stdClass $user): int {
        $rules = learning_service::section_rules($course);
        $rule = reset($rules);
        $sent = 0;
        foreach (self::settings($course) as $kind => $setting) {
            if (!$setting->enabled) {
                continue;
            }
            $values = ['[[PROGRESSION]]' => 40, '[[JOURS]]' => $kind === 'unlock' ? 7 : $setting->days];
            if ($rule) {
                $values += ['[[MODULE]]' => $rule->name,
                    '[[URL_MODULE]]' => (new \moodle_url('/course/section.php', ['id' => $rule->section->id]))->out(false)];
            }
            $sent += (int) self::send($course, $creator, $user, $setting, $values);
        }
        return $sent;
    }

    /**
     * What a course sends, in words (for the log).
     *
     * @param \stdClass $course Course.
     * @return array label => state
     */
    public static function summary(\stdClass $course): array {
        $summary = [];
        foreach (self::settings($course) as $kind => $setting) {
            $summary[get_string('notifs:' . $kind, 'local_oriskill')] = !$setting->enabled ? get_string('no')
                : get_string('notifs:summary_' . $kind, 'local_oriskill', $setting);
        }
        return $summary;
    }
}
