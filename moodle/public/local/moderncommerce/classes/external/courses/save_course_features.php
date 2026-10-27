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
 * External API saving course merchandising metadata.
 *
 * @package    local_moderncommerce
 * @copyright  2025 Adebare Showemimo | adebareshowemimo@gmail.com | support@agunfoninteractivity.com | www.agunfoninteractivity.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moderncommerce\external\courses;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_moderncommerce\api\pricing_api;

/**
 * Save a course's advanced/merchandising metadata, objectives, and outline.
 */
class save_course_features extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID.'),
            'durationhours' => new external_value(PARAM_INT, 'Duration hours.', VALUE_DEFAULT, 0),
            'durationminutes' => new external_value(PARAM_INT, 'Duration minutes.', VALUE_DEFAULT, 0),
            'skilllevel' => new external_value(PARAM_TEXT, 'Skill level.', VALUE_DEFAULT, ''),
            'language' => new external_value(PARAM_TEXT, 'Language.', VALUE_DEFAULT, ''),
            'passgrade' => new external_value(PARAM_FLOAT, 'Pass grade (0-100, -1 = unset).', VALUE_DEFAULT, -1),
            'certenabled' => new external_value(PARAM_BOOL, 'Certificate enabled.', VALUE_DEFAULT, false),
            'overviewauto' => new external_value(PARAM_BOOL, 'Auto-generate overview.', VALUE_DEFAULT, true),
            'overviewtext' => new external_value(PARAM_RAW, 'Overview text.', VALUE_DEFAULT, ''),
            'featured' => new external_value(PARAM_BOOL, 'Featured badge.', VALUE_DEFAULT, false),
            'bestseller' => new external_value(PARAM_BOOL, 'Bestseller badge.', VALUE_DEFAULT, false),
            'trending' => new external_value(PARAM_BOOL, 'Trending badge.', VALUE_DEFAULT, false),
            'price' => new external_value(PARAM_FLOAT, 'Regular price (-1 = leave unchanged).', VALUE_DEFAULT, -1),
            'saleprice' => new external_value(PARAM_FLOAT, 'Sale price (0 = none).', VALUE_DEFAULT, 0),
            'objectives' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Objective text.'),
                'Learning objectives.',
                VALUE_DEFAULT,
                []
            ),
            'outline' => new external_multiple_structure(
                new external_single_structure([
                    'title' => new external_value(PARAM_TEXT, 'Section title.'),
                    'time' => new external_value(PARAM_TEXT, 'Estimated time.', VALUE_DEFAULT, ''),
                ]),
                'Course outline rows.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $courseid Course ID.
     * @param int $durationhours Hours.
     * @param int $durationminutes Minutes.
     * @param string $skilllevel Skill level.
     * @param string $language Language.
     * @param float $passgrade Pass grade.
     * @param bool $certenabled Certificate enabled.
     * @param bool $overviewauto Overview auto.
     * @param string $overviewtext Overview text.
     * @param bool $featured Featured.
     * @param bool $bestseller Bestseller.
     * @param bool $trending Trending.
     * @param float $price Price.
     * @param float $saleprice Sale price.
     * @param array $objectives Objectives.
     * @param array $outline Outline.
     * @return array
     */
    public static function execute(
        int $courseid,
        int $durationhours = 0,
        int $durationminutes = 0,
        string $skilllevel = '',
        string $language = '',
        float $passgrade = -1,
        bool $certenabled = false,
        bool $overviewauto = true,
        string $overviewtext = '',
        bool $featured = false,
        bool $bestseller = false,
        bool $trending = false,
        float $price = -1,
        float $saleprice = 0,
        array $objectives = [],
        array $outline = []
    ): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'durationhours' => $durationhours,
            'durationminutes' => $durationminutes,
            'skilllevel' => $skilllevel,
            'language' => $language,
            'passgrade' => $passgrade,
            'certenabled' => $certenabled,
            'overviewauto' => $overviewauto,
            'overviewtext' => $overviewtext,
            'featured' => $featured,
            'bestseller' => $bestseller,
            'trending' => $trending,
            'price' => $price,
            'saleprice' => $saleprice,
            'objectives' => $objectives,
            'outline' => $outline,
        ]);

        require_login();
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/moderncommerce:managecourses', $context);

        $course = $DB->get_record('course', ['id' => $params['courseid']], 'id', MUST_EXIST);
        if ((int) $course->id === (int) SITEID) {
            throw new \moodle_exception('error:invalidcourse', 'local_moderncommerce');
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        try {
            $record = (object) [
                'courseid' => (int) $course->id,
                'durationminutes' => (max(0, $params['durationhours']) * 60) + max(0, $params['durationminutes']),
                'skilllevel' => $params['skilllevel'] !== '' ? $params['skilllevel'] : null,
                'language' => $params['language'] !== '' ? $params['language'] : null,
                'passgrade' => self::clamp_grade($params['passgrade']),
                'certificateenabled' => $params['certenabled'] ? 1 : 0,
                'overview' => $params['overviewauto'] ? null : ($params['overviewtext'] !== '' ? $params['overviewtext'] : null),
                'featured' => $params['featured'] ? 1 : 0,
                'bestseller' => $params['bestseller'] ? 1 : 0,
                'trending' => $params['trending'] ? 1 : 0,
                'usermodified' => $USER->id,
                'timemodified' => $now,
            ];

            $existing = $DB->get_record('local_moderncommerce_course_meta', ['courseid' => $course->id]);
            if ($existing) {
                $record->id = $existing->id;
                $record->timecreated = $existing->timecreated;
                $DB->update_record('local_moderncommerce_course_meta', $record);
            } else {
                $record->timecreated = $now;
                $DB->insert_record('local_moderncommerce_course_meta', $record);
            }

            // Objectives: replace by reinsert.
            $DB->delete_records('local_moderncommerce_course_objectives', ['courseid' => $course->id]);
            $sort = 1;
            foreach ($params['objectives'] as $text) {
                $text = trim((string) $text);
                if ($text === '') {
                    continue;
                }
                $DB->insert_record('local_moderncommerce_course_objectives', (object) [
                    'courseid' => (int) $course->id,
                    'sortorder' => $sort++,
                    'objective' => $text,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }

            // Outline: replace by reinsert.
            $DB->delete_records('local_moderncommerce_course_outline', ['courseid' => $course->id]);
            $sort = 1;
            foreach ($params['outline'] as $row) {
                $title = trim((string) ($row['title'] ?? ''));
                $time = trim((string) ($row['time'] ?? ''));
                if ($title === '' && $time === '') {
                    continue;
                }
                $DB->insert_record('local_moderncommerce_course_outline', (object) [
                    'courseid' => (int) $course->id,
                    'sortorder' => $sort++,
                    'sectiontitle' => $title,
                    'estimatedtime' => $time !== '' ? $time : null,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }

            // Pricing, only when a regular price is supplied.
            if ($params['price'] >= 0) {
                $regular = (float) $params['price'];
                $sale = $params['saleprice'] > 0 && $params['saleprice'] < $regular ? (float) $params['saleprice'] : null;
                pricing_api::set_course_pricing((int) $course->id, [
                    'price' => $regular,
                    'saleprice' => $sale,
                    'enabled' => true,
                    'featured' => $params['featured'],
                    'taxable' => true,
                ]);
            }

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return ['success' => false, 'message' => $e->getMessage(), 'warnings' => []];
        }

        return [
            'success' => true,
            'message' => get_string('changessaved'),
            'warnings' => [],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the metadata was saved.'),
            'message' => new external_value(PARAM_TEXT, 'Result message.'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Clamp a pass grade to 0-100, or null when unset (-1).
     *
     * @param float $grade Submitted grade.
     * @return float|null
     */
    private static function clamp_grade(float $grade): ?float {
        if ($grade < 0) {
            return null;
        }

        return round(min(100, $grade), 2);
    }
}
