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

/**
 * Core Modern Commerce course review service.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\services;

/**
 * Business logic for course reviews and reactions.
 */
class review_service {
    /** @var string Core reviews table. */
    public const REVIEWS_TABLE = 'local_moderncommerce_reviews';

    /** @var string Core review reactions table. */
    public const REACTIONS_TABLE = 'local_moderncommerce_review_rxn';

    /**
     * Check whether course reviews are enabled.
     *
     * @return bool True when reviews should be visible and writable.
     */
    public static function reviews_enabled(): bool {
        $value = get_config('local_moderncommerce', 'reviews_enabled');
        return $value === false || $value === null || $value === '' || (int)$value === 1;
    }

    /**
     * Check whether a learner may submit a review for this course.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return bool True when the user has verified course access and the submit capability.
     */
    public static function user_can_submit_review(int $courseid, int $userid): bool {
        if (!self::reviews_enabled() || $courseid <= 0 || $userid <= 0 || self::is_guest_user($userid)) {
            return false;
        }

        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || !has_capability('local/moderncommerce:submitreview', $context, $userid)) {
            return false;
        }

        return access_service::user_has_course_access($userid, $courseid);
    }

    /**
     * Add or update a review for a course by a user.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param int $rating Rating from 1 to 5.
     * @param string $comment Review body.
     */
    public static function upsert_review(int $courseid, int $userid, int $rating, string $comment): void {
        global $DB;

        if ($rating < 1 || $rating > 5) {
            throw new \moodle_exception('error:invalidrating', 'local_moderncommerce');
        }

        $now = time();
        $record = $DB->get_record(self::REVIEWS_TABLE, [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        if ($record) {
            $record->rating = $rating;
            $record->comment = $comment;
            $record->hidden = 0;
            $record->timemodified = $now;
            $DB->update_record(self::REVIEWS_TABLE, $record);
            return;
        }

        $record = (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'rating' => $rating,
            'comment' => $comment,
            'hidden' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record(self::REVIEWS_TABLE, $record);
    }

    /**
     * Get aggregated visible rating summary for a course.
     *
     * @param int $courseid Course ID.
     * @return array Review count and average rating.
     */
    public static function get_course_summary(int $courseid): array {
        global $DB;

        if (!self::reviews_enabled()) {
            return [
                'reviewcount' => 0,
                'avgrating' => 0.0,
            ];
        }

        $sql = "SELECT COUNT(1) AS reviewcount, AVG(rating) AS avgrating
                  FROM {" . self::REVIEWS_TABLE . "}
                 WHERE courseid = :courseid AND hidden = 0";
        $summary = $DB->get_record_sql($sql, ['courseid' => $courseid]);

        return [
            'reviewcount' => (int)($summary->reviewcount ?? 0),
            'avgrating' => $summary && $summary->reviewcount ? round((float)$summary->avgrating, 2) : 0.0,
        ];
    }

    /**
     * Get visible summaries for many courses.
     *
     * @param array $courseids Course IDs.
     * @return array Map course ID to summary.
     */
    public static function get_course_summaries(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if (!self::reviews_enabled() || empty($courseids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $records = $DB->get_records_sql(
            "SELECT courseid, COUNT(1) AS reviewcount, AVG(rating) AS avgrating
               FROM {" . self::REVIEWS_TABLE . "}
              WHERE hidden = 0 AND courseid {$insql}
           GROUP BY courseid",
            $params
        );

        $summaries = [];
        foreach ($records as $record) {
            $summaries[(int)$record->courseid] = [
                'reviewcount' => (int)$record->reviewcount,
                'avgrating' => $record->reviewcount ? round((float)$record->avgrating, 2) : 0.0,
            ];
        }

        return $summaries;
    }

    /**
     * Get a combined rating summary across several courses.
     *
     * Used for bundle/program cards: the average is weighted by review (one row per review)
     * across every member course, which is the correct aggregate for a multi-course product.
     *
     * @param array $courseids Member course IDs.
     * @return array Combined review count and average rating.
     */
    public static function get_aggregate_summary(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if (!self::reviews_enabled() || empty($courseids)) {
            return [
                'reviewcount' => 0,
                'avgrating' => 0.0,
            ];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $summary = $DB->get_record_sql(
            "SELECT COUNT(1) AS reviewcount, AVG(rating) AS avgrating
               FROM {" . self::REVIEWS_TABLE . "}
              WHERE hidden = 0 AND courseid {$insql}",
            $params
        );

        return [
            'reviewcount' => (int)($summary->reviewcount ?? 0),
            'avgrating' => $summary && $summary->reviewcount ? round((float)$summary->avgrating, 2) : 0.0,
        ];
    }

    /**
     * Get reviews for a course.
     *
     * @param int $courseid Course ID.
     * @param int $limitfrom Offset.
     * @param int $limitnum Limit.
     * @param bool $includehidden Include hidden reviews.
     * @return array Review records.
     */
    public static function get_course_reviews(
        int $courseid,
        int $limitfrom = 0,
        int $limitnum = 20,
        bool $includehidden = false
    ): array {
        global $DB;

        if (!self::reviews_enabled() && !$includehidden) {
            return [];
        }

        $conditions = ['courseid' => $courseid];
        if (!$includehidden) {
            $conditions['hidden'] = 0;
        }

        $reviews = $DB->get_records(self::REVIEWS_TABLE, $conditions, 'timecreated DESC', '*', $limitfrom, $limitnum);

        return array_values($reviews);
    }

    /**
     * Toggle a reaction for a review by a user.
     *
     * @param int $reviewid Review ID.
     * @param int $userid User ID.
     * @param int $reaction 1=like, 2=dislike, 3=love.
     */
    public static function set_reaction(int $reviewid, int $userid, int $reaction): void {
        global $DB;

        if (!in_array($reaction, [1, 2, 3], true)) {
            throw new \coding_exception('Invalid reaction value');
        }

        $now = time();
        $existing = $DB->get_record(self::REACTIONS_TABLE, [
            'reviewid' => $reviewid,
            'userid' => $userid,
        ]);

        if ($existing) {
            if ((int)$existing->reaction === $reaction) {
                $DB->delete_records(self::REACTIONS_TABLE, ['id' => $existing->id]);
                return;
            }

            $existing->reaction = $reaction;
            $existing->timecreated = $now;
            $DB->update_record(self::REACTIONS_TABLE, $existing);
            return;
        }

        $record = (object) [
            'reviewid' => $reviewid,
            'userid' => $userid,
            'reaction' => $reaction,
            'timecreated' => $now,
        ];
        $DB->insert_record(self::REACTIONS_TABLE, $record);
    }

    /**
     * Get reaction counts for a review.
     *
     * @param int $reviewid Review ID.
     * @return array Reaction counts keyed by reaction type.
     */
    public static function get_reaction_counts(int $reviewid): array {
        global $DB;

        $sql = "SELECT reaction, COUNT(1) AS cnt
                  FROM {" . self::REACTIONS_TABLE . "}
                 WHERE reviewid = :reviewid
              GROUP BY reaction";
        $records = $DB->get_records_sql($sql, ['reviewid' => $reviewid]);

        $result = [1 => 0, 2 => 0, 3 => 0];
        foreach ($records as $rec) {
            $key = (int)$rec->reaction;
            $result[$key] = (int)$rec->cnt;
        }

        return $result;
    }

    /**
     * Check whether a user ID is the guest user.
     *
     * @param int $userid User ID.
     * @return bool True for the guest account.
     */
    private static function is_guest_user(int $userid): bool {
        $guest = guest_user();
        return $guest && (int)$guest->id === $userid;
    }
}
