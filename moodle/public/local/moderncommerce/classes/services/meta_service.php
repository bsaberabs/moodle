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
 * Meta Service for Modern Commerce
 *
 * Provides read-only accessors for canonical course catalog metadata.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class meta_service {
    /**
     * Retrieve full meta record for a course.
     *
     * @param int $courseid
     * @return ?\stdClass Null if no record exists; otherwise typed fields.
     */
    public static function get_course_meta(int $courseid): ?\stdClass {

        global $DB;
        if ($courseid <= 0) {
            return null;
        }

        $fields = 'courseid, durationminutes, skilllevel, language, passgrade, certificateenabled, overview, ' .
            'featured, bestseller, trending';
        $record = $DB->get_record('local_moderncommerce_course_meta', ['courseid' => $courseid], $fields, IGNORE_MISSING);
        if (!$record) {
            return null;
        }

        $durationminutes = isset($record->durationminutes) ? max(0, (int)$record->durationminutes) : 0;
        $customoutline = $DB->record_exists('local_moderncommerce_course_outline', ['courseid' => $courseid]);
        // Normalize canonical fields to the legacy property names used by existing templates.
        $meta = new \stdClass();
        $meta->courseid = (int)$record->courseid;
        $meta->duration_hours = intdiv($durationminutes, 60);
        $meta->duration_minutes = $durationminutes % 60;
        $meta->durationminutes = $durationminutes;
        $meta->skill_level = isset($record->skilllevel) && $record->skilllevel !== '' ? (string)$record->skilllevel : null;
        $meta->skilllevel = $meta->skill_level;
        $meta->language = isset($record->language) && $record->language !== '' ? (string)$record->language : null;
        $meta->quizzes_count = self::count_quizzes($courseid);
        $meta->cert_enabled = !empty($record->certificateenabled);
        $meta->certificateenabled = $meta->cert_enabled;
        $meta->pass_grade = isset($record->passgrade) && $record->passgrade !== '' ? (float)$record->passgrade : null;
        $meta->passgrade = $meta->pass_grade;
        $meta->overview_autogen = empty($record->overview);
        $meta->overview_text = isset($record->overview) && $record->overview !== '' ? (string)$record->overview : null;
        $meta->overview = $meta->overview_text;
        $meta->outline_autogen = !$customoutline;
        $meta->meta_downloadable_resources = false;
        $meta->meta_prerequisites = false;
        $meta->meta_featured_course = !empty($record->featured);
        $meta->meta_bestseller_badge = !empty($record->bestseller);
        $meta->meta_trending_badge = !empty($record->trending);
        return $meta;
    }
    /**
     * Get pass grade from meta.
     *
     * @param int $courseid
     * @return ?float Null if not set or record missing.
     */
    public static function get_pass_grade(int $courseid): ?float {
        $meta = self::get_course_meta($courseid);
        if (!$meta) {
            return null;
        }
        return $meta->pass_grade ?? null;
    }

    /**
     * Get duration parts from meta.
     *
     * @param int $courseid
     * @return ?array Array ['hours' => int, 'minutes' => int] or null if not set/record missing.
     */
    public static function get_duration(int $courseid): ?array {
        $meta = self::get_course_meta($courseid);
        if (!$meta) {
            return null;
        }
        $hours = (int)$meta->duration_hours;
        $minutes = (int)$meta->duration_minutes;
        if ($hours === 0 && $minutes === 0) {
            return null;
        }
        return ['hours' => $hours, 'minutes' => $minutes];
    }

    /**
     * Format duration like "1 hr 30 mins".
     *
     * @param int $hours
     * @param int $minutes
     * @return string
     */
    public static function format_duration(int $hours, int $minutes): string {
        $hours = max(0, (int)$hours);
        $minutes = max(0, (int)$minutes);
        if ($minutes >= 60) {
            $hours += intdiv($minutes, 60);
            $minutes = $minutes % 60;
        }
        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . ' hr' . ($hours === 1 ? '' : 's');
        }
        if ($minutes > 0 || empty($parts)) {
            $parts[] = $minutes . ' min' . ($minutes === 1 ? '' : 's');
        }
        return implode(' ', $parts);
    }

    /**
     * Get boolean flags from meta.
     *
     * @param int $courseid
     * @return array Boolean meta flags.
     */
    public static function get_flags(int $courseid): array {
        $meta = self::get_course_meta($courseid);
        if (!$meta) {
            return [];
        }
        return [
            'cert_enabled' => (bool)$meta->cert_enabled,
            'overview_autogen' => (bool)$meta->overview_autogen,
            'outline_autogen' => (bool)$meta->outline_autogen,
            'meta_downloadable_resources' => (bool)$meta->meta_downloadable_resources,
            'meta_prerequisites' => (bool)$meta->meta_prerequisites,
            'meta_featured_course' => (bool)$meta->meta_featured_course,
            'meta_bestseller_badge' => (bool)$meta->meta_bestseller_badge,
            'meta_trending_badge' => (bool)$meta->meta_trending_badge,
        ];
    }

    /**
     * Get overview details.
     *
     * @param int $courseid
     * @return array ['autogen' => bool, 'text' => ?string]
     */
    public static function get_overview(int $courseid): array {
        $meta = self::get_course_meta($courseid);
        if (!$meta) {
            return ['autogen' => false, 'text' => null];
        }
        // If autogen is enabled, fetch summary from core course table.
        if (!empty($meta->overview_autogen)) {
            global $DB;
            $course = $DB->get_record('course', ['id' => $courseid], 'summary', IGNORE_MISSING);
            $summary = $course && isset($course->summary) ? (string)$course->summary : null;
            return ['autogen' => true, 'text' => $summary];
        }
        return ['autogen' => false, 'text' => $meta->overview_text];
    }

    /**
     * Get quizzes count.
     *
     * @param int $courseid
     * @return int
     */
    public static function get_quizzes_count(int $courseid): int {

        $meta = self::get_course_meta($courseid);
        return $meta ? (int)$meta->quizzes_count : self::count_quizzes($courseid);
    }
    /**
     * Get language and skill level.
     *
     * @param int $courseid
     * @return array ['language' => ?string, 'skill_level' => ?string]
     */
    public static function get_language_skill(int $courseid): array {
        $meta = self::get_course_meta($courseid);
        if (!$meta) {
            return ['language' => null, 'skill_level' => null];
        }
        return ['language' => $meta->language, 'skill_level' => $meta->skill_level];
    }

    /**
     * Count quiz modules from Moodle course data.
     *
     * @param int $courseid Course ID.
     * @return int
     */
    private static function count_quizzes(int $courseid): int {

        global $DB;
        if ($courseid <= 0) {
            return 0;
        }

        return (int)$DB->count_records_sql("SELECT COUNT(1)
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :courseid
                AND m.name = :modname
                AND cm.deletioninprogress = 0", ['courseid' => $courseid, 'modname' => 'quiz']);
    }

    // Pricing lives in the product price tables and is exposed by pricing_service.
}
